<?php
/**
 * Tests that slonyId output is correct
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author <jettea46@yahoo.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class SlonyIdOutputTest extends dbstewardUnitTestBase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
  }

  public function testSlonikOutputIsCorrect() {
  $xml = <<<OUTXML
<?xml version="1.0" encoding="UTF-8"?>
<dbsteward>
  <database>
    <sqlformat>pgsql8</sqlformat>
    <role>
      <application>application</application>
      <owner>dba</owner>
      <replication>slony</replication>
      <readonly>readonly</readonly>
    </role>
    <slony clusterName="aim">
      <slonyNode id="1" comment="Master" dbPassword="wonk" dbUser="aim_slony" dbHost="db00" dbName="mrh"/>
      <slonyNode id="2" comment="Replica" dbPassword="wonk" dbUser="aim_slony" dbHost="db01" dbName="mrh"/>
      <slonyReplicaSet id="101" comment="only set" originNodeId="1" upgradeSetId="2">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
      </slonyReplicaSet>
      <slonyReplicaSet id="201" comment="only set" originNodeId="1" upgradeSetId="3">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
      </slonyReplicaSet>
    </slony>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="log" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonySetId="101" slonyId="101">
      <column name="id" type="bigserial" slonySetId="101" slonyId="101"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonySetId="101" slonyId="102">
      <column name="id" type="bigserial" slonySetId="101" slonyId="102"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonySetId="201" slonyId="105">
      <column name="id" type="bigserial" slonySetId="201" slonyId="105"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonySetId="201" slonyId="106">
      <column name="id" type="bigserial" slonySetId="201" slonyId="106"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="1098">
      <column name="id" type="bigserial" slonyId="1098"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <!-- here for additional changes -->
  </schema>
</dbsteward>
OUTXML;

      $old_db_doc = simplexml_load_string($xml);
      dbsteward::$generate_slonik = TRUE;

      ob_start();
      pgsql8::build('', $old_db_doc);
      $output = ob_get_contents();
      ob_end_clean();
      preg_match('/sequence ID segments:\s(.*)\n/', $output, $matches);
      $this->assertEquals("1098", $matches[1]);
      preg_match('/101:\s(.*)\n/', $output, $matches);
      $this->assertEquals("101-102", $matches[1]);
      preg_match('/201:\s(.*)\n/', $output, $matches);
      $this->assertEquals("105-106", $matches[1]);
  }
  
}
?>
