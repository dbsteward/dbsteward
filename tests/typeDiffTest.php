<?php
/**
 * DBSteward type differencing test
 *
 * Items tested:
 *  type diffing when members are added
 *  tables contain columns dependant on the type
 *  type dependant column depends on a type defined in a different schema than the table
 *  type dependant column is references in a view
 *  view definition does not change from A to B
 *
 * MySQL5 tests for these items are located in:
 *   mysql5/Mysql5TableDiffSQLTest.php ::testEnums()
 *   mysql5/Mysql5TypeDiffSQLTest.php
 *   mysql5/Mysql5TypeSQLTest.php
 *   mysql5/Mysql5ViewDiffSQLTest.php
 *
 * todo:
 *  type value insertion testing - remove <col sql="true" on enumerated type column entries
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/dbstewardUnitTestBase.php';

class typeDiffTest extends dbstewardUnitTestBase {
  // don't use tabrows if not actually starting dbsteward builds from scratch;
  // pgsql8::build has no provisions for expanding them
  private $pgsql8_xml_a = <<<XML
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
    <slony clusterName="duplicate_slony_ids_testsuite">
      <slonyNode id="1" comment="DSI - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="common duplicate testing database definition">
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
  <schema name="otherschema" owner="ROLE_OWNER">
    <type name="event_type" type="enum">
      <enum name="Read"/>
      <enum name="Write"/>
      <enum name="Delete"/>
    </type>
    <table name="othertable" owner="ROLE_OWNER" primaryKey="othertable_id" description="othertable for other data" slonyId="50">
      <column name="othertable_id" type="int"/>
      <column name="othertable_name" type="varchar(100)" null="false"/>
      <column name="othertable_detail" type="text" null="false"/>
      <grant role="ROLE_APPLICATION" operation="SELECT"/>
    </table>
  </schema>
  <schema name="user_info" owner="ROLE_OWNER">
    <table name="user" owner="ROLE_OWNER" primaryKey="user_id" description="user event log" slonyId="1">
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
  <schema name="log" owner="ROLE_OWNER">
    <table name="event" owner="ROLE_OWNER" primaryKey="event_id" description="user event log" slonyId="2">
      <column name="event_id" type="bigserial" slonyId="2"/>
      <column name="user_id" foreignSchema="user_info" foreignTable="user" foreignColumn="user_id" null="false"/>
      <column name="event_type" type="otherschema.event_type" null="false"/>
      <column name="event_date" type="date" null="false" default="NOW()"/>
      <column name="event_detail" type="text" null="false"/>
      <grant role="ROLE_APPLICATION" operation="SELECT, INSERT"/>
      <rows columns="event_id, user_id, event_type, event_detail">
        <row>
          <col>20</col>
          <col>1</col>
          <col sql="true">'Read'</col>
          <col>Profile read</col>
        </row>
      </rows>
    </table>
    <view name="user_event_log" description="action log with actors" owner="ROLE_OWNER">
      <viewQuery>
        SELECT
          user_info.user.user_id, user_name, user_role
          event_log_id, event_type, event_date, event_detail
        FROM log.event
        JOIN user_info.user ON (user_info.user.user_id = log.event.user_id)
      </viewQuery>
      <grant operation="SELECT" role="ROLE_APPLICATION"/>
    </view>
  </schema>
</dbsteward>
XML;
  private $pgsql8_xml_b = <<<XML
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
    <slony clusterName="duplicate_slony_ids_testsuite">
      <slonyNode id="1" comment="DSI - Local Primary"  dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="2" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyNode id="3" comment="DSI - Local Backup"   dbName="test" dbHost="db-dev1" dbUser="unittest_slony" dbPassword="drowssap1"/>
      <slonyReplicaSet id="100" originNodeId="1" upgradeSetId="101" comment="common duplicate testing database definition">
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
  <schema name="otherschema" owner="ROLE_OWNER">
    <type name="event_type" type="enum">
      <enum name="Read"/>
      <enum name="Write"/>
      <enum name="Delete"/>
      <enum name="Update"/>
      <enum name="Transmit"/>
    </type>
    <table name="othertable" owner="ROLE_OWNER" primaryKey="othertable_id" description="othertable for other data" slonyId="50">
      <column name="othertable_id" type="int"/>
      <column name="othertable_name" type="varchar(100)" null="false"/>
      <column name="othertable_detail" type="text" null="false"/>
      <grant role="ROLE_APPLICATION" operation="SELECT"/>
    </table>
  </schema>
  <schema name="user_info" owner="ROLE_OWNER">
    <table name="user" owner="ROLE_OWNER" primaryKey="user_id" description="user event log" slonyId="1">
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
  <schema name="log" owner="ROLE_OWNER">
    <table name="event" owner="ROLE_OWNER" primaryKey="event_id" description="user event log" slonyId="2">
      <column name="event_id" type="bigserial" slonyId="2"/>
      <column name="user_id" foreignSchema="user_info" foreignTable="user" foreignColumn="user_id" null="false"/>
      <column name="event_type" type="otherschema.event_type" null="false"/>
      <column name="event_date" type="date" null="false" default="NOW()"/>
      <column name="event_detail" type="text" null="false"/>
      <grant role="ROLE_APPLICATION" operation="SELECT, INSERT"/>
      <rows columns="event_id, user_id, event_type, event_detail">
        <row>
          <col>20</col>
          <col>1</col>
          <col sql="true">'Read'</col>
          <col>Profile read</col>
        </row>
      </rows>
    </table>
    <view name="user_event_log" description="action log with actors" owner="ROLE_OWNER">
      <viewQuery>
        SELECT
          user_info.user.user_id, user_name, user_role
          event_log_id, event_type, event_date, event_detail
        FROM log.event
        JOIN user_info.user ON (user_info.user.user_id = log.event.user_id)
      </viewQuery>
      <grant operation="SELECT" role="ROLE_APPLICATION"/>
    </view>
  </schema>
</dbsteward>
XML;
  
  /**
   * @group pgsql8
   */
  public function testAddEnumMemberPGSQL8() {
    $this->markTestSkipped('testAddEnumMember does not actually test anything');
    $this->set_xml_content_a($this->pgsql8_xml_a);
    $this->set_xml_content_b($this->pgsql8_xml_b);

    $this->build_db_pgsql8();
    $this->upgrade_db_pgsql8();
  }
  
  /**
   * @group mssql10
   */
  public function testAddEnumMemberMSSQL10() {
    $this->markTestSkipped('testAddEnumMember does not actually test anything');
    $this->set_xml_content_a($this->pgsql8_xml_a);
    $this->set_xml_content_b($this->pgsql8_xml_b);
    
    $this->build_db_mssql10();
    $this->upgrade_db_mssql10();
  }
  
}

?>
