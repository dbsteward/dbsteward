<?php
/**
 * Tests the expansion of tabrows with alternate delimiters
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/dbstewardUnitTestBase.php';

class TabrowDelimiterTest extends PHPUnit_Framework_TestCase {
  /**
   * @dataProvider tabrowParsingProvider
   */
  public function testTabrowParsing($tabrow, $delimiter, $expected) {
    $parsed = $this->getParsedRow($tabrow, $delimiter);
    $this->assertEquals($expected, $parsed);
  }
  public function tabrowParsingProvider() {
    return array(
      array("asdf\t4123\tfalse", false, array('asdf', '4123', 'false')),
      array("asdf,4123,false", ',', array('asdf', '4123', 'false')),
      array("asdf , 4123 , false", ' , ', array('asdf', '4123', 'false')),
      array("asdf , 4123 , false", ',', array('asdf ', ' 4123 ', ' false')),
      // note: these are not literal newline/tab delimiters
      array("asdf\n4123\nfalse", '\n', array('asdf', '4123', 'false')),
      array("asdf\t4123\tfalse", '\t', array('asdf', '4123', 'false')),
    );
  }

  public function testMismatchedTabrow() {
    try {
      $this->getParsedRow("asdf:4123:false", ',');
    } catch (exception $ex) {
      $this->assertEquals('overlay_cols list count does not match overlay_row->col count', $ex->getMessage());
      return;
    }
    $this->fail('Expected to get an exception for mismatched columns in row, but got nothing');
  }

  private function getParsedRow($tabrow, $delimiter) {
    $xml = <<<XML
<dbsteward>
  <database>
    <role>
      <application>x</application>
      <owner>x</owner>
      <replication>x</replication>
      <readonly>x</readonly>
    </role>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="tabrowtable" owner="ROLE_OWNER" primaryKey="c1">
      <column name="c1" type="int"/>
      <column name="c2" type="int"/>
      <column name="c3" type="int"/>
      <rows columns="c1,c2,c3">
        <tabrow>$tabrow</tabrow>
      </rows>
    </table>
  </schema>
</dbsteward>
XML;
    $doc = simplexml_load_string($xml);

    if ($delimiter !== false) {
      $doc->schema->table->rows['tabrowDelimiter'] = $delimiter;
    }

    $composite = xml_parser::composite_doc(null, $doc);

    $cols = array();
    foreach ($composite->schema->table->rows->row->col as $col) {
      $cols[] = (string)$col;
    }

    return $cols;
  }
}