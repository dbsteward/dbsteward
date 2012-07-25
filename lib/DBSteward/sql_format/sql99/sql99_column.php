<?php
/**
 * Manipulate postgresql column definition nodes
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
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
  
  /**
   * Returns default value for given column type. If no default value
   * is specified then null is returned.
   *
   * @param type column type
   *
   * @return found default value or null
   */
  public static function get_default_value($type) {
    $default_value = null;

    if ( preg_match("/^smallint$|^int.*$|^bigint$|^decimal.*$|^numeric.*$|^real$|^double precision$|^float.*$|^double$|^money$/i", $type) > 0 ) {
      $default_value = "0";
    }
    else if ( preg_match("/^character varying.*$|^varchar.*$|^char.*$|^text$/i", $type) > 0 ) {
      $default_value = "''";
    }
    else if ( preg_match("/^boolean$/i", $type) > 0 ) {
      $default_value = "false";
    }

    return $default_value;
  }

}

?>
