<?php
/**
 * Parses GRANT and REVOKE commands
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_grant_revoke.php$
 */

class pgsql8_parser_grant_revoke {
  /**
   * Pattern for testing whether command is GRANT/REVOKE command.
   */
  const PATTERN_GRANT_REVOKE = '/^(GRANT|REVOKE)[\s]+(.*)[\s]+ON[\s]+(.*)[\s]+(?:TO|FROM)[\s]+(.*)$/i';

  /**
   * Parses GRANT and REVOKE commands
   *
   * @param database database
   * @param command REVOKE command
   *
   */
  public static function parse($database, $command) {
    $command = sql_parser::remove_last_semicolon($command);

    if (preg_match(self::PATTERN_GRANT_REVOKE, $command, $matches) > 0) {

      if ( count($matches) != 5 ) {
        throw new exception("GRANT/REVOKE definition preg exploded into " . count($matches) . ", panic!");
      }

      $action = strtoupper($matches[1]);
      switch($action) {
        case 'GRANT':
        case 'REVOKE':
          break;
        default:
          throw new exception("permission action " . $action . " is unknown, panic!");
          break;
      }

      $operations = preg_split("/[\,\s]+/", $matches[2], -1, PREG_SPLIT_NO_EMPTY);
      if ( !is_array($operations) ) {
        $permission = array($operations);
      }
      for($i=0; $i < count($operations); $i++) {
        $operations[$i] = strtoupper($operations[$i]);
        switch($operations[$i]) {
          case 'ALL':
          case 'SELECT':
          case 'INSERT':
          case 'UPDATE':
          case 'DELETE':
          case 'USAGE':
          case 'REFERENCES':
          case 'TRIGGER':
            break;
          default:
            var_dump($operations);
            throw new exception("the operation " . $operations[$i] . " is unknown, panic!");
            break;
        }
      }

      $object = $matches[3];
      $chunks = preg_split("/[\s]+/", $object, -1, PREG_SPLIT_NO_EMPTY);
      if ( count($chunks) == 1 ) {
        // if there is no white space separating this bit
        // then let postgresql decide what it is when the grant is run
        $object_type = '';
        $object_name = $chunks[0];
      }
      else if ( count($chunks) == 2 ) {
        // SEQUENCE schema.table_table_id_seq
        // TABLE schema.table
        $object_type = $chunks[0];
        $object_name = $chunks[1];
        // if it's a schema, don't try to explode / default the schema prefix
        if ( strcasecmp($object_type, 'SCHEMA') == 0 ) {
          $schema = &dbx::get_schema($database, $object_name);
        }
        else {
          $object_name = sql_parser::get_schema_name($object_name, $database) . '.' . sql_parser::get_object_name($object_name);
          $schema = &dbx::get_schema($database, sql_parser::get_schema_name($object_name, $database));
        }
        if ( $schema == null ) {
          throw new exception("Failed to find schema for grant/revoke: " . sql_parser::get_schema_name($object_name, $database));
        }
      }
      else {
        throw new exception("object definition exploded into " . count($chunks) . " chunks, panic!");
      }
      $role = $matches[4];

      // find the node_object, swtich'd on $object_type
      // based on http://www.postgresql.org/docs/8.4/static/sql-grant.html
      // empty object_type should be considered a TABLE GRANT/REVOKE
      if ( strlen($object_type) == 0 ) {
        $object_type = 'TABLE';
      }
/*
var_dump($command);
var_dump(sql_parser::get_schema_name($object_name, $database));
var_dump(sql_parser::get_object_name($object_name));
/**/
      switch(strtoupper($object_type)) {
        case 'SCHEMA':
          $node_object = &dbx::get_schema($database, $object_name);
          break;
        case 'SEQUENCE':
          $node_schema = &dbx::get_schema($database, sql_parser::get_schema_name($object_name, $database));
          $node_object = &dbx::get_sequence($node_schema, sql_parser::get_object_name($object_name));
          break;
        case 'TABLE':
          $node_schema = &dbx::get_schema($database, sql_parser::get_schema_name($object_name, $database));
          $node_object = &dbx::get_table($node_schema, sql_parser::get_object_name($object_name));
          break;
        default:
          throw new exception("unknown object_type " . $object_type . " encountered, panic!");
          break;
      }

      dbx::set_permission($node_object, $action, $operations, $role);
    }
    // no match, don't know what to do
    else {
      throw new exception("Cannot parse command: " . $command);
    }
  }

}

?>
