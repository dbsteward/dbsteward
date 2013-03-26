<?php
/**
 * DBSteward unit test for mysql5 database generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

class Mysql5SchemaSQLTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_all_names = true;
  }

  public function testCreation() {
    $schema = new SimpleXMLElement('<schema name="foo" owner="ROLE_OWNER" comment="this is a comment"></schema>');

    // MySQL doesn't allow comments on its databases
    $expected = "CREATE DATABASE IF NOT EXISTS `foo`;";
    $actual = trim(mysql5_schema::get_creation_sql($schema));

    $this->assertEquals($expected, $actual);
  }

  public function testDeletion() {
    $schema = new SimpleXMLElement('<schema name="foo" owner="ROLE_OWNER" comment="this is a comment"></schema>');
    
    $expected = "DROP DATABASE IF EXISTS `foo`;";
    $actual = trim(mysql5_schema::get_drop_sql($schema));

    $this->assertEquals($expected, $actual);
  }
}
?>