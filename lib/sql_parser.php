<?php
/**
 * SQL statement parsing utilities
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: sql_parser.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class sql_parser {

  /**
   * Returns position of last character of single command within
   * larger command (like CREATE TABLE). Last character is either ',' or
   * ')'. If no such character is found and method reaches the end of the
   * command then position after the last character in the command is
   * returned.
   *
   * @param command command
   * @param start start position
   *
   * @return end position of the command
   */
  public static function get_command_end($command, $start) {
    $bracesCount = 0;
    $singleQuoteOn = FALSE;
    $charPos = $start;

    for (; $charPos < strlen($command); $charPos++) {
      $c = substr($command, $charPos, 1);

      if ($c == '(') {
        $bracesCount++;
      }
      else if ($c == ')') {
        if ($bracesCount == 0) {
          break;
        }
        else {
          $bracesCount--;
        }
      }
      else if ($c == '\'') {
        $singleQuoteOn = !$singleQuoteOn;
      }
      else if (($c == ',') && !$singleQuoteOn && ($bracesCount == 0)) {
        break;
      }
    }

    return $charPos;
  }

  /**
   * Returns object name from optionally schema qualified name.
   *
   * @param name optionally schema qualified name
   *
   * @return name of the object
   */
  public static function get_object_name($name) {
    $pos = strpos($name, '.');

    if ($pos === FALSE) {
      $result = $name;
    }
    else {
      $result = substr($name, $pos + 1);
    }

    return self::quoted_name_strip($result);
  }

  /**
   * Returns schema name from optionally schema qualified name.
   *
   * @param name optionally schema qualified name
   * @param database database
   *
   * @return name of the schema
   */
  public static function get_schema_name($name, $database) {
    $pos = strpos($name, '.');
    if ($pos === FALSE) {
      $default_schema = dbx::get_default_schema();
      $result = $default_schema['name'];
    }
    else {
      $result = substr($name, 0, $pos);
    }
    return self::quoted_name_strip($result);
  }

  /**
   * Removes semicolon from the end of the $command,
   * only if $command ends with semicolon.
   *
   * @param string    $command command
   *
   * @return string   $command without semicolon and trimmed
   */
  public static function remove_last_semicolon($command) {
    if (substr(trim($command), -1) == ';') {
      $result = trim(substr(trim($command), 0, strlen(trim($command)) - 1));
    }
    else {
      $result = $command;
    }
    return $result;
  }

  public static function quoted_name_strip($name) {
    if (substr($name, 0, 1) == '"' && substr($name, -1) == '"') {
      $name = substr($name, 1, strlen($name) - 2);
    }
    return $name;
  }

  public static function quoted_value_strip($value) {
    if (substr($value, 0, 1) == "'" && substr($value, -1) == "'") {
      $value = substr($value, 1, strlen($value) - 2);
    }
    return $value;
  }

  public static function clause_explode($clause) {
    $segmenting_symbols = array('(', ')', '!=', '<>', '>=', '<=', '=', 'AND', 'OR');

    // explode all the tokens in the clause with whitespace
    $tokens = preg_split("/[\s]+/", $clause, -1, PREG_SPLIT_NO_EMPTY);

    // now go through and find segmenting symbols that did not have whitespace between them and tokens
    foreach ($segmenting_symbols AS $segmenting_symbol) {
      for ($i = 0; $i < count($tokens); $i++) {
        $leading = NULL;

        // token is longer than segmenting symbol ?
        if (strlen($tokens[$i]) > strlen($segmenting_symbol)) {
          // contains segmenting symbol ?
          $pos = strpos($tokens[$i], $segmenting_symbol);
          if ($pos !== FALSE) {
            $before = array_slice($tokens, 0, $i);
            $leading = array();
            if ($pos > 1) {
              $leading = array(substr($tokens[$i], 0, $pos));
            }
            $trailing = array(substr($tokens[$i], $pos + 1));
            $after = array_slice($tokens, $i + 1);
          }
        }

        // reorder needs to happen?
        if ($leading !== NULL) {
          $tokens = array_merge($before, $leading, array($segmenting_symbol), $trailing, $after);
          $i = -1;
        }
        else {
          // clean the symbol if is a quoted name
          $tokens[$i] = self::quoted_name_strip($tokens[$i]);
        }
      }
    }

    // array nest based on parenthesis
    $tokens = self::parenthesis_nest($tokens);

    return $tokens;
  }

  public static function parenthesis_nest($tokens) {
    for ($i = 0; $i < count($tokens); $i++) {
      $group_start = NULL;
      if ($tokens[$i] == ')') {
        $group_end = $i;
        // find the end of the () pair, put the group in its own array
        for ($j = $i - 1; $j >= 0; $j--) {
          if ($tokens[$j] == '(') {
            $group_start = $j;
            break;
          }
        }
        if ($group_start === NULL) {
          var_dump($tokens);
          throw new exception("failed to find group end for token group that started at $group_start");
        }

        $before = array_slice($tokens, 0, $group_start);
        $group = array(array_slice($tokens, $group_start + 1, $group_end - $group_start - 1));
        $after = array_slice($tokens, $group_end + 1);
      }

      if ($group_start !== NULL) {
        $tokens = array_merge($before, $group, $after);
        $i = -1;
      }
    }

    // remove extraneous parenthesis nesting
    $tokens = self::parenthesis_nest_collapse($tokens);

    return $tokens;
  }

  public static function parenthesis_nest_collapse($tokens) {
    for ($i = 0; $i < count($tokens); $i++) {
      // child is array and only has 1 array child itself?
      if (is_array($tokens[$i]) && count($tokens[$i]) == 1 && is_array($tokens[$i][0])) {
        $tokens[$i] = self::parenthesis_nest_collapse($tokens[$i][0]);
      }
    }

    // first and only member is an array?
    if (count($tokens) == 1 && is_array($tokens[0])) {
      // collapse it down
      $tokens = $tokens[0];
    }

    return $tokens;
  }
}

?>
