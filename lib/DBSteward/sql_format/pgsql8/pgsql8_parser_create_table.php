<?php
/**
 * Parses CREATE TABLE commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_create_table {
  /**
   * Pattern for getting table name from CREATE TABLE.
   */
  const PATTERN_TABLE_NAME = "/CREATE[\\s]+TABLE[\\s]+\"?([^\\s\"]+)\"?[\\s]*\\(/i";

  /**
   * Pattern for getting CONSTRAINT parameters.
   */
  const PATTERN_CONSTRAINT = "/CONSTRAINT[\\s]+\"?([^\\s\"]+)\"?[\\s]+(.*)/i";

  /**
   * Pattern for parsing column definition.
   */
  const PATTERN_COLUMN = "/\"?([^\\s\"]+)\"?[\\s]+(.*)/i";

  /**
   * Pattern for parsing INHERITS.
   */
  const PATTERN_INHERITS = "/INHERITS[\\s]+([^;]+)[;]?/i";

  /**
   * Pattern for checking whether string contains WITH OIDS string.
   */
  const PATTERN_WITH_OIDS = "/.*WITH[\\s]+OIDS.*/i";

  /**
   * Pattern for checking whether string contains WITHOUT OIDS
   * string.
   */
  const PATTERN_WITHOUT_OIDS = "/.*WITHOUT[\\s]+OIDS.*/i";

  /**
   * Parses CREATE TABLE command.
   *
   * @param database database
   * @param command CREATE TABLE command
   *
   */
  public static function parse($database, $command) {
    $line = $command;

    if (preg_match(self::PATTERN_TABLE_NAME, $line, $matches) > 0) {
      $table_name = trim($matches[1]);
      $line = preg_replace(self::PATTERN_TABLE_NAME, '', $line);
    } else {
      throw new exception("Cannot parse command: " . $line);
    }
    $table_name = sql_parser::get_schema_name($table_name, $database) . '.' . sql_parser::get_object_name($table_name);

    $schema_name = sql_parser::get_schema_name($table_name, $database);
    $node_schema = dbx::get_schema($database, $schema_name);
    if ($node_schema == null) {
      throw new exception("Cannot get schema '" . $schema_name . "'. Need to issue 'CREATE SCHEMA " . $schema_name . ";' before 'CREATE TABLE " . $table_name . "...;'?");
    }
    $node_table = dbx::get_table($node_schema, sql_parser::get_object_name($table_name), true);

    self::parse_rows($node_schema, $node_table, sql_parser::remove_last_semicolon($line));
  }

  /**
   * Parses COLUMN and other DDL within '(' and ')' in CREATE TABLE
   * definition.
   *
   * @param table table being parsed
   * @param line line being processed
   */
  private static function parse_column_defs(&$node_schema, &$node_table, $line) {
    if (strlen($line) > 0) {
      $matched = false;

      if (preg_match(self::PATTERN_CONSTRAINT, trim($line), $matches) > 0) {
        $node_constraint = &dbx::create_table_constraint($node_table, trim($matches[1]));
        dbx::set_attribute($node_constraint, 'definition', trim($matches[2]));
        //@TODO: type?
        $matched = true;
      }

      if (!$matched) {
        if (preg_match(self::PATTERN_COLUMN, $line, $matches) > 0) {
          $node_column = &dbx::get_table_column($node_table, trim($matches[1]), true);
          pgsql8_column::parse_definition($node_schema, $node_table, $node_column, trim($matches[2]));
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
   * @param table table being parsed
   * @param commands commands being processed
   *
   * @return boolean command was the last command for CREATE TABLE
   */
  private static function parse_post_columns(&$node_table, $commands) {
    $line = $commands;

    if (preg_match(self::PATTERN_INHERITS, $line, $matches) > 0) {
      dbx::set_attribute($node_table, 'inherits', trim($matches[1]));
      $line = preg_replace(self::PATTERN_INHERITS, '', $line);
    }

    if (preg_match(self::PATTERN_WITH_OIDS, $line, $matches) > 0) {
      dbx::set_attribute($node_table, 'withOIDS', 'true');
      $line = str_ireplace("WITH OIDS", '', $line);
    } else if (preg_match(self::PATTERN_WITHOUT_OIDS, $line, $matches) > 0) {
      dbx::set_attribute($node_table, 'withOIDS', 'false');
      $line = str_ireplace("WITHOUT OIDS", '', $line);
    }

    return $line;
  }

  /**
   * Parses all rows in CREATE TABLE command.
   *
   * @param $node_schema   schema table belongs to
   * @param $node_table    table being parsed
   * @param $command  command without 'CREATE TABLE ... (' string
   */
  private static function parse_rows(&$node_schema, &$node_table, $command) {
    $line = $command;
    $post_columns = false;

    while (strlen($line) > 0) {
      $command_end = sql_parser::get_command_end($line, 0);
      $subCommand = trim(substr($line, 0, $command_end));

      if ($post_columns) {
        $line = self::parse_post_columns($node_table, $subCommand);
        break;
      } else if (substr($line, $command_end, 1) == ')') {
        $post_columns = true;
      }

      // look for modifier tokens and act accordingly
      $tokens = preg_split("/[\s]+/", $subCommand, -1, PREG_SPLIT_NO_EMPTY);
      // start at 2, first is always name, second is always type
      for($i = 2; $i < count($tokens); $i++) {
        if ( strcasecmp($tokens[$i], 'UNIQUE') == 0 ) {
          // CREATE TABLE test_table (
          //   test_table_id varchar(64) PRIMARY KEY,
          //   test_table_col_c varchar(100) UNIQUE NOT NULL
          // );
          // NOTICE:  CREATE TABLE / PRIMARY KEY will create implicit index "test_table_pkey" for table "test_table"
          // NOTICE:  CREATE TABLE / UNIQUE will create implicit index "test_table_test_table_col_c_key" for table "test_table"
          dbsteward::debug("NOTICE:  CREATE TABLE with UNIQUE column attribute -- creating implicit index \"" . pgsql8_index::index_name(sql_parser::get_object_name($node_table->get_name()), sql_parser::get_object_name($tokens[0]), 'key') . "\" for table \"" . $node_schema->get_name() . '.' . $node_table->get_name() . "\"");
          $node_index = &dbx::create_table_index($node_table, pgsql8_index::index_name(sql_parser::get_object_name($node_table['name']), sql_parser::get_object_name($tokens[0]), 'key'));
          dbx::set_attribute($node_index, 'unique', 'true');
          dbx::set_attribute($node_index, 'using', 'btree');
          $node_index->addChild('indexDimension', sql_parser::get_object_name($tokens[0]))
            ->addAttribute('name', $tokens[0] . '_unq');

          // make sure we don't process this token again
          unset($tokens[$i]);
          $tokens = array_merge($tokens);
          $i--;
          continue;
        }
        // @TODO: other cases?
        // other cases is how you would fix pgsql8_column::parse_definition() throwing 'column definition parse fail' exceptions
      }
      $subCommand = implode(' ', $tokens);

      self::parse_column_defs($node_schema, $node_table, $subCommand);
      $line = ($command_end >= strlen($line)) ? "" : substr($line, $command_end + 1);
    }

    $line = trim($line);

    if (strlen($line) > 0) {
      throw new exception("Cannot parse CREATE TABLE '" . $node_table['name'] . "' - do not know how to parse '" . $line . "'");
    }
  }
}

?>
