<?php
/**
 * DBSteward unit test for mysql5 function diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

require_once __DIR__ . '/../mock_output_file_segmenter.php';

class Mysql5FunctionDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;

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
    
    dbsteward::$old_database = new SimpleXMLElement($db_doc_xml);
    dbsteward::$new_database = new SimpleXMLElement($db_doc_xml);
  }

  private $xml_0 = <<<XML
<schema name="public" owner="ROLE_OWNER">
</schema>
XML;

  private $xml_1 = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function name="fn_a" owner="ROLE_OWNER" returns="text">
    <functionParameter name="arg0" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;

  private $xml_1_force = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function name="fn_a" owner="ROLE_OWNER" returns="text" forceRedefine="true">
    <functionParameter name="arg0" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;

  private $xml_1_own = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function name="fn_a" owner="SOMEBODY" returns="text">
    <functionParameter name="arg0" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;

  private $xml_1_argn = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function name="fn_a" owner="ROLE_OWNER" returns="text">
    <functionParameter name="a" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;

  private $xml_1_argt = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function name="fn_a" owner="ROLE_OWNER" returns="text">
    <functionParameter name="arg0" type="int"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;
  
  private $xml_1_ret = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function name="fn_a" owner="ROLE_OWNER" returns="int">
    <functionParameter name="arg0" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
</schema>
XML;

  private $xml_1_def = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function name="fn_a" owner="ROLE_OWNER" returns="text">
    <functionParameter name="arg0" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN '123';
    </functionDefinition>
  </function>
</schema>
XML;

  private $xml_3 = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function name="fn_a" owner="ROLE_OWNER" returns="text">
    <functionParameter name="arg0" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'xyz';
    </functionDefinition>
  </function>
  <function name="fn_b" owner="ROLE_OWNER" returns="text">
    <functionParameter name="arg0" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'abc';
    </functionDefinition>
  </function>
  <function name="fn_c" owner="SOMEBODY" returns="text">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN '123';
    </functionDefinition>
  </function>
</schema>
XML;

  private $xml_3_alt = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <function name="fn_a" owner="ROLE_OWNER" returns="text">
    <functionParameter name="arg0" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN '123';
    </functionDefinition>
  </function>
  <function name="fn_b" owner="ROLE_OWNER" returns="text">
    <functionParameter name="arg0" type="text"/>
    <functionParameter name="arg1" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN 'abc';
    </functionDefinition>
  </function>
  <function name="fn_c" owner="SOMEBODY" returns="text">
    <functionParameter name="a" type="text"/>
    <functionParameter name="b" type="text"/>
    <functionDefinition language="sql" sqlFormat="mysql5">
      RETURN '123';
    </functionDefinition>
  </function>
</schema>
XML;

  private $create_fn_a = <<<SQL
DROP FUNCTION IF EXISTS `fn_a`;
CREATE DEFINER = the_owner FUNCTION `fn_a` (`arg0` text, `arg1` text)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;
  private $create_fn_a_own = <<<SQL
DROP FUNCTION IF EXISTS `fn_a`;
CREATE DEFINER = SOMEBODY FUNCTION `fn_a` (`arg0` text, `arg1` text)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;
  private $create_fn_a_argn = <<<SQL
DROP FUNCTION IF EXISTS `fn_a`;
CREATE DEFINER = the_owner FUNCTION `fn_a` (`a` text, `arg1` text)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;
  private $create_fn_a_argt = <<<SQL
DROP FUNCTION IF EXISTS `fn_a`;
CREATE DEFINER = the_owner FUNCTION `fn_a` (`arg0` int, `arg1` text)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;
  private $create_fn_a_ret = <<<SQL
DROP FUNCTION IF EXISTS `fn_a`;
CREATE DEFINER = the_owner FUNCTION `fn_a` (`arg0` text, `arg1` text)
RETURNS int
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz';
SQL;
  private $create_fn_a_def = <<<SQL
DROP FUNCTION IF EXISTS `fn_a`;
CREATE DEFINER = the_owner FUNCTION `fn_a` (`arg0` text, `arg1` text)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN '123';
SQL;
  private $drop_fn_a = <<<SQL
DROP FUNCTION IF EXISTS `fn_a`;
SQL;
  private $create_fn_b = <<<SQL
DROP FUNCTION IF EXISTS `fn_b`;
CREATE DEFINER = the_owner FUNCTION `fn_b` (`arg0` text, `arg1` text)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'abc';
SQL;
  private $drop_fn_b = <<<SQL
DROP FUNCTION IF EXISTS `fn_b`;
SQL;
  private $create_fn_c = <<<SQL
DROP FUNCTION IF EXISTS `fn_c`;
CREATE DEFINER = SOMEBODY FUNCTION `fn_c` (`a` text, `b` text)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN '123';
SQL;
  private $drop_fn_c = <<<SQL
DROP FUNCTION IF EXISTS `fn_c`;
SQL;

  public function testNoneToNone() {
    $this->common($this->xml_0, $this->xml_0, '', '');
  }

  public function testSameToSame() {
    $this->common($this->xml_1, $this->xml_1, '', '');
  }

  public function testForceRedefine() {
    $this->common($this->xml_1, $this->xml_1_force, $this->create_fn_a, '', 'no force -> force');
    $this->common($this->xml_1_force, $this->xml_1, '', '', 'force -> no force');
  }

  public function testAddNew() {
    $this->common($this->xml_0, $this->xml_1, $this->create_fn_a, '');
  }

  public function testAddSome() {
    $this->common($this->xml_1, $this->xml_3, "$this->create_fn_b\n$this->create_fn_c", '');
  }

  public function testDropAll() {
    $this->common($this->xml_1, $this->xml_0, '', $this->drop_fn_a);
  }

  public function testDropSome() {
    $this->common($this->xml_3, $this->xml_1, '', "$this->drop_fn_b\n$this->drop_fn_c");
  }

  public function testChangeOne() {
    $this->common($this->xml_1, $this->xml_1_own, $this->create_fn_a_own, '', "change owner");
    $this->common($this->xml_1, $this->xml_1_argn, $this->create_fn_a_argn, '', "change arg name");
    $this->common($this->xml_1, $this->xml_1_argt, $this->create_fn_a_argt, '', "change arg type");
    $this->common($this->xml_1, $this->xml_1_ret, $this->create_fn_a_ret, '', "change return type");
    $this->common($this->xml_1, $this->xml_1_def, $this->create_fn_a_def, '', "change definition");
  }

  public function testAddSomeAndChange() {
    $this->common($this->xml_1, $this->xml_3_alt, "$this->create_fn_a_def\n$this->create_fn_b\n$this->create_fn_c", '');
  }

  public function testDropSomeAndChange() {
    $this->common($this->xml_3_alt, $this->xml_1, "$this->create_fn_a", "$this->drop_fn_b\n$this->drop_fn_c");
  }

  protected function common($xml_a, $xml_b, $expected1, $expected3, $message = '') {
    $schema_a = new SimpleXMLElement($xml_a);
    $schema_b = new SimpleXMLElement($xml_b);

    $ofs1 = new mock_output_file_segmenter();
    $ofs3 = new mock_output_file_segmenter();

    mysql5_diff_functions::diff_functions($ofs1, $ofs3, $schema_a, $schema_b);

    $actual1 = trim(preg_replace('/--.*(\n\s*)?/','',$ofs1->_get_output()));
    $actual3 = trim(preg_replace('/--.*(\n\s*)?/','',$ofs3->_get_output()));

    $this->assertEquals($expected1, $actual1, "in stage 1: $message");
    $this->assertEquals($expected3, $actual3, "in stage 3: $message");
  }
}