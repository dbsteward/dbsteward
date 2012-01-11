<?php
/**
 * Manipulate postgresql table index definition nodes
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_index.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_index {

  /**
   * Creates and returns SQL for creation of the index.
   *
   * @return created SQL
   */
  public function get_creation_sql($node_schema, $node_table, $node_index) {
    $sql = "CREATE ";

    if ( isset($node_index['unique']) && strcasecmp($node_index['unique'], 'true') == 0 ) {
      $sql .= "UNIQUE ";
    }

    $sql .= "INDEX "
      . pgsql8_diff::get_quoted_name($node_index['name'], dbsteward::$quote_object_names)
      . " ON "
      . pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.'
      . pgsql8_diff::get_quoted_name($node_table['name'], dbsteward::$quote_table_names);
    if ( isset($node_index['using']) && strlen($node_index['using']) > 0 ) {
      $sql .= ' USING ' . $node_index['using'];
    }
    $sql .= ' (';
    foreach($node_index->indexDimension AS $dimension) {
      $sql .= $dimension . ', ';
    }
    $sql = substr($sql, 0, -2);
    $sql .= ');';

    return $sql;
  }

  public function get_drop_sql($node_schema, $node_table, $node_index) {
    $ddl = "DROP INDEX " . pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . "." . pgsql8_diff::get_quoted_name($node_index['name'], dbsteward::$quote_object_names) . ";\n";
    return $ddl;
  }

  public function equals($node_index_a, $node_index_b) {
    if ( strcasecmp($node_index_a['name'], $node_index_b['name']) != 0 ) {
      $equal = false;
    }
    else if ( strcasecmp($node_index_a['unique'], $node_index_b['unique']) != 0 ) {
      $equal = false;
    }
    else if ( strcasecmp($node_index_a['using'], $node_index_b['using']) != 0 ) {
      $equal = false;
    }
    else {
      $a_dimensions = '';
      foreach($node_index_a->indexDimension AS $dimension) {
        $a_dimensions .= $dimension . '|';
      }
      $b_dimensions = '';
      foreach($node_index_b->indexDimension AS $dimension) {
        $b_dimensions .= $dimension . '|';
      }
      if ( $a_dimensions != $b_dimensions ) {
        $equal = false;
      }
      else {
        $equal = true;
      }
    }
    return $equal;
  }

}

?>
