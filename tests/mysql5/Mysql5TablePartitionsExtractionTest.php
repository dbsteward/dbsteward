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

  public function testExtractHashByColumnPartition() {
    $schema = $this->extract("CREATE TABLE hash_test (id int PRIMARY KEY) PARTITION BY HASH (id) PARTITIONS 4;");
    $partition = $schema->table->tablePartition;

    $this->assertNotEmpty($partition);
    $this->assertEquals('HASH', (string)$partition['type']);

    $opts = mysql5_table::get_partition_options($schema->table['name'], $partition);
    $this->assertEquals('id', $opts['expression']);
    $this->assertEquals('4', $opts['number']);
  }
}