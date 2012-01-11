<?php
/**
 * Parses ALTER SCHEMA commands.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_alter_schema.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_parser_alter_schema {
  /**
   * Pattern for matching ALTER SCHEMA ... OWNER TO ...;.
   */
  const PATTERN_OWNER = "/^ALTER[\\s]+SCHEMA[\\s]+(.*)[\\s]+OWNER[\\s]+TO[\\s]+(.*);$/i";

  /**
   * Pattern for matching table name and optional definition.
   */
  const PATTERN_START = "/ALTER[\\s]+SCHEMA[\\s]+\"?([^\\s]+)\"?(.+)?/i";

  /**
   * Parses ALTER SCHEMA command.
   *
   * @param database database
   * @param command ALTER SCHEMA command
   *
   */
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN_OWNER, $command, $matches) > 0) {
      $line = $command;

      $schema_name = trim($matches[1]);
      $owner_name = trim($matches[2]);

      $schema = &dbx::get_schema($database, $schema_name);
      if ( $schema == null ) {
        throw new exception("failed to find schema " . $schema_name . " for alter owner statement");
      }
      dbx::set_attribute($schema, 'owner', $owner_name);
    }
    // no match, don't know what to do
    else {
      throw new exception("Cannot parse command: " . $command);
    }
  }
}

?>
