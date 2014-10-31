<?php
/**
 * Manipulate postgresql view node
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_view extends sql99_view {

  /**
   * Creates and returns SQL for creation of the view.
   *
   * @return string
   */
  public static function get_creation_sql($db_doc, $node_schema, $node_view) {
    if ( isset($node_view['description']) && strlen($node_view['description']) > 0 ) {
      $ddl = "-- " . dbsteward::string_cast($node_view['description']) . "\n";
    }

    $view_name = pgsql8::get_quoted_schema_name($node_schema['name']) . '.' . pgsql8::get_quoted_table_name($node_view['name']);

    $ddl = "CREATE OR REPLACE VIEW " . $view_name;
    $ddl .= "\n\tAS " . pgsql8_view::get_view_query($node_view) . ";\n";

    if ( isset($node_view['owner']) && strlen($node_view['owner']) > 0 ) {
      $ddl .= "ALTER VIEW " . $view_name
        . "\n\tOWNER TO " . xml_parser::role_enum($db_doc, $node_view['owner']) . ";\n";
    }

    return $ddl;
  }

  /**
   * return SQL command for dropping the view
   *
   * @return string
   */
  public static function get_drop_sql($node_schema, $node_view) {
    $ddl = "DROP VIEW IF EXISTS " . pgsql8::get_quoted_schema_name($node_schema['name']) . '.' . pgsql8::get_quoted_table_name($node_view['name']) . ";\n";
    return $ddl;
  }

}
