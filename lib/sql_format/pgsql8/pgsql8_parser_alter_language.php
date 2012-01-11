<?php
/**
 * Parses ALTER LANGUAGE commands.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_alter_language.php$
 */

class pgsql8_parser_alter_language {
  /**
   * Pattern for matching ALTER [PROCEDURAL] LANGUAGE ... OWNER TO ...;.
   */
  const PATTERN = "/ALTER[\\s]+(?:PROCEDURAL[\\s]+)LANGUAGE[\\s]+\"?([^\\s\"]+)\"?[\\s]+OWNER[\\s]+TO[\\s]+\"?([^\\s\"]+)\"?[\\s]*;?/i";

  /**
   * Parses ALTER LANGUAGE command.
   *
   * @param database database
   * @param command ALTER LANGUAGE command
   *
   */
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN, $command, $matches) > 0) {
      $line = $command;

      $language_name = trim($matches[1]);
      $owner_name = trim($matches[2]);

      $language = &dbx::get_language($database, $language_name);
      if ( $language == null ) {
        throw new exception("Language " . $language_name . " not found. Is the create for it missing or after the ALTER LANGUAGE statement ?");
      }
      dbx::set_attribute($language, 'owner', $owner_name);
    }
    // no match, don't know what to do
    else {
      throw new exception("Cannot parse command: " . $command);
    }
  }
}

?>
