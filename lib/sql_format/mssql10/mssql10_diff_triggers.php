<?php
/**
 * Diffs triggers.
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_diff_triggers {

  /**
   * Outputs commands for differences in triggers.
   *
   * @param fp output file pointer
   * @param oldSchema original schema
   * @param newSchema new schema
   */
  public static function diff_triggers($fp, $old_schema, $new_schema) {
    foreach (dbx::get_tables($new_schema) as $new_table) {
      if ($old_schema == NULL) {
        $old_table = NULL;
      }
      else {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }
      self::diff_triggers_table($fp, $old_schema, $old_table, $new_schema, $new_table);
    }
  }

  public static function diff_triggers_table($fp, $old_schema, $old_table, $new_schema, $new_table) {
    // drop triggers that no longer exist or are modified
    foreach (self::get_drop_triggers($old_schema, $old_table, $new_schema, $new_table) as $old_trigger) {
      // only do triggers set to the current sql format
      if (strcasecmp($old_trigger['sqlFormat'], dbsteward::get_sql_format()) == 0) {
        fwrite($fp, mssql10_trigger::get_drop_sql($old_schema, $old_trigger) . "\n");
      }
    }

    // add new triggers
    foreach (self::get_new_triggers($old_schema, $old_table, $new_schema, $new_table) AS $new_trigger) {
      // only do triggers set to the current sql format
      if (strcasecmp($new_trigger['sqlFormat'], dbsteward::get_sql_format()) == 0) {
        fwrite($fp, mssql10_trigger::get_creation_sql($new_schema, $new_trigger) . "\n");
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
  private static function get_drop_triggers($old_schema, $old_table, $new_schema, $new_table) {
    $list = array();

    if (($new_table != NULL) && ($old_table != NULL)) {
      $new_triggers = dbx::get_table_triggers($new_schema, $new_table);
      foreach (dbx::get_table_triggers($old_schema, $old_table) as $old_trigger) {
        $new_contains_old = FALSE;
        foreach ($new_triggers AS $new_trigger) {
          if (mssql10_trigger::equals($old_trigger, $new_trigger)) {
            $new_contains_old = TRUE;
            break;
          }
        }
        if (!$new_contains_old) {
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
  private static function get_new_triggers($old_schema, $old_table, $new_schema, $new_table) {
    $list = array();

    if ($new_table != NULL) {
      if ($old_table == NULL) {
        $list = dbx::get_table_triggers($new_schema, $new_table);
      }
      else {
        foreach (dbx::get_table_triggers($new_schema, $new_table) as $new_trigger) {
          $old_contains_new = FALSE;
          $old_triggers = dbx::get_table_triggers($old_schema, $old_table);
          foreach ($old_triggers AS $old_trigger) {
            if (mssql10_trigger::equals($old_trigger, $new_trigger)) {
              $old_contains_new = TRUE;
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
}

?>
