<?php
/**
 * schema node manipulation
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mysql5_schema extends sql99_schema {
  
  /**
   * Creates and returns SQL for creation of the schema.
   *
   * @return created SQL
   */
  public static function get_creation_sql($node_schema) {
    $name = mysql5::get_quoted_schema_name($node_schema['name']);
    return "CREATE DATABASE IF NOT EXISTS $name;";
  }

  public static function get_use_sql($node_schema) {
    $name = mysql5::get_quoted_schema_name($node_schema['name']);
    return "USE $name;";
  }
  
  /**
   * returns DDL to drop specified schema
   *
   * @return string
   */
  public static function get_drop_sql($node_schema) {
    $name = mysql5::get_quoted_schema_name($node_schema['name']);
    return "DROP DATABASE IF EXISTS $name;";
  }

}

?>
