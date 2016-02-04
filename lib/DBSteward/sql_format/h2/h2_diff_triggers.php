<?php

class h2_diff_triggers extends sql99_diff_triggers {
  protected static function get_table_triggers($schema, $table) {
    return array_merge(parent::get_table_triggers($schema, $table), h2_table::get_triggers_needed($schema, $table));
  }
  protected static function schema_contains_trigger($schema, $trigger) {
    if ( parent::schema_contains_trigger($schema, $trigger) ) {
      return TRUE;
    }

    foreach ( dbx::get_tables($schema) as $table ) {
      foreach ( h2_table::get_triggers_needed($schema, $table) as $table_trigger ) {
        if ( strcasecmp($table_trigger['name'], $trigger['name']) === 0 ) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }
}
?>
