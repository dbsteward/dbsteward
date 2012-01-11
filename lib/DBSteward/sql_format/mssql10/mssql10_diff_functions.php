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
   * Outputs commands for differences in functions.
   *
   * @param $fp1 stage1 output pointer
   * @param $fp2 stage2 output pointer
   * @param old_schema original schema
   * @param new_schema new schema
   */
  public static function diff_functions($fp1, $fp2, $old_schema, $new_schema) {
    // drop functions that no longer exist in stage 2
    if ($old_schema != NULL) {
      foreach (dbx::get_functions($old_schema) as $old_function) {
        if (!mssql10_schema::contains_function($new_schema, mssql10_function::get_declaration($new_schema, $old_function))) {
          fwrite($fp2, mssql10_function::get_drop_sql($old_schema, $old_function) . "\n");
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
        fwrite($fp1, mssql10_function::get_creation_sql($new_schema, $new_function) . "\n");
      }
      else if (!mssql10_function::equals($new_schema, $new_function, $old_function, mssql10_diff::$ignore_function_whitespace)) {
        // functions are not equal, old_function is not null, it previously existed
        // for MSSQL, there is no CREATE OR REPLACE FUNCTION, so drop the function explicitly
        fwrite($fp1, mssql10_function::get_drop_sql($old_schema, $old_function) . "\n");
        fwrite($fp1, mssql10_function::get_creation_sql($new_schema, $new_function) . "\n");
      } else if ( isset($new_function['forceRedefine']) && strcasecmp($new_function['forceRedefine'], 'true') == 0 ) {
        fwrite($fp1, "-- DBSteward insists on function recreation: " . $new_schema['name'] . "." . $new_function['name'] . " has forceRedefine set to true\n");
        fwrite($fp1, mssql10_function::get_creation_sql($new_schema, $new_function) . "\n");
      } else if (mssql10_schema::contains_type($new_schema, $new_function['returns'])
        && mssql10_schema::contains_type($old_schema, $new_function['returns'])
        && !mssql10_type::equals(dbx::get_type($old_schema, $new_function['returns']), dbx::get_type($new_schema, $new_function['returns']))) {
        fwrite($fp1, "-- dbstward insisting on function re-creation " . $new_function['name'] . " for type " . $new_function['returns'] . " definition change\n");
        fwrite($fp1, mssql10_function::get_drop_sql($old_schema, $old_function) . "\n");
        fwrite($fp1, mssql10_function::get_creation_sql($new_schema, $new_function) . "\n");
      }
    }
  }
}

?>
