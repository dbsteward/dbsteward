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
}