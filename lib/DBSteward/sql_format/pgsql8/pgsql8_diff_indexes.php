<?php
/**
 * Diffs indexes.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_diff_indexes {

  /**
   * Outputs commands for differences in indexes.
   *
   * @param $ofs       output file pointer
   * @param old_schema original schema
   * @param new_schema new schema
   */
  public static function diff_indexes($ofs, $old_schema, $new_schema) {
    foreach(dbx::get_tables($new_schema) as $new_table) {
      if ($old_schema == null) {
        $old_table = null;
      } else {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }
      self::diff_indexes_table($ofs, $old_schema, $old_table, $new_schema, $new_table);
    }
  }

  public static function diff_indexes_table($ofs, $old_schema, $old_table, $new_schema, $new_table) {
    // Drop indexes that do not exist in new schema or are modified
    foreach(self::get_drop_indexes($old_schema, $old_table, $new_schema, $new_table) as $index) {
      $ofs->write(pgsql8_index::get_drop_sql($new_schema, $new_table, $index));
    }

    // Add new indexes
    if ($old_schema == null) {
      foreach(format_index::get_table_indexes($new_schema, $new_table) as $index) {
        $ofs->write(pgsql8_index::get_creation_sql($new_schema, $new_table, $index) . "\n");
      }
    } else {
      foreach(self::get_new_indexes($old_schema, $old_table, $new_schema, $new_table) as $index) {
        $ofs->write(pgsql8_index::get_creation_sql($new_schema, $new_table, $index) . "\n");
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
      foreach(format_index::get_table_indexes($old_schema, $old_table) as $index) {
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
        foreach(format_index::get_table_indexes($new_schema, $new_table) as $index) {
          $list[] = $index;
        }
      } else {
        foreach(format_index::get_table_indexes($new_schema, $new_table) as $index) {
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
