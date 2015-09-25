<?php
/**
 * DBSteward unit test for mysql5 extraction errors
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/Mysql5ExtractionTest.php';

/**
 * @group mysql5
 */
class Mysql5TablePartitionsExtractionTest extends Mysql5ExtractionTest {

  const DEBUG = true;

  public function testExtractHash() {
    $schema = $this->extract("CREATE TABLE hash_test (id int PRIMARY KEY) PARTITION BY HASH (id) PARTITIONS 4;");
    $partition = $schema->table->tablePartition;

    $this->assertNotEmpty($partition);
    $this->assertEquals('HASH', (string)$partition['type']);

    $opts = mysql5_table::get_partition_options($schema->table['name'], $partition);
    $this->assertEquals('id', $opts['expression']);
    $this->assertEquals('4', $opts['number']);
  }

  public function testExtractLinearHash() {
    $schema = $this->extract("CREATE TABLE linear_hash_test (id int PRIMARY KEY) PARTITION BY LINEAR HASH (id) PARTITIONS 4;");
    $partition = $schema->table->tablePartition;

    $this->assertNotEmpty($partition);
    $this->assertEquals('LINEAR HASH', (string)$partition['type']);

    $opts = mysql5_table::get_partition_options($schema->table['name'], $partition);
    $this->assertEquals('id', $opts['expression']);
    $this->assertEquals('4', $opts['number']);
  }

  public function testExtractKey() {
    $schema = $this->extract("CREATE TABLE key_test (id int, foo int, PRIMARY KEY (id, foo)) PARTITION BY KEY (id, foo) PARTITIONS 4;");
    $partition = $schema->table->tablePartition;

    $this->assertNotEmpty($partition);
    $this->assertEquals('KEY', (string)$partition['type']);

    $opts = mysql5_table::get_partition_options($schema->table['name'], $partition);
    $this->assertEquals('id,foo', $opts['columns']);
    $this->assertEquals('4', $opts['number']);
  }

  public function testExtractLinearKey() {
    $schema = $this->extract("CREATE TABLE key_test (id int, foo int, PRIMARY KEY (id, foo)) PARTITION BY LINEAR KEY (id, foo) PARTITIONS 4;");
    $partition = $schema->table->tablePartition;

    $this->assertNotEmpty($partition);
    $this->assertEquals('LINEAR KEY', (string)$partition['type']);

    $opts = mysql5_table::get_partition_options($schema->table['name'], $partition);
    $this->assertEquals('id,foo', $opts['columns']);
    $this->assertEquals('4', $opts['number']);
  }

  public function testExtractList() {
    $sql = <<<SQL
CREATE TABLE list_test (id int PRIMARY KEY)
PARTITION BY LIST (id) (
  PARTITION p0 VALUES IN (1, 2, 3),
  PARTITION p1 VALUES IN (4, 5, 6),
  PARTITION p2 VALUES IN (7, 8, 9)
);
SQL;
    $schema = $this->extract($sql);
    $partition = $schema->table->tablePartition;

    $this->assertNotEmpty($partition);
    $this->assertEquals('LIST', (string)$partition['type']);

    $opts = mysql5_table::get_partition_options($schema->table['name'], $partition);
    $this->assertEquals('id', $opts['expression']);

    $this->assertEquals(3, count($partition->tablePartitionSegment));
    $this->assertEquals('p0', (string)$partition->tablePartitionSegment[0]['name']);
    $this->assertEquals('1,2,3', (string)$partition->tablePartitionSegment[0]['value']);
    $this->assertEquals('p1', (string)$partition->tablePartitionSegment[1]['name']);
    $this->assertEquals('4,5,6', (string)$partition->tablePartitionSegment[1]['value']);
    $this->assertEquals('p2', (string)$partition->tablePartitionSegment[2]['name']);
    $this->assertEquals('7,8,9', (string)$partition->tablePartitionSegment[2]['value']);
  }

  public function testExtractRange() {
    $sql = <<<SQL
CREATE TABLE range_test (id int PRIMARY KEY)
PARTITION BY RANGE (id) (
  PARTITION p0 VALUES LESS THAN (10),
  PARTITION p1 VALUES LESS THAN (20),
  PARTITION p2 VALUES LESS THAN (MAXVALUE)
);
SQL;
    $schema = $this->extract($sql);
    $partition = $schema->table->tablePartition;

    $this->assertNotEmpty($partition);
    $this->assertEquals('RANGE', (string)$partition['type']);

    $opts = mysql5_table::get_partition_options($schema->table['name'], $partition);
    $this->assertEquals('id', $opts['expression']);

    $this->assertEquals(3, count($partition->tablePartitionSegment));
    $this->assertEquals('p0', (string)$partition->tablePartitionSegment[0]['name']);
    $this->assertEquals('10', (string)$partition->tablePartitionSegment[0]['value']);
    $this->assertEquals('p1', (string)$partition->tablePartitionSegment[1]['name']);
    $this->assertEquals('20', (string)$partition->tablePartitionSegment[1]['value']);
    $this->assertEquals('p2', (string)$partition->tablePartitionSegment[2]['name']);
    $this->assertEquals('MAXVALUE', (string)$partition->tablePartitionSegment[2]['value']);
  }

  public function testExtractRangeColumns() {
    $sql = <<<SQL
CREATE TABLE range_test (id int, foo int, PRIMARY KEY (id, foo))
PARTITION BY RANGE COLUMNS (id, foo) (
  PARTITION p0 VALUES LESS THAN (10, 20),
  PARTITION p1 VALUES LESS THAN (20, 30),
  PARTITION p2 VALUES LESS THAN (MAXVALUE, MAXVALUE)
);
SQL;
    $schema = $this->extract($sql);
    $partition = $schema->table->tablePartition;

    $this->assertNotEmpty($partition);
    $this->assertEquals('RANGE COLUMNS', (string)$partition['type']);

    $opts = mysql5_table::get_partition_options($schema->table['name'], $partition);
    $this->assertEquals('id,foo', $opts['columns']);

    $this->assertEquals(3, count($partition->tablePartitionSegment));
    $this->assertEquals('p0', (string)$partition->tablePartitionSegment[0]['name']);
    $this->assertEquals('10,20', (string)$partition->tablePartitionSegment[0]['value']);
    $this->assertEquals('p1', (string)$partition->tablePartitionSegment[1]['name']);
    $this->assertEquals('20,30', (string)$partition->tablePartitionSegment[1]['value']);
    $this->assertEquals('p2', (string)$partition->tablePartitionSegment[2]['name']);
    $this->assertEquals('MAXVALUE,MAXVALUE', (string)$partition->tablePartitionSegment[2]['value']);
  }
}