<?php
/**
 * DBSteward unit test for mysql5 table diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

/**
 * @group pgsql8
 */
class TableDiffTest extends PHPUnit_Framework_TestCase {

  private $db_doc_xml = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
      <customRole>SOMEBODY</customRole>
    </role>
  </database>
XML;

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = TRUE;
    dbsteward::$ignore_oldnames = FALSE;
  }

  public function testColumnCaseChange() {
    $lower = <<<XML
<schema name="test0" owner="NOBODY">
  <table name="table" owner="NOBODY">
    <column name="column" type="int" />
  </table>
</schema>
XML;
    $upper_with_oldname = <<<XML
<schema name="test0" owner="NOBODY">
  <table name="table" owner="NOBODY">
    <column name="CoLuMn" type="int" oldColumnName="column" />
  </table>
</schema>
XML;
  $upper_without_oldname = <<<XML
<schema name="test0" owner="NOBODY">
  <table name="table" owner="NOBODY">
    <column name="CoLuMn" type="int" />
  </table>
</schema>
XML;

    // when quoting is off, a change in case is a no-op
    dbsteward::$quote_all_names = FALSE;
    dbsteward::$quote_column_names = FALSE;
    $this->common_diff($lower, $upper_without_oldname, '', '');

    // when quoting is on, a change in case results in a rename
    dbsteward::$quote_all_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    $this->common_diff($lower, $upper_with_oldname, "-- column rename from oldColumnName specification\nALTER TABLE \"test0\".\"table\" RENAME COLUMN \"column\" TO \"CoLuMn\";", '');

    // but, if oldColumnName is not given when doing case sensitive renames, it should throw
    try {
      $this->common_diff($lower, $upper_without_oldname, 'NO EXPECTED OUTPUT', 'NO EXPECTED OUTPUT');
    }
    catch (Exception $e) {
      $this->assertContains('ambiguous operation', strtolower($e->getMessage()));
      return;
    }
    $this->fail("Expected an 'ambiguous operation' exception due to column case change, got nothing.");
  }

  private function common_diff($xml_a, $xml_b, $expected1, $expected3, $message='') {
    dbsteward::$old_database = new SimpleXMLElement($this->db_doc_xml . $xml_a . '</dbsteward>');
    dbsteward::$new_database = new SimpleXMLElement($this->db_doc_xml . $xml_b . '</dbsteward>');

    $ofs1 = new mock_output_file_segmenter();
    $ofs3 = new mock_output_file_segmenter();

    pgsql8_diff_tables::diff_tables($ofs1, $ofs3, dbsteward::$old_database->schema, dbsteward::$new_database->schema);

    $actual1 = trim($ofs1->_get_output());
    $actual3 = trim($ofs3->_get_output());

    $this->assertEquals($expected1, $actual1, "during stage 1: $message");
    $this->assertEquals($expected3, $actual3, "during stage 3: $message");
  }

}