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
    mysql5::$swap_function_delimiters = FALSE;
  }

  public function testNoneToNone() {
    $this->common($this->xml_0, $this->xml_0, "");
  }

  public function testSameToSame() {
    $this->common($this->xml_3, $this->xml_3, "");
  }

  public function testAddNew() {
    $expected = mysql5_sequence::get_shim_creation_sql();
    $expected .= <<<SQL


INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `start_value`, `cycle`)
VALUES
  ('seq0', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT),
  ('seq1', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT),
  ('seq2', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT);
SQL;
    
    $this->common($this->xml_0, $this->xml_3, $expected);
  }

  public function testAddSome() {
    $expected = <<<SQL
INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `start_value`, `cycle`)
VALUES
  ('seq1', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT),
  ('seq2', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT);
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
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `start_value`, `cycle`)
VALUES
  ('seq1', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT),
  ('seq2', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT);
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

  public function testSerials() {
    $old = <<<XML
<schema name="test0" owner="NOBODY">
</schema>
XML;
    $new = <<<XML
<schema name="test0" owner="NOBODY">
  <table name="table" owner="NOBODY">
    <column name="id" type="serial"/>
  </table>
</schema>
XML;
  
    // shouldn't create any serial sequences  
    $this->common($new, $new, '');

    // should create a serial sequence
    $expected = mysql5_sequence::get_shim_creation_sql();
    $expected.= <<<SQL
\n\nINSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `start_value`, `cycle`)
VALUES
  ('__test0_table_id_serial_seq', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT);
SQL;
    $this->common($old, $new, $expected);

    // should drop a serial sequence
    $expected = <<<SQL
DROP TABLE IF EXISTS `__sequences`;
DROP FUNCTION IF EXISTS `nextval`;
DROP FUNCTION IF EXISTS `setval`;
DROP FUNCTION IF EXISTS `currval`;
DROP FUNCTION IF EXISTS `lastval`;
SQL;
    $this->common($new, $old, $expected);

    $renamed = <<<XML
<schema name="test0" owner="NOBODY">
  <table name="table" owner="NOBODY">
    <column name="newid" type="serial" oldName="id"/>
  </table>
</schema>
XML;
    
    // should UPDATE for name
    $this->common($new, $renamed, "UPDATE `__sequences`\nSET `name` = '__test0_table_newid_serial_seq'\nWHERE `name` = '__test0_table_id_serial_seq';");
  }

  protected function common($xml_a, $xml_b, $expected) {
    $schema_a = new SimpleXMLElement($xml_a);
    $schema_b = new SimpleXMLElement($xml_b);

    $ofs = new mock_output_file_segmenter();

    mysql5_diff_sequences::diff_sequences($ofs, $schema_a, $schema_b);

    $expected = trim(preg_replace('/--.*(\n\s*)?/','',$expected));
    $actual = trim(preg_replace('/--.*(\n\s*)?/','',$ofs->_get_output()));

    $this->assertEquals($expected, $actual);
  }
}