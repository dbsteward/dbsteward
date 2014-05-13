<?php
/**
 * Tests that slonyId attributes are correctly checked during both build and upgrade
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Rusty Hamilton <rusty@shrub3.net>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class SlonyIdDiffTest extends PHPUnit_Framework_TestCase {
  
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
    <slony clusterName="aim">
      <slonyNode id="1" comment="Master" dbPassword="wonk" dbUser="aim_slony" dbHost="db00" dbName="mrh"/>
      <slonyNode id="2" comment="Replica" dbPassword="wonk" dbUser="aim_slony" dbHost="db01" dbName="mrh"/>
      <slonyReplicaSet id="1" comment="only set" originNodeId="1" upgradeSetId="2">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
      </slonyReplicaSet>
    </slony>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="log" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="1">
      <column name="id" type="bigserial"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
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
    <slony clusterName="aim">
      <slonyNode id="1" comment="Master" dbPassword="wonk" dbUser="aim_slony" dbHost="db00" dbName="mrh"/>
      <slonyNode id="2" comment="Replica" dbPassword="wonk" dbUser="aim_slony" dbHost="db01" dbName="mrh"/>
      <slonyReplicaSet id="1" comment="only set" originNodeId="1" upgradeSetId="2">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
      </slonyReplicaSet>
    </slony>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="log" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="1">
      <column name="id" type="bigserial" slonyId="1"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <!-- here for additional changes -->
  </schema>
</dbsteward>
XML;
  
  protected function build_replica_sets_for_test() {
    dbsteward::$old_database = new SimpleXMLElement($this->oldxml);
    dbsteward::$new_database = new SimpleXMLElement($this->newxml);
    pgsql8_diff::$new_table_dependency = xml_parser::table_dependency_order(dbsteward::$new_database);
    pgsql8_diff::$old_table_dependency = xml_parser::table_dependency_order(dbsteward::$old_database);
    
    $old_replica_set = pgsql8::get_slony_replica_sets(dbsteward::$old_database);
    $new_replica_set = pgsql8::get_slony_replica_sets(dbsteward::$new_database);
    $slony_prefix = dirname(__FILE__) . '/../testdata/slonyid_diff_test';
    pgsql8::build_upgrade_slonik_replica_set(dbsteward::$old_database, dbsteward::$new_database, $old_replica_set, $new_replica_set, $slony_prefix);    
    return $slony_prefix;
  }
  
  public function setUp() {
    parent::setUp();
    dbsteward::set_sql_format('pgsql8');
    
    // clear these before each test so we don't run into conflicts
    pgsql8::$table_slony_ids = array();
    pgsql8::$sequence_slony_ids = array();
    dbsteward::$generate_slonik = TRUE;
    pgsql8_diff::$new_table_dependency = null;
    pgsql8_diff::$old_table_dependency = null;
  }
  
  protected function transaction_statement_check($expected) {
    dbsteward::$old_database = new SimpleXMLElement($this->oldxml);
    dbsteward::$new_database = new SimpleXMLElement($this->newxml);
    pgsql8_diff::$new_table_dependency = xml_parser::table_dependency_order(dbsteward::$new_database);
    pgsql8_diff::$old_table_dependency = xml_parser::table_dependency_order(dbsteward::$old_database);
    
    $ofs = new mock_output_file_segmenter();

    pgsql8_diff::diff_doc_work($ofs, $ofs, $ofs, $ofs);
    $this->assertEquals($expected, stripos(trim($ofs->_get_output()), 'BEGIN') === FALSE);
    $this->assertEquals($expected, stripos(trim($ofs->_get_output()), 'COMMIT') === FALSE);
  }
  
  public function testGenerateSlonikRemovesTransactionStatements() {
    dbsteward::$generate_slonik = TRUE;
    $this->transaction_statement_check(TRUE);
    dbsteward::$generate_slonik = FALSE;
    $this->transaction_statement_check(FALSE);
  }

  
  public function testSlonikChangesMadeForExistingSequence() {
    $slony_prefix = $this->build_replica_sets_for_test();
    
    $expected = <<<EXPECTED
SET ADD SEQUENCE (
  SET ID = 2,
  ORIGIN = 1,
  ID = 1,
  FULLY QUALIFIED NAME = 'public.log_id_seq',
  COMMENT = 'public.log_id_seq serial sequence column replication'
);
EXPECTED;
    $this->do_slonik_change_checking($expected, $slony_prefix);
    
  }
  
  public function testSlonikChangesRegressionTest() {
    // make sure we still generate slonik changes for sequences that
    // aren't present at all in original table; this was previous behavior
    $additional_table = <<<TABLEXML
    <table name="testtable" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="2">
      <column name="id" type="bigserial" slonyId="2"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
TABLEXML;
        
    $expected1 = <<<EXPECTED
SET ADD SEQUENCE (
  SET ID = 2,
  ORIGIN = 1,
  ID = 1,
  FULLY QUALIFIED NAME = 'public.log_id_seq',
  COMMENT = 'public.log_id_seq serial sequence column replication'
);
EXPECTED;
      

    $expected2 = <<<EXPECTED2
SET ADD SEQUENCE (
  SET ID = 2,
  ORIGIN = 1,
  ID = 2,
  FULLY QUALIFIED NAME = 'public.testtable_id_seq',
  COMMENT = 'public.testtable_id_seq serial sequence column replication'
);
EXPECTED2;
      
      
    $this->newxml = str_replace('<!-- here for additional changes -->', $additional_table, $this->newxml);
    $slony_prefix = $this->build_replica_sets_for_test();
    $expecteds = array($expected1, $expected2);
    $this->do_slonik_change_checking($expecteds, $slony_prefix);
  }
  
  protected function do_slonik_change_checking($expecteds, $slony_filename_prefix) {
    if (!is_array($expecteds)) {
      $expecteds = array($expecteds);
    }
    $actual = file_get_contents($slony_filename_prefix . '_stage3.slonik');
    $not_expected = file_get_contents($slony_filename_prefix . '_stage1.slonik');
    foreach ($expecteds as $expected) {
      // make sure expected changes are present in file we expect them to be in
      $this->assertTrue(stripos($actual, $expected) !== FALSE);
      // make sure changes are NOT present in stage1 slonik
      $this->assertTrue(stripos($not_expected, $expected) === FALSE);     
    }
  }
  
  protected function generate_slonik_testing($check) {
    $slony_filename_prefix = $this->build_replica_sets_for_test();
    $expecteds = array('BEGIN', 'COMMIT');
    $stage3_file = file_get_contents($slony_filename_prefix . '_stage3.slonik');
    $stage1_file = file_get_contents($slony_filename_prefix . '_stage1.slonik');
    
    foreach ($expecteds as $expected) {
      $this->assertTrue(stripos($stage1_file, $expected) === $check);
      $this->assertTrue(stripos($stage3_file, $expected) === $check);
    }    
  }
  
}
