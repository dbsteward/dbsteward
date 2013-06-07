<?php
/**
 * Manipulate table nodes
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_table extends sql99_table {
  
  public static $include_column_default_nextval_in_create_sql = TRUE;

  /**
   * Creates and returns SQL for creation of the table.
   *
   * @return created SQL command
   */
  public function get_creation_sql($node_schema, $node_table) {
    if ( $node_schema->getName() != 'schema' ) {
      throw new exception("node_schema object element name is not schema. check stack for offending caller");
    }

    if ( $node_table->getName() != 'table' ) {
      throw new exception("node_table object element name is not table. check stack for offending caller");
    }
    
    format::set_context_replica_set_id($node_table);

    $table_name = pgsql8::get_quoted_schema_name($node_schema['name']) . '.' . pgsql8::get_quoted_table_name($node_table['name']);

    $sql = "CREATE TABLE " . $table_name . " (\n";

    foreach(dbx::get_table_columns($node_table) as $column) {
      $sql .= "\t"
        . pgsql8_column::get_full_definition(dbsteward::$new_database, $node_schema, $node_table, $column, FALSE, TRUE, pgsql8_table::$include_column_default_nextval_in_create_sql)
        . ",\n";
    }

    $sql = trim($sql, ",\n");
    $sql .= "\n)";
    
    $opt_sql = pgsql8_table::get_table_options_sql($node_schema, $node_table);
    if (!empty($opt_sql)) {
      $sql .= "\n" . $opt_sql;
    }

    if (isset($node_table['inheritsTable']) && strlen($node_table['inheritsTable']) > 0) {
      if (!isset($node_table['inheritsSchema']) || strlen($node_table['inheritsSchema']) == 0) {
        throw new exception("Must provide both table and schema for inheritance for $table_name");
      }
      $sql .= "\nINHERITS (" . pgsql8::get_quoted_schema_name($node_table['inheritsSchema']) . '.' . pgsql8::get_quoted_table_name($node_table['inheritsTable']) . ')';
    }
    $sql .= ";";

    // table comment
    if (isset($node_table['description']) && strlen($node_table['description']) > 0) {
      $sql .= "\nCOMMENT ON TABLE " . $table_name . " IS '" . pg_escape_string(dbsteward::string_cast($node_table['description'])) . "';\n";
    }

    foreach(dbx::get_table_columns($node_table) as $column) {
      if ( isset($column['statistics']) ) {
        $sql .= "\nALTER TABLE ONLY "
          . $table_name
          . " ALTER COLUMN " . pgsql8::get_quoted_column_name($column['name'])
          . " SET STATISTICS " . $column['statistics'] . ";\n";
      }

      // column comments
      if ( isset($column['description']) && strlen($column['description']) > 0 ) {
        $sql .= "\nCOMMENT ON COLUMN " . $table_name . '.' . pgsql8::get_quoted_column_name($column['name'])
          . " IS '" . pg_escape_string(dbsteward::string_cast($column['description'])) . "';\n";
      }
    }

    // table ownership
    if (isset($node_table['owner']) && strlen($node_table['owner']) > 0) {
      // see dtd owner attribute enum: ROLE_OWNER, ROLE_APPLICATION, ROLE_SLONY
      // map ROLE_ enums to database->role->owner etc
      $owner = xml_parser::role_enum(dbsteward::$new_database, $node_table['owner']);
      $sql .= "\nALTER TABLE " . $table_name . " OWNER TO " . $owner . ";\n";

      // set serial columns ownership based on table ownership
      foreach($node_table->column AS $column ) {
        if ( preg_match(pgsql8::PATTERN_TABLE_LINKED_TYPES, dbsteward::string_cast($column['type'])) > 0 ) {
          $sequence_name = pgsql8::identifier_name($node_schema['name'], $node_table['name'], $column['name'], '_seq');
          // we use alter table so we change the ownership of the sequence tracking counter, alter sequence can't do this
          $sql .= sprintf("\nALTER TABLE %s.%s OWNER TO %s;\n",
            pgsql8::get_quoted_schema_name($node_schema['name']),
            pgsql8::get_quoted_object_name($sequence_name),
            $owner
          );
        }
      }
    }

    return $sql;
  }

  /**
   * Creates and returns SQL command for dropping the table.
   *
   * @return created SQL command
   */
  public function get_drop_sql($node_schema, $node_table) {
    if ( !is_object($node_schema) ) {
      var_dump($node_schema);
      throw new exception("node_schema is not an object");
    }
    if ( $node_schema->getName() != 'schema' ) {
      var_dump($node_schema);
      throw new exception("node_schema element type " . $node_schema->getName() . " != schema. check stack for offending caller");
    }
    if ( $node_table->getName() != 'table' ) {
      var_dump($node_schema);
      var_dump($node_table);
      throw new exception("node_table element type " . $node_table->getName() . " != table. check stack for offending caller");
    }
    format::set_context_replica_set_id($node_table);
    return "DROP TABLE " . pgsql8::get_quoted_schema_name($node_schema['name']) . '.' . pgsql8::get_quoted_table_name($node_table['name']) . ";";
  }

  /**
   * create SQL To create the constraint passed in the $constraint array
   *
   * @return string
   */
  public function get_constraint_sql($constraint) {
    if ( !is_array($constraint) ) {
      throw new exception("constraint is not an array?");
    }
    if ( strlen($constraint['table_name']) == 0 ) {
      var_dump(array_keys($constraint));
      throw new exception("table_name is blank");
    }
    $sql = "ALTER TABLE "
      . pgsql8::get_quoted_schema_name($constraint['schema_name']) . '.'
      . pgsql8::get_quoted_table_name($constraint['table_name']) . "\n"
      . static::get_constraint_sql_change_statement($constraint);

    $sql .= ';';
    return $sql;
  }

  public static function get_constraint_sql_change_statement($constraint) {
    $sql = "\tADD CONSTRAINT "
      . pgsql8::get_quoted_object_name($constraint['name']) . ' '
      . $constraint['type'] . ' '
      . $constraint['definition'] ;

   // FOREIGN KEY ON DELETE / ON UPDATE handling
    if ( isset($constraint['foreignOnDelete']) && strlen($constraint['foreignOnDelete']) ) {
      $sql .= " ON DELETE " . $constraint['foreignOnDelete'];
    }
    if ( isset($constraint['foreignOnUpdate']) && strlen($constraint['foreignOnUpdate']) ) {
      $sql .= " ON UPDATE " . $constraint['foreignOnUpdate'];
    }

    return $sql;
  }

  public static function get_constraint_drop_sql_change_statement($constraint) {
      return "\tDROP CONSTRAINT "
        . pgsql8::get_quoted_object_name($constraint['name']);
  }

  public function get_constraint_drop_sql($constraint) {
    if ( !is_array($constraint) ) {
      throw new exception("constraint is not an array?");
    }
    if ( strlen($constraint['schema_name']) == 0 ) {
      var_dump(array_keys($constraint));
      throw new exception("schema_name is blank");
    }
    if ( strlen($constraint['table_name']) == 0 ) {
      var_dump(array_keys($constraint));
      throw new exception("table_name is blank");
    }
    $sql = "ALTER TABLE "
      . pgsql8::get_quoted_schema_name($constraint['schema_name']) . '.'
      . pgsql8::get_quoted_table_name($constraint['table_name']) . "\n"
      . static::get_constraint_drop_sql_change_statement($constraint)
      . ';';
    return $sql;
  }
  
  public function has_default_nextval($node_table) {
    foreach(dbx::get_table_columns($node_table) as $column) {
      if ( pgsql8_column::has_default_nextval($node_table, $column) ) {
        return TRUE;
      }
    }
    return FALSE;
  }
  
  public function get_default_nextval_sql($node_schema, $node_table) {
    $sql = '';
    foreach(dbx::get_table_columns($node_table) as $column) {
      if ( pgsql8_column::has_default_nextval($node_table, $column) ) {
        if ( strlen($sql) > 0 ) {
          $sql .= "\n";
        }
        dbsteward::console_line(5, "Specifying skipped " . $column['name'] . " default expression \"" . $column['default'] . "\"");
        $sql .= "ALTER TABLE " .
          pgsql8::get_quoted_schema_name($node_schema['name']) . '.' .
          pgsql8::get_quoted_table_name($node_table['name']) .
          " ALTER COLUMN " . pgsql8::get_quoted_column_name($column['name']) . 
          " SET DEFAULT " . $column['default'] . "; -- column default nextval expression being added post table creation";
      }
    }
    return $sql;
  }

  public static function get_table_options($node_schema, $node_table=false) {
    $opts = parent::get_table_options($node_schema, $node_table);

    uksort($opts, function($a, $b) {
      if (strcasecmp($a,'tablespace') === 0) {
        return 1;
      }
      elseif (strcasecmp($b,'tablespace') === 0) {
        return -1;
      }
      else {
        return strcasecmp($a,$b);
      }
    });

    return $opts;
  }

  public static function parse_storage_params($param_string) {
    $params = array();
    foreach (explode(',',substr($param_string,1,-1)) as $param) {
      list($k,$v) = explode('=',$param,2);
      $params[$k] = $v;
    }
    return $params;
  }

  public static function compose_storage_params($params) {
    $strs = array();
    foreach ($params as $k=>$v) {
      $strs[] = $k.'='.$v;
    }
    return '('.implode(',', $strs).')';
  }

}

?>
