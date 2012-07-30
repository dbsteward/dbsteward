<?php
/**
 * Generic permission node manipulation
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */
class sql99_permission {

  public static function set_permission($node_object, $action, $operations, $role) {
    throw new BadMethodCallException("Unimplemented: ".__METHOD__);
  }

  public static function has_permission($node_object, $node_permission) {
    throw new BadMethodCallException("Unimplemented: ".__METHOD__);
  }

  public static function get_permission_roles($node_permission) {
    return preg_split(dbsteward::PATTERN_SPLIT_ROLE, $node_permission['role'], -1, PREG_SPLIT_NO_EMPTY);
  }

  protected static function get_valid_permission_operations() {
    return array();
  }

  public static function get_permission_operations($node_permission) {
    if ( strcasecmp($node_permission->getName(), 'grant') != 0 && strcasecmp($node_permission->getName(), 'revoke') != 0 ) {
      throw new exception("Cannot extract permission rights from node that is not grant or revoke");
    }
    if ( !isset($node_permission['operation']) || strlen($node_permission['operation']) == 0 ) {
      throw new exception("node_permission operation definition is empty");
    }

    $valid_permission_operations = static::get_valid_permission_operations();

    $list = array();
    $operation_chunks = preg_split(dbsteward::PATTERN_SPLIT_OPERATION, $node_permission['operation'], -1, PREG_SPLIT_NO_EMPTY);
    foreach ( $operation_chunks as $chunk ) {
      $operation = strtoupper(trim($chunk));

      if ( in_array($operation, $valid_permission_operations) ) {
        $list[] = $operation;
      }
      else {
        throw new exception("Unknown permission operation: " . $chunk);
      }
    }
    return $list;
  }
}