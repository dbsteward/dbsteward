<?php
/**
 * DBSteward unit test for mysql5 permissions generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

class Mysql5PermissionsSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;

    $xml = <<<XML
<dbsteward>
  <database>
    <host>db-host</host>
    <name>dbsteward</name>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
  </database>
</dbsteward>
XML;
    $this->dbdoc = new SimpleXMLElement($xml);
  }

  public function testSchema() {
    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <grant operation="select" role="ROLE_OWNER"/>
</schema>
XML;
  
    // test single operation, single role
    $this->common($xml, NULL, "GRANT SELECT ON `public`.* TO deployment;");

    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <grant operation="select,update,delete,invalid" role="ROLE_OWNER,ROLE_APPLICATION"/>
</schema>
XML;
    
    // test multiple operations, multiple roles, and potentially invalid permissions
    $this->common($xml, NULL, "GRANT SELECT, UPDATE, DELETE, INVALID ON `public`.* TO deployment, dbsteward_phpunit_app;");
  }

  public function testTable() {
    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" owner="ROLE_OWNER">
    <grant operation="select,truncate" role="ROLE_OWNER"/>
  </table>
</schema>
XML;
    // note: truncate => drop
    $this->common($xml, 'table', "GRANT SELECT, DROP ON `public`.`table` TO deployment;");
  }

  public function testView() {
    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <view name="view" owner="ROLE_OWNER">
    <grant operation="select" role="ROLE_OWNER"/>
  </view>
</schema>
XML;
    $this->common($xml, 'view', "-- Ignoring permissions on view 'view' because MySQL uses SQL SECURITY DEFINER semantics");
  }

  public function testFunction() {
    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function owner="ROLE_OWNER" name="function">
    <grant operation="execute, alter" role="ROLE_OWNER"/>
  </function>
</schema>
XML;
    // note: alter => alter routine
    $this->common($xml, 'function', "GRANT EXECUTE, ALTER ROUTINE ON FUNCTION `function` TO deployment;");
  }

  public function testSequence() {
    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <sequence owner="ROLE_OWNER" name="sequence">
    <grant operation="select" role="ROLE_OWNER"/>
  </sequence>
</schema>
XML;
    // note: alter => alter routine
    $this->common($xml, 'sequence', "GRANT SELECT ON `public`.`__sequences` TO deployment;");
  }

  public function testPublicMacroRole() {
    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <grant operation="select" role="PUBLIC"/>
</schema>
XML;
    
    $this->common($xml, NULL, "GRANT SELECT ON `public`.* TO dbsteward_phpunit_app;");
  }

  private function common($schema_xml, $obj=NULL, $expected) {
    $schema = new SimpleXMLElement($schema_xml);
    $node_obj = $obj? $schema->{$obj} : $schema;
    $actual = mysql5_permission::get_permission_sql($this->dbdoc, $schema, $node_obj, $node_obj->grant);
    $this->assertEquals($expected,trim($actual));
  }
}
?>
