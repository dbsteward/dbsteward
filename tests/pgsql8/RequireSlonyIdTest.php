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
  <column name="id" type="serial" slonyId="3"/>
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
<sequence name="seq" owner="ROLE_OWNER" slonyId="4"/>

XML;
  private $sequence_ignore = <<<XML
<sequence name="seq" owner="ROLE_OWNER" slonyId="IGNORE_REQUIRED"/>
XML;
  private $sequence_without = <<<XML
<sequence name="seq" owner="ROLE_OWNER"/>

XML;

  public function testSlonyIdCheck() {
    dbsteward::$require_slony_id = FALSE;
    // since slonyId's are not required, it shouldn't throw for missing or ignored ids

    $ids = array('with','ignore','without');
    for ($i=0; $i<3; $i++) {
      for ($j=0; $j<3; $j++) {
        for ($k=0; $k<3; $k++) {
          $xml = $this->{'table_'.$ids[$i]}.$this->{'column_'.$ids[$j]}.$this->{'sequence_'.$ids[$k]};
          $this->common($xml);
        }
      }
    }

    dbsteward::$require_slony_id = TRUE;
    // now ignores shouldn't throw, but missing should

    $ids = array('with','ignore','without');
    for ($i=0; $i<3; $i++) {
      for ($j=0; $j<3; $j++) {
        for ($k=0; $k<3; $k++) {
          $xml = $this->{'table_'.$ids[$i]}.$this->{'column_'.$ids[$j]}.$this->{'sequence_'.$ids[$k]};
          if ($i == 2 || $j == 2 || $k == 2) {
            try {
              $this->common($xml);
            } catch (Exception $ex) {
              if (stripos($ex->getMessage(), 'missing slonyId and slonyIds are required') === FALSE) {
                $this->fail("Expecting a missing slonyId exception, got: '{$ex->getMessage()}' for\n$xml");
              }
              continue;
            }
            $this->fail("Expecting a missing slonyId exception, got nothing for\n$xml");
          }
          else {
            $this->common($xml);
          }
        }
      }
    }
  }

  /** Generates DDL for a build and upgrade given dbxml fragments **/
  private function common($frag) {

    pgsql8::$table_slony_ids = array();
    pgsql8::$sequence_slony_ids = array();
    pgsql8::$known_pg_identifiers = array();

    $new = <<<XML
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
  <schema name="dbsteward" owner="ROLE_OWNER">
    $frag
  </schema>
</dbsteward>
XML;
    $this->set_xml_content_b($new);
    pgsql8::build($this->xml_file_b);

    $old = <<<XML
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
  <schema name="dbsteward" owner="ROLE_OWNER">
  </schema>
</dbsteward>
XML;
    $this->set_xml_content_a($old);
    pgsql8::build_upgrade($this->xml_file_a, $this->xml_file_b);
  }
}
?>