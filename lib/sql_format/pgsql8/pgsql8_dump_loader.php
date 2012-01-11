<?php
/**
 * Load PostgreSQL .sql dump into dbsteward XML definition
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_dump_loader.php 2268 2012-01-09 19:53:59Z nkiraly $
 */

require_once dirname(__FILE__) . '/pgsql8_parser_alter_function.php';
require_once dirname(__FILE__) . '/pgsql8_parser_alter_language.php';
require_once dirname(__FILE__) . '/pgsql8_parser_alter_schema.php';
require_once dirname(__FILE__) . '/pgsql8_parser_alter_sequence.php';
require_once dirname(__FILE__) . '/pgsql8_parser_alter_table.php';
require_once dirname(__FILE__) . '/pgsql8_parser_alter_view.php';
require_once dirname(__FILE__) . '/pgsql8_parser_config_parameter.php';
require_once dirname(__FILE__) . '/pgsql8_parser_create_function.php';
require_once dirname(__FILE__) . '/pgsql8_parser_create_index.php';
require_once dirname(__FILE__) . '/pgsql8_parser_create_language.php';
require_once dirname(__FILE__) . '/pgsql8_parser_create_schema.php';
require_once dirname(__FILE__) . '/pgsql8_parser_create_sequence.php';
require_once dirname(__FILE__) . '/pgsql8_parser_create_table.php';
require_once dirname(__FILE__) . '/pgsql8_parser_create_type.php';
require_once dirname(__FILE__) . '/pgsql8_parser_create_trigger.php';
require_once dirname(__FILE__) . '/pgsql8_parser_create_view.php';
require_once dirname(__FILE__) . '/pgsql8_parser_delete_from.php';
require_once dirname(__FILE__) . '/pgsql8_parser_grant_revoke.php';
require_once dirname(__FILE__) . '/pgsql8_parser_insert_into.php';

class pgsql8_dump_loader {

  /**
   * Pattern for testing whether command is CREATE SCHEMA command.
   */
  const PATTERN_CREATE_SCHEMA = "/^CREATE[\\s]+SCHEMA[\\s]+.*$/i";
  /**
   * Pattern for parsing default schema (search_path).
   */
  const PATTERN_DEFAULT_SCHEMA = "/^SET[\\s]+search_path[\\s]*=[\\s]*([^,\\s]+)(?:,[\\s]+.*)?;$/i";
  /**
   * Pattern for parsing schema alteration statements
   */
  const PATTERN_ALTER_SCHEMA = "/^ALTER[\\s]+SCHEMA[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is CREATE TABLE command.
   */
  const PATTERN_CREATE_TABLE = "/^CREATE[\\s]+TABLE[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is CREATE VIEW command.
   */
  const PATTERN_CREATE_VIEW = "/^CREATE[\\s]+(?:OR[\\s]+REPLACE[\\s]+)?VIEW[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is ALTER VIEW command.
   */
  const PATTERN_ALTER_VIEW = "/^ALTER[\\s]+VIEW[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is ALTER TABLE command.
   */
  const PATTERN_ALTER_TABLE = "/^ALTER[\\s]+TABLE[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is CREATE SEQUENCE command.
   */
  const PATTERN_CREATE_SEQUENCE = "/^CREATE[\\s]+SEQUENCE[\\s]+.*$/i";
  /**
   * Pattern for matching ALTER SEQUENCE ... statements
   */
  const PATTERN_ALTER_SEQUENCE = "/^ALTER[\\s]+SEQUENCE[\\s]+(.*)[\\s]+(.*)$/i";
  /**
   * Pattern for testing whether command is CREATE INDEX command.
   */
  const PATTERN_CREATE_INDEX = "/^CREATE[\\s]+(?:UNIQUE[\\s]+)?INDEX[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is CREATE TABLE command.
   */
  const PATTERN_CREATE_TYPE = "/^CREATE[\\s]+TYPE[\\s]+.*AS.*[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is SET command.
   */
  const PATTERN_SET = "/^SET[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is COMMENT command.
   */
  const PATTERN_COMMENT = "/^COMMENT[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is SELECT command.
   */
  const PATTERN_SELECT = "/^SELECT[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is INSERT INTO command.
   */
  const PATTERN_INSERT_INTO = "/^INSERT[\\s]+INTO[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is DELETE FROM ... command.
   */
  const PATTERN_DELETE_FROM = "/^DELETE[\\s]+FROM[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is REVOKE command.
   */
  const PATTERN_GRANT_REVOKE = "/^(GRANT|REVOKE)[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is CREATE TRIGGER command.
   */
  const PATTERN_CREATE_TRIGGER = "/^CREATE[\\s]+TRIGGER[\\s]+.*$/i";
  /**
   * Pattern for testing whether command is CREATE FUNCTION or CREATE
   * OR REPLACE FUNCTION command.
   */
  const PATTERN_CREATE_FUNCTION = "/^CREATE[\\s]+(?:OR[\\s]+REPLACE[\\s]+)?FUNCTION[\\s]+.*$/i";
  /**
   * Pattern for parsing schema alteration statements
   */
  const PATTERN_ALTER_FUNCTION = "/^ALTER[\\s]+FUNCTION[\\s]+.*$/i";
  /**
   * Pattern for getting the string that is used to end the function
   * or the function definition itself.
   */
  const PATTERN_END_OF_FUNCTION = "/^(?:.*[\\s]+)?AS[\\s]+(['$][^\\s]*).*$/i";
  /**
   * pattern for matching create language lines
   */
  const PATTERN_CREATE_LANGUAGE = "/CREATE\s+(?:(.*))\s+LANGUAGE\s+([^\(]+)/i";

