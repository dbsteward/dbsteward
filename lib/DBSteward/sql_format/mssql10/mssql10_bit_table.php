<?php
/**
 * Manipulate sequence nodes
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

/**
 * mssql10_bit_table creates functional equivalents to postgresql sequences
 * with simple tables named after the sequence, with exposed identity columns
 *
 */
class mssql10_bit_table {

  /**
   * Creates and returns SQL command for creation of the sequence.
   *
   * @return created SQL command
   */
  public function get_creation_sql($node_schema, $node_sequence) {
    if (isset($node_sequence['start']) && !is_numeric((string)$node_sequence['start'])) {
      throw new exception("start value is not numeric: " . $node_sequence['start']);
    }
    if (isset($node_sequence['inc']) && !is_numeric((string)$node_sequence['inc'])) {
      throw new exception("increment by value is not numeric: " . $node_sequence['inc']);
    }

    $sequence_name = mssql10::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . mssql10::get_quoted_name($node_sequence['name'], dbsteward::$quote_table_names);

    $ddl = "-- this is a pgsql8 equivalency implementation. to use it as a stand alone auto incrementing sequence in mssql, use a query such as\n";
    $ddl.= "-- INSERT INTO $sequence_name (place_holder) VALUES (1) SELECT SCOPE_IDENTITY()\n";
    $ddl.= "CREATE TABLE " . $sequence_name . " (" . "\n" . "  id BIGINT IDENTITY(" . $node_sequence['start'] . "," . $node_sequence['inc'] . ")," . "\n" . "  place_holder bit NOT NULL" . "\n" . ");\n";

    // @IMPLEMENT: $node_sequence['cache'] ?
    // @IMPLEMENT: $node_sequence['cycle'] ?
    // @IMPLEMENT: $node_sequence['min'] ?
    // @IMPLEMENT: $node_sequence['max'] ?
    // @IMPLEMENT: $node_sequence['owner'] ?
    // @IMPLEMENT: $node_sequence['description'] ?
    return $ddl;
  }

  /**
   * Creates and returns SQL command for dropping the sequence.
   *
   * @return string
   */
  public function get_drop_sql($node_schema, $node_sequence) {
    $ddl = "DROP TABLE " . mssql10::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . mssql10::get_quoted_name($node_sequence['name'], dbsteward::$quote_table_names) . ";\n";
    return $ddl;
  }
}

?>
