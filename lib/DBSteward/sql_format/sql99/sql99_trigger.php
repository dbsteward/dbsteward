<?php
/**
 * Manipulate trigger node
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_trigger {
  public function get_events($node_trigger) {
    return preg_split("/[\,\s]+/", $node_trigger['event'], -1, PREG_SPLIT_NO_EMPTY);
  }

  public function equals($trigger_a, $trigger_b) {
    if ( strcasecmp($trigger_a['name'], $trigger_b['name']) != 0 ) {
      return false;
    }
    if ( strcasecmp($trigger_a['table'], $trigger_b['table']) != 0 ) {
      return false;
    }
    if ( strcasecmp($trigger_a['function'], $trigger_b['function']) != 0 ) {
      return false;
    }

    $equals =
      strcasecmp($trigger_a['when'], $trigger_b['when']) == 0
      && strcasecmp($trigger_a['forEach'], $trigger_b['forEach']) == 0
      && strcasecmp($trigger_a['event'], $trigger_b['event']) == 0;

    return $equals;
  }
}
?>
