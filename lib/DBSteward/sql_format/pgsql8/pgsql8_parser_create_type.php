<?php
/**
 * Parses CREATE TYPE commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_create_type {
  /**
   * Pattern for getting type name from CREATE TYPE.
   */
  const PATTERN_TYPE_NAME = "/CREATE[\\s]+TYPE[\\s]+\"?([^\\s\"]+)\"?[\\s]*AS[\\s]*/i";

  /**
   * Pattern for parsing column definition.
   */
  const PATTERN_COLUMN = "/\"?([^\\s\"]+)\"?[\\s]+(.*)/i";

  /**
   * Pattern for checking whether string contains WITH OIDS string.
   */
  const PATTERN_WITH_OIDS = "/.*WITH[\\s]+OIDS.*/i";

  /**
   * Pattern for checking whether string contains WITHOUT OIDS string.
   */
  const PATTERN_WITHOUT_OIDS = "/.*WITHOUT[\\s]+OIDS.*/i";

  /**
   * Parses CREATE TYPE command.
   *
   * @param database database
   * @param command CREATE TYPE command
   */
  public static function parse($database, $command) {
    $line = $command;

    if (preg_match(self::PATTERN_TYPE_NAME, $line, $matches) > 0) {
      $typeName = trim($matches[1]);
      $line = preg_replace(self::PATTERN_TYPE_NAME, '', $line);
    } else {
      throw new exception("Cannot parse command: " . $line);
    }

    $type = new pgsql8_type(sql_parser::get_object_name($typeName));
    $schemaName = sql_parser::get_schema_name($typeName, $database);
    $schema = $database->get_schema($schemaName);

    if ($schema == null) {
      throw new exception("Cannot get schema '" . $schemaName . "'. Need to issue 'CREATE SCHEMA " . $schemaName . ";' before 'CREATE TYPE " . $typeName . "...;'?");
    }

    $schema->add_type($type);
    self::parse_rows($type, sql_parser::remove_last_semicolon($line));
  }

    /**
     * Parses COLUMN and other DDL within '(' and ')' in CREATE TYPE
     * definition.
     *
     * @param type type being parsed
     * @param line line being processed
     *
     * @throws ParserException Thrown if problem occurred while parsing DDL.
     */
    private static function parse_column_defs($type, $line) {
      if (strlen($line) > 0) {
        $matched = false;

        if (!$matched) {
          if (preg_match(self::PATTERN_COLUMN, $line, $matches) > 0) {
            $column = new pgsql8_column(trim($matches[1]));
            $column->parse_definition(trim($matches[2]));
            $type->add_column($column);
            $matched = true;
          }
        }

        if (!$matched) {
          throw new exception("Cannot parse command: " . $line);
        }
      }
    }

  /**
  * Parses definitions that are present after column definition is
  * closed with ')'.
  *
  * @param type type being parsed
  * @param commands commands being processed
  *
  * @return true if the command was the last command for CREATE TYPE,
  *         otherwise false
  */
  private static function parse_post_columns(&$type, $commands) {
    $line = $commands;

    if (preg_match(self::PATTERN_WITH_OIDS, $line, $matches) > 0) {
      dbx::set_attribute($node_table, 'withOIDS', 'true');
      $line = sql_parser::removeSubString($line, "WITH OIDS");
    } else if (preg_match(self::PATTERN_WITHOUT_OIDS, $line, $matches) > 0) {
      dbx::set_attribute($node_table, 'withOIDS', 'false');
      $line = sql_parser::removeSubString($line, "WITHOUT OIDS");
    }

    return $line;
  }

  /**
  * Parses all rows in CREATE TYPE command.
  *
  * @param type type being parsed
  * @param command command without 'CREATE SEQUENCE ... (' string
  *
  * @throws ParserException Thrown if problem occurred with parsing of DDL.
  */
  private static function parse_rows($type, $command) {
    $line = $command;
    $post_columns = false;

    while(strlen($line) > 0) {
      $commandEnd = sql_parser::get_command_end($line, 0);
      $subCommand = trim(substr($line, 0, $commandEnd));

      if ($post_columns) {
        $line = self::parse_post_columns($type, $subCommand);
        break;
      } else if (substr($line, $commandEnd, 1) == ')') {
        $post_columns = true;
      }

      self::parse_column_defs($type, $subCommand);
      $line = ($commandEnd >= strlen($line)) ? "" : substr($line, $commandEnd + 1);
    }

    $line = trim($line);

    if (strlen($line) > 0) {
      throw new exception("Cannot parse CREATE TYPE '" . $type->get_name() . "' - do not know how to parse '" . $line . "'");
    }
  }
}

?>
