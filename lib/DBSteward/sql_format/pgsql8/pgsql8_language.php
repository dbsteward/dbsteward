<?php
/**
 * Manipulate postgresql language defeinition nodes
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_language {

  /**
   * Creates and returns SQL for creation of trigger.
   *
   * @return created SQL
   */
  public static function get_creation_sql($node_language) {
    /*
    CREATE [ PROCEDURAL ] LANGUAGE name
    CREATE [ TRUSTED ] [ PROCEDURAL ] LANGUAGE name
      HANDLER call_handler [ VALIDATOR valfunction ]
    /**/
    $ddl = "CREATE "
      . ( strcasecmp(dbsteward::string_cast($node_language['trusted']), 'true') == 0 ? "TRUSTED " : "" )
      . ( strcasecmp(dbsteward::string_cast($node_language['procedural']), 'true') == 0 ? "PROCEDURAL " : "" )
      . "LANGUAGE "
      . pgsql8::get_quoted_object_name($node_language['name'])
      . ( strlen($node_language['handler']) > 0 ? "HANDLER " . pgsql8::get_quoted_function_name($node_language['handler']) : "" )
      . ( strlen($node_language['validator']) > 0 ? "VALIDATOR " . pgsql8::get_quoted_function_name($node_language['validator']) : "" )
      . " ;\n";

    if ( strlen($node_language['owner']) > 0 ) {
      $ddl .= "ALTER "
        . ( strcasecmp(dbsteward::string_cast($node_language['procedural']), 'true') == 0 ? "PROCEDURAL " : "" )
        . "LANGUAGE "
        . pgsql8::get_quoted_object_name($node_language['name'])
        . " OWNER TO " . xml_parser::role_enum(dbsteward::$new_database, $node_language['owner'])
        . " ;\n";
    }

    return $ddl;
  }

  /**
   * Creates and returns SQL for dropping the language.
   *
   * @return string
   */
  public static function get_drop_sql($node_language) {
    $ddl = "DROP "
      . ( strcasecmp(dbsteward::string_cast($node_language['procedural']), 'true') == 0 ? "PROCEDURAL " : "" )
      . " LANGUAGE "
      . pgsql8::get_quoted_object_name($node_language['name'])
      . " ;";
    return $ddl;
  }

  public static function equals($lang_a, $lang_b) {
    if ( strcasecmp($lang_a['name'], $lang_b['name']) != 0 ) {
      return false;
    }

    $equals =
      strcasecmp($lang_a['trusted'], $lang_b['trusted']) == 0
      && strcasecmp($lang_a['procedural'], $lang_b['procedural']) == 0
      && strcasecmp($lang_a['handler'], $lang_b['handler']) == 0
      && strcasecmp($lang_a['validator'], $lang_b['validator']) == 0;

    return $equals;
  }

}

?>
