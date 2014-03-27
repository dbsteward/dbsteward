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

    dbsteward::set_sql_format('pgsql8');

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

  public function testIndexesExtractInCorrectOrder() {
    $sql = <<<SQL
CREATE TABLE i_test (
  id SERIAL PRIMARY KEY,
  col1 int,
  col2 int,
  col3 int,
  col4 int,
  col5 int
);
CREATE INDEX idx1 ON i_test(col5, col4, col3, col2);
CREATE INDEX idx2 ON i_test(col1, col4, col5, col2);
CREATE INDEX idx3 ON i_test(col1, col2, col3, col4);
SQL;
    $schema = $this->extract($sql);

    function getDims($node_index) {
      $dims = array();
      foreach ($node_index->indexDimension as $node_dim) {
        $dims[] = (string)$node_dim;
      }
      return $dims;
    }

    $idx1 = $schema->table->xpath('index[@name="idx1"]');
    $this->assertEquals(array('col5','col4','col3','col2'), getDims($idx1[0]));

    $idx2 = $schema->table->xpath('index[@name="idx2"]');
    $this->assertEquals(array('col1','col4','col5','col2'), getDims($idx2[0]));

    $idx3 = $schema->table->xpath('index[@name="idx3"]');
    $this->assertEquals(array('col1','col2','col3','col4'), getDims($idx3[0]));
  }

  /**
   * Tests that pgsql8 extracts compound unique constraints correctly */
  public function testExtractCompoundUniqueConstraint() {
    $sql = <<<SQL
CREATE TABLE test (
  col1 bigint NOT NULL PRIMARY KEY,
  col2 bigint NOT NULL,
  col3 character varying(20) NOT NULL,
  col4 character varying(20),
  CONSTRAINT test_constraint UNIQUE (col2, col3, col4)
);
SQL;

    $schema = $this->extract($sql);

    $this->assertNotEquals('true', (string)$schema->table->column[1]['unique']);
    $this->assertNotEquals('true', (string)$schema->table->column[2]['unique']);
    $this->assertNotEquals('true', (string)$schema->table->column[3]['unique']);

    $this->assertEquals('test_constraint', (string)$schema->table->constraint['name']);
    $this->assertEquals('UNIQUE', strtoupper($schema->table->constraint['type']));
    $this->assertEquals('("col2", "col3", "col4")', (string)$schema->table->constraint['definition']);
  }

  public function testExtractTableColumnComments() {
    $table_description = 'A description of the test table';
    $column_description = 'A description of col1 on the test table';
    $sql = <<<SQL
CREATE TABLE test (
  col1 text PRIMARY KEY
);
COMMENT ON TABLE test IS '$table_description';
COMMENT ON COLUMN test.col1 IS '$column_description';
SQL;
    
    $schema = $this->extract($sql);

    $this->assertEquals($table_description, (string)$schema->table['description']);
    $this->assertEquals($column_description, (string)$schema->table->column['description']);
  }
  
  /*
   * Test that functions with ampersands in their definition bodies
   * extract properly - because XML
   */
  public function testExtractFunctionWithAmpersandParade() {
    $function_body = <<<SQL
DECLARE
  overlap boolean;
BEGIN
  overlap := $1 && $2;
  RETURN overlap;
END;
SQL;
    
    // this function definition cheats - money is not an implicit array
    // even so, this test ensures that functionDefinitions with && will get extracted properly
    // function_body is used inline in the SQL definition and checked for in the assertion
    $sql = <<<SQL
CREATE OR REPLACE FUNCTION "rates_overlap"(rates_a money, rates_b money) RETURNS boolean
    AS \$_$
$function_body
    \$_$
LANGUAGE plpgsql VOLATILE;
SQL;
    
    $schema = $this->extract($sql);

    $extracted_function_body = trim($schema->function->functionDefinition);
    $this->assertEquals(trim($function_body), $extracted_function_body);
  }

  public function testExtractFunctionWithArrayTypeAndArgumentNames() {
    $schema = $this->extract("DROP LANGUAGE IF EXISTS plpgsql; CREATE LANGUAGE plpgsql; CREATE OR REPLACE FUNCTION increment(arg1 integer[], arg2 uuid[]) RETURNS integer AS $$ BEGIN RETURN 1; END; $$ LANGUAGE plpgsql");
    $this->assertEquals("integer[]", (string)$schema->function->functionParameter[0]->attributes()->type);
    $this->assertEquals("arg1", (string)$schema->function->functionParameter[0]->attributes()->name);
    $this->assertEquals("uuid[]", (string)$schema->function->functionParameter[1]->attributes()->type);
    $this->assertEquals("arg2", (string)$schema->function->functionParameter[1]->attributes()->name);
  }

  public function testExtractFunctionWithArrayTypeAndNoArgumentNames() {
    $schema = $this->extract("DROP LANGUAGE IF EXISTS plpgsql; CREATE LANGUAGE plpgsql; CREATE OR REPLACE FUNCTION increment(integer[], uuid[]) RETURNS integer AS $$ BEGIN RETURN 1; END; $$ LANGUAGE plpgsql");
    $this->assertEquals("integer[]", (string)$schema->function->functionParameter[0]->attributes()->type);
    $this->assertEquals("", (string)$schema->function->functionParameter[0]->attributes()->name);
    $this->assertEquals("uuid[]", (string)$schema->function->functionParameter[1]->attributes()->type);
    $this->assertEquals("", (string)$schema->function->functionParameter[1]->attributes()->name);
  }

  public function testExtractFunctionWithArrayTypeAndMixedArgumentNames() {
    $schema = $this->extract("DROP LANGUAGE IF EXISTS plpgsql; CREATE LANGUAGE plpgsql; CREATE OR REPLACE FUNCTION increment(arg1 integer[], uuid[]) RETURNS integer AS $$ BEGIN RETURN 1; END; $$ LANGUAGE plpgsql");
    $this->assertEquals("integer[]", (string)$schema->function->functionParameter[0]->attributes()->type);
    $this->assertEquals("arg1", (string)$schema->function->functionParameter[0]->attributes()->name);
    $this->assertEquals("uuid[]", (string)$schema->function->functionParameter[1]->attributes()->type);
    $this->assertEquals("", (string)$schema->function->functionParameter[1]->attributes()->name);
    $schema = $this->extract("CREATE OR REPLACE FUNCTION increment(integer[], arg1 uuid[]) RETURNS integer AS $$ BEGIN RETURN 1; END; $$ LANGUAGE plpgsql");
    $this->assertEquals("integer[]", (string)$schema->function->functionParameter[0]->attributes()->type);
    $this->assertEquals("", (string)$schema->function->functionParameter[0]->attributes()->name);
    $this->assertEquals("uuid[]", (string)$schema->function->functionParameter[1]->attributes()->type);
    $this->assertEquals("arg1", (string)$schema->function->functionParameter[1]->attributes()->name);
  }

  public function testExtractTableWithArrayType() {
    $schema = $this->extract("CREATE TABLE test(name text[]); CREATE INDEX lower_idx on test(name);");
    $column = $schema->table->column;
    $this->assertEquals("text[]", (string)$schema->table->column->attributes()->type);
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