  /**
   * Pattern for matching ALTER [PROCEDURAL] LANGUAGE ... OWNER TO ...;.
   */
  const PATTERN_ALTER_LANGUAGE = "/ALTER[\\s]+(?:PROCEDURAL[\\s]+)LANGUAGE[\\s]+\"?([^\\s\"]+)\"?[\\s]+OWNER[\\s]+TO[\\s]+\"?([^\\s\"]+)\"?[\\s]*;?/i";

  /**
   * pattern for matching LITERAL_SQL_INCLUDE lines to be collected in the database object
   */
  const PATTERN_LITERAL_SQL = "/.*LITERAL_SQL_INCLUDE.*/i";

  /***
   * match begin and end statements
   */
  const PATTERN_BEGIN_END = "/^(BEGIN|COMMIT).*/i";

  const PATTERN_CONFIG_PARAMETER = '/^SELECT[\s]+dbsteward.db_config_parameter[\s]*\(.*$/i';

  /**
   * Loads database schema from dump file.
   *
   * @param file input file to be read
   *
   * @return database schema from dump fle
   */
  public static function load_database($files) {
    // one or more files to load as database
    if ( !is_array($files) ) {
      $files = array($files);
    }

    pgsql8::$track_pg_identifiers = true;
    pgsql8::$known_pg_identifiers = array();

    $database = new SimpleXMLElement('<dbsteward></dbsteward>');
    dbx::set_default_schema($database, 'public');

    foreach($files AS $file) {
      dbsteward::console_line(1, "Loading " . $file);
      $fp = fopen($file, 'r');
      if ( $fp === false ) {
        throw new exception("failed to open database dump file " . $file);
      }

      $line = fgets($fp);

      while($line != null) {
        // blindly include LITERAL_SQL_INCLUDE lines in the database literal_sql collection
        if (preg_match(self::PATTERN_LITERAL_SQL, $line, $matches) > 0) {
          dbx::add_sql($database, trim($line));
          // clear the line that was literally obsorbed
          $line = ' ';
        }

        $line = trim(self::strip_comment(trim($line)));

        if (strlen($line) == 0) {
          $line = fgets($fp);
          continue;
        } else if (preg_match(self::PATTERN_INSERT_INTO, $line, $matches) > 0) {
          pgsql8_parser_insert_into::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_DELETE_FROM, $line, $matches) > 0) {
          pgsql8_parser_delete_from::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CREATE_LANGUAGE, $line, $matches) > 0) {
          pgsql8_parser_create_language::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_ALTER_LANGUAGE, $line, $matches) > 0) {
          pgsql8_parser_alter_language::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CREATE_TYPE, $line, $matches) > 0) {
          pgsql8_parser_create_type::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CREATE_SCHEMA, $line, $matches) > 0) {
          pgsql8_parser_create_schema::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_DEFAULT_SCHEMA, $line, $matches) > 0) {
          dbx::set_default_schema($database, $matches[1]);
        } else if (preg_match(self::PATTERN_ALTER_SCHEMA, $line, $matches) > 0) {
          pgsql8_parser_alter_schema::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CREATE_TABLE, $line, $matches) > 0) {
          pgsql8_parser_create_table::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_ALTER_TABLE, $line, $matches) > 0) {
          pgsql8_parser_alter_table::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CREATE_SEQUENCE, $line, $matches) > 0) {
          pgsql8_parser_create_sequence::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_ALTER_SEQUENCE, $line, $matches) > 0) {
          pgsql8_parser_alter_sequence::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CREATE_INDEX, $line, $matches) > 0) {
          pgsql8_parser_create_index::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CREATE_VIEW, $line, $matches) > 0) {
          pgsql8_parser_create_view::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_ALTER_VIEW, $line, $matches) > 0) {
          pgsql8_parser_alter_view::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CREATE_TRIGGER, $line, $matches) > 0) {
          pgsql8_parser_create_trigger::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CREATE_FUNCTION, $line, $matches) > 0) {
          pgsql8_parser_create_function::parse($database, self::get_whole_function($fp, $line));
        } else if (preg_match(self::PATTERN_ALTER_FUNCTION, $line, $matches) > 0) {
          pgsql8_parser_alter_function::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_GRANT_REVOKE, $line, $matches) > 0) {
          pgsql8_parser_grant_revoke::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_CONFIG_PARAMETER, $line, $matches) > 0) {
          pgsql8_parser_config_parameter::parse($database, self::get_whole_command($fp, $line));
        } else if (preg_match(self::PATTERN_SET, $line, $matches) > 0
            || preg_match(self::PATTERN_COMMENT, $line, $matches) > 0
            || preg_match(self::PATTERN_SELECT, $line, $matches) > 0
            || preg_match(self::PATTERN_BEGIN_END, $line, $matches) > 0 ) {
          // @TODO: implement these pg_dump modifiers?
          self::get_whole_command($fp, $line);
        }
        else {
          throw new exception("Line did not match to any patterns: " . $line);
        }

        $line = fgets($fp);

