<?php
/**
 * Manipulate table and column constraints
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_constraint {

  public static function foreign_key_lookup($db_doc, $node_schema, $node_table, $column, $visited = array()) {
    $foreign = array();

    $fschema = strlen($column['foreignSchema']) == 0 ? (string)$node_schema['name'] : (string)$column['foreignSchema'];
    $foreign['schema'] = dbx::get_schema($db_doc, $fschema);
    if ( ! $foreign['schema'] ) {
      throw new Exception("Failed to find foreign schema '{$column['foreignSchema']}' for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
    }

    $foreign['table'] = dbx::get_table($foreign['schema'], $column['foreignTable']);
    if ( ! $foreign['table'] ) {
      throw new Exception("Failed to find foreign table '{$column['foreignTable']}' for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
    }

    // if foreignColumn is not set
    // the column is assumed to be the same name as the referring column
    if ( ! empty($column['foreignColumn']) ) {
      $foreignColumn = $column['foreignColumn'];
    }
    else {
      $foreignColumn = $column['name'];
    }

    $foreign['column'] = self::resolve_foreign_column($db_doc,
      $node_schema, $node_table, (string)$column['name'],
      $foreign['schema'], $foreign['table'], $foreignColumn, $visited);

    $table = $foreign['table'];
    $schema = $foreign['schema'];

    $foreign['name'] = format_index::index_name($node_table['name'], $column['name'], 'fkey');
    $foreign['references'] = static::get_foreign_key_reference_sql($foreign);

    return $foreign;
  }

  public static function foreign_key_lookup_compound($db_doc, $node_schema, $node_table, $node_fkey) {
    $lschema_name = (string)$node_schema['name'];
    $ltable_name = (string)$node_table['name'];

    $lcol_names = (string)$node_fkey['columns'];
    $lcol_names = preg_split('/[\s,]+/', $lcol_names, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($lcol_names)) {
      throw new Exception("Columns list on foreignKey on table $lschema_name.$ltable_name is empty or missing");
    }

    // fall back to local column names of foreign columns aren't defined
    $fcol_names = (string)$node_fkey['foreignColumns'];
    if (strlen($fcol_names)) {
      $fcol_names = preg_split('/[\s,]+/', $fcol_names, -1, PREG_SPLIT_NO_EMPTY);
    } else {
      $fcol_names = $lcol_names;
    }

    $index_name = strlen($node_fkey['indexName']) ? (string)$node_fkey['indexName'] : format_index::index_name($ltable_name, $lcol_names[0], 'fkey');

    if (($f=count($fcol_names)) !== ($l=count($lcol_names))) {
      throw new Exception("Column mismatch on foreignKey $lschema_name.$ltable_name.$index_name: $l local columns, $f foreign columns");
    }

    // fall back to current schema name if not explicit
    $fschema_name = strlen($node_fkey['foreignSchema']) ? (string)$node_fkey['foreignSchema'] : (string)$node_schema['name'];
    $fschema = dbx::get_schema($db_doc, $fschema_name);
    if (!$fschema) {
      throw new Exception("Failed to find foreign schema '$fschema_name' for foreignKey $lschema_name.$ltable_name.$index_name");
    }

    $ftable_name = (string)$node_fkey['foreignTable'];
    if (!strlen($ftable_name)) {
      throw new Exception("foreignTable attribute is required on foreignKey $lschema_name.$ltable_name.$index_name");
    }
    $ftable = dbx::get_table($fschema, $ftable_name);
    if (!$ftable) {
      throw new Exception("Failed to find foreign table '$ftable_name' for foreignKey $lschema_name.$ltable_name.$index_name");
    }

    $fcols = array();
    foreach ($fcol_names as $i => $fcol_name) {
      $fcols[] = self::resolve_foreign_column($db_doc,
        $node_schema, $node_table, $lcol_names[$i],
        $fschema, $ftable, $fcol_name);
    }

    $quoted_fcols = implode(', ', array_map('format::get_quoted_column_name', $fcol_names));

    return array(
      'schema' => $fschema,
      'table' => $ftable,
      'column' => $fcols,
      'name' => $index_name,
      'references' => format::get_fully_qualified_table_name($fschema['name'], $ftable['name']) . " ($quoted_fcols)"
    );
  }

  /**
   * Attepts to find a column on a foreign table.
   * Walks up table inheritance chains.
   * If the foreign column is itself a foreign key, resolves the type of that column before returning.
   */
  private static function resolve_foreign_column($db_doc,
    $local_schema, $local_table, $local_colname,
    $foreign_schema, $foreign_table, $foreign_colname, $visited=array()) {

    // walk up the foreign table inheritance chain to find the foreign column definition
    $fschema = $foreign_schema;
    $ftable = $foreign_table;
    do {
      $foreign_column = dbx::get_table_column($ftable, $foreign_colname);
      if ($ftable['inheritsSchema']) {
        $fschema = dbx::get_schema($db_doc, (string)$ftable['inheritsSchema']);
      }

      if ($ftable['inheritsTable']) {
        $ftable = dbx::get_table($fschema, (string)$ftable['inheritsTable']);
      } else {
        $ftable = null;
      }
    } while (!$foreign_column && !!$fschema && !!$ftable);
    
    if (!$foreign_column) {
      // column wasn't found in any referenced tables
      throw new Exception("Local column {$local_schema['name']}.{$local_table['name']}.$local_colname references unknown column {$foreign_schema['name']}.{$foreign_table['name']}.$foreign_colname");
    }

    // column type is missing, and resolved foreign is also a foreign key?
    // recurse and find the cascading foreign key
    if ( empty($foreign_column['type']) && !empty($foreign_column['foreignTable']) ) {
      // make sure we don't visit the same column twice
      $foreign_col = format::get_fully_qualified_column_name($foreign_schema['name'], $foreign_table['name'], $foreign_column['name']);
      if ( in_array($foreign_col, $visited) ) {
        $local = format::get_fully_qualified_column_name($local_schema['name'], $local_table['name'], $local_colname);
        throw new Exception("Foreign key cyclic dependency detected! Local column $local pointing to foreign column $foreign_col");
      }
      $visited[] = $foreign_col;

      $nested_fkey = self::foreign_key_lookup($db_doc, $foreign_schema, $foreign_table, $foreign_column, $visited);

      // make a separate clone of the column element because we are specifying the type only for foreign key type referencing
      $foreign_column = new SimpleXMLElement($foreign_column->asXML());
      $foreign_column['type'] = (string)$nested_fkey['column']['type'];
    }

    return $foreign_column;
  }

  public static function get_foreign_key_reference_sql($foreign) {
    return format::get_fully_qualified_table_name($foreign['schema']['name'], $foreign['table']['name']) . ' (' . format::get_quoted_column_name($foreign['column']['name']) . ')';
  }
  
  /**
   * @NOTE: because this gets the defintion from the composite list returned by get_table_constraints
   * the constraint is not returned by reference as it is not modifiable like other get functions in this class
   * when saving changes to constraints, need to lookup the child where they would come from explicitly
   */
  public static function get_table_constraint($db_doc, $node_schema, $node_table, $name) {
    $constraints = self::get_table_constraints($db_doc, $node_schema, $node_table, 'all');
    $return_constraint = NULL;
    foreach ($constraints AS $constraint) {
      if (strcasecmp($constraint['name'], $name) == 0) {
        if ($return_constraint == NULL) {
          $return_constraint = $constraint;
        }
        else {
          var_dump($constraints);
          throw new exception("more than one table " . $node_schema['name'] . '.' . $node_table['name'] . " constraint called " . $name . " found");
        }
      }
    }
    return $return_constraint;
  }

  /**
   * return collection of arrays representing all of the constraints on a table
   * this is more than just the <constraint> discret children of a table element
   * this is also primary key, inline column foreign keys, and inline column unique constraints
   * everything comparing the constraints of a table should be calling this
   */
  public static function get_table_constraints($db_doc, $node_schema, $node_table, $type = 'all') {
    if ( !is_object($node_table) ) {
      var_dump($node_table);
      throw new Exception("node_table is not an object, check trace for bad table pointer");
    }
    switch ($type) {
      case 'all':
      case 'primaryKey':
      case 'constraint':
      case 'foreignKey':
      break;
      default:
        throw new Exception("unknown type " . $type . " encountered");
    }
    $constraints = array();

    if ($type == 'all' || $type == 'primaryKey') {
      if (!empty($node_table['primaryKey'])) {
        $pk_name = static::get_primary_key_name($node_table);
        $pk_def = static::get_primary_key_definition($node_table);

        $constraints[] = array(
          'name' => $pk_name,
          'schema_name' => (string)$node_schema['name'],
          'table_name' => (string)$node_table['name'],
          'type' => 'PRIMARY KEY',
          'definition' => $pk_def
        );
      }
      else if ( !dbsteward::$ignore_primary_key_errors ) {
        throw new Exception("Table {$node_schema['name']}.{$node_table['name']} does not have a primaryKey!");
      }
    }

    if ( $type == 'all' || $type == 'constraint' || $type == 'foreignKey' ) {
      // look for constraints in <constraint> elements
      foreach ( $node_table->constraint AS $node_constraint ) {
        // sanity check node definition constraint types
        switch ( strtoupper((string)$node_constraint['type']) ) {
          case 'PRIMARY KEY':
            throw new Exception("Primary keys are not allowed to be defined in a <constraint>");
            break;

          default:
            throw new Exception('unknown constraint type ' . $node_constraint['type'] . ' encountered');
            break;

          case 'CHECK':
          case 'UNIQUE':
            // if we're ONLY looking for foreign keys, ignore everything else
            if ( $type == 'foreignKey' ) {
              continue;
            }
            // fallthru
          case 'FOREIGN KEY':
            $constraints[] = array(
              'name' => (string)$node_constraint['name'],
              'schema_name' => (string)$node_schema['name'],
              'table_name' => (string)$node_table['name'],
              'type' => strtoupper((string)$node_constraint['type']),
              'definition' => (string)$node_constraint['definition']
            );
            break;
        }
      }

      // look for explicit <foreignKey> elements
      foreach ($node_table->foreignKey as $node_fkey) {
        $foreign = self::foreign_key_lookup_compound($db_doc, $node_schema, $node_table, $node_fkey);
        $local_cols = preg_split('/[\s,]+/', $node_fkey['columns'], -1, PREG_SPLIT_NO_EMPTY);
        $quoted_cols = implode(', ', array_map('format::get_quoted_column_name', $local_cols));

        $constraint = array(
          'name' => (string)$node_fkey['constraintName'],
          'schema_name' => (string)$node_schema['name'],
          'table_name' => (string)$node_table['name'],
          'type' => 'FOREIGN KEY',
          'definition' => "($quoted_cols) REFERENCES {$foreign['references']}",
          'foreign_key_data' => $foreign
        );
        if (isset($node_fkey['onDelete'])) {
          $constraint['foreignOnDelete'] = (string)$node_fkey['onDelete'];
        }
        if (isset($node_fkey['onUpdate'])) {
          $constraint['foreignOnUpdate'] = (string)$node_fkey['onUpdate'];
        }
        if (isset($node_fkey['indexName'])) {
          $constraint['foreignIndexName'] = (string)$node_fkey['foreignIndexName'];
        }
        $constraints[] = $constraint;
      }

      // look for constraints in columns: foreign key and unique
      foreach ($node_table->column AS $column) {
        if ( isset($column['foreignTable']) ) {

          if ( empty($column['foreignTable']) ) {
            throw new Exception("Invalid foreignSchema|foreignTable pair for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
          }
          if ( ! empty($column['type']) ) {
            throw new exception("Foreign-Keyed columns should not specify a type for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
          }

          $foreign = static::foreign_key_lookup($db_doc, $node_schema, $node_table, $column);
          if ( ! empty($column['foreignKeyName']) > 0) {
            // explicitly name the foreign key if specified in the node
            $foreign['name'] = (string)$column['foreignKeyName'];
          }

          $column_fkey_constraint = array(
            'name' => (string)$foreign['name'],
            'schema_name' => (string)$node_schema['name'],
            'table_name' => (string)$node_table['name'],
            'type' => 'FOREIGN KEY',
            'definition' => '(' . dbsteward::quote_column_name($column['name']) . ') REFERENCES ' . $foreign['references'],
            'foreign_key_data' => $foreign
          );

          if ( ! empty($column['foreignIndexName']) ) {
            $column_fkey_constraint['foreignIndexName'] = (string)$column['foreignIndexName'];
          }

          if ( ! empty($column['foreignOnDelete']) ) {
            $column_fkey_constraint['foreignOnDelete'] = strtoupper((string)$column['foreignOnDelete']);
          }
          if ( ! empty($column['foreignOnUpdate']) ) {
            $column_fkey_constraint['foreignOnUpdate'] = strtoupper((string)$column['foreignOnUpdate']);
          }

          $constraints[] = $column_fkey_constraint;
        }
      }
    }
    
    if ($type == 'all' || $type == 'constraint' || $type == 'check' ) {
      foreach ($node_table->column AS $column) {
        // add column check constraints to the list
        if ( isset($column['check']) ) {
          $column_check_constraint = array(
            'name' => $column['name'] . '_check',
            'schema_name' => (string)$node_schema['name'],
            'table_name' => (string)$node_table['name'],
            'type' => 'CHECK',
            'definition' => $column['check']
          );
          $constraints[] = $column_check_constraint;
        }
      }
    }
    return $constraints;
  }

  public static function equals($constraint_a, $constraint_b) {
    if ( strcasecmp($constraint_a['name'], $constraint_b['name']) != 0 ) {
      return false;
    }
    $a_type = null;
    $b_type = null;
    if ( isset($constraint_a['type']) ) {
      $a_type = (string)$constraint_a['type'];
    }
    if ( isset($constraint_b['type']) ) {
      $b_type = (string)$constraint_b['type'];
    }
    if ( strcasecmp($a_type, $b_type) != 0 ) {
      return false;
    }

    $a_column_type = null;
    $b_column_type = null;
    if ( isset($constraint_a['foreign_key_data']) ) {
      $a_column_type = $constraint_a['foreign_key_data']['column']->attributes()['type'];
    }
    if ( isset($constraint_b['foreign_key_data']) ) {
      $b_column_type = $constraint_b['foreign_key_data']['column']->attributes()['type'];
    }

    if ( strcasecmp($a_column_type, $b_column_type) != 0 ) {
      return false;
    }

    $a_foreign_on_delete = null;
    $b_foreign_on_delete = null;
    if ( isset($constraint_a['foreignOnDelete']) ) {
      $a_foreign_on_delete = $constraint_a['foreignOnDelete'];
    }
    if ( isset($constraint_b['foreignOnDelete']) ) {
      $b_foreign_on_delete = $constraint_b['foreignOnDelete'];
    }
    if ( strcasecmp($a_foreign_on_delete, $b_foreign_on_delete) != 0 ) {
      return false;
    }

    $a_foreign_on_update = null;
    $b_foreign_on_update = null;
    if ( isset($constraint_a['foreignOnUpdate']) ) {
      $a_foreign_on_update = $constraint_a['foreignOnUpdate'];
    }
    if ( isset($constraint_b['foreignOnUpdate']) ) {
      $b_foreign_on_update = $constraint_b['foreignOnUpdate'];
    }
    if ( strcasecmp($a_foreign_on_update, $b_foreign_on_update) != 0 ) {
      return false;
    }

    $equals = strcasecmp($constraint_a['definition'], $constraint_b['definition']) == 0;

    return $equals;
  }

  /**
   * Split the primary key up into an array of columns
   *
   * @param string $primary_key_string The primary key string (e.g. "schema_name, table_name, column_name")
   * @return array The primary key(s) split into an array
   */
  public static function primary_key_split($primary_key_string) {
    return preg_split("/[\,\s]+/", $primary_key_string, -1, PREG_SPLIT_NO_EMPTY);
  }

  public static function get_primary_key_name($node_table) {
    if ( ! empty($node_table['primaryKeyName']) ) {
      return dbsteward::string_cast($node_table['primaryKeyName']);
    }
    else {
      return format_index::index_name($node_table['name'], NULL, 'pkey');
    }
  }

  public static function get_primary_key_definition($node_table) {
    return '(' . implode(', ', array_map('format::get_quoted_column_name', static::primary_key_split($node_table['primaryKey']))) . ')';
  }

  /**
   * Converts referential integrity options (NO_ACTION, RESTRICT, CASCADE, SET_NULL, SET_DEFAULT) to syntax-dependent SQL
   */
  public static function get_reference_option_sql($ref_opt) {
    return strtoupper($ref_opt);
  }
  
  public static function get_constraint_sql($constraint) {
    throw new exception("sql99 extender must implement get_constraint_sql()");
  }

  public static function get_constraint_drop_sql($constraint) {
    throw new exception("sql99 extender must implement get_constraint_drop_sql()");
  }
}
?>
