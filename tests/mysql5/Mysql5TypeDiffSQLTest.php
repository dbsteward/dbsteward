<?php
/**
 * DBSteward unit test for mysql5 type diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

/**
 * @group mysql5
 */
class Mysql5TypeDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
  }

  public function testAdd() {
    $old = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" owner="ROLE_OWNER" primaryKey="id">
    <column name="id" type="int"/>
    <column name="enum_col" type="text"/>
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" owner="ROLE_OWNER" primaryKey="id">
    <column name="id" type="int"/>
    <column name="enum_col" type="enum_type"/>
  </table>
  <type name="enum_type" type="enum">
    <enum name="a"/>
    <enum name="b"/>
    <enum name="c"/>
  </type>
</schema>
XML;

    $this->common($old, $new, "-- found enum type enum_type. references to type enum_type will be replaced by ENUM('a','b','c')
ALTER TABLE `table`
  MODIFY COLUMN `enum_col` ENUM('a','b','c');");
  }

  public function testDrop() {
    $old = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" owner="ROLE_OWNER" primaryKey="id">
    <column name="id" type="int"/>
    <column name="enum_col" type="enum_type"/>
  </table>
  <type name="enum_type" type="enum">
    <enum name="a"/>
    <enum name="b"/>
    <enum name="c"/>
  </type>
</schema>
XML;
    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" owner="ROLE_OWNER" primaryKey="id">
    <column name="id" type="int"/>
    <column name="enum_col" type="text"/>
  </table>
</schema>
XML;
    $this->common($old, $new, "-- dropping enum type enum_type. references to type enum_type will be replaced with the type 'text'
ALTER TABLE `table`
  MODIFY COLUMN `enum_col` text;");
  }

  public function testChange() {
    $old = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" owner="ROLE_OWNER" primaryKey="id">
    <column name="id" type="int"/>
    <column name="enum_col" type="enum_type"/>
  </table>
  <type name="enum_type" type="enum">
    <enum name="a"/>
    <enum name="b"/>
    <enum name="c"/>
  </type>
</schema>
XML;
    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" owner="ROLE_OWNER" primaryKey="id">
    <column name="id" type="int"/>
    <column name="enum_col" type="new_name"/>
  </table>
  <type name="new_name" type="enum">
    <enum name="a"/>
    <enum name="b"/>
    <enum name="c"/>
  </type>
</schema>
XML;
    
    // just change the name. there shouldn't be any ddl generated, except a note that it was "dropped" and "recreated"
    $this->common($old, $new, "-- dropping enum type enum_type. references to type enum_type will be replaced with the type 'text'
-- found enum type new_name. references to type new_name will be replaced by ENUM('a','b','c')");

    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table" owner="ROLE_OWNER" primaryKey="id">
    <column name="id" type="int"/>
    <column name="enum_col" type="enum_type"/>
  </table>
  <type name="enum_type" type="enum">
    <enum name="x"/>
    <enum name="y"/>
    <enum name="z"/>
  </type>
</schema>
XML;

    // change values in the enum. this should cause column modifications
    $this->common($old, $new, "ALTER TABLE `table`
  MODIFY COLUMN `enum_col` ENUM('x','y','z');");
  }

  private function common($xml_a, $xml_b, $expected, $message = NULL) {
    $schema_a = new SimpleXMLElement($xml_a);
    $schema_b = new SimpleXMLElement($xml_b);

    $ofs = new mock_output_file_segmenter();

    mysql5_diff_types::apply_changes($ofs, $schema_a, $schema_b);
    mysql5_diff_tables::diff_tables($ofs, $ofs, $schema_a, $schema_b);

    $actual = trim($ofs->_get_output());

    $this->assertEquals($expected, $actual, $message);
  }

}
?>
