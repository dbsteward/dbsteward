<?php
/**
 * Parses ALTER SEQUENCE commands.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_alter_sequence.php$
 */

class pgsql8_parser_alter_sequence {
  /**
   * Pattern for matching ALTER SEQUENCE ... OWNED BY ...;
   */
  const PATTERN_SEQUENCE_OWNED_BY = "/ALTER[\\s]+SEQUENCE[\\s]+(.*)[\\s]+OWNED[\\s]+BY[\\s]+([^;]+);/i";

  /**
   * Parses ALTER SEQUENCE command.
   *
   * @param database database
   * @param command ALTER SEQUENCE command
   */
  public static function parse($database, $command) {
    $line = $command;
    if (preg_match(self::PATTERN_SEQUENCE_OWNED_BY, $command, $matches) > 0) {
      $sequenceName = trim($matches[1]);
      // make sequenceName fully qualified
      // default_schema will make set search path induced schemas come through correctly
      $sequenceName = sql_parser::get_schema_name($sequenceName, $database) . '.' . sql_parser::get_object_name($sequenceName);

      $ownerTable = trim($matches[2]);
      // make ownerTable fully qualified
      // default_schema will make set search path induced schemas come through correctly
      $ownerTable = sql_parser::get_schema_name($ownerTable, $database) . '.' . sql_parser::get_object_name($ownerTable);

      $schema = $database->get_schema(sql_parser::get_schema_name($sequenceName, $database));
      if ( $schema == null ) {
        throw new exception("Schema " . sql_parser::get_schema_name($sequenceName, $database) . " not found");
      }
      $sequence = $schema->get_sequence($sequenceName);
      if ( $sequence == null ) {
        throw new exception("Sequence " . $sequenceName . " not found. Is the create for it missing or after the ALTER SEQUENCE statement ?");
      }
      $sequence->set_owned_by($ownerTable);
    }
    // no match, don't know what to do
    else {
      throw new exception("Cannot parse command: " . $command);
    }
  }
}

?>
