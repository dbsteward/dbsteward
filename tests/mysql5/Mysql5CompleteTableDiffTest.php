<?php
/**
 * DBSteward unit test for mysql5 table diffing
 * 
 * Unlike Mysql5TableDiffSQLTest.php, this one tests the combination of diffing
 * constraints, keys, tables, and indexes just like the actual diff engine
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group mysql5
 * @group nodb
 */
class Mysql5CompleteTableDiffTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
    dbsteward::$ignore_oldnames = FALSE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
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

  public function testAutoIncrementPKFK() {
    $a = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="foo" primaryKey="fooID" owner="ROLE_OWNER">
    <column name="fooID" type="int auto_increment" null="false"/>
    <column name="barID" null="false" foreignSchema="public" foreignTable="bar" foreignColumn="barID"/>
  </table>
  <table name="bar" primaryKey="barID" owner="ROLE_OWNER">
    <column name="barID" type="int"/>
  </table>
</schema>
XML;

    $b = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="foo" primaryKey="barID" owner="ROLE_OWNER">
    <column name="barID" null="false" foreignSchema="public" foreignTable="bar" foreignColumn="barID"/>
  </table>
  <table name="bar" primaryKey="barID" owner="ROLE_OWNER">
    <column name="barID" type="int"/>
  </table>
</schema>
XML;
    
    // drop the PK with the auto-increment, then make the other column the PK
    // we also drop the barID index, because it was necessary for the foreign key in $a, but in $b it uses the primary key
    $expected = <<<SQL
ALTER TABLE `foo`
  MODIFY `fooID` int NOT NULL,
  DROP PRIMARY KEY;
ALTER TABLE `foo`
  DROP INDEX `barID`,
  ADD PRIMARY KEY (`barID`);
SQL;
    $this->diff($a, $b, $expected, "ALTER TABLE `foo`\n  DROP COLUMN `fooID`;");

    // add a new column, and make it PK with auto-increment
    // add barID index for the foreign key, since it's no longer using the PK index
    $expected = <<<SQL
ALTER TABLE `foo`
  DROP PRIMARY KEY;
ALTER TABLE `foo`
  ADD COLUMN `fooID` int NOT NULL FIRST;

ALTER TABLE `foo`
  ADD INDEX `barID` (`barID`) USING BTREE,
  ADD PRIMARY KEY (`fooID`),
  MODIFY `fooID` int NOT NULL AUTO_INCREMENT;
SQL;
    $this->diff($b, $a, $expected, "");
  }

  /**
   * Ensure AUTO_INCREMENT columns are correctly generated. At some point, they started getting ignored
   */
  public function testAutoIncrementIsAppliedInUpgrade() {
    $with_auto_increment = <<<XML
<schema name="ImdxTest" owner="ROLE_OWNER">
  <table name="GbiBatches" owner="ROLE_OWNER" primaryKey="GbiBatchID">
    <column name="GbiBatchID" type="int(11) AUTO_INCREMENT"/>
  </table>
</schema>
XML;
    $without_auto_increment = <<<XML
<schema name="ImdxTest" owner="ROLE_OWNER">
  <table name="GbiBatches" owner="ROLE_OWNER" primaryKey="GbiBatchID">
    <column name="GbiBatchID" type="int(11)"/>
  </table>
</schema>
XML;
    
    // no AI -> with AI: add AI flag via redefinition in stage 1
    $this->diff($without_auto_increment, $with_auto_increment, "ALTER TABLE `GbiBatches`\n  MODIFY COLUMN `GbiBatchID` int(11) AUTO_INCREMENT;", '');

    // with AI -> without AI: remove AI flag via redefinition in stage 1
    $this->diff($with_auto_increment, $without_auto_increment, "ALTER TABLE `GbiBatches`\n  MODIFY COLUMN `GbiBatchID` int(11);", '');
  }

  public function testRenameFKIndex() {
    $a = <<<XML
<schema name="test" owner="NOBODY">
<table name="table1" owner="ROLE_OWNER" description="" primaryKey="table1_id">
  <column name="table1_id" type="int(11) AUTO_INCREMENT" null="false"/>
  <column name="table2_id" foreignSchema="test" foreignTable="table2" foreignColumn="table2_id" foreignKeyName="table1_ibfk_1"/>
  <index name="table1_ibfk_1_idx" using="btree" unique="false">
    <indexDimension name="table2_id_1">table2_id</indexDimension>
  </index>
</table>
<table name="table2" owner="ROLE_OWNER" description="" primaryKey="table2_id">
  <column name="table2_id" type="int(11) AUTO_INCREMENT" null="false"/>
</table>
</schema>
XML;
    $b = <<<XML
<schema name="test" owner="NOBODY">
<table name="table1" owner="ROLE_OWNER" description="" primaryKey="table1_id">
  <column name="table1_id" type="int(11) AUTO_INCREMENT" null="false"/>
  <column name="table2_id" foreignSchema="test" foreignTable="table2" foreignColumn="table2_id" foreignKeyName="table1_ibfk_1"/>
  <index name="table2_id" using="btree" unique="false">
    <indexDimension name="table2_id_1">table2_id</indexDimension>
  </index>
</table>
<table name="table2" owner="ROLE_OWNER" description="" primaryKey="table2_id">
  <column name="table2_id" type="int(11) AUTO_INCREMENT" null="false"/>
</table>
</schema>
XML;

    $expected = <<<SQL
ALTER TABLE `table1`
  DROP INDEX `table1_ibfk_1_idx`,
  ADD INDEX `table2_id` (`table2_id`) USING BTREE;
SQL;

    $this->diff($a, $b, $expected, '');
  }

