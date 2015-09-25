<?php
/**
 * Tests constraint gathering
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

use DBSteward\Definition\XML\XMLDatabaseDefinition;

class GetTableConstraintsTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    dbsteward::$quote_all_names = true;
  }
  /** @group pgsql8 */
  public function testGetsExplicitForeignKeysPgsql8() {
    dbsteward::set_sql_format('pgsql8');
    $this->_testGetsExplicitForeignKeys('("t1c1", "t1c2")', '"s2"."t2" ("t2c1", "t2c2")');
  }
  /** @group mysql5 */
  public function testGetsExplicitForeignKeysMysql5() {
    dbsteward::set_sql_format('mysql5');
    $this->_testGetsExplicitForeignKeys('(`t1c1`, `t1c2`)', '`t2` (`t2c1`, `t2c2`)');
  }
  private function _testGetsExplicitForeignKeys($local, $foreign) {
    $xml = <<<XML
<schema name="s1">
  <table name="t1" primaryKey="t1c1">
    <column name="t1c1"/>
    <column name="t1c2"/>

    <foreignKey
      columns="t1c1, t1c2"
      foreignSchema="s2"
      foreignTable="t2"
      foreignColumns="t2c1, t2c2"
      constraintName="fkey1"
      indexName="fkey1_idx"/>
  </table>
</schema>
<schema name="s2">
  <table name="t2" primaryKey="t2c1">
    <column name="t2c1" type="serial"/>
    <column name="t2c2" type="varchar(21)"/>
  </table>
</schema>
XML;
    
    $doc = $this->getDoc($xml);
    $constraints = format_constraint::get_table_constraints($doc, $doc->schema[0], $doc->schema[0]->table[0], 'foreignKey');

    $this->assertCount(1, $constraints);

    $c = $constraints[0];

    $this->assertEquals('fkey1', $c['name']);
    $this->assertEquals('s1', $c['schema_name']);
    $this->assertEquals('t1', $c['table_name']);
    $this->assertEquals('FOREIGN KEY', $c['type']);
    $this->assertEquals("$local REFERENCES $foreign", $c['definition']);
    $this->assertEquals(array(
      'schema' => $doc->schema[1],
      'table' => $doc->schema[1]->table[0],
      'column' => array(
        $doc->schema[1]->table[0]->column[0],
        $doc->schema[1]->table[0]->column[1]
      ),
      'name' => 'fkey1_idx',
      'references' => $foreign
    ), $c['foreign_key_data']);
  }

  /** @group pgsql8 */
  public function testExplicitFKeyDefaultSchemaPgsql8() {
    dbsteward::set_sql_format('pgsql8');
    $this->_testExplicitFKeyDefaultSchema('("t1c1", "t1c2")', '"s1"."t2" ("t2c1", "t2c2")');
  }
  /** @group mysql5 */
  public function testExplicitFKeyDefaultSchemaMysql5() {
    dbsteward::set_sql_format('mysql5');
    $this->_testExplicitFKeyDefaultSchema('(`t1c1`, `t1c2`)', '`t2` (`t2c1`, `t2c2`)');
  }
  private function _testExplicitFKeyDefaultSchema($local, $foreign) {
    $xml = <<<XML
<schema name="s1">
  <table name="t1" primaryKey="t1c1">
    <column name="t1c1"/>
    <column name="t1c2"/>

    <foreignKey
      columns="t1c1, t1c2"
      foreignTable="t2"
      foreignColumns="t2c1, t2c2"
      constraintName="fkey1"
      indexName="fkey1_idx"/>
  </table>
  <table name="t2" primaryKey="t2c1">
    <column name="t2c1" type="serial"/>
    <column name="t2c2" type="varchar(21)"/>
  </table>
</schema>
XML;
    
    $doc = $this->getDoc($xml);
    $constraints = format_constraint::get_table_constraints($doc, $doc->schema[0], $doc->schema[0]->table[0], 'foreignKey');

    $this->assertCount(1, $constraints);

    $c = $constraints[0];

    $this->assertEquals('fkey1', $c['name']);
    $this->assertEquals('s1', $c['schema_name']);
    $this->assertEquals('t1', $c['table_name']);
    $this->assertEquals('FOREIGN KEY', $c['type']);
    $this->assertEquals("$local REFERENCES $foreign", $c['definition']);
    $this->assertEquals(array(
      'schema' => $doc->schema[0],
      'table' => $doc->schema[0]->table[1],
      'column' => array(
        $doc->schema[0]->table[1]->column[0],
        $doc->schema[0]->table[1]->column[1]
      ),
      'name' => 'fkey1_idx',
      'references' => $foreign
    ), $c['foreign_key_data']);
  }

  /** @group pgsql8 */
  public function testExplicitFKeyDefaultColumnsPgsql8() {
    dbsteward::set_sql_format('pgsql8');
    $this->_testExplicitFKeyDefaultColumns('("t2c1", "t2c2")', '"s2"."t2" ("t2c1", "t2c2")');
  }
  /** @group mysql5 */
  public function testExplicitFKeyDefaultColumnsMysql5() {
    dbsteward::set_sql_format('mysql5');
    $this->_testExplicitFKeyDefaultColumns('(`t2c1`, `t2c2`)', '`t2` (`t2c1`, `t2c2`)');
  }
  private function _testExplicitFKeyDefaultColumns($local, $foreign) {
    $xml = <<<XML
<schema name="s1">
  <table name="t1" primaryKey="t2c1">
    <column name="t2c1"/>
    <column name="t2c2"/>

    <foreignKey
      columns="t2c1, t2c2"
      foreignSchema="s2"
      foreignTable="t2"
      constraintName="fkey1"
      indexName="fkey1_idx"/>
  </table>
</schema>
<schema name="s2">
  <table name="t2" primaryKey="t2c1">
    <column name="t2c1" type="serial"/>
    <column name="t2c2" type="varchar(21)"/>
  </table>
</schema>
XML;
    
    $doc = $this->getDoc($xml);
    $constraints = format_constraint::get_table_constraints($doc, $doc->schema[0], $doc->schema[0]->table[0], 'foreignKey');

    $this->assertCount(1, $constraints);

    $c = $constraints[0];

    $this->assertEquals('fkey1', $c['name']);
    $this->assertEquals('s1', $c['schema_name']);
    $this->assertEquals('t1', $c['table_name']);
    $this->assertEquals('FOREIGN KEY', $c['type']);
    $this->assertEquals("$local REFERENCES $foreign", $c['definition']);
    $this->assertEquals(array(
      'schema' => $doc->schema[1],
      'table' => $doc->schema[1]->table[0],
      'column' => array(
        $doc->schema[1]->table[0]->column[0],
        $doc->schema[1]->table[0]->column[1]
      ),
      'name' => 'fkey1_idx',
      'references' => $foreign
    ), $c['foreign_key_data']);
  }

  private function getDoc($schemaXml) {
    $xml = <<<XML
<dbsteward>
  $schemaXml
</dbsteward>
XML;
    
    return simplexml_load_string($xml);
  }
}