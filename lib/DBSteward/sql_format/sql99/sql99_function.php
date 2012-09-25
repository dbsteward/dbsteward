<?php
/**
 * Manipulate function node
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_function {
  public static function supported_language($language) {
    return false;
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

  public function equals($node_schema_a, $node_function_a, $node_function_b, $ignore_function_whitespace) {
    if ( strcasecmp($node_function_a['name'], $node_function_b['name']) != 0 ) {
      return false;
    }

    $a_definition = static::get_definition($node_function_a);
    $b_definition = static::get_definition($node_function_b);

    if ($ignore_function_whitespace) {
      $a_definition = trim(preg_replace("/\\s+/", " ", $a_definition));
      $b_definition = trim(preg_replace("/\\s+/", " ", $b_definition));
    }

    $a_definition = rtrim($a_definition, ';');
    $b_definition = rtrim($b_definition, ';');

    $equals =
      strcasecmp(static::get_declaration($node_schema_a, $node_function_a),
                 static::get_declaration($node_schema_a, $node_function_b)) == 0
      && strcasecmp($a_definition, $b_definition) == 0
      && strcasecmp($node_function_a['owner'], $node_function_b['owner']) == 0
      && strcasecmp($node_function_a['returns'], $node_function_b['returns']) == 0;

    return $equals;
  }
}
?>
