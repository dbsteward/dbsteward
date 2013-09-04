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

/**
 * @group mysql5
 */
class Mysql5ConstraintSQLTest extends PHPUnit_Framework_TestCase {

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

    $expected = "ALTER TABLE `test` ADD CONSTRAINT `other_id_fk` FOREIGN KEY `other_id_fk` (`other_id`) REFERENCES `other` (`other_id`) ON DELETE NO ACTION ON UPDATE CASCADE;";
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

    $expected = "ALTER TABLE `test` ADD CONSTRAINT `other_id_fk` FOREIGN KEY `other_id_fk` (`other_id`) REFERENCES `other` (`other_id`);";
    $actual = mysql5_constraint::get_constraint_sql($fk);
    $this->assertEquals($expected, $actual);

    $this->assertEquals("ALTER TABLE `test` DROP FOREIGN KEY `other_id_fk`;", mysql5_constraint::get_constraint_drop_sql($fk));
  }

  /** Because MySQL, that's why */
  public function testInlineForeignKeyIndexName() {
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
      foreignIndexName="fk_idx"
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
      'foreignIndexName' => 'fk_idx',
      'foreignOnDelete' => 'NO_ACTION',
      'foreignOnUpdate' => 'CASCADE'
    );
    $this->assertEquals($expected, $fk);

    $expected = "ALTER TABLE `test` ADD CONSTRAINT `other_id_fk` FOREIGN KEY `fk_idx` (`other_id`) REFERENCES `other` (`other_id`) ON DELETE NO ACTION ON UPDATE CASCADE;";
    $actual = mysql5_constraint::get_constraint_sql($fk);
    $this->assertEquals($expected, $actual);

    $this->assertEquals("ALTER TABLE `test` DROP FOREIGN KEY `other_id_fk`;", mysql5_constraint::get_constraint_drop_sql($fk));
  }

  public function testCyclicForeignKeys() {
    $xml = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test_self" primaryKey="test_id">
    <column name="test_id" type="serial"/>
    <column name="other_id"
      foreignSchema="public"
      foreignTable="test_self"
      foreignColumn="test_id"
      foreignKeyName="test_id_fk"
      foreignOnDelete="NO_ACTION"
      foreignOnUpdate="cascade"/>
  </table>
  <table name="test1" primaryKey="test_id">
    <column name="test_id" type="serial"/>
    <column name="other_id"
      foreignSchema="public"
      foreignTable="test2"
      foreignColumn="other_id"
      foreignKeyName="test_id_fk"
      foreignOnDelete="NO_ACTION"
      foreignOnUpdate="cascade"/>
  </table>
  <table name="test2" primaryKey="test_id">
    <column name="test_id" type="serial"/>
    <column name="other_id"
      foreignSchema="public"
      foreignTable="test3"
      foreignColumn="other_id"
      foreignKeyName="test_id_fk"
      foreignOnDelete="NO_ACTION"
      foreignOnUpdate="cascade"/>
  </table>
  <table name="test3" primaryKey="test_id">
    <column name="test_id" type="serial"/>
    <column name="other_id"
      foreignSchema="public"
      foreignTable="test1"
      foreignColumn="other_id"
      foreignKeyName="test_id_fk"
      foreignOnDelete="NO_ACTION"
      foreignOnUpdate="cascade"/>
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
      'name' => 'test_id_fk',
      'schema_name' => 'public',
      'table_name' => 'test_self',
      'type' => 'FOREIGN KEY',
      'definition' => '(`other_id`) REFERENCES `test_self` (`test_id`)',
      'foreign_key_data' => array(
        'schema' => $dbs->schema,
        'table' => $dbs->schema->table[0],
        'column' => $dbs->schema->table[0]->column,
        'references' => '`test_self` (`test_id`)',
        'name' => 'test_id_fk'
      ),
      'foreignOnDelete' => 'NO_ACTION',
      'foreignOnUpdate' => 'CASCADE'
    );
    $this->assertEquals($expected, $fk);

    $expected = "ALTER TABLE `test_self` ADD CONSTRAINT `test_id_fk` FOREIGN KEY `test_id_fk` (`other_id`) REFERENCES `test_self` (`test_id`) ON DELETE NO ACTION ON UPDATE CASCADE;";
    $actual = mysql5_constraint::get_constraint_sql($fk);
    $this->assertEquals($expected, $actual);

    $this->assertEquals("ALTER TABLE `test_self` DROP FOREIGN KEY `test_id_fk`;", mysql5_constraint::get_constraint_drop_sql($fk));

    $dbs->schema->table[0]->column[1]['foreignColumn'] = 'other_id';

    try {
      // FK pointing to self
      mysql5_constraint::get_table_constraints($dbs, $dbs->schema, $dbs->schema->table[0]);
    }
    catch ( Exception $ex ) {
      $this->assertEquals("Foreign key cyclic dependency detected! Local column `test_self`.`other_id` pointing to foreign column `test_self`.`other_id`", $ex->getMessage());
    }

    try {
      // FK 1 pointing to FK 2 pointing to FK 3 pointing to FK 1
      mysql5_constraint::get_table_constraints($dbs, $dbs->schema, $dbs->schema->table[1]);
    }
    catch ( Exception $ex ) {
      $this->assertEquals("Foreign key cyclic dependency detected! Local column `test1`.`other_id` pointing to foreign column `test2`.`other_id`", $ex->getMessage());
    }
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
?>
