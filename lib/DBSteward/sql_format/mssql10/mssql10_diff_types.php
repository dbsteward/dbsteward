<?php
/**
 * Difference type definitions
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_diff_types {

  /**
   * Drop removed types
   * Add new types
   * Apply type definition differences, updating the type's tables along the way
   *
   * @param $ofs          output file segmenter
   * @param $old_schema   original schema
   * @param $new_schema   new schema
   */
  public static function apply_changes($ofs, $old_schema, $new_schema) {
    // drop any types that are no longer defined
    self::drop_types($ofs, $old_schema, $new_schema);
    
    // create any types that are new in the new definition
    self::create_types($ofs, $old_schema, $new_schema);

    // there is no alter for types
    // find types that still exist that are different
    // placehold type data in table columns, and recreate the type
    foreach (dbx::get_types($new_schema) AS $new_type) {
      // does type exist in old definition ?
      if (($old_schema == NULL) || !mssql10_schema::contains_type($old_schema, $new_type['name'])) {
        continue;
      }

      $old_type = dbx::get_type($old_schema, $new_type['name']);
      
      // is there a difference between the old and new type definitions?
      if ( mssql10_type::equals($old_schema, $old_type, $new_schema, $new_type) ) {
        continue;
      }
      
      $columns = array();
      
      $ofs->write("-- type " . $new_type['name'] . " definition migration (1/4): dependant tables column constraint drop\n");
      $ofs->write(mssql10_type::column_constraint_temporary_drop($columns, $old_schema, $old_type) . "\n");

      $ofs->write("-- type " . $new_type['name'] . " definition migration (2/4): delete type list values\n");
      $ofs->write(mssql10_type::get_enum_value_delete($old_schema, $old_type) . "\n");
      
      $ofs->write("-- type " . $new_type['name'] . " definition migration (3/4): recreate type list values\n");
      $ofs->write(mssql10_type::get_enum_value_insert($new_schema, $new_type) . "\n");
      
      $ofs->write("-- type " . $new_type['name'] . " definition migration (4/4): restore dependant tables column constraint add\n");
      $ofs->write(mssql10_type::column_constraint_restore($columns, $new_schema, $new_type) . "\n");
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
    foreach (dbx::get_types($new_schema) AS $type) {
      if (($old_schema == NULL) || !mssql10_schema::contains_type($old_schema, $type['name'])) {
        $ofs->write(mssql10_type::get_creation_sql($new_schema, $type) . "\n");
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
      foreach (dbx::get_types($old_schema) AS $type) {
        if (!mssql10_schema::contains_type($new_schema, $type['name'])) {
          $ofs->write(mssql10_type::get_drop_sql($new_schema, $type) . "\n");
        }
      }
    }
  }
}

?>
