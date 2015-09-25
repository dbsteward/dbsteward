<?php
/**
 * DBSteward unit test for pgsql8 character escaping during data diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class EscapeCharTypeTest extends PHPUnit_Framework_TestCase {

  private $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="i_test" owner="ROLE_OWNER" primaryKey="pk">
    <column name="pk" type="int"/>
    <column name="col1" type="char(10)"/>
    <rows columns="pk, col1">
      <row>
        <col>1</col>
        <col>hi</col>
      </row>
    </rows>
  </table>
</schema>
XML;

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = TRUE;
  }
  
  public function testCharTypeColumnsEscaped() {
    $schema = simplexml_load_string($this->xml);
    $table = $schema->table;
    
    $expected = 'INSERT INTO "public"."i_test" ("pk", "col1") VALUES (1, E\'hi\');';

    $actual = trim(pgsql8_diff_tables::get_data_sql(NULL, NULL, $schema, $table, FALSE));

    $this->assertEquals($expected, $actual);
  }
}
