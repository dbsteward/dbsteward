<?php
/**
 * DBSteward database structure extraction tests
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/structureExtractionTestBase.php';

class Pgsql8StructureExtractionTest extends structureExtractionTestBase {

  protected function setUp() {
    $this->xml_content_a = <<<XML
<dbsteward>
  <database>
    <host>db-host</host>
    <name>dbsteward</name>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
    <slony>
      <masterNode id="1"/>
      <replicaNode id="2" providerId="1"/>
      <replicaNode id="3" providerId="2"/>
      <replicationSet id="1"/>
      <replicationUpgradeSet id="2"/>
    </slony>
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
  <language name="plpgsql" procedural="true" owner="ROLE_OWNER"/>
  <schema name="dbsteward" owner="ROLE_OWNER">
    <function name="db_config_parameter" returns="text" owner="ROLE_OWNER" cachePolicy="VOLATILE" description="used to push configurationParameter values permanently into the database configuration">
      <functionParameter name="config_parameter" type="text"/>
      <functionParameter name="config_value" type="text"/>
      <functionDefinition language="plpgsql" sqlFormat="pgsql8">
        DECLARE
          q text;
          name text;
          n text;
        BEGIN
          SELECT INTO name current_database();
          q := 'ALTER DATABASE ' || name || ' SET ' || config_parameter || ' ''' || config_value || ''';';
          n := 'DB CONFIG CHANGE: ' || q;
          RAISE NOTICE '%', n;
          EXECUTE q;
          RETURN n;
        END;
      </functionDefinition>
    </function>
  </schema>
  <schema name="hotel" owner="ROLE_OWNER">
    <table name="rate" owner="ROLE_OWNER" primaryKey="rate_id" slonyId="1">
      <column name="rate_id" type="integer" null="false"/>
      <column name="rate_group_id" foreignSchema="hotel" foreignTable="rate_group" foreignColumn="rate_group_id" null="false"/>
      <column name="rate_name" type="character varying(120)"/>
      <column name="rate_value" type="numeric"/>
    </table>
    <table name="rate_group" owner="ROLE_OWNER" primaryKey="rate_group_id" slonyId="2">
      <column name="rate_group_id" type="integer" null="false"/>
      <column name="rate_group_name" type="character varying(100)"/>
      <column name="rate_group_enabled" type="boolean" null="false" default="true"/>
    </table>
  </schema>
</dbsteward>
XML;

    parent::setUp();
  }

  protected function build_db() {
    $this->build_db_pgsql8();
  }

  protected function get_connection() {
    return $this->pgsql;
  }

  protected function apply_options() {
    $this->apply_options_pgsql8();
  }
}