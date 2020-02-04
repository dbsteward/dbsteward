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

  /**
   * confirm that table dependencies are detected through inheritsSchema on the table element
   * 
   * @group nodb
   * @group psql8
   * @group mysql5
   */
  public function testDependencyOfChildViaInheritsSchema() {
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
  
  /**
   * confirm that table dependencies are detected through foreignSchema/foreignTable/foreignColumn inline in column element
   * 
   * @group nodb
   * @group psql8
   * @group mysql5
   */
  public function testDependencyOfChildViaColumnInlineForeignKey() {
    $parent = '<schema name="parentSchema" />';
    $parent_table = '
        <table name="parentTable">
          <column name="parentColumn" type="integer"/>
        </table>';

    $child = '<schema name="parentSchema" />';
    $child_table = '
        <table name="childTable">
          <column name="childColumn" foreignSchema="parentSchema" foreignTable="parentTable" foreignColumn="parentColumn"/>
        </table>';

    $parent_obj = array('schema' => simplexml_load_string($parent),
                        'table' => simplexml_load_string($parent_table));
    $child_obj = array('schema' => simplexml_load_string($child),
                       'table' => simplexml_load_string($child_table));

    $this->assertTrue(xml_parser::table_has_dependency($child_obj, $parent_obj));
    $this->assertFalse(xml_parser::table_has_dependency($parent_obj, $child_obj));
  }

  /**
   * confirm that table dependencies are detected through foreignKey elements
   * 
   * @group nodb
   * @group psql8
   * @group mysql5
   */
  public function testDependencyOfChildViaForeignKey() {
    $parent = '<schema name="parentSchema" />';
    $parent_table = '
        <table name="parentTable">
          <column name="parentColumn1" type="integer"/>
          <column name="parentColumn2" type="integer"/>
        </table>';

    $child = '<schema name="parentSchema" />';
    $child_table = '
        <table name="childTable">
          <column name="childColumn1" type="integer"/>
          <column name="childColumn2" type="integer"/>
          <foreignKey 
            columns="childColumn1, childColumn2"
            foreignSchema="parentSchema"
            foreignTable="parentTable"
            foreignColumns="parentColumn1, parentColumn2"
            constraintName="multi_column_foreign_key"
            onDelete="CASCADE"
          />
        </table>';

    $parent_obj = array('schema' => simplexml_load_string($parent),
                        'table' => simplexml_load_string($parent_table));
    $child_obj = array('schema' => simplexml_load_string($child),
                       'table' => simplexml_load_string($child_table));

    $this->assertTrue(xml_parser::table_has_dependency($child_obj, $parent_obj));
    $this->assertFalse(xml_parser::table_has_dependency($parent_obj, $child_obj));
  }

}

