<?php
/**
 * Parses ALTER VIEW commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_alter_view {
  /**
   * Pattern for matching ALTER VIEW ... OWNER TO ...;.
   */
  const PATTERN_OWNER = "/^ALTER[\\s]+VIEW[\\s]+([^\\s]+)[\\s]+OWNER[\\s]+TO[\\s]+(.*);$/i";

  /**
   * Parses ALTER VIEW command.
   *
   * @param database database
   * @param command ALTER VIEW command
   *
   */
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN_OWNER, $command, $matches) > 0) {
      $line = $command;

      $view_name = trim($matches[1]);
      // make all view name fully qualified
      // default_schema will make set search path induced schemas come through correctly
      $view_schema_name = sql_parser::get_schema_name($view_name, $database);
      $view_name = sql_parser::get_object_name($view_name);
      $owner_name = trim($matches[2]);

      $schema = $database->get_schema($view_schema_name);
      if ( $schema === null ) {
        throw new exception("Failed to find view schema " . $view_schema_name);
      }
      $view = $schema->get_view($view_name);
      if ( $view === null ) {
        throw new exception("Failed to find view " . $view_name . " in schema " . $schema->get_name());
      }
      $view->set_owner($owner_name);
    }
    // no match, don't know what to do
    else {
      throw new exception("Cannot parse command: " . $command);
    }
  }
}

?>
