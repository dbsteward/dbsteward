<?php
/**
 * Manipulate functional equivalents of pgsql's sequences
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/mysql5.php';

class mysql5_sequence {

  const TABLE_NAME = '__sequences';
  const SEQ_COL = 'name';
  const INC_COL = 'increment';
  const MIN_COL = 'min_value';
  const MAX_COL = 'max_value';
  const CUR_COL = 'cur_value';
  const CYC_COL = 'cycle';
  /**
   * Creates and returns SQL command for creation of the sequence.
   *
   * @return created SQL command
   */
  public function get_creation_sql($node_schema, $node_sequences) {
    $values = array();

    if ( ! is_array($node_sequences) ) {
      $node_sequences = array($node_sequences);
    }

    foreach ( $node_sequences as $node_sequence ) {
      if ( isset($node_sequence['start']) && !is_numeric((string)$node_sequence['start']) ) {
        throw new exception("start value is not numeric: " . $node_sequence['start']);
      }
      if ( isset($node_sequence['inc']) && !is_numeric((string)$node_sequence['inc']) ) {
        throw new exception("increment by value is not numeric: " . $node_sequence['inc']);
      }
      if ( isset($node_sequence['min']) && !is_numeric((string)$node_sequence['min']) ) {
        throw new exception("minimum value is not numeric: " . $node_sequence['min']);
      }
      if ( isset($node_sequence['max']) && !is_numeric((string)$node_sequence['max']) ) {
        throw new exception("maximum value is not numeric: " . $node_sequence['max']);
      }

      if ( isset($node_sequence['inc']) ) {
        $increment = (int)$node_sequence['inc'];
      }
      else {
        $increment = 'DEFAULT';
      }

      $max_value = (int)$node_sequence['max'];
      if ( $max_value > 0 ) {
        $max = $max_value;
      }
      else {
        $max = 'DEFAULT';
      }

      $min_value = (int)$node_sequence['min'];
      if ( $min_value > 0 ) {
        $min = $min_value;
      }
      else {
        $min = 'DEFAULT';
      }

      $start_value = (int)$node_sequence['start'];
      if ( $start_value > 0 ) {
        $start = $start_value;
      }
      else {
        $start = $min;
      }

      if ( isset($node_sequence['cycle']) ) {
        $cycle = (strcasecmp($node_sequence['cycle'], 'false') == 0) ? 'FALSE' : 'TRUE';
      }
      else {
        $cycle = 'DEFAULT';
      }

      $name = $node_sequence['name'];

      $values[] = "('$name', $increment, $min, $max, $start, $cycle)";
    }

    $table_name = mysql5::get_quoted_table_name(self::TABLE_NAME);
    $seq_col = mysql5::get_quoted_column_name(self::SEQ_COL);
    $inc_col = mysql5::get_quoted_column_name(self::INC_COL);
    $min_col = mysql5::get_quoted_column_name(self::MIN_COL);
    $max_col = mysql5::get_quoted_column_name(self::MAX_COL);
    $cur_col = mysql5::get_quoted_column_name(self::CUR_COL);
    $cyc_col = mysql5::get_quoted_column_name(self::CYC_COL);

    $values = implode(",\n  ", $values);

    return <<<SQL
-- see http://www.microshell.com/database/mysql/emulating-nextval-function-to-get-sequence-in-mysql/
INSERT INTO $table_name
  ($seq_col, $inc_col, $min_col, $max_col, $cur_col, $cyc_col)
VALUES
  $values;
SQL;
  }

  public static function get_shim_creation_sql() {
    $table_name = mysql5::get_quoted_table_name(self::TABLE_NAME);
    $seq_col = mysql5::get_quoted_column_name(self::SEQ_COL);
    $inc_col = mysql5::get_quoted_column_name(self::INC_COL);
    $min_col = mysql5::get_quoted_column_name(self::MIN_COL);
    $max_col = mysql5::get_quoted_column_name(self::MAX_COL);
    $cur_col = mysql5::get_quoted_column_name(self::CUR_COL);
    $cyc_col = mysql5::get_quoted_column_name(self::CYC_COL);

    return <<<SQL
-- pgsql sequence equivalent implementation
-- this creates a table called __sequences which contains each sequence's data
-- see http://www.microshell.com/database/mysql/emulating-nextval-function-to-get-sequence-in-mysql/

CREATE TABLE $table_name (
  $seq_col varchar(100) NOT NULL,
  $inc_col int(11) unsigned NOT NULL DEFAULT 1,
  $min_col int(11) unsigned NOT NULL DEFAULT 1,
  $max_col bigint(20) unsigned NOT NULL DEFAULT 18446744073709551615,
  $cur_col bigint(20) unsigned DEFAULT 1,
  $cyc_col boolean NOT NULL DEFAULT FALSE,
  PRIMARY KEY ($seq_col)
) ENGINE=MyISAM;

DELIMITER $$
CREATE FUNCTION `nextval` (`seq_name` varchar(100))
RETURNS bigint(20) NOT DETERMINISTIC
BEGIN
  DECLARE cur_val bigint(20);

  SELECT $cur_col INTO cur_val FROM $table_name WHERE $seq_col = seq_name;
   
  IF cur_val IS NOT NULL THEN
    UPDATE $table_name
    SET $cur_col = IF (
      ($cur_col + $inc_col) > $max_col,
      IF ($cyc_col = TRUE, $min_col, NULL),
      $cur_col + $inc_col
    )
    WHERE $seq_col = seq_name;
  END IF;

  RETURN cur_val;
END$$
DELIMITER ;
SQL;
  }

  public static function get_shim_drop_sql() {
    $table_name = mysql5::get_quoted_table_name(self::TABLE_NAME);
    return <<<SQL
DROP TABLE IF EXISTS $table_name;
DROP FUNCTION IF EXISTS `nextval`;
SQL;
  }

  /**
   * Creates and returns SQL command for dropping the sequence.
   *
   * @return string
   */
  public static function get_drop_sql($node_schema, $node_sequences) {
    $table_name = mysql5::get_quoted_table_name(self::TABLE_NAME);
    $seq_col = mysql5::get_quoted_column_name(self::SEQ_COL);

    if ( ! is_array($node_sequences) ) {
      $node_sequences = array($node_sequences);
    }

    $sequence_names = "('" . implode("', '", array_map(function($n) { return $n['name']; }, $node_sequences)) . "')";
    return "DELETE FROM $table_name WHERE $seq_col IN $sequence_names;";
  }
}

?>
