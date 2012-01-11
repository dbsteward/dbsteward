<?php
/**
 * Parses CREATE TRIGGER commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_create_trigger {
  /**
   * Pattern for parsing CREATE TRIGGER command.
   */
  const PATTERN = "/^CREATE[\\s]+TRIGGER[\\s]+\"?([^\\s\"]+)\"?[\\s]+(BEFORE|AFTER)[\\s]+(INSERT|UPDATE|DELETE)(?:[\\s]+OR[\\s]+)?(INSERT|UPDATE|DELETE)?(?:[\\s]+OR[\\s]+)?(INSERT|UPDATE|DELETE)?[\\s]+ON[\\s]+\"?([^\\s\"]+)\"?[\\s]+(?:FOR[\\s]+)?(?:EACH[\\s]+)?(ROW|STATEMENT)?[\\s]+EXECUTE[\\s]+PROCEDURE[\\s]+([^;]+);$/i";

  /**
   * Parses CREATE TRIGGER command.
   *
   * @param database database
   * @param command CREATE TRIGGER command
   *
   * @throws ParserException Thrown if problem occured while parsing the
   *         command.
   */
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN, trim($command), $matches) > 0) {
      $trigger_name = trim($matches[1]);
      $when = $matches[2];
      $events = array();
      if ( strlen($matches[3]) > 0 ) {
        $events[] = $matches[3];
      }
      if ( strlen($matches[4]) > 0 ) {
        $events[] = $matches[4];
      }
      if ( strlen($matches[5]) > 0 ) {
        $events[] = $matches[5];
      }

      $table_name = trim($matches[6]);
      $fireOn = $matches[7];
      $procedure = $matches[8];

      $node_schema = &dbx::get_schema($database, sql_parser::get_schema_name($table_name, $database));
      $node_table = &dbx::get_table($node_schema, sql_parser::get_object_name($table_name));
      if ( $node_table == null ) {
        throw new exception("Failed to find trigger table " . $trigger->get_table_name());
      }
      $node_trigger = &dbx::get_table_trigger($node_schema, $node_table, $trigger_name, true);
      dbx::set_attribute($node_trigger, 'when', strcasecmp('BEFORE', $when) == 0 ? 'BEFORE' : 'AFTER');
      dbx::set_attribute($node_trigger, 'forEach', strcasecmp('ROW', $when) == 0 ? 'ROW' : 'STATEMENT');
      dbx::set_attribute($node_trigger, 'function', trim($procedure));
      dbx::set_attribute($node_trigger, 'event', implode(', ', $events));
    } else {
      throw new exception("Cannot parse command: " . $command);
    }
  }

}

?>
