<?php
/**
 * DBSteward unit test for mysql5 index diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

/**
 * @group mysql5
 */
class Mysql5IndexDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
  }

  private $xml_0 = <<<XML
<schema name="test0" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a"/>
  </table>
</schema>
XML;

  private $xml_1 = <<<XML
<schema name="test1" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a"/>
    <index name="test_idxa">
      <indexDimension name="a_1">a</indexDimension>
    </index>
  </table>
</schema>
XML;

  private $xml_1a = <<<XML
<schema name="test1a" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a"/>
    <index name="test_idxa" using="hash">
      <indexDimension name="a_1">a</indexDimension>
    </index>
  </table>
</schema>
XML;

  private $xml_1b = <<<XML
<schema name="test1b" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a"/>
    <index name="test_idxa" unique="true">
      <indexDimension name="a_1">a</indexDimension>
    </index>
  </table>
</schema>
XML;

  private $xml_3 = <<<XML
<schema name="test3" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a"/>
    <column name="b"/>
    <column name="c"/>
    <index name="test_idxa">
      <indexDimension name="a_1">a</indexDimension>
    </index>
    <index name="test_idxb">
      <indexDimension name="b_2">b</indexDimension>
    </index>
    <index name="test_idxc">
      <indexDimension name="c_3">c</indexDimension>
    </index>
  </table>
</schema>
XML;

  public function testNoneToNone() {
    $this->common($this->xml_0, $this->xml_0, "");
  }

  public function testSameToSame() {
    $this->common($this->xml_3, $this->xml_3, "");
  }

  public function testAddNew() {
    $expected = <<<SQL
ALTER TABLE `test`
  ADD INDEX `test_idxa` (`a`),
  ADD INDEX `test_idxb` (`b`),
  ADD INDEX `test_idxc` (`c`);
SQL;
    $this->common($this->xml_0, $this->xml_3, $expected);
  }

  public function testAddSome() {
    $expected = <<<SQL
ALTER TABLE `test`
  ADD INDEX `test_idxb` (`b`),
  ADD INDEX `test_idxc` (`c`);
SQL;
    $this->common($this->xml_1, $this->xml_3, $expected);
  }

  public function testDropAll() {
    $expected = <<<SQL
ALTER TABLE `test`
  DROP INDEX `test_idxa`,
  DROP INDEX `test_idxb`,
  DROP INDEX `test_idxc`;
SQL;
    $this->common($this->xml_3, $this->xml_0, $expected);
  }

  public function testDropSome() {
    $expected = <<<SQL
ALTER TABLE `test`
  DROP INDEX `test_idxb`,
  DROP INDEX `test_idxc`;
SQL;
    $this->common($this->xml_3, $this->xml_1, $expected);
  }

  public function testChangeOne() {
    $expected = <<<SQL
ALTER TABLE `test`
  DROP INDEX `test_idxa`,
  ADD UNIQUE INDEX `test_idxa` (`a`);
SQL;
    $this->common($this->xml_1a, $this->xml_1b, $expected);
  }

  public function testAddSomeAndChange() {
    $expected = <<<SQL
ALTER TABLE `test`
  DROP INDEX `test_idxa`,
  ADD INDEX `test_idxa` (`a`),
  ADD INDEX `test_idxb` (`b`),
  ADD INDEX `test_idxc` (`c`);
SQL;
    $this->common($this->xml_1a, $this->xml_3, $expected);
  }

  public function testDropSomeAndChange() {
    $expected = <<<SQL
ALTER TABLE `test`
  DROP INDEX `test_idxa`,
  DROP INDEX `test_idxb`,
  DROP INDEX `test_idxc`,
  ADD INDEX `test_idxa` (`a`) USING HASH;
SQL;
    $this->common($this->xml_3, $this->xml_1a, $expected);
  }

  public function common($a, $b, $expected) {
    $schema_a = new SimpleXMLElement($a);
    $schema_b = new SimpleXMLElement($b);

    $ofs = new mock_output_file_segmenter();

    mysql5_diff_indexes::diff_indexes($ofs, $schema_a, $schema_b);

    $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));

    $this->assertEquals($expected, $actual);
  }
}
?>
