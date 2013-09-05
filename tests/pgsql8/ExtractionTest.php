<?php
/**
 * DBSteward unit test for pgsql8 tableOption diffing
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
class ExtractionTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    // disable pesky output buffering
    while (ob_get_level()) ob_end_clean();

    $this->conn = $GLOBALS['db_config']->pgsql8_conn;
    $this->createSchema();
  }
  public function tearDown() {
    $this->dropSchema();

    // re-enable output buffering so phpunit doesn't bitch
    ob_start();
  }

  /** Tests that indexDimension expressions are correctly extracted */
  public function testExtractFunctionalIndex() {
    $schema = $this->extract("CREATE TABLE test(name text); CREATE INDEX lower_idx on test(lower(name));");

    $this->assertEquals('lower_idx_1', (string)$schema->table->index->indexDimension['name']);
    $this->assertEquals('lower(name)', (string)$schema->table->index->indexDimension);
  }

  /** Tests that even very complicated expressions are correctly extracted */
  public function testExtractComplicatedFunctionalIndex() {
    $schema = $this->extract("CREATE TABLE test(col1 text, col2 text, col3 text);\nCREATE INDEX testidx ON test(lower(col1), col2, (col1||';;'), col3, overlay(trim(col2) placing 'x' from 2));");
    $dims = $schema->table->index->indexDimension;

    // NOTE: pgsql reports the acutal expression a little bit different than how it's input, because it's
    // serializing the internal expression tree into a human-readable string. It appears to normalize
    // whitespace, resolve aliased functions (trim -> btrim), quote things that need quoted, convert
    // special keywords (like "placing 'x' from") to equivalent function arguments, and make explicit casts
    $this->assertEquals('testidx_1', (string)$dims[0]['name']);
    $this->assertEquals('lower(col1)', (string)$dims[0]);

    $this->assertEquals('testidx_2', (string)$dims[1]['name']);
    $this->assertEquals('col2', (string)$dims[1]);

    $this->assertEquals('testidx_3', (string)$dims[2]['name']);
    $this->assertEquals("(col1 || ';;'::text)", (string)$dims[2]);

    $this->assertEquals('testidx_4', (string)$dims[3]['name']);
    $this->assertEquals('col3', (string)$dims[3]);

    $this->assertEquals('testidx_5', (string)$dims[4]['name']);
    // original: overlay(trim(col2) placing 'x' from 2)
    // pgsql quotes "overlay", changes trim to btrim, casts 'x' to stext, and replaces "placing" and "from" with commas
    $this->assertEquals("\"overlay\"(btrim(col2), 'x'::text, 2)", (string)$dims[4]);
  }

  protected function extract($sql, $in_schema = TRUE) {
    $schemaname = __CLASS__;
    
    $sql = rtrim($sql, ';');
    $sql = "SET search_path TO \"$schemaname\",public;\nBEGIN;\n$sql;\nCOMMIT;";
    $this->query($sql);

      $xml = pgsql8::extract_schema($this->conn->get_dbhost(), $this->conn->get_dbport(), $this->conn->get_dbname(), $this->conn->get_dbuser(), $this->conn->get_dbpass());
      $dbdoc = simplexml_load_string($xml);

    foreach ($dbdoc->schema as $schema) {
      if (strcmp($schema['name'], $schemaname) == 0) {
        echo "Got schema:\n" . $schema->asXML() . "\n";
        return $schema;
      }
    }
    echo $dbdoc->asXML() . "\n";
    throw new exception("No schema named $schemaname was found!?");
  }

  protected function createSchema() {
    $schemaname = __CLASS__;
    $this->dropSchema();
    $this->query("CREATE SCHEMA \"$schemaname\";");
  }

  protected function dropSchema() {
    $schemaname = __CLASS__;
    $this->query("DROP SCHEMA IF EXISTS \"$schemaname\" CASCADE;");
  }

  protected function query($sql) {
    echo "Running query:\n$sql\n\n";
    $this->conn->query($sql);
  }
}