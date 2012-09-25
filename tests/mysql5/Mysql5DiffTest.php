<?php
/**
 * DBSteward unit test for mysql5 differencing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

class Mysql5DiffTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
    dbsteward::$always_recreate_views = FALSE;
  }

  public function testUpdateStructure() {
    $old = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
      <application>the_app</application>
      <customRole>SOMEBODY</customRole>
    </role>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <grant operation="SELECT,UPDATE,DELETE" role="ROLE_OWNER"/>

    <table name="project" owner="ROLE_OWNER" primaryKey="project_id">
      <column name="project_id" type="serial"/>
      <column name="name" type="varchar(100)" null="false"/>

      <constraint name="unique_name_constraint" type="unique" definition="(`name`)" />
    </table>
    <table name="issue" owner="ROLE_OWNER" primaryKey="issue_id">
      <grant operation="SELECT,UPDATE,DELETE" role="ROLE_APPLICATION"/>
      <column name="issue_id" type="serial"/>
      <column name="project_id"
        foreignSchema="public"
        foreignTable="project"
        foreignColumn="project_id"
        foreignOnDelete="cascade"/>
      <column name="title" type="varchar(100)" unique="true"/>
      <column name="description" type="text" null="true"/>
      <column name="type" type="issue_type" default="bug"/>

      <index name="type_index" using="hash">
        <indexDimension>type</indexDimension>
      </index>
    </table>

    <type name="issue_type" type="enum">
      <enum name="bug"/>
      <enum name="task"/>
      <enum name="proposal"/>
    </type>

    <sequence name="odds" owner="ROLE_OWNER" inc="2" start="1" cycle="true">
      <grant operation="SELECT,UPDATE,DELETE" role="ROLE_APPLICATION"/>
    </sequence>

    <view name="issues_with_projects" owner="ROLE_OWNER">
      <viewQuery sqlFormat="mysql5">SELECT * FROM `issue` INNER JOIN `projects` USING (`project_id`)</viewQuery>
      <grant operation="SELECT,UPDATE,DELETE" role="ROLE_APPLICATION"/>
    </view>

    <trigger name="set_last_issue"
      sqlFormat="mysql5"
      when="after"
      event="insert"
      table="issue"
      function="SET @last_issue_id = NEW.`issue_id`"/>

    <function name="get_last_issue" owner="ROLE_OWNER" returns="int">
      <grant operation="EXECUTE" role="ROLE_APPLICATION"/>
      <functionDefinition language="sql" sqlFormat="mysql5">
        RETURN @last_issue_id
      </functionDefinition>
    </function>
  </schema>
</dbsteward>
XML;
    $new = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
      <application>the_app</application>
      <customRole>SOMEBODY</customRole>
    </role>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <!-- add insert -->
    <grant operation="SELECT,INSERT,UPDATE,DELETE" role="ROLE_OWNER"/>
    <table name="issue_group" oldName="project" owner="ROLE_OWNER" primaryKey="issue_group_id">
      <column name="issue_group_id" oldName="project_id" type="serial"/>
      <column name="name" type="varchar(100)" null="false"/>
      <column name="slug" type="varchar(20)" null="false"/>

      <constraint name="unique_name_constraint" type="unique" definition="(`slug`)" />
    </table>

    <table name="issue" owner="ROLE_OWNER" primaryKey="issue_id">
      <grant operation="SELECT,UPDATE,DELETE" role="ROLE_APPLICATION"/>
      <column name="issue_id" type="serial"/>
      <column name="project_id"
        foreignSchema="public"
        foreignTable="issue_group"
        foreignColumn="issue_group_id"
        foreignOnDelete="cascade"/>
      <column name="title" type="varchar(100)" unique="true"/>
      <column name="description" type="text" null="true"/>
      <column name="type" type="issue_type" default="bug"/>
      <column name="assignee_id"
        foreignSchema="public"
        foreignTable="user"
        foreignColumn="user_id"/>

      <index name="type_index" using="hash">
        <indexDimension>type</indexDimension>
        <indexDimension>project_id</indexDimension>
      </index>
    </table>

    <table name="user" owner="ROLE_OWNER" primaryKey="user_id">
      <column name="user_id" type="serial"/>
      <column name="name" type="varchar(100)"/>
    </table>

    <type name="issue_type" type="enum">
      <enum name="bug"/>
      <enum name="task"/>
      <enum name="proposal"/>
      <enum name="request"/>
    </type>

    <sequence name="odds" owner="ROLE_OWNER" inc="2" start="3" cycle="true">
      <!-- get rid of delete -->
      <grant operation="SELECT,UPDATE" role="ROLE_APPLICATION"/>
    </sequence>

    <view name="issues_with_projects" owner="ROLE_OWNER">
      <viewQuery sqlFormat="mysql5">SELECT * FROM `issue` INNER JOIN `issue_group` ON `issue`.`project_id` = `issue_group`.`issue_group_id`</viewQuery>
      <!-- add ROLE_OWNER -->
      <grant operation="SELECT,UPDATE,DELETE" role="ROLE_APPLICATION,ROLE_OWNER"/>
    </view>

    <trigger name="set_last_issue"
      sqlFormat="mysql5"
      when="after"
      event="insert,update"
      table="issue"
      function="SET @last_issue_id = NEW.`issue_id`"/>

    <function name="get_last_issue" owner="ROLE_OWNER" returns="int">
      <grant operation="EXECUTE" role="ROLE_APPLICATION"/>
      <functionParameter name="issue_group_id" type="int"/>
      <functionDefinition language="sql" sqlFormat="mysql5">
        RETURN @last_issue_id
      </functionDefinition>
    </function>
  </schema>
</dbsteward>
XML;

    echo "\n\n-----------------------------Nothing to Old-------------------------------------\n\n";

    $this->common_structure("<dbsteward/>", $old);

    echo "\n\n-------------------------------Old to Old---------------------------------------\n\n";

    $this->common_structure($old, $old);

    echo "\n\n-------------------------------Old to New---------------------------------------\n\n";

    $this->common_structure($old, $new);

    echo "\n\n------------------------------New to Nothing------------------------------------\n\n";

    $this->common_structure($new, "<dbsteward/>");
  }

  private function common_structure($old, $new) {
    dbsteward::$old_database = new SimpleXMLElement($old);
    dbsteward::$new_database = new SimpleXMLElement($new);

    mysql5_diff::$new_table_dependency = xml_parser::table_dependency_order(dbsteward::$new_database);

    $ofs1 = new mock_output_file_segmenter();
    $ofs3 = new mock_output_file_segmenter();

    mysql5_diff::revoke_permissions($ofs1, $ofs3);
    mysql5_diff::update_structure($ofs1, $ofs3);
    mysql5_diff::update_permissions($ofs1, $ofs3);

    // @TODO: assert expected = actual
    // echo "\n\nofs 1:\n\n";
    // echo $ofs1->_get_output();
    // echo "\n\nofs 3:\n\n";
    // echo $ofs3->_get_output();
  }
}
?>
