<?php
/**
 * Parses CREATE SEQUENCE commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_create_sequence {
  /**
   * Pattern for getting sequence name.
   */
  const PATTERN_SEQUENCE_NAME = "/CREATE[\\s]+SEQUENCE[\\s]+\"?([^\\s\"]+)\"?/i";

  /**
   * Pattern for getting value of START WITH parameter.
   */
  const PATTERN_START_WITH = "/START[\\s]+(?:WITH[\\s]+)?([-]?[\\d]+)/i";

  /**
   * Pattern for getting value of INCREMENT BY parameter.
   */
  const PATTERN_INCREMENT_BY = "/INCREMENT[\\s]+(?:BY[\\s]+)?([-]?[\\d]+)/i";

  /**
   * Pattern for getting value of MAXVALUE parameter.
   */
  const PATTERN_MAXVALUE = "/MAXVALUE[\\s]+([-]?[\\d]+)/i";

  /**
   * Pattern for getting value of MINVALUE parameter.
   */
  const PATTERN_MINVALUE = "/MINVALUE[\\s]+([-]?[\\d]+)/i";

  /**
   * Pattern for getting value of CACHE parameter.
   */
  const PATTERN_CACHE = "/CACHE[\\s]+([\\d]+)/i";

  /**
   * Pattern for checking whether string contains NO CYCLE string.
   */
  const PATTERN_NO_CYCLE = "/.*NO[\\s]+CYCLE.*/i";

  /**
   * Pattern for checking whether string contains CYCLE string.
   */
  const PATTERN_CYCLE = "/.*CYCLE.*/i";

  /**
   * Pattern for checking whether string contains NO MAXVALUE string.
   */
  const PATTERN_NO_MAXVALUE = "/.*NO[\\s]+MAXVALUE.*/i";

  /**
   * Pattern for checking whether string contains NO MINVALUE string.
   */
  const PATTERN_NO_MINVALUE = "/.*NO[\\s]+MINVALUE.*/i";

  /**
   * Parses CREATE SEQUENCE command.
   *
   * @param database database
   * @param command CREATE SEQUENCE command
   */
  public static function parse($database, $command) {
    $line = $command;

    if (preg_match(self::PATTERN_SEQUENCE_NAME, $line, $matches) > 0) {
      $sequence_name = trim($matches[1]);
      $line = preg_replace(self::PATTERN_SEQUENCE_NAME, '', $line);
    } else {
      throw new exception("Cannot parse line: " . $line);
    }

    $node_schema = &dbx::get_schema($database, sql_parser::get_schema_name($sequence_name, $database));
    $node_sequence = &dbx::get_sequence($node_schema, sql_parser::get_object_name($sequence_name), true);

    $line = sql_parser::remove_last_semicolon($line);
    $line = self::processMaxValue($node_sequence, $line);
    $line = self::processMinValue($node_sequence, $line);
    $line = self::processCycle($node_sequence, $line);
    $line = self::processCache($node_sequence, $line);
    $line = self::processIncrement($node_sequence, $line);
    $line = self::processstart_with($node_sequence, $line);
    $line = trim($line);

    if (strlen($line) > 0) {
      throw new exception("Cannot parse commmand '" . $command . "', string '" . $line . "'");
    }
  }

  /**
   * Processes CACHE instruction.
   *
   * @param sequence sequence
   * @param command command
   *
   * @return command without CACHE instruction
   */
  private static function processCache(&$node_sequence, $command) {
    $line = $command;

    if (preg_match(self::PATTERN_CACHE, $line, $matches) > 0) {
      dbx::set_attribute($node_sequence, 'cache', trim($matches[1]));
      $line = preg_replace(self::PATTERN_CACHE, '', $line);
    }

    return $line;
  }

  /**
   * Processes CYCLE and NO CYCLE instructions.
   *
   * @param sequence sequence
   * @param command command
   *
   * @return command without CYCLE instructions
   */
  private static function processCycle(&$node_sequence, $command) {
    $line = $command;

    if (preg_match(self::PATTERN_NO_CYCLE, $line, $matches) > 0) {
      dbx::set_attribute($node_sequence, 'cycle', 'false');
      $line = str_ireplace("NO CYCLE", '', $line);
    } else if (preg_match(self::PATTERN_CYCLE, $line, $matches) > 0) {
      dbx::set_attribute($node_sequence, 'cycle', 'true');
      $line = str_ireplace("CYCLE", '', $line);
    }

    return $line;
  }

  /**
   * Processes INCREMENT BY instruction.
   *
   * @param sequence sequence
   * @param command command
   *
   * @return command without INCREMENT BY instruction
   */
  private static function processIncrement(&$node_sequence, $command) {
    $line = $command;

    if (preg_match(self::PATTERN_INCREMENT_BY, $line, $matches) > 0) {
      dbx::set_attribute($node_sequence, 'inc', trim($matches[1]));
      $line = preg_replace(self::PATTERN_INCREMENT_BY, '', $line);
    }

    return $line;
  }

  /**
   * Processes MAX VALUE and NO MAXVALUE instructions.
   *
   * @param sequence sequence
   * @param command command
   *
   * @return command without MAX VALUE instructions
   */
  private static function processMaxValue(&$node_sequence, $command) {
    $line = $command;

    if (preg_match(self::PATTERN_NO_MAXVALUE, $line, $matches) > 0) {
      dbx::unset_attribute($node_sequence, 'max');
      $line = str_ireplace("NO MAXVALUE", '', $line);
    } else {
      if (preg_match(self::PATTERN_MAXVALUE, $line, $matches) > 0) {
        dbx::set_attribute($node_sequence, 'max', trim($matches[1]));
        $line = preg_replace(self::PATTERN_MAXVALUE, '', $line);
      }
    }

    return $line;
  }

  /**
   * Processes MIN VALUE and NO MINVALUE instructions.
   *
   * @param sequence sequence
   * @param command command
   *
   * @return command without MIN VALUE instructions
   */
  private static function processMinValue(&$node_sequence, $command) {
    $line = $command;

    if (preg_match(self::PATTERN_NO_MINVALUE, $line, $matches) > 0) {
      dbx::unset_attribute($node_sequence, 'min');
      $line = str_ireplace("NO MINVALUE", '', $line);
    } else {
      if (preg_match(self::PATTERN_MINVALUE, $line, $matches) > 0) {
        dbx::set_attribute($node_sequence, 'min', trim($matches[1]));
        $line = str_ireplace("NO MAXVALUE", '', $line);
        $line = preg_replace(self::PATTERN_MINVALUE, '', $line);
      }
    }

    return $line;
  }

  /**
   * Processes START WITH instruction.
   *
   * @param sequence sequence
   * @param command command
   *
   * @return command without START WITH instruction
   */
  private static function processstart_with(&$node_sequence, $command) {
    $line = $command;

    if (preg_match(self::PATTERN_START_WITH, $line, $matches) > 0) {
      dbx::set_attribute($node_sequence, 'start', trim($matches[1]));
      $line = preg_replace(self::PATTERN_START_WITH, '', $line);
    }

    return $line;
  }
}

?>
