<?php
/**
 * schema node manipulation
 *
 * @package DBSteward
 * @subpackage mysql4
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mysql4_schema extends sql99_schema {
  
  /**
   * Creates and returns SQL for creation of the schema.
   *
   * @return created SQL
   */
  public function get_creation_sql($node_schema) {
    if (strcasecmp('dbo', $node_schema['name']) == 0) {
      // don't create the dbo schema
      $ddl = '';
    }
    else {
      $schema_name = mysql4::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names);

      $ddl = "CREATE SCHEMA " . $schema_name . ";\n";

      // @IMPLEMENT: $node_schema['owner'] ?
      // @IMPLEMENT: $node_schema['description'] ?
    }

    return $ddl;
  }
  
  /**
   * returns DDL to drop specified schema
   *
   * @return string
   */
  public function get_drop_sql($node_schema) {
    $ddl = "DROP SCHEMA " . mysql4::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . ";\n";
    return $ddl;
  }

}

?>
