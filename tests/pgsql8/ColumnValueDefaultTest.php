<?php
/**
 * Tests functionality of pgsql8::column_value_default()
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class ColumnValueDefaultTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
  }

  public function testNullColumnReturnsNull() {
    $value = $this->getColumnValue(
      '<column name="foo" type="text"/>',
      '<col null="true">adsf</col>');

    $this->assertEquals('NULL', $value, 'Expected NULL if null="true" is specified');
  }

  public function testEmptyColumnGivesEmptyString() {
    $value = $this->getColumnValue(
      '<column name="foo" type="text"/>',
      '<col empty="true">asdf</col>');

    $expected = pgsql8::E_ESCAPE ? "E''" : "''";

    $this->assertEquals($expected, $value, "Expected $expected if empty=\"true\" is specified");
  }

  public function testSQLGivesLiteralButWrapped() {
    $value = $this->getColumnValue(
      '<column name="foo" type="text"/>',
      '<col sql="true">some_function()</col>');

    $this->assertEquals('(some_function())', $value, 'Expected literal column value wrapped in parens if sql="true"');
  }

  public function testSQLWithDefaultDoesNotWrap() {
    $value = $this->getColumnValue(
      '<column name="foo" type="text"/>',
      '<col sql="true">DEFAULT</col>');

    $this->assertEquals('DEFAULT', $value, 'Expected DEFAULT without wrapping parens if sql="true" and column value is DEFAULT');
  }

  public function testUsesColumnDefaultIfEmpty() {
    $value = $this->getColumnValue(
      '<column name="foo" type="text" default="asdf"/>',
      '<col></col>');

    $this->assertEquals('asdf', $value, 'Expected column default if data was empty');
  }

  public function testUsesLiteralForInt() {
    $value = $this->getColumnValue(
      '<column name="foo" type="int"/>',
      '<col>42</col>');

    $this->assertEquals('42', $value, 'Expected literal int value for integers');
  }

  public function testQuotesStrings() {
    $value = $this->getColumnValue(
      '<column name="foo" type="text"/>',
      '<col>asdf</col>');

    $expected = pgsql8::E_ESCAPE ? "E'asdf'" : "'asdf'";
    $this->assertEquals($expected, $value, "Expected $expected for a string value");
  }

  private function getColumnValue($def, $data) {
    $defNode = simplexml_load_string($def);
    $defNodeName = $defNode['name'];

    $schemaXml = <<<XML
<schema name="test_schema">
  <table name="test_table" primaryKey="$defNodeName">
    $def
    <rows columns="$defNodeName">
      <row>$data</row>
    </rows>
  </table>
</schema>
XML;
    
    $schema = simplexml_load_string($schemaXml);
    return pgsql8::column_value_default($schema, $schema->table, $defNodeName, $schema->table->rows->row->col);
  }
}