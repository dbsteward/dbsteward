<?php
/**
 * DBSteward unit test for mysql5 constraint definition generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../../lib/DBSteward/sql_format/mysql5/mysql5_constraint.php';

class Mysql5ConstraintSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  public function testPrimaryKeys() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" primaryKeyName="id_pk">
    <column name="id" type="int"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $constraints = mysql5_constraint::get_table_constraints($schema, $schema, $schema->table);

    $this->assertEquals(1, count($constraints));

    $pk = $constraints[0];

    $expected = array(
      'name' => 'PRIMARY',
      'schema_name' => 'public',
      'table_name' => 'test',
      'type' => 'PRIMARY KEY',
      'definition' => '(`id`)'
    );

    $this->assertEquals($expected, $pk);

    $this->assertEquals("ALTER TABLE `test` ADD PRIMARY KEY (`id`);", mysql5_constraint::get_constraint_sql($pk));

    $this->assertEquals("ALTER TABLE `test` DROP PRIMARY KEY;", mysql5_constraint::get_constraint_drop_sql($pk));
  }

  public function testCompoundPrimaryKeys() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id,a" primaryKeyName="id_pk">
    <column name="id" type="int"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $constraints = mysql5_constraint::get_table_constraints($schema, $schema, $schema->table);

    $this->assertEquals(1, count($constraints));

    $pk = $constraints[0];

    $expected = array(
      'name' => 'PRIMARY',
      'schema_name' => 'public',
      'table_name' => 'test',
      'type' => 'PRIMARY KEY',
      'definition' => '(`id`, `a`)'
    );

    $this->assertEquals($expected, $pk);

    $this->assertEquals("ALTER TABLE `test` ADD PRIMARY KEY (`id`, `a`);", mysql5_constraint::get_constraint_sql($pk));

    $this->assertEquals("ALTER TABLE `test` DROP PRIMARY KEY;", mysql5_constraint::get_constraint_drop_sql($pk));
  }

  public function testInlineForeignKeys() {
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

    $constraints = mysql5_constraint::get_table_constraints($dbs, $dbs->schema, $dbs->schema->table[0]);

    // should contain primary key and foreign key
    $this->assertEquals(2, count($constraints));
    $this->assertEquals('PRIMARY KEY', $constraints[0]['type']);
    $this->assertEquals('FOREIGN KEY', $constraints[1]['type']);

    $fk = $constraints[1];

    $expected = array(
      'name' => 'other_id_fk',
      'schema_name' => 'public',
      'table_name' => 'test',
      'type' => 'FOREIGN KEY',
      'definition' => '(`other_id`) REFERENCES `other` (`other_id`)',
      'foreign_key_data' => array(
        'schema' => $dbs->schema,
        'table' => $dbs->schema->table[1],
        'column' => $dbs->schema->table[1]->column,
        'references' => '`other` (`other_id`)',
        'name' => 'other_id_fk'
      ),
      'foreignOnDelete' => 'NO_ACTION',
      'foreignOnUpdate' => 'CASCADE'
    );
    $this->assertEquals($expected, $fk);

    $expected = "ALTER TABLE `test` ADD FOREIGN KEY `other_id_fk` (`other_id`) REFERENCES `other` (`other_id`) ON DELETE NO ACTION ON UPDATE CASCADE;";
    $actual = mysql5_constraint::get_constraint_sql($fk);
    $this->assertEquals($expected, $actual);

    $this->assertEquals("ALTER TABLE `test` DROP FOREIGN KEY `other_id_fk`;", mysql5_constraint::get_constraint_drop_sql($fk));
  }

  public function testConstraintForeignKeys() {
    $xml = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="test_id">
    <column name="test_id" type="serial"/>
    <column name="other_id"/>
    <constraint name="other_id_fk" type="Foreign Key" definition="(`other_id`) REFERENCES `other` (`other_id`)"/>
  </table>
  <table name="other" primaryKey="other_id">
    <column name="other_id" type="serial"/>
  </table>
</schema>
</dbsteward>
XML;
    $dbs = new SimpleXMLElement($xml);
    $constraints = mysql5_constraint::get_table_constraints($dbs, $dbs->schema, $dbs->schema->table[0]);

    // should contain primary key and foreign key
    $this->assertEquals(2, count($constraints));
    $this->assertEquals('PRIMARY KEY', $constraints[0]['type']);
    $this->assertEquals('FOREIGN KEY', $constraints[1]['type']);

    $fk = $constraints[1];

    $expected = array(
      'name' => 'other_id_fk',
      'schema_name' => 'public',
      'table_name' => 'test',
      'type' => 'FOREIGN KEY',
      'definition' => '(`other_id`) REFERENCES `other` (`other_id`)'
    );
    $this->assertEquals($expected, $fk);

    $expected = "ALTER TABLE `test` ADD FOREIGN KEY `other_id_fk` (`other_id`) REFERENCES `other` (`other_id`);";
    $actual = mysql5_constraint::get_constraint_sql($fk);
    $this->assertEquals($expected, $actual);

    $this->assertEquals("ALTER TABLE `test` DROP FOREIGN KEY `other_id_fk`;", mysql5_constraint::get_constraint_drop_sql($fk));
  }

  public function testChecks() {
    $xml = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="test_id">
    <column name="test_id" type="serial"/>
    <constraint name="test_check" type="Check" definition="test_id = 2"/>
  </table>
</schema>
</dbsteward>
XML;
    $dbs = new SimpleXMLElement($xml);
    $constraints = mysql5_constraint::get_table_constraints($dbs, $dbs->schema, $dbs->schema->table[0]);

    // should contain primary key and check
    $this->assertEquals(2, count($constraints));
    $this->assertEquals('PRIMARY KEY', $constraints[0]['type']);
    $this->assertEquals('CHECK', $constraints[1]['type']);

    $check = $constraints[1];

    $expected = array(
      'name' => 'test_check',
      'schema_name' => 'public',
      'table_name' => 'test',
      'type' => 'CHECK',
      'definition' => 'test_id = 2'
    );
    $this->assertEquals($expected, $check);

    $expected = "-- Ignoring constraint 'test_check' on table 'test' because MySQL doesn't support the CHECK constraint";
    $this->assertEquals($expected, mysql5_constraint::get_constraint_sql($check));

    $expected = "-- Not dropping constraint 'test_check' on table 'test' because MySQL doesn't support the CHECK constraint";
    $this->assertEquals($expected, mysql5_constraint::get_constraint_drop_sql($check));
  }

  public function testUnique() {
    $xml = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="test_id">
    <column name="test_id" type="serial"/>
    <constraint name="test_unique" type="Unique" definition="(`test_id`)"/>
  </table>
</schema>
</dbsteward>
XML;
    $dbs = new SimpleXMLElement($xml);
    $constraints = mysql5_constraint::get_table_constraints($dbs, $dbs->schema, $dbs->schema->table[0]);

    // should contain primary key and unique
    $this->assertEquals(2, count($constraints));
    $this->assertEquals('PRIMARY KEY', $constraints[0]['type']);
    $this->assertEquals('UNIQUE', $constraints[1]['type']);

    $unique = $constraints[1];

    $expected = array(
      'name' => 'test_unique',
      'schema_name' => 'public',
      'table_name' => 'test',
      'type' => 'UNIQUE',
      'definition' => '(`test_id`)'
    );
    $this->assertEquals($expected, $unique);

    $expected = "ALTER TABLE `test` ADD UNIQUE INDEX `test_unique` (`test_id`);";
    $this->assertEquals($expected, mysql5_constraint::get_constraint_sql($unique));

    $expected = "ALTER TABLE `test` DROP INDEX `test_unique`;";
    $this->assertEquals($expected, mysql5_constraint::get_constraint_drop_sql($unique));
  }
}