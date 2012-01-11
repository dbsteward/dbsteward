<?php
/**
 * Diffs sequences.
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_diff_sequences {

  /**
   * Outputs commands for differences in sequences.
   * note that sequences are created/handled for MSSQL by mssql10_bit_table
   *
   * @param fp output file pointer
   * @param old_schema original schema
   * @param new_schema new schema
   */
  public static function diff_sequences($fp, $old_schema, $new_schema) {
    // Drop sequences that do not exist in new schema
    if ($old_schema != NULL) {
      foreach (dbx::get_sequences($old_schema) as $sequence) {
        if (!mssql10_schema::contains_sequence($new_schema, $sequence['name'])) {
          fwrite($fp, mssql10_bit_table::get_drop_sql($old_schema, $sequence) . "\n");
        }
      }
    }

    // Add new sequences
    foreach (dbx::get_sequences($new_schema) as $sequence) {
      if ($old_schema == NULL
        || !mssql10_schema::contains_sequence($old_schema, $sequence['name'])) {
        fwrite($fp, mssql10_bit_table::get_creation_sql($new_schema, $sequence) . "\n");
      }
    }

    // Alter modified sequences
    self::add_modified_sequences($fp, $old_schema, $new_schema);
  }

  /**
   * Modify sequence values if they have changed
   *
   * @param fp output file pointer
   * @param old_schema original schema
   * @param new_schema new schema
   */
  private static function add_modified_sequences($fp, $old_schema, $new_schema) {
    // this exists in pgsql8_diff_sequences
    // however as seen in mssql10_bit_table @IMPLEMENT: lines
    // what to do with these parameters has yet to be determined
    return;
  }
}

?>
