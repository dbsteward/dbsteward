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

require_once dirname(__FILE__) . '/../lib/DBSteward/dbsteward.php';

dbsteward::load_sql_formats();

require_once dirname(__FILE__) . '/dbsteward_pgsql8_connection.php';
require_once dirname(__FILE__) . '/dbsteward_mssql10_connection.php';

class dbstewardUnitTestBase extends PHPUnit_Framework_TestCase {

  // test cases need to define $this->xml_content_a and $this->xml_content_b for their scenarios
  protected $xml_content_a = "XML_CONTENT_A_UNDEFINED";
  protected $xml_content_b = "XML_CONTENT_B_UNDEFINED";

  protected $xml_file_a;
  protected $xml_file_b;

  protected function setUp() {
    if ( !is_dir(dirname(__FILE__) . '/testdata') ) {
      mkdir(dirname(__FILE__) . '/testdata');
    }
    $this->xml_file_a = dirname(__FILE__) . '/testdata/unit_test_xml_a.xml';
    file_put_contents($this->xml_file_a, $this->xml_content_a);

    $this->xml_file_b = dirname(__FILE__) . '/testdata/unit_test_xml_b.xml';
    file_put_contents($this->xml_file_b, $this->xml_content_b);
    
    $this->pgsql = new dbsteward_pgsql8_connection();
    $this->mssql = new dbsteward_mssql10_connection();
    
    // be sure to reset dbsteward runtime tracking variables every time
    pgsql8::$table_slony_ids = array();
    pgsql8::$sequence_slony_ids = array();
    pgsql8::$known_pg_identifiers = array();
  }
  
  protected function tearDown() {
    // make sure connection is closed to DB can be dropped
    // when running multiple tests
    $this->pgsql->close_connection();
    $this->mssql->close_connection();
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
    
    $this->pgsql->create_db();

    // build initial "A" database
    $this->pgsql->run_file(dirname(__FILE__) . '/testdata/unit_test_xml_a_build.sql');
  }

  protected function upgrade_db_pgsql8() {
    $this->apply_options_pgsql8();
    
    // build the upgrade DDL first, incase dbsteward code wants to throw about something
    pgsql8::build_upgrade($this->xml_file_a, $this->xml_file_b);

    // upgrade database to "B" with each stage file
    $this->pgsql->run_file(dirname(__FILE__) . '/testdata/upgrade_stage1_schema1.sql');
    $this->pgsql->run_file(dirname(__FILE__) . '/testdata/upgrade_stage2_data1.sql');
    $this->pgsql->run_file(dirname(__FILE__) . '/testdata/upgrade_stage3_schema1.sql');
    $this->pgsql->run_file(dirname(__FILE__) . '/testdata/upgrade_stage4_data1.sql');
    
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
    
    $this->mssql->create_db();

    // build initial "A" database
    $this->mssql->run_file(dirname(__FILE__) . '/testdata/unit_test_xml_a_build.sql');
  }

  protected function upgrade_db_mssql10() {
    $this->apply_options_mssql10();
    
    // build the upgrade DDL first, incase dbsteward code wants to throw about something
    mssql10::build_upgrade($this->xml_file_a, $this->xml_file_b);
    
    // upgrade database to "B" with each stage file
    $this->mssql->run_file(dirname(__FILE__) . '/testdata/upgrade_stage1_schema1.sql');
    $this->mssql->run_file(dirname(__FILE__) . '/testdata/upgrade_stage2_data1.sql');
    $this->mssql->run_file(dirname(__FILE__) . '/testdata/upgrade_stage3_schema1.sql');
    $this->mssql->run_file(dirname(__FILE__) . '/testdata/upgrade_stage4_data1.sql');
    
    //@TODO: confirm tables defined in B are present
  }

}

?>
