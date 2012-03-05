<?php
/**
 * Difference type definitions
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_diff_types {
  
  /**
   * Drop removed types
   * Add new types
   * Apply type definition differences, updating the type's tables along the way
   *
   * @param $ofs          output segementer
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  public static function apply_changes($ofs, $old_schema, $new_schema) {
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
      
      $ofs->write("-- type " . $new_type['name'] . " definition migration (1/4): dependant tables column type alteration\n");
      $ofs->write(pgsql8_type::alter_column_type_placeholder($columns, $old_schema, $old_type) . "\n");

      $ofs->write("-- type " . $new_type['name'] . " definition migration (2/4): drop old type\n");
      $ofs->write(pgsql8_type::get_drop_sql($old_schema, $old_type) . "\n\n");
      
      $ofs->write("-- type " . $new_type['name'] . " definition migration (3/4): recreate type with new definition\n");
      $ofs->write(pgsql8_type::get_creation_sql($new_schema, $new_type) . "\n\n");
      
      $ofs->write("-- type " . $new_type['name'] . " definition migration (4/4): dependant tables type restoration\n");
      $ofs->write(pgsql8_type::alter_column_type_restore($columns, $new_schema, $new_type) . "\n");
    }
  }

  /**
   * Outputs commands for creation of new types in a schema
   *
   * @param $ofs          output file pointer
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  private static function create_types($ofs, $old_schema, $new_schema) {
    foreach(dbx::get_types($new_schema) AS $type) {
      if ( ($old_schema == NULL) || !pgsql8_schema::contains_type($old_schema, $type['name']) ) {
        $ofs->write(pgsql8_type::get_creation_sql($new_schema, $type) . "\n");
      }
    }
  }

  /**
   * Outputs commands for dropping types.
   *
   * @param $ofs          output file pointer
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  private static function drop_types($ofs, $old_schema, $new_schema) {
    if ($old_schema != NULL) {
      foreach(dbx::get_types($old_schema) AS $type) {
        if ( !pgsql8_schema::contains_type($new_schema, $type['name'])) {
          $ofs->write(pgsql8_type::get_drop_sql($new_schema, $type) . "\n");
        }
      }
    }
  }

}

?>
