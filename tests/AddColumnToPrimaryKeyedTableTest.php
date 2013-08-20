<?php

require_once dirname(__FILE__) . '/dbstewardUnitTestBase.php';

class AddColumnToPrimaryKeyedTableTest extends dbstewardUnitTestBase {

  protected $xml_content_a = <<<XML
<dbsteward>
  <database>
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
    <table name="serial_test" owner="ROLE_OWNER" primaryKey="test_string" slonyId="1">
      <column name="test_string" type="text"/>
      <column name="test_number" type="integer"/>
      <rows columns="test_string, test_number">
        <tabrow>testtest	12345</tabrow>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;

  protected $xml_content_b = <<<XML
<dbsteward>
  <database>
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
    <table name="serial_test" owner="ROLE_OWNER" primaryKey="test_string" slonyId="1">
      <column name="test_serial" type="serial" slonyId="2"/>
      <column name="test_string" type="text"/>
      <column name="test_number" type="integer"/>
      <rows columns="test_serial, test_string, test_number">
        <tabrow>1	testtest	12345</tabrow>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;

  public function setUp() {
    parent::setUp();
  }

  /**
   * @group pgsql8
   * @group mysql5
   * @group mssql10
   */
  public function testAddSerialColumn() {
    $dir = __DIR__;
    $cmd = "$dir/../dbsteward.php --oldxml=$dir/testdata/unit_test_xml_a.xml --newxml=$dir/testdata/unit_test_xml_b.xml";
    system($cmd);
    $this->output_prefix = str_replace('xml_a', 'xml_b', $this->output_prefix);
    $sql_file = file_get_contents($this->output_prefix . '_upgrade_stage4_data1.sql');
    $match = stristr($sql_file, "UPDATE dbsteward.serial_test SET test_serial = 1 WHERE (test_string = E'testtest');");
    $this->assertTrue($match !== FALSE);
  }
}
