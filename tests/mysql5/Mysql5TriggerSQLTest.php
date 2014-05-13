<?php
/**
 * DBSteward unit test for mysql5 trigger ddl generation
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
class Mysql5TriggerSQLTest extends PHPUnit_Framework_TestCase {

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

  public function testSimple() {
    $xml = <<<XML
<schema name="public">
  <table name="table"></table>
  <trigger name="trigger" sqlFormat="mysql5" table="table" when="before" event="insert" function="EXECUTE xyz"/>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
DROP TRIGGER IF EXISTS `trigger`;
CREATE TRIGGER `trigger` BEFORE INSERT ON `table`
FOR EACH ROW EXECUTE xyz;
SQL;

    $actual = trim(mysql5_trigger::get_creation_sql($schema, $schema->trigger));
    $this->assertEquals($expected, $actual);

    $expected = "DROP TRIGGER IF EXISTS `trigger`;";
    $actual = trim(mysql5_trigger::get_drop_sql($schema, $schema->trigger));
    $this->assertEquals($expected, $actual);
  }

  public function testMultipleEvents() {
    $xml = <<<XML
<schema name="public">
  <table name="table"></table>
  <trigger name="trigger" sqlFormat="mysql5" table="table" when="before" event="insert,update,delete" function="EXECUTE xyz"/>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
DROP TRIGGER IF EXISTS `trigger_INSERT`;
DROP TRIGGER IF EXISTS `trigger_UPDATE`;
DROP TRIGGER IF EXISTS `trigger_DELETE`;
CREATE TRIGGER `trigger_INSERT` BEFORE INSERT ON `table`
FOR EACH ROW EXECUTE xyz;
CREATE TRIGGER `trigger_UPDATE` BEFORE UPDATE ON `table`
FOR EACH ROW EXECUTE xyz;
CREATE TRIGGER `trigger_DELETE` BEFORE DELETE ON `table`
FOR EACH ROW EXECUTE xyz;
SQL;
    $actual = trim(preg_replace('/--.*\n?/','',mysql5_trigger::get_creation_sql($schema, $schema->trigger)));
    $this->assertEquals($expected, $actual);

    $expected = <<<SQL
DROP TRIGGER IF EXISTS `trigger_INSERT`;
DROP TRIGGER IF EXISTS `trigger_UPDATE`;
DROP TRIGGER IF EXISTS `trigger_DELETE`;
SQL;
    $actual = trim(preg_replace('/--.*\n?/','',mysql5_trigger::get_drop_sql($schema, $schema->trigger)));
    $this->assertEquals($expected, $actual);
  }

  public function testOtherFormats() {
    $xml = <<<XML
<schema name="public">
  <table name="table"></table>
  <trigger name="trigger" sqlFormat="pgsql8" table="table" when="before" event="insert" function="EXECUTE xyz"/>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = "-- Ignoring pgsql8 trigger 'trigger'";
    $actual = trim(mysql5_trigger::get_creation_sql($schema, $schema->trigger));
    $this->assertEquals($expected, $actual);

    $expected = "-- Ignoring pgsql8 trigger 'trigger'";
    $actual = trim(mysql5_trigger::get_drop_sql($schema, $schema->trigger));
    $this->assertEquals($expected, $actual);
  }
}
?>
