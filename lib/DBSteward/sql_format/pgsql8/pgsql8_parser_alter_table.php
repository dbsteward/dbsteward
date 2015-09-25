<?php
/**
 * Parses ALTER TABLE commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_alter_table {
  /**
   * Pattern for matching ALTER TABLE ... OWNER TO ...;.
   */
  const PATTERN_OWNER = "/^ALTER[\\s]+TABLE[\\s]+(.*)[\\s]+OWNER[\\s]+TO[\\s]+(.*).*$/i";

  /**
   * Pattern for matching table name and optional definition.
   */
  const PATTERN_START = "/ALTER[\\s]+TABLE[\\s]+(?:ONLY[\\s]+)?\"?([^\\s\"]+)\"?(?:[\\s]+)?(.+)?/i";

  /**
   * Pattern for matching ADD CONSTRAINT row.
   */
  const PATTERN_ADD_CONSTRAINT = "/^ADD[\\s]+CONSTRAINT[\\s]+\"?([^\\s\"]+)\"?[\\s]+(.*)$/i";

  /**
   * Pattern for matching ADD FOREIGN KEY row.
   */
  const PATTERN_ADD_FOREIGN_KEY = "/^ADD[\\s]+(FOREIGN[\\s]+KEY[\\s]+\\(([^\\s]+)\\)[\\s]+.*)$/i";

  /**
   * Pattern for matching ADD CONSTRAINT ... FOREIGN KEY row.
   */
  const PATTERN_ADD_CONSTRAINT_FOREIGN_KEY = "/^ADD[\\s]+CONSTRAINT[\\s]+(.*)[\\s]+(FOREIGN[\\s]+KEY[\\s]+\\(([^\\s]+)\\)[\\s]+.*)$/i";

  /**
   * Pattern for matching ADD PRIMARY KEY constraint.
   */
  const PATTERN_ADD_PRIMARY_KEY = "/^ADD[\\s]+(PRIMARY[\\s]+KEY[\\s]+\(\"?([^\"].*)\"?\))$/i";

  /**
   * Pattern for matching ALTER COLUMN ... SET DEFAULT ...
   */
  const PATTERN_SET_DEFAULT = "/^ALTER[\\s]+COLUMN[\\s]+\"?([^\\s\"]+)\"?[\\s]+SET[\\s]+DEFAULT[\\s]+(.*)$/i";

  /**
   * Pattern for checking whether string is ALTER COLUMN.
   */
  const PATTERN_ALTER_COLUMN = "/ALTER[\\s]+COLUMN/i";

  /**
   * Pattern for checking whether string is ALTER COLUMN.
   */
  const PATTERN_ALTER_COLUMN_STATISTICS = "/ALTER[\\s]+(?:COLUMN[\\s]+)([\\w]+)[\\s]+SET[\\s]+STATISTICS[\\s]+([\\d]+)/i";

  /**
   * pattern to match to CLUSTER ON statements
   */
  const PATTERN_CLUSTER_ON = "/CLUSTER[\s]+ON[\s]+([\w]+)/i";

  /**
   * match ENABLE or DISABLE trigger ALTER TABLE statements
   */
  const PATTERN_TRIGGER = "/(ENABLE|DISABLE[\\s]+TRIGGER)[\\s]+\"?([^\\s;\"]+)\"?[\\s]*[;]{0,1}/i";

  /**
   * Parses ALTER TABLE command.
   *
   * @param database database
   * @param command ALTER TABLE command
   *
   */
  public static function parse($database, $command) {
    $line = $command;
    if (preg_match(self::PATTERN_OWNER, $command, $matches) > 0) {
      $table_name = trim($matches[1]);
      $table_owner = sql_parser::remove_last_semicolon(trim($matches[2]));
      $schema_name = sql_parser::get_schema_name($table_name, $database);
      $node_schema = dbx::get_schema($database, $schema_name);
      if ( $node_schema == null ) {
        throw new exception("schema " . $schema_name . " not found in database object");
      }
      // is it actually a sequence ownership reference?
      if ( substr($table_name, -4) == '_seq' ) {
        //@TODO: figure out what sequences are not table linked and need to have ownership set on them
      }
      else {
        $node_table = dbx::get_table($node_schema, sql_parser::get_object_name($table_name));
        if ( $node_table == null ) {
          throw new exception("table " . sql_parser::get_object_name($table_name) . " not found in " . $node_schema['name'] . " schema object");
        }
        dbx::set_attribute($node_table, 'owner', $table_owner);
      }
    }
    else {
      if (preg_match(self::PATTERN_START, $line, $matches) > 0) {
        $table_name = trim($matches[1]);
      } else {
        throw new exception("Cannot parse command: " . $line);
      }

      $schema_name = sql_parser::get_schema_name($table_name, $database);
      $node_schema = dbx::get_schema($database, $schema_name);
      $node_table = dbx::get_table($node_schema, sql_parser::get_object_name($table_name));
      $line = sql_parser::remove_last_semicolon($matches[2]);

      self::parse_rows($database, $node_schema, $node_table, $line);
    }
  }

  /**
   * Parses all rows in ALTER TABLE command.
   *
   * @param table table being parsed
   * @param commands commands
   *
   * @throws ParserException Thrown if problem occured while parsing DDL.
   */
  private static function parse_rows(&$db_doc, &$node_schema, &$node_table, $commands) {
    $line = $commands;
    $subCommand = null;

    while (strlen($line) > 0) {
      $commandEnd = sql_parser::get_command_end($line, 0);
      $subCommand = trim(substr($line, 0, $commandEnd));
      $line = ($commandEnd >= strlen($line) ? "" : substr($line, $commandEnd + 1));

      if (strlen($subCommand) > 0) {
        if (preg_match(self::PATTERN_ADD_CONSTRAINT_FOREIGN_KEY, $subCommand, $matches) > 0) {
          $column_name = trim($matches[3]);
          $constraint_name = trim($matches[1]);
          $node_constraint = pgsql8_constraint::get_table_constraint($db_doc, $node_table, $constraint_name, true);
          dbx::set_attribute($node_constraint, 'definition', trim($matches[2]));
          $subCommand = "";
        }
      }

      if (preg_match(self::PATTERN_ADD_CONSTRAINT, $subCommand, $matches) > 0) {
        $constraint_name = trim($matches[1]);
        $node_constraint = pgsql8_constraint::get_table_constraint($db_doc, $node_table, $constraint_name, true);
        dbx::set_attribute($node_constraint, 'definition', trim($matches[2]));
        $subCommand = "";
      }

      if (strlen($subCommand) > 0) {
        if (preg_match(self::PATTERN_ADD_PRIMARY_KEY, $subCommand, $matches) > 0) {
          $definition = trim($matches[1]);
          $column_name = trim($matches[2]);
          $constraint_name = $node_table['name'] . '_pkey';
          dbx::set_attribute($node_table, 'primaryKey', $column_name);
          $subCommand = "";
        }
      }

      if (strlen($subCommand) > 0) {
        if (preg_match(self::PATTERN_ADD_FOREIGN_KEY, $subCommand, $matches) > 0) {
          $column_name = trim($matches[2]);
          $constraint_name = pgsql8::identifier_name($node_schema['name'], $node_table['name'], $column_name, '_fkey');
          $node_constraint = pgsql8_constraint::get_table_constraint($db_doc, $node_table, $constraint_name, true);
          dbx::set_attribute($node_constraint, 'definition', trim($matches[1]));
          $subCommand = "";
        }
      }

      if (strlen($subCommand) > 0) {
        if (preg_match(self::PATTERN_SET_DEFAULT, $subCommand, $matches) > 0) {
          $column_name = trim($matches[1]);
          $default_value = trim($matches[2]);

          if ($node_table->contains_column($column_name)) {
            $node_column = &dbx::get_column($node_table, $column_name);
            dbx::set_attribute($node_column, 'default', $default_value);
          } else {
            throw new exception("Cannot find column '" . $column_name . " 'in table '" . $node_table['name'] . "'");
          }
          $subCommand = "";
        }
      }

      if (preg_match(self::PATTERN_ALTER_COLUMN_STATISTICS, $subCommand, $matches) > 0) {
        $column_name = trim($matches[2]);
        $value = trim($matches[3]);
        $node_column = &dbx::get_column($node_table, $column_name);
        dbx::set_attribute($node_column, 'statistics', $value);
        $subCommand = "";
      }

      if (preg_match(self::PATTERN_CLUSTER_ON, $subCommand, $matches) > 0) {
        $indexName = trim($matches[1]);
        dbx::set_attribute($node_column, 'clusterIndexName', $indexName);
        $subCommand = "";
      }

      if (strlen($subCommand) > 0) {
        if (preg_match(self::PATTERN_TRIGGER, $subCommand, $matches) > 0) {
          $triggerName = trim($matches[2]);
          throw new exception("@TODO: do something with ALTER TABLE ... ENABLE / DISABLE trigger statements");
          $subCommand = "";
        }
      }

      if (strlen($subCommand) > 0) {
        throw new exception("Don't know how to parse: " . $subCommand);
      }
    }
  }
}

?>
