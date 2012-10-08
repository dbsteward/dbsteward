<?php
/**
 * Diffs triggers.
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_diff_triggers {
  /**
   * Outputs DDL for differences in triggers.
   *
   * @param $ofs       output file pointer
   * @param $oldSchema original schema
   * @param $newSchema new schema
   */
  public static function diff_triggers($ofs, $old_schema, $new_schema) {
    foreach(dbx::get_tables($new_schema) as $new_table) {
      if ($old_schema == null) {
        $old_table = null;
      }
      else {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }
      static::diff_triggers_table($ofs, $old_schema, $old_table, $new_schema, $new_table);
    }
  }

  public static function diff_triggers_table($ofs, $old_schema, $old_table, $new_schema, $new_table) {
    // drop triggers that no longer exist or are modified
    foreach(static::get_drop_triggers($old_schema, $old_table, $new_schema, $new_table) as $old_trigger) {
      // only do triggers set to the current sql_format
      if ( strcasecmp($old_trigger['sqlFormat'], dbsteward::get_sql_format()) == 0 ) {
        $ofs->write(format_trigger::get_drop_sql($old_schema, $old_trigger) . "\n");
      }
    }

    // add new triggers
    foreach(static::get_new_triggers($old_schema, $old_table, $new_schema, $new_table) AS $new_trigger) {
      // only do triggers set to the current sql format
      if ( strcasecmp($new_trigger['sqlFormat'], dbsteward::get_sql_format()) == 0 ) {
        $ofs->write(format_trigger::get_creation_sql($new_schema, $new_trigger) . "\n");
      }
    }
  }

  /**
   * Returns list of triggers that should be dropped.
   *
   * @param old_table original table
   * @param new_table new table
   *
   * @return list of triggers that should be dropped
   */
  protected static function get_drop_triggers($old_schema, $old_table, $new_schema, $new_table) {
    $list = array();

    if (($new_table != null) && ($old_table != null)) {
      foreach(static::get_table_triggers($old_schema, $old_table) as $old_trigger) {
        // if this trigger doesn't exist by name in the new schema, drop it
        if ( ! static::schema_contains_trigger($new_schema, $old_trigger) ) {
          $list[] = $old_trigger;
        }
      }
    }

    return $list;
  }

  /**
   * Returns list of triggers that should be added.
   *
   * @param old_table original table
   * @param new_table new table
   *
   * @return list of triggers that should be added
   */
  protected static function get_new_triggers($old_schema, $old_table, $new_schema, $new_table) {
    $list = array();

    if ($new_table != null) {
      if ($old_table == null) {
        $list = static::get_table_triggers($new_schema, $new_table);
      } else {
        // if there isn't a trigger in the old schema which is the same as the new one,
        // recreate it
        foreach(static::get_table_triggers($new_schema, $new_table) as $new_trigger) {
          $old_contains_new = false;
          $old_triggers = static::get_table_triggers($old_schema, $old_table);
          foreach($old_triggers AS $old_trigger) {
            if ( format_trigger::equals($old_trigger, $new_trigger) ) {
              $old_contains_new = true;
              break;
            }
          }

          if (!$old_contains_new) {
            $list[] = $new_trigger;
          }
        }
      }
    }

    return $list;
  }

  protected static function get_table_triggers($schema, $table) {
    return dbx::get_table_triggers($schema, $table);
  }

  protected static function schema_contains_trigger($schema, $trigger) {
    return count($schema->xpath("trigger[@name='{$trigger['name']}']")) != 0;
  }
}
?>
