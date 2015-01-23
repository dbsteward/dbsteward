<?php
/**
 * Test that output SQL files contain transactions when they should
 *
 * @package DBSteward
 * @license BSD 2 Clause <http://opensource.org/licenses/BSD-2-Clause>
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class SlonyStageTransactionalityTest extends PHPUnit_Framework_TestCase {
  
  protected $oldxml = <<<XML
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
    <slony clusterName="farmmachine">
      <slonyNode id="1" comment="Master" dbPassword="tract0r" dbUser="farmmachine_slony" dbHost="db00" dbName="fmdb"/>
      <slonyNode id="2" comment="Replica" dbPassword="tract0r" dbUser="farmmachine_slony" dbHost="db01" dbName="fmdb"/>
      <slonyReplicaSet id="1" comment="only set" originNodeId="1" upgradeSetId="2">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
      </slonyReplicaSet>
    </slony>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="log" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="1">
      <column name="id" type="bigserial"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT"/>
    </table>
    <table name="log_category" owner="ROLE_OWNER" primaryKey="category" slonyId="2">
      <column name="category" type="varchar(50)" />
      <grant role="ROLE_APPLICATION" operation="SELECT"/>
      <rows columns="category">
        <row>
          <col>GENERIC</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
  
  protected $newxml = <<<XML
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
    <slony clusterName="farmmachine">
      <slonyNode id="1" comment="Master" dbPassword="tract0r" dbUser="farmmachine_slony" dbHost="db00" dbName="fmdb"/>
      <slonyNode id="2" comment="Replica" dbPassword="tract0r" dbUser="farmmachine_slony" dbHost="db01" dbName="fmdb"/>
      <slonyReplicaSet id="1" comment="only set" originNodeId="1" upgradeSetId="2">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
      </slonyReplicaSet>
    </slony>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="log" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="1">
      <column name="id" type="bigserial" slonyId="1"/>
      <column name="category" foreignSchema="public" foreignTable="log_category" null="false"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE"/>
    </table>
    <table name="log_category" owner="ROLE_OWNER" primaryKey="category" slonyId="2">
      <column name="category" type="varchar(100)" />
      <column name="severity" type="int" null="false" default="0" />
      <grant role="ROLE_APPLICATION" operation="SELECT"/>
      <rows columns="category">
        <row>
          <col>GENERAL</col>
          <col>0</col>
        </row>
        <row>
          <col>DATALOSS</col>
          <col>90</col>
        </row>
        <row>
          <col>SQLINJECTION</col>
          <col>100</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
  
  protected function diff_definitions($output_prefix) {
    dbsteward::$old_database = new SimpleXMLElement($this->oldxml);
    dbsteward::$new_database = new SimpleXMLElement($this->newxml);
    pgsql8_diff::$new_table_dependency = xml_parser::table_dependency_order(dbsteward::$new_database);
    pgsql8_diff::$old_table_dependency = xml_parser::table_dependency_order(dbsteward::$old_database);

    $output_prefix_path = dirname(__FILE__) . '/../testdata/' . $output_prefix;
    pgsql8::build_upgrade('', 'old_SlonyStageTransactionalityTest', dbsteward::$old_database, array(), $output_prefix_path, 'new_SlonyStageTransactionalityTest', dbsteward::$new_database, array());
    return $output_prefix_path;
  }
  
  public function setUp() {
    parent::setUp();
    dbsteward::set_sql_format('pgsql8');
    
    // reset runtime mode flags to their default
    dbsteward::$single_stage_upgrade = FALSE;
    dbsteward::$generate_slonik = FALSE;
    pgsql8_diff::$as_transaction = TRUE;

    // reset runtime tracking variables
    pgsql8::$table_slony_ids = array();
    pgsql8::$sequence_slony_ids = array();
    pgsql8::$known_pg_identifiers = array();
    pgsql8_diff::$new_table_dependency = null;
    pgsql8_diff::$old_table_dependency = null;
  }
  
  public function testStageTransactional() {
    dbsteward::$generate_slonik = FALSE;

    $output_file_prefix = $this->diff_definitions('slony_stage_transaction');
    
    $regexp_begin = '/^BEGIN;/im';
    $this->check_stage_content($output_file_prefix, '_upgrade_stage1_schema1.sql', $regexp_begin);
    $this->check_stage_content($output_file_prefix, '_upgrade_stage2_data1.sql', $regexp_begin);
    $this->check_stage_content($output_file_prefix, '_upgrade_stage3_schema1.sql', $regexp_begin);
    $this->check_stage_content($output_file_prefix, '_upgrade_stage4_data1.sql', $regexp_begin);
  }
  
  public function testStageNotTransactionalWithGenerateSlonik() {
    dbsteward::$generate_slonik = TRUE;

    $output_file_prefix = $this->diff_definitions('slony_stage_not_transaction');
    
    $regexp_begin = '/^BEGIN;/im';
    $regexp_not_begin = '/^(?!BEGIN;).*$/im';
    $this->check_stage_content($output_file_prefix, '_upgrade_slony_replica_set_1_stage1_schema1.sql', $regexp_not_begin);
    $this->check_stage_content($output_file_prefix, '_upgrade_slony_replica_set_1_stage2_data1.sql', $regexp_begin);
    $this->check_stage_content($output_file_prefix, '_upgrade_slony_replica_set_1_stage3_schema1.sql', $regexp_not_begin);
    $this->check_stage_content($output_file_prefix, '_upgrade_slony_replica_set_1_stage4_data1.sql', $regexp_begin);
  }
  
  protected function check_stage_content($filename_prefix, $stage_file, $pattern) {
    $stage_content = file_get_contents($filename_prefix . $stage_file);
    $this->assertRegExp($pattern, $stage_content);
  }
  
}
