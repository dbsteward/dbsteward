<?php
/**
 * Manipulate postgresql schema nodes
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_schema extends sql99_schema {

  /**
   * Creates and returns SQL for creation of the schema.
   *
   * @return created SQL
   */
  public function get_creation_sql($node_schema) {
    if ( strcasecmp('public', $node_schema['name']) == 0 ) {
      // don't create the public schema
      $ddl = '';
    }
    else {
      $schema_name = pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names);
      $ddl = "CREATE SCHEMA " . $schema_name . ";\n";

      // schema ownership
      if (isset($node_schema['owner']) && strlen($node_schema['owner']) > 0) {
        // see dtd owner attribute enum: ROLE_OWNER, ROLE_APPLICATION, ROLE_SLONY
        // map ROLE_ enums to database->role->owner etc
        $ddl .= "ALTER SCHEMA " . $schema_name . " OWNER TO " . xml_parser::role_enum(dbsteward::$new_database, $node_schema['owner']) . ";\n";
      }

      // schema comment
      if (isset($node_schema['description']) && strlen($node_schema['description']) > 0) {
        $ddl .= "COMMENT ON SCHEMA " . $schema_name . " IS '" . pg_escape_string($node_schema['description']) . "';\n";
      }
    }

    return $ddl;
  }

  /**
   * returns DDL to drop specified schema
   *
   * @return string
   */
  public function get_drop_sql($node_schema) {
    $ddl = "DROP SCHEMA " . pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . " CASCADE;\n";
    return $ddl;
  }

}

?>
