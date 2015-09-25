<?php

/**
 * DBSteward slonyId validation and enforcement tests
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

class XMLSlonyIdValidationTest extends dbstewardUnitTestBase {

  private $pgsql8_xml_bad_table_id = <<<XML
<dbsteward>
  <database>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
    <slony clusterName="xml_slony_id_validation_testsuite">
      <slonyNode id="1" comment="XSIV - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="XSIV - Local Backup"   dbName="test_node2" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="XSIV - Local Backup"   dbName="test_node3" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="core data replica set">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
        <slonyReplicaSetNode id="3" providerNodeId="2"/>
      </slonyReplicaSet>
    </slony>
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
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
  <schema name="user_info" owner="ROLE_OWNER">
    <table name="user" owner="ROLE_OWNER" primaryKey="user_id">
      <column name="user_id" type="bigserial" slonyId="1"/>
      <column name="user_name" type="varchar(100)" null="false"/>
      <column name="user_role" type="varchar(100)" null="false"/>
      <column name="user_create_date" type="timestamp with time zone" null="false" default="NOW()"/>
      <grant role="ROLE_APPLICATION" operation="SELECT, INSERT, UPDATE"/>
      <rows columns="user_id, user_name, user_role">
        <row>
          <col>1</col>
          <col>toor</col>
          <col>super_admin</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;

  private $pgsql8_xml_good_table_id = <<<XML
<dbsteward>
  <database>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
    <slony clusterName="xml_slony_id_validation_testsuite">
      <slonyNode id="1" comment="XSIV - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="XSIV - Local Backup"   dbName="test_node2" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="XSIV - Local Backup"   dbName="test_node3" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="core data replica set">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
        <slonyReplicaSetNode id="3" providerNodeId="2"/>
      </slonyReplicaSet>
    </slony>
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
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
  <schema name="user_info" owner="ROLE_OWNER">
    <table name="user" owner="ROLE_OWNER" primaryKey="user_id" slonyId="1">
      <column name="user_id" type="bigserial" slonyId="1"/>
      <column name="user_name" type="varchar(100)" null="false"/>
      <column name="user_role" type="varchar(100)" null="false"/>
      <column name="user_create_date" type="timestamp with time zone" null="false" default="NOW()"/>
      <grant role="ROLE_APPLICATION" operation="SELECT, INSERT, UPDATE"/>
      <rows columns="user_id, user_name, user_role">
        <row>
          <col>1</col>
          <col>toor</col>
          <col>super_admin</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
  
  private $pgsql8_xml_bad_serial_id = <<<XML
<dbsteward>
  <database>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
    <slony clusterName="xml_slony_id_validation_testsuite">
      <slonyNode id="1" comment="XSIV - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="XSIV - Local Backup"   dbName="test_node2" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="XSIV - Local Backup"   dbName="test_node3" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="core data replica set">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
        <slonyReplicaSetNode id="3" providerNodeId="2"/>
      </slonyReplicaSet>
    </slony>
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
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
  <schema name="user_info" owner="ROLE_OWNER">
    <table name="user" owner="ROLE_OWNER" primaryKey="user_id" slonyId="1">
      <column name="user_id" type="bigserial" />
      <column name="user_name" type="varchar(100)" null="false"/>
      <column name="user_role" type="varchar(100)" null="false"/>
      <column name="user_create_date" type="timestamp with time zone" null="false" default="NOW()"/>
      <grant role="ROLE_APPLICATION" operation="SELECT, INSERT, UPDATE"/>
      <rows columns="user_id, user_name, user_role">
        <row>
          <col>1</col>
          <col>toor</col>
          <col>super_admin</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;

  private $pgsql8_xml_good_serial_id = <<<XML
<dbsteward>
  <database>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
    <slony clusterName="xml_slony_id_validation_testsuite">
      <slonyNode id="1" comment="XSIV - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="XSIV - Local Backup"   dbName="test_node2" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="XSIV - Local Backup"   dbName="test_node3" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="core data replica set">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
        <slonyReplicaSetNode id="3" providerNodeId="2"/>
      </slonyReplicaSet>
    </slony>
    <configurationParameter name="TIME ZONE" value="America/New_York"/>
  </database>
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
  <schema name="user_info" owner="ROLE_OWNER">
    <table name="user" owner="ROLE_OWNER" primaryKey="user_id" slonyId="1">
      <column name="user_id" type="bigserial" slonyId="1"/>
      <column name="user_name" type="varchar(100)" null="false"/>
      <column name="user_role" type="varchar(100)" null="false"/>
      <column name="user_create_date" type="timestamp with time zone" null="false" default="NOW()"/>
      <grant role="ROLE_APPLICATION" operation="SELECT, INSERT, UPDATE"/>
      <rows columns="user_id, user_name, user_role">
        <row>
          <col>1</col>
          <col>toor</col>
          <col>super_admin</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
  
  /**
   * @group pgsql8
   * @expectedException        Exception
   * @expectedExceptionMessage Table user_info.user missing slonyId and slonyIds are required
   */
  public function testBadTableIdPGSQL8() {
    // reset options
    $this->apply_options_pgsql8();

    dbsteward::$require_slony_id = TRUE;
    dbsteward::$generate_slonik = TRUE;

    $this->set_xml_content_a($this->pgsql8_xml_bad_table_id);

    // build the DDL first, incase dbsteward code wants to throw about something
    pgsql8::build($this->output_prefix, xml_parser::xml_composite(array($this->xml_file_a)));

    // if that worked, build a db with this definition
    $this->pgsql8->create_db();
    $this->assertStringNotEqualsFile($this->output_prefix . '_build.sql', '');
    $this->pgsql8->run_file($this->output_prefix . '_build.sql');
    
    // throw a different exception if we got this far to signify failure
    throw new Exception("Table without slonyId definition did not throw");
  }
  
  /**
   * @group pgsql8
   */
  public function testGoodTableIdPGSQL8() {
    // reset options
    $this->apply_options_pgsql8();

    dbsteward::$require_slony_id = TRUE;
    dbsteward::$generate_slonik = TRUE;

    $this->set_xml_content_a($this->pgsql8_xml_good_table_id);

    // build the DDL first, incase dbsteward code wants to throw about something
    pgsql8::build($this->output_prefix, xml_parser::xml_composite(array($this->xml_file_a)));

    // if that worked, build a db with this definition
    $this->pgsql8->create_db();
    $this->assertStringNotEqualsFile($this->output_prefix . '_build.sql', '');
    $this->pgsql8->run_file($this->output_prefix . '_build.sql');
  }

  /**
   * @group pgsql8
   * @expectedException        Exception
   * @expectedExceptionMessage user_info.user.user_id serial column missing slonyId and slonyIds are required
   */
  public function testBadSerialIdPGSQL8() {
    // reset options
    $this->apply_options_pgsql8();

    dbsteward::$require_slony_id = TRUE;
    dbsteward::$generate_slonik = TRUE;

    $this->set_xml_content_a($this->pgsql8_xml_bad_serial_id);

    // build the DDL first, incase dbsteward code wants to throw about something
    pgsql8::build($this->output_prefix, xml_parser::xml_composite(array($this->xml_file_a)));

    // if that worked, build a db with this definition
    $this->pgsql8->create_db();
    $this->assertStringNotEqualsFile($this->output_prefix . '_build.sql', '');
    $this->pgsql8->run_file($this->output_prefix . '_build.sql');
    
    // throw a different exception if we got this far to signify failure
    throw new Exception("Serial column without slonyId definition did not throw");
  }
  
  /**
   * @group pgsql8
   */
  public function testGoodSerialIdPGSQL8() {
    // reset options
    $this->apply_options_pgsql8();

    dbsteward::$require_slony_id = TRUE;
    dbsteward::$generate_slonik = TRUE;

    $this->set_xml_content_a($this->pgsql8_xml_good_serial_id);

    // build the DDL first, incase dbsteward code wants to throw about something
    pgsql8::build($this->output_prefix, xml_parser::xml_composite(array($this->xml_file_a)));

    // if that worked, build a db with this definition
    $this->pgsql8->create_db();
    $this->assertStringNotEqualsFile($this->output_prefix . '_build.sql', '');
    $this->pgsql8->run_file($this->output_prefix . '_build.sql');
  }

}
