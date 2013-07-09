<?php
/**
 * Tests that slonyId attributes are correctly checked during both build and upgrade
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

class RequireSlonyIdTest extends dbstewardUnitTestBase {
  private $table_with = <<<XML
<table name="foo" owner="ROLE_OWNER" primaryKey="id" slonyId="1">
  <column name="id" type="int"/>
</table>


XML;
  private $table_ignore = <<<XML
<table name="foo" owner="ROLE_OWNER" primaryKey="id" slonyId="IGNORE_REQUIRED">
  <column name="id" type="int"/>
</table>

XML;
  private $table_without = <<<XML
<table name="foo" owner="ROLE_OWNER" primaryKey="id">
  <column name="id" type="int"/>
</table>

XML;
  private $column_with = <<<XML
<table name="bar" owner="ROLE_OWNER" primaryKey="id" slonyId="2">
  <column name="id" type="serial" slonyId="1"/>
</table>

XML;
  private $column_ignore = <<<XML
<table name="bar" owner="ROLE_OWNER" primaryKey="id" slonyId="2">
  <column name="id" type="serial" slonyId="IGNORE_REQUIRED"/>
</table>

XML;
  private $column_without = <<<XML
<table name="bar" owner="ROLE_OWNER" primaryKey="id" slonyId="2">
  <column name="id" type="serial"/>
</table>

XML;
  private $sequence_with = <<<XML
<sequence name="seq" owner="ROLE_OWNER" slonyId="2"/>

XML;
  private $sequence_ignore = <<<XML
<sequence name="seq" owner="ROLE_OWNER" slonyId="IGNORE_REQUIRED"/>

XML;
  private $sequence_without = <<<XML
<sequence name="seq" owner="ROLE_OWNER"/>

XML;

  public function setUp() {
    $this->xml_file_a = __DIR__ . '/../testdata/unit_test_xml_a.xml';
    $this->xml_file_b = __DIR__ . '/../testdata/unit_test_xml_b.xml';
    $this->output_prefix = dirname(__FILE__) . '/../testdata/unit_test_xml_a';
    dbsteward::set_sql_format('pgsql8');
  }
  
  public function tearDown() {
    // doesn't do anything
  }
  
  public function testSlonyIdCheck() {
    dbsteward::$generate_slonik = FALSE;
    dbsteward::$require_slony_id = FALSE;
    // since slonyId's are not required, it shouldn't throw for missing or ignored ids

    // apologies for commented out echos / var_dumps here, this test is
    // really meta and hard to see what's going on
    
    $ids = array('with','ignore','without');
    for ($i=0; $i<3; $i++) {
      for ($j=0; $j<3; $j++) {
        for ($k=0; $k<3; $k++) {
          $xml = $this->{'table_'.$ids[$i]}.$this->{'column_'.$ids[$j]}.$this->{'sequence_'.$ids[$k]};
//          echo "i = $i j = $j k = $k\n";
//          var_dump($xml);
          $this->common($xml, NULL, FALSE);
          $this->common($xml,$xml, FALSE);
        }
      }
    }

    dbsteward::$require_slony_id = TRUE;
    dbsteward::$generate_slonik = TRUE;
    // now ignores shouldn't throw, but missing should

    $ids = array('with','ignore','without');
    for ($i=0; $i<3; $i++) {
      for ($j=0; $j<3; $j++) {
        for ($k=0; $k<3; $k++) {
          $seq_a = str_replace('name="seq"', 'name="seq_a"', $this->{'sequence_'.$ids[$k]});
          $seq_b = str_replace('name="seq"', 'name="seq_b"', $this->{'sequence_'.$ids[$k]});
          $seq_b = str_replace('slonyId="2"', 'slonyId="3"', $seq_b);
          
          $xml = $this->{'table_'.$ids[$i]} . 
                 $seq_a . 
                 $this->{'column_'.$ids[$j]} . 
                 $seq_b;
                 
//          echo "i: " . $i . " j: " . $j . " k: " . $k . "\n";
          if ($i == 2 || $j == 2 || $k == 2) {
            $obj = $i==2 ? 'table' : ($j==2 ? 'column' : 'sequence');
            try {
//              echo "build test $i $j $k\n";
              $this->common($xml, NULL, TRUE);
            }
            catch (Exception $ex) {
              if (stripos($ex->getMessage(), 'missing slonyId and slonyIds are required') === FALSE) {
                $this->fail("Expecting a missing slonyId exception for the $obj during build, got: '{$ex->getMessage()}' for\n$xml");
              }
              try {
//                echo "build upgrade test $i $j $k\n";
                $this->common($xml, $xml, TRUE);
              }
              catch (Exception $ex) {
                if (stripos($ex->getMessage(), 'missing slonyId and slonyIds are required') === FALSE) {
                  $this->fail("Expecting a missing slonyId exception for the $obj during upgrade, got: '{$ex->getMessage()}' for\n$xml");
                }
                continue;
              }
              $this->fail("Expecting a missing slonyId exception for the $obj during upgrade, got nothing for\n$xml");
            }
            $this->fail("Expecting a missing slonyId exception for the $obj during build, got nothing for\n$xml");
          }
          else {
//            echo "else build test $i $j $k\n";
            $this->common($xml, NULL, TRUE);
//            echo "else build upgrade test $i $j $k\n";
            $this->common($xml,$xml, TRUE);
          }
        }
      }
    }
  }

  /** Generates DDL for a build or upgrade given dbxml fragments **/
  private function common($old, $new=FALSE, $generate_slonik = TRUE) {

    pgsql8::$table_slony_ids = array();
    pgsql8::$sequence_slony_ids = array();
    pgsql8::$known_pg_identifiers = array();
    if (is_string($old) && empty($old)) {
//      $old = <<<XML
//<table name="foo" owner="ROLE_OWNER" primaryKey="id" slonyId="1">
//  <column name="id" type="int"/>
//</table>
//<sequence name="seq" owner="ROLE_OWNER" slonyId="4"/>
//XML;

    }
    $xml_a = <<<XML
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
  <schema name="dbsteward" owner="ROLE_OWNER">
    $old
  </schema>
</dbsteward>
XML;
    $this->set_xml_content_a($xml_a);

    if ($new) {
      $xml_b = <<<XML
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
  <schema name="dbsteward" owner="ROLE_OWNER">
    $new
  </schema>
</dbsteward>
XML;
      $this->set_xml_content_b($xml_b);

      ob_start();
      
      try {

        // new parameters for function:
        // $old_output_prefix, $old_composite_file, $old_db_doc, $old_files, $new_output_prefix, $new_composite_file, $new_db_doc, $new_files
        $old_db_doc = simplexml_load_file($this->xml_file_a);
        $new_db_doc = simplexml_load_file($this->xml_file_b);
        dbsteward::$generate_slonik = $generate_slonik;

        pgsql8::build_upgrade('', $old_db_doc, $old_db_doc, array(), $this->output_prefix, $new_db_doc, $new_db_doc, array());
        ob_end_clean();
      }
      catch (Exception $ex) {
        ob_end_clean();
        throw $ex;
      }
    }
    else {
      ob_start();
      try {
        $db_doc = simplexml_load_file($this->xml_file_a);
        dbsteward::$generate_slonik = $generate_slonik;
        pgsql8::build($this->output_prefix, $db_doc);
        ob_end_clean();
      }
      catch (Exception $ex) {
        ob_end_clean();
        throw $ex;
      }
    }
  }
}
?>