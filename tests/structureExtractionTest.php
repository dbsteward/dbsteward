<?php
/**
 * DBSteward database structure extraction tests
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/dbstewardUnitTestBase.php';

class structureExtractionTest extends dbstewardUnitTestBase {

  protected function setUp() {
    $this->xml_content_a = <<<XML
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
    <function name="db_config_parameter" returns="text" language="plpgsql" owner="ROLE_OWNER" cachePolicy="VOLATILE" description="used to push configurationParameter values permanently into the database configuration">
      <functionParameter name="config_parameter" type="text"/>
      <functionParameter name="config_value" type="text"/>
      <functionDefinition>
        DECLARE
          q text;
          name text;
          n text;
        BEGIN
          SELECT INTO name current_database();
          q := 'ALTER DATABASE ' || name || ' SET ' || config_parameter || ' ''' || config_value || ''';';
          n := 'DB CONFIG CHANGE: ' || q;
          RAISE NOTICE '%', n;
          EXECUTE q;
          RETURN n;
        END;
      </functionDefinition>
    </function>
  </schema>
  <schema name="hotel" owner="ROLE_OWNER">
    <table name="rate" owner="ROLE_OWNER" primaryKey="rate_id" slonyId="1">
      <column name="rate_id" type="serial"/>
      <column name="rate_group_id" foreignSchema="hotel" foreignTable="rate_group" foreignColumn="rate_group_id" null="false"/>
      <column name="rate_name" type="character varying(120)"/>
      <column name="rate_value" type="numeric(6, 2)"/>
    </table>
    <table name="rate_group" owner="ROLE_OWNER" primaryKey="rate_group_id" slonyId="2">
      <column name="rate_group_id" type="integer"/>
      <column name="rate_group_name" type="character varying(100)"/>
      <column name="rate_group_enabled" type="boolean" null="false" default="true"/>
    </table>
  </schema>
</dbsteward>
XML;

    parent::setUp();
  }
  
  /**
   * Structure Extraction Testing - Postgresql 8
   *
   * 1) Build a database from definition A
   * 2) Extract database schema to definition B
   * 3) Compare and expect zero differences between A and B with DBSteward difference engine
   * 4) Check for and validate tables in resultant XML definiton
   * 
   * @param   void
   * @return  void
   */
  public function testBuildExtractCompare_pgsql8() {
    // 1) Build a database from definition A
    $this->build_db_pgsql8();

    // 2) Extract database schema to definition B
    $this->xml_content_b = pgsql8::extract_schema($this->pgsql->get_dbhost(), $this->pgsql->get_dbport(), $this->pgsql->get_dbname(), $this->pgsql->get_dbuser(), $this->pgsql->get_dbpass());

    // 3) Compare and expect zero differences between A and B
    $this->xml_file_a = dirname(__FILE__) . '/testdata/extract_diff_xml_a.xml';
    file_put_contents($this->xml_file_a, $this->xml_content_a);
    $this->xml_file_b = dirname(__FILE__) . '/testdata/extract_diff_xml_b.xml';
    file_put_contents($this->xml_file_b, $this->xml_content_b);

    $this->apply_options_pgsql8();
    pgsql8::build_upgrade($this->xml_file_a, $this->xml_file_b);
    
    $upgrade_stage1_schema1_sql = file_get_contents(dirname(__FILE__) . '/testdata/upgrade_stage1_schema1.sql');
    $upgrade_stage1_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage1_schema1_sql);
    $upgrade_stage1_slony_slonik = file_get_contents(dirname(__FILE__) . '/testdata/upgrade_stage1_slony.slonik');
    $upgrade_stage1_slony_slonik = preg_replace('/\s+/', ' ', $upgrade_stage1_slony_slonik);
    $upgrade_stage2_data1_sql = file_get_contents(dirname(__FILE__) . '/testdata/upgrade_stage2_data1.sql');
    $upgrade_stage2_data1_sql = preg_replace('/\s+/', ' ', $upgrade_stage2_data1_sql);
    $upgrade_stage3_schema1_sql = file_get_contents(dirname(__FILE__) . '/testdata/upgrade_stage3_schema1.sql');
    $upgrade_stage3_schema1_sql = preg_replace('/\s+/', ' ', $upgrade_stage3_schema1_sql);
    $upgrade_stage3_slony_slonik = file_get_contents(dirname(__FILE__) . '/testdata/upgrade_stage3_slony.slonik');
    $upgrade_stage3_slony_slonik = preg_replace('/\s+/', ' ', $upgrade_stage3_slony_slonik);
    $upgrade_stage4_data1_sql = file_get_contents(dirname(__FILE__) . '/testdata/upgrade_stage4_data1.sql');
    $upgrade_stage4_data1_sql = preg_replace('/\s+/', ' ', $upgrade_stage4_data1_sql);

    $this->assertEquals(
      0,
      preg_match('/ALTER TABLE/i', $upgrade_stage1_schema1_sql),
      "ALTER TABLE token found in upgrade_stage1_schema1_sql"
    );
    
    $this->assertEquals(
      0,
      preg_match('/INSERT INTO/i', $upgrade_stage2_data1_sql),
      "INSERT INTO token found in upgrade_stage2_data1_sql"
    );

    $this->assertEquals(
      0,
      preg_match('/ALTER TABLE/i', $upgrade_stage3_schema1_sql),
      "ALTER TABLE token found in upgrade_stage3_schema1_sql"
    );
    
    $this->assertEquals(
      0,
      preg_match('/DELETE FROM/i', $upgrade_stage4_data1_sql),
      "DELETE FROM token found in upgrade_stage4_data1_sql"
    );
    
    
    // @TODO: 4) Check for and validate tables in resultant XML definiton
  }
  
}

?>
