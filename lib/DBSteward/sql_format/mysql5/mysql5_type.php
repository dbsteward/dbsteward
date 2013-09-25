<?php
/**
 * Manipulate type node
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class mysql5_type extends sql99_type {
  public static function equals($schema_a, $type_a, $schema_b, $type_b) {
    return strcasecmp(static::get_enum_type_declaration($type_a), static::get_enum_type_declaration($type_b)) === 0;
  }

  public static function get_creation_sql($node_schema, $node_type) {
    if ( strcasecmp($node_type['type'], 'enum') != 0 ) {
      throw new exception("unknown type {$node_type['name']} type {$node_type['type']}");
    }

    if ( ! isset($node_type->enum) ) {
      throw new exception("type of type enum contains no enum children");
    }

    $name = $node_type['name'];
    return "-- found enum type $name. references to type $name will be replaced by " . self::get_enum_type_declaration($node_type);
  }

  public static function get_drop_sql($node_schema, $node_type) {
    $name = $node_type['name'];
    return "-- dropping enum type $name. references to type $name will be replaced with the type 'text'";
  }

  public static function get_enum_type_declaration($node_type) {
    return "ENUM('" . implode("','", dbx::to_array($node_type->enum, 'name')) . "')";
  }

  /**
   * Given a type name like `schema`.`enum_name`, find the type node in schema 'schema' with name 'enum_name'
   * @return SimpleXMLElement
   */
  public static function get_type_node($db_doc, $node_schema, $name_ref) {
    if ( preg_match('/(?:["`]?(\w+)["`]?\.)?["`]?(.+)["`]?/', $name_ref, $matches) > 0 ) {
      $schema_ref = $matches[1];
      $type_name = $matches[2];

      // if we found a schema name in the name reference, then attempt to override the given node_schema with the named one
      if ( ! $schema_ref ) {
        if ( ! $node_schema ) {
          throw new Exception("No schema node given and no schema name found in type name reference '$name_ref'");
        }
      }
      else {
        $node_schema = dbx::get_schema($db_doc, $schema_ref);
        if ( ! $node_schema ) {
          throw new Exception("Could not find schema '$schema_ref', given by type name reference '$name_ref'");
        }
      }

      $node_type = dbx::get_type($node_schema, $type_name);
      if ( ! $node_type ) {
        // we did not find the given type - this is not exceptional because we might just be testing to see if it exists
        return NULL;
      }

      // if we got this far, we found the referenced type node
      return $node_type;
    }

    throw new Exception("Unrecognizable type name reference: '$name_ref'");
  }
}
?>
