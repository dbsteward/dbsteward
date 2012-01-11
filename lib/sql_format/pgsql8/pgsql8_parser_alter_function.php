<?php
/**
 * Parses ALTER FUNCTION commands.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_alter_function.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_parser_alter_function {
  /**
   * Pattern for matching ALTER FUNCTION ... OWNER TO ...;.
   */
  const PATTERN_OWNER = "/^ALTER[\\s]+FUNCTION[\\s]+([^\\s(]+)\\(([^)]*)\\)[\\s]+OWNER[\\s]+TO[\\s]+(.*);$/i";

  /**
   * Pattern for matching table name and optional definition.
   */
  const PATTERN_START = "/ALTER[\\s]+FUNCTION[\\s]+\"?([^\\s]+)\"?(.+)?/i";

  /**
   * Parses ALTER FUNCTION command.
   *
   * @param database database
   * @param command ALTER FUNCTION command
   *
   */
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN_OWNER, $command, $matches) > 0) {
      $line = $command;

      $function_name = trim($matches[1]);
      // make all functionName's fully qualified
      // default_schema will make set search path induced schemas come through correctly
      $function_name = sql_parser::get_schema_name($function_name, $database) . '.' . sql_parser::get_object_name($function_name);
      $arguments = $matches[2];
      $owner_name = trim($matches[3]);

      $node_schema = dbx::get_schema($database, sql_parser::get_schema_name($function_name, $database));
      if ( $node_schema == null ) {
        throw new exception("Failed to find function schema for " . $function_name);
      }
      $node_function = dbx::get_function($node_schema, sql_parser::get_object_name($function_name));
      if ( $node_function == null ) {
        throw new exception("Failed to find function " . $function_name . " in schema " . $node_schema['name']);
      }
      dbx::set_attribute($node_function, 'owner', $owner_name);
    }
    // no match, don't know what to do
    else {
      throw new exception("Cannot parse command: " . $command);
    }
  }
}

?>
