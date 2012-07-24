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
require_once __DIR__ . '/../../lib/DBSteward/sql_format/mysql5/mysql5_function.php';

class Mysql5FunctionSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
  }

  public function testSupported() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <function name="test_fn" returns="text" language="sql">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition>
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $this->assertTrue(dbsteward::supported_function_language($schema->function));
  }

  public function testSimple() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <function name="test_fn" returns="text" language="sql">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition>
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
DELIMITER \$_\$
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';\$_\$
DELIMITER ;
SQL;
    
    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);
  }

  public function testCachePolicy() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <function name="test_fn" returns="text" language="sql" cachePolicy="IMMUTABLE">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition>
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
DELIMITER \$_\$
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
NO SQL
DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';\$_\$
DELIMITER ;
SQL;
    
    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);

    $schema->function['cachePolicy'] = "STABLE";

    $expected = <<<SQL
DELIMITER \$_\$
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
READS SQL DATA
DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';\$_\$
DELIMITER ;
SQL;

    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);

    $schema->function['cachePolicy'] = "VOLATILE";

    $expected = <<<SQL
DELIMITER \$_\$
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';\$_\$
DELIMITER ;
SQL;

    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);
  }

  public function testOwner() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <function name="test_fn" returns="text" language="sql" owner="SOMEBODY">
    <functionDefinition>
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
DELIMITER \$_\$
CREATE DEFINER = SOMEBODY FUNCTION `test_fn` ()
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';\$_\$
DELIMITER ;
SQL;
    
    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);
  }

  public function testDefiner() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <function name="test_fn" returns="text" language="sql" securityDefiner="true">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition>
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $expected = <<<SQL
DELIMITER \$_\$
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY DEFINER
RETURN 'xyz';\$_\$
DELIMITER ;
SQL;
    
    $actual = trim(mysql5_function::get_creation_sql($schema, $schema->function));

    $this->assertEquals($expected, $actual);
  }

  public function testDrop() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <function name="test_fn" returns="text" language="sql" securityDefiner="true">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="int"/>
    <functionParameter name="c" type="date"/>
    <functionDefinition>
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
}