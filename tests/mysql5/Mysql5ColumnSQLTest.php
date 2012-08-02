<?php
/**
 * DBSteward unit test for mysql5 column definition generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

class Mysql5ColumnSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
  }

  public function testSimple() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="id" type="int" unique="true" null="false" default="2" description="test col'umn id"/>
    <column name="foo" type="text"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected_id = "`id` int NOT NULL DEFAULT 2 COMMENT 'test col\'umn id'";
    $expected_foo = "`foo` text";

    $this->assertEquals($expected_id, mysql5_column::get_full_definition($schema, $schema, $schema->table, $schema->table->column[0], true));
    $this->assertEquals($expected_foo, mysql5_column::get_full_definition($schema, $schema, $schema->table, $schema->table->column[1], true));
  }

  public function testEnum() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <type type="enum" name="test_enum">
    <enum name="alpha"/>
    <enum name="bravo"/>
    <enum name="charlie"/>
  </type>

  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="foo" type="test_enum"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    mysql5_type::get_creation_sql($schema, $schema->type);

    $expected = "`foo` ENUM('alpha','bravo','charlie')";

    $this->assertEquals($expected, mysql5_column::get_full_definition($schema, $schema, $schema->table, $schema->table->column, true));
  }

  public function testNullsAndDefaults() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="null_false" type="text" null="false"/>
    <column name="null_true" type="text" null="true"/>
    <column name="default_null_false" type="text" default="'xyz'" null="false"/>
    <column name="default_null_true" type="text" default="'xyz'" null="true"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $def = function ($col, $add_default, $include_null_def) use (&$schema) {
      return mysql5_column::get_full_definition($schema, $schema, $schema->table, $col, $add_default, $include_null_def);
    };

    $cols = $schema->table->column;

    // NOT NULL, no default with add_default = false, include_null_def = false
    $this->assertEquals("`null_false` text", $def($cols[0], false, false));

    // NOT NULL, no default with add_default = false, include_null_def = true
    $this->assertEquals("`null_false` text NOT NULL", $def($cols[0], false, true));

    // NOT NULL, no default with add_default = true, include_null_def = false
    $this->assertEquals("`null_false` text DEFAULT ''", $def($cols[0], true, false));

    // NOT NULL, no default with add_default = true, include_null_def = true
    $this->assertEquals("`null_false` text NOT NULL DEFAULT ''", $def($cols[0], true, true));



    // NOT NULL, default given with add_default = false, include_null_def = false
    $this->assertEquals("`default_null_false` text DEFAULT 'xyz'", $def($cols[2], false, false));

    // NOT NULL, default given with add_default = false, include_null_def = true
    $this->assertEquals("`default_null_false` text NOT NULL DEFAULT 'xyz'", $def($cols[2], false, true));

    // NOT NULL, default given with add_default = true, include_null_def = false
    $this->assertEquals("`default_null_false` text DEFAULT 'xyz'", $def($cols[2], true, false));

    // NOT NULL, default given with add_default = true, include_null_def = true
    $this->assertEquals("`default_null_false` text NOT NULL DEFAULT 'xyz'", $def($cols[2], true, true));



    // NULL, no default with add_default = false, include_null_def = false
    $this->assertEquals("`null_true` text", $def($cols[1], false, false));

    // NULL, no default with add_default = false, include_null_def = true
    $this->assertEquals("`null_true` text", $def($cols[1], false, true));

    // NULL, no default with add_default = true, include_null_def = false
    $this->assertEquals("`null_true` text", $def($cols[1], true, false));

    // NULL, no default with add_default = true, include_null_def = true
    $this->assertEquals("`null_true` text", $def($cols[1], true, true));



    // NULL, default given with add_default = false, include_null_def = false
    $this->assertEquals("`default_null_true` text DEFAULT 'xyz'", $def($cols[3], false, false));

    // NULL, default given with add_default = false, include_null_def = true
    $this->assertEquals("`default_null_true` text DEFAULT 'xyz'", $def($cols[3], false, true));

    //  NULL, default given with add_default = true, include_null_def = false
    $this->assertEquals("`default_null_true` text DEFAULT 'xyz'", $def($cols[3], true, false));

    // NULL, default given with add_default = true, include_null_def = true
    $this->assertEquals("`default_null_true` text DEFAULT 'xyz'", $def($cols[3], true, true));
  }

  public function testTypesAndDefaults() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="integer" type="integer" null="false"/>
    <column name="int" type="int" null="false"/>
    <column name="decimal" type="decimal" null="false"/>
    <column name="float" type="float" null="false"/>
    <column name="double" type="double" null="false"/>

    <column name="text" type="text" null="false"/>
    <column name="varchar80" type="varchar(80)" null="false"/>
    <column name="char" type="char" null="false"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $def = function ($col, $add_default, $include_null_def) use (&$schema) {
      return mysql5_column::get_full_definition($schema, $schema, $schema->table, $col, $add_default, $include_null_def);
    };

    for ( $i=0; $i<=4; $i++ ) {
      $col = $schema->table->column[$i];
      $this->assertEquals("`{$col['name']}` {$col['type']} NOT NULL DEFAULT 0", $def($col, true, true));
    }
    for ( $i=5; $i<=7; $i++ ) {
      $col = $schema->table->column[$i];
      $this->assertEquals("`{$col['name']}` {$col['type']} NOT NULL DEFAULT ''", $def($col, true, true));
    }
  }

  public function testSerials() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY">
    <column name="s1" type="serial"/>
    <column name="s2" type="bigserial" null="false"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected_s1 = "`s1` int AUTO_INCREMENT";
    $expected_s2 = "`s2` bigint NOT NULL AUTO_INCREMENT";

    $this->assertEquals($expected_s1, mysql5_column::get_full_definition($schema, $schema, $schema->table, $schema->table->column[0], true));
    $this->assertEquals($expected_s2, mysql5_column::get_full_definition($schema, $schema, $schema->table, $schema->table->column[1], true));
  }

  public function testForeignKeys() {
    $xml = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="test_id">
    <column name="test_id" type="serial"/>
    <column name="other_id"
      foreignSchema="public"
      foreignTable="other"
      foreignColumn="other_id"
      foreignKeyName="other_id_fk"
      foreignOnDelete="NO_ACTION"
      foreignOnUpdate="cascade"/>
  </table>
  <table name="other" primaryKey="other_id">
    <column name="other_id" type="serial"/>
  </table>
</schema>
</dbsteward>
XML;
    $dbs = new SimpleXMLElement($xml);

    $expected = "`other_id` int";
    $this->assertEquals($expected, mysql5_column::get_full_definition($dbs, $dbs->schema, $dbs->schema->table, $dbs->schema->table->column[1], true));
  }
}