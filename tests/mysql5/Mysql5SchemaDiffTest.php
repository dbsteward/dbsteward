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
 * @group nodb
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
    
    $expected3 = <<<SQL
-- `s2_t2` triggers, indexes, constraints will be implicitly dropped when the table is dropped
-- `s2_t2` will be dropped later according to table dependency order
-- DROP TABLE `s2_t2` omitted: new table `s1_t2` indicates it is her replacement
SQL;
    
    $this->diff($old, $new, $expected1, $expected3, 'Moving a table between schemas while using schema prefixing should result in a rename');
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
    
    $expected3 = <<<SQL
-- `s2_t2` triggers, indexes, constraints will be implicitly dropped when the table is dropped
-- `s2_t2` will be dropped later according to table dependency order
DROP TABLE `s2_t2`;
SQL;

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
    
    $this->diff($old, $new, $expected1, '-- DROP TABLE `s1_t2` omitted: new table `s2_t2` indicates it is her replacement', 'Splitting a schema and using oldSchemaName while in schema prefixing mode should be a rename');
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

  public function testDropSchemaWithObjects() {
    mysql5::$use_schema_name_prefix = TRUE;

    $old = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="table1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
<schema name="s2" owner="NOBODY">
  <table name="table2" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
  <type name="yesno" type="enum">
    <enum name="yes"/>
    <enum name="no"/>
  </type>
  <function name="test_concat" returns="text" owner="ROLE_OWNER" cachePolicy="VOLATILE" description="a test function that concats strings">
    <functionParameter name="param1" type="text" />
    <functionParameter name="param2" type="text" />
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN CONCAT(param1, param2);
    </functionDefinition>
  </function>
  <sequence name="the_sequence" owner="NOBODY" max="10" cycle="true" inc="3" start="2"/>
  <trigger name="trigger" sqlFormat="mysql5" table="table2" when="BEFORE" event="insert" function="EXECUTE xyz"/>
  <view name="view" owner="NOBODY" description="Description goes here">
    <viewQuery sqlFormat="mysql5">SELECT * FROM table2</viewQuery>
  </view>
</schema>
XML;

    $new = <<<XML
<schema name="s1" owner="NOBODY">
  <table name="table1" owner="NOBODY" primaryKey="col1">
    <column name="col1" type="int" />
  </table>
</schema>
XML;
    
    $expected3 = <<<SQL
DROP VIEW IF EXISTS `s2_view`;
-- dropping enum type yesno. references to type yesno will be replaced with the type 'text'
DROP FUNCTION IF EXISTS `s2_test_concat`;
DELETE FROM `__sequences` WHERE `name` IN ('the_sequence');
-- `s2_table2` triggers, indexes, constraints will be implicitly dropped when the table is dropped
-- `s2_table2` will be dropped later according to table dependency order
DROP TABLE `s2_table2`;
SQL;

    $this->diff($old, $new, '', $expected3);
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

    mysql5_diff::$old_table_dependency = xml_parser::table_dependency_order(dbsteward::$old_database);
    mysql5_diff::$new_table_dependency = xml_parser::table_dependency_order(dbsteward::$new_database);

    mysql5_diff::update_structure($ofs1, $ofs3);

    $actual1 = trim($ofs1->_get_output());
    $actual3 = trim($ofs3->_get_output());

    $this->assertEquals($expected1, $actual1, "during stage 1: $message");
    $this->assertEquals($expected3, $actual3, "during stage 3: $message");
  }
}
