<?php
/**
 * Tests that generated index names aren't too long.
 *
 * We encountered a generated index name that was 64 characters long (pgsql8):
 * 'resource_device_type_list_resource_device_type_locator_chan_fkey', but the 
 * max is 63 characters
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class IndexNameTooLongTest extends PHPUnit_Framework_TestCase {
  /**
   * @group pgsql8
   */
  public function testPgsql8IndexNameLengthLongColumn() {
    $table = 'resource_device_type_list';
    $column = 'resource_device_type_locator_channel';
    $suffix = 'fkey';

    $actual = pgsql8_index::index_name($table, $column, $suffix);

    $this->assertEquals(pgsql8::MAX_IDENTIFIER_LENGTH, $l = strlen($actual), "Generated index name '$actual' has length $l but pgsql max is 63");
  }

  /**
   * @group pgsql8
   */
  public function testPgsql8IndexNameLengthLongTable() {
    $table = 'resource_device_type_locator_channel';
    $column = 'resource_device_type_list';
    $suffix = 'fkey';

    $actual = pgsql8_index::index_name($table, $column, $suffix);

    $this->assertEquals(pgsql8::MAX_IDENTIFIER_LENGTH, $l = strlen($actual), "Generated index name '$actual' has length $l but pgsql max is 63");
  }

  /**
   * @group pgsql8
   */
  public function testPgsql8IndexNameLengthShortTableColumn() {
    $table = 'resource_device_type_list';
    $column = 'resource_device_type_list';
    $suffix = 'fkey';

    $expected = "{$table}_{$column}_{$suffix}";
    $actual = pgsql8_index::index_name($table, $column, $suffix);

    $this->assertEquals($expected, $actual);
  }

  /**
   * @group pgsql8
   */
  public function testPgsql8IndexNameLengthNoColumn() {
    $table = 'resource_device_type_list';
    $column = '';
    $suffix = 'fkey';

    $expected = "{$table}_{$suffix}";
    $actual = pgsql8_index::index_name($table, $column, $suffix);

    $this->assertEquals($expected, $actual);
  }

  /**
   * @group pgsql8
   */
  public function testPgsql8IndexNameLengthNoColumnLongTable() {
    $table = 'resource_device_type_locator_channel_type_list_id_holy_crap_dont_do_this';
    $column = '';
    $suffix = 'fkey';

    $actual = pgsql8_index::index_name($table, $column, $suffix);

    $this->assertEquals(pgsql8::MAX_IDENTIFIER_LENGTH, $l = strlen($actual), "Generated index name '$actual' has length $l but pgsql8 max is 63");
  }

}