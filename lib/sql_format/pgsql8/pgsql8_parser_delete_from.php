<?php
/**
 * Parses DELETE FROM commands.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_delete_from.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_parser_delete_from {
  /**
   * pattern for first-pass breakdown of DELETE FROM command
   */
  const PATTERN_DELETE_FROM = "/^DELETE[\\s]+FROM[\\s]+(.*)[\\s]+WHERE[\\s]+(.*);$/i";

  /**
   * Parses DELETE FROM command.
   *
   * @param database database
   * @param command DELETE FROM command
   *
   */
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN_DELETE_FROM, $command, $matches) > 0) {
      $line = $command;

      $table_name = $matches[1];
      $where_clause = $matches[2];

      $table_name = sql_parser::get_schema_name($table_name, $database) . '.' . sql_parser::get_object_name($table_name);
      $schema = $database->get_schema(sql_parser::get_schema_name($table_name, $database));
      if ( $schema == null ) {
        throw new exception("Failed to find schema for data delete: " . sql_parser::get_schema_name($table_name, $database));
      }
      $table = $schema->get_table(sql_parser::get_object_name($table_name));
      if ( $table == null ) {
        throw new exception("Failed to find table for data delete: " . $table_name);
      }

      pgsql8_table::delete_data_row($table, $where_clause);
    }
    // no match, don't know what to do
    else {
      throw new exception("Cannot parse command: " . $command);
    }
  }

}

?>
