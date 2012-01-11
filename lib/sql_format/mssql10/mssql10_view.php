<?php
/**
 * Manipulate view node
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: mssql10_view.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class mssql10_view extends pgsql8_view {

  /**
   * Creates and returns SQL for creation of the view.
   *
   * @return string
   */
  public function get_creation_sql($node_schema, $node_view) {
    if (isset($node_view['description']) && strlen($node_view['description']) > 0) {
      $ddl = "-- " . dbsteward::string_cast($node_view['description']) . "\n";
    }

    $ddl = "CREATE VIEW " . mssql10_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . mssql10_diff::get_quoted_name($node_view['name'], dbsteward::$quote_table_names);
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
    $ddl = "DROP VIEW " . mssql10_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . mssql10_diff::get_quoted_name($node_view['name'], dbsteward::$quote_table_names) . ";\n";
    return $ddl;
  }
}

?>
