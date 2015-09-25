<?php
/**
 * Manipulate view node
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_view extends pgsql8_view {

  /**
   * Creates and returns SQL for creation of the view.
   *
   * @return string
   */
  public static function get_creation_sql($node_schema, $node_view) {
    if (isset($node_view['description']) && strlen($node_view['description']) > 0) {
      $ddl = "-- " . dbsteward::string_cast($node_view['description']) . "\n";
    }

    $ddl = "CREATE VIEW " . mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_view['name']);
    $ddl .= "\n\tAS " . mssql10_view::get_view_query($node_view) . ";\n";

    // @IMPLEMENT: $node_view['owner'] ?
    return $ddl;
  }

  /**
   * return SQL command for dropping the view
   *
   * @return string
   */
  public function get_drop_sql($node_schema, $node_view) {
    $ddl = "DROP VIEW " . mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_view['name']) . ";\n";
    return $ddl;
  }
}

?>
