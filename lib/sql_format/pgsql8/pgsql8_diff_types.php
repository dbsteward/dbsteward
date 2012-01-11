<?php
/**
 * Difference type definitions
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_diff_types.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_diff_types {
  
  /**
   * Drop removed types
   * Add new types
   * Apply type definition differences, updating the type's tables along the way
   *
   * @param $fp           output pointer
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  public static function apply_changes($fp, $old_schema, $new_schema) {
    // drop any types that are no longer defined
    self::drop_types($fp, $old_schema, $new_schema);
    
    // create any types that are new in the new definition
    self::create_types($fp, $old_schema, $new_schema);

    // there is no alter for types
    // find types that still exist that are different
    // placehold type data in table columns, and recreate the type
    foreach (dbx::get_types($new_schema) AS $new_type) {
      // does type exist in old definition ?
      if (($old_schema == NULL) || !pgsql8_schema::contains_type($old_schema, $new_type['name'])) {
        continue;
      }

      $old_type = dbx::get_type($old_schema, $new_type['name']);
      
      // is there a difference between the old and new type definitions?
      if ( pgsql8_type::equals($old_schema, $old_type, $new_schema, $new_type) ) {
        continue;
      }
      
      $columns = array();
      
      fwrite($fp, "-- type " . $new_type['name'] . " definition migration (1/4): dependant tables column type alteration\n");
      fwrite($fp, pgsql8_type::alter_column_type_placeholder($columns, $old_schema, $old_type) . "\n");

      fwrite($fp, "-- type " . $new_type['name'] . " definition migration (2/4): drop old type\n");
      fwrite($fp, pgsql8_type::get_drop_sql($old_schema, $old_type) . "\n\n");
      
      fwrite($fp, "-- type " . $new_type['name'] . " definition migration (3/4): recreate type with new definition\n");
      fwrite($fp, pgsql8_type::get_creation_sql($new_schema, $new_type) . "\n\n");
      
      fwrite($fp, "-- type " . $new_type['name'] . " definition migration (4/4): dependant tables type restoration\n");
      fwrite($fp, pgsql8_type::alter_column_type_restore($columns, $new_schema, $new_type) . "\n");
    }
  }

  /**
   * Outputs commands for creation of new types in a schema
   *
   * @param $fp           output file pointer
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  private static function create_types($fp, $old_schema, $new_schema) {
    foreach(dbx::get_types($new_schema) AS $type) {
      if ( ($old_schema == NULL) || !pgsql8_schema::contains_type($old_schema, $type['name']) ) {
        fwrite($fp, pgsql8_type::get_creation_sql($new_schema, $type) . "\n");
      }
    }
  }

  /**
   * Outputs commands for dropping types.
   *
   * @param $fp           output file pointer
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  private static function drop_types($fp, $old_schema, $new_schema) {
    if ($old_schema != NULL) {
      foreach(dbx::get_types($old_schema) AS $type) {
        if ( !pgsql8_schema::contains_type($new_schema, $type['name'])) {
          fwrite($fp, pgsql8_type::get_drop_sql($new_schema, $type) . "\n");
        }
      }
    }
  }

}

?>
