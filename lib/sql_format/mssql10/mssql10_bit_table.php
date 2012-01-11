<?php
/**
 * Manipulate sequence nodes
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: mssql10_bit_table.php 2261 2012-01-09 08:37:44Z nkiraly $
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

    $sequence_name = mssql10_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . mssql10_diff::get_quoted_name($node_sequence['name'], dbsteward::$quote_table_names);

    $ddl = "-- pgsql sequence equivalent implementation\n" . "CREATE TABLE " . $sequence_name . " (" . "\n" . "  id BIGINT IDENTITY(" . $node_sequence['start'] . "," . $node_sequence['inc'] . ")," . "\n" . "  place_holder bit NOT NULL" . "\n" . ");\n";

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
    $ddl = "DROP TABLE " . mssql10_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . mssql10_diff::get_quoted_name($node_sequence['name'], dbsteward::$quote_table_names) . ";\n";
    return $ddl;
  }
}

?>
