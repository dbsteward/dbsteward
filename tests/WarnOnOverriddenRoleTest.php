<?php
/**
 * Tests ticket "Warn when a role is being overridden as owner because of ignore_custom_roles flag"
 * 1) Emit a warning when dbsteward::$ignore_custom_roles = TRUE and the ignoring is happening
 * 2) add a command line switch to control dbsteward::$ignore_custom_roles
 * 3) default dbsteward::$ignore_custom_roles to FALSE
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/../lib/DBSteward/dbsteward.php';

include_once 'PHPUnit/Extensions/OutputTestCase.php';
if (!class_exists('PHPUnit_Extensions_OutputTestCase')) {
  class_alias('PHPUnit_Framework_TestCase', 'PHPUnit_Extensions_OutputTestCase', FALSE);
}

class WarnOnOverriddenRoleTest extends PHPUnit_Extensions_OutputTestCase {
  public function setUp() {
    // format doesn't really matter
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;

    $xml = <<<XML
<dbsteward>
  <database>
    <host>db-host</host>
    <name>dbsteward</name>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
      <customRole>custom</customRole>
    </role>
  </database>
</dbsteward>
XML;
    $this->dbdoc = new SimpleXMLElement($xml);
  }

  public function testDefaultValue() {
    $this->assertFalse(dbsteward::$ignore_custom_roles);
  }

  public function testWarningWhenTrue() {
    dbsteward::$ignore_custom_roles = TRUE;

    $role = xml_parser::role_enum($this->dbdoc, 'custom');
    $this->assertEquals('custom', $role);

    $role = xml_parser::role_enum($this->dbdoc, 'invalid');
    $this->assertEquals('deployment', $role);

    $this->expectOutputString("[DBSteward-1] Warning: Ignoring custom roles. Role 'invalid' is being overridden by ROLE_OWNER ('deployment').\n");
  }

  public function testThrowWhenFalse() {
    dbsteward::$ignore_custom_roles = FALSE;

    try {
      xml_parser::role_enum($this->dbdoc, 'invalid');
    }
    catch (Exception $ex) {
      $this->assertEquals('Failed to confirm custom role: invalid', $ex->getMessage());
      return;
    }
    $this->fail("Expected exception when not ignoring custom roles");
  }
}
?>
