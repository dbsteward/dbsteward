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

class WarnOnOverriddenRoleTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    $this->testHandler = new Monolog\Handler\TestHandler;
    dbsteward::get_logger()->pushHandler($this->testHandler);

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
  public function tearDown() {
    dbsteward::get_logger()->popHandler();
  }

  /**
   * @group pgsql8
   * @group mysql5
   * @group mssql10
   */
  public function testDefaultValue() {
    $this->assertFalse(dbsteward::$ignore_custom_roles);
  }

  /**
   * @group pgsql8
   * @group mysql5
   * @group mssql10
   */
  public function testWarningWhenTrue() {
    dbsteward::$ignore_custom_roles = TRUE;

    $role = xml_parser::role_enum($this->dbdoc, 'custom');
    $this->assertEquals('custom', $role);

    $role = xml_parser::role_enum($this->dbdoc, 'invalid');
    $this->assertEquals('deployment', $role);

    $this->assertLogged(Monolog\Logger::WARNING, "Warning: Ignoring custom roles. Role 'invalid' is being overridden by ROLE_OWNER ('deployment').");
  }

  /**
   * @group pgsql8
   * @group mysql5
   * @group mssql10
   */
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

  private function assertLogged($level, $message) {
    $this->assertTrue($this->testHandler->hasRecordThatContains($message, $level));
  }
}
