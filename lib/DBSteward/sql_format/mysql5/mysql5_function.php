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

  const ALT_DELIMITER = '$_$';

  public static function supported_language($language) {
    return strcasecmp($language, 'sql') == 0;
  }

  public static function is_procedure($node_function) {
    return (isset($node_function['procedure']) && $node_function['procedure']);
  }

  public static function get_creation_sql($node_schema, $node_function) {
    $name = static::get_declaration($node_schema, $node_function);

    $definer = (strlen($node_function['owner']) > 0) ? xml_parser::role_enum(dbsteward::$new_database,$node_function['owner']) : 'CURRENT_USER';

    // always drop the function first, just to be safe, and to be compatible with pgsql8's CREATE OR REPLACE
    $sql = static::get_drop_sql($node_schema, $node_function) . "\n";

    if ( mysql5::$swap_function_delimiters ) {
      $sql .= 'DELIMITER ' . static::ALT_DELIMITER . "\n";
    }

    $function_type = static::is_procedure($node_function) ? 'PROCEDURE' : 'FUNCTION';

    $sql .= "CREATE DEFINER = $definer $function_type $name (";

    if ( isset($node_function->functionParameter) ) {
      $params = array();
      foreach ( $node_function->functionParameter as $param ) {
        if ( isset($param['direction']) && ! static::is_procedure($node_function) ) {
          throw new exception("Parameter directions are not supported in MySQL functions");
        }
        if ( empty($param['name']) ) {
          throw new exception("Function parameters must have names in MySQL. In function '{$node_function['name']}'");
        }

        $type = $param['type'];
        if ( $node_type = mysql5_type::get_type_node(dbsteward::$new_database, $node_schema, $type) ) {
          $type = mysql5_type::get_enum_type_declaration($node_type);
        }

        $sparam = '';
        if (isset($param['direction'])) {
          $sparam .= (string)$param['direction'] . ' ';
        }

        $sparam .= mysql5::get_quoted_function_parameter($param['name']) . ' ' . $type;
        $params[] = $sparam;
      }
      $sql .= implode(', ', $params);
    }

    $sql .= ")\n";

    // Procedures don't have a return statement
    if (!static::is_procedure($node_function)) {
      $returns = $node_function['returns'];
      if ( $node_type = mysql5_type::get_type_node(dbsteward::$new_database, $node_schema, $returns) ) {
        $returns = mysql5_type::get_enum_type_declaration($node_type);
      }
      $sql .= "RETURNS " . $returns . "\n";
    }
    $sql .= "LANGUAGE SQL\n";

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

    if ( !empty($node_function['description']) ) {
      $sql .= "COMMENT " . mysql5::quote_string_value($node_function['description']) . "\n";
    }

    $sql .= trim(static::get_definition($node_function));

    $sql = rtrim($sql, ';');

    if ( mysql5::$swap_function_delimiters ) {
      $sql .= static::ALT_DELIMITER . "\nDELIMITER ;";
    }
    else {
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
    $function_type = static::is_procedure($node_function) ? 'PROCEDURE' : 'FUNCTION';
    return "DROP $function_type IF EXISTS " . mysql5::get_fully_qualified_object_name($node_schema['name'], $node_function['name'], 'function') . ";";
  }

  public static function get_declaration($node_schema, $node_function) {
    return mysql5::get_fully_qualified_object_name($node_schema['name'], $node_function['name'], 'function');
  }

  public static function equals($node_schema_a, $node_function_a, $node_function_b, $ignore_function_whitespace) {
    $everything_but_args_equal = parent::equals($node_schema_a, $node_function_a, $node_function_b, $ignore_function_whitespace);

    if ( ! $everything_but_args_equal ) {
      return false;
    }

    // if the args are different, consider it changed
    $agg = function ($a, $param) {
      return $a . $param['name'] . $param['type'];
    };
    $params_a = array_reduce($node_function_a->xpath('functionParameter'), $agg);
    $params_b = array_reduce($node_function_b->xpath('functionParameter'), $agg);

    return strcasecmp($params_a, $params_b) === 0;
  }
}
?>
