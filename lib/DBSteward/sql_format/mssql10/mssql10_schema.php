<?php
/**
 * MSSQL schema node manipulation
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_schema extends sql99_schema {

  /**
   * Creates and returns SQL for creation of the schema.
   *
   * @return created SQL
   */
  public static function get_creation_sql($node_schema) {
    if (strcasecmp('dbo', $node_schema['name']) == 0) {
      // don't create the dbo schema
      $ddl = '';
    }
    else {
      $schema_name = mssql10::get_quoted_schema_name($node_schema['name']);

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
    $ddl = "DROP SCHEMA " . mssql10::get_quoted_schema_name($node_schema['name']) . ";\n";
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
