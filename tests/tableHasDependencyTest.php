<?php
/**
 * DBSteward serial start confirmat test
 *
 * 1) Confirm serial starts are applied when creating new tables
 * 2) Confirm when adding new tables with serial columns that serial starts are applied in stage 2
 *
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/mock_output_file_segmenter.php';

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

?>
