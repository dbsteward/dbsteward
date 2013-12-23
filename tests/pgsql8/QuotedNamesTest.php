<?php
/**
 * DBSteward unit test for pgsql8 quotation tests
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

/**
 * @group pgsql8
 */
class QuotedNamesTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = TRUE;
  }

  public function testQuoteIndexDimensions() {
    $xml = <<<XML
<schema name="test">
  <table name="test">
    <index name="idx" using="btree" unique="false">
      <indexDimension name="idx_1">col1</indexDimension>
      <indexDimension name="idx_2">col2</indexDimension>
    </index>
  </table>
</schema>
XML;
    $schema = simplexml_load_string($xml);

    $sql = pgsql8_index::get_creation_sql($schema, $schema->table, $schema->table->index);

    $this->assertEquals('CREATE INDEX "idx" ON "test"."test" USING btree ("col1", "col2");', $sql);
  }
}