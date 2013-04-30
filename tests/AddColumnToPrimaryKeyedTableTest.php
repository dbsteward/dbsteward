<?php

require_once dirname(__FILE__) . '/dbstewardUnitTestBase.php';

class AddColumnToPrimaryKeyedTableTest extends dbstewardUnitTestBase {

  protected $xml_content_a = <<<XML
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

  public function testAddSerialColumn() {
    system(dirname(__FILE__) . "/../dbsteward.php --oldxml=testdata/unit_test_xml_a.xml --newxml=testdata/unit_test_xml_b.xml");

    $sql_file = file_get_contents(dirname(__FILE__) . '/testdata/upgrade_stage2_data1.sql');
    $match = stristr($sql_file, "UPDATE dbsteward.serial_test SET test_serial = 1 WHERE (test_string = E'testtest');");
    $this->assertTrue($match !== FALSE);
    
  }
}
