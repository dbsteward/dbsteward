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
class SlonyIdStreakTest extends dbstewardUnitTestBase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
  }
  /**
   * There was a bug in streaker where it wasn't counting the entire first streak, output used to be for below: 1, 5-6, 98-98
  */
  public function testSlonikStreakerIsGood() {
  $xml = <<<SLONXML
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
      <slonyReplicaSet id="1" comment="only set" originNodeId="1" upgradeSetId="2">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
      </slonyReplicaSet>
    </slony>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="log_sl" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="1">
      <column name="id" type="bigserial" slonyId="1"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log_sl2" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="2">
      <column name="id" type="bigserial" slonyId="2"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log_sl3" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="5">
      <column name="id" type="bigserial" slonyId="5"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log_sl4" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="6">
      <column name="id" type="bigserial" slonyId="6"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log_sl5" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="98">
      <column name="id" type="bigserial" slonyId="98"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <!-- here for additional changes -->
  </schema>
</dbsteward>
SLONXML;

      $old_db_doc = simplexml_load_string($xml);
      dbsteward::$generate_slonik = TRUE;

      ob_start();
      pgsql8::build('', $old_db_doc);
      $output = ob_get_contents();
      ob_end_clean();
      preg_match('/sequence ID segments:\s(.*)\n/', $output, $matches);
      $this->assertEquals("1-2, 5-6, 98", $matches[1]);
  }

}
?>
