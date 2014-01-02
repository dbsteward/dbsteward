<?php
/**
 * DBSteward unit test for type definition quoting regression
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

class TypeQuotingTest extends PHPUnit_Framework_TestCase {

  private $xml_empty = <<<XML_EMPTY
<dbsteward>
  <database>
    <role>
      <application>app_runtime</application>
      <owner>app_owner</owner>
      <replication/>
      <readonly>app_audit_readonly</readonly>
    </role>
  </database>
  <schema name="schema1" owner="ROLE_OWNER">
  </schema>
</dbsteward>
XML_EMPTY;
  
  private $xml = <<<XML_NEW
<dbsteward>
  <database>
    <role>
      <application>app_runtime</application>
      <owner>app_owner</owner>
      <replication/>
      <readonly>app_audit_readonly</readonly>
    </role>
  </database>
  <schema name="schema1" owner="ROLE_OWNER">
    <type name="enumCamelCaseType" type="enum">
      <enum name="Read"/>
      <enum name="Write"/>
      <enum name="Delete"/>
    </type>
    <table name="table_shable" owner="ROLE_OWNER" primaryKey="table_shable_id">
      <column name="table_shable_id" type="int"/>
      <column name="table_shable_value" type="char(10)"/>
      <column name="table_shable_mode" type="enumCamelCaseType"/>
      <rows columns="table_shable_id, table_shable_value, table_shable_mode">
        <row>
          <col>1</col>
          <col>shim sham</col>
          <col>BETA</col>
        </row>
        <row>
          <col>2</col>
          <col>flim flam</col>
          <col>GAMMA</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML_NEW;

  public function setUp() {
  }

  /**
   * @group pgsql8
   */
  public function testTableColumnTypeQuotingPgsql8() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = TRUE;
    dbsteward::$single_stage_upgrade = TRUE;

    $doc_empty = simplexml_load_string($this->xml_empty);
    $doc_empty = xml_parser::composite_doc(FALSE, $doc_empty);
    dbsteward::$old_database = $doc_empty;
    $doc = simplexml_load_string($this->xml);
    $doc = xml_parser::composite_doc(FALSE, $doc);
    dbsteward::$new_database = $doc;
    
    $table_dependency = xml_parser::table_dependency_order($doc);

    //var_dump(xml_parser::format_xml($doc_empty->saveXML()));
    //var_dump(xml_parser::format_xml($doc->saveXML()));

    $schema = $doc->schema;
    $table = $schema->table;
    
    // make sure the type is named with quoting as part of a definition build
    $expected = "CREATE TYPE \"schema1\".\"enumCamelCaseType\" AS ENUM ('Read','Write','Delete');";
    $mofs = new mock_output_file_segmenter();
    pgsql8::build_schema($doc, $mofs, $table_dependency);
    $actual = trim($mofs->_get_output());
    $this->assertContains($expected, $actual);
    // make sure the type is referred to with quoting in a table creation as part of a definition build
    $expected_column = '"table_shable_mode" "enumCamelCaseType"';
    $this->assertContains($expected_column, $actual);
    
    // make sure the type is referred to with quoting when generating table create statements
    $expected = '"table_shable_mode" "enumCamelCaseType"';
    $sql = pgsql8_table::get_creation_sql($schema, $table);
    $this->assertContains($expected, $sql);
    
    // make sure create table quotes the type name
    $expected = '"table_shable_mode" "enumCamelCaseType"';
    $mofs = new mock_output_file_segmenter();
    var_dump(dbx::get_tables($schema));
    pgsql8_diff_tables::diff_tables($mofs, $mofs, NULL, $schema);
    $actual = trim($mofs->_get_output());
    $this->assertContains($expected, $actual);
    
    // make sure insert statements are made that match the XML definition
    $expected = "INSERT INTO \"schema1\".\"table_shable\" (\"table_shable_id\", \"table_shable_value\", \"table_shable_mode\") VALUES (1, E'shim sham', BETA);";
    $actual = trim(pgsql8_diff_tables::get_data_sql(NULL, NULL, $schema, $table, FALSE));
    $this->assertContains($expected, $actual);
  }
}
