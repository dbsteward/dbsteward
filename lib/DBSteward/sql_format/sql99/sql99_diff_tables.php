<?php
/**
 * Diffs tables.
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class sql99_diff_tables {
  
  /**
   * Is the specified table in the specified schema renamed?
   * 
   * @param object $schema
   * @param object $table
   * return boolean
   */
  public static function is_renamed_table($schema, $table) {
    if ( !is_object($schema) ) {
      throw new exception("schema is not an object");
    }
    if ( !is_object($table) ) {
      throw new exception("table is not an object");
    }
    
    // command line switch sanity
    if ( dbsteward::$ignore_oldnames ) {
      throw new exception("dbsteward::ignore_oldname option is on, is_renamed_table() should not be getting called");
    }

    // if new_table['oldTableName'] is not defined, abort checks
    if ( ! isset($table['oldTableName']) ) {
      return false;
    }
    
    // definition sanity checks
    if ( sql99_schema::contains_table($schema, $table['oldTableName']) ) {
      throw new Exception("oldTableName panic - new_schema " . $schema['name'] . " still contains table named " . $table['oldTableName']);
    }

    $old_schema = sql99_table::get_old_table_schema($schema, $table);
    if (!is_null($old_schema)) {
      if ( !sql99_schema::contains_table($old_schema, $table['oldTableName']) ) {
        throw new Exception("oldTableName panic - old_schema " . $old_schema['name'] . " does not contain table named " . $table['oldTableName']);
      }
    }
    
    // it is a new old named table rename if:
    // table['oldTableName'] exists in old schema
    // table['oldTableName'] does not exist in new schema
    if ( sql99_schema::contains_table($old_schema, $table['oldTableName'])
        && !sql99_schema::contains_table($schema, $table['oldTableName']) ) {
      dbsteward::console_line(7, "NOTICE: " . $table['name'] . " used to be called " . $table['oldTableName']);
      return true;
    }

    return false;
  }
  
  
  /**
   * Does this table constrain against a renamed table?
   * 
   * @param object $db_doc
   * @param object $schema
   * @param object $table
   * return boolean
   */
  public static function constrains_against_renamed_table($db_doc, $schema, $table) {
    foreach(dbx::get_table_constraints($db_doc, $schema, $table, 'constraint') as $constraint) {
      if ( pgsql8_table::constraint_depends_on_renamed_table($db_doc, $constraint) ) {
        dbsteward::console_line(7, "NOTICE: " . $schema['name'] . "." . $table['name'] . " constrains against a renamed table with constraint " . $constraint['name']);
        return TRUE;
      }
    }
    return FALSE;
  }

  public static function is_renamed_column($old_table, $new_table, $new_column) {
    if ( !is_object($old_table) ) {
      throw new exception("old_table is not an object");
    }
    if ( !is_object($new_table) ) {
      throw new exception("new_table is not an object");
    }
    if ( !is_object($new_column) ) {
      throw new exception("new_column is not an object");
    }

    // command line switch sanity first and foremost
    if ( dbsteward::$ignore_oldnames ) {
      throw new exception("dbsteward::ignore_oldname option is on, is_renamed_column() should not be getting called");
    }

    $case_sensitive = FALSE;
    if ( dbsteward::$quote_column_names || dbsteward::$quote_all_names || strcasecmp('mysql5', dbsteward::get_sql_format()) == 0 ) {
      // do case-sensitive check
      $new_colname = (string)$new_column['name'];
      foreach ($old_table->column as $old_column) {
        $old_colname = (string)$old_column['name'];
        if (strcasecmp($old_colname, $new_colname) === 0) {
          if ($old_colname != $new_colname && !isset($new_column['oldColumnName'])) {
            throw new Exception("Ambiguous operation! It looks like column name case changed between old_column {$old_table['name']}.{$old_colname} and new_column {$new_table['name']}.{$new_colname}");
          }
          break;
        }
      }
      $case_sensitive = TRUE;
    }

    // if new_column['oldColumnName'] is not defined, abort checks
    if ( ! isset($new_column['oldColumnName']) ) {
      return false;
    }
    
    // definition sanity checks
    if ( sql99_table::contains_column($new_table, $new_column['oldColumnName'], $case_sensitive) ) {
      throw new Exception("oldColumnName panic - table " . $new_table['name'] . " still contains column named " . $new_column['oldColumnName']);
    }
    if ( !sql99_table::contains_column($old_table, $new_column['oldColumnName'], $case_sensitive) ) {
      throw new Exception("oldColumnName panic - table " . $old_table['name'] . " does not contain column named " . $new_column['oldColumnName']);
    }
    
    // it is a new old named table rename if:
    // new_column['oldColumnName'] exists in old schema
    // new_column['oldColumnName'] does not exist in new schema
    if ( sql99_table::contains_column($old_table, $new_column['oldColumnName'], $case_sensitive)
        && !sql99_table::contains_column($new_table, $new_column['oldColumnName'], $case_sensitive) ) {
      return true;
    }

    return false;
  }

  public static function table_option_changed($old_option, $new_option) {
    return strcmp($old_option['value'], $new_option['value']) !== 0;
  }

  public static function update_table_options($ofs1, $ofs3, $old_schema, $old_table, $new_schema, $new_table) {
    if (strcasecmp(dbsteward::get_sql_format(),'mssql10') === 0
      || strcasecmp(dbsteward::get_sql_format(),'oracle10g') === 0) {
      dbsteward::console_line(1, "mssql10 and oracle10g tableOptions are not implemented yet");
      return;
    }
    if ($new_schema && $new_table) {
      $alter_options = array();
      $create_options = array();
      $drop_options = array();

      $old_options = format_table::get_table_options($old_schema, $old_table);
      $new_options = format_table::get_table_options($new_schema, $new_table);

      // dropped options are those present in the old table, but not in the new
      $drop_options = array_diff_key($old_options, $new_options);

      // added options are those present in the new table but not in the old
      $create_options = array_diff_key($new_options, $old_options);

      // altered options are those present in both but with different values
      $alter_options = array_intersect_ukey($new_options, $old_options, function ($new_key, $old_key) use ($new_options, $old_options) {
        if ($new_key == $old_key && strcasecmp($new_options[$new_key], $old_options[$old_key]) !== 0) {
          return 0;
        }
        else {
          return -1;
        }
      });

      static::apply_table_options_diff($ofs1, $ofs3, $new_schema, $new_table, $alter_options, $create_options, $drop_options);
    }
  }
}
?>
