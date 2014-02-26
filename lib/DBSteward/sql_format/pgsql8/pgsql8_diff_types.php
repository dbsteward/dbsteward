<?php
/**
 * Difference type definitions
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_diff_types {
  
  /**
   * Drop removed types
   * Add new types
   * Apply type definition differences, updating the type's tables along the way
   *
   * @param $ofs          output segementer
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  public static function apply_changes($ofs, $old_schema, $new_schema) {
    // drop any types that are no longer defined
    self::drop_types($ofs, $old_schema, $new_schema);
    
    // create any types that are new in the new definition
    self::create_types($ofs, $old_schema, $new_schema);

    // there is no alter for types
    // find types that still exist that are different
    // placehold type data in table columns, and recreate the type
    foreach (dbx::get_types($new_schema) AS $new_type) {
      // does type exist in old definition ?
      if (($old_schema == NULL) || !pgsql8_schema::contains_type($old_schema, $new_type['name'])) {
        continue;
      }

      $old_type = dbx::get_type($old_schema, $new_type['name']);
      
      // is there a difference between the old and new type definitions?
      if ( pgsql8_type::equals($old_schema, $old_type, $new_schema, $new_type) ) {
        continue;
      }
      
      pgsql8::set_context_replica_set_id($new_type);
      
      $columns = array();
      $is_domain = strcasecmp($new_type['type'], 'domain') === 0;
      $n = $is_domain ? 3 : 4;
      $word = $is_domain ? 'domain' : 'type';
      
      $ofs->write("-- $word {$new_type['name']} definition migration (1/$n): dependent tables column type alteration\n");
      $ofs->write(pgsql8_type::alter_column_type_placeholder($columns, $old_schema, $old_type) . "\n");

      if ($is_domain) {
        $ofs->write("-- $word {$new_type['name']} definition migration (2/$n): alter domain\n");
        self::apply_domain_changes($ofs, $old_schema, $old_type, $new_schema, $new_type);
      }
      else {
        $ofs->write("-- $word {$new_type['name']} definition migration (2/$n): drop old type\n");
        $ofs->write(pgsql8_type::get_drop_sql($old_schema, $old_type) . "\n\n");
        
        $ofs->write("-- $word {$new_type['name']} definition migration (3/$n): recreate type with new definition\n");
        $ofs->write(pgsql8_type::get_creation_sql($new_schema, $new_type) . "\n\n");
      }
      
      $ofs->write("-- $word {$new_type['name']} definition migration ($n/$n): dependent tables type restoration\n");
      $ofs->write(pgsql8_type::alter_column_type_restore($columns, $new_schema, $new_type) . "\n");
    }
  }

  /**
   * Outputs commands for ALTER DOMAIN
   * @param $ofs          output file pointer
   * @param $old_schema   original schema
   * @param $old_type     original type
   * @param $new_schema   new schema
   * @param $new_type     new type
   */
  private static function apply_domain_changes($ofs, $old_schema, $old_type, $new_schema, $new_type) {
    // http://www.postgresql.org/docs/8.1/static/sql-alterdomain.html
    
    $domain = pgsql8::get_quoted_schema_name($new_schema['name']) . '.' . pgsql8::get_quoted_object_name($new_type['name']);

    $old_domain = $old_type->domainType;
    $new_domain = $new_type->domainType;

    // if base type changes, we need to drop and re-add
    if (strcasecmp($old_domain['baseType'], $new_domain['baseType']) !== 0) {
      $ofs->write("-- domain base type changed from {$old_domain['baseType']} to {$new_domain['baseType']} - recreating\n");
      $ofs->write(pgsql8_type::get_drop_sql($old_schema, $old_type) . "\n");
      $ofs->write(pgsql8_type::get_creation_sql($new_schema, $new_type) . "\n");
      return;
    }

    $base_type = strtolower($new_domain['baseType']);

    // default is dropped
    if (isset($old_domain['default']) && !isset($new_domain['default'])) {
      $ofs->write("-- domain default dropped\n");
      $ofs->write("ALTER DOMAIN $domain DROP DEFAULT;\n");
    }
    // default is changed
    elseif (strcmp($old_domain['default'], $new_domain['default']) !== 0) {
      $old_default = pgsql8::value_escape($base_type, (string)$old_domain['default']);
      $new_default = pgsql8::value_escape($base_type, (string)$new_domain['default']);
      $ofs->write("-- domain default changed from $old_default\n");
      $ofs->write("ALTER DOMAIN $domain SET DEFAULT $new_default;\n");
    }

    $old_null = strcasecmp($old_domain['null'], 'false') !== 0;
    $new_null = strcasecmp($new_domain['null'], 'false') !== 0;

    // NULL -> NOT NULL
    if ($old_null && !$new_null) {
      $ofs->write("-- domain changed from NULL to NOT NULL\n");
      $ofs->write("ALTER DOMAIN $domain SET NOT NULL;\n");
    }
    // NOT NULL -> NULL
    elseif (!$old_null && $new_null) {
      $ofs->write("-- domain changed from NOT NULL to NULL\n");
      $ofs->write("ALTER DOMAIN $domain DROP NOT NULL;\n");
    }

    // diff constraints
    $old_constraints = array();
    foreach ($old_type->domainConstraint as $old_constraint) {
      $old_constraints[(string)$old_constraint['name']] = pgsql8_type::normalize_domain_constraint($old_constraint);
    }
    foreach ($new_type->domainConstraint as $new_constraint) {
      $name = (string)$new_constraint['name'];
      $constraint = pgsql8_type::normalize_domain_constraint($new_constraint);

      if (array_key_exists($name, $old_constraints)) {
        if (strcmp($constraint, $old_constraints[$name]) !== 0) {
          $ofs->write("-- domain constraint $name changed from {$old_constraints[$name]}\n");
          $ofs->write("ALTER DOMAIN $domain DROP CONSTRAINT $name;\n");
          $ofs->write("ALTER DOMAIN $domain ADD CONSTRAINT $name CHECK($constraint);\n");
        }
        unset($old_constraints[$name]);
      }
      else {
        $ofs->write("-- domain constraint $name added\n");
        $ofs->write("ALTER DOMAIN $domain ADD CONSTRAINT $name CHECK($constraint);\n");
      }
    }
    foreach ($old_constraints as $name => $constraint) {
      $ofs->write("-- domain constraint $name removed\n");
      $ofs->write("ALTER DOMAIN $domain DROP CONSTRAINT $name;\n");
    }
  }

  /**
   * Outputs commands for creation of new types in a schema
   *
   * @param $ofs          output file pointer
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  private static function create_types($ofs, $old_schema, $new_schema) {
    foreach(dbx::get_types($new_schema) AS $type) {
      if ( ($old_schema == NULL) || !pgsql8_schema::contains_type($old_schema, $type['name']) ) {
        pgsql8::set_context_replica_set_id($type);
        $ofs->write(pgsql8_type::get_creation_sql($new_schema, $type) . "\n");
      }
    }
  }

  /**
   * Outputs commands for dropping types.
   *
   * @param $ofs          output file pointer
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  private static function drop_types($ofs, $old_schema, $new_schema) {
    if ($old_schema != NULL) {
      foreach(dbx::get_types($old_schema) AS $type) {
        if ( !pgsql8_schema::contains_type($new_schema, $type['name'])) {
          pgsql8::set_context_replica_set_id($type);
          $ofs->write(pgsql8_type::get_drop_sql($new_schema, $type) . "\n");
        }
      }
    }
  }
}

?>
