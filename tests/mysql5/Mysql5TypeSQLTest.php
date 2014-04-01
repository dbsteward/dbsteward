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

/**
 * @group mysql5
 * @group nodb
 */
class Mysql5TypeSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
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
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected_sql = "-- found enum type enum_a. references to type enum_a will be replaced by ENUM('alpha','bravo','charlie')";
    $actual_sql = mysql5_type::get_creation_sql($schema, $schema->type);
    $this->assertEquals($expected_sql, $actual_sql);

    $expected_sql = "-- dropping enum type enum_a. references to type enum_a will be replaced with the type 'text'";
    $actual_sql = mysql5_type::get_drop_sql($schema, $schema->type);
    $this->assertEquals($expected_sql, $actual_sql);
  }

}
?>
