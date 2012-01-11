<?php
/**
 * Parses ALTER VIEW commands.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_alter_view.php 2261 2012-01-09 08:37:44Z nkiraly $
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
