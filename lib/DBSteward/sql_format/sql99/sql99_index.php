<?php
/**
 * Manipulate table index definition nodes
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_index {
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

  public static function get_using_option_sql($using) {
    $using = strtoupper((string)$using);
    return $using;
  }
}