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

/**
 * @group mysql5
 */
class Mysql5IndexSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
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

    $this->assertEquals("CREATE INDEX `default_idx` ON `test` (`a`);", $default);
    $this->assertEquals("CREATE INDEX `hash_idx` ON `test` (`a`) USING HASH;", $hash);
    $this->assertEquals("CREATE INDEX `btree_idx` ON `test` (`a`) USING BTREE;", $btree);
    $this->assertEquals("CREATE INDEX `gin_idx` ON `test` (`a`) USING BTREE;", $gin);
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
      "CREATE UNIQUE INDEX `unique_idx` ON `test` (`a`);",
      "CREATE INDEX `not_unique_idx` ON `test` (`a`);",
      "CREATE UNIQUE INDEX `a` ON `test` (`a`) USING BTREE;"
    );

    $actual = array_map(function ($index) use (&$schema) {
      return trim(preg_replace('/--.*/','',mysql5_index::get_creation_sql($schema, $schema->table, $index)));
    }, mysql5_index::get_table_indexes($schema, $schema->table));

    $this->assertEquals($expected, $actual);
  }

  public function testUniqueColumnNameCollisions() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY">
    <column name="a" unique="true"/>
    <column name="b" unique="true"/>
    <column name="c" unique="true"/>
    <column name="d" unique="true"/>
    <column name="e_2" unique="true"/>

    <index name="a" unique="true">
      <indexDimension name="a_1">a</indexDimension>
    </index>
    <index name="b_2" unique="true">
      <indexDimension name="b_1">b</indexDimension>
    </index>
    <index name="c" unique="true">
      <indexDimension name="c_1">c</indexDimension>
    </index>
    <index name="c_2" unique="true">
      <indexDimension name="c_1">c</indexDimension>
      <indexDimension name="c_1">b</indexDimension>
    </index>
    <index name="d_3" unique="true">
      <indexDimension name="d_1">d</indexDimension>
    </index>
    <index name="d_2" unique="true">
      <indexDimension name="d_1">d</indexDimension>
    </index>
    <index name="d_1" unique="true">
      <indexDimension name="d_1">d</indexDimension>
    </index>
    <index name="d" unique="true">
      <indexDimension name="d_1">d</indexDimension>
    </index>
    <index name="e_2" unique="true">
      <indexDimension name="e_1">e_2</indexDimension>
    </index>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = array(
      "CREATE UNIQUE INDEX `a` ON `test` (`a`);",
      "CREATE UNIQUE INDEX `b_2` ON `test` (`b`);",
      "CREATE UNIQUE INDEX `c` ON `test` (`c`);",
      "CREATE UNIQUE INDEX `c_2` ON `test` (`c`, `b`);",
      "CREATE UNIQUE INDEX `d_3` ON `test` (`d`);",
      "CREATE UNIQUE INDEX `d_2` ON `test` (`d`);",
      "CREATE UNIQUE INDEX `d_1` ON `test` (`d`);",
      "CREATE UNIQUE INDEX `d` ON `test` (`d`);",
      "CREATE UNIQUE INDEX `e_2` ON `test` (`e_2`);",

      // column indexes
      // column a should get named 'a_2', because there's already an index called 'a'
      "CREATE UNIQUE INDEX `a_2` ON `test` (`a`) USING BTREE;",

      // column b should get named 'b', because there's no other column called 'b'
      "CREATE UNIQUE INDEX `b` ON `test` (`b`) USING BTREE;",

      // column c should get named 'c_3', because there's already index 'c' and 'c_2'
      "CREATE UNIQUE INDEX `c_3` ON `test` (`c`) USING BTREE;",

      // column d should get named 'd_4', because there's already indexes 'd', 'd_2', and 'd_3'
      // d_1 shouldn't matter.
      "CREATE UNIQUE INDEX `d_4` ON `test` (`d`) USING BTREE;",

      // get ready for this: creating an index on a column that already has a numeric suffix 
      // actually ignores the suffix and adds ANOTHER ONE!
      "CREATE UNIQUE INDEX `e_2_2` ON `test` (`e_2`) USING BTREE;",
    );

    $actual = array_map(function ($index) use (&$schema) {
      return trim(preg_replace('/--.*/','',mysql5_index::get_creation_sql($schema, $schema->table, $index)));
    }, mysql5_index::get_table_indexes($schema, $schema->table));

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

    $expected = "CREATE INDEX `compound_idx` ON `test` (`a`, `b`, `c`);";
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
CREATE INDEX `compound_idx` ON `test` (`a`, `b`, `c`);
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
    $this->assertEquals("DROP INDEX `default_idx` ON `test`;", $actual);
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
      mysql5_index::get_table_indexes($schema, $table);
    }
    catch (Exception $ex) {
      $this->assertContains('Duplicate index name', $ex->getMessage());
      return;
    }
    $this->fail("Expected an exception because a table had duplicate index names");
  }

  public function testForeignKeyOnPrimaryKeyDoesntCreateIndex() {
    $xml = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="table1" owner="ROLE_OWNER" primaryKey="col1">
    <column name="col1" foreignSchema="public" foreignTable="table2" foreignColumn="col2"/>
  </table>
  <table name="table2" owner="ROLE_OWNER" primaryKey="col1">
    <column name="col1" type="int"/>
  </table>
</schema>
XML;
    
    $schema = simplexml_load_string($xml);
    $table = $schema->table;

    $indexes = mysql5_index::get_table_indexes($schema, $table);

    $this->assertEquals(array(), $indexes, "There should be no indexes created when the fkey is on the pkey");
  }
}
?>