//   public function testLotsOfAlterTables() {
//     $a = <<<XML
// <schema name="public" owner="ROLE_OWNER">
//   <table name="foo" primaryKey="fooID" owner="ROLE_OWNER">
//     <column name="fooID" type="int" null="false"/>
//     <column name="barID" null="false" foreignSchema="public" foreignTable="bar" foreignColumn="barID"/>
//   </table>
//   <table name="bar" primaryKey="barID" owner="ROLE_OWNER">
//     <column name="barID" type="int"/>
//   </table>
// </schema>
// XML;

//     $b = <<<XML
// <schema name="public" owner="ROLE_OWNER">
//   <table name="foo" primaryKey="barID" owner="ROLE_OWNER">
//     <column name="fooID" type="int" null="false"/>
//     <column name="barID" type="int" null="false"/>
//     <column name="catID" foreignSchema="public" foreignTable="bar" foreignColumn="barID"/>
//   </table>
//   <table name="bar" primaryKey="barID" owner="ROLE_OWNER">
//     <column name="barID" type="int"/>
//   </table>
// </schema>
// XML;

//     // maximum number of alter tables in stage 1 is caused by:
//     //   one constraint dropped
//     //   one primary key moved
//     //   one column changed
//     //   one constraint added
//     $expected = <<<SQL
// ALTER TABLE `foo`
//   DROP FOREIGN KEY `foo_barID_fkey`,
//   DROP PRIMARY KEY,
//   ADD COLUMN `catID` int AFTER `barID`,
//   ADD PRIMARY KEY (`barID`),
//   ADD CONSTRAINT `foo_catID_fkey` FOREIGN KEY `foo_catID_fkey` (`catID`) REFERENCES `bar` (`barID`);
// SQL;
//     $this->diff($a, $b, $expected, '');
//   }

  private function diff($old, $new, $expected1, $expected3, $message='') {
    dbsteward::$old_database = new SimpleXMLElement($this->db_doc_xml . $old . '</dbsteward>');
    dbsteward::$new_database = new SimpleXMLElement($this->db_doc_xml . $new . '</dbsteward>');

    $ofs1 = new mock_output_file_segmenter();
    $ofs3 = new mock_output_file_segmenter();

    // same structure as mysql5_diff::update_structure
    foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);

      mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'constraint', TRUE);
      mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'primaryKey', TRUE);
      mysql5_diff_tables::drop_tables($ofs3, $old_schema, $new_schema);
      mysql5_diff_tables::diff_tables($ofs1, $ofs3, $old_schema, $new_schema);
      // mysql5_diff_indexes::diff_indexes($ofs1, $old_schema, $new_schema);
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
