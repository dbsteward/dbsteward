<?php
/**
 * Parses dbsteward.db_config_parameter() statements
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_grant_revoke.php$
 */

class pgsql8_parser_config_parameter {
  /**
   * Pattern for testing whether command is GRANT/REVOKE command.
   */
  const PATTERN_CONFIG_PARAMETER = '/^SELECT[\s]+dbsteward.db_config_parameter[\s]*\(\'(.*)\',[\s]*\'(.*)\'\)$/i';

  /**
   * Parses GRANT and REVOKE commands
   *
   * @param database database
   * @param command REVOKE command
   */
  public static function parse($database, $command) {
    $command = sql_parser::remove_last_semicolon($command);

    if (preg_match(self::PATTERN_CONFIG_PARAMETER, $command, $matches) > 0) {

      if ( count($matches) != 3 ) {
        var_dump($matches);
        throw new exception("Database configuration parameter call preg exploded into " . count($matches) . ", panic!");
      }

      // just do what the call does push around the name -> value
      $configuration_parameter = &dbx::get_configuration_parameter($database, $matches[1], true);
      dbx::set_attribute($configuration_parameter, 'value', $matches[2]);
    }
    // no match, don't know what to do
    else {
      throw new exception("Cannot parse command: " . $command);
    }
  }

}

?>
