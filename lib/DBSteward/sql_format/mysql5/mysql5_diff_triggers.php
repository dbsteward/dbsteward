<?php
/**
 * Diffs triggers.
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class mysql5_diff_triggers extends sql99_diff_triggers {
  protected static function get_table_triggers($schema, $table) {
    return array_merge(parent::get_table_triggers($schema, $table), mysql5_table::get_triggers_needed($schema, $table));
  }
  protected static function schema_contains_trigger($schema, $trigger) {
    if ( parent::schema_contains_trigger($schema, $trigger) ) {
      return TRUE;
    }

    foreach ( dbx::get_tables($schema) as $table ) {
      foreach ( mysql5_table::get_triggers_needed($schema, $table) as $table_trigger ) {
        if ( strcasecmp($table_trigger['name'], $trigger['name']) === 0 ) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }
}