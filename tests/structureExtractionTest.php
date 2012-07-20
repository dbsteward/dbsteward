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
      <column name="rate_id" type="integer" null="false"/>
      <column name="rate_group_id" foreignSchema="hotel" foreignTable="rate_group" foreignColumn="rate_group_id" null="false"/>
      <column name="rate_name" type="character varying(120)"/>
      <column name="rate_value" type="numeric"/>
    </table>
    <table name="rate_group" owner="ROLE_OWNER" primaryKey="rate_group_id" slonyId="2">
      <column name="rate_group_id" type="integer" null="false"/>
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
    
    $this->write_xml_definition_to_disk();

    // 3) Compare and expect zero differences between A and B
    $this->apply_options_pgsql8();
    pgsql8::build_upgrade($this->xml_file_a, $this->xml_file_b);
    
    $upgrade_stage1_schema1_sql = $this->get_script_compress(__DIR__ . '/testdata/upgrade_stage1_schema1.sql');
    $upgrade_stage2_data1_sql = $this->get_script_compress(__DIR__ . '/testdata/upgrade_stage2_data1.sql');
    $upgrade_stage3_schema1_sql = $this->get_script_compress(__DIR__ . '/testdata/upgrade_stage3_schema1.sql');
    $upgrade_stage4_data1_sql = $this->get_script_compress(__DIR__ . '/testdata/upgrade_stage4_data1.sql');

    // check for no differences as expressed in DDL / DML
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
    
    
    // 4) Check for and validate tables in resultant XML definiton
    $this->compare_xml_definition();
  }
  
  protected function write_xml_definition_to_disk() {
    $this->xml_file_a = __DIR__ . '/testdata/extract_diff_xml_a.xml';
    file_put_contents($this->xml_file_a, $this->xml_content_a);

    $this->xml_file_b = __DIR__ . '/testdata/extract_diff_xml_b.xml';
    file_put_contents($this->xml_file_b, $this->xml_content_b);
  }
  
  protected function get_script($file_name) {
    return file_get_contents($file_name);
  }
  
  protected function get_script_compress($file_name) {
    // kill excess whitespace and newlines for comparison
    return preg_replace('/\s+/', ' ', $this->get_script($file_name));
  }
  
  protected function compare_xml_definition() {
    $doc_a = simplexml_load_file($this->xml_file_a);
    $doc_b = simplexml_load_file($this->xml_file_b);
    
    // are all of the schemas defined in A in B?
    foreach($doc_a->schema AS $schema_a) {
      $schema_b = dbx::get_schema($doc_b, $schema_a['name']);
      $this->assertTrue(is_object($schema_b), $schema_a['name'] . ' schema_b object pointer not found');
      $this->assertEquals($schema_a['name'], $schema_b['name']);
      
      // are all of the tables defined in A in B?
      foreach($schema_a->table AS $table_a) {
        $table_b = dbx::get_table($schema_b, $table_a['name']);
        $this->assertTrue(is_object($table_b), $table_a['name'] . ' table_b object pointer not found');
        $this->assertEquals($table_a['name'], $table_b['name']);
      }
    }
  }
  
  /**
   * Structure Extraction Testing - Mysql 4
   *
   * 1) Build a database from definition A
   * 2) Extract database schema to definition B
   * 3) Compare and expect zero differences between A and B with DBSteward difference engine
   * 4) Check for and validate tables in resultant XML definiton
   * 
   * @param   void
   * @return  void
   */
  public function testBuildExtractCompare_mysql5() {
    // 1) Build a database from definition A
    $this->build_db_mysql5();

    // 2) Extract database schema to definition B
    $this->xml_content_b = mysql5::extract_schema($this->pgsql->get_dbhost(), $this->pgsql->get_dbport(), $this->pgsql->get_dbname(), $this->pgsql->get_dbuser(), $this->pgsql->get_dbpass());
    
    $this->write_xml_definition_to_disk();

    // 3) Compare and expect zero differences between A and B
    $this->apply_options_mysql5();
    mysql5::build_upgrade($this->xml_file_a, $this->xml_file_b);
    
    $upgrade_stage1_schema1_sql = $this->get_script_compress(__DIR__ . '/testdata/upgrade_stage1_schema1.sql');
    $upgrade_stage2_data1_sql = $this->get_script_compress(__DIR__ . '/testdata/upgrade_stage2_data1.sql');
    $upgrade_stage3_schema1_sql = $this->get_script_compress(__DIR__ . '/testdata/upgrade_stage3_schema1.sql');
    $upgrade_stage4_data1_sql = $this->get_script_compress(__DIR__ . '/testdata/upgrade_stage4_data1.sql');

    // check for no differences as expressed in DDL / DML
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
    
    
    // 4) Check for and validate tables in resultant XML definiton
    $this->compare_xml_definition();
  }
  
}

?>
