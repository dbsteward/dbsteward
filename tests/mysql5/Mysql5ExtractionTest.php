<?php
/**
 * DBSteward unit test for mysql5 extraction errors
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

/**
 * @group mysql5
 */
class Mysql5ExtractionTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    // disable pesky output buffering
    while (ob_get_level()) ob_end_clean();

    $this->conn = $GLOBALS['db_config']->mysql5_conn;
    $this->createSchema();
  }
  public function tearDown() {
    $this->dropSchema();

    // re-enable output buffering so phpunit doesn't bitch
    ob_start();
  }

  // public function testSchema() {
  //   $schema = $this->extract('--');
  //   $this->assertEquals('ExtractionTest', (string)$schema['name']);
  // }


  protected function extract($sql) {
    $schemaname = __CLASS__;
    
    $sql = rtrim($sql, ';');
    $sql = "USE $schemaname;\n$sql;";
    $this->query($sql);

    $xml = mysql5::extract_schema($this->conn->get_dbhost(), $this->conn->get_dbport(), $schemaname, $this->conn->get_dbuser(), $this->conn->get_dbpass());
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
    $this->query("CREATE DATABASE $schemaname;");
  }

  protected function dropSchema() {
    $schemaname = __CLASS__;
    $this->query("DROP DATABASE IF EXISTS $schemaname;");
  }

  protected function query($sql) {
    echo "Running query:\n$sql\n\n";
    $this->conn->query($sql);
  }
}