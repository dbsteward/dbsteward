<?php
/**
 * DBSteward unit test for mysql5 table ddl generation
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
class Mysql5TableSQLTest extends PHPUnit_Framework_TestCase {

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

  public function testSimple() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY" description="test desc'ription">
    <column name="id" type="int"/>
    <column name="foo" type="int"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
CREATE TABLE `test` (
  `id` int,
  `foo` int
)
COMMENT 'test desc\'ription';
SQL;
    
    $this->assertEquals($expected, mysql5_table::get_creation_sql($schema, $schema->table));

    $this->assertEquals("DROP TABLE `test`;", mysql5_table::get_drop_sql($schema, $schema->table));
  }

  public function testSerials() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY" description="test desc'ription">
    <column name="id" type="serial"/>
    <column name="other" type="bigserial"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
CREATE TABLE `test` (
  `id` int NOT NULL,
  `other` bigint NOT NULL
)
COMMENT 'test desc\'ription';
SQL;

    $this->assertEquals($expected, mysql5_table::get_creation_sql($schema, $schema->table));
  }

  public function testInheritance() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY" inherits="other">
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);
    
    $this->assertEquals("-- Skipping table 'test' because MySQL does not support table inheritance", mysql5_table::get_creation_sql($schema, $schema->table));
  }

  public function testAutoIncrement() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="id" type="int auto_increment"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $this->assertEquals("CREATE TABLE `test` (\n  `id` int\n);", mysql5_table::get_creation_sql($schema, $schema->table));

    // ensure that foreign auto_increment flags aren't transferred to the table definition
    $xml = <<<XML
<dbsteward>
<database/>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="id" type="int auto_increment"/>
    <column name="fk" foreignSchema="public" foreignTable="other" foreignColumn="id"/>
  </table>
  <table name="other" primaryKey="id" onwer="NOBODY">
    <column name="id" type="int auto_increment"/>
  </table>
</schema>
</dbsteward>
XML;
    $dbs = new SimpleXMLElement($xml);
    dbsteward::$new_database = $dbs;
    $this->assertEquals("CREATE TABLE `test` (\n  `id` int,\n  `fk` int\n);", mysql5_table::get_creation_sql($dbs->schema, $dbs->schema->table[0]));
  }

  public function testGetHashPartitionSql() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="id" type="int auto_increment"/>
    <tablePartition type="HASH">
      <tablePartitionOption name="column" value="id"/>
      <tablePartitionOption name="number" value="4"/>
    </tablePartition>
  </table>
</schema>
XML;
    $schema = simplexml_load_string($xml);
    $table = $schema->table;
    $get_sql = function() use (&$schema, &$table) {
      return mysql5_table::get_partition_sql($schema, $table);
    };

    // base case - note the quoted column
    $this->assertEquals("PARTITION BY HASH (`id`) PARTITIONS 4", $get_sql());

    // modulo is acceptable too
    $table->tablePartition['type'] = 'MODULO';
    $this->assertEquals("PARTITION BY HASH (`id`) PARTITIONS 4", $get_sql());

    // linear hash is just a different algorithm
    $table->tablePartition['type'] = 'LINEAR HASH';
    $this->assertEquals("PARTITION BY LINEAR HASH (`id`) PARTITIONS 4", $get_sql());

    // check that a column option looks for the column
    $table->tablePartition['type'] = 'HASH';
    $table->tablePartition->tablePartitionOption[0]['value'] = 'foo';
    $this->expect("no column named 'foo'", $get_sql);

    // check that we validate the number of partitions
    $table->tablePartition->tablePartitionOption[0]['value'] = 'id';
    $table->tablePartition->tablePartitionOption[1]['value'] = 'x';
    $this->expect("tablePartitionOption 'number' must be an integer greater than 0", $get_sql);

    // check that using an expression does NOT quote the value
    $table->tablePartition->tablePartitionOption[1]['value'] = '4';
    $table->tablePartition->tablePartitionOption[0]['name'] = 'expression';
    $table->tablePartition->tablePartitionOption[0]['value'] = 'id + 1';
    $this->assertEquals("PARTITION BY HASH (id + 1) PARTITIONS 4", $get_sql());
  }

  public function testGetKeyPartitionSql() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="id" type="int auto_increment"/>
    <column name="foo" type="int"/>
    <tablePartition type="KEY">
      <tablePartitionOption name="column" value="id"/>
      <tablePartitionOption name="number" value="4"/>
    </tablePartition>
  </table>
</schema>
XML;
    $schema = simplexml_load_string($xml);
    $table = $schema->table;
    $get_sql = function() use (&$schema, &$table) {
      return mysql5_table::get_partition_sql($schema, $table);
    };

    // base case - note the quoted column
    $this->assertEquals("PARTITION BY KEY (`id`) PARTITIONS 4", $get_sql());

    // linear key is just a different algorithm
    $table->tablePartition['type'] = 'LINEAR KEY';
    $this->assertEquals("PARTITION BY LINEAR KEY (`id`) PARTITIONS 4", $get_sql());

    // check that a column option looks for the column
    $table->tablePartition['type'] = 'KEY';
    $table->tablePartition->tablePartitionOption[0]['value'] = 'bar';
    $this->expect("no column named 'bar'", $get_sql);

    // check that we validate the number of partitions
    $table->tablePartition->tablePartitionOption[0]['value'] = 'id';
    $table->tablePartition->tablePartitionOption[1]['value'] = 'x';
    $this->expect("tablePartitionOption 'number' must be an integer greater than 0", $get_sql);

    // check that using an expression does NOT quote the value
    $table->tablePartition->tablePartitionOption[1]['value'] = '4';
    $table->tablePartition->tablePartitionOption[0]['name'] = 'columns';
    $table->tablePartition->tablePartitionOption[0]['value'] = 'id, foo';
    $this->assertEquals("PARTITION BY KEY (`id`, `foo`) PARTITIONS 4", $get_sql());
  }

  public function testListRangePartitionSql() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="id" type="int auto_increment"/>
    <column name="foo" type="int"/>
    <tablePartition type="LIST">
      <tablePartitionOption name="column" value="id"/>
      <tablePartitionSegment name="p0" value="1, 2, 3"/>
      <tablePartitionSegment name="p1" value="4, 5, 6"/>
    </tablePartition>
  </table>
</schema>
XML;
    $schema = simplexml_load_string($xml);
    $table = $schema->table;
    $get_sql = function() use (&$schema, &$table) {
      return mysql5_table::get_partition_sql($schema, $table);
    };

    $this->assertEquals("PARTITION BY LIST (`id`) (
  PARTITION `p0` VALUES IN (1, 2, 3),
  PARTITION `p1` VALUES IN (4, 5, 6)
)", $get_sql());

    $table->tablePartition['type'] = 'RANGE';
    $table->tablePartition->tablePartitionSegment[0]['value'] = '4';
    $table->tablePartition->tablePartitionSegment[1]['value'] = '6';
    $p2 = $table->tablePartition->addChild('tablePartitionSegment');
    $p2['name'] = 'p2';
    $p2['value'] = 'MAXVALUE';


    $this->assertEquals("PARTITION BY RANGE (`id`) (
  PARTITION `p0` VALUES LESS THAN (4),
  PARTITION `p1` VALUES LESS THAN (6),
  PARTITION `p2` VALUES LESS THAN (MAXVALUE)
)", $get_sql());


    $table->tablePartition['type'] = 'RANGE COLUMNS';
    $table->tablePartition->tablePartitionOption[0]['name'] = 'columns';
    $table->tablePartition->tablePartitionOption[0]['value'] = 'id,foo';
    $table->tablePartition->tablePartitionSegment[0]['value'] = '4,10';
    $table->tablePartition->tablePartitionSegment[1]['value'] = '6,20';
    $table->tablePartition->tablePartitionSegment[2]['value'] = 'MAXVALUE,MAXVALUE';

    $this->assertEquals("PARTITION BY RANGE COLUMNS (`id`, `foo`) (
  PARTITION `p0` VALUES LESS THAN (4,10),
  PARTITION `p1` VALUES LESS THAN (6,20),
  PARTITION `p2` VALUES LESS THAN (MAXVALUE,MAXVALUE)
)", $get_sql());
  }

  private function expect($err, $callback) {
    try {
      $res = call_user_func($callback);
    }
    catch (exception $ex) {
      $this->assertContains(strtolower($err), strtolower($ex->getMessage()));
      return;
    }
    $this->fail("Expected to catch exception with message containing '$err', instead got result:\n".print_r($res));
  }
}