/* Development debug: every line, save our current rendition of $database to disk
xml_parser::save_xml(dirname(__FILE__) . '/../../../../dbsteward_monitor.xml', $database->asXML());
/**/
//echo $line . "\n";
      }

      fclose($fp);
    }

    pgsql8::$track_pg_identifiers = false;

    return $database;
  }

  /**
   * Reads whole command from the reader into single-line string.
   *
   * @param fp reader to be read
   * @param line first line read
   *
   * @return whole command from the reader into single-line string
   */
  private static function get_whole_command($fp, $line) {
    $new_line = trim($line);
    $command = $new_line;

    while(substr(trim($new_line), -1) != ';') {
      $buffer = fgets($fp);
      if ( $buffer == null ) {
        break; // fp has no more to give
      }
      $new_line = trim(self::strip_comment($buffer));
      if (strlen($new_line) > 0) {
        $command .= ' ';
        $command .= $new_line;
      }
    }

    return $command;
  }

  /**
   * Reads whole CREATE FUNCTION DDL from the reader into multi-line string.
   *
   * @param fp reader file pointer
   * @param line first line read
   *
   * @return whole CREATE FUNCTION DDL from the reader into multi-line string
   */
  private static function get_whole_function($fp, $line) {
    $command = '';
    $new_line = $line;
    $end_of_function_pattern = null;
    $search_for_semicolon = false;

    while($new_line != null) {
      if (!$search_for_semicolon && ($end_of_function_pattern == null)) {

        if (preg_match(self::PATTERN_END_OF_FUNCTION, $new_line, $matches) > 0) {
          $end_of_function = $matches[1];

          if ($end_of_function{0} == "'") {
            $end_of_function = "'";
          } else {
            $end_of_function = substr($end_of_function, 0, strpos($end_of_function, '$', 1) + 1);
          }

          if ($end_of_function == "'") {
            $end_of_function_pattern = "/(?:.*[^\\\\]'.*|^.*[\\s]*'[\\s]*.*$)/";
          } else {
            $end_of_function_pattern = "/.*\\Q" . $end_of_function . "\\E.*$/i";
          }

          $stripped = preg_replace("/[\\s]+AS[\\s]+\\Q" . $end_of_function . "\\E/", " ", $new_line);
          $search_for_semicolon = preg_match($end_of_function_pattern, $stripped, $matches) > 0;
        }
      }

      $command .= $new_line;
      //$command .= "\n"; // fgets() includes the trailing new line, I don't think we need this

      if ($search_for_semicolon && substr(trim($new_line), -1) == ';') {
        break;
      }

      $new_line = fgets($fp);
      if ( $new_line === false ) {
        throw new exception("failed to read file pointer");
      }

      if ($new_line == null) {
        throw new exception("Cannot find end of function: " . $line);
      }

      if (!$search_for_semicolon && ($end_of_function_pattern != null) && preg_match($end_of_function_pattern, $new_line, $matches) > 0) {
        $search_for_semicolon = true;
      }
    }

    return $command;
  }

  /**
   * Strips comment from command line.
   *
   * @param command command
   *
   * @return if comment was found then command without the comment, otherwise the original command
   */
  private static function strip_comment($command) {
    $result = $command;
    $pos = strpos($result, "--");

    while($pos !== false) {
      if ($pos == 0) {
        $result = "";
        break;
      } else {
        $count = 0;
        for ($c = 0; $c < $pos; $c++) {
          if (substr($result, $c, 1) == "'") {
            $count++;
          }
        }
        if (($count % 2) == 0) {
          $result = trim(substr($result, 0, $pos));
          break;
        }
      }
      $pos = strpos($result, "--", $pos + 1);
    }

    return $result;
  }

}

?>
