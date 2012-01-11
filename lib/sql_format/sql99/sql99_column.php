<?php
/**
 * Manipulate postgresql column definition nodes
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: sql99_column.php 2267 2012-01-09 19:50:46Z nkiraly $
 */

class sql99_column {

  public static function null_allowed($node_table, $node_column) {
    if ( !is_object($node_column) ) {
      var_dump($node_column);
      throw new exception("node_column passed is not an object");
    }

    if ( !isset($node_column['null']) ) {
      $null_allowed = true;
    }
    else {
      // if null value is not false then nulls are allowed
      $null_allowed = strcasecmp($node_column['null'], 'false') != 0;
    }

    return $null_allowed;
  }

}

?>
