<?php
/**
 * Tests that slonyId output is correct
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author <jettea46@yahoo.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class SlonyIdOutputTest extends dbstewardUnitTestBase {

  public function setUp() {
    $this->testHandler = new Monolog\Handler\TestHandler;
    dbsteward::get_logger()->pushHandler($this->testHandler);

    dbsteward::set_sql_format('pgsql8');
    pgsql8::$table_slony_ids = array();
    pgsql8::$sequence_slony_ids = array();
  }

  public function tearDown() {
    dbsteward::get_logger()->popHandler();
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

      $output_prefix_path = dirname(__FILE__) . '/../testdata/' . 'slony_id_output';
      pgsql8::build($output_prefix_path, $old_db_doc);

      $this->assertLogged(Monolog\Logger::NOTICE, '/101:\s101-102/');
      // before 1098 wasn't getting put into first natural order, now it should be
      $this->assertLogged(Monolog\Logger::NOTICE, '/101:\s[\d\-]+,\s*1098/', "SlonyIds without slonySetIds are not put into first natural order slonySet");
      $this->assertLogged(Monolog\Logger::NOTICE, '/201:\s105-106/');
  }
  

  private function assertLogged($level, $regex, $message = null) {
    $this->assertTrue($this->testHandler->hasRecordThatMatches($regex, $level),
      "Expected to find a log matching $regex\n$message");
  }
}
?>
