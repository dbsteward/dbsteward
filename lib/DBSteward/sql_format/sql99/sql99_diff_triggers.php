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
    foreach ( static::get_drop_triggers($old_schema, $new_schema) as $old_trigger ) {
      $ofs->write(format_trigger::get_drop_sql($old_schema, $old_trigger)."\n");
    }
    foreach ( static::get_new_triggers($old_schema, $new_schema) as $new_trigger ) {
      $ofs->write(format_trigger::get_creation_sql($new_schema, $new_trigger)."\n");
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
  private static function get_drop_triggers($old_schema, $new_schema) {
    $list = array();

    if (($new_schema != null) && ($old_schema != null)) {
      $new_triggers = $new_schema->xpath('trigger');
      $old_triggers = $old_schema->xpath('trigger');
      foreach($old_triggers as $old_trigger) {
        $new_contains_old = false;
        foreach($new_triggers AS $new_trigger) {
          if ( format_trigger::equals($old_trigger, $new_trigger) ) {
            $new_contains_old = true;
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
  private static function get_new_triggers($old_schema, $new_schema) {
    $list = array();

    if ($new_schema != null) {
      $new_triggers = $new_schema->xpath('trigger');
      if ($old_schema == null) {
        $list = $new_triggers;
      }
      else {
        foreach($new_triggers as $new_trigger) {
          $old_contains_new = false;
          $old_triggers = $old_schema->xpath('trigger');
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
}