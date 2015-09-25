<?php
/**
 * DBSteward unit test for mysql5 sequences sql generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group mysql5
 * @group nodb
 */
class Mysql5SequenceSQLTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    mysql5::$swap_function_delimiters = FALSE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
  }

  public function testCreationDefaults() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="the_sequence" owner="NOBODY" />
</schema>
XML;
    
    $expected = $this->getExpectedSequenceDDL('the_sequence','DEFAULT','DEFAULT','DEFAULT','DEFAULT','DEFAULT');

    $schema = new SimpleXMLElement($xml);

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_creation_sql($schema, $schema->sequence)));

    $this->assertEquals($expected, $actual);
  }

  public function testCreationStandard() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="the_sequence" owner="NOBODY" max="10" cycle="true" inc="3" start="2"/>
</schema>
XML;

    $expected = $this->getExpectedSequenceDDL('the_sequence',3,'DEFAULT',10,2,'TRUE');

    $schema = new SimpleXMLElement($xml);

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_creation_sql($schema, $schema->sequence)));

    $this->assertEquals($expected, $actual);
  }

  public function testCreationExceptions() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="seq1" owner="NOBODY" start="abc" inc="abc" min="abc" max="abc" />
  <sequence name="seq2" owner="NOBODY" start="1" inc="abc" min="abc" max="abc" />
  <sequence name="seq3" owner="NOBODY" start="1" inc="1" min="abc" max="abc" />
  <sequence name="seq4" owner="NOBODY" start="1" inc="1" min="1" max="abc" />
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    try {
      mysql5_sequence::get_creation_sql($schema, $schema->sequence[0]);
    }
    catch ( Exception $ex ) {
      if ( stripos($ex->getMessage(), 'start value') !== FALSE ) {
        try {
          mysql5_sequence::get_creation_sql($schema, $schema->sequence[1]);
        }
        catch ( Exception $ex ) {
          if ( stripos($ex->getMessage(), 'increment by value') !== FALSE ) {
            try {
              mysql5_sequence::get_creation_sql($schema, $schema->sequence[2]);
            }
            catch ( Exception $ex ) {
              if ( stripos($ex->getMessage(), 'minimum value') !== FALSE ) {
                try {
                  mysql5_sequence::get_creation_sql($schema, $schema->sequence[3]);
                }
                catch ( Exception $ex ) {
                  if ( stripos($ex->getMessage(), 'maximum value') === FALSE ) {
                    $this->fail("Expected maximum value exception, got '" . $ex->getMessage() . "'");
                  }
                }
              }
              else {
                $this->fail("Expected minimum value exception, got '" . $ex->getMessage() . "'");
              }
            }
          }
          else {
            $this->fail("Expected increment value exception, got '" . $ex->getMessage() . "'");
          }
        }
      }
      else {
        $this->fail("Expected start value exception, got '" . $ex->getMessage() . "'");
      }
    }
  }

  public function testCreationTricky() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="the_sequence" owner="NOBODY" inc="0" min="-1" max="0" start="-1" cycle="x"/>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_creation_sql($schema, $schema->sequence)));

    $this->assertEquals($this->getExpectedSequenceDDL('the_sequence',0,'DEFAULT','DEFAULT','DEFAULT','TRUE'), $actual);
  }

  public function testDropSql() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="the_sequence" owner="NOBODY" inc="0" min="-1" max="0" start="-1" cycle="x"/>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);

    $expected = "DELETE FROM `__sequences` WHERE `name` IN ('the_sequence');";

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_drop_sql($schema, $schema->sequence)));

    $this->assertEquals($expected, $actual);
  }

  public function testMultipleSequences() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="seq0" max="10" cycle="true" inc="3" start="2"/>
  <sequence name="seq1" max="10" cycle="true" inc="3" start="2"/>
  <sequence name="seq2" max="10" cycle="true" inc="3" start="2"/>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `start_value`, `cycle`)
