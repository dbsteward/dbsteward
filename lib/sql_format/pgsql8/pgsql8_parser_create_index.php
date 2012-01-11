<?php
/**
 * Parses CREATE INDEX commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_create_index {

  /**
   * Pattern for parsing CREATE INDEX definition.
   */
  //const CREATE_PATTERN = '/CREATE[\s](|UNIQUE[\s]+)INDEX[\s]+"?([^\s"]+)"?[\s]+ON[\s]+"?([^\s"(]+)"?[\s]*([^;]+)[;]?/i';
  const CREATE_PATTERN = '/CREATE[\s](|UNIQUE[\s]+)INDEX[\s]+"?([^\s"]+)"?[\s]+ON[\s]+"?([^\s"(]+)"?[\s]+USING\s+([^;]+)[;]?/i';

  /**
   * Parses CREATE INDEX command.
   *
   * @param database database
   * @param command CREATE INDEX command
   */
  public static function parse($database, $command) {
    if (preg_match(self::CREATE_PATTERN, trim($command), $matches) > 0) {
      $unique_value = strlen(trim($matches[1])) > 0 ? 'true' : 'false';
      $index_name = $matches[2];
      $table_name = $matches[3];
      $using = trim($matches[4]);

      if (($index_name == null) || ($table_name == null) || ($using == null)) {
        throw new exception("Cannot parse command: " . $command);
      }

      $node_schema = &dbx::get_schema($database, sql_parser::get_schema_name(trim($table_name), $database));
      $node_table = &dbx::get_table($node_schema, sql_parser::get_object_name(trim($table_name)));
      if ( $node_table == null ) {
        throw new exception("Failed to find table: " . $table_name);
      }
      $node_index = &dbx::create_table_index($node_table, $index_name);
      dbx::set_attribute($node_index, 'using', $using);
      dbx::set_attribute($node_index, 'unique', $unique_value);
    } else {
      throw new exception("Cannot parse command: " . $command);
    }
  }
}

?>
