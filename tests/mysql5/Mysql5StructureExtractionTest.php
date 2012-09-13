<?php
/**
 * DBSteward database structure extraction tests
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/../structureExtractionTestBase.php';

class Mysql5StructureExtractionTest extends structureExtractionTestBase {

  protected function setUp() {
    $this->xml_content_a = <<<XML
<dbsteward>
  <database>
    <host>127.0.0.1</host>
    <name>dbsteward_phpunit</name>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <function name="test_concat" returns="text" owner="ROLE_OWNER" cachePolicy="VOLATILE" description="a test function that concats strings">
      <functionParameter name="param1" type="text"/>
      <functionParameter name="param2" type="text"/>
      <functionDefinition language="sql" sqlFormat="mysql5">
        RETURN CONCAT(param1, param2);
      </functionDefinition>
    </function>
    <table name="rate" owner="ROLE_OWNER" primaryKey="rate_id">
      <column name="rate_id" type="int(11)" null="false"/>
      <column name="rate_group_id" foreignSchema="public" foreignTable="rate_group" foreignColumn="rate_group_id" null="false"/>
      <column name="rate_name" type="varchar(120)"/>
      <column name="rate_value" type="decimal(10,0)"/>
    </table>
    <table name="rate_group" owner="ROLE_OWNER" primaryKey="rate_group_id">
      <column name="rate_group_id" type="int(11)" null="false"/>
      <column name="rate_group_name" type="varchar(100)"/>
      <column name="rate_group_enabled" type="tinyint(1)" null="false" default="1"/>
    </table>
  </schema>
</dbsteward>
XML;

    parent::setUp();
  }

  protected function build_db() {
    $this->build_db_mysql5();
  }

  protected function get_connection() {
    return $this->mysql;
  }

  protected function apply_options() {
    $this->apply_options_mysql5();
  }
}

?>
