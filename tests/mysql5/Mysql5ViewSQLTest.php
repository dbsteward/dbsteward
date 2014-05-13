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
class Mysql5ViewSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
    
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
    dbsteward::$new_database = new SimpleXMLElement($db_doc_xml);
  }

  public function testSimple() {
    $xml = <<<XML
<schema name="public">
  <view name="view" owner="SOMEBODY" description="Description goes here">
    <viewQuery sqlFormat="mysql5">SELECT * FROM sometable</viewQuery>
  </view>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
CREATE OR REPLACE DEFINER = SOMEBODY SQL SECURITY DEFINER VIEW `view`
  AS SELECT * FROM sometable;
SQL;
    $actual = trim(preg_replace('/--.*\n?/','',mysql5_view::get_creation_sql($schema, $schema->view)));
    $this->assertEquals($expected, $actual);

    $expected = "DROP VIEW IF EXISTS `view`;";
    $actual = trim(preg_replace('/--.*\n?/','',mysql5_view::get_drop_sql($schema, $schema->view)));
    $this->assertEquals($expected, $actual);
  }

  public function testOtherFormatQueries() {
    $xml = <<<XML
<schema name="public">
  <view name="view" owner="SOMEBODY" description="Description goes here">
    <viewQuery sqlFormat="mysql5">SELECT * FROM mysql5table</viewQuery>
    <viewQuery sqlFormat="pgsql8">SELECT * FROM pgsql8table</viewQuery>
  </view>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
CREATE OR REPLACE DEFINER = SOMEBODY SQL SECURITY DEFINER VIEW `view`
  AS SELECT * FROM mysql5table;
SQL;
    $actual = trim(preg_replace('/--.*\n?/','',mysql5_view::get_creation_sql($schema, $schema->view)));
    $this->assertEquals($expected, $actual);

    $expected = "DROP VIEW IF EXISTS `view`;";
    $actual = trim(preg_replace('/--.*\n?/','',mysql5_view::get_drop_sql($schema, $schema->view)));
    $this->assertEquals($expected, $actual);

    $xml = <<<XML
<schema name="public">
  <view name="view" owner="SOMEBODY" description="Description goes here">
    <viewQuery sqlFormat="pgsql8">SELECT * FROM pgsql8table</viewQuery>
  </view>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    try {
      mysql5_view::get_creation_sql($schema, $schema->view);
    }
    catch ( Exception $ex ) {
      if ( stripos($ex->getMessage(),'failed to find viewquery') === FALSE ) {
        throw $ex;
      }
      return;
    }
    $this->fail("Expected an exception because there was no mysql5 viewQuery");
  }
}
?>
