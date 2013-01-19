<?php
/**
 * DBSteward database structure extraction test base
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/dbstewardUnitTestBase.php';

class structureExtractionTest extends dbstewardUnitTestBase {

  /**
   * @group pgsql8
   */
  public function testBuildExtractCompare_pgsql8() {
    $xml = <<<XML
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
  <language name="plpgsql" procedural="true" owner="ROLE_OWNER" />
  <schema name="dbsteward" owner="ROLE_OWNER">
    <function name="db_config_parameter" returns="text" owner="ROLE_OWNER" cachePolicy="VOLATILE" description="used to push configurationParameter values permanently into the database configuration">
      <functionParameter name="config_parameter" type="text"/>
      <functionParameter name="config_value" type="text"/>
      <functionDefinition language="plpgsql" sqlFormat="pgsql8">
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

    $this->do_structure_test('pgsql8', $xml);
  }

  /**
   * @group mysql5
   */
  public function testBuildExtractCompare_mysql5() {
    $xml = <<<XML
<dbsteward>
  <database>
    <host>127.0.0.1</host>
    <name>dbsteward_phpunit</name>
    <role>
      <application>deployment</application>
      <owner>deployment</owner>
      <replication>deployment</replication>
      <readonly>deployment</readonly>
    </role>
    <configurationParameter name="TIME ZONE" value="America/New_York" />
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="rate" owner="ROLE_OWNER" primaryKey="rate_id">
      <tableOption name="engine" sqlFormat="mysql5" value="InnoDB" />
      <tableOption name="default charset" sqlFormat="mysql5" value="latin1" />
      <column name="rate_id" type="serial" null="false" />
      <column name="rate_group_id" foreignSchema="public" foreignTable="rate_group" foreignColumn="rate_group_id" null="false" />
      <column name="rate_name" type="varchar(120)" />
      <column name="rate_value" type="decimal(10,0)" />
    </table>
    <table name="rate_group" owner="ROLE_OWNER" primaryKey="rate_group_id">
      <tableOption name="engine" sqlFormat="mysql5" value="InnoDB" />
      <tableOption name="default charset" sqlFormat="mysql5" value="latin1" />
      <column name="rate_group_id" type="int(11)" null="false" />
      <column name="rate_group_name" type="varchar(100)" />
      <column name="rate_group_enabled" type="tinyint(1)" null="false" default="1" />
    </table>
    <function name="test_concat" returns="text" owner="ROLE_OWNER" cachePolicy="VOLATILE" description="a test function that concats strings">
      <functionParameter name="param1" type="text" />
      <functionParameter name="param2" type="text" />
      <functionDefinition language="sql" sqlFormat="mysql5">
        RETURN CONCAT(param1, param2);
      </functionDefinition>
    </function>
  </schema>
</dbsteward>
XML;

    $this->do_structure_test('mysql5', $xml);
  }
  
  /**
   * Structure Extraction Testing
   *
   * 1) Build a database from definition A
   * 2) Extract database schema to definition B
   * 3) Compare and expect zero differences between A and B with DBSteward difference engine
   * 4) Check for and validate tables in resultant XML definiton
   */
  public function do_structure_test($format, $xml) {

    $this->set_xml_content_a($xml);

    // 1) Build a database from definition A
    $this->build_db($format);

    // 2) Extract database schema to definition B
    $conn = $this->get_connection($format);
    $this->xml_content_b = $format::extract_schema($conn->get_dbhost(), $conn->get_dbport(), $conn->get_dbname(), $conn->get_dbuser(), $conn->get_dbpass());
    
    $this->write_xml_definition_to_disk();

    // 3) Compare and expect zero differences between A and B
    $this->apply_options($format);
    $format::build_upgrade($this->xml_file_a, $this->xml_file_b);
    
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
  
}

?>
