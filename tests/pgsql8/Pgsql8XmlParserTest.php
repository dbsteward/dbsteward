<?php
/**
 * DBSteward unit tests for pgsql8_xml_parser
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Brian Stoots <bstoots@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class Pgsql8XmlParserTest extends dbstewardUnitTestBase {

  /**
   * 
   */
  public function setUp() {
    parent::setUp();
    static::$last_quote_all_names = dbsteward::$quote_all_names;
    dbsteward::$quote_all_names = TRUE;
  }

  /**
   * 
   */
  public function tearDown() {
    dbsteward::$quote_all_names = static::$last_quote_all_names;
    parent::tearDown();
  }

  /**
   * @dataProvider processProvider()
   */
  public function testProcess($in_xml, $out_xml) {
    $doc = new SimpleXMLElement(file_get_contents($in_xml));
    pgsql8_xml_parser::process($doc);
    // Uncomment this line to create the _out file that will be asserted against.
    // Make sure to double check the contents and then commit to version control
    //file_put_contents($out_xml, $doc->saveXML());
    $this->assertEquals(new SimpleXMLElement(file_get_contents($out_xml)), $doc);
  }

  /**
   * 
   */
  public static function processProvider() {
    $dir = static::getTestDataDir();
    return array(
      array("{$dir}/pgsql8/partition/partition_001_in.xml", "{$dir}/pgsql8/partition/partition_001_out.xml"),
      array("{$dir}/pgsql8/partition/partition_002_in.xml", "{$dir}/pgsql8/partition/partition_002_out.xml"),
      array("{$dir}/pgsql8/partition/partition_003_in.xml", "{$dir}/pgsql8/partition/partition_003_out.xml"),
    );
  }

}
