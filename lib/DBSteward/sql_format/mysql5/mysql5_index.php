<?php
/**
 * Manipulate table index definition nodes
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/mysql5.php';
require_once __DIR__ . '/../sql99/sql99_index.php';

class mysql5_index extends sql99_index {
  /**
   * Generate a name for an unnamed index that matches up with what the database engine expects
   *
   * For MySQL unique keys, this is as simple as the column name: http://dev.mysql.com/doc/refman/5.5/en/create-table.html (third of the way down the page)
   *
   * @todo Need to change this for primary key names? MySQL uses `PRIMARY`, but this is working for now, so I'd rather not change it.
   *
   * @param $table
   * @param $column
   * @param $suffix
   * @return index name
   */
  public static function index_name($table, $column, $suffix) {
    if ($suffix == 'key') {
      return $column;
    }
    return parent::index_name($table, $column, $suffix);
  }
  /**
   * Creates and returns SQL for creation of the index.
   *
   * @return created SQL
   */
  public static function get_creation_sql($node_schema, $node_table, $node_index) {
    $index_note = "-- note that MySQL does not support indexed expressions or named dimensions\n";
    $sql = "CREATE ";

    if ( isset($node_index['unique']) && strcasecmp($node_index['unique'], 'true') == 0 ) {
      $sql .= "UNIQUE ";
    }

    $sql .= "INDEX "
      . mysql5::get_quoted_object_name($node_index['name'])
      . " ON "
      . mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);
    
    $dimensions = array();
    foreach ( $node_index->indexDimension as $dimension ) {
      // mysql only supports indexed columns, not indexed expressions like in pgsql or mssql
      if ( ! mysql5_table::contains_column($node_table, $dimension) ) {
        throw new Exception("Table " . mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']) . " does not contain column '$dimension'");
      }

      if ( ! empty($dimension['name']) ) {
        $index_note .= "-- ignoring name '{$dimension['name']}' for dimension '$dimension' on index '{$node_index['name']}'\n";
      }

      $dimensions[] = mysql5::get_quoted_column_name($dimension);
    }
    $sql .= ' (' . implode(', ', $dimensions) . ')';

    if ( !empty($node_index['using']) ) {
      $sql .= ' USING ' . static::get_using_option_sql($node_index['using']);
    }
    
    //@TODO: mysql5 partial indexes with indexWhere - see pgsql8_index

    return $index_note.$sql.';';
  }

  public static function get_drop_sql($node_schema, $node_table, $node_index) {
    return "DROP INDEX " . mysql5::get_quoted_object_name($node_index['name']) . " ON " . mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']) . ";";
  }

  public static function get_using_option_sql($using) {
    $using = strtoupper((string)$using);

    switch ( $using ) {
      case 'HASH':
      case 'BTREE':
        return $using;
        break;

      default:
        dbsteward::console_line(1, "MySQL does not support the $using index type, defaulting to BTREE");
        return 'BTREE';
        break;
    }
  }
}
?>
