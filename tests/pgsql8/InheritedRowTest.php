<?php
/**
 * DBSteward unit test to make sure that inherited tables can define rows
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Adam Jette <jettea46@yahoo.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class InheritedRowTest extends PHPUnit_Framework_TestCase {

  private $xml_parent = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="parent_table" owner="ROLE_OWNER" primaryKey="pk">
    <column name="pk" type="int"/>
    <column name="col1" type="char(10)"/>
  </table>
</schema>
XML;

  private $xml_child = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="child_table" owner="ROLE_OWNER" primaryKey="pkchild">
    <column name="pkchild" type="int"/>
    <column name="x" type="int" />
    <rows columns="pkchild, col1">
      <row>
        <col>99999999999999</col>
        <col>techmology</col>
      </row>
    </rows>
  </table>
</schema>
XML;

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = TRUE;
  }
  
  public function testDefineRows() {
    $schema_parent = simplexml_load_string($this->xml_parent);
    $schema_child = simplexml_load_string($this->xml_child);

    xml_parser::composite_doc($schema_parent, $schema_child);
  }
}
