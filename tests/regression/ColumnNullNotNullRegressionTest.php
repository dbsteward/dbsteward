<?php
/**
 * Column NULL / NOT NULL enforcement and modification regression tests
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once dirname(__FILE__) . '/../dbstewardUnitTestBase.php';

class ColumnNullNotNullRegressionTest extends dbstewardUnitTestBase {
  
  public function setUp() {
    parent::setUp();

  }
  
  protected function setup_definition_xml(&$base_xml, &$strict_overlay_xml, &$new_table_xml) {
    $base_xml = <<<XML
<dbsteward>
  <database>
    <role>
      <application>app_application</application>
      <owner>postgres</owner>
      <replication>app_slony</replication>
      <readonly>app_readonly</readonly>
    </role>
  </database>
  <schema name="app" owner="ROLE_OWNER">
    <table name="action" owner="ROLE_OWNER" primaryKey="action" slonyId="100">
      <column name="action" type="character varying(16)" null="false" />
      <column name="description" type="character varying(200)"/>
      <rows columns="action, description">
        <row>
          <col>ACTION1</col>
          <col>Action 1</col>
        </row>
        <row>
          <col>ACTION2</col>
          <col>Action 2 Description</col>
        </row>
        <row>
          <col>ACTION3</col>
          <col/>
        </row>
        <row>
          <col>ACTION4</col>
          <col/>
        </row>
        <row>
          <col>ACTION99</col>
          <col>Action 99 Reserved</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
    $strict_overlay_xml = <<<XML
<dbsteward>
  <schema name="app" owner="ROLE_OWNER">
    <table name="action">
      <column name="description" type="character varying(200)" null="false" />
      <rows columns="action, description">
        <row>
          <col>ACTION1</col>
          <col>Action 1 Alternate Description</col>
        </row>
        <row>
          <col>ACTION3</col>
          <col>Action 3 Custom Action</col>
        </row>
        <row>
          <col>ACTION4</col>
          <col>Action 4 Custom Action</col>
        </row>
        <row>
          <col>ACTION99</col>
          <col>Action 99 Override</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
    $new_table_xml = <<<XML
<dbsteward>
  <schema name="app" owner="ROLE_OWNER">
    <table name="resolution" owner="ROLE_OWNER" primaryKey="resolution" slonyId="105">
      <column name="resolution" type="character varying(16)" null="false" />
      <column name="points" type="int" />
      <rows columns="resolution, points">
        <row>
          <col>RESOLUTION1</col>
          <col>5</col>
        </row>
        <row>
          <col>RESOLUTION2</col>
          <col>2</col>
        </row>
        <row>
          <col>RESOLUTION3</col>
          <col/>
        </row>
        <row>
          <col>RESOLUTION4</col>
          <col/>
        </row>
        <row>
          <col>RESOLUTION99</col>
          <col>99</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
  }
  
  protected function setup_pgsql8() {
    $base_xml = '';
    $strict_overlay_xml = '';
    $new_table_xml = '';
    $this->setup_definition_xml($base_xml, $strict_overlay_xml, $new_table_xml);

    $this->xml_file_a = dirname(__FILE__) . '/../testdata/pgsql8_unit_test_xml_a.xml';
    $this->xml_file_b = dirname(__FILE__) . '/../testdata/pgsql8_unit_test_xml_b.xml';
    $this->xml_file_c = dirname(__FILE__) . '/../testdata/pgsql8_unit_test_xml_c.xml';
        
    $this->set_xml_content_a($base_xml);
    $this->set_xml_content_b($strict_overlay_xml);
    $this->set_xml_content_c($new_table_xml);
    $this->output_prefix = dirname(__FILE__) . '/../testdata/pgsql8_test_column_nulls';
    dbsteward::$single_stage_upgrade = TRUE;
    dbsteward::$generate_slonik = FALSE;
  }
  
  protected function setup_mysql5() {
    $base_xml = '';
    $strict_overlay_xml = '';
    $new_table_xml = '';
    $this->setup_definition_xml($base_xml, $strict_overlay_xml, $new_table_xml);

    $this->xml_file_a = dirname(__FILE__) . '/../testdata/mysql5_unit_test_xml_a.xml';
    $this->xml_file_b = dirname(__FILE__) . '/../testdata/mysql5_unit_test_xml_b.xml';
    $this->xml_file_c = dirname(__FILE__) . '/../testdata/mysql5_unit_test_xml_c.xml';
        
    $this->set_xml_content_a($base_xml);
    $this->set_xml_content_b($strict_overlay_xml);
    $this->set_xml_content_c($new_table_xml);
    $this->output_prefix = dirname(__FILE__) . '/../testdata/mysql5_unit_test_column_nulls';
    dbsteward::$single_stage_upgrade = TRUE;    
  }
  
  /**
   * @group pgsql8
   */
  public function testUpgradeIdenticalDDLPgsql8() {
    $this->apply_options_pgsql8();
    $this->setup_pgsql8();

    $base_db_doc = xml_parser::xml_composite(array($this->xml_file_a));
    $upgrade_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_a));
    pgsql8::build_upgrade('', 'column_nulls_identical_test_pgsql8_old', $base_db_doc, array(), $this->output_prefix, 'column_nulls_identical_test_pgsql8_new', $upgrade_db_doc, array());

    $text = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');
    $this->assertNotRegExp('/ALTER\s+/', $text, 'Diff SQL output contains ALTER statements');
    $this->assertNotRegExp('/UPDATE\s+/', $text, 'Diff SQL output contains UPDATE statements');
    $this->assertNotRegExp('/INSERT\s+/', $text, 'Diff SQL output contains INSERT statements');
    $this->assertNotRegExp('/DELETE\s+/', $text, 'Diff SQL output contains DELETE statements');


    // and do identical comparison with strict definitions on top
    $base_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_b));
    $upgrade_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_b));
    pgsql8::build_upgrade('', 'column_nulls_identical_strict_pgsql8_old', $base_db_doc, array(), $this->output_prefix, 'column_nulls_identical_strict_pgsql8_new', $upgrade_db_doc, array());

    $text = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');
    $this->assertNotRegExp('/ALTER\s+/', $text, 'Diff SQL output contains ALTER statements');
    $this->assertNotRegExp('/UPDATE\s+/', $text, 'Diff SQL output contains UPDATE statements');
    $this->assertNotRegExp('/INSERT\s+/', $text, 'Diff SQL output contains INSERT statements');
    $this->assertNotRegExp('/DELETE\s+/', $text, 'Diff SQL output contains DELETE statements');
  }

  
  /**
   * @group mysql5
   */
  public function testUpgradeIdenticalDDLMysql5() {
    $this->apply_options_mysql5();
    $this->setup_mysql5();

    $base_db_doc = xml_parser::xml_composite(array($this->xml_file_a));
    $upgrade_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_a));
    mysql5::build_upgrade('', 'column_nulls_identical_test_mysql5_old', $base_db_doc, array(), $this->output_prefix, 'column_nulls_identical_test_mysql5_new', $upgrade_db_doc, array());

    $text = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');

    $this->assertNotRegExp('/ALTER\s+/', $text, 'Diff SQL output contains ALTER statements');
    $this->assertNotRegExp('/UPDATE\s+/', $text, 'Diff SQL output contains UPDATE statements');
    $this->assertNotRegExp('/INSERT\s+/', $text, 'Diff SQL output contains INSERT statements');
    $this->assertNotRegExp('/DELETE\s+/', $text, 'Diff SQL output contains DELETE statements');


    // and do identical comparison with strict definitions on top
    $base_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_b));
    $upgrade_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_b));
    mysql5::build_upgrade('', 'column_nulls_identical_strict_mysql5_old', $base_db_doc, array(), $this->output_prefix, 'column_nulls_identical_strict_mysql5_new', $upgrade_db_doc, array());

    $text = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');

    $this->assertNotRegExp('/ALTER\s+/', $text, 'Diff SQL output contains ALTER statements');
    $this->assertNotRegExp('/UPDATE\s+/', $text, 'Diff SQL output contains UPDATE statements');
    $this->assertNotRegExp('/INSERT\s+/', $text, 'Diff SQL output contains INSERT statements');
    $this->assertNotRegExp('/DELETE\s+/', $text, 'Diff SQL output contains DELETE statements');
  }

  /**
   * @group pgsql8
   */
  public function testFullBuildPgsql8() {
    $this->apply_options_pgsql8();
    $this->setup_pgsql8();

    // build base full, check contents
    $base_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_a));
    pgsql8::build($this->output_prefix, $base_db_doc);
    $text = file_get_contents($this->output_prefix . '_build.sql');
    // make sure SET NOT NULL is specified for action column
    $this->assertContains('ALTER TABLE app.action ALTER COLUMN action SET NOT NULL', $text);
    // make sure SET NOT NULL is NOT specified for description column
    $this->assertNotContains('ALTER TABLE app.action ALTER COLUMN description SET NOT NULL', $text);
    
    // build base + strict, check contents
    $strict_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_b));
    pgsql8::build($this->output_prefix, $strict_db_doc);
    $text = file_get_contents($this->output_prefix . '_build.sql');
    // make sure SET NOT NULL is specified for action column
    $this->assertContains('ALTER TABLE app.action ALTER COLUMN action SET NOT NULL', $text);
    // make sure SET NOT NULL is specified for description column
    $this->assertContains('ALTER TABLE app.action ALTER COLUMN description SET NOT NULL', $text);
    
    // build base + strict + new table, check contents
    $addtable_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_b, $this->xml_file_c));
    pgsql8::build($this->output_prefix, $addtable_db_doc);
    $text = file_get_contents($this->output_prefix . '_build.sql');
    // make sure NOT NULL is specified for resolution column
    $this->assertContains('ALTER TABLE app.resolution ALTER COLUMN resolution SET NOT NULL', $text);
    // make sure NOT NULL is NOT specified for points column
    $this->assertNotContains('ALTER TABLE app.resolution ALTER COLUMN points SET NOT NULL', $text);
  }
  
  /**
   * @group mysql5
   */
  public function testFullBuildMysql5() {
    $this->apply_options_mysql5();
    $this->setup_mysql5();

    // build base full, check contents
    $base_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_a));
    mysql5::build($this->output_prefix, $base_db_doc);
    $text = file_get_contents($this->output_prefix . '_build.sql');
    // make sure NOT NULL is specified for action column
    $this->assertContains('`action` character varying(16) NOT NULL', $text);
    // make sure NOT NULL is NOT specified for description column
    $this->assertNotContains('`description` character varying(200) NOT NULL', $text);
    
    // build base + strict, check contents
    $strict_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_b));
    mysql5::build($this->output_prefix, $strict_db_doc);
    $text = file_get_contents($this->output_prefix . '_build.sql');
    // make sure NOT NULL is specified for action column
    $this->assertContains('`action` character varying(16) NOT NULL', $text);
    // make sure NOT NULL is specified for description column
    $this->assertContains('`description` character varying(200) NOT NULL', $text);
    
    // build base + strict + new table, check contents
    $addtable_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_b, $this->xml_file_c));
    mysql5::build($this->output_prefix, $addtable_db_doc);
    $text = file_get_contents($this->output_prefix . '_build.sql');
    // make sure NOT NULL is specified for resolution column
    $this->assertContains('`resolution` character varying(16) NOT NULL', $text);
    // make sure NOT NULL is NOT specified for points column
    $this->assertNotContains('`points` int NOT NULL', $text);
  }

  /**
   * @group pgsql8
   */
  public function testUpgradeNewTablePgsql8() {
    $this->apply_options_pgsql8();
    $this->setup_pgsql8();
    
    // upgrade from base 
    // to base + strict action table + new resolution table
    // check null specificity
    $base_db_doc = xml_parser::xml_composite(array($this->xml_file_a));
    $newtable_db_doc = xml_parser::xml_composite(array($this->xml_file_a, $this->xml_file_b, $this->xml_file_c));
    pgsql8::build_upgrade('', 'newtable_upgrade_test_pgsql8_base', $base_db_doc, array(), $this->output_prefix, 'newtable_upgrade_test_pgsql8_newtable', $newtable_db_doc, array());
    $text = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');
    // make sure NOT NULL is specified for resolution column
    $this->assertContains('ALTER TABLE app.resolution ALTER COLUMN resolution SET NOT NULL', $text);
    // make sure SET NOT NULL is specified for description column for the upgrade
    $this->assertContains('ALTER COLUMN description SET NOT NULL', $text);
    // make sure NOT NULL is NOT specified for points column
    $this->assertNotContains('ALTER TABLE app.resolution ALTER COLUMN points SET NOT NULL', $text);
  }

  
}
