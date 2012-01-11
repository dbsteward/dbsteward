<?php
/**
 * Manipulate column definition nodes
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: mssql10_column.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class mssql10_column extends pgsql8_column {

  /**
   * Returns full definition of the column.
   *
   * @param add_defaults whether default value should be added in case NOT
   *        NULL constraint is specified but no default value is set
   *
   * @return full definition of the column
   */
  public function get_full_definition($db_doc, $node_schema, $node_table, $node_column, $add_defaults, $include_null_definition = TRUE) {
    $column_type = mssql10_column::column_type($db_doc, $node_schema, $node_table, $node_column, $foreign);
    $definition = mssql10_diff::get_quoted_name($node_column['name'], dbsteward::$quote_column_names) . ' ' . $column_type;

    if (strlen($node_column['default']) > 0) {
      $definition .= " DEFAULT " . $node_column['default'];
    }
    else if (!mssql10_column::null_allowed($node_table, $node_column) && $add_defaults) {
      $default_col_value = mssql10_column::get_default_value($node_column['type']);
      if ($default_col_value != NULL) {
        $definition .= " DEFAULT " . $default_col_value;
      }
    }

    if ($include_null_definition && !mssql10_column::null_allowed($node_table, $node_column)) {
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
    }
    else if (preg_match(self::PATTERN_NULL, $type, $matches) > 0 && preg_match(self::PATTERN_DEFAULT_NULL, $type) == 0) {
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
    if (preg_match('/[\s]+/', $type) > 0) {
      // type contains whitespace
      // split the type and look for bad tokens
      $bad_keywords = array('DEFAULT', 'UNIQUE');
      $tokens = preg_split("/[\s]+/", $type, -1, PREG_SPLIT_NO_EMPTY);

      foreach ($tokens AS $token) {
        foreach ($bad_keywords AS $bad_keyword) {
          if (strcasecmp($token, $bad_keyword) == 0) {
            var_dump($definition);
            throw new exception($node_column['name'] . " column definition parse fail: type '" . $type . "' still contains '" . $bad_keyword . "' keyword -- look at callers for mis-handling of definition parameter");
          }
        }
      }
    }

    dbx::set_attribute($node_column, 'type', $type);

    // for serial and bigserials, create the accompanying sequence that powers the serial
    if (preg_match(pgsql8::PATTERN_TABLE_LINKED_TYPES, $type) > 0) {
      $sequence_name = pgsql8::identifier_name($node_schema['name'], $node_table['name'], $node_column['name'], '_seq');
      $node_sequence = & dbx::get_sequence($node_schema, $sequence_name, TRUE);
      dbx::set_attribute($node_sequence, 'owner', $node_table['owner']);
      dbx::set_attribute($node_sequence, 'start', '1');
      dbx::set_attribute($node_sequence, 'min', '1');
      dbx::set_attribute($node_sequence, 'inc', '1');
      dbx::set_attribute($node_sequence, 'cycle', 'false');
    }
  }

  public static function column_type($db_doc, $schema, $table, $column, &$foreign, $check_for_enum_types = TRUE) {
    // if it is a foreign keyed column, solve for the foreignKey type
    if (isset($column['foreignTable'])) {
      dbx::foreign_key($db_doc, $schema, $table, $column, $foreign);
      $column_type = $foreign['column']['type'];
      // for foreign keys, identity columns translate to integers
      if (stripos($column_type, 'int identity') === 0) {
        // starts with int identity?
        $column_type = 'int';
      }
      else if (stripos($column_type, 'bigint identity') === 0) {
        // starts with bigint identity?
        $column_type = 'bigint';
      }
    }
    else {
      if (!isset($column['type'])
        || strlen($column['type']) == 0) {
        throw new Exception("column missing type -- " . $schema['name'] . "." . $table['name'] . "." . $column['name']);
      }

      $column_type = (string)$column['type'];

      if ($check_for_enum_types) {
        if (mssql10_column::enum_type_check(dbsteward::$new_database, $schema, $table, $column, $drop_sql, $add_sql)) {
          // enum types rewritten as varchar(255)
          $column_type = 'varchar(255)';
        }
      }
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
    $default_value = NULL;

    if (preg_match("/^smallint$|^int.*$|^bigint$|^decimal.*$|^numeric.*$|^real$|^double precision$|^float.*$|^double$|^money$/i", $type) > 0) {
      $default_value = "0";
    }
    else if (preg_match("/^character varying.*$|^varchar.*$|^char.*$|^text$/i", $type) > 0) {
      $default_value = "''";
    }
    else if (preg_match("/^boolean$/i", $type) > 0) {
      $default_value = "false";
    }

    return $default_value;
  }

  public static function enum_type_check($db_doc, $node_schema, $node_table, $node_column, &$drop_sql, &$add_sql) {
    // if the column type is a defined enum, (re)add a check constraint to enforce the pseudo-enum
    $foreign = array();
    $column_type = mssql10_column::column_type($db_doc, $node_schema, $node_table, $node_column, $foreign, FALSE);
    if (preg_match('/' . dbx::enum_regex($db_doc) . '/i', $column_type) > 0) {
      $type_schema_name = sql_parser::get_schema_name($column_type, $db_doc);
      $type_schema = dbx::get_schema($db_doc, $type_schema_name);
      $node_type = dbx::get_type($type_schema, sql_parser::get_object_name($column_type, $db_doc));
      if (!$node_type) {
        var_dump($node_type);
        throw new exception('failed to find column_type ' . $column_type . ' in type_schema_name ' . $type_schema_name);
      }
      $drop_sql = mssql10_type::get_drop_check_sql($node_schema, $node_table, $node_column, $node_type);
      $add_sql = mssql10_type::get_add_check_sql($node_schema, $node_table, $node_column, $node_type);
      return TRUE;
    }
    return FALSE;
  }

  public static function null_allowed($node_table, $node_column) {
    $null_allowed = parent::null_allowed($node_table, $node_column);

    if ($null_allowed) {
      // if the column is in the primary_key list, make it NOT NULL anyway
      // mssql will not implicitly make the column NOT NULL like postgresql
      $primary_keys = pgsql8_table::primary_key_columns($node_table);
      if (in_array((string)$node_column['name'], $primary_keys)) {
        $null_allowed = FALSE;
      }
    }

    return $null_allowed;
  }
}

?>
