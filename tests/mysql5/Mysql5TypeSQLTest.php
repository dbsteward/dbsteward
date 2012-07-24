<?php
/**
 * DBSteward unit test for mysql5 type sql generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../../lib/DBSteward/sql_format/mysql5/mysql5_type.php';

class Mysql5TypeSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;

    mysql5_type::clear_registered_enums();
  }

  public function testInvalid() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <type type="set"/>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    try {
      mysql5_type::get_creation_sql($schema, $schema->type);
    }
    catch ( Exception $ex ) {
      if ( stripos($ex->getMessage(), 'unknown type') === FALSE ) {
        throw $ex;
      }
    }

    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <type type="enum" name="test_enum"/>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    try {
      mysql5_type::get_creation_sql($schema, $schema->type);
    }
    catch ( Exception $ex ) {
      if ( stripos($ex->getMessage(), 'contains no enum children') === FALSE ) {
        throw $ex;
      }
    }
  }

  public function testValid() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <type type="enum" name="enum_a">
    <enum name="alpha"/>
    <enum name="bravo"/>
    <enum name="charlie"/>
  </type>
  <type type="enum" name="enum_b">
    <enum name="apple"/>
    <enum name="banana"/>
    <enum name="carrot"/>
  </type>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);
    $expected_sql = "-- found enum type enum_a. references to this type will be replaced with the MySQL-compliant ENUM expression\n-- found enum type enum_b. references to this type will be replaced with the MySQL-compliant ENUM expression\n";
    $expected_array = array(
      'enum_a' => array('alpha','bravo','charlie'),
      'enum_b' => array('apple','banana','carrot')
    );

    $actual_sql = mysql5_type::get_creation_sql($schema, $schema->type[0]);
    $actual_sql.= mysql5_type::get_creation_sql($schema, $schema->type[1]);

    $actual_array = mysql5_type::get_enum_values();

    $this->assertEquals($expected_sql, $actual_sql);
    $this->assertEquals($expected_array, $actual_array);
  }

}