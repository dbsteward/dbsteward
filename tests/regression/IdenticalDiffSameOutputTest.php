<?php
/**
 * Regression test for pulling pgsql8_column::get_serial_start_dml() up to sql99_column
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Rusty Hamilton <rusty@shrub3.net>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once dirname(__FILE__) . '/../dbstewardUnitTestBase.php';

class IdenticalDiffSameOutputTest extends dbstewardUnitTestBase
{
  
  public function setUp() {
    parent::setUp();

  }
  
  protected function setup_pgsql8() {
        $xml = <<<XML
<dbsteward>
  <database>
    <sqlformat>pgsql8</sqlformat>
    <role>
      <application>app_application</application>
      <owner>postgres</owner>
      <replication>app_slony</replication>
      <readonly>app_readonly</readonly>
    </role>
  </database>
  <schema name="app" owner="ROLE_OWNER">
    <table name="my_table" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="my_table_pk" slonyId="0">
      <column name="id" type="character varying(32)" null="false"/>
      <column name="action" type="character varying(32)"/>
      <rows columns="id, action">
        <row>
          <col>1</col>
          <col>Row 1</col>
        </row>
        <row>
          <col>2</col>
          <col>Row 2</col>
        </row>
        <row>
          <col>3</col>
          <col>Row 3</col>
        </row>
        <row>
          <col>4</col>
          <col>Row 4</col>
        </row>
        <row>
          <col>5</col>
          <col>Row 5</col>
        </row>          
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
    $this->xml_file_a = dirname(__FILE__) . '/../testdata/pgsql8_unit_test_xml_a.xml';
    $this->xml_file_b = dirname(__FILE__) . '/../testdata/pgsql8_unit_test_xml_b.xml';        
        
    $this->set_xml_content_a($xml);
    $this->set_xml_content_b($xml);
    $old_db_doc = simplexml_load_file($this->xml_file_a);
    $new_db_doc = simplexml_load_file($this->xml_file_b);
    $this->output_prefix = dirname(__FILE__) . '/../testdata/pgsql8_unit_test_xml_a';
    dbsteward::$single_stage_upgrade = TRUE;
    dbsteward::$generate_slonik = FALSE;
    pgsql8::build_upgrade('', $old_db_doc, $old_db_doc, array(), $this->output_prefix, $new_db_doc, $new_db_doc, array());
  }
  
  protected function setup_mysql5() {
        $xml = <<<XML
<dbsteward>
  <database>
    <sqlformat>mysql5</sqlformat>
    <role>
      <application>app_application</application>
      <owner>postgres</owner>
      <replication>app_slony</replication>
      <readonly>app_readonly</readonly>
    </role>
  </database>
  <schema name="app" owner="ROLE_OWNER">
    <table name="my_table" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="my_table_pk" slonyId="0">
      <column name="id" type="character varying(32)" null="false"/>
      <column name="action" type="character varying(32)"/>
      <rows columns="id, action">
        <row>
          <col>1</col>
          <col>Row 1</col>
        </row>
        <row>
          <col>2</col>
          <col>Row 2</col>
        </row>
        <row>
          <col>3</col>
          <col>Row 3</col>
        </row>
        <row>
          <col>4</col>
          <col>Row 4</col>
        </row>
        <row>
          <col>5</col>
          <col>Row 5</col>
        </row>          
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
    $this->xml_file_a = dirname(__FILE__) . '/../testdata/mysql5_unit_test_xml_a.xml';
    $this->xml_file_b = dirname(__FILE__) . '/../testdata/mysql5_unit_test_xml_b.xml';        
        
    $this->set_xml_content_a($xml);
    $this->set_xml_content_b($xml);
    $old_db_doc = simplexml_load_file($this->xml_file_a);
    $new_db_doc = simplexml_load_file($this->xml_file_b);
    $this->output_prefix = dirname(__FILE__) . '/../testdata/mysql5_unit_test_xml_a';
    dbsteward::$single_stage_upgrade = TRUE;
    mysql5::build_upgrade('', $old_db_doc, $old_db_doc, array(), $this->output_prefix, $new_db_doc, $new_db_doc, array());    
  }
  
  /**
   * @group pgsql8
   */
  public function testBuildIdenticalDDLPostgres() {
    $this->apply_options_pgsql8();
    $this->setup_pgsql8();
    $text = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');
    $success = preg_match('/ALTER/', $text);
    $this->assertEquals(0, $success, $text);
  }

  
  /**
   * @group mysql5
   */
  public function testBuildIdenticalDDLMysql() {
    // now do the same thing, but with mysql5
    $this->apply_options_mysql5();
    $this->setup_mysql5();
    $text = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');
    $success = preg_match('/ALTER/', $text);
    $this->assertEquals(0, $success);      
  }

  
}
