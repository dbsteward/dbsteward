<?php
/**
 * DBSteward unit test for mysql5 tableOption DDL generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

class Mysql5TableOptionsSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  public function testTableOptions() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="auto_increment" value="5"/>
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <tableOption sqlFormat="pgsql8" name="inherits" value="other"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);

    mysql5::$use_auto_increment_table_options = TRUE;

    $expected = <<<SQL
AUTO_INCREMENT=5
ENGINE=InnoDB
SQL;
    
    $actual = mysql5_table::get_table_options_sql(mysql5_table::get_table_options($schema, $schema->table));
    $this->assertEquals($expected, $actual);

    mysql5::$use_auto_increment_table_options = FALSE;

        $expected = <<<SQL
ENGINE=InnoDB
SQL;
    
    $actual = mysql5_table::get_table_options_sql(mysql5_table::get_table_options($schema, $schema->table));
    $this->assertEquals($expected, $actual);
  }

  public function testCreateTable() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="id" owner="NOBODY" description="test description">
    <tableOption sqlFormat="mysql5" name="auto_increment" value="5"/>
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <tableOption sqlFormat="pgsql8" name="inherits" value="other"/>
    <column name="id" type="int"/>
    <column name="foo" type="int"/>
  </table>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);


    mysql5::$use_auto_increment_table_options = TRUE;

    $expected = <<<SQL
CREATE TABLE `public`.`test` (
  `id` int,
  `foo` int
)
AUTO_INCREMENT=5
ENGINE=InnoDB
COMMENT 'test description';
SQL;
    $actual = mysql5_table::get_creation_sql($schema, $schema->table);
    $this->assertEquals($expected, $actual);

    mysql5::$use_auto_increment_table_options = FALSE;

    $expected = <<<SQL
CREATE TABLE `public`.`test` (
  `id` int,
  `foo` int
)
ENGINE=InnoDB
COMMENT 'test description';
SQL;
    $actual = mysql5_table::get_creation_sql($schema, $schema->table);
    $this->assertEquals($expected, $actual);
  }

  public function testNone() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);
    $this->assertEquals('', mysql5_table::get_table_options_sql(mysql5_table::get_table_options($schema, $schema->table)));
  }

  public function testDuplicates() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="opt_a"/>
    <tableOption sqlFormat="mysql5" name="opt_b"/>
    <tableOption sqlFormat="pgsql8" name="opt_b"/>
    <tableOption sqlFormat="mysql5" name="OpT_A"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);
    
    try {
      mysql5_table::get_table_options_sql(mysql5_table::get_table_options($schema, $schema->table));
    }
    catch (Exception $ex) {
      if (strcasecmp($ex->getMessage(), "Duplicate tableOption 'opt_a' in table public.test is not allowed") !== 0) {
        throw $ex;
      }
      return;
    }
    $this->fail("Was expecting duplicate tableOption exception, got nothing");
  }

  public function testEmptyName() {
    $xml = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="" value="5"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);
    
    try {
      mysql5_table::get_table_options_sql(mysql5_table::get_table_options($schema, $schema->table));
    }
    catch (Exception $ex) {
      if (strcasecmp($ex->getMessage(), "tableOption of table public.test cannot have an empty name") !== 0) {
        throw $ex;
      }
      return;
    }
    $this->fail("Was expecting empty tableOption name exception, got nothing");
  }
}
