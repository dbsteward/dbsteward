<?php
/**
 * DBSteward unit test for pgsql8 type diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Adam Jette <jettea46@yahoo.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 * @group nodb
 */
class Pgsql8TypeDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  public function testChange() {
    $old = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_arch_type_in_return" returns="test.arch_type">
    <functionDefinition language="plpgsql" sqlFormat="pgsql8">
      BEGIN
        RETURN 1;
      END
    </functionDefinition>
  </function>
  <function name="test_arch_type_in_param" returns="bigint">
    <functionParameter name="testparam" type="test.arch_type" />
    <functionDefinition language="plpgsql" sqlFormat="pgsql8">
      BEGIN
        RETURN 1;
      END
    </functionDefinition>
  </function>
  <type name="arch_type" type="composite">
    <typeCompositeElement name="uh_phrasing" type="text"/>
    <typeCompositeElement name="boom_phrasing" type="text"/>
  </type>
</schema>
XML;
    $new = <<<XML
<schema name="test" owner="ROLE_OWNER">
  <function name="test_arch_type_in_return" returns="test.arch_type">
    <functionDefinition language="plpgsql" sqlFormat="pgsql8">
      BEGIN
        RETURN 1;
      END
    </functionDefinition>
  </function>
  <function name="test_arch_type_in_param" returns="bigint">
    <functionParameter name="testparam" type="test.arch_type" />
    <functionDefinition language="plpgsql" sqlFormat="pgsql8">
      BEGIN
        RETURN 1;
      END
    </functionDefinition>
  </function>
  <type name="arch_type" type="composite">
    <typeCompositeElement name="uh_phrasing" type="text"/>
    <typeCompositeElement name="boom_phrasing" type="text"/>
    <typeCompositeElement name="ummmm_phrasing" type="text"/>
  </type>
</schema>
XML;
    
    // just change the name. there shouldn't be any ddl generated, except a note that it was "dropped" and "recreated"
    $this->common($old, $new, 
'-- type arch_type definition migration (1/6): dependent functions return/parameter type alteration
DROP FUNCTION IF EXISTS "test"."test_arch_type_in_return"();
DROP FUNCTION IF EXISTS "test"."test_arch_type_in_param"(testparam test.arch_type);

-- type arch_type definition migration (2/6): dependent tables column type alteration

-- type arch_type definition migration (3/6): drop old type
DROP TYPE "test"."arch_type";

-- type arch_type definition migration (4/6): recreate type with new definition
CREATE TYPE "test"."arch_type" AS (
  uh_phrasing text,
  boom_phrasing text,
  ummmm_phrasing text
);

-- type arch_type definition migration (5/6): dependent functions return/parameter type with new definition
CREATE OR REPLACE FUNCTION "test"."test_arch_type_in_return"() RETURNS test.arch_type
  AS $_$

      BEGIN
        RETURN 1;
      END' . "\n    \n\t" . '$_$
LANGUAGE plpgsql; -- DBSTEWARD_FUNCTION_DEFINITION_END

CREATE OR REPLACE FUNCTION "test"."test_arch_type_in_param"(testparam test.arch_type) RETURNS bigint
  AS $_$

      BEGIN
        RETURN 1;
      END' . "\n    \n\t" . '$_$
LANGUAGE plpgsql; -- DBSTEWARD_FUNCTION_DEFINITION_END



-- type arch_type definition migration (6/6): dependent tables type restoration');
  }

  private function common($xml_a, $xml_b, $expected, $message = NULL) {
    $schema_a = new SimpleXMLElement($xml_a);
    $schema_b = new SimpleXMLElement($xml_b);

    $ofs = new mock_output_file_segmenter();

    pgsql8_diff_types::apply_changes($ofs, $schema_a, $schema_b);

    $actual = trim($ofs->_get_output());

    $this->assertEquals($expected, $actual, $message);
  }

}
