<?php
/**
 * DBSteward unit test framework / base class
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../lib/DBSteward/dbsteward.php';

require_once __DIR__ . '/dbsteward_sql99_connection.php';
require_once __DIR__ . '/dbsteward_pgsql8_connection.php';
require_once __DIR__ . '/dbsteward_mssql10_connection.php';
require_once __DIR__ . '/dbsteward_mysql5_connection.php';

class dbstewardUnitTestBase extends PHPUnit_Framework_TestCase {

  // test cases need to define $this->xml_content_a and $this->xml_content_b for their scenarios
  protected $xml_content_a = "XML_CONTENT_A_UNDEFINED";
  protected $xml_content_b = "XML_CONTENT_B_UNDEFINED";

  protected $xml_file_a;
  protected $xml_file_b;

  protected $pgsql8;
  protected $mssql10;
  protected $mysql5;

  protected function setUp() {
    if ( !is_dir(__DIR__ . '/testdata') ) {
      mkdir(__DIR__ . '/testdata');
    }
    $this->xml_file_a = __DIR__ . '/testdata/unit_test_xml_a.xml';
    file_put_contents($this->xml_file_a, $this->xml_content_a);

    $this->xml_file_b = __DIR__ . '/testdata/unit_test_xml_b.xml';
    file_put_contents($this->xml_file_b, $this->xml_content_b);
    
    $this->pgsql8 = new dbsteward_pgsql8_connection();
    $this->mssql10 = new dbsteward_mssql10_connection();
    $this->mysql5 = new dbsteward_mysql5_connection();
    
    // be sure to reset dbsteward runtime tracking variables every time
    pgsql8::$table_slony_ids = array();
    pgsql8::$sequence_slony_ids = array();
    pgsql8::$known_pg_identifiers = array();
  }
  
  protected function tearDown() {
    // make sure connection is closed to DB can be dropped
    // when running multiple tests
    $this->mssql10->close_connection();
  }

  protected function set_xml_content_a($xml) {
    $this->xml_content_a = $xml;
    file_put_contents($this->xml_file_a, $xml);
  }
  protected function set_xml_content_b($xml) {
    $this->xml_content_b = $xml;
    file_put_contents($this->xml_file_b, $xml);
  }

  protected function build_db($format) {
    return call_user_func(array($this, "build_db_$format"));
  }

  protected function apply_options($format) {
    return call_user_func(array($this, "apply_options_$format"));
  }

  protected function upgrade_db($format) {
    return call_user_func(array($this, "upgrade_db_$format"));
  }

  protected function get_connection($format) {
    return $this->$format;
  }
  
  protected function apply_options_pgsql8() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }
  
  protected function build_db_pgsql8() {
    $this->apply_options_pgsql8();
    
    // build the DDL first, incase dbsteward code wants to throw about something
    pgsql8::build($this->xml_file_a);
    
    $this->pgsql8->create_db();

    // build initial "A" database
    $this->pgsql8->run_file(__DIR__ . '/testdata/unit_test_xml_a_build.sql');
  }

  protected function upgrade_db_pgsql8() {
    $this->apply_options_pgsql8();
    
    // build the upgrade DDL first, incase dbsteward code wants to throw about something
    pgsql8::build_upgrade($this->xml_file_a, $this->xml_file_b);

    // upgrade database to "B" with each stage file
    $this->pgsql8->run_file(__DIR__ . '/testdata/upgrade_stage1_schema1.sql');
    $this->pgsql8->run_file(__DIR__ . '/testdata/upgrade_stage2_data1.sql');
    $this->pgsql8->run_file(__DIR__ . '/testdata/upgrade_stage3_schema1.sql');
    $this->pgsql8->run_file(__DIR__ . '/testdata/upgrade_stage4_data1.sql');
    
    //@TODO: confirm tables defined in B are present
  }
  
  protected function apply_options_mssql10() {
    dbsteward::set_sql_format('mssql10');
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }
  
  protected function build_db_mssql10() {
    $this->markTestIncomplete('Need to iron out MSSQL permissions and connectivity to get MSSQL db creation working');
    
    $this->apply_options_mssql10();
    
    // build the DDL first, incase dbsteward code wants to throw about something
    mssql10::build($this->xml_file_a);
    
    $this->mssql10->create_db();

    // build initial "A" database
    $this->mssql10->run_file(__DIR__ . '/testdata/unit_test_xml_a_build.sql');
  }

  protected function upgrade_db_mssql10() {
    $this->apply_options_mssql10();
    
    // build the upgrade DDL first, incase dbsteward code wants to throw about something
    mssql10::build_upgrade($this->xml_file_a, $this->xml_file_b);
    
    // upgrade database to "B" with each stage file
    $this->mssql10->run_file(__DIR__ . '/testdata/upgrade_stage1_schema1.sql');
    $this->mssql10->run_file(__DIR__ . '/testdata/upgrade_stage2_data1.sql');
    $this->mssql10->run_file(__DIR__ . '/testdata/upgrade_stage3_schema1.sql');
    $this->mssql10->run_file(__DIR__ . '/testdata/upgrade_stage4_data1.sql');
    
    //@TODO: confirm tables defined in B are present
  }
  
  protected function apply_options_mysql5() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
  }
  
  protected function build_db_mysql5() {
    $this->apply_options_mysql5();
    
    // build the DDL first, incase dbsteward code wants to throw about something
    mysql5::build($this->xml_file_a);
    
    $this->mysql5->create_db();

    // build initial "A" database
    $this->mysql5->run_file(__DIR__ . '/testdata/unit_test_xml_a_build.sql');
  }

  protected function upgrade_db_mysql5() {
    $this->apply_options_mysql5();
    
    // build the upgrade DDL first, incase dbsteward code wants to throw about something
    mysql5::build_upgrade($this->xml_file_a, $this->xml_file_b);
    
    // upgrade database to "B" with each stage file
    $this->mysql5->run_file(__DIR__ . '/testdata/upgrade_stage1_schema1.sql');
    $this->mysql5->run_file(__DIR__ . '/testdata/upgrade_stage2_data1.sql');
    $this->mysql5->run_file(__DIR__ . '/testdata/upgrade_stage3_schema1.sql');
    $this->mysql5->run_file(__DIR__ . '/testdata/upgrade_stage4_data1.sql');
    
    //@TODO: confirm tables defined in B are present
  }

}

?>
