<?php
/**
 * Tests that columns with foreignTable attributes but not foreignSchema attributes default to the current schema
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/dbstewardUnitTestBase.php';

class OptionalForeignSchemaTest extends \PHPUnit_Framework_TestCase {
  /** @group pgsql8 */
  public function testDbxForeignKeyPgsql8() {
    dbsteward::set_sql_format('pgsql8');
    $this->_testDbxForeignKey();
  }
  /** @group mysql5 */
  public function testDbxForeignKeyMysql5() {
    dbsteward::set_sql_format('mysql5');
    $this->_testDbxForeignKey();
  }
  private function _testDbxForeignKey() {
    $doc = simplexml_load_string($this->xml);
    $schema = $doc->schema;
    $table1 = $schema->table[0];
    $table1_t1id = $table1->column[0];
    $table1_t2id = $table1->column[1];
    $table2 = $schema->table[1];
    $table2_t2id = $table2->column[0];
    dbx::foreign_key($doc, $schema, $table1, $table1_t2id, $foreign);

    $this->assertEquals($schema, $foreign['schema']);
    $this->assertEquals($table2, $foreign['table']);
    $this->assertEquals($table2_t2id, $foreign['column']);
  }

  /** @group pgsql8 */
  public function testXmlParserTableHasDepPgsql8() {
    dbsteward::set_sql_format('pgsql8');
    $this->_testXmlParserTableHasDep();
  }
  /** @group mysql5 */
  public function testXmlParserTableHasDepMysql5() {
    dbsteward::set_sql_format('mysql5');
    $this->_testXmlParserTableHasDep();
  }
  private function _testXmlParserTableHasDep() {
    $doc = simplexml_load_string($this->xml);
    $schema = $doc->schema;
    $table1 = array('schema' => $schema, 'table' => $schema->table[0]);
    $table2 = array('schema' => $schema, 'table' => $schema->table[1]);

    $this->assertTrue(xml_parser::table_has_dependency($table1, $table2));
  }

  /** @group pgsql8 */
  public function testConstraintFKLookupPgsql8() {
    dbsteward::set_sql_format('pgsql8');
    $this->_testConstraintFKLookup();
  }
  /** @group mysql5 */
  public function testConstraintFKLookupMysql5() {
    dbsteward::set_sql_format('mysql5');
    $this->_testConstraintFKLookup();
  }
  private function _testConstraintFKLookup() {
    $doc = simplexml_load_string($this->xml);
    $schema = $doc->schema;
    $table1 = $schema->table[0];
    $table1_t1id = $table1->column[0];
    $table1_t2id = $table1->column[1];
    $table2 = $schema->table[1];
    $table2_t2id = $table2->column[0];
    $foreign = format_constraint::foreign_key_lookup($doc, $schema, $table1, $table1_t2id);

    $this->assertEquals($schema, $foreign['schema']);
    $this->assertEquals($table2, $foreign['table']);
    $this->assertEquals($table2_t2id, $foreign['column']);
  }

  private $xml = <<<XML
<dbsteward>
  <database>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
  </database>
  <schema name="test_schema" owner="ROLE_OWNER">
    <table name="table1" owner="ROLE_OWNER" primaryKey="table1_id">
      <column name="table1_id" type="bigserial"/>
      <column name="table2_id" foreignTable="table2"/>
    </table>
    <table name="table2" owner="ROLE_OWNER" primaryKey="table2_id">
      <column name="table2_id" type="bigserial"/>
    </table>
  </schema>
</dbsteward>
XML;
}