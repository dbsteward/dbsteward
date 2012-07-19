<?php
/**
 * Manipulate column definition nodes
 *
 * @package DBSteward
 * @subpackage mysql4
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mysql4_column extends pgsql8_column {
  
  /**
   * Returns full definition of the column.
   *
   * @param add_defaults whether default value should be added in case NOT
   *        NULL constraint is specified but no default value is set
   *
   * @return full definition of the column
   */
  public function get_full_definition($db_doc, $node_schema, $node_table, $node_column, $add_defaults, $include_null_definition = true) {
    $column_type = mysql4_column::column_type($db_doc, $node_schema, $node_table, $node_column, $foreign);
    $definition = mysql4::get_quoted_name($node_column['name'], dbsteward::$quote_column_names) . ' ' . $column_type;

    if (strlen($node_column['default']) > 0) {
      $definition .= " DEFAULT " . $node_column['default'];
    } else if ( !mysql4_column::null_allowed($node_table, $node_column) && $add_defaults) {
      $default_col_value = mysql4_column::get_default_value($node_column['type']);
      if ($default_col_value != null) {
        $definition .= " DEFAULT " . $default_col_value;
      }
    }

    if ($include_null_definition && !mysql4_column::null_allowed($node_table, $node_column) ) {
      $definition .= " NOT NULL";
    }

    return $definition;
  }

}

?>
