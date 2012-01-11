<?php
/**
 * Parses CREATE SCHEMA commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_create_schema {
  /**
   * Pattern for parsing CREATE SCHEMA ... AUTHORIZATION ...
   */
  const PATTERN_CREATE_SCHEMA = "/^CREATE[\\s]+SCHEMA[\\s]+([^\\s;]+)(?:[\\s]+AUTHORIZATION[\\s]+([^;]+))?[\\s]*;$/i";

  /**
   * Pattern for parsing CREATE SCHEMA AUTHORIZATION ...
   */
  const PATTERN_CREATE_SCHEMA_AUTHORIZATION = "/^CREATE[\\s]+SCHEMA[\\s]+AUTHORIZATION[\\s]+([^\\s;]+);$/i";

  /**
   * Parses CREATE SCHEMA command.
   *
   * @param database database
   * @param command CREATE SCHEMA command
   */
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN_CREATE_SCHEMA, $command, $matches) > 0) {
      $node_schema = dbx::get_schema($database, $matches[1], true);
      if ( isset($matches[2]) ) {
        dbx::set_attribute($node_schema, 'authorization', $matches[2]);
      }
    } else if (preg_match(self::PATTERN_CREATE_SCHEMA_AUTHORIZATION, $command, $matches) > 0) {
      $node_schema = dbx::get_schema($database, $matches[1], true);
      dbx::set_attribute($node_schema, 'authorization', $node_schema['name']);
    } else {
      throw new exception("Cannot parse command: " . $command);
    }
  }
}

?>
