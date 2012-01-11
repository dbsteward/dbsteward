<?php
/**
 * MSSQL schema node manipulation
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: mssql10_schema.php 2267 2012-01-09 19:50:46Z nkiraly $
 */

class mssql10_schema extends sql99_schema {

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
      $schema_name = mssql10_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names);

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
    $ddl = "DROP SCHEMA " . mssql10_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . ";\n";
    return $ddl;
  }

  /**
   * Returns true if schema contains function with given $declaration, otherwise false.
   *
   * @param $declaration   declaration of the function
   *
   * @return true if schema contains function with given $declaration, otherwise false
   */
  public function contains_function($node_schema, $declaration) {
    $found = FALSE;

    foreach (dbx::get_functions($node_schema) as $node_function) {
      if (strcasecmp(mssql10_function::get_declaration($node_schema, $node_function), $declaration) == 0) {
        $found = TRUE;
        break;
      }
    }

    return $found;
  }
}

?>
