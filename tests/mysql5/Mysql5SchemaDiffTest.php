<?php
/**
 * DBSteward unit test for testing schema differences with and without schema name prefixing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

/**
 * @group mysql5
 */
class Mysql5SchemaDiffTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_all_names = TRUE;
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
    dbsteward::$ignore_oldnames = FALSE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
  }

  public function testMergeSchemasWithOldSchemaNameWithSchemaPrefix() {
    mysql5::$use_schema_name_prefix = TRUE;

    $old = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
<schema name="s2" owner="NOBODY">
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
  <table name="t2" owner="NOBODY" primaryKey="col1" oldSchemaName="s2">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    
    $expected1 = <<<SQL
-- table rename from oldTableName specification
ALTER TABLE `s2_t2` RENAME TO `s1_t2`;
SQL;
    
    $this->diff($old, $new, $expected1, '', 'Moving a table between schemas while using schema prefixing should result in a rename');
  }

  public function testMergeSchemasWithOldSchemaNameWithoutSchemaPrefix() {
    mysql5::$use_schema_name_prefix = FALSE;

    $old = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
<schema name="s2" owner="NOBODY">
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="s1_s2" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
  <table name="t2" owner="NOBODY" primaryKey="col1" oldSchemaName="s2">
    <column name="col1" type="int" />
  </table>
</schema>
XML;

    $this->should_throw($old, $new, 'you cannot use more than one schema in mysql5 without schema name prefixing');
  }

  public function testMergeSchemasWithoutOldSchemaNameWithSchemaPrefix() {
    mysql5::$use_schema_name_prefix = TRUE;

    $old = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
<schema name="s2" owner="NOBODY">
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
  <table name="t2" owner="NOBODY" primaryKey="col1"> <!-- No oldSchemaName here to indicate move -->
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    
    $expected1 = <<<SQL
CREATE TABLE `s1_t2` (
  `col1` int(11)
);
ALTER TABLE `s1_t2`
  ADD PRIMARY KEY (`col1`);
SQL;
    
    $expected3 = 'DROP TABLE `s2_t2`;';

    $this->diff($old, $new, $expected1, $expected3, 'Moving a table without oldSchemaName with schema prefixes should result in a drop+create');
  }

  public function testMergeSchemasWithoutOldSchemaNameWithoutSchemaPrefix() {
    mysql5::$use_schema_name_prefix = FALSE;

    $old = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
<schema name="s2" owner="NOBODY">
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
  <table name="t2" owner="NOBODY" primaryKey="col1"> <!-- No oldSchemaName here to indicate move -->
    <column name="col1" type="int" />
  </table>
</schema>
XML;

    $this->should_throw($old, $new, 'you cannot use more than one schema in mysql5 without schema name prefixing');
  }

  public function testSplitSchemasWithOldSchemaNameWithSchemaPrefix() {
    mysql5::$use_schema_name_prefix = TRUE;

    $old = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
<schema name="s2" owner="NOBODY">
  <table name="t2" owner="NOBODY" primaryKey="col1" oldSchemaName="s1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;

    $expected1 = <<<SQL
-- table rename from oldTableName specification
ALTER TABLE `s1_t2` RENAME TO `s2_t2`;
SQL;
    
    $this->diff($old, $new, $expected1, '', 'Splitting a schema and using oldSchemaName while in schema prefixing mode should be a rename');
  }

  public function testSplitSchemasWithOldSchemaNameWithoutSchemaPrefix() {
    mysql5::$use_schema_name_prefix = FALSE;

    $old = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
<schema name="s2" owner="NOBODY">
  <table name="t2" owner="NOBODY" primaryKey="col1" oldSchemaName="s1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    
    $this->should_throw($old, $new, 'you cannot use more than one schema in mysql5 without schema name prefixing');
  }

  public function testSplitSchemasWithoutOldSchemaNameWithSchemaPrefix() {
    mysql5::$use_schema_name_prefix = TRUE;

    $old = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
<schema name="s2" owner="NOBODY">
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;

    $expected1 = <<<SQL
CREATE TABLE `s2_t2` (
  `col1` int(11)
);
ALTER TABLE `s2_t2`
  ADD PRIMARY KEY (`col1`);
SQL;

    $expected3 = 'DROP TABLE `s1_t2`;';
    
    $this->diff($old, $new, $expected1, $expected3, 'Splitting a schema and not using oldSchemaName while in schema prefixing mode should be a recreate');
  }

  public function testSplitSchemasWithoutOldSchemaNameWithoutSchemaPrefix() {
    mysql5::$use_schema_name_prefix = FALSE;

    $old = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="t1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
<schema name="s2" owner="NOBODY">
  <table name="t2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;

    $this->should_throw($old, $new, 'you cannot use more than one schema in mysql5 without schema name prefixing');
  }

  private $db_doc_xml = <<<XML
<dbsteward>
  <database>
    <role>
      <application/>
      <owner>the_owner</owner>
      <replication/>
      <readonly/>
      <customRole>SOMEBODY</customRole>
    </role>
  </database>
XML;

  private function should_throw($old, $new, $message) {
    try {
      $this->diff($old, $new, 'NOPE', 'NOPE');
    }
    catch (exception $ex) {
      $this->assertContains(strtolower($message), strtolower($ex->getMessage()), "Expected exception: $message");
      return;
    }
    $this->fail("Expected exception: $message");
  }

  private function diff($old, $new, $expected1, $expected3, $message='') {
    dbsteward::$old_database = xml_parser::composite_doc(NULL, simplexml_load_string($this->db_doc_xml . $old . '</dbsteward>'));
    dbsteward::$new_database = xml_parser::composite_doc(NULL, simplexml_load_string($this->db_doc_xml . $new . '</dbsteward>'));

    $ofs1 = new mock_output_file_segmenter();
    $ofs3 = new mock_output_file_segmenter();

    // same structure as mysql5_diff::update_structure
    foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);

      mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'constraint', TRUE);
      mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'primaryKey', TRUE);
      mysql5_diff_tables::drop_tables($ofs3, $old_schema, $new_schema);
      mysql5_diff_tables::diff_tables($ofs1, $ofs3, $old_schema, $new_schema);
      mysql5_diff_indexes::diff_indexes($ofs1, $old_schema, $new_schema);
      mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'primaryKey', FALSE);
    }

    foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
      mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'constraint', FALSE);
    }

    $actual1 = trim($ofs1->_get_output());
    $actual3 = trim($ofs3->_get_output());

    $this->assertEquals($expected1, $actual1, "during stage 1: $message");
    $this->assertEquals($expected3, $actual3, "during stage 3: $message");
  }
}