<?php
/**
 * Parses INSERT INTO commands.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_parser_insert_into {
  /**
   * Pattern for testing whether command is INSERT INTO command.
   */
  const PATTERN_INSERT_INTO = "/^INSERT[\\s]+INTO[\\s]+\"?([^\\s\"]+)\"?[\\s]+\((.*)\)[\\s]+VALUES[\\s]+\((.*)\);$/i";

  /**
   * Parses INSERT INTO command.
   *
   * @param database database
   * @param command INSERT INTO command
   *
   */
  public static $columns_cache = array();
  public static function parse($database, $command) {
    if (preg_match(self::PATTERN_INSERT_INTO, $command, $matches) > 0) {
      $line = $command;

      $table_name = $matches[1];
      $columns_signature = md5($matches[2]);
      if ( !isset(self::$columns_cache[$columns_signature]) ) {
        self::$columns_cache[$columns_signature] = $columns = self::column_split($matches[2], array('"') );
      }
      else {
        $columns = self::$columns_cache[$columns_signature];
      }

      $data_row = self::column_split($matches[3], array("'") );

      if ( count($columns) != count($data_row) ) {
        throw new exception("column count " . count($columns) . " does not match data_row count " . count($data_row));
      }
      // merge together as an alpha index row
      for($i=0; $i<count($columns); $i++) {
        $row[$columns[$i]] = $data_row[$i];
      }

      $node_schema = &dbx::get_schema($database, sql_parser::get_schema_name($table_name, $database));
      if ( $node_schema == null ) {
        throw new exception("Failed to find schema for data append: " . sql_parser::get_schema_name($table_name, $database));
      }
      $node_table = &dbx::get_table($node_schema, sql_parser::get_object_name($table_name));
      if ( $node_table == null ) {
        throw new exception("Failed to find table for data append: " . $table_name);
      }

      try {
        pgsql8_table::add_data_row($node_table, $row);
      }
      catch(Exception $e) {
        var_dump($command);
        throw $e;
      }
    }
    // no match, don't know what to do
    else {
      throw new exception("Cannot parse command: " . $command);
    }
  }

  public static function column_split($column_list, $token_quotes) {
    $columns = array();
    $in_quoted = false;
    $col = "";
    for($i=0; $i<strlen($column_list); $i++) {
      $c = substr($column_list, $i, 1);
      $c_next = "";
      if ( $i < strlen($column_list) - 1 ) {
        $c_next = substr($column_list, $i + 1, 1);
      }

      // quoted string enclosure
      if ( in_array($c, $token_quotes) ) {
        // not escaped apostrophe?
        if ( $c_next != "'" ) {
          // invert in_quoted state to represent beginning/end
          $in_quoted = !$in_quoted;
        }
        else {
          // it is, include it in $c value
          $c .= $c_next;
          $i++;
        }
      }

      // not in quoted string and end of token? (comma)
      if ( !$in_quoted && $c == "," ) {
        $col = trim($col);
        // explicit or capitalized quoted column names "likeThis" are safe to strip as our column list is case senstive
        // and far simplifies pgsql8_table::add_data_row() sanity check logic
        $col = sql_parser::quoted_name_strip($col);
        $columns[] = $col;
        $col = '';
      }
      else {
        // not the end, add it to the currently building comma separated value
        $col .= $c;
      }
    }
    // if there is column name/data remaining, append it to the array
    if ( strlen(trim($col)) > 0 ) {
      $columns[] = sql_parser::quoted_name_strip(trim($col));
    }
    return $columns;
  }

}

?>
