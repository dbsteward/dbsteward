<?php
/**
 * DBSteward unit test for mysql5 index definition generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

class Mysql5IndexSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  public function testIndexMethods() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a"/>
    <index name="default_idx">
      <indexDimension name="a_1">a</indexDimension>
    </index>
    <index name="hash_idx" using="hash">
      <indexDimension name="a_1">a</indexDimension>
    </index>
    <index name="btree_idx" using="btree">
      <indexDimension name="a_1">a</indexDimension>
    </index>
    <index name="gin_idx" using="gin">
      <indexDimension name="a_1">a</indexDimension>
    </index>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $default = trim(preg_replace('/--.*/','',mysql5_index::get_creation_sql($schema, $schema->table, $schema->table->index[0])));
    $hash = trim(preg_replace('/--.*/','',mysql5_index::get_creation_sql($schema, $schema->table, $schema->table->index[1])));
    $btree = trim(preg_replace('/--.*/','',mysql5_index::get_creation_sql($schema, $schema->table, $schema->table->index[2])));
    $gin = trim(preg_replace('/--.*/','',mysql5_index::get_creation_sql($schema, $schema->table, $schema->table->index[3])));

    $this->assertEquals("CREATE INDEX `default_idx` ON `public`.`test` (`a`);", $default);
    $this->assertEquals("CREATE INDEX `hash_idx` ON `public`.`test` (`a`) USING HASH;", $hash);
    $this->assertEquals("CREATE INDEX `btree_idx` ON `public`.`test` (`a`) USING BTREE;", $btree);
    $this->assertEquals("CREATE INDEX `gin_idx` ON `public`.`test` (`a`) USING BTREE;", $gin);
  }

  public function testUnique() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a" unique="true"/>
    <index name="unique_idx" unique="true">
      <indexDimension name="a_1">a</indexDimension>
    </index>
    <index name="not_unique_idx" unique="false">
      <indexDimension name="a_1">a</indexDimension>
    </index>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = array(
      "CREATE UNIQUE INDEX `unique_idx` ON `public`.`test` (`a`);",
      "CREATE INDEX `not_unique_idx` ON `public`.`test` (`a`);",
      "CREATE UNIQUE INDEX `test_a_key` ON `public`.`test` (`a`) USING BTREE;"
    );

    $actual = array_map(function ($index) use (&$schema) {
      return trim(preg_replace('/--.*/','',mysql5_index::get_creation_sql($schema, $schema->table, $index)));
    }, dbx::get_table_indexes($schema, $schema->table));

    $this->assertEquals($expected, $actual);
  }

  public function testCompound() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a"/>
    <column name="b"/>
    <column name="c"/>
    <index name="compound_idx">
      <indexDimension name="a_1">a</indexDimension>
      <indexDimension name="b_2">b</indexDimension>
      <indexDimension name="c_3">c</indexDimension>
    </index>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = "CREATE INDEX `compound_idx` ON `public`.`test` (`a`, `b`, `c`);";
    $actual = trim(preg_replace('/--.*/','',mysql5_index::get_creation_sql($schema, $schema->table, $schema->table->index)));

    $this->assertEquals($expected, $actual);
  }

  public function testDimensionNames() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a"/>
    <column name="b"/>
    <column name="c"/>
    <index name="compound_idx">
      <indexDimension name="idx_dim_a">a</indexDimension>
      <indexDimension name="idx_dim_b">b</indexDimension>
      <indexDimension name="idx_dim_c">c</indexDimension>
    </index>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
-- note that MySQL does not support indexed expressions or named dimensions
-- ignoring name 'idx_dim_a' for dimension 'a' on index 'compound_idx'
-- ignoring name 'idx_dim_b' for dimension 'b' on index 'compound_idx'
-- ignoring name 'idx_dim_c' for dimension 'c' on index 'compound_idx'
CREATE INDEX `compound_idx` ON `public`.`test` (`a`, `b`, `c`);
SQL;
    $actual = mysql5_index::get_creation_sql($schema, $schema->table, $schema->table->index);

    $this->assertEquals($expected, $actual);
  }

  public function testDrop() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a"/>
    <index name="default_idx">
      <indexDimension name="a_1">a</indexDimension>
    </index>
  </table>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);

    $actual = mysql5_index::get_drop_sql($schema, $schema->table, $schema->table->index);
    $this->assertEquals("DROP INDEX `default_idx` ON `public`.`test`;", $actual);
  }
}
?>
