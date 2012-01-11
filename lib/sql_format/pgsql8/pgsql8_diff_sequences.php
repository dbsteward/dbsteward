<?php
/**
 * Diffs sequences.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_diff_sequences {

  /**
   * Outputs commands for differences in sequences.
   *
   * @param fp output file pointer
   * @param old_schema original schema
   * @param new_schema new schema
   */
  public static function diff_sequences($fp, $old_schema, $new_schema) {
    // Drop sequences that do not exist in new schema
    if ($old_schema != null) {
      foreach(dbx::get_sequences($old_schema) as $sequence) {
        if (!pgsql8_schema::contains_sequence($new_schema, $sequence['name'])) {
          fwrite($fp, pgsql8_sequence::get_drop_sql($old_schema, $sequence) . "\n");
        }
      }
    }

    // Add new sequences
    foreach(dbx::get_sequences($new_schema) as $sequence) {
      if ( $old_schema == null
          || !pgsql8_schema::contains_sequence($old_schema, $sequence['name']) ) {
        fwrite($fp, pgsql8_sequence::get_creation_sql($new_schema, $sequence) . "\n");
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
    foreach(dbx::get_sequences($new_schema) as $new_sequence) {
      if ( $old_schema != null
          && pgsql8_schema::contains_sequence($old_schema, $new_sequence['name']) ) {
        $old_sequence = dbx::get_sequence($old_schema, $new_sequence['name']);
        $sql = '';

        if ( $new_sequence['inc'] != null && strcasecmp($new_sequence['inc'], $old_sequence['inc']) != 0 ) {
          $sql .= "\n\tINCREMENT BY " . $new_sequence['inc'];
        }

        if ($new_sequence['min'] == null && $old_sequence['min'] != null) {
          $sql .= "\n\tNO MINVALUE";
        } else if (
          $new_sequence['min'] != null && strcasecmp($new_sequence['min'], $old_sequence['min']) != 0 ) {
          $sql .= "\n\tMINVALUE " . $new_sequence['min'];
        }

        if ($new_sequence['max'] == null && $old_sequence['max'] != null) {
          $sql .= "\n\tNO MAXVALUE";
        } else if ( $new_sequence['max'] != null && strcasecmp($new_sequence['max'], $old_sequence['max']) != 0 ) {
          $sql .= "\n\tMAXVALUE " . $new_sequence['max'];
        }

        if (!pgsql8_diff::$ignore_start_with) {
          if ($new_sequence['start'] != null && strcasecmp($new_sequence['start'], $old_sequence['start']) != 0 ) {
            $sql .= "\n\tRESTART WITH " . $new_sequence['start'];
          }
        }

        if ($new_sequence['cache'] != null && strcasecmp($new_sequence['cache'], $old_sequence['cache']) != 0 ) {
          $sql .= "\n\tCACHE " . $new_sequence['cache'];
        }

        if ($old_sequence['cycle'] && !$new_sequence['cycle']) {
          $sql .= "\n\tNO CYCLE";
        } else if (!$old_sequence['cycle'] && $new_sequence['cycle']) {
          $sql .= "\n\tCYCLE";
        }

        if (strlen($sql) > 0) {
          fwrite($fp, "ALTER SEQUENCE "
            . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.'
            . pgsql8_diff::get_quoted_name($new_sequence['name'], dbsteward::$quote_object_names)
            . $sql . ";\n");
        }
      }
    }
  }
}

?>
