<?php
/**
 * DBSteward database structure extraction test base
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/dbstewardUnitTestBase.php';

abstract class structureExtractionTestBase extends dbstewardUnitTestBase {

  // IMPORTANT: in subclasses' setUp method, assign $this->xml_content_a
  
  // Override these in subclasses to delegate to appropriate methods/objects
  protected abstract function build_db();
  protected abstract function get_connection();
  protected abstract function apply_options();
  
  /**
   * Structure Extraction Testing
   *
   * 1) Build a database from definition A
   * 2) Extract database schema to definition B
   * 3) Compare and expect zero differences between A and B with DBSteward difference engine
   * 4) Check for and validate tables in resultant XML definiton
   */
  public function testBuildExtractCompare() {
    // 1) Build a database from definition A
    $this->build_db();

    // 2) Extract database schema to definition B
    $conn = $this->get_connection();
    $this->xml_content_b = format::extract_schema($conn->get_dbhost(), $conn->get_dbport(), $conn->get_dbname(), $conn->get_dbuser(), $conn->get_dbpass());
    
    $this->write_xml_definition_to_disk();

    // 3) Compare and expect zero differences between A and B
    $this->apply_options();
    format::build_upgrade($this->xml_file_a, $this->xml_file_b);
    
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
