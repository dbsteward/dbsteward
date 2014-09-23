<?php
/**
 * DBSteward unit test for testing that the xpath for sql nodes works with quotes
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Adam Jette <jettea46@yahoo.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 * @group mysql5
 * @group nodb
 */
class XmlParserSqlXpathTest extends PHPUnit_Framework_TestCase {

  public function testXPathWorksWithQuotes() {
    $file_name = 'sqlXpathTest.xml';
    $base = new SimpleXMLElement("<allyour>baseyourbaseyourbasebase</allyour>");
    $over = new SimpleXMLElement("<allyour><sql>(1, 'version=\"1.0\"')</sql></allyour>");
    $this->assertNotEquals($base, $over);
    // ensure the sql is composited
    xml_parser::xml_composite_children($base, $over, $file_name);
    $this->assertEquals($base, $over);
  }
}

