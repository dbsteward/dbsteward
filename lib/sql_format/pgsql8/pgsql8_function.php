<?php
/**
 * Manipulate postgresql definition nodes
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_function.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_function {

  /**
   * Returns SQL to create the function
   *
   * @return string
   */
  public function get_creation_sql($node_schema, $node_function) {
    $sql = "CREATE OR REPLACE FUNCTION " . pgsql8_function::get_declaration($node_schema, $node_function) . " RETURNS " . $node_function['returns'] . "\n"
      . '  AS $_$' . "\n" . pgsql8_function::get_definition($node_function) . "\n\t" . '$_$' . "\n";

    $sql .= "LANGUAGE " . $node_function['language'];

    if ( isset($node_function['cachePolicy']) && strlen($node_function['cachePolicy']) > 0 ) {
      $sql .= ' ' . $node_function['cachePolicy'];
    }

    if ( isset($node_function['securityDefiner']) && strlen($node_function['securityDefiner']) > 0 ) {
      $sql .= ' SECURITY DEFINER';
    }

    $sql .= "; -- DBSTEWARD_FUNCTION_DEFINITION_END\n";

    // set function ownership if defined
    if (strlen($node_function['owner']) > 0 ) {
      $sql .= "ALTER FUNCTION " . pgsql8_function::get_declaration($node_schema, $node_function)
        . " OWNER TO " . xml_parser::role_enum(dbsteward::$new_database, $node_function['owner']) . ";\n";
    }

    // set function comment if defined
    if (isset($node_function['description']) && strlen($node_function['description']) > 0) {
      $sql .= "COMMENT ON FUNCTION " . pgsql8_function::get_declaration($node_schema, $node_function)
        . " IS '" . pg_escape_string($node_function['description']) . "';\n";
    }

    return $sql;
  }

  /**
   * Save declaration information to the node_function
   *
   * @param functionName name of the function
   * @param arguments string containing arguments part of function
   *        declaration
   */
  public static function set_declaration(&$node_schema, &$node_function, $arguments) {
    if ( strlen(trim($arguments)) == 0 ) {
      // no arguments to set for the function
    } else {
      $arguement_parts = explode(',', trim($arguments));
      $args = array();
      foreach($arguement_parts as $part) {
        $chunks = preg_split("/[\s]+/", $part, -1, PREG_SPLIT_NO_EMPTY);
        // per http://www.postgresql.org/docs/8.4/static/sql-createfunction.html
        // IN is default
        $direction = 'IN';
        $name = '';
        // if the first chunk is in or out, push it off as the direction
        if ( strcasecmp($chunks[0], 'IN') == 0 || strcasecmp($chunks[0], 'OUT') == 0) {
          $direction = strtoupper(array_shift($chunks));
        }
        // only 1 remaining chunk, and no match to known types, cry about it
        if ( count($chunks) < 2 && preg_match(dbsteward::PATTERN_KNOWN_TYPES, implode(' ', $chunks)) == 0 ) {
          throw new exception("unknown type encountered: " . implode(' ', $chunks));
        }
        // if the remainder is not a known type, push it off as the parameter name
        if ( preg_match(dbsteward::PATTERN_KNOWN_TYPES, implode(' ', $chunks)) == 0 ) {
          $name = array_shift($chunks);
        }

        // whatever is left is the type of the parameter
        $type = implode(' ', $chunks);
        // is reformed type in the known type list?
        if ( preg_match(dbsteward::PATTERN_KNOWN_TYPES, $type) == 0 ) {
          throw new exception("unknown type inferred: " . implode(' ', $chunks));
        }

        $function_parameter = &dbx::get_function_parameter($node_function, $name, true);
        dbx::set_attribute($function_parameter, 'direction', $direction);
        dbx::set_attribute($function_parameter, 'type', $type);
      }
    }
    return self::get_declaration($node_schema, $node_function);
  }

  /**
   * Creates declaration string for the function. The string consists
   * of function name, '(', list of argument types separated by ',' and ')'.
   *
   * @param $node_function
   */
  public static function get_declaration($node_schema, $node_function) {
    $r = pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . $node_function['name'] . '(';
    $parameters = dbx::get_function_parameters($node_function);
    foreach($parameters AS $parameter) {
      $arg = '';
      if ( isset($parameter['direction']) && strlen($parameter['direction']) > 0 ) {
        $arg .= $parameter['direction'] . ' ';
      }
      if ( strlen($parameter['name']) > 0 ) {
        $arg .= $parameter['name'] . ' ';
      }
      $arg .= $parameter['type'];

     $r .= $arg . ', ';
    }
    if ( count($parameters) > 0 ) {
      $r = substr($r, 0, -2);
    }
    $r .= ')';
    return $r;
  }

  public function set_definition(&$node_function, $definition) {
    $node_function->addChild('functionDefinition', $definition);
  }

  public function get_definition(&$node_function) {
    $nodes = $node_function->xpath('functionDefinition');
    return $nodes[0];
  }

  /**
   * Creates and returns SQL for dropping the function.
   *
   * @return created SQL
   */
  public function get_drop_sql($node_schema, $node_function) {
    $declaration = self::get_declaration($node_schema, $node_function);
    $declaration = str_ireplace("character varying", "varchar", $declaration);
    $declaration = str_ireplace("varying", "varchar", $declaration);
    return "DROP FUNCTION IF EXISTS " . $declaration . ";";
  }

  public function equals($node_schema_a, $node_function_a, $node_function_b, $ignore_function_whitespace) {
    if ( strcasecmp($node_function_a['name'], $node_function_b['name']) != 0 ) {
      return false;
    }

    $a_definition = pgsql8_function::get_definition($node_function_a);
    $b_definition = pgsql8_function::get_definition($node_function_b);

    if ($ignore_function_whitespace) {
      $a_definition = preg_replace("/\\s+/", " ", $a_definition);
      $b_definition = preg_replace("/\\s+/", " ", $b_definition);
    }

    $equals =
      strcasecmp(pgsql8_function::get_declaration($node_schema_a, $node_function_a),
                 pgsql8_function::get_declaration($node_schema_a, $node_function_b)) == 0
      && strcasecmp($a_definition, $b_definition) == 0
      && strcasecmp($node_function_a['owner'], $node_function_b['owner']) == 0
      && strcasecmp($node_function_a['type'], $node_function_b['type']) == 0;

    return $equals;
  }
}

?>
