<?php
/**
 * Manipulate column definition nodes
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mysql5_column extends sql99_column {
  
  /**
   * Returns full definition of the column.
   *
   * @param add_defaults whether default value should be added in case NOT
   *        NULL constraint is specified but no default value is set
   *
   * @return full definition of the column
   */
  public static function get_full_definition($db_doc, $node_schema, $node_table, $node_column, $add_defaults, $include_null_definition = true) {
    $flags = '';
    $column_type = '';

    // if the column is a foreign key, solve for the foreignKey type
    if ( isset($node_column['foreignTable']) ) {
      $foreign = format_constraint::foreign_key_lookup($db_doc, $node_schema, $node_table, $node_column);
      $foreign_type = $foreign['column']['type'];
      if ( strcasecmp('serial', $foreign_type) == 0 ) {
        $column_type = 'int';
      }
      else if ( strcasecmp('bigserial', $foreign_type) == 0 ) {
        $column_type = 'bigint';
      }
      else {
        throw new Exception("Invalid foreign column type $column_type for local key {$node_column['name']}");
      }
    }
    elseif ( ! isset($node_column['type']) ) {
      throw new Exception("column missing type -- " . $table['name'] . "." . $column['name']);
    }
    // if the column type matches a registered enum type, inject the enum declaration here
    elseif ( $values = mysql5_type::get_enum_values($node_column['type'].'') ) {
      $column_type = mysql5_type::get_enum_type_declaration($values);
    }
    elseif ( $node_column['type'] == 'serial' ) {
      $column_type = 'int';
      $flags .= " AUTO_INCREMENT";
      // @TODO: startSerial?
    }
    elseif ( $node_column['type'] == 'bigserial' ) {
      $column_type = 'bigint';
      $flags .= " AUTO_INCREMENT";
      // @TODO: startSerial?
    }
    else {
      $column_type = $node_column['type'].'';
    }

    $definition = mysql5::get_quoted_column_name($node_column['name']) . ' ' . $column_type;

    if ($include_null_definition && !self::null_allowed($node_table, $node_column) ) {
      $definition .= " NOT NULL";
    }

    $definition .= $flags;

    if ( strlen($node_column['default']) > 0 ) {
      $definition .= " DEFAULT " . $node_column['default'];
    }
    else if ( !self::null_allowed($node_table, $node_column) && $add_defaults ) {
      $default_col_value = self::get_default_value($node_column['type']);
      if ($default_col_value != null) {
        $definition .= " DEFAULT " . $default_col_value;
      }
    }

    if ( strlen($node_column['description']) > 0 ) {
      $definition .= " COMMENT '" . str_replace("'","\'",$node_column['description']) . "'";
    }

    return $definition;
  }

  public static function get_serial_sequence_name($schema, $table, $column) {
    return '__'.$schema['name'].'_'.$table['name'].'_'.$column['name'].'_serial_seq';
  }

  public static function get_serial_start_setval_sql($schema, $table, $column) {
    $sequence_name = static::get_serial_sequence_name($schema, $table, $column);
    return "SELECT setval('$sequence_name', {$column['serialStart']}, TRUE);";
  }

}