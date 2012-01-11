<?php
/**
 * Parses CREATE VIEW commands.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_create_view.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_parser_create_view {
  /**
   * Pattern for parsing CREATE VIEW definition.
   */
  const PATTERN = "/CREATE[\\s]+(?:OR[\\s]+REPLACE[\\s]+)?VIEW[\\s]+\"?([^\\s\"]+)\"?[\\s]+(?:\\(([^)]+)\\)[\\s]+)?AS[\\s]+(.+)?(?:;)/i";

  /**
   * Parses CREATE VIEW command.
   *
   * @param database database
   * @param command CREATE VIEW command
   */
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN, trim($command), $matches) > 0) {
      $view_name = $matches[1];
      $column_names = $matches[2];
      $query = $matches[3];

      if ( strlen($view_name) == 0 || strlen($query) == 0 ) {
        throw new exception("Cannot parse command: " . $command);
      }

      $node_schema = &dbx::get_schema($database, sql_parser::get_schema_name($view_name, $database));
      $node_view = &dbx::get_view($node_schema, sql_parser::get_object_name($view_name, $database), true);
      $node_view->addChild('viewQuery', $query);
    } else {
      throw new exception("Cannot parse command: " . $command);
    }
  }
}

?>
