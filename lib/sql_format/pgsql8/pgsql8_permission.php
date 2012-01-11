<?php
/**
 * postgresql permission node manipulation
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_permission.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_permission {

  /**
   * add the specified permission to the node_object
   *
   */
  public static function set_permission(&$node_object, $action, $operations, $role) {
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

  public static function has_permission(&$node_object, $node_permission) {
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

  public static function get_sql($db_doc, $node_schema, $node_object, $node_permission) {
    $perms = pgsql8_permission::get_permission_operations($node_permission);
    $roles = preg_split(dbsteward::PATTERN_SPLIT_ROLE, $node_permission['role'], -1, PREG_SPLIT_NO_EMPTY);
    $object_type = strtoupper($node_object->getName());
    switch($object_type) {
      case 'SCHEMA':
        $object_name = $node_schema['name'];
        break;
      case 'SEQUENCE':
      case 'TABLE':
      case 'VIEW':
        $object_name = $node_schema['name'] . '.' . $node_object['name'];
        break;
      case 'FUNCTION':
        $object_name = pgsql8_function::get_declaration($node_schema, $node_object);
        break;
      default:
        throw new exception("unknown object type encountered: " . $object_type);
    }
    $sql = '';
    for($j = 0; $j < count($roles); $j++) {
      $with = '';
      if ( isset($node_permission['with']) && strlen($node_permission['with']) > 0 ) {
        $with = "WITH " . $node_permission['with'] . " OPTION";
      }

      if ( strcasecmp($object_type, 'VIEW') == 0 ) {
        // postgresql doesn't want you to name the view keyword when you grant rights to views
        $pg_object_type = '';
      }
      else {
        $pg_object_type = $object_type;
      }

      if ( strlen($sql) > 0 ) {
        $sql .= "\n";
      }
      $sql .= self::compile_sql_statement(
        strtoupper($node_permission->getName()),
        implode(', ', $perms),
        $pg_object_type,
        $object_name,
        xml_parser::role_enum($db_doc, $roles[$j]),
        $with
      );

      // SCHEMA IMPLICIT GRANTS
      if ( strcasecmp($object_type, 'SCHEMA') == 0 ) {
        // READYONLY USER PROVISION: grant usage on the schema for the readonly user
        if ( strlen($db_doc->database->role->readonly) > 0 ) {
          if ( strlen($sql) > 0 ) {
            $sql .= "\n";
          }
          $sql .= self::compile_sql_statement(
            'GRANT',
            'USAGE',
            'SCHEMA',
            $node_schema['name'],
            $db_doc->database->role->readonly
          );
        }
      }

      // SEQUENCE IMPLICIT GRANTS
      if ( strcasecmp($object_type, 'SEQUENCE') == 0 ) {
        // READYONLY USER PROVISION: generate a SELECT on the sequence for the readonly user
        if ( strlen($db_doc->database->role->readonly) > 0 ) {
          if ( strlen($sql) > 0 ) {
            $sql .= "\n";
          }
          $sql .= self::compile_sql_statement(
            'GRANT',
            'SELECT',
            'SEQUENCE',
            $node_schema['name'].'.'.$node_object['name'],
            $db_doc->database->role->readonly
          );
        }
      }

      // TABLE IMPLICIT GRANTS
      if ( strcasecmp($object_type, 'TABLE') == 0 ) {
        // READYONLY USER PROVISION: grant select on the table for the readonly user
        if ( strlen($db_doc->database->role->readonly) > 0 ) {
          if ( strlen($sql) > 0 ) {
            $sql .= "\n";
          }
          $sql .= self::compile_sql_statement(
            'GRANT',
            'SELECT',
            'TABLE',
            $node_schema['name'].'.'.$node_object['name'],
            $db_doc->database->role->readonly
          );
        }

        // don't need to grant cascaded serial permissions to the table owner
        if ( strcasecmp('ROLE_OWNER', $roles[$j]) == 0 ) {
          continue;
        }

        // set serial columns permissions based on table permissions
        foreach($node_object->column AS $column ) {
          if ( preg_match(pgsql8::PATTERN_TABLE_LINKED_TYPES, $column['type']) > 0 ) {
            $col_sequence = pgsql8::identifier_name($node_schema['name'], $node_object['name'], $column['name'], '_seq');

            $seq_priv = array();
            // if you can SELECT, INSERT or UPDATE the table, you can SELECT on the sequence
            if ( in_array('SELECT', $perms) || in_array('INSERT', $perms) || in_array('UPDATE', $perms) ) {
              $seq_priv[] = 'SELECT';
            }
            // if you can INSERT or UPDATE the table, you can UPDATE the sequence
            if ( in_array('INSERT', $perms) || in_array('UPDATE', $perms) ) {
              $seq_priv[] = 'UPDATE';
            }

            // if you only have USAGE or SELECT
            // then seq_priv is empty, and no grant should be issued
            if ( count($seq_priv) > 0 ) {
              $with = '';
              if ( isset($node_permission['with']) && strlen($node_permission['with']) > 0 ) {
                $with = "WITH " . $node_permission['with'] . " OPTION";
              }

              if ( strlen($sql) > 0 ) {
                $sql .= "\n";
              }
              $sql .= self::compile_sql_statement(
                'GRANT',
                implode(',',$seq_priv),
                'SEQUENCE',
                $node_schema['name'] . '.' . $col_sequence,
                xml_parser::role_enum($db_doc, $roles[$j]),
                $with
              );
            }

            // READYONLY USER PROVISION: grant implicit select on the sequence for the readonly user
            if ( strlen($db_doc->database->role->readonly) > 0 ) {
              if ( strlen($sql) > 0 ) {
                $sql .= "\n";
              }
              $sql .= self::compile_sql_statement(
                'GRANT',
                'SELECT',
                'SEQUENCE',
                $node_schema['name'] . '.' . $col_sequence,
                $db_doc->database->role->readonly
              );
            }
          }
        }
      }
    }

    return $sql;
  }

  public static function compile_sql_statement($action, $right, $object_type, $object_name, $role, $with = '') {
    $sql = $action . ' ' . $right . " ON " . $object_type . " " . $object_name . " TO " . $role;
    if ( strlen($with) > 0 ) {
      $sql .= ' ' . $with;
    }
    $sql .= ";";
    return $sql;
  }

  public static function get_permission_operations($node_permission) {
    if ( strcasecmp($node_permission->getName(), 'grant') != 0 && strcasecmp($node_permission->getName(), 'revoke') != 0 ) {
      throw new exception("Cannot extract permission rights from node that is not grant or revoke");
    }
    if ( !isset($node_permission['operation']) || strlen($node_permission['operation']) == 0 ) {
      throw new exception("node_permission operation definition is empty");
    }

    $valid_permission_operations = array(
      'USAGE',
      'SELECT',
      'DELETE',
      'INSERT',
      'UPDATE',
      'TRIGGER',
      'ALL',
      'EXECUTE',
      // mssql specific
      'REFERENCES',
      'CREATE TABLE',
      'ALTER'
    );

    $list = array();
    $operation_chunks = preg_split(dbsteward::PATTERN_SPLIT_OPERATION, $node_permission['operation'], -1, PREG_SPLIT_NO_EMPTY);
    for($i = 0; $i < count($operation_chunks); $i++) {
      $chunk = strtoupper(trim($operation_chunks[$i]));

      // ignore mssql specific operation grants when not generating for it
      if ( strcasecmp(dbsteward::get_sql_format(), 'mssql') != 0 ) {
        if ( strcasecmp($chunk, 'REFERENCES') == 0 ) {
          continue;
        }
        if ( strcasecmp($chunk, 'CREATE TABLE') == 0 ) {
          continue;
        }
        if ( strcasecmp($chunk, 'ALTER') == 0 ) {
          continue;
        }
      }

      if ( in_array($chunk, $valid_permission_operations) ) {
        $list[] = $chunk;
      }
      else {
        throw new exception("Unknown permission operation: " . $operation_chunks[$i]);
      }
    }
    return $list;
  }

}

?>
