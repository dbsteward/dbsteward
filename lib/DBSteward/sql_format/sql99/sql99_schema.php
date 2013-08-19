<?php
/**
 * Manipulate schema nodes
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class sql99_schema {

  /**
   * Returns true if schema contains type with given $name, otherwise false.
   *
   * @param $name      name of the type
   *
   * @return boolean   true if schema contains type with given $name, otherwise false.
   */
  public static function contains_type(&$node_schema, $name) {
    $found = false;

    foreach(dbx::get_types($node_schema) as $type) {
      if (strcasecmp($type['name'], $name) == 0) {
        $found = true;
        break;
      }
    }

    return $found;
  }

  /**
   * Returns true if schema contains function with given $declaration, otherwise false.
   *
   * @param $declaration   declaration of the function
   *
   * @return true if schema contains function with given $declaration, otherwise false
   */
  public static function contains_function($node_schema, $declaration) {
    $found = false;

    foreach(dbx::get_functions($node_schema) as $node_function) {
      if (strcasecmp(format_function::get_declaration($node_schema, $node_function, FALSE), $declaration) == 0) {
        $found = true;
        break;
      }
    }

    return $found;
  }

  /**
   * Returns true if schema contains sequence with given $name, otherwise false.
   *
   * @param $name      name of the sequence
   *
   * @return boolean   true if schema contains sequence with given $name, otherwise false
   */
  public static function contains_sequence($node_schema, $name) {
    $found = false;

    foreach(dbx::get_sequences($node_schema) as $sequence) {
      if (strcasecmp($sequence['name'], $name) == 0) {
        $found = true;
        break;
      }
    }

    return $found;
  }

  /**
   * Returns true if schema contains table with given $name, otherwise false.
   *
   * @param $name      name of the table
   *
   * @return boolean   true if schema contains table with given $name, otherwise false.
   */
  public static function contains_table($node_schema, $name) {
    $found = false;

    if (is_null($node_schema)) {
      return false;
    }

    foreach(dbx::get_tables($node_schema) as $table) {
      if (strcasecmp($table['name'], $name) == 0) {
        $found = true;
        break;
      }
    }

    return $found;
  }
  
  /**
   * Return schema and table (by reference) that claim to be the $old_schema and $old_table specified
   * 
   * @param type $db_doc
   * @param type $old_schema
   * @param type $old_table
   * @param type $prince_schema
   * @param type $prince_table
   * @return boolean
   * @throws exception
   */
  public static function table_formerly_known_as($db_doc, $old_schema, $old_table, &$prince_schema = NULL, &$prince_table = NULL) {
    if ( dbsteward::$ignore_oldnames ) {
      throw new exception("dbsteward::ignore_oldname option is on, table_formerly_known_as() should not be getting called");
    }

    foreach(dbx::get_schemas($db_doc) as $doc_schema) {
      foreach(dbx::get_tables($doc_schema) as $doc_table) {
        // does doc_table claim to be old_table in a former life?
        if (strcasecmp($old_table['name'], $doc_table['oldTableName']) == 0) {
          // does doc_table define an old schema?
          if ( isset($doc_table['oldSchemaName']) ) {
            // is the table referring to it having the current old_schema?
            if (strcasecmp($doc_table['oldSchemaName'], $old_schema['name']) != 0) {
              // keep looking
              continue;
            }
            // schema is the same
            // fall into pointering
          }
          else if (strcasecmp($doc_schema['name'], $old_schema['name']) != 0) {
            // this is not the right schema, continue
            continue;
          }
          $prince_schema = $doc_schema;
          $prince_table = $doc_table;
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Does the schema contain a view by the name $name ?
   *
   * @param $name      name of the view
   *
   * @return boolean
   */
  public static function contains_view($node_schema, $name) {
    $found = false;

    foreach(dbx::get_views($node_schema) as $view) {
      if (strcasecmp($view['name'], $name) == 0) {
        $found = true;
        break;
      }
    }

    return $found;
  }
}

?>