VALUES
  ('seq0', 3, DEFAULT, 10, 2, 2, TRUE),
  ('seq1', 3, DEFAULT, 10, 2, 2, TRUE),
  ('seq2', 3, DEFAULT, 10, 2, 2, TRUE);
SQL;

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_creation_sql($schema, $schema->sequence)));

    $this->assertEquals($expected, $actual);

    $expected_drop = "DELETE FROM `__sequences` WHERE `name` IN ('seq0', 'seq1', 'seq2');";
    $actual_drop = trim(preg_replace('/--.*/','',mysql5_sequence::get_drop_sql($schema, $schema->sequence)));

    $this->assertEquals($expected_drop, $actual_drop);
  }

  public function testShim() {
    $actual = mysql5_sequence::get_shim_creation_sql();
    $actual = trim(preg_replace('/--.*(\n\s*)?/','',$actual));
    $expected = <<<SQL
CREATE TABLE IF NOT EXISTS `__sequences` (
  `name` VARCHAR(100) NOT NULL,
  `increment` INT(11) unsigned NOT NULL DEFAULT 1,
  `min_value` INT(11) unsigned NOT NULL DEFAULT 1,
  `max_value` BIGINT(20) unsigned NOT NULL DEFAULT 18446744073709551615,
  `cur_value` BIGINT(20) unsigned DEFAULT 1,
  `start_value` BIGINT(20) unsigned DEFAULT 1,
  `cycle` BOOLEAN NOT NULL DEFAULT FALSE,
  `should_advance` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`name`)
) ENGINE = MyISAM;

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

DROP FUNCTION IF EXISTS `nextval`;
CREATE FUNCTION `nextval` (`seq_name` varchar(100))
RETURNS BIGINT(20) NOT DETERMINISTIC
BEGIN
  DECLARE advance BOOLEAN;

  CREATE TEMPORARY TABLE IF NOT EXISTS `__sequences_currvals` (
    `name` VARCHAR(100) NOT NULL,
    `currval` BIGINT(20),
    PRIMARY KEY (`name`)
  );

  SELECT `cur_value` INTO @__sequences_lastval FROM `__sequences` WHERE `name` = seq_name;
  SELECT `should_advance` INTO advance FROM `__sequences` WHERE `name` = seq_name;
  
  IF @__sequences_lastval IS NOT NULL THEN

    IF advance = TRUE THEN
      UPDATE `__sequences`
      SET `cur_value` = IF (
        (`cur_value` + `increment`) > `max_value`,
        IF (`cycle` = TRUE, `min_value`, NULL),
        `cur_value` + `increment`
      )
      WHERE `name` = seq_name;

      SELECT `cur_value` INTO @__sequences_lastval FROM `__sequences` WHERE `name` = seq_name;

    ELSE
      UPDATE `__sequences`
      SET `should_advance` = TRUE
      WHERE `name` = seq_name;
    END IF;

    REPLACE INTO `__sequences_currvals` (`name`, `currval`)
    VALUE (seq_name, @__sequences_lastval);
  END IF;

  RETURN @__sequences_lastval;
END;

DROP FUNCTION IF EXISTS `setval`;
CREATE FUNCTION `setval` (`seq_name` varchar(100), `value` bigint(20), `advance` BOOLEAN)
RETURNS bigint(20) NOT DETERMINISTIC
BEGIN

  UPDATE `__sequences`
  SET `cur_value` = value,
      `should_advance` = advance
  WHERE `name` = seq_name;

  IF advance = FALSE THEN
    CREATE TEMPORARY TABLE IF NOT EXISTS `__sequences_currvals` (
      `name` VARCHAR(100) NOT NULL,
      `currval` BIGINT(20),
      PRIMARY KEY (`name`)
    );

    REPLACE INTO `__sequences_currvals` (`name`, `currval`)
    VALUE (seq_name, value);
    SET @__sequences_lastval = value;
  END IF;

  RETURN value;
