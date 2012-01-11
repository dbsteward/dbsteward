<?php
/**
 * Parses CREATE LANGUAGE commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_create_language {
  /**
   * General match pattern
   */
  const PATTERN_CREATE_LANGUAGE = "/CREATE\s+(?:(.*))\s+LANGUAGE\s+(.*)/i";

  /**
   * Parses CREATE TYPE command.
   *
   * @param database database
   * @param command CREATE TYPE command
   */
  public static function parse($database, $command) {
    $line = $command;

    //  CREATE PROCEDURAL LANGUAGE plpgsql;
    //  CREATE [ PROCEDURAL ] LANGUAGE name
    //  CREATE [ TRUSTED ] [ PROCEDURAL ] LANGUAGE name
    //    HANDLER call_handler [ VALIDATOR valfunction ]

    if (preg_match(self::PATTERN_CREATE_LANGUAGE, $line, $matches) > 0) {
      // simplify parsing by killing last semicolon
      $line = sql_parser::remove_last_semicolon($line);
      // break up the command by whitespace
      $chunks = preg_split('/[\s]+/', $line, -1, PREG_SPLIT_NO_EMPTY);

      // shift the LANGUAGE keyword off
      array_shift($chunks);
      // shift the language name off
      $language_name = array_shift($chunks);
      // create language entry
      $language = &dbx::get_language($database, $language_name, true);

      // grab the language modifiers
      while(strcasecmp('LANGUAGE', $chunks[0]) != 0 ) {
        if ( strcasecmp('CREATE', $chunks[0]) == 0 ) {
          // expected first CREATE lead doesn't modify anything
        }
        else if ( strcasecmp('TRUSTED', $chunks[0]) == 0 ) {
          dbx::set_attribute($language, 'trusted', $chunks[0]);
        }
        else if ( strcasecmp('PROCEDURAL', $chunks[0]) == 0 ) {
          dbx::set_attribute($language, 'procedural', $chunks[0]);
        }
        else {
          throw new exception("unknown CREATE LANGUAGE modifier: " . $chunks[0]);
        }
        // shift the lead chunk off now that it has been interpreted
        array_shift($chunks);
      }

      // if there are chunks left, figure out what optional parameteres they are and save them in the language object
      // make sure it's not the trailing ;, we don't care
      while(count($chunks) > 0 && trim(implode(' ', $chunks)) != ';' ) {
        if ( strcasecmp('HANDLER', $chunks[0]) == 0 ) {
          dbx::set_attribute($language, 'handler', $chunks[1]);
        }
        else if ( strcasecmp('VALIDATOR', $chunks[0]) == 0 ) {
          dbx::set_attribute($language, 'validator', $chunks[1]);
        }
        else {
          throw new exception("unknown CREATE LANGUAGE callback: " . $chunks[0]);
        }
        // shift the lead chunk and its value off now that it has been interpreted
        array_shift($chunks);
        array_shift($chunks);
      }

    } else {
      throw new exception("Cannot parse command: " . $line);
    }
  }
}

?>
