<?php
/**
 * Test Slony ID --slonyid numbering tools
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class SlonyIdNumberTest extends PHPUnit_Framework_TestCase {
  
  protected $slonyidxml = <<<XML
<dbsteward>
  <database>
    <sqlformat>pgsql8</sqlformat>
    <role>
      <application>application</application>
      <owner>dba</owner>
      <replication>slony</replication>
      <readonly>readonly</readonly>
    </role>
    <slony clusterName="aim">
      <slonyNode id="1" comment="Master" dbPassword="wonk" dbUser="aim_slony" dbHost="db00" dbName="mrh"/>
      <slonyNode id="2" comment="Replica" dbPassword="wonk" dbUser="aim_slony" dbHost="db01" dbName="mrh"/>
      <slonyReplicaSet id="1" comment="only set" originNodeId="1" upgradeSetId="2">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
      </slonyReplicaSet>
    </slony>
  </database>
  <schema name="someapp" owner="ROLE_OWNER">
    <table name="users" owner="ROLE_OWNER" primaryKey="user_id" slonyId="10">
      <column name="user_id" type="bigserial"/>
      <column name="user_name" type="varchar(300)"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <!-- as a control, the groups table and column has all slony IDs specified -->
    <table name="groups" owner="ROLE_OWNER" primaryKey="group_id" slonyId="20">
      <column name="group_id" type="bigserial" slonyId="20"/>
      <column name="group_name" type="varchar(300)"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log" owner="ROLE_OWNER" primaryKey="log_id" >
      <column name="log_id" type="bigserial"/>
      <column name="log_type" type="varchar(32)"/>
      <column name="log_date" type="timestamp with time zone"/>
      <column name="log_entry" type="text"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
  </schema>
</dbsteward>
XML;
  
  protected $in_doc;
  protected $slonyid_doc;
  
  public function setUp() {
    parent::setUp();
    dbsteward::set_sql_format('pgsql8');
    
    // reset these flags before each test
    pgsql8_diff::$as_transaction = TRUE;
    dbsteward::$require_slony_set_id = FALSE;
    dbsteward::$require_slony_id = FALSE;
    dbsteward::$generate_slonik = FALSE;
    dbsteward::$slonyid_set_value = 1;
    dbsteward::$slonyid_start_value = 1;
    
    // clear these before each test so we don't run into conflicts
    pgsql8::$table_slony_ids = array();
    pgsql8::$sequence_slony_ids = array();
    pgsql8_diff::$new_table_dependency = null;
    pgsql8_diff::$old_table_dependency = null;
    
    // rest test fixtures
    $this->in_doc = null;
    $this->slonyid_doc = null;
  }
  
  /**
   * test the same thing that case dbsteward::MODE_XML_SLONY_ID: does in dbsteward class
   */
  protected function do_slony_numbering() {
    // just load XML straight up
    $this->in_doc = simplexml_load_string($this->slonyidxml);

    // process XML with slonyid_number() to then check results
    $this->slonyid_doc = xml_parser::slonyid_number($this->in_doc);
  }
  
  public function testNoRequireSlonySetId() {
    // make sure if require_slony_set_id is FALSE then there are no changes
    dbsteward::$require_slony_set_id = FALSE;
    $this->do_slony_numbering();
    $in_doc_xml = xml_parser::format_xml($this->in_doc->asXML());
    $slonyid_doc_xml = xml_parser::format_xml($this->slonyid_doc->asXML());
    $this->assertEquals($in_doc_xml, $slonyid_doc_xml);
  }
  
  public function testNoRequireSlonyId() {
    // make sure if require_slony_id is FALSE then there are no changes
    dbsteward::$require_slony_id = FALSE;
    $this->do_slony_numbering();
    $in_doc_xml = xml_parser::format_xml($this->in_doc->asXML());
    $slonyid_doc_xml = xml_parser::format_xml($this->slonyid_doc->asXML());
    $this->assertEquals($in_doc_xml, $slonyid_doc_xml);
  }
  
  public function testRequireSlonySetId() {
    // make sure if require_slony_set_id is TRUE then there are changes
    dbsteward::$require_slony_set_id = TRUE;
    $this->do_slony_numbering();
    $in_doc_xml = xml_parser::format_xml($this->in_doc->asXML());
    $slonyid_doc_xml = xml_parser::format_xml($this->slonyid_doc->asXML());
    $this->assertNotEquals($in_doc_xml, $slonyid_doc_xml);
  }
  
  public function testRequireSlonyId() {
    // make sure if require_slony_id is TRUE then there are no changes
    dbsteward::$require_slony_id = TRUE;
    $this->do_slony_numbering();
    $in_doc_xml = xml_parser::format_xml($this->in_doc->asXML());
    $slonyid_doc_xml = xml_parser::format_xml($this->slonyid_doc->asXML());
    $this->assertNotEquals($in_doc_xml, $slonyid_doc_xml);
  }
  
  public function testSlonySetId() {
    // make sure if require_slony_set_id is TRUE then there are changes
    dbsteward::$require_slony_set_id = TRUE;
    dbsteward::$slonyid_set_value = 5001;
    $this->do_slony_numbering();
    
    // test that someapp schema now has slonySetId
    $out_schema = dbx::get_schema($this->slonyid_doc, 'someapp');
    $this->assertEquals('5001', $out_schema['slonySetId']);
    
    // test that users table now has slonySetId 5001
    $out_table_users = dbx::get_table($out_schema, 'users');
    $this->assertEquals('5001', $out_table_users['slonySetId']);
    
    // test that log table now has slonySetId 5001
    $out_table_log = dbx::get_table($out_schema, 'log');
    $this->assertEquals('5001', $out_table_log['slonySetId']);
  }
  
  public function testSlonyId() {
    // make sure if require_slony_set_id is TRUE then there are changes
    dbsteward::$require_slony_id = TRUE;
    dbsteward::$slonyid_start_value = 9001;
    $this->do_slony_numbering();
    
    $out_schema = dbx::get_schema($this->slonyid_doc, 'someapp');

    // test that users table column user_id with type serial got set to slonyId 9001
    $out_table_users = dbx::get_table($out_schema, 'users');
    $out_column_users_user_id = dbx::get_table_column($out_table_users, 'user_id');
    $this->assertEquals('9001', $out_column_users_user_id['slonyId']);
    
    // test that log table now has slonyId 9002
    $out_table_log = dbx::get_table($out_schema, 'log');
    $this->assertEquals('9002', $out_table_log['slonyId']);
    
    // test that log table log_id serial now has slonyId 9003
    $out_column_log_log_id = dbx::get_table_column($out_table_log, 'log_id');
    $this->assertEquals('9003', $out_column_log_log_id['slonyId']);
  }
  
}
