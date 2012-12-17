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
  
  public static function is_renamed_table($old_schema, $new_schema, $new_table) {
    // command line switch sanity first and foremost
    if ( dbsteward::$ignore_oldname ) {
      throw new exception("dbsteward::ignore_oldname option is on, is_renamed_table() should not be getting called");
    }

    // if new_table['oldName'] is not defined, abort checks
    if ( ! isset($new_table['oldName']) ) {
      return false;
    }
    
    // definition sanity checks
    if ( sql99_schema::contains_table($new_schema, $new_table['oldName']) ) {
      throw new Exception("table oldName panic - schema " . $new_schema['name'] . " still contains table named " . $new_table['oldName']);
    }
    if (!is_null($old_schema)) {
      if ( !sql99_schema::contains_table($old_schema, $new_table['oldName']) ) {
        throw new Exception("table oldName panic - schema " . $old_schema['name'] . " does not contain table named " . $new_table['oldName']);
      }
    }
    
    // it is a new old named table rename if:
    // new_table['oldName'] exists in old schema
    // new_table['oldName'] does not exist in new schema
    if ( sql99_schema::contains_table($old_schema, $new_table['oldName'])
        && !sql99_schema::contains_table($new_schema, $new_table['oldName']) ) {
      return true;
    }

    return false;
  }

  public static function is_renamed_column($old_table, $new_table, $new_column) {
    // command line switch sanity first and foremost
    if ( dbsteward::$ignore_oldname ) {
      throw new exception("dbsteward::ignore_oldname option is on, is_renamed_column() should not be getting called");
    }

    // if new_column['oldName'] is not defined, abort checks
    if ( ! isset($new_column['oldName']) ) {
      return false;
    }
    
    // definition sanity checks
    if ( sql99_table::contains_column($new_table, $new_column['oldName']) ) {
      throw new Exception("column oldName panic - table " . $new_table['name'] . " still contains column named " . $new_column['oldName']);
    }
    if ( !sql99_table::contains_column($old_table, $new_column['oldName']) ) {
      throw new Exception("column oldName panic - table " . $old_table['name'] . " does not contain column named " . $new_column['oldName']);
    }
    
    // it is a new old named table rename if:
    // new_column['oldName'] exists in old schema
    // new_column['oldName'] does not exist in new schema
    if ( sql99_table::contains_column($old_table, $new_column['oldName'])
        && !sql99_table::contains_column($new_table, $new_column['oldName']) ) {
      return true;
    }

    return false;
  }
  
  public static function table_was_renamed($node_schema, $table_name) {
    return sql99_schema::table_name_by_old_name($node_schema, $table_name) !== false;
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
      // look for changed or dropped options
      foreach ($old_table->tableOption as $old_option) {
        if (($new_option = format_table::contains_table_option($new_table, $old_option['name']))) {
          // alter the option. note that options and how they're applied vary wildly between formats
          if (static::table_option_changed($old_option, $new_option)) {
            $alter_options[] =$new_option;
          }
        }
        else {
          // drop the option
         $drop_options[] = $old_option;
        }
      }

      // look for added options
      foreach ($new_table->tableOption as $new_option) {
        if (!format_table::contains_table_option($old_table, $new_option['name'])) {
          // add the option
          $create_options[] = $new_option;
        }
      }

      static::apply_table_options_diff($ofs1, $ofs3, $new_schema, $new_table, $alter_options, $create_options, $drop_options);
    }
  }
}
?>
