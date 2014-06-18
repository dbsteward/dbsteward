<?php
/**
 * DBSteward unit test for mysql5 table partitions differencing
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
class Mysql5TablePartitionsDiffSQLTest extends PHPUnit_Framework_TestCase {


  private $db_doc_xml = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
      <customRole>SOMEBODY</customRole>
    </role>
  </database>
XML;
  
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

  public function testAddRemovePartitioning() {

    $without = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <table name="partition_test" primaryKey="id" owner="ROLE_OWNER">
    <column name="id" type="int" />
  </table>
</schema>
XML;
    $with = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <table name="partition_test" primaryKey="id" owner="ROLE_OWNER">
    <tablePartition type="HASH">
      <tablePartitionOption name="column" value="id" />
      <tablePartitionOption name="number" value="4" />
    </tablePartition>

    <column name="id" type="int" />
  </table>
</schema>
XML;
    
    $this->diff($without, $with,
      "ALTER TABLE `partition_test`\n  PARTITION BY HASH (`id`) PARTITIONS 4;", '');

    $this->diff($with, $without,
      "ALTER TABLE `partition_test`\n  REMOVE PARTITIONING;", '');
  }

  public function changeHashKeyNumber() {
    $this->markTestSkipped('Does not pass yet');
    $hash2 = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <table name="partition_test" primaryKey="id" owner="ROLE_OWNER">
    <tablePartition type="HASH">
      <tablePartitionOption name="column" value="id" />
      <tablePartitionOption name="number" value="2" />
    </tablePartition>

    <column name="id" type="int" />
  </table>
</schema>
XML;
    $hash4 = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <table name="partition_test" primaryKey="id" owner="ROLE_OWNER">
    <tablePartition type="HASH">
      <tablePartitionOption name="column" value="id" />
      <tablePartitionOption name="number" value="4" />
    </tablePartition>

    <column name="id" type="int" />
  </table>
</schema>
XML;
    
    $key4 = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <table name="partition_test" primaryKey="id" owner="ROLE_OWNER">
    <tablePartition type="KEY">
      <tablePartitionOption name="column" value="id" />
      <tablePartitionOption name="number" value="4" />
    </tablePartition>

    <column name="id" type="int" />
  </table>
</schema>
XML;

    $this->diff($hash2, $key4,
      "ALTER TABLE `test`\n  PARTITION BY KEY (`id`) PARTITIONS 4;", '');

    $this->diff($key4, $hash2,
      "ALTER TABLE `test`\n  PARTITION BY HASH (`id`) PARTITIONS 2;", '');
  }


  private function diff($xml_a, $xml_b, $expected1, $expected3, $message='') {
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

}
