<?php
/**
 * Manipulate functional equivalents of pgsql's sequences
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mysql5_sequence {

  const TABLE_NAME = '__sequences';
  const SEQ_COL = 'name';
  const INC_COL = 'increment';
  const MIN_COL = 'min_value';
  const MAX_COL = 'max_value';
  const CUR_COL = 'cur_value';
  const CYC_COL = 'cycle';
  const ADV_COL = 'should_advance';

  /**
   * Creates and returns SQL command for creation of the sequence.
   *
   * @return created SQL command
   */
  public function get_creation_sql($node_schema, $node_sequences) {
    $values = array();

    foreach ( dbx::to_array($node_sequences) as $node_sequence ) {
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
    $adv_col = mysql5::get_quoted_column_name(self::ADV_COL);

    return <<<SQL
-- pgsql sequence equivalent implementation
-- this creates a table called __sequences which contains each sequence's data
-- see http://www.microshell.com/database/mysql/emulating-nextval-function-to-get-sequence-in-mysql/

CREATE TABLE IF NOT EXISTS $table_name (
  $seq_col VARCHAR(100) NOT NULL,
  $inc_col INT(11) unsigned NOT NULL DEFAULT 1,
  $min_col INT(11) unsigned NOT NULL DEFAULT 1,
  $max_col BIGINT(20) unsigned NOT NULL DEFAULT 18446744073709551615,
  $cur_col BIGINT(20) unsigned DEFAULT 1,
  $cyc_col BOOLEAN NOT NULL DEFAULT FALSE,
  $adv_col BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY ($seq_col)
) ENGINE = MyISAM;

-- emulation of http://www.postgresql.org/docs/8.4/static/functions-sequence.html
-- note, these function ARE NOT ATOMIC. according to the MySQL 5.5 manual:
-- "MySQL also permits stored procedures (but not stored functions) to contain SQL transaction statements such as COMMIT"
-- and "LOCK TABLES is not transaction-safe and implicitly commits any active transaction before attempting to lock the tables."
-- because these functions cannot use transactions, and cannot safely use table locks, we cannot guarantee their atomicity

-- From the PostgreSQL 8.4 manual:
-- Return the value most recently obtained by nextval for this sequence in the current session.
-- (An error is reported if nextval has never been called for this sequence in this session.)
-- Because this is returning a session-local value, it gives a predictable answer whether
-- or not other sessions have executed nextval since the current session did.
DROP FUNCTION IF EXISTS `currval`;
CREATE FUNCTION `currval` (`seq_name` varchar(100))
RETURNS BIGINT(20) NOT DETERMINISTIC
BEGIN
  DECLARE val BIGINT(20);
  IF @__sequences_lastval IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'nextval() has not been called yet this session';
  ELSE
    SELECT `currval` INTO val FROM  `__sequences_currvals` WHERE `name` = seq_name;
    RETURN val;
  END IF;
END;

-- From the PostgreSQL 8.4 manual:
-- Return the value most recently returned by nextval in the current session. This function is
-- identical to currval, except that instead of taking the sequence name as an argument it fetches
-- the value of the last sequence used by nextval in the current session.
-- It is an error to call lastval if nextval has not yet been called in the current session.
DROP FUNCTION IF EXISTS `lastval`;
CREATE FUNCTION `lastval` ()
RETURNS BIGINT(20) NOT DETERMINISTIC
BEGIN
  IF @__sequences_lastval IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'nextval() has not been called yet this session';
  ELSE
    RETURN @__sequences_lastval;
  END IF;
END;

-- From the PostgreSQL 8.4 manual: 
-- Advance the sequence object to its next value and return that value.
-- This is done atomically: even if multiple sessions execute nextval concurrently,
-- each will safely receive a distinct sequence value. [NOPE, BECAUSE MySQL]
DROP FUNCTION IF EXISTS `nextval`;
CREATE FUNCTION `nextval` (`seq_name` varchar(100))
RETURNS BIGINT(20) NOT DETERMINISTIC
BEGIN
  DECLARE advance BOOLEAN;

  -- create a session-local table the first time we call nextval or setval for currval()
  CREATE TEMPORARY TABLE IF NOT EXISTS `__sequences_currvals` (
    `name` VARCHAR(100) NOT NULL,
    `currval` BIGINT(20),
    PRIMARY KEY (`name`)
  );

  SELECT $cur_col INTO @__sequences_lastval FROM $table_name WHERE $seq_col = seq_name;
  SELECT $adv_col INTO advance FROM $table_name WHERE $seq_col = seq_name;
  
  IF @__sequences_lastval IS NOT NULL THEN

    -- only advance the sequence to the next value if we're supposed to
    -- it will usually be true, unless setval has been called for this
    -- sequence with its third value = false
    IF advance = TRUE THEN
      UPDATE $table_name
      SET $cur_col = IF (
        ($cur_col + $inc_col) > $max_col,
        IF ($cyc_col = TRUE, $min_col, NULL),
        $cur_col + $inc_col
      )
      WHERE $seq_col = seq_name;

      SELECT $cur_col INTO @__sequences_lastval FROM $table_name WHERE $seq_col = seq_name;

    -- otherwise, set it to advance the next time around
    ELSE
      UPDATE $table_name
      SET $adv_col = TRUE
      WHERE $seq_col = seq_name;
    END IF;

    -- update our session-local table for currval()
    REPLACE INTO `__sequences_currvals` (`name`, `currval`)
    VALUE (seq_name, @__sequences_lastval);
  END IF;

  RETURN @__sequences_lastval;
END;

-- From the PostgreSQL 8.4 manual:
-- Reset the sequence object's counter value. The two-parameter form sets the sequence's
-- last_value field to the specified value and sets its is_called field to true, meaning that the
-- next nextval will advance the sequence before returning a value. The value reported by currval
-- is also set to the specified value. In the three-parameter form, is_called can be set to either
-- true or false. true has the same effect as the two-parameter form. If it is set to false, the
-- next nextval will return exactly the specified value, and sequence advancement commences with
-- the following nextval. Furthermore, the value reported by currval is not changed in this case
-- (this is a change from pre-8.3 behavior).
-- The result returned by setval is just the value of its second argument.
-- We only allow the 3-parameter form, because MySQL doesn't support optional or default parameters
DROP FUNCTION IF EXISTS `setval`;
CREATE FUNCTION `setval` (`seq_name` varchar(100), `value` bigint(20), `advance` BOOLEAN)
RETURNS bigint(20) NOT DETERMINISTIC
BEGIN

  -- update the sequence
  UPDATE $table_name
  SET $cur_col = value,
      $adv_col = advance
  WHERE $seq_col = seq_name;

  IF advance = FALSE THEN
    -- create a session-local table the first time we call nextval or setval for currval()
    CREATE TEMPORARY TABLE IF NOT EXISTS `__sequences_currvals` (
      `name` VARCHAR(100) NOT NULL,
      `currval` BIGINT(20),
      PRIMARY KEY (`name`)
    );

    -- update the session table and lastval session variable with our new value
    REPLACE INTO `__sequences_currvals` (`name`, `currval`)
    VALUE (seq_name, value);
    SET @__sequences_lastval = value;
  END IF;

  RETURN value;
END;
SQL;
  }

  public static function get_shim_drop_sql() {
    $table_name = mysql5::get_quoted_table_name(self::TABLE_NAME);
    return <<<SQL
DROP TABLE IF EXISTS $table_name;
DROP FUNCTION IF EXISTS `nextval`;
DROP FUNCTION IF EXISTS `setval`;
DROP FUNCTION IF EXISTS `currval`;
DROP FUNCTION IF EXISTS `lastval`;
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

    $sequence_names = "('" . implode("', '", array_map(function($n) { return $n['name']; }, dbx::to_array($node_sequences))) . "')";
    return "DELETE FROM $table_name WHERE $seq_col IN $sequence_names;";
  }
}

?>
