<?php
/**
 * Diffs indexes.
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/../sql99/sql99_diff_indexes.php';
require_once __DIR__ . '/mysql5_index.php';

class mysql5_diff_indexes extends sql99_diff_indexes {
  protected static function get_index_drop_sql($schema, $table, $index) {
    return mysql5_index::get_drop_sql($schema, $table, $index);
  }

  protected static function get_index_create_sql($schema, $table, $index) {
    return mysql5_index::get_creation_sql($schema, $table, $index);
  }
}