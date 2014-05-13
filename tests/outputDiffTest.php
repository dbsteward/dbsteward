<?php
/**
 * Test the output of dbsteward diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/dbstewardUnitTestBase.php';

class outputDiffTest extends dbstewardUnitTestBase {

  private $pgsql8_serial_xml_a = <<<XML
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
    <slony clusterName="duplicate_slony_ids_testsuite">
      <slonyNode id="1" comment="DSI - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="common duplicate testing database definition">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
        <slonyReplicaSetNode id="3" providerNodeId="2"/>
      </slonyReplicaSet>
    </slony>
 
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
  <language name="plpgsql" procedural="true" owner="ROLE_OWNER"/>
  <schema name="dbsteward" owner="ROLE_OWNER">
    <table name="serial_test" owner="ROLE_OWNER" primaryKey="test_id" slonyId="1">
      <column name="test_id" type="serial" slonyId="2"/>
    </table>
  </schema>
</dbsteward>
XML;
  private $pgsql8_serial_xml_b = <<<XML
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
    <slony clusterName="duplicate_slony_ids_testsuite">
      <slonyNode id="1" comment="DSI - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="common duplicate testing database definition">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
        <slonyReplicaSetNode id="3" providerNodeId="2"/>
      </slonyReplicaSet>
    </slony>
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
  <language name="plpgsql" procedural="true" owner="ROLE_OWNER"/>
  <schema name="dbsteward" owner="ROLE_OWNER">
    <table name="serial_test" owner="ROLE_OWNER" primaryKey="test_id" slonyId="1">
      <column name="test_id" type="int"/>
    </table>
  </schema>
</dbsteward>
XML;
  private $mssql10_serial_xml_a; // = $pgsql8_serial_xml_a
  private $mssql10_serial_xml_b; // = $pgsql8_serial_xml_b

  private $mysql5_serial_xml_a; // = $pgsql8_serial_xml_a
  private $mysql5_serial_xml_b; // = $pgsql8_serial_xml_b

  private $pgsql8_single_xml_a = <<<XML
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
    <slony clusterName="duplicate_slony_ids_testsuite">
      <slonyNode id="1" comment="DSI - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="common duplicate testing database definition">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
        <slonyReplicaSetNode id="3" providerNodeId="2"/>
      </slonyReplicaSet>
    </slony>
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
  <language name="plpgsql" procedural="true" owner="ROLE_OWNER"/>
  <schema name="dbsteward" owner="ROLE_OWNER">
    <table name="user" owner="ROLE_OWNER" primaryKey="user_id" slonyId="1">
      <column name="user_id" type="uuid" />
      <column name="user_name" type="varchar(32)" />
      <rows columns="user_id, user_name">
        <row>
          <col>1</col>
          <col>Admin</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
  private $pgsql8_single_xml_b = <<<XML
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
    <slony clusterName="duplicate_slony_ids_testsuite">
      <slonyNode id="1" comment="DSI - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="common duplicate testing database definition">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
        <slonyReplicaSetNode id="3" providerNodeId="2"/>
      </slonyReplicaSet>
    </slony>
 
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
  <language name="plpgsql" procedural="true" owner="ROLE_OWNER"/>
  <schema name="dbsteward" owner="ROLE_OWNER">
    <table name="user" owner="ROLE_OWNER" primaryKey="user_id" slonyId="1">
      <column name="user_id" type="uuid" />
      <column name="user_name" type="varchar(64)" />
      <rows columns="user_id, user_name">
        <row>
          <col>1</col>
          <col>Administrator</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;

  private $mssql10_single_xml_a; // = $pgsql8_single_xml_a
  private $mssql10_single_xml_b; // = $pgsql8_single_xml_b

  private $mysql5_single_xml_a; // = $pgsql8_single_xml_a
  private $mysql5_single_xml_b; // = $pgsql8_single_xml_b

  protected function setUp() {
    $this->mssql10_serial_xml_a = $this->pgsql8_serial_xml_a;
    $this->mssql10_serial_xml_b = $this->pgsql8_serial_xml_b;

    // mysql5 usernames must be <= 16 chars
    $this->mysql5_serial_xml_a = str_replace('dbsteward_phpunit_app', 'dbsteward_pu_app', $this->pgsql8_serial_xml_a);
    $this->mysql5_serial_xml_b = str_replace('dbsteward_phpunit_app', 'dbsteward_pu_app', $this->pgsql8_serial_xml_b);

    $this->mssql10_single_xml_a = $this->pgsql8_single_xml_a;
    $this->mssql10_single_xml_b = $this->pgsql8_single_xml_b;

    $this->mysql5_single_xml_a = str_replace('dbsteward_phpunit_app', 'dbsteward_pu_app', $this->pgsql8_single_xml_a);
    $this->mysql5_single_xml_b = str_replace('dbsteward_phpunit_app', 'dbsteward_pu_app', $this->pgsql8_single_xml_b);

    parent::setUp();
  }

  protected function do_upgrade($sql_format) {
    $old_db_doc = simplexml_load_file($this->xml_file_a);
    $new_db_doc = simplexml_load_file($this->xml_file_b);
    $this->output_prefix = dirname(__FILE__) . '/testdata/' .  $sql_format . '_unit_test_xml_a'; 
    // need to unfortunately do the one thing austin told me not to:
    // use more than one format type per run
    if (strcasecmp($sql_format, 'pgsql8') == 0) {
      pgsql8::build_upgrade('', $old_db_doc, $old_db_doc, array(), $this->output_prefix, $new_db_doc, $new_db_doc, array());
    }
    else if (strcasecmp($sql_format, 'mysql5') == 0) {
      mysql5::build_upgrade('', $old_db_doc, $old_db_doc, array(), $this->output_prefix, $new_db_doc, $new_db_doc, array());
    }
    else {
      $this->fail("This test only uses pgsql8 and mysql5 formats, but can be expanded.");
    }
  }

  /**
   * @group pgsql8
   */
  public function testSerialToIntPgsql8() {
    $this->xml_content_a = $this->pgsql8_serial_xml_a;
    $this->xml_file_a = dirname(__FILE__) . '/testdata/type_diff_xml_a.xml';
    file_put_contents($this->xml_file_a, $this->xml_content_a);

    $this->xml_content_b = $this->pgsql8_serial_xml_b;
    $this->xml_file_b = dirname(__FILE__) . '/testdata/type_diff_xml_b.xml';
    file_put_contents($this->xml_file_b, $this->xml_content_b);

    $this->apply_options_pgsql8();
    dbsteward::$generate_slonik = TRUE;
    $this->do_upgrade('pgsql8');

    $upgrade_stage1_schema1_sql = file_get_contents($this->output_prefix . '_upgrade_slony_replica_set_100_stage1_schema1.sql');
    $upgrade_stage1_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage1_schema1_sql);
    $this->assertTrue(
      (boolean)preg_match('/ALTER COLUMN "test_id" TYPE int/i', $upgrade_stage1_schema1_sql),
      "Column type change was not found in upgrade_slony_replica_set_100_stage1_schema1.sql:\n$upgrade_stage1_schema1_sql"
      );
    $this->assertTrue(
      (boolean)preg_match('/ALTER COLUMN "test_id" DROP DEFAULT/i', $upgrade_stage1_schema1_sql),
      "Removal of SERIAL default not found in upgrade_slony_replica_set_100_stage1_schema1.sql:\n$upgrade_stage1_schema1_sql"
      );

    $upgrade_stage3_schema1_sql = file_get_contents($this->output_prefix . '_upgrade_slony_replica_set_100_stage3_schema1.sql');
    $upgrade_stage3_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage3_schema1_sql);
    $this->assertTrue(
      (boolean)preg_match('/DROP SEQUENCE IF EXISTS "dbsteward"."serial_test_test_id_seq"/i', $upgrade_stage3_schema1_sql),
      "Serial drop was not found in upgrade_slony_replica_set_100_stage3_schema1.sql:\n$upgrade_stage3_schema1_sql"
      );

    $upgrade_stage1_slony_slonik = file_get_contents($this->output_prefix . '_upgrade_slony_replica_set_100_stage1.slonik');
    $upgrade_stage1_slony_slonik = preg_replace('/\s+/', ' ', $upgrade_stage1_slony_slonik);
    $this->assertTrue(
      (boolean)preg_match('/SET DROP SEQUENCE \( ORIGIN = 1, ID = 2 \);/i', $upgrade_stage1_slony_slonik),
      "Serial drop was not found in upgrade_stage1_slony.slonik:\n$upgrade_stage1_slony_slonik"
      );
  }

  /**
   * Test that when the only serial is converted to an int, and there are no sequences,
   * that the sequences table and serial trigger is dropped
   *
   * @group mysql5
   */
  public function testSerialToIntMysql5() {
    $this->xml_content_a = $this->mysql5_serial_xml_a;
    $this->xml_file_a = dirname(__FILE__) . '/testdata/type_diff_xml_a.xml';
    file_put_contents($this->xml_file_a, $this->xml_content_a);

    $this->xml_content_b = $this->mysql5_serial_xml_b;
    $this->xml_file_b = dirname(__FILE__) . '/testdata/type_diff_xml_b.xml';
    file_put_contents($this->xml_file_b, $this->xml_content_b);

    $this->apply_options_mysql5();
    
    dbsteward::$generate_slonik = FALSE;
    $this->do_upgrade('mysql5');
    //mysql5::build_upgrade($this->xml_file_a, $this->xml_file_b);

    $upgrade_stage1_schema1_sql = file_get_contents($this->output_prefix . '_upgrade_stage1_schema1.sql');
    $upgrade_stage1_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage1_schema1_sql);

    $upgrade_stage3_schema1_sql = file_get_contents($this->output_prefix . '_upgrade_stage3_schema1.sql');
    $upgrade_stage3_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage3_schema1_sql);
    $this->assertTrue(
      (boolean)preg_match('/DROP TABLE IF EXISTS `__sequences`;/i', $upgrade_stage3_schema1_sql),
      "Sequences drop was not found in upgrade_stage3_schema1.sql:\n$upgrade_stage3_schema1_sql"
      );
    
    $this->assertTrue(
      (boolean)preg_match('/DROP TRIGGER IF EXISTS `__dbsteward_serial_test_test_id_serial_trigger`;/i', $upgrade_stage1_schema1_sql),
      "Serial trigger drop was not found in upgrade_stage1_schema1.sql:\n$upgrade_stage1_schema1_sql"
      );
  }

  /**
   * @group pgsql8
   */
  public function testSingleStageColumnAndDataChangePgsql8() {
    $this->xml_content_a = $this->pgsql8_single_xml_a;
    $this->xml_file_a = __DIR__ . '/testdata/type_diff_xml_a.xml';
    file_put_contents($this->xml_file_a, $this->xml_content_a);

    $this->xml_content_b = $this->pgsql8_single_xml_b;
    $this->xml_file_b = __DIR__ . '/testdata/type_diff_xml_b.xml';
    file_put_contents($this->xml_file_b, $this->xml_content_b);

    $this->apply_options_pgsql8();
    // these options are applied when specifying --singlestageupgrade
    dbsteward::$single_stage_upgrade = TRUE;
    dbsteward::$always_recreate_views = FALSE;
    dbsteward::$generate_slonik = FALSE;

    $this->do_upgrade('pgsql8');
    //pgsql8::build_upgrade($this->xml_file_a, $this->xml_file_b);

    $upgrade_single_stage_sql = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');
    // $upgrade_single_stage_sql = preg_replace('/\s+/', ' ', $upgrade_single_stage_sql);
    $this->assertRegExp(
      '/'.preg_quote('ALTER COLUMN "user_name" TYPE varchar(64)','/').'/i',
      $upgrade_single_stage_sql,
      "user_name column type change was not found in upgrade_single_stage.sql"
    );

    $this->assertRegExp(
      '/'.preg_quote('UPDATE "dbsteward"."user" SET "user_name" = E\'Administrator\' WHERE ("user_id" = \'1\');','/').'/i',
      $upgrade_single_stage_sql,
      "Update of user_name column static value not found in upgrade_single_stage.sql"
    );
  }

  /**
   * @group mysql5
   */
  public function testSingleStageColumnAndDataChangeMysql5() {
    $this->xml_content_a = $this->mysql5_single_xml_a;
    $this->xml_file_a = __DIR__ . '/testdata/type_diff_xml_a.xml';
    file_put_contents($this->xml_file_a, $this->xml_content_a);

    $this->xml_content_b = $this->mysql5_single_xml_b;
    $this->xml_file_b = __DIR__ . '/testdata/type_diff_xml_b.xml';
    file_put_contents($this->xml_file_b, $this->xml_content_b);

    $this->apply_options_mysql5();
    // these options are applied when specifying --singlestageupgrade
    dbsteward::$single_stage_upgrade = TRUE;
    dbsteward::$always_recreate_views = FALSE;
    $this->do_upgrade('mysql5');
    //mysql5::build_upgrade($this->xml_file_a, $this->xml_file_b);
    
    dbsteward::$generate_slonik = FALSE;
    $upgrade_single_stage_sql = file_get_contents($this->output_prefix . '_upgrade_single_stage.sql');
    $upgrade_single_stage_sql = preg_replace('/\s+/', ' ', $upgrade_single_stage_sql);

    $this->assertTrue(
      (boolean)preg_match('/'.preg_quote("ALTER TABLE `user` MODIFY COLUMN `user_name` varchar(64)",'/').'/i', $upgrade_single_stage_sql, $matches),
      "user_name column type change was not found in upgrade_single_stage_sql.sql:\n$upgrade_single_stage_sql"
      );

    $this->assertTrue(
      (boolean)preg_match('/'.preg_quote("UPDATE `user` SET `user_name` = 'Administrator' WHERE (`user_id` = 1);",'/').'/i', $upgrade_single_stage_sql),
      "Update of user_name column static value not found in upgrade_single_stage.sql:\n$upgrade_single_stage_sql"
      );
  }
}

?>
