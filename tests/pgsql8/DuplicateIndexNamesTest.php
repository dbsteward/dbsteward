<?php
/**
 * DBSteward unit test for testing what happens with duplicate index names
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

class DuplicateIndexNamesTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = TRUE;
  }

  public function testDuplicateIndexNamesThrowException() {
    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table1" owner="ROLE_OWNER" primaryKey="col1">
    <column name="col1" type="int"/>
    <index name="index1">
      <indexDimension name="index1_1">col1</indexDimension>
    </index>
    <index name="index1">
      <indexDimension name="index1_1">col1</indexDimension>
    </index>
  </table>
</schema>
XML;
    
    $schema = simplexml_load_string($xml);
    $table = $schema->table;

    try {
      pgsql8_index::get_table_indexes($schema, $table);
    }
    catch (Exception $ex) {
      $this->assertContains('Duplicate index name', $ex->getMessage());
      return;
    }
    $this->fail("Expected an exception because a table had duplicate index names");
  }
}