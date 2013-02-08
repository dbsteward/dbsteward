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

require_once 'PHPUnit/Framework/TestCase.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

class Mysql5CompleteTableDiffTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
    dbsteward::$ignore_oldnames = FALSE;
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

  public function testAutoIncrementPK() {
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
    $expected = <<<SQL
ALTER TABLE `foo`
  MODIFY `fooID` int NOT NULL,
  DROP PRIMARY KEY;
ALTER TABLE `foo`
  ADD PRIMARY KEY (`barID`);
SQL;
    $this->diff($a, $b, $expected, "ALTER TABLE `foo`\n  DROP COLUMN `fooID`;");

    // add a new column, and make it PK with auto-increment
    $expected = <<<SQL
ALTER TABLE `foo`
  DROP PRIMARY KEY;
ALTER TABLE `foo`
  ADD COLUMN `fooID` int NOT NULL FIRST;

ALTER TABLE `foo`
  ADD PRIMARY KEY (`fooID`),
  MODIFY `fooID` int NOT NULL AUTO_INCREMENT;
SQL;
    $this->diff($b, $a, $expected, "");
  }


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