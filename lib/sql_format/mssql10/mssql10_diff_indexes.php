<?php
/**
 * Diffs indexes.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: mssql10_diff_indexes.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class mssql10_diff_indexes extends pgsql8_diff_indexes {

  public static function diff_indexes_table($fp, $old_schema, $old_table, $new_schema, $new_table) {
    // Drop indexes that do not exist in new schema or are modified
    foreach (self::get_drop_indexes($old_schema, $old_table, $new_schema, $new_table) as $index) {
      fwrite($fp, mssql10_index::get_drop_sql($new_schema, $new_table, $index));
    }

    // Add new indexes
    if ($old_schema == NULL) {
      foreach (dbx::get_table_indexes($new_schema, $new_table) as $index) {
        fwrite($fp, mssql10_index::get_creation_sql($new_schema, $new_table, $index) . "\n");
      }
    }
    else {
      foreach (self::get_new_indexes($old_schema, $old_table, $new_schema, $new_table) as $index) {
        fwrite($fp, mssql10_index::get_creation_sql($new_schema, $new_table, $index) . "\n");
      }
    }
  }
}

?>
