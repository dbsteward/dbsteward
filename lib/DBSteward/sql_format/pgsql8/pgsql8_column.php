<?php
/**
 * Manipulate postgresql column definition nodes
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_column extends sql99_column {
  /**
   * Pattern for parsing NULL arguments.
   */
  const PATTERN_NULL = "/^(.+)[\\s]+NULL$/i";
  /**
   * Pattern for matching a trailing DEFAULT NULL statement to avoid false positives on PATTERN_NULL
   */
  const PATTERN_DEFAULT_NULL = "/^(.+)[\\s]+DEFAULT[\\s]+NULL$/i";

  /**
   * Pattern for parsing NOT NULL arguments.
   */
  const PATTERN_NOT_NULL = "/^(.+)[\\s]+NOT[\\s]+NULL$/i";

  /**
   * Pattern for parsing DEFAULT value.
   */
  const PATTERN_DEFAULT = "/^(.+)[\\s]+DEFAULT[\\s]+(.+)$/i";

  const PATTERN_DEFAULT_NOW = '/^(NOW\(\)|CURRENT_TIMESTAMP|cfxn\.current_ts\(\))$/i';

  /**
   * Returns full definition of the column.
   *
   * @param add_defaults whether default value should be added in case NOT
   *        NULL constraint is specified but no default value is set
   *
   * @return full definition of the column
   */
  public function get_full_definition($db_doc, $node_schema, $node_table, $node_column, $add_defaults, $include_null_definition = true) {
    $column_type = pgsql8_column::column_type($db_doc, $node_schema, $node_table, $node_column, $foreign);
    $definition = pgsql8_diff::get_quoted_name($node_column['name'], dbsteward::$quote_column_names) . ' ' . $column_type;

    if (strlen($node_column['default']) > 0) {
      $definition .= " DEFAULT " . $node_column['default'];
    } else if ( !pgsql8_column::null_allowed($node_table, $node_column) && $add_defaults) {
      $default_col_value = pgsql8_column::get_default_value($node_column['type']);
      if ($default_col_value != null) {
        $definition .= " DEFAULT " . $default_col_value;
      }
    }

    if ($include_null_definition && !pgsql8_column::null_allowed($node_table, $node_column) ) {
      $definition .= " NOT NULL";
    }

    return $definition;
  }

  /**
   * Parses definition of the column
   *
   * @param definition definition of the column
   */
  public static function parse_definition(&$node_schema, &$node_table, &$node_column, $definition) {
    $type = $definition;

    if (preg_match(self::PATTERN_NOT_NULL, $type, $matches) > 0) {
      $type = trim($matches[1]);
      dbx::set_attribute($node_column, 'null', 'false');
    } else if (preg_match(self::PATTERN_NULL, $type, $matches) > 0 && preg_match(self::PATTERN_DEFAULT_NULL, $type) == 0) {
      // PATTERN_NULL match only if it is not a trailing DEFAULT NULL
      // as that is not a null designation just a default designation
      $type = trim($matches[1]);
      dbx::set_attribute($node_column, 'null', 'true');
    }

    if (preg_match(self::PATTERN_DEFAULT, $type, $matches) > 0) {
      $type = trim($matches[1]);
      dbx::set_attribute($node_column, 'default', trim($matches[2]));
    }

    // post-parsing sanity checks
    if ( preg_match('/[\s]+/', $type) > 0 ) {
      // type contains whitespace
      // split the type and look for bad tokens
      $bad_keywords = array(
        'DEFAULT',
        'UNIQUE'
      );
      $tokens = preg_split("/[\s]+/", $type, -1, PREG_SPLIT_NO_EMPTY);

      foreach($tokens AS $token) {
        foreach($bad_keywords AS $bad_keyword) {
          if ( strcasecmp($token, $bad_keyword) == 0 ) {
            var_dump($definition);
            throw new exception($node_column['name'] . " column definition parse fail: type '" . $type . "' still contains '" . $bad_keyword . "' keyword -- look at callers for mis-handling of definition parameter");
          }
        }
      }
    }

    dbx::set_attribute($node_column, 'type', $type);

    // for serial and bigserials, create the accompanying sequence that powers the serial
    if ( preg_match(pgsql8::PATTERN_TABLE_LINKED_TYPES, $type) > 0 ) {
      $sequence_name = pgsql8::identifier_name($node_schema['name'], $node_table['name'], $node_column['name'], '_seq');
      $node_sequence = &dbx::get_sequence($node_schema, $sequence_name, true);
      dbx::set_attribute($node_sequence, 'owner', $node_table['owner']);
      dbx::set_attribute($node_sequence, 'start', '1');
      dbx::set_attribute($node_sequence, 'min', '1');
      dbx::set_attribute($node_sequence, 'inc', '1');
      dbx::set_attribute($node_sequence, 'cycle', 'false');
    }
  }

  public static function column_type($db_doc, $schema, $table, $column, &$foreign) {
    // if it is a foreign keyed column, solve for the foreignKey type
    if ( isset($column['foreignTable']) ) {
      dbx::foreign_key($db_doc, $schema, $table, $column, $foreign);
      $column_type = $foreign['column']['type'];
      // for foreign keys, translate serial types to their integer base
      if ( strcasecmp('serial', $column_type) == 0 ) {
        $column_type = 'int';
      }
      else if ( strcasecmp('bigserial', $column_type) == 0 ) {
        $column_type = 'bigint';
      }
    }
    else {
      if ( !isset($column['type']) || strlen($column['type']) == 0 ) {
        throw new Exception("column missing type -- " . $schema['name'] . "." . $table['name'] . "." . $column['name']);
      }
      $column_type = $column['type'];
    }
    return $column_type;
  }

  /**
   * Returns default value for given column type. If no default value
   * is specified then null is returned.
   *
   * @param type column type
   *
   * @return found default value or null
   */
  public static function get_default_value($type) {
    $default_value = null;

    if ( preg_match("/^smallint$|^int.*$|^bigint$|^decimal.*$|^numeric.*$|^real$|^double precision$|^float.*$|^double$|^money$/i", $type) > 0 ) {
      $default_value = "0";
    } else if ( preg_match("/^character varying.*$|^varchar.*$|^char.*$|^text$/i", $type) > 0 ) {
      $default_value = "''";
    } else if ( preg_match("/^boolean$/i", $type) > 0 ) {
      $default_value = "false";
    }

    return $default_value;
  }

  public static function has_default_now($node_table, $node_column) {
    if ( !is_object($node_column) ) {
      var_dump($node_column);
      throw new exception("node_column passed is not an object");
    }

    $default_is_now = false;
    if ( isset($node_column['default']) ) {
      if ( preg_match(self::PATTERN_DEFAULT_NOW, $node_column['default'], $matches) > 0 ) {
        $default_is_now = true;
      }
    }

    return $default_is_now;
  }

}

?>
