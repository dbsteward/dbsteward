<?php
/**
 * Regression test for pulling pgsql8_column::get_serial_start_dml() up to sql99_column
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Rusty Hamilton <rusty@shrub3.net>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

class IdenticalDiffSameOutputTest extends dbstewardUnitTestBase {

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
      <column name="description" type="character varying(200)"/>
      <rows columns="id, action, description">
        <row>
          <col>1</col>
          <col>Row 1</col>
          <col>Action 1 Description</col>
        </row>
        <row>
          <col>2</col>
          <col>Row 2</col>
          <col>Action 2 Description</col>
        </row>
        <row>
          <col>3</col>
          <col>Row 3</col>
          <col>Action 3 Description</col>
        </row>
        <row>
          <col>4</col>
          <col>Row 4</col>
          <col>Action 4 Description</col>
        </row>
        <row>
          <col>5</col>
          <col>Row 5</col>
          <col>Action 5 Description</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
    $xml_data_overlay = <<<XML
<dbsteward>
  <database>
    <role>
      <application>client_app_application</application>
      <owner>postgres</owner>
      <replication>client_app_slony</replication>
      <readonly>client_app_readonly</readonly>
    </role>
  </database>
  <schema name="app" owner="ROLE_OWNER">
    <table name="my_table" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="my_table_pk" slonyId="0">
      <rows columns="id, description">
        <row>
          <col>2</col>
          <col>Action 2 Alternate Description</col>
        </row>
        <row>
          <col>4</col>
          <col>Action 4 Alternate Description</col>
        </row>
        <row>
          <col>5</col>
          <col>Action 5 Alternate Description</col>
        </row>          
      </rows>
    </table>
  </schema>
</dbsteward>
XML;

    $this->xml_file_a = dirname(__FILE__) . '/../testdata/pgsql8_unit_test_xml_a.xml';
    $this->xml_file_b = dirname(__FILE__) . '/../testdata/pgsql8_unit_test_xml_b.xml';
    $this->xml_file_c = dirname(__FILE__) . '/../testdata/pgsql8_unit_test_xml_c.xml';
        
    $this->set_xml_content_a($xml);
    $this->set_xml_content_b($xml);
    $this->set_xml_content_c($xml_data_overlay);
    $this->output_prefix = dirname(__FILE__) . '/../testdata/pgsql8_test_identical';
    dbsteward::$single_stage_upgrade = TRUE;
    dbsteward::$generate_slonik = FALSE;
    $old_db_doc_comp = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_c));
    $new_db_doc_comp = xml_parser::xml_composite(array($this->xml_file_b, $this->xml_file_c));
    pgsql8::build_upgrade('', 'identical_diff_test_pgsql8_old', $old_db_doc_comp, array(), $this->output_prefix, 'identical_diff_test_pgsql8_new', $new_db_doc_comp, array());
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
    <table name="my_table" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="my_table_pk">
      <column name="id" type="character varying(32)" null="false"/>
      <column name="action" type="character varying(32)"/>
      <column name="description" type="character varying(200)"/>
      <rows columns="id, action, description">
        <row>
          <col>1</col>
          <col>Row 1</col>
          <col>Action 1 Description</col>
        </row>
        <row>
          <col>2</col>
          <col>Row 2</col>
          <col>Action 2 Description</col>
        </row>
        <row>
          <col>3</col>
          <col>Row 3</col>
          <col>Action 3 Description</col>
        </row>
        <row>
          <col>4</col>
          <col>Row 4</col>
          <col>Action 4 Description</col>
        </row>
        <row>
          <col>5</col>
          <col>Row 5</col>
          <col>Action 5 Description</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
    $xml_data_overlay = <<<XML
<dbsteward>
  <database>
    <role>
      <application>client_app_application</application>
      <owner>postgres</owner>
      <replication>client_app_slony</replication>
      <readonly>client_app_readonly</readonly>
    </role>
  </database>
  <schema name="app" owner="ROLE_OWNER">
    <table name="my_table" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="my_table_pk">
      <rows columns="id, description">
        <row>
          <col>2</col>
          <col>Action 2 Alternate Description</col>
        </row>
        <row>
          <col>3</col>
          <col>Action 3 Alternate Description</col>
        </row>
        <row>
          <col>5</col>
          <col>Action 5 Alternate Description</col>
        </row>          
      </rows>
    </table>
  </schema>
</dbsteward>
XML;

    $this->xml_file_a = dirname(__FILE__) . '/../testdata/mysql5_unit_test_xml_a.xml';
    $this->xml_file_b = dirname(__FILE__) . '/../testdata/mysql5_unit_test_xml_b.xml';
    $this->xml_file_c = dirname(__FILE__) . '/../testdata/mysql5_unit_test_xml_c.xml';
        
    $this->set_xml_content_a($xml);
    $this->set_xml_content_b($xml);
    $this->set_xml_content_c($xml_data_overlay);
    $this->output_prefix = dirname(__FILE__) . '/../testdata/mysql5_unit_test_identical';
    dbsteward::$single_stage_upgrade = TRUE;
    $old_db_doc_comp = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_c));
    $new_db_doc_comp = xml_parser::xml_composite(array($this->xml_file_b, $this->xml_file_c));
    mysql5::build_upgrade('', 'identical_diff_test_mysql5_old', $old_db_doc_comp, array(), $this->output_prefix, 'identical_diff_test_mysql5_new', $new_db_doc_comp, array());
  }
  
  /**
   * @group pgsql8
   */
  public function testBuildIdenticalDDLPostgres() {
    $this->apply_options_pgsql8();
    $this->setup_pgsql8();
    $text = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');
    $this->assertNotRegExp('/ALTER\s+/', $text, 'Diff SQL output contains ALTER statements');
    $this->assertNotRegExp('/UPDATE\s+/', $text, 'Diff SQL output contains UPDATE statements');
    $this->assertNotRegExp('/INSERT\s+/', $text, 'Diff SQL output contains INSERT statements');
    $this->assertNotRegExp('/DELETE\s+/', $text, 'Diff SQL output contains DELETE statements');
  }

  
  /**
   * @group mysql5
   */
  public function testBuildIdenticalDDLMysql() {
    // now do the same thing, but with mysql5
    $this->apply_options_mysql5();
    $this->setup_mysql5();
    $text = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');
    $this->assertNotRegExp('/ALTER\s+/', $text, 'Diff SQL output contains ALTER statements');
    $this->assertNotRegExp('/UPDATE\s+/', $text, 'Diff SQL output contains UPDATE statements');
    $this->assertNotRegExp('/INSERT\s+/', $text, 'Diff SQL output contains INSERT statements');
    $this->assertNotRegExp('/DELETE\s+/', $text, 'Diff SQL output contains DELETE statements');
  }

  
}
