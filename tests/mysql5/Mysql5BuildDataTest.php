<?php
/**
 * DBSteward unit test for mysql5 ddl generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

class Mysql5BuildDataTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  public function testSimple() {
    $xml = <<<XML
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
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="result_list" owner="ROLE_OWNER" primaryKey="result_list_id" slonyId="7">
      <column name="result_list_id" type="integer"/>
      <column name="result" type="character varying(50)"/>
      <grant role="ROLE_APPLICATION" operation="SELECT"/>
      <rows columns="result_list_id, result">
        <row>
          <col>0</col>
          <col>Passed</col>
        </row>
        <row>
          <col>1</col>
          <col>Incomplete</col>
        </row>
        <row>
          <col>2</col>
          <col>Failure</col>
        </row>
        <row>
          <col>3</col>
          <col>Error</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
    
    $expected = <<<SQL
INSERT INTO `result_list` (`result_list_id`, `result`) VALUES (0, 'Passed');
INSERT INTO `result_list` (`result_list_id`, `result`) VALUES (1, 'Incomplete');
INSERT INTO `result_list` (`result_list_id`, `result`) VALUES (2, 'Failure');
INSERT INTO `result_list` (`result_list_id`, `result`) VALUES (3, 'Error');
SQL;

    $this->common($xml, $expected);
  }

  public function testSerials() {
    $xml = <<<XML
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
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="result_list" owner="ROLE_OWNER" primaryKey="result_list_id" slonyId="7">
      <column name="result_list_id" type="serial"/>
      <column name="result" type="character varying(50)"/>
      <rows columns="result_list_id, result">
        <row>
          <col>0</col>
          <col>Passed</col>
        </row>
        <row>
          <col>1</col>
          <col>Incomplete</col>
        </row>
        <row>
          <col>2</col>
          <col>Failure</col>
        </row>
        <row>
          <col>3</col>
          <col>Error</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
    
    $expected = <<<SQL
INSERT INTO `result_list` (`result_list_id`, `result`) VALUES (0, 'Passed');
INSERT INTO `result_list` (`result_list_id`, `result`) VALUES (1, 'Incomplete');
INSERT INTO `result_list` (`result_list_id`, `result`) VALUES (2, 'Failure');
INSERT INTO `result_list` (`result_list_id`, `result`) VALUES (3, 'Error');
SELECT setval('__public_result_list_result_list_id_serial_seq',MAX(`result_list_id`),TRUE) FROM `result_list`;
SQL;

    $this->common($xml, $expected);
  }

  private function common($xml, $expected) {
    $dbs = new SimpleXMLElement($xml);
    $ofs = new mock_output_file_segmenter();

    dbsteward::$new_database = $dbs;
    $table_dependency = xml_parser::table_dependency_order($dbs);

    mysql5::build_data($dbs, $ofs, $table_dependency);

    $actual = $ofs->_get_output();
    
    // get rid of comments
    // $expected = preg_replace('/\s*-- .*(\n\s*)?/','',$expected);
    // // get rid of extra whitespace
    // $expected = trim(preg_replace("/\n\n/","\n",$expected));
    $expected = preg_replace("/^ +/m","",$expected);
    $expected = trim(preg_replace("/\n+/","\n",$expected));

    // echo $actual;

    // get rid of comments
    $actual = preg_replace("/\s*-- .*$/m",'',$actual);
    // get rid of extra whitespace
    // $actual = trim(preg_replace("/\n\n+/","\n",$actual));
    $actual = preg_replace("/^ +/m","",$actual);
    $actual = trim(preg_replace("/\n+/","\n",$actual));

    $this->assertEquals($expected, $actual);
  }
}