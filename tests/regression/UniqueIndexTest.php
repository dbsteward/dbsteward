<?php
/**
 * DBSteward unit test for testing that indexes are caught if they are not unique
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Adam Jette <jettea46@yahoo.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

class UniqueIndexTest extends PHPUnit_Framework_TestCase {

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
    <table name="table_shable" owner="ROLE_OWNER" primaryKey="table_shable_id">
      <column name="table_shable_id" type="int"/>
      <column name="table_shable_value" type="char(10)"/>
      <index name="table_shable_idx" using="btree">
        <indexDimension name="position_1">table_shable_value</indexDimension>
      </index>
      <index name="table_shable_idx" using="gin">
        <indexDimension name="position_1">table_shable_value</indexDimension>
      </index>
    </table>
  </schema>
</dbsteward>
XML_NEW;

  private $two_xml = <<<XML_TWO
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
    <table name="table_shable" owner="ROLE_OWNER" primaryKey="table_shable_id">
      <column name="table_shable_id" type="int"/>
      <column name="table_shable_value" type="char(10)"/>
      <index name="table_shable_lable_idx" using="btree">
        <indexDimension name="position_1">table_shable_lable_value</indexDimension>
      </index>
    </table>
    <table name="table_shable_lable" owner="ROLE_OWNER" primaryKey="table_shable_id">
      <column name="table_shable_lable_id" type="int"/>
      <column name="table_shable_lable_value" type="char(10)"/>
      <index name="table_shable_lable_idx" using="btree">
        <indexDimension name="position_1">table_shable_lable_value</indexDimension>
      </index>
    </table>
  </schema>
</dbsteward>
XML_TWO;

  public function testTwoTablesWithTwoIndexes() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = TRUE;
    dbsteward::$single_stage_upgrade = TRUE;

    $doc_empty = simplexml_load_string($this->xml_empty);
    $doc_empty = xml_parser::composite_doc(FALSE, $doc_empty);
    dbsteward::$old_database = $doc_empty;
    $doc = simplexml_load_string($this->two_xml);
    $doc = xml_parser::composite_doc(FALSE, $doc);
    dbsteward::$new_database = $doc;
    
    $table_dependency = xml_parser::table_dependency_order($doc);

    $schema = $doc->schema;
    $table = $schema->table;
    
    // make sure the type is named with quoting as part of a definition build
    $mofs = new mock_output_file_segmenter();
    pgsql8::build_schema($doc, $mofs, $table_dependency);
  }

  public function testExceptionIsThrownWithDupes() {
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

    $schema = $doc->schema;
    $table = $schema->table;
    
    // make sure the type is named with quoting as part of a definition build
    $mofs = new mock_output_file_segmenter();
    try {
      pgsql8::build_schema($doc, $mofs, $table_dependency);
    }
    catch (Exception $e) {
      $this->assertContains('duplicate index name', strtolower($e->getMessage()));
      return;
    }
    $this->fail("build_schema did not detect duplicate index names");
  }
}
