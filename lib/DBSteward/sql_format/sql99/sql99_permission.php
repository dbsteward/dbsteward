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
    if ( !is_object($node_object) ) {
      throw new exception("node_object is not an object, something is wrong, trace calling stack");
    }
    $action = strtolower($action);
    // see @TODO below, this xpath may need generalized if it is implemented
    $nodes = $node_object->xpath($action . "[@role='" . $role . "']");
    if ( count($nodes) == 0 ) {
      // no $action for $object not found
      $node_permission = $node_object->addChild($action);
      dbx::set_attribute($node_permission, 'role', $role);
      dbx::set_attribute($node_permission, 'operation', implode(', ', $operations));
    }

    //@TODO: compare to existing $node_object grants with has_permission()?
    //@TODO: maybe return NOP if node_object already has role operation right defined?
  }

  public static function has_permission($node_object, $node_permission) {
    $permission_operations = pgsql8_permission::get_permission_operations($node_permission);
    // for each of the rights the node_permission provides
    foreach($permission_operations AS $permission_operation) {
      // look at each of the permissions on the node_object and see if the right is given
      foreach(dbx::get_permissions($node_object) AS $node_object_permission) {
        if ( strcasecmp($node_object_permission->getName(), $node_permission->getName()) == 0 ) {
          if ( strcasecmp($node_object_permission['role'], $node_permission['role']) == 0 ) {
            // if this node_object_permission of node_object provides the right
            // the permission is confirmed provided in the object already
            if ( in_array($permission_operation, pgsql8_permission::get_permission_operations($node_object_permission)) ) {
              // so move on to the next permission_operation
              continue 2;
            }
          }
        }
      }
      // if we get here if the right is not found in the in_array comparison for the object
//dbsteward::console_line(3, "permission_operation " . $permission_operation . " not found in " . $node_object['name'] . " permissions for " . $node_permission['role']);
      return false;
    }
    // if we get here then all rights were found to be provided in the object already
    // so the answer to has_permission? is yes
    return true;
  }

  public static function get_permission_roles($db_doc, $node_permission) {
    return array_map(
      function($o)use($db_doc){return xml_parser::role_enum($db_doc, $o);},
      preg_split(dbsteward::PATTERN_SPLIT_ROLE, $node_permission['role'], -1, PREG_SPLIT_NO_EMPTY)
    );
  }

  public static function get_permission_privileges($node_permission) {
    return array_map(
      function($o){return strtoupper(trim($o));},
      preg_split(dbsteward::PATTERN_SPLIT_OPERATION, $node_permission['operation'], -1, PREG_SPLIT_NO_EMPTY)
    );
  }

  public static function get_permission_options_sql($node_permission) {
    if ( !empty($node_permission['with']) ) {
        // @TODO: Support MAX_*_PER_HOUR grant options
        if ( strcasecmp($node_permission['with'], 'grant') != 0 ) {
          dbsteward::console_line(1, "Ignoring WITH option '{$node_permission['with']}' because MySQL only supports WITH GRANT OPTION.");
        }
        else {
          return " WITH GRANT OPTION";
        }
      }
  }
}