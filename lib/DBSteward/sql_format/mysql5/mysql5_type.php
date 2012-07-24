<?php
/**
 * Manipulate type node
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/mysql5.php';
require_once __DIR__ . '/../sql99/sql99_type.php';

class mysql5_type extends sql99_type {
  private static $enums = array();

  public static function get_creation_sql($node_schema, $node_type) {
    if ( strcasecmp($node_type['type'], 'enum') != 0 ) {
      throw new exception("unknown type {$node_type['name']} type {$node_type['type']}");
    }

    if ( ! isset($node_type->enum) ) {
      throw new exception("type of type enum contains no enum children");
    }

    $name = $node_type['name'].'';

    self::$enums[$name] = array();
    foreach ( $node_type->enum as $enum ) {
      self::$enums[$name][] = $enum['name'].'';
    }

    return "-- found enum type $name. references to this type will be replaced with the MySQL-compliant ENUM expression\n";
  }

  public static function get_enum_values($enum_name = FALSE) {
    if ( $enum_name === FALSE ) {
      return self::$enums;
    }

    $enum_name .= '';

    return self::$enums[$enum_name];
  }

  public static function clear_registered_enums() {
    self::$enums = array();
  }
}

?>
