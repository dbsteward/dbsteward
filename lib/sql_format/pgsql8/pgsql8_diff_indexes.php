<?php
/**
 * Diffs indexes.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_diff_indexes.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_diff_indexes {

  /**
   * Outputs commands for differences in indexes.
   *
   * @param fp output file pointer
   * @param old_schema original schema
   * @param new_schema new schema
   */
  public static function diff_indexes($fp, $old_schema, $new_schema) {
    foreach(dbx::get_tables($new_schema) as $new_table) {
      if ($old_schema == null) {
        $old_table = null;
      } else {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }
      self::diff_indexes_table($fp, $old_schema, $old_table, $new_schema, $new_table);
    }
  }

  public static function diff_indexes_table($fp, $old_schema, $old_table, $new_schema, $new_table) {
    // Drop indexes that do not exist in new schema or are modified
    foreach(self::get_drop_indexes($old_schema, $old_table, $new_schema, $new_table) as $index) {
      fwrite($fp, pgsql8_index::get_drop_sql($new_schema, $new_table, $index));
    }

    // Add new indexes
    if ($old_schema == null) {
      foreach(dbx::get_table_indexes($new_schema, $new_table) as $index) {
        fwrite($fp, pgsql8_index::get_creation_sql($new_schema, $new_table, $index) . "\n");
      }
    } else {
      foreach(self::get_new_indexes($old_schema, $old_table, $new_schema, $new_table) as $index) {
        fwrite($fp, pgsql8_index::get_creation_sql($new_schema, $new_table, $index) . "\n");
      }
    }
  }

  /**
   * Returns list of indexes that should be dropped.
   *
   * @param old_table original table
   * @param new_table new table
   *
   * @return list of indexes that should be dropped
   *
   * @todo Indexes that are depending on a removed field should not be added
   *       to drop because they are already removed.
   */
  public static function get_drop_indexes($old_schema, $old_table, $new_schema, $new_table) {
    $list = array();

    if (($new_table != null) && ($old_table != null)) {
      foreach(dbx::get_table_indexes($old_schema, $old_table) as $index) {
        $old_index = dbx::get_table_index($new_schema, $new_table, $index['name']);
        if ( !pgsql8_table::contains_index($new_schema, $new_table, $index['name']) ) {
            $list[] = $index;
        }
        else if ( !pgsql8_index::equals($old_index, $index) ) {
          $list[] = $index;
        }
      }
    }

    return $list;
  }

  /**
   * Returns list of indexes that should be added.
   *
   * @param old_table original table
   * @param new_table new table
   *
   * @return list of indexes that should be added
   */
  public static function get_new_indexes($old_schema, $old_table, $new_schema, $new_table) {
    $list = array();

    if ($new_table != null) {
      if ($old_table == null) {
        foreach(dbx::get_table_indexes($new_schema, $new_table) as $index) {
          $list[] = $index;
        }
      } else {
        foreach(dbx::get_table_indexes($new_schema, $new_table) as $index) {
          $old_index = dbx::get_table_index($old_schema, $old_table, $index['name']);
          if ( !pgsql8_table::contains_index($old_schema, $old_table, $index['name']) ) {
            $list[] = $index;
          }
          else if ( !pgsql8_index::equals($old_index, $index) ) {
            $list[] = $index;
          }
        }
      }
    }

    return $list;
  }
}

?>
