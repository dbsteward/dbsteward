<?php
/**
 * 
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Rusty Hamilton <rusty@shrub3.net>
 */

require_once 'PHPUnit/Framework/TestCase.php';

require_once __DIR__ . '/../dbstewardUnitTestBase.php';
require_once(dirname(__FILE__) . '/../../lib/DBSteward/sql_format/sql99/sql99.php');
require_once(dirname(__FILE__) . '/../../lib/DBSteward/sql_format/pgsql8/pgsql8.php');

class IsolatedSequenceTest extends dbstewardUnitTestBase {
  
  protected $xml_content_a =  <<<XML
<dbsteward>
  <database>
    <host>db-host</host>
    <name>dbsteward</name>
    <role>
      <application>deployment</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <sequence name="test_seq" start="1" inc="1" max="15" cycle="false" cache="1" owner="ROLE_OWNER">
      <grant operation="USAGE,SELECT,UPDATE" role="ROLE_APPLICATION"/>
    </sequence>
    <table name="user" owner="ROLE_OWNER" primaryKey="user_id" description="user logins">
      <column name="user_id" type="int" default="nextval('test_seq')"/>
      <column name="user_name" type="varchar(100)" null="false"/>
      <column name="user_role" type="varchar(100)" null="false"/>
      <column name="user_create_date" type="timestamp with time zone" null="false" default="NOW()"/>
      <grant role="ROLE_APPLICATION" operation="SELECT, INSERT, UPDATE"/>
      <rows columns="user_id, user_name, user_role">
        <tabrow>1	toor	super_admin</tabrow>
      </rows>
    </table>
  </schema>
  <schema name="testschema" owner="ROLE_OWNER">
    <table name="testtable" owner="ROLE_OWNER" primaryKey="idcol" description="test">
      <column name="idcol" type="bigint"/>
      <column name="testtext" type="text" null="false"/>
      <grant role="ROLE_APPLICATION" operation="SELECT, INSERT"/>
    </table>
  </schema>
</dbsteward>
XML;
  
  public function setUp() {
    $this->output_prefix = dirname(__FILE__) . '/../testdata/unit_test_xml_a';
    parent::setUp();
    $role_check = "SELECT 1 AS role_check FROM pg_roles WHERE rolname = 'test_user'";
    $role_results = $this->query_db($role_check);
    if (empty($role_results)) {

      $role_sql = "CREATE ROLE test_user WITH LOGIN";
      $this->query_db($role_sql);

    }
   
  }
  
  public function tearDown() {
    //$this->pgsql8->close_connection();
  }
  
  protected function query_db($sql) {
    $host = dbsteward_pgsql8_connection::get_dbhost();
    $port = dbsteward_pgsql8_connection::get_dbport();
    $database = dbsteward_pgsql8_connection::get_dbname();
    $user = dbsteward_pgsql8_connection::get_dbuser();
    $password = dbsteward_pgsql8_connection::get_dbpass();
    
    pgsql8_db::connect("host=$host port=$port dbname=$database user=$user password=$password");
    $rs = pgsql8_db::query($sql);
    
    $rows = array();
    
    while (($row = pg_fetch_assoc($rs)) !== FALSE) {
      $rows[] = $row;
    }
    pgsql8_db::disconnect();
    return $rows;
  }
  
  protected function assign_grants_setup() {
    $change_sql = "GRANT SELECT, INSERT, UPDATE ON public.user TO test_user";
    $this->query_db($change_sql);
    $change_sql2 = "GRANT SELECT, INSERT ON testschema.testtable TO test_user";
    $this->query_db($change_sql2);    
  }
  
  protected function set_up_sequence_testing($schema_name = 'public') {
    $pgsql_parser = new pgsql8();
    $extracted_xml = $pgsql_parser->extract_schema(dbsteward_pgsql8_connection::get_dbhost(), 
       dbsteward_pgsql8_connection::get_dbport(), dbsteward_pgsql8_connection::get_dbname(), 
       dbsteward_pgsql8_connection::get_dbuser(), dbsteward_pgsql8_connection::get_dbpass());
    $rebuilt_db = simplexml_load_string($extracted_xml);
    var_dump($rebuilt_db);
    $schema_node = $rebuilt_db->xpath("schema[@name='" . $schema_name . "']");
    var_dump($schema_node);
    $sequence_node = $schema_node[0]->xpath("sequence");
    var_dump($sequence_node);
    $expected_seq = $sequence_node[0];    
    return $expected_seq;
  }
  
  public function testPublicSequencesBuildProperly() {
    $this->build_db_pgsql8();
    $this->assign_grants_setup();
    
    $sql = "CREATE SEQUENCE blah MINVALUE 3 MAXVALUE 10 CACHE 5";
    $this->pgsql8->query($sql);

    $grant_sql = "GRANT SELECT ON blah TO test_user";
    $this->pgsql8->query($grant_sql);
    
    $expected_seq = $this->set_up_sequence_testing();
    
    $this->assertEquals('blah', (string)$expected_seq['name']);
    $this->assertEquals(3, (string)$expected_seq['min']);
    $this->assertEquals(10, (string)$expected_seq['max']);
    $this->assertEquals(5, (string)$expected_seq['cache']);
    
  }
  
  public function testIsolatedSequencesBuildProperly() {
    $this->build_db_pgsql8();
    
    $sql = "CREATE SEQUENCE testschema.testseq MINVALUE 3 MAXVALUE 10 CACHE 5";
    $this->pgsql8->query($sql);

    $grant_sql = "GRANT SELECT ON testschema.testseq TO test_user";
    $this->pgsql8->query($grant_sql);
    
    $expected_seq = $this->set_up_sequence_testing('testschema');
    
    $this->assertEquals('testseq', (string)$expected_seq['name']);
    $this->assertEquals(3, (string)$expected_seq['min']);
    $this->assertEquals(10, (string)$expected_seq['max']);
    $this->assertEquals(5, (string)$expected_seq['cache']);
    
  }
  
  public function testIntSequencesBecomeSerials() {
    $this->build_db_pgsql8();
    $pgsql_parser = new pgsql8();
    $extracted_xml = $pgsql_parser->extract_schema(dbsteward_pgsql8_connection::get_dbhost(), 
       dbsteward_pgsql8_connection::get_dbport(), dbsteward_pgsql8_connection::get_dbname(), 
       dbsteward_pgsql8_connection::get_dbuser(), dbsteward_pgsql8_connection::get_dbpass());
    $rebuilt_db = simplexml_load_string($extracted_xml);
    $public = $rebuilt_db->xpath("schema[@name='public']");
    $table = $public[0]->xpath("table[@name='user']");
    $column = $table[0]->xpath("column[@name='user_id']");

    $this->assertEquals('serial', (string)$column[0]['type']);
    
  }
  
}
