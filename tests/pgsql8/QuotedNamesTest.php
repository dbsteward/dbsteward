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

  public function testQuoteFKeyTable() {
    $xml = <<<XML
<dbsteward>
<schema name="test">
  <table name="test1">
    <column name="col1" type="int" />
  </table>
  <table name="test2">
    <column name="col1" foreignSchema="test" foreignTable="test1" foreignColumn="col1"/>
  </table>
</schema>
</dbsteward>
XML;
    $db_doc = simplexml_load_string($xml);

    $constraints = dbx::get_table_constraints($db_doc, $db_doc->schema, $db_doc->schema->table[1], 'foreignKey');

    $sql = trim(pgsql8_table::get_constraint_sql_change_statement($constraints[0]));
    $expected = 'ADD CONSTRAINT "test2_col1_fkey" FOREIGN KEY ("col1") REFERENCES "test"."test1"("col1")';
    $this->assertEquals($expected, $sql);
  }
  
  public function testFunctionNameQuoting() {
    $xml = <<<XML
<dbsteward>
  <schema name="test">
    <table name="test1">
      <column name="col1" type="int" />
    </table>
    <function name="testfunc" returns="int" owner="ROLE_OWNER" cachePolicy="VOLATILE" description="test function">
      <functionParameter name="test_id" type="int" />
      <functionDefinition language="plpgsql" sqlFormat="pgsql8">
        BEGIN
          RETURN test_id;
        END;
      </functionDefinition>
    </function>
  </schema>
</dbsteward>
XML;
    $db_doc = simplexml_load_string($xml);
    $fxn_sql = pgsql8_function::get_declaration($db_doc->schema, $db_doc->schema->function);
    $expected = '"test"."testfunc"(test_id int)';
    $this->assertEquals($expected, $fxn_sql);
  }
}