<?php
/**
 * Manipulate function definition nodes
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class mysql5_function extends sql99_function {
  public static function supported_language($language) {
    return strcasecmp($language, 'sql') == 0;
  }

  public static function get_creation_sql($node_schema, $node_function) {
    $name = mysql5::get_quoted_function_name($node_function['name']);

    $definer = (strlen($node_function['owner']) > 0) ? xml_parser::role_enum(dbsteward::$new_database,$node_function['owner']) : 'CURRENT_USER';

    $sql = "CREATE DEFINER = $definer FUNCTION $name (";

    if ( isset($node_function->functionParameter) ) {
      $params = array();
      foreach ( $node_function->functionParameter as $param ) {
        if ( isset($param['direction']) ) {
          throw new exception("Parameter directions are not supported in MySQL functions");
        }

        $type = $param['type'];
        if ( $values = mysql5_type::get_enum_values($type) ) {
          $type = mysql5_type::get_enum_type_declaration($values);
        }

        $params[] = mysql5::get_quoted_function_parameter($param['name']) . ' ' . $type;
      }
      $sql .= implode(', ', $params);
    }

    $returns = $node_function['returns'];
    if ( $values = mysql5_type::get_enum_values($returns) ) {
      $returns = mysql5_type::get_enum_type_declaration($values);
    }

    $sql .= ")\nRETURNS " . $returns . "\nLANGUAGE SQL\n";

    switch ( strtoupper($node_function['cachePolicy']) ) {
      case 'IMMUTABLE':
        $sql .= "NO SQL\nDETERMINISTIC\n";
        break;
      case 'STABLE':
        $sql .= "READS SQL DATA\nNOT DETERMINISTIC\n";
        break;
      case 'VOLATILE':
      default:
        $sql .= "MODIFIES SQL DATA\nNOT DETERMINISTIC\n";
        break;
    }

    // unlike pgsql8, mysql5 defaults to SECURITY DEFINER, so we need to set it to INVOKER unless explicitly told to leave it DEFINER
    if ( ! isset($node_function['securityDefiner']) || strcasecmp($node_function['securityDefiner'], 'false') == 0 ) {
      $sql .= "SQL SECURITY INVOKER\n";
    }
    else {
      $sql .= "SQL SECURITY DEFINER\n";
    }

    $sql .= trim(static::get_definition($node_function));

    if (substr($sql, -1) != ';') {
      $sql .= ';';
    }

    return $sql;
  }

  public static function get_drop_sql($node_schema, $node_function) {
    if ( ! static::has_definition($node_function) ) {
      $note = "Not dropping function '{$node_function['name']}' - no definitions for mysql5";
      dbsteward::console_line(1, $note);
      return "-- $note\n";
    }
    return "DROP FUNCTION IF EXISTS " . mysql5::get_quoted_function_name($node_function['name']) . ";";
  }

}