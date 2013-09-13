<?php
/**
 * Manipulate postgresql definition nodes
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_function {
  public static function supported_language($language) {
    return strcasecmp($language, 'sql') == 0 || strcasecmp($language, 'plpgsql') == 0;
  }

  /**
   * Returns SQL to create the function
   *
   * @return string
   */
  public static function get_creation_sql($node_schema, $node_function) {
    pgsql8::set_context_replica_set_id($node_function);
    $definition = static::get_definition($node_function);

    $sql = "CREATE OR REPLACE FUNCTION " . pgsql8_function::get_declaration($node_schema, $node_function) . " RETURNS " . $node_function['returns'] . "\n"
      . '  AS $_$' . "\n" . $definition . "\n\t" . '$_$' . "\n";

    $sql .= "LANGUAGE " . $definition['language'];

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
  public static function get_declaration($node_schema, $node_function, $include_names = TRUE) {
    $r = pgsql8::get_quoted_schema_name($node_schema['name']) . '.' . $node_function['name'] . '(';
    $parameters = dbx::get_function_parameters($node_function);
    foreach($parameters AS $parameter) {
      $arg = '';
      if ( isset($parameter['direction']) && strlen($parameter['direction']) > 0 ) {
        $arg .= $parameter['direction'] . ' ';
      }
      if ( $include_names && strlen($parameter['name']) > 0 ) {
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

  public static function set_definition(&$node_function, $definition) {
    $node_function->addChild('functionDefinition', $definition);
  }
  public static function has_definition($node_function) {
    foreach ( $node_function->functionDefinition as $def ) {
      if ( empty($def['sqlFormat']) || empty($def['language']) ) {
        throw new Exception("Attributes sqlFormat and language are required on functionDefinitions, in function '{$node_function['name']}'");
      }
      if ( $def['sqlFormat'] == dbsteward::get_sql_format() && static::supported_language($def['language']) ) {
        return true;
      }
    }
    return false;
  }

  public static function get_definition($node_function) {
    $definition = null;
    foreach ( $node_function->functionDefinition as $def ) {
      if ( empty($def['sqlFormat']) || empty($def['language']) ) {
        throw new Exception("Attributes sqlFormat and language are required on functionDefinitions, in function '{$node_function['name']}'");
      }
      if ( $def['sqlFormat'] == dbsteward::get_sql_format() && static::supported_language($def['language']) ) {
        if ( $definition !== null ) {
          throw new Exception("duplicate function definition for {$def['sqlFormat']}/{$def['language']} in function '{$node_function['name']}'");
        }
        $definition = $def;
      }
    }
    if ( $definition === null ) {
      foreach( $node_function->functionDefinition AS $def) {
        var_dump($def);
      }
      $format = dbsteward::get_sql_format();
      throw new Exception("no function definitions in a known language for format $format in function '{$node_function['name']}'");
    }
    return $definition;
  }

  /**
   * Creates and returns SQL for dropping the function.
   *
   * @return created SQL
   */
  public static function get_drop_sql($node_schema, $node_function) {
    pgsql8::set_context_replica_set_id($node_function);
    $declaration = self::get_declaration($node_schema, $node_function);
    $declaration = str_ireplace("character varying", "varchar", $declaration);
    $declaration = str_ireplace("varying", "varchar", $declaration);
    return "DROP FUNCTION IF EXISTS " . $declaration . ";";
  }

  public static function equals($node_schema_a, $node_function_a, $node_function_b, $ignore_function_whitespace) {
    if ( strcasecmp($node_function_a['name'], $node_function_b['name']) != 0 ) {
      return false;
    }

    $a_definition = pgsql8_function::get_definition($node_function_a);
    $b_definition = pgsql8_function::get_definition($node_function_b);

    if ($ignore_function_whitespace) {
      $a_definition = preg_replace("/\\s+/", " ", $a_definition);
      $b_definition = preg_replace("/\\s+/", " ", $b_definition);
    }

    $db_doc = $node_schema_a->xpath('..');
    $a_owner = xml_parser::role_enum($db_doc[0], $node_function_a['owner']);
    $b_owner = xml_parser::role_enum($db_doc[0], $node_function_b['owner']);

    $equals =
      strcasecmp(pgsql8_function::get_declaration($node_schema_a, $node_function_a),
                 pgsql8_function::get_declaration($node_schema_a, $node_function_b)) == 0
      && strcasecmp($a_definition, $b_definition) == 0
      && strcasecmp($a_owner, $b_owner) == 0
      && strcasecmp($node_function_a['type'], $node_function_b['type']) == 0;

    return $equals;
  }
}

?>
