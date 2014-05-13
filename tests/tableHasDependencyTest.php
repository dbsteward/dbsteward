<?php
/**
 * DBSteward inherited table tests
 *
 * Ensure that inherited tables are properly resolved by table dependency algorithm
 *
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/dbstewardUnitTestBase.php';

class tableHasDependencyTest extends PHPUnit_Framework_TestCase {

  public function testDependencyOfChild() {
    $parent = '<schema name="parentSchema" />';
    $parent_table = '
        <table name="parentTable">
        </table>';

    $child = '<schema name="childSchema" />';
    $child_table = '
        <table name="childTable" inheritsSchema="parentSchema" inheritsTable="parentTable">
        </table>';

    $parent_obj = array('schema' => simplexml_load_string($parent),
                        'table' => simplexml_load_string($parent_table));
    $child_obj = array('schema' => simplexml_load_string($child),
                       'table' => simplexml_load_string($child_table));

    $this->assertTrue(xml_parser::table_has_dependency($child_obj, $parent_obj));
    $this->assertFalse(xml_parser::table_has_dependency($parent_obj, $child_obj));
  }

}

