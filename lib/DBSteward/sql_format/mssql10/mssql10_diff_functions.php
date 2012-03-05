<?php
/**
 * Diffs functions.
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_diff_functions {

  /**
   * Outputs DDL for differences in functions
   *
   * @param $ofs1       stage1 output pointer
   * @param $ofs3       stage3 output pointer
   * @param $old_schema original schema
   * @param $new_schema new schema
   */
  public static function diff_functions($ofs1, $ofs3, $old_schema, $new_schema) {
    // drop functions that no longer exist in stage 2
    if ($old_schema != NULL) {
      foreach (dbx::get_functions($old_schema) as $old_function) {
        if (!mssql10_schema::contains_function($new_schema, mssql10_function::get_declaration($new_schema, $old_function))) {
          $ofs3->write(mssql10_function::get_drop_sql($old_schema, $old_function) . "\n");
        }
      }
    }

    // Add new functions and replace modified functions
    foreach (dbx::get_functions($new_schema) AS $new_function) {
      $old_function = NULL;
      if ($old_schema != NULL) {
        $old_function = dbx::get_function($old_schema, $new_function['name'], mssql10_function::get_declaration($new_schema, $new_function));
      }

      if ($old_function == NULL) {
        $ofs1->write(mssql10_function::get_creation_sql($new_schema, $new_function) . "\n");
      }
      else if (!mssql10_function::equals($new_schema, $new_function, $old_function, mssql10_diff::$ignore_function_whitespace)) {
        // functions are not equal, old_function is not null, it previously existed
        // for MSSQL, there is no CREATE OR REPLACE FUNCTION, so drop the function explicitly
        $ofs1->write(mssql10_function::get_drop_sql($old_schema, $old_function) . "\n");
        $ofs1->write(mssql10_function::get_creation_sql($new_schema, $new_function) . "\n");
      } else if ( isset($new_function['forceRedefine']) && strcasecmp($new_function['forceRedefine'], 'true') == 0 ) {
        $ofs1->write("-- DBSteward insists on function recreation: " . $new_schema['name'] . "." . $new_function['name'] . " has forceRedefine set to true\n");
        $ofs1->write(mssql10_function::get_creation_sql($new_schema, $new_function) . "\n");
      } else if (mssql10_schema::contains_type($new_schema, $new_function['returns'])
        && mssql10_schema::contains_type($old_schema, $new_function['returns'])
        && !mssql10_type::equals(dbx::get_type($old_schema, $new_function['returns']), dbx::get_type($new_schema, $new_function['returns']))) {
        $ofs1->write("-- dbstward insisting on function re-creation " . $new_function['name'] . " for type " . $new_function['returns'] . " definition change\n");
        $ofs1->write(mssql10_function::get_drop_sql($old_schema, $old_function) . "\n");
        $ofs1->write(mssql10_function::get_creation_sql($new_schema, $new_function) . "\n");
      }
    }
  }
}

?>
