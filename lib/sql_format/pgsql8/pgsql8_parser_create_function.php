<?php
/**
 * Parses CREATE FUNCTION and CREATE OR REPLACE FUNCTION commands.
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_parser_create_function.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_parser_create_function {
  /**
   * Pattern for parsing CREATE FUNCTION and CREATE OR REPLACE FUNCTION command.
   */
  const PATTERN = "/^CREATE[\\s]+(?:OR[\\s]+REPLACE[\\s]+)?FUNCTION[\\s]+([^\\s(]+)\\(([^)]*)\\).*$/is";

  /**
  * Pattern for parsing function arguments.
  */
  // this one doesn't like argument types with spaces in them
  //const PATTERN_ARGUMENT = "/^(?:(?:IN|OUT|INOUT)[\\s]+)?(?:\"?[^\\s\"]+\"?[\\s]+)?(.+)$/i";
  const PATTERN_ARGUMENT = "/^(?:(?:IN|OUT|INOUT)[\s]+)?(\"?[^\"]+\"?[\s]+.*$/i";

  /**
   * Pattern for parsing (function declaration) RETURNS <type> AS $_$ FUNCTION command.
   */
  const PATTERN_RETURNS = "/^.*RETURNS[\\s]+([^\\s]+)[\\s]*.+$/i";

  /**
   * Pattern for parsing function declaration(args) RETURNS <type> AS $_$
   * LANGUAGE plpgsql etc
   */
  const PATTERN_LANGUAGE = "/^.*LANGUAGE[\\s]+([^\\s]+)[\\s]*.+$/i";

  /**
   * Parses CREATE FUNCTION and CREATE OR REPLACE FUNCTION command.
   *
   * @param database database
   * @param command CREATE FUNCTION command
   */
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN, trim($command), $matches) > 0) {
      $function_name = trim($matches[1]);
      // make all functionName's fully qualified
      // default_schema will make set search path induced schemas come through correctly
      $function_name = sql_parser::get_schema_name($function_name, $database) . '.' . sql_parser::get_object_name($function_name);
      $arguments = $matches[2];

      $node_schema = dbx::get_schema($database, sql_parser::get_schema_name($function_name, $database));
      if ( $node_schema == null ) {
        throw new exception("Failed to find function schema for " . $function_name);
      }

      $node_function = dbx::get_function($node_schema, sql_parser::get_object_name($function_name), null, true);
      //@TODO: this may be a problem when there is more than one prototype for a function
      $function_declaration = pgsql8_function::set_declaration($node_schema, $node_function, $arguments);

      // check remaining definition for function modifiers by chopping of function declaration
      $function_close_position = stripos($command, ')');
      $function_modifiers = str_replace("\n", ' ', substr($command, $function_close_position + 1));
      // kill extra whitespace by regex match
      $function_modifiers = preg_replace("/\\s+/", " ", $function_modifiers);
      // kill trailing semicolon
      $function_modifiers = trim($function_modifiers);
      if ( substr($function_modifiers, -1) == ';' ) {
        $function_modifiers = trim(substr($function_modifiers, 0, -1));
      }
      $function_modifiers = ' ' . $function_modifiers . ' ';

      // AS token (definition) token
      // AS $_$ BEGIN DO STUFF END $_$
      if ( ($as_pos = stripos($function_modifiers, ' AS ')) !== false ) {
        $end_as_token_pos = strpos($function_modifiers, ' ', $as_pos + 4);
        $as_token = substr($function_modifiers, $as_pos + 4, $end_as_token_pos - ($as_pos + 4));
        $definition_start = strpos($function_modifiers, $as_token, $as_pos) + strlen($as_token);
        $definition_end = strpos($function_modifiers, $as_token, $definition_start + strlen($as_token));
        $definition = substr($function_modifiers, $definition_start, $definition_end - $definition_start);
        $definition = trim($definition);
        pgsql8_function::set_definition($node_function, $definition);
        // cut out what we just found
        $function_modifiers = substr($function_modifiers, 0, $as_pos) . ' ' . substr($function_modifiers, $definition_end + strlen($as_token));
      }

      // now that the AS <token> (definition) <token> section is gone, parsing is simpler:
      // RETURNS (type)
      if (preg_match(self::PATTERN_RETURNS, $function_modifiers, $matches) > 0){
        dbx::set_attribute($node_function, 'returns', trim($matches[1]));
      }
      // LANGUAGE (languagename)
      if (preg_match(self::PATTERN_LANGUAGE, $function_modifiers, $matches) > 0){
        dbx::set_attribute($node_function, 'language', trim($matches[1]));
      }
      // check for IMMUTABLE | STABLE | VOLATILE modifiers
      if (preg_match('/.*\s+IMMUTABLE\s+.*/i', $function_modifiers, $matches) > 0){
        dbx::set_attribute($node_function, 'cachePolicy', 'IMMUTABLE');
      }
      if (preg_match('/.*\s+STABLE\s+.*/i', $function_modifiers, $matches) > 0){
        dbx::set_attribute($node_function, 'cachePolicy', 'STABLE');
      }
      if (preg_match('/.*\s+VOLATILE\s+.*/i', $function_modifiers, $matches) > 0){
        dbx::set_attribute($node_function, 'cachePolicy', 'VOLATILE');
      }
      // check for SECURITY DEFINER modifier
      if (preg_match('/.*\s+SECURITY DEFINER\s+.*/i', $function_modifiers, $matches) > 0){
        dbx::set_attribute($node_function, 'securityDefiner', 'true');
      }

    } else {
      throw new exception("Cannot parse command: " . $command);
    }
  }

}

?>