END;
SQL;
    $this->assertEquals($expected, $actual);
  }

  public function testDelimiters() {
    mysql5::$swap_function_delimiters = TRUE;
    $actual = mysql5_sequence::get_shim_creation_sql();
    $actual = trim(preg_replace('/--.*(\n\s*)?/','',$actual));
    $expected = <<<SQL
CREATE TABLE IF NOT EXISTS `__sequences` (
  `name` VARCHAR(100) NOT NULL,
  `increment` INT(11) unsigned NOT NULL DEFAULT 1,
  `min_value` INT(11) unsigned NOT NULL DEFAULT 1,
  `max_value` BIGINT(20) unsigned NOT NULL DEFAULT 18446744073709551615,
  `cur_value` BIGINT(20) unsigned DEFAULT 1,
  `start_value` BIGINT(20) unsigned DEFAULT 1,
  `cycle` BOOLEAN NOT NULL DEFAULT FALSE,
  `should_advance` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`name`)
) ENGINE = MyISAM;

DELIMITER \$_$
DROP FUNCTION IF EXISTS `currval`\$_$
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
END\$_$

DROP FUNCTION IF EXISTS `lastval`\$_$
CREATE FUNCTION `lastval` ()
RETURNS BIGINT(20) NOT DETERMINISTIC
BEGIN
  IF @__sequences_lastval IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'nextval() has not been called yet this session';
  ELSE
    RETURN @__sequences_lastval;
  END IF;
END\$_$

DROP FUNCTION IF EXISTS `nextval`\$_$
CREATE FUNCTION `nextval` (`seq_name` varchar(100))
RETURNS BIGINT(20) NOT DETERMINISTIC
BEGIN
  DECLARE advance BOOLEAN;

  CREATE TEMPORARY TABLE IF NOT EXISTS `__sequences_currvals` (
    `name` VARCHAR(100) NOT NULL,
    `currval` BIGINT(20),
    PRIMARY KEY (`name`)
  );

  SELECT `cur_value` INTO @__sequences_lastval FROM `__sequences` WHERE `name` = seq_name;
  SELECT `should_advance` INTO advance FROM `__sequences` WHERE `name` = seq_name;
  
  IF @__sequences_lastval IS NOT NULL THEN

    IF advance = TRUE THEN
      UPDATE `__sequences`
      SET `cur_value` = IF (
        (`cur_value` + `increment`) > `max_value`,
        IF (`cycle` = TRUE, `min_value`, NULL),
        `cur_value` + `increment`
      )
      WHERE `name` = seq_name;

      SELECT `cur_value` INTO @__sequences_lastval FROM `__sequences` WHERE `name` = seq_name;

    ELSE
      UPDATE `__sequences`
      SET `should_advance` = TRUE
      WHERE `name` = seq_name;
    END IF;

    REPLACE INTO `__sequences_currvals` (`name`, `currval`)
    VALUE (seq_name, @__sequences_lastval);
  END IF;

  RETURN @__sequences_lastval;
END\$_$

DROP FUNCTION IF EXISTS `setval`\$_$
CREATE FUNCTION `setval` (`seq_name` varchar(100), `value` bigint(20), `advance` BOOLEAN)
RETURNS bigint(20) NOT DETERMINISTIC
BEGIN

  UPDATE `__sequences`
  SET `cur_value` = value,
      `should_advance` = advance
  WHERE `name` = seq_name;

  IF advance = FALSE THEN
    CREATE TEMPORARY TABLE IF NOT EXISTS `__sequences_currvals` (
      `name` VARCHAR(100) NOT NULL,
      `currval` BIGINT(20),
      PRIMARY KEY (`name`)
    );

    REPLACE INTO `__sequences_currvals` (`name`, `currval`)
    VALUE (seq_name, value);
    SET @__sequences_lastval = value;
  END IF;

  RETURN value;
END\$_$
DELIMITER ;
SQL;
    $this->assertEquals($expected, $actual);
  }

  protected function getExpectedSequenceDDL($name, $inc, $min, $max, $start, $cycle) {
    return <<<SQL
INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `start_value`, `cycle`)
VALUES
  ('$name', $inc, $min, $max, $start, $start, $cycle);
SQL;
  }
}
?>
