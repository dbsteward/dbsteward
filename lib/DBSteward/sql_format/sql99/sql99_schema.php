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
  public function contains_type(&$node_schema, $name) {
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
  public function contains_function($node_schema, $declaration) {
    $found = false;

    foreach(dbx::get_functions($node_schema) as $node_function) {
      if (strcasecmp(format_function::get_declaration($node_schema, $node_function), $declaration) == 0) {
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
  public function contains_sequence($node_schema, $name) {
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
  public function contains_table($node_schema, $name) {
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
   * Returns name of table that says it used to be called $old_name
   *
   * @param   $old_name
   *
   * @return  string
   */
  public function table_name_by_old_name($node_schema, $old_name) {
    if ( dbsteward::$ignore_oldname ) {
      throw new exception("dbsteward::ignore_oldname option is on, column_name_by_old_name() should not be getting called");
    }
    
    $name = false;

    foreach(dbx::get_tables($node_schema) as $table) {
      if (strcasecmp($table['oldName'], $old_name) == 0) {
        $name = $table['name'];
        break;
      }
    }

    return $name;
  }

  /**
   * Does the schema contain a view by the name $name ?
   *
   * @param $name      name of the view
   *
   * @return boolean
   */
  public function contains_view($node_schema, $name) {
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
