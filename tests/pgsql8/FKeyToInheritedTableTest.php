<?php
/**
 * DBSteward unit test to make sure that foreign keys to inherited columns are resolved correctly
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 * @group nodb
 */
class FKeyToInheritedTableTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = TRUE;
  }

  public function testFkeyToInheritedColumn() {
    $xml = <<<XML
<dbsteward>
  <database>
    <role>
      <application>test</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
  </database>
  <schema name="test" owner="ROLE_OWNER">
    <table name="parent" primaryKey="foo" owner="ROLE_OWNER">
      <column name="foo" type="varchar(255)"/>
    </table>
    <table name="child" inheritsSchema="test" inheritsTable="parent" primaryKey="foo" owner="ROLE_OWNER">
      <column name="bar" type="varchar(255)"/>
    </table>
  </schema>
  <schema name="other" owner="ROLE_OWNER">
    <table name="baz" primaryKey="footoo" owner="ROLE_OWNER">
      <column name="footoo" foreignSchema="test" foreignTable="child" foreignColumn="foo"/>
    </table>
  </schema>
</dbsteward>
XML;

    $doc = simplexml_load_string($xml);
    $schema = $doc->schema[1];
    $table = $schema->table;
    $column = $table->column;

    $fkey = pgsql8_constraint::foreign_key_lookup($doc, $schema, $table, $column);

    $this->assertEquals($doc->schema[0], $fkey['schema']);
    $this->assertEquals($doc->schema[0]->table[1], $fkey['table']);
    $this->assertEquals($doc->schema[0]->table[0]->column, $fkey['column']);
  }
}