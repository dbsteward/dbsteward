<?php
/**
 * Test the output of dbsteward diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/dbstewardUnitTestBase.php';

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
    <slony>
      <masterNode id="1"/>
      <replicaNode id="2" providerId="1"/>
      <replicaNode id="3" providerId="2"/>
      <replicationSet id="1"/>
      <replicationUpgradeSet id="2"/>
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
    <slony>
      <masterNode id="1"/>
      <replicaNode id="2" providerId="1"/>
      <replicaNode id="3" providerId="2"/>
      <replicationSet id="1"/>
      <replicationUpgradeSet id="2"/>
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
    <slony>
      <masterNode id="1"/>
      <replicaNode id="2" providerId="1"/>
      <replicaNode id="3" providerId="2"/>
      <replicationSet id="1"/>
      <replicationUpgradeSet id="2"/>
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
    <slony>
      <masterNode id="1"/>
      <replicaNode id="2" providerId="1"/>
      <replicaNode id="3" providerId="2"/>
      <replicationSet id="1"/>
      <replicationUpgradeSet id="2"/>
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
    pgsql8::build_upgrade($this->xml_file_a, $this->xml_file_b);

    $upgrade_stage1_schema1_sql = file_get_contents(__DIR__ . '/testdata/upgrade_stage1_schema1.sql');
    $upgrade_stage1_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage1_schema1_sql);
    $this->assertTrue(
      (boolean)preg_match('/ALTER TABLE dbsteward."serial_test" ALTER COLUMN "test_id" TYPE int/i', $upgrade_stage1_schema1_sql),
      "Column type change was not found in upgrade_stage1_schema1.sql:\n$upgrade_stage1_schema1_sql"
      );
    $this->assertTrue(
      (boolean)preg_match('/ALTER COLUMN "test_id" DROP DEFAULT/i', $upgrade_stage1_schema1_sql),
      "Removal of SERIAL default not found in upgrade_stage1_schema1.sql:\n$upgrade_stage1_schema1_sql"
      );

    $upgrade_stage3_schema1_sql = file_get_contents(__DIR__ . '/testdata/upgrade_stage3_schema1.sql');
    $upgrade_stage3_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage3_schema1_sql);
    $this->assertTrue(
      (boolean)preg_match('/DROP SEQUENCE IF EXISTS dbsteward."serial_test_test_id_seq"/i', $upgrade_stage3_schema1_sql),
      "Serial drop was not found in upgrade_stage3_schema1.sql:\n$upgrade_stage3_schema1_sql"
      );

    $upgrade_stage1_slony_slonik = file_get_contents(__DIR__ . '/testdata/upgrade_stage1_slony.slonik');
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
    mysql5::build_upgrade($this->xml_file_a, $this->xml_file_b);

    $upgrade_stage1_schema1_sql = file_get_contents(__DIR__ . '/testdata/upgrade_stage1_schema1.sql');
    $upgrade_stage1_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage1_schema1_sql);

    $upgrade_stage3_schema1_sql = file_get_contents(__DIR__ . '/testdata/upgrade_stage3_schema1.sql');
    $upgrade_stage3_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage3_schema1_sql);
    $this->assertTrue(
      (boolean)preg_match('/DROP TABLE IF EXISTS `__sequences`;/i', $upgrade_stage3_schema1_sql),
      "Sequences drop was not found in upgrade_stage3_schema1.sql:\n$upgrade_stage3_schema1_sql"
      );
    
    $this->assertTrue(
      (boolean)preg_match('/DROP TRIGGER IF EXISTS __dbsteward_serial_test_test_id_serial_trigger;/i', $upgrade_stage1_schema1_sql),
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

    $this->apply_options_mysql5();
    // these options are applied when specifying --singlestageupgrade
    dbsteward::$single_stage_upgrade = TRUE;
    dbsteward::$always_recreate_views = FALSE;
    pgsql8::build_upgrade($this->xml_file_a, $this->xml_file_b);

    $upgrade_single_stage_sql = file_get_contents(__DIR__ . '/testdata/upgrade_single_stage.sql');
    $upgrade_single_stage_sql = preg_replace('/\s+/', ' ', $upgrade_single_stage_sql);
    $this->assertTrue(
      (boolean)preg_match('/'.preg_quote('ALTER TABLE "dbsteward"."user" ALTER COLUMN "user_name" TYPE varchar(64)','/').'/i', $upgrade_single_stage_sql),
      "user_name column type change was not found in upgrade_single_stage.sql:\n$upgrade_single_stage_sql"
    );

    $this->assertTrue(
      (boolean)preg_match('/'.preg_quote('UPDATE "dbsteward"."user" SET "user_name" = E\'Administrator\' WHERE ("user_id" = E\'1\');','/').'/i', $upgrade_single_stage_sql),
      "Update of user_name column static value not found in upgrade_single_stage.sql:\n$upgrade_single_stage_sql"
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
    mysql5::build_upgrade($this->xml_file_a, $this->xml_file_b);

    $upgrade_single_stage_sql = file_get_contents(__DIR__ . '/testdata/upgrade_single_stage.sql');
    $upgrade_single_stage_sql = preg_replace('/\s+/', ' ', $upgrade_single_stage_sql);
    $this->assertTrue(
      (boolean)preg_match('/'.preg_quote("ALTER TABLE `user` MODIFY COLUMN `user_name` varchar(64)",'/').'/i', $upgrade_single_stage_sql),
      "user_name column type change was not found in upgrade_single_stage_sql.sql:\n$upgrade_single_stage_sql"
      );

    $this->assertTrue(
      (boolean)preg_match('/'.preg_quote("UPDATE `user` SET `user_name` = 'Administrator' WHERE (`user_id` = '1');",'/').'/i', $upgrade_single_stage_sql),
      "Update of user_name column static value not found in upgrade_single_stage.sql:\n$upgrade_single_stage_sql"
      );
  }
}

?>
