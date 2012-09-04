<?php
/**
 * MySQL permission node manipulation
 *
 * Since MySQL does permissions very differently from Postgres or MSSQL, the goal here is to allow
 * flexibility for MySQL's inconsistent permissions by translating permissions specific to other vendors
 * into permissions MySQL understands. For example, in Postgres, the DROP and TRUNCATE permissions are
 * different permissions, but MySQL's DROP permission roughly means the same as specifying both in Postgres.
 *
 * Permissions not recognized by DBSteward are not touched in any way. In general, we're leaving it up to the
 * DBSteward user and MySQL to validate permissions, rather than validating them when generating the grants
 * (like we would do with datatypes or identifiers)
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 * @link http://dev.mysql.com/doc/refman/5.5/en/grant.html
 * @link http://dev.mysql.com/doc/refman/5.5/en/privileges-provided.html
 */

class mysql5_permission extends sql99_permission {

  public static function get_permission_sql($db_doc, $node_schema, $node_object, $node_permission, $action='grant') {
    if ( strcasecmp($node_permission->getName(), 'grant') != 0 && strcasecmp($node_permission->getName(), 'revoke') != 0 ) {
      throw new exception("Cannot extract permission rights from node that is not grant or revoke");
    }
    if ( !isset($node_permission['operation']) || strlen($node_permission['operation']) == 0 ) {
      throw new exception("node_permission operation definition is empty");
    }

    $object_name = '';
    $object_type = strtoupper($node_object->getName());
    $privileges = array_map(function ($p) use ($object_type){
      return mysql5_permission::get_real_privilege($p, $object_type);
    }, static::get_permission_privileges($node_permission));
    $roles = static::get_permission_roles($db_doc, $node_permission);
    $with = static::get_permission_options_sql($node_permission);

    switch ( $object_type ) {
      case 'SCHEMA':
        // all tables on current database, because no schemas
        $object_name = '*';
        break;
      case 'VIEW':
        return "-- Ignoring permissions on view '{$node_object['name']}' because MySQL uses SQL SECURITY DEFINER semantics\n";
      case 'TABLE':
        $object_name = mysql5::get_fully_qualified_table_name($node_schema['name'],$node_object['name']);
        break;
      case 'FUNCTION':
        $object_name = "ROUTINE " . mysql5::get_quoted_function_name($node_object['name']);
        break;
      case 'SEQUENCE':
        // sequences exist as rows in a table for mysql
        $object_name = mysql5::get_fully_qualified_table_name($node_schema['name'],mysql5_sequence::TABLE_NAME);
        break;
      default:
        throw new exception("unknown object type encountered: " . $object_type);
    }

    $sql = static::get_sql(strtoupper($action), $object_name, $privileges, $roles, $with) . "\n";
    
    return $sql;
  }

  /**
   * Get sql for GRANTing/REVOKEing all given priveleges, for all given users(roles) on all given objects
   * 
   * @param string $action GRANT/REVOKE
   * @param array $objects
   * @param array $privileges
   * @param array $roles
   * @param string $option_sql optional option sql
   */
  public static function get_sql($action, $objects, $privileges, $roles, $option_sql) {
    $keyword = strcasecmp($action, 'REVOKE') ? 'TO' : 'FROM';
    $sql = array();
    foreach ( (array)$objects as $object ) {
      $sql[] = "$action " . implode(', ', (array)$privileges) . " ON $object $keyword " . implode(', ', (array)$roles) . ($option_sql?" $option_sql;":';');
    }
    return implode("\n",$sql);
  }

  /**
   * Attempt to translate DBSteward standard privileges into their corresponding MySQL privileges
   * 
   * @param string $privilege The privilege as specified in the XML file
   * @param string $obj_type The type of the object the privilege is for
   * @return string The valid MySQL privilege for GRANT/REVOKE statements
   */
  public static function get_real_privilege($privilege, $obj_type) {
    switch ( $privilege=strtoupper($privilege) ) {
      case 'TRUNCATE': // pgsql's TRUNCATE is roughly included in mysql's DROP
        return 'DROP';

      case 'ALTER':
        if ( strcasecmp($obj_type, 'function') === 0 ) {
          // different permission for ALTERing functions
          return 'ALTER ROUTINE';
        }
        // fallthru
      default:            // whatever you say, boss
        return $privilege;

    }
  }
}