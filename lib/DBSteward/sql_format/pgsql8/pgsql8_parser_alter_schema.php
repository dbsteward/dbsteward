<?php
/**
 * Parses ALTER SCHEMA commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
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
