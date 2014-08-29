<?php
/**
 * Manipulate postgresql view node
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_view {

  /**
   * Creates and returns SQL for creation of the view.
   *
   * @return string
   */
  public static function get_creation_sql($node_schema, $node_view) {
    if ( isset($node_view['description']) && strlen($node_view['description']) > 0 ) {
      $ddl = "-- " . dbsteward::string_cast($node_view['description']) . "\n";
    }

    $view_name = pgsql8::get_quoted_schema_name($node_schema['name']) . '.' . pgsql8::get_quoted_table_name($node_view['name']);

    $ddl = "CREATE OR REPLACE VIEW " . $view_name;
    $ddl .= "\n\tAS " . pgsql8_view::get_view_query($node_view) . ";\n";

    if ( isset($node_view['owner']) && strlen($node_view['owner']) > 0 ) {
      $ddl .= "ALTER VIEW " . $view_name
        . "\n\tOWNER TO " . xml_parser::role_enum(dbsteward::$new_database, $node_view['owner']) . ";\n";
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

  public static function get_view_query($node_view) {
    $q = '';
    foreach($node_view->viewQuery AS $query) {
      if ( !isset($query['sqlFormat']) || strcasecmp($query['sqlFormat'], dbsteward::get_sql_format()) == 0 ) {
        // sanity check to make sure not more than one viewQuery is matching the sqlFormat scenario
        if ( strlen($q) > 0 ) {
          throw new exception("query already matched for sqlFormat -- extra viewQuery elements present?");
        }

        // sqlFormat is not present or
        // sqlFormat matches the current static run-time setting
        // use this viewQuery
        $q = (string)$query;
      }
    }

    if ( strlen($q) == 0 ) {
      foreach($node_view->viewQuery AS $query) {
        var_dump($query);
      }
      throw new exception("view " . $node_view['name'] . " - failed to find viewQuery that matches active sql format " . dbsteward::get_sql_format());
    }

    // if last char is ;, prune it
    if ( substr($q, -1) == ';' ) {
      $q = substr($q, 0, -1);
    }

    return $q;
  }

}

?>
