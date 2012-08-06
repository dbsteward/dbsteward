<?php
/**
 * Regression test for pulling pgsql8_column::get_serial_start_dml() up to sql99_column
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

class PullUpSerialStartDMLTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  public function testPgsql8() {
    dbsteward::set_sql_format('pgsql8');
    $this->common("SELECT setval(pg_get_serial_sequence('public.table', 'column'), 5, TRUE);");
  }

  public function testMssql10() {
    dbsteward::set_sql_format('mssql10');
    $this->common("SELECT setval(pg_get_serial_sequence('public.table', 'column'), 5, TRUE);");
  }

  public function testMysql5() {
    dbsteward::set_sql_format('mysql5');
    $this->common("SELECT setval('__public_table_column_serial_seq', 5, TRUE);");
  }

  private function common($expected) {
    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" owner="ROLE_OWNER">
    <column name="column" type="serial" serialStart="5"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    // sanity check magic format loader
    $this->assertEquals(dbsteward::get_sql_format().'_column', get_class(new format_column));

    $expected = "-- serialStart 5 specified for public.table.column\n$expected\n";
    $actual = format_column::get_serial_start_dml($schema, $schema->table, $schema->table->column);

    $this->assertEquals($expected, $actual);
  }
}