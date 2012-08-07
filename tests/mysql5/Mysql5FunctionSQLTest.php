<?php
/**
 * DBSteward unit test for mysql5 function sql generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

class Mysql5FunctionSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;

    $db_doc_xml = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
      <customRole>SOMEBODY</customRole>
    </role>
  </database>
</dbsteward>
XML;
    dbsteward::$new_database = new SimpleXMLElement($db_doc_xml);
  }

  public function testSupported() {
    $xml = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_fn" returns="text">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $this->assertTrue(mysql5_function::supported_language('sql'));
    $this->assertFalse(mysql5_function::supported_language('tsql'));
    $this->assertTrue(mysql5_function::has_definition($schema->function));

    $schema->function->functionDefinition['language'] = 'tsql';
    $this->assertFalse(mysql5_function::has_definition($schema->function));

    $schema->function->functionDefinition['language'] = 'sql';
    $schema->function->functionDefinition['sqlFormat'] = 'pgsql8';
    $this->assertFalse(mysql5_function::has_definition($schema->function));
  }

  public function testSimple() {
    $xml = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_fn" returns="text">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;
    
    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);
  }

  public function testCachePolicy() {
    $xml = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_fn" returns="text" cachePolicy="IMMUTABLE">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
NO SQL
DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;
    
    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);

    $schema->function['cachePolicy'] = "STABLE";

    $expected = <<<SQL
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
READS SQL DATA
DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;

    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);

    $schema->function['cachePolicy'] = "VOLATILE";

    $expected = <<<SQL
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;

    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);
  }

  public function testOwner() {
    $xml = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_fn" returns="text" owner="SOMEBODY">
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
CREATE DEFINER = SOMEBODY FUNCTION `test_fn` ()
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;
    
    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);
  }

  public function testDefiner() {
    $xml = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_fn" returns="text" securityDefiner="true">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY DEFINER
RETURN 'xyz';
SQL;
    
    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);
  }

  public function testDrop() {
    $xml = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_fn" returns="text" securityDefiner="true">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = "DROP FUNCTION IF EXISTS `test_fn`;";
    
    $actual = trim(mysql5_function::get_drop_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);
  }

  public function testOtherFormats() {
    $xml = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_fn" returns="text" securityDefiner="true">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition language="tsql" sqlFormat="mssql10">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);

    $this->assertEquals("-- Not dropping function 'test_fn' - no definitions for mysql5",trim(mysql5_function::get_drop_sql($schema, $schema->function)));

    try {
      mysql5_function::get_creation_sql($schema, $schema->function);
    }
    catch ( Exception $ex ) {
      if ( stripos($ex->getMessage(), 'no function definitions in a known language for format mysql5') === false ) {
        throw $ex;
      }

      $xml = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_fn" returns="text" securityDefiner="true">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;

      $schema = new SimpleXMLElement($xml);

      try {
        mysql5_function::get_creation_sql($schema, $schema->function);
      }
      catch ( Exception $ex ) {
        if ( stripos($ex->getMessage(), 'duplicate function definition for mysql5/sql') === false ) {
          throw $ex;
        }
        return;
      }
      $this->fail('Expected exception for duplicate function definitions');
    }
    $this->fail('Expected exception for no function definitions');
  }
}