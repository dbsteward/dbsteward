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
    $this->conn = $GLOBALS['db_config']->pgsql8_conn;
  }

  protected function extract($sql, $in_schema = TRUE) {
    $schemaname = __CLASS__;

    if ($in_schema) {
      $this->conn->query("DROP SCHEMA IF EXISTS $schemaname CASCADE; CREATE SCHEMA $schemaname;");
      $sql = "SET search_path TO $schemaname,public; $sql";
    }

    $this->conn->query($sql);

    $xml = pgsql8::extract_schema($this->conn->get_dbhost(), $this->conn->get_dbport(), $this->conn->get_dbname(), $this->conn->get_dbuser(), $this->conn->get_dbpass());
    $dbdoc = simplexml_load_string($xml);

    if ($in_schema) {
      foreach ($dbdoc->schema as $schema) {
        if (strcmp($schema['name'], $schemaname) == 0) {
          return $schema;
        }
      }
      var_dump($dbdoc);
      throw new exception("No schema named $schemaname was found!?");
    }
    return $dbdoc;
  }
}