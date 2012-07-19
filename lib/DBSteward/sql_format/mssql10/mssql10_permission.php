<?php
/**
 * MSSQL permission node manipulation
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_permission extends pgsql8_permission {

  public static function get_sql($db_doc, $node_schema, $node_object, $node_permission) {
    $operations = mssql10_permission::get_permission_operations($node_permission);
    $roles = preg_split(dbsteward::PATTERN_SPLIT_ROLE, $node_permission['role'], -1, PREG_SPLIT_NO_EMPTY);
    $object_type = strtoupper($node_object->getName());

    $sql = '';

    switch ($object_type) {
      case 'SCHEMA':
        $object_name = mssql10::get_quoted_schema_name($node_schema['name']);
        for ($i = 0; $i < count($operations); $i++) {
          // see http://msdn.microsoft.com/en-us/library/ms187940.aspx
          if (strcasecmp($operations[$i], 'USAGE') == 0) {
            // for schemas, translate USAGE into SELECT
            $operations[$i] = 'SELECT';
          }
          if (strcasecmp($operations[$i], 'ALL') == 0) {
            $operations[$i] = 'SELECT';
            $operations[] = 'INSERT';
            $operations[] = 'UPDATE';
            $operations[] = 'DELETE';
          }

          // CREATE TABLE permission is database-wide
          // so create it explicitly here in-line
          // and then remove it from the list of operations to define
          if (strcasecmp($operations[$i], 'CREATE TABLE') == 0) {
            for ($j = 0; $j < count($roles); $j++) {
              $sql .= "GRANT CREATE TABLE TO " . xml_parser::role_enum($db_doc, $roles[$j]) . ";\n";
            }
            unset($operations[$i]);
            $operations = array_merge($operations);
            $i--;
          }
        }
      break;
      case 'SEQUENCE':
        for ($i = 0; $i < count($operations); $i++) {
          // for sequences, translate USAGE into INSERT
          if (strcasecmp($operations[$i], 'USAGE') == 0) {
            $operations[$i] = 'INSERT';
          }
        }
        // give explicit DELETE permission for pseudo sequences, implemented as mssql10_bit_table
        if (!in_array('DELETE', $operations)) {
          $operations[] = 'DELETE';
        }
      case 'TABLE':
      case 'VIEW':
      case 'FUNCTION':
        $object_name = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_object['name']);
      break;
      default:
        throw new exception("unknown object type encountered: " . $object_type);
    }

    for ($j = 0; $j < count($roles); $j++) {
      $with = '';
      if (isset($node_permission['with']) && strlen($node_permission['with']) > 0) {
        $with = "WITH " . $node_permission['with'] . " OPTION";
      }

      // treat sequences as tables, because that's how mssql10_bit_table created them
      if (strcasecmp($object_type, 'SEQUENCE') == 0) {
        $object_type = 'TABLE';
      }

      // translate pg to ms object type names that the GRANT ... CLASS :: specifier accepts
      $ms_object_type = $object_type;

      // in mssql a table is an object when doing grants
      if (strcasecmp($object_type, 'TABLE') == 0) {
        $ms_object_type = 'OBJECT';
      }

      // in mssql a view is an object when doing grants
      if (strcasecmp($object_type, 'VIEW') == 0) {
        $ms_object_type = 'OBJECT';
      }

      // in mssql a function and a procedure is an object when doing grants
      if (strcasecmp($object_type, 'FUNCTION') == 0) {
        $ms_object_type = 'OBJECT';
      }

      if ( strlen($sql) > 0 ) {
        $sql .= "\n";
      }
      $sql .= self::compile_sql_statement(strtoupper($node_permission->getName()), implode(', ', $operations), $ms_object_type, $object_name, xml_parser::role_enum($db_doc, $roles[$j]), $with);
    }

    return $sql;
  }

  public static function compile_sql_statement($action, $operation, $object_type, $object_name, $role, $with = '') {
    //GRANT { ALL [ PRIVILEGES ] }
    //  | permission [ (column [ ,...n ] ) ] [ ,...n ]
    //  [ ON [ class:: ] securable ] TO principal [ ,...n ]
    //  [ WITH GRANT OPTION ] [ AS principal ]
    // :: scope qualifier -- see http://msdn.microsoft.com/en-us/library/ms187965.aspx
    // GRANT SELECT ON SCHEMA :: theschema TO therole;
    $sql = $action . ' ' . $operation . " ON " . $object_type . " :: " . $object_name . " TO " . $role;
    if (strlen($with) > 0) {
      $sql .= ' ' . $with;
    }
    $sql .= ";";
    return $sql;
  }
}

?>
