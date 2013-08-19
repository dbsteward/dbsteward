<?php
/**
 * DBSteward unit test for mysql5 trigger diffing
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
class Mysql5TriggerDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;

    $db_doc_xml = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
      <customRole>SOMEBODY</customRole>
    </role>
  </database>
</dbsteward>
XML;
    
    dbsteward::$old_database = new SimpleXMLElement($db_doc_xml);
    dbsteward::$new_database = new SimpleXMLElement($db_doc_xml);
  }

  private $xml_0 = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" />
</schema>
XML;

  private $xml_1 = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <trigger name="trigger_a" sqlFormat="mysql5" when="before" event="insert" table="table" function="EXECUTE stuff"/>
  <table name="table" />
</schema>
XML;

  private $xml_1_timing = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <trigger name="trigger_a" sqlFormat="mysql5" when="after" event="insert" table="table" function="EXECUTE stuff"/>
  <table name="table" />
</schema>
XML;

  private $xml_1_event = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <trigger name="trigger_a" sqlFormat="mysql5" when="before" event="update" table="table" function="EXECUTE stuff"/>
  <table name="table" />
</schema>
XML;
  
  private $xml_1_table = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <trigger name="trigger_a" sqlFormat="mysql5" when="before" event="insert" table="another" function="EXECUTE stuff"/>
  <table name="another" />
  <table name="table" />
</schema>
XML;

  private $xml_1_def = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <trigger name="trigger_a" sqlFormat="mysql5" when="before" event="insert" table="table" function="EXECUTE otherstuff"/>
  <table name="table" />
</schema>
XML;

  private $xml_3 = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <trigger name="trigger_a" sqlFormat="mysql5" when="before" event="insert" table="table" function="EXECUTE stuff"/>
  <trigger name="trigger_b" sqlFormat="mysql5" when="after" event="update" table="table" function="EXECUTE stuff"/>
  <trigger name="trigger_c" sqlFormat="mysql5" when="after" event="delete" table="table" function="EXECUTE stuff"/>
  <table name="table" />
</schema>
XML;

  private $xml_3_alt = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <trigger name="trigger_a" sqlFormat="mysql5" when="after" event="insert" table="table" function="EXECUTE stuff"/>
  <trigger name="trigger_b" sqlFormat="mysql5" when="after" event="update" table="table" function="EXECUTE stuff"/>
  <trigger name="trigger_c" sqlFormat="mysql5" when="after" event="delete" table="table" function="EXECUTE stuff"/>
  <table name="table" />
</schema>
XML;

  private $create_a = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_a`;
CREATE TRIGGER `public`.`trigger_a` BEFORE INSERT ON `public`.`table`
FOR EACH ROW EXECUTE stuff;
SQL;
  private $create_a_timing = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_a`;
CREATE TRIGGER `public`.`trigger_a` AFTER INSERT ON `public`.`table`
FOR EACH ROW EXECUTE stuff;
SQL;
  private $create_a_event = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_a`;
CREATE TRIGGER `public`.`trigger_a` BEFORE UPDATE ON `public`.`table`
FOR EACH ROW EXECUTE stuff;
SQL;
  private $create_a_table = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_a`;
CREATE TRIGGER `public`.`trigger_a` BEFORE INSERT ON `public`.`another`
FOR EACH ROW EXECUTE stuff;
SQL;
  private $create_a_def = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_a`;
CREATE TRIGGER `public`.`trigger_a` BEFORE INSERT ON `public`.`table`
FOR EACH ROW EXECUTE otherstuff;
SQL;
  private $drop_a = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_a`;
SQL;
  private $create_b = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_b`;
CREATE TRIGGER `public`.`trigger_b` AFTER UPDATE ON `public`.`table`
FOR EACH ROW EXECUTE stuff;
SQL;
  private $drop_b = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_b`;
SQL;
  private $create_c = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_c`;
CREATE TRIGGER `public`.`trigger_c` AFTER DELETE ON `public`.`table`
FOR EACH ROW EXECUTE stuff;
SQL;
  private $drop_c = <<<SQL
DROP TRIGGER IF EXISTS `public`.`trigger_c`;
SQL;

  public function testNoneToNone() {
    $this->common($this->xml_0, $this->xml_0, '');
  }

  public function testSameToSame() {
    $this->common($this->xml_1, $this->xml_1, '');
  }

  public function testAddNew() {
    $this->common($this->xml_0, $this->xml_1, $this->create_a);
  }

  public function testAddSome() {
    $this->common($this->xml_1, $this->xml_3, "$this->create_b\n\n$this->create_c");
  }

  public function testDropAll() {
    $this->common($this->xml_1, $this->xml_0, $this->drop_a);
  }

  public function testDropSome() {
    $this->common($this->xml_3, $this->xml_1, "$this->drop_b\n\n$this->drop_c");
  }

  public function testChangeOne() {
    // changing a trigger will not cause a drop, because the drop is baked into the creation DDL
    $this->common($this->xml_1, $this->xml_1_timing, "$this->create_a_timing", "change timing");
    $this->common($this->xml_1, $this->xml_1_event, "$this->create_a_event", "change event");
    $this->common($this->xml_1, $this->xml_1_table, "$this->create_a_table", "change table");
    $this->common($this->xml_1, $this->xml_1_def, "$this->create_a_def", "change definition");
  }

  public function testAddSomeAndChange() {
    $this->common($this->xml_1, $this->xml_3_alt, "$this->create_a_timing\n\n$this->create_b\n\n$this->create_c");
  }

  public function testDropSomeAndChange() {
    $this->common($this->xml_3_alt, $this->xml_1, "$this->drop_b\n\n$this->drop_c\n\n$this->create_a");
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

    

    // shouldn't create any extra triggers
    $this->common($new, $new, '');

    // should create a new trigger
    $expected = <<<SQL
DROP TRIGGER IF EXISTS `test0`.`__test0_table_id_serial_trigger`;
CREATE TRIGGER `test0`.`__test0_table_id_serial_trigger` BEFORE INSERT ON `test0`.`table`
FOR EACH ROW SET NEW.`id` = COALESCE(NEW.`id`, nextval('__test0_table_id_serial_seq'));
SQL;
    $this->common($old, $new, $expected);

    // dropping the table drops the trigger as well
    $this->common($new, $old, '');

    $renamed = <<<XML
<schema name="test0" owner="NOBODY">
  <table name="table" owner="NOBODY">
    <column name="newid" type="serial" oldColumnName="id"/>
  </table>
</schema>
XML;

    // renaming should drop the old trigger, create the new
    $expected = <<<SQL
DROP TRIGGER IF EXISTS `test0`.`__test0_table_id_serial_trigger`;

DROP TRIGGER IF EXISTS `test0`.`__test0_table_newid_serial_trigger`;
CREATE TRIGGER `test0`.`__test0_table_newid_serial_trigger` BEFORE INSERT ON `test0`.`table`
FOR EACH ROW SET NEW.`newid` = COALESCE(NEW.`newid`, nextval('__test0_table_newid_serial_seq'));
SQL;
    
    $this->common($new, $renamed, $expected);
  }

  protected function common($xml_a, $xml_b, $expected, $message = NULL) {
    $schema_a = new SimpleXMLElement($xml_a);
    $schema_b = new SimpleXMLElement($xml_b);

    $ofs = new mock_output_file_segmenter();

    mysql5_diff_triggers::diff_triggers($ofs, $schema_a, $schema_b);

    $actual = trim($ofs->_get_output());
    
    $this->assertEquals($expected, $actual, $message);
  }
}
?>
