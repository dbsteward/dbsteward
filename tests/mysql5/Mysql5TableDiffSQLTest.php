<?php
/**
 * DBSteward unit test for mysql5 table diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

require_once __DIR__ . '/../mock_output_file_segmenter.php';

class Mysql5TableDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  private $db_doc_xml = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
      <customRole>SOMEBODY</customRole>
    </role>
  </database>
XML;

  public function testDropTables() {
    $old = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text"/>
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
</schema>
XML;

    // don't drop anything
    $this->common_drop($old, $old, '');
    $this->common_drop($new, $new, '');
    $this->common_drop($new, $old, '');

    // drop a single table
    $this->common_drop($old, $new, 'DROP TABLE `table`;');

    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="newtable" primaryKey="id" owner="ROLE_OWNER" oldName="table">
    <column name="id" type="int"/>
    <column name="col" type="text"/>
  </table>
</schema>
XML;
    

    // don't drop a renamed table
    $this->common_drop($old, $new, '-- DROP TABLE `table` omitted: new table newtable indicates it is the replacement for `table`');

    // going backwards, it should look like we dropped newtable and added table
    $this->common_drop($new, $old, 'DROP TABLE `newtable`;');

    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="newtable" primaryKey="id" owner="ROLE_OWNER" oldName="newtable">
    <column name="id" type="int"/>
    <column name="col" type="text"/>
  </table>
</schema>
XML;
    
    // we shouldn't rename itself
    $this->common_drop($new, $new, '');
  }

  public function testCreateTables() {
    $old = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="old" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text"/>
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text"/>
  </table>
</schema>
XML;
    
    $expected1 = "CREATE TABLE `table` (\n  `id` int,\n  `col` text\n);";

    $this->common_diff($old, $new, $expected1, '');
  }

  public function testColumns() {
    $old = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text"/>
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text"/>
    <column name="newcol" type="int"/>
  </table>
</schema>
XML;

    // add column
    $this->common_diff($old, $new, "ALTER TABLE `table`\n\tADD COLUMN `newcol` int;", '');

    // drop column
    $this->common_diff($new, $old, '', "ALTER TABLE `table`\n\tDROP COLUMN `newcol`;");

    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="diff" type="text" oldName="col"/>
  </table>
</schema>
XML;

    // rename column
    $this->common_diff($old, $new,
      "-- column rename from oldName specification\nALTER TABLE `table` CHANGE COLUMN `col` `diff` text;",
      '-- `table` DROP COLUMN `col` omitted: new column diff indicates it is the replacement for `col`');

    // drop/add column
    $this->common_diff($new, $old, "ALTER TABLE `table`\n\tADD COLUMN `col` text;", "ALTER TABLE `table`\n\tDROP COLUMN `diff`;");
  }

  public function testNullAndDefaultColumnChanges() {
    $old = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text" default="'xyz'"/>
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text"/>
  </table>
</schema>
XML;

    // drop defaults
    $this->common_diff($old, $new, "ALTER TABLE `table`\n\tALTER COLUMN `col` DROP DEFAULT;", '');

    // add defaults
    $this->common_diff($new, $old, "ALTER TABLE `table`\n\tALTER COLUMN `col` SET DEFAULT 'xyz';", '');


    $nullable = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text" null="true"/>
  </table>
</schema>
XML;
    $notnullable = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text" null="false"/>
  </table>
</schema>
XML;
    
    // NULL -> NOT NULL
    $this->common_diff($nullable, $notnullable, '', "ALTER TABLE `table`\n\tMODIFY COLUMN `col` text NOT NULL;");

    // NOT NULL -> NULL
    $this->common_diff($notnullable, $nullable, "ALTER TABLE `table`\n\tMODIFY COLUMN `col` text;", '');

    $nullable_with_default = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text" null="true" default="'xyz'"/>
  </table>
</schema>
XML;
    $notnullable_with_default = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text" null="false" default="'xyz'"/>
  </table>
</schema>
XML;
    
    // NULL -> NOT NULL
    $this->common_diff($nullable_with_default, $notnullable_with_default,
     "UPDATE `table` SET `col` = 'xyz' WHERE `col` IS NULL; -- has_default_now: make modified column that is null the default value before NOT NULL hits",
     "ALTER TABLE `table`\n\tMODIFY COLUMN `col` text NOT NULL DEFAULT 'xyz';");

    // NOT NULL -> NULL
    $this->common_diff($notnullable_with_default, $nullable_with_default, "ALTER TABLE `table`\n\tMODIFY COLUMN `col` text DEFAULT 'xyz';", '');

    $notnullable_without_default = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="text" null="false"/>
  </table>
</schema>
XML;

    $this->common_diff($nullable_with_default, $notnullable_without_default,
      "ALTER TABLE `table`\n\tALTER COLUMN `col` DROP DEFAULT;",
      "ALTER TABLE `table`\n\tMODIFY COLUMN `col` text NOT NULL;");

    // extraneous ALTER COLUMN SET DEFAULT won't actually hurt anything
    $this->common_diff($notnullable_without_default, $nullable_with_default,
      "ALTER TABLE `table`\n\tALTER COLUMN `col` SET DEFAULT 'xyz' ,\n\tMODIFY COLUMN `col` text DEFAULT 'xyz';",
      "");
  }

  public function testEnums() {
    $old = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <type name="enum" type="enum">
    <enum name="x"/>
    <enum name="y"/>
    <enum name="z"/>
  </type>
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="enum"/>
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <type name="enum" type="enum">
    <enum name="x"/>
    <enum name="y"/>
    <enum name="z"/>
  </type>
  <table name="table" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int"/>
    <column name="col" type="enum"/>
  </table>
</schema>
XML;
  }

//   public function testSerials() {
//     $none = <<<XML
// <schema name="public" owner="ROLE_OWNER">
  
// </schema>
// XML;
//     $one = <<<XML
// <schema name="public" owner="ROLE_OWNER">
//   <table name="table" primaryKey="id" owner="ROLE_OWNER">
//     <column name="id" type="serial"/>
//   </table>
// </schema>
// XML;

//     // add table with serial
//     $this->common_diff($none, $one, '', '');

//     // drop table with serial
//     $this->common_diff($one, $none, '', '');

//     $one_renamed = <<<XML
// <schema name="public" owner="ROLE_OWNER">
//   <table name="table" primaryKey="new_id" owner="ROLE_OWNER">
//     <column name="new_id" type="serial" oldName="id"/>
//   </table>
// </schema>
// XML;

//     // rename serial column
//     $this->common_diff($one, $one_renamed, '', '');
//   }

  private function common_diff($xml_a, $xml_b, $expected1, $expected3, $message='') {
    dbsteward::$old_database = new SimpleXMLElement($this->db_doc_xml . $xml_a . '</dbsteward>');
    dbsteward::$new_database = new SimpleXMLElement($this->db_doc_xml . $xml_b . '</dbsteward>');

    $ofs1 = new mock_output_file_segmenter();
    $ofs3 = new mock_output_file_segmenter();

    mysql5_diff_tables::diff_tables($ofs1, $ofs3, dbsteward::$old_database->schema, dbsteward::$new_database->schema);

    $actual1 = trim($ofs1->_get_output());
    $actual3 = trim($ofs3->_get_output());

    $this->assertEquals($expected1, $actual1, "during stage 1: $message");
    $this->assertEquals($expected3, $actual3, "during stage 3: $message");
  }

  private function common_drop($xml_a, $xml_b, $expected, $message=NULL) {
    dbsteward::$old_database = new SimpleXMLElement($this->db_doc_xml . $xml_a . '</dbsteward>');
    dbsteward::$new_database = new SimpleXMLElement($this->db_doc_xml . $xml_b . '</dbsteward>');

    $ofs = new mock_output_file_segmenter();

    mysql5_diff_tables::drop_tables($ofs, dbsteward::$old_database->schema, dbsteward::$new_database->schema);

    $actual = trim($ofs->_get_output());

    $this->assertEquals($expected, $actual, $message);
  }
}