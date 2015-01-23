<?php
/**
 * DBSteward unit test for mysql5 column default differencing
 * 
 * Unlike Mysql5TableDiffSQLTest.php, this one tests the combination of diffing
 * constraints, keys, tables, and indexes just like the actual diff engine
 *
 * @package DBSteward
 * @license BSD 2 Clause <http://opensource.org/licenses/BSD-2-Clause>
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group mysql5
 * @group nodb
 */
class Mysql5ColumnDefaultTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
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

  public function testDropSetDefault() {
    $a = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="widgets" primaryKey="widgetID" owner="ROLE_OWNER">
    <column name="widgetID" type="int auto_increment" null="false"/>
    <column name="widgetName" type="varchar(100)" null="false" />
    <column name="widgetCreator" type="varchar(100)" null="false" />
    <column name="order" type="bigint(11)" null="false" default="0"/>
  </table>
</schema>
XML;

    $b = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="widgets" primaryKey="widgetID" owner="ROLE_OWNER">
    <column name="widgetID" type="int auto_increment" null="false"/>
    <column name="widgetName" type="varchar(100)" null="false" />
    <column name="widgetCreator" type="varchar(100)" null="false" default="'tstark'" />
    <column name="order" type="bigint(11)" null="false" />
  </table>
</schema>
XML;
    
    // column should have it's default dropped
    $expected1 = <<<SQL
ALTER TABLE `widgets`
  ALTER COLUMN `widgetCreator` SET DEFAULT 'tstark',
  ALTER COLUMN `order` DROP DEFAULT;
SQL;
    $expected3 = '';
    $this->diff($a, $b, $expected1, $expected3);

    // inverse: column should have default set
    // note that in mysql5 this is done in stage 1 to take advantage of 
    // auto not-nulls for columns when setting a default to improve migration performance
    $expected1 = <<<SQL
ALTER TABLE `widgets`
  ALTER COLUMN `order` SET DEFAULT 0,
  ALTER COLUMN `widgetCreator` DROP DEFAULT;
SQL;
    $expected3 = '';
    
    $this->diff($b, $a, $expected1, $expected3);
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
