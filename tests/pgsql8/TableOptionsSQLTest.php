<?php
/**
 * DBSteward unit test for pgsql8 tableOption DDL generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

class TableOptionsSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  public function testCreateTable() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" description="test description">
    <tableOption sqlFormat="pgsql8" name="on commit" value="PRESERVE ROWS"/>
    <tableOption sqlFormat="pgsql8" name="tablespace" value="schmableschpace"/>
    <tableOption sqlFormat="mysql5" name="auto_increment" value="5"/>
    <column name="id" type="int"/>
    <column name="foo" type="int"/>
  </table>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);


    $expected = <<<SQL
CREATE TABLE "public"."test" (
	"id" int,
	"foo" int
)
ON COMMIT PRESERVE ROWS
TABLESPACE schmableschpace;
COMMENT ON TABLE "public"."test" IS 'test description';
SQL;
    $actual = pgsql8_table::get_creation_sql($schema, $schema->table);
    $this->assertEquals($expected, trim($actual));
  }

}
?>