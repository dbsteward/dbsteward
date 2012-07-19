<?php
/**
 * Manipulate postgresql sequence nodes
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_sequence {

  /**
   * Creates and returns SQL command for creation of the sequence.
   *
   * @return created SQL command
   */
  public function get_creation_sql($node_schema, $node_sequence) {
    if ( isset($node_sequence['start']) && !is_numeric((string)$node_sequence['start']) ) {
      throw new exception("start value is not numeric: " . $node_sequence['start']);
    }
    if ( isset($node_sequence['inc']) && !is_numeric((string)$node_sequence['inc']) ) {
      throw new exception("increment by value is not numeric: " . $node_sequence['inc']);
    }
    if ( isset($node_sequence['max']) && !is_numeric((string)$node_sequence['max']) ) {
      throw new exception("max value is not numeric: " . $node_sequence['max']);
    }
    if ( isset($node_sequence['min']) && !is_numeric((string)$node_sequence['min']) ) {
      throw new exception("min value is not numeric: " . $node_sequence['min']);
    }
    $cache = 1;
    if ( isset($node_sequence['cache']) ) {
      if ( !is_numeric((string)$node_sequence['cache']) ) {
        throw new exception($node_schema['name'] . '.' . $node_sequence['name'] . " cache parameter for is not numeric: " . $node_sequence['cache']);
      }
      $cache = (int)$node_sequence['cache'];
    }

    if ( isset($node_sequence['start']) ) {
      $start = (int)$node_sequence['start'];
    }
    else if ( isset($node_sequence['min']) ) {
      $start = (int)$node_sequence['min'];
    }
    else {
      $start = '1';
    }

    if ( isset($node_sequence['inc']) ) {
      $inc = (int)$node_sequence['inc'];
    }
    else {
      $inc = '1';
    }

    $max_value = (int)$node_sequence['max'];
    if ( $max_value > 0 ) {
      $max_value = 'MAXVALUE ' . $max_value;
    }
    else {
      $max_value = 'NO MAXVALUE';
    }

    $min_value = (int)$node_sequence['min'];
    if ( $min_value > 0 ) {
      $min_value = 'MINVALUE ' . $min_value;
    }
    else {
      $min_value = 'NO MINVALUE';
    }

    $cycle = 'NO CYCLE';
    if ( strlen($node_sequence['cycle']) > 0  && strcasecmp($node_sequence['cycle'], 'false') != 0) {
      $cycle = 'CYCLE';
    }

    $owned_by = '';
    if (strlen($node_sequence['ownedBy']) > 0) {
      $owned_by = " OWNED BY " . $node_sequence['ownedBy'];
    }

    $sequence_name = pgsql8::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8::get_quoted_name($node_sequence['name'], dbsteward::$quote_object_names);

    $ddl = "CREATE SEQUENCE " . $sequence_name . "
\tSTART WITH " . $start . "
\tINCREMENT BY " . $inc . "
\t" . $max_value . "
\t" . $min_value ."
\tCACHE " . $cache . "
\t" . $cycle . $owned_by . ";\n";

    // sequence ownership
    if ( isset($node_sequence['owner']) ) {
      $ddl .= "ALTER TABLE " . $sequence_name . " OWNER TO " . xml_parser::role_enum(dbsteward::$new_database, $node_sequence['owner']) . ";\n";
    }

    // sequence comment
    if (isset($schema['description']) && strlen($schema['description']) > 0) {
      $ddl .= "COMMENT ON SCHEMA " . $sequence_name . " IS '" . pg_escape_string($schema['description']) . "';\n";
    }

    return $ddl;
  }

  /**
   * Creates and returns SQL command for dropping the sequence.
   *
   * @return string
   */
  public function get_drop_sql($node_schema, $node_sequence) {
    $ddl = "DROP SEQUENCE " . pgsql8::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8::get_quoted_name($node_sequence['name'], dbsteward::$quote_object_names) . ";\n";
    return $ddl;
  }

}

?>
