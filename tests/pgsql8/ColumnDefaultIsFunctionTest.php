<?php

require_once dirname(__FILE__) . '/../dbstewardUnitTestBase.php';

class ColumnDefaultIsFunctionTest extends dbstewardUnitTestBase
{
  
  /**
   * Tests that functions referenced as default values for columns
   * 1) do not result in build failures
   * 2) generate sane SQL on build
   */
  public function testBuildColumnSQL() {
    $xml = <<<XML
<dbsteward>
  <database>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
  </database>

  <schema name="dbsteward" owner="ROLE_OWNER">
    <function name="test" returns="integer" owner="ROLE_OWNER" cachePolicy="VOLATILE" description="always returns 5, is a test function">
      <functionDefinition language="plpgsql" sqlFormat="pgsql8">
        BEGIN
          RETURN 5;
        END
      </functionDefinition>
    </function>
  </schema>
  <schema name="hotel" owner="ROLE_OWNER">
    <table name="rate" owner="ROLE_OWNER" primaryKey="rate_id" primaryKeyName="rate_pkey">
      <tableOption sqlFormat="pgsql8" name="with" value="(oids=false)"/>
      <column name="rate_id" type="integer" null="false"/>
      <column name="rate_group_id" null="false" foreignSchema="hotel" foreignTable="rate_group" foreignColumn="rate_group_id" foreignKeyName="rate_rate_group_id_fkey" foreignOnUpdate="NO_ACTION" foreignOnDelete="NO_ACTION"/>
      <column name="rate_name" type="character varying(120)"/>
      <column name="rate_value" type="numeric"/>
    </table>
    <table name="rate_group" owner="ROLE_OWNER" primaryKey="rate_group_id" primaryKeyName="rate_group_pkey">
      <tableOption sqlFormat="pgsql8" name="with" value="(oids=false)"/>
      <column name="rate_group_id" type="integer" null="false" default="dbsteward.test()"/>
      <column name="rate_group_name" type="character varying(100)"/>
      <column name="rate_group_enabled" type="boolean" null="false" default="true"/>
    </table>
  </schema>            
</dbsteward>    
XML;
    
    $expected = <<<EXP
ALTER TABLE hotel.rate ALTER COLUMN rate_id SET NOT NULL;
ALTER TABLE hotel.rate ALTER COLUMN rate_group_id SET NOT NULL;
ALTER TABLE hotel.rate_group ALTER COLUMN rate_group_id SET DEFAULT dbsteward.test();
ALTER TABLE hotel.rate_group ALTER COLUMN rate_group_id SET NOT NULL;
ALTER TABLE hotel.rate_group ALTER COLUMN rate_group_enabled SET DEFAULT true;
ALTER TABLE hotel.rate_group ALTER COLUMN rate_group_enabled SET NOT NULL;
EXP;
    
    $this->set_xml_content_a($xml);
    $this->build_db('pgsql8');
    $actual = file_get_contents(dirname(__FILE__) . '/../testdata/unit_test_xml_a_build.sql');
    $this->assertTrue(stripos($actual, $expected) !== FALSE);
  }
}
