<?php
/**
 * DBSteward unit test for mysql5 sequence diff sql generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

require_once __DIR__ . '/../mock_output_file_segmenter.php';

class Mysql5SequenceDiffSQLTest extends PHPUnit_Framework_TestCase {
  private $xml_0 = <<<XML
<schema name="test0" owner="NOBODY">
</schema>
XML;

  private $xml_1 = <<<XML
<schema name="test1" owner="NOBODY">
  <sequence name="seq0" owner="NOBODY"/>
</schema>
XML;

  private $xml_1a = <<<XML
<schema name="test1a" owner="NOBODY">
  <sequence name="seq0" owner="NOBODY" inc="1" min="2" start="3" max="4" cycle="false"/>
</schema>
XML;

  private $xml_1b = <<<XML
<schema name="test1b" owner="NOBODY">
  <sequence name="seq0" owner="NOBODY" inc="2" min="2" start="3" max="4" cycle="true"/>
</schema>
XML;

  private $xml_3 = <<<XML
<schema name="test3" owner="NOBODY">
  <sequence name="seq0" owner="NOBODY"/>
  <sequence name="seq1" owner="NOBODY"/>
  <sequence name="seq2" owner="NOBODY"/>
</schema>
XML;

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
  }

  public function testNoneToNone() {
    $this->common($this->xml_0, $this->xml_0, "");
  }

  public function testSameToSame() {
    $this->common($this->xml_3, $this->xml_3, "");
  }

  public function testAddNew() {
    $expected = $this->getExpectedSequenceShimDDL();
    $expected .= <<<SQL


INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `cycle`)
VALUES
  ('seq0', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT),
  ('seq1', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT),
  ('seq2', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT);
SQL;
    
    $this->common($this->xml_0, $this->xml_3, $expected);
  }

  public function testAddSome() {
    $expected = <<<SQL
INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `cycle`)
VALUES
  ('seq1', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT),
  ('seq2', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT);
SQL;
    
    $this->common($this->xml_1, $this->xml_3, $expected);
  }

  public function testDropAll() {
    $expected = <<<SQL
DROP TABLE IF EXISTS `__sequences`;
DROP FUNCTION IF EXISTS `nextval`;
DROP FUNCTION IF EXISTS `setval`;
DROP FUNCTION IF EXISTS `currval`;
DROP FUNCTION IF EXISTS `lastval`;
SQL;

    $this->common($this->xml_3, $this->xml_0, $expected);
  }

  public function testDropSome() {
    $expected = "DELETE FROM `__sequences` WHERE `name` IN ('seq1', 'seq2');";
    $this->common($this->xml_3, $this->xml_1, $expected);
  }

  public function testChangeOne() {
    $expected = <<<SQL
UPDATE `__sequences`
SET `increment` = 2,
    `cycle` = TRUE
WHERE `name` = 'seq0';
SQL;
    $this->common($this->xml_1a, $this->xml_1b, $expected);
  }

  public function testAddSomeAndChange() {
    $expected = <<<SQL
UPDATE `__sequences`
SET `increment` = DEFAULT,
    `min_value` = DEFAULT,
    `max_value` = DEFAULT,
    `cycle` = DEFAULT
WHERE `name` = 'seq0';
INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `cycle`)
VALUES
  ('seq1', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT),
  ('seq2', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT);
SQL;
    $this->common($this->xml_1a, $this->xml_3, $expected);
  }

  public function testDropSomeAndChange() {
    $expected = <<<SQL
DELETE FROM `__sequences` WHERE `name` IN ('seq1', 'seq2');

UPDATE `__sequences`
SET `increment` = 1,
    `min_value` = 2,
    `max_value` = 4,
    `cur_value` = 3,
    `cycle` = FALSE
WHERE `name` = 'seq0';
SQL;
    $this->common($this->xml_3, $this->xml_1a, $expected);
  }

  protected function common($xml_a, $xml_b, $expected) {
    $schema_a = new SimpleXMLElement($xml_a);
    $schema_b = new SimpleXMLElement($xml_b);

    $ofs = new mock_output_file_segmenter();

    mysql5_diff_sequences::diff_sequences($ofs, $schema_a, $schema_b);

    $actual = trim(preg_replace('/--.*(\n\s*)?/','',$ofs->_get_output()));

    $this->assertEquals($expected, $actual);
  }

  protected function getExpectedSequenceShimDDL() {
    return <<<SQL
CREATE TABLE IF NOT EXISTS `__sequences` (
  `name` VARCHAR(100) NOT NULL,
  `increment` INT(11) unsigned NOT NULL DEFAULT 1,
  `min_value` INT(11) unsigned NOT NULL DEFAULT 1,
  `max_value` BIGINT(20) unsigned NOT NULL DEFAULT 18446744073709551615,
  `cur_value` BIGINT(20) unsigned DEFAULT 1,
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
  }
}