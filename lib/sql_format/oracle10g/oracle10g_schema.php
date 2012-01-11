<?php
/**
 * schema node manipulation
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: oracle10g_schema.php 2267 2012-01-09 19:50:46Z nkiraly $
 */

class oracle10g_schema extends sql99_schema {
  
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
      $schema_name = oracle10g_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names);

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
    $ddl = "DROP SCHEMA " . oracle10g_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . ";\n";
    return $ddl;
  }

}

?>
