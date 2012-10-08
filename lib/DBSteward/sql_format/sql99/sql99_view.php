<?php
/**
 * Manipulate view node
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_view {
  public function get_view_query($node_view) {
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
