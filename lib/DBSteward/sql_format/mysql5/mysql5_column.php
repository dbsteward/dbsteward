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

  public static function null_allowed($node_table, $node_column) {
    if ( static::is_serial($node_column['type']) ) {
      // serial columns are not allowed to be null
      return false;
    }
    else {
      return parent::null_allowed($node_table, $node_column);
    }
  }
  
  /**
   * Returns full definition of the column.
   *
   * @param add_defaults whether default value should be added in case NOT
   *        NULL constraint is specified but no default value is set
   *
   * @return full definition of the column
   */
  public static function get_full_definition($db_doc, $node_schema, $node_table, $node_column, $add_defaults, $include_null_definition = true) {
    $column_type = static::column_type($db_doc, $node_schema, $node_table, $node_column);

    $definition = mysql5::get_quoted_column_name($node_column['name']) . ' ' . $column_type;

    if ($include_null_definition && !static::null_allowed($node_table, $node_column) ) {
      $definition .= " NOT NULL";
    }

    if ( strlen($node_column['default']) > 0 ) {
      if ( static::is_serial($node_column['type']) ) {
        $note = "Ignoring default '{$node_column['default']}' on {$node_schema['name']}.{$node_table['name']}.{$node_column['name']} because it is a serial type";
        dbsteward::console_line(1, $note."\n");
      }
      else {
        $definition .= " DEFAULT " . $node_column['default'];
      }
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

  public static function column_type($db_doc, $node_schema, $node_table, $node_column) {
    // if the column is a foreign key, solve for the foreignKey type
    if ( isset($node_column['foreignTable']) ) {
      $foreign = format_constraint::foreign_key_lookup($db_doc, $node_schema, $node_table, $node_column);
      $foreign_type = $foreign['column']['type'];
      return static::convert_serial($foreign_type);
    }

    // if there's no type specified, that's a problem
    elseif ( ! isset($node_column['type']) ) {
      throw new Exception("column missing type -- " . $table['name'] . "." . $column['name']);
    }

    // if the column type matches a registered enum type, inject the enum declaration here
    elseif ( $values = mysql5_type::get_enum_values($node_column['type'].'') ) {
     return mysql5_type::get_enum_type_declaration($values);
    }

    // translate serials to their corresponding int types
    elseif ( static::is_serial($node_column['type']) ) {
      return static::convert_serial($node_column['type']);
    }

    // nothing special about this type
    else {
      return (string)$node_column['type'];
    }
  }

  public static function get_serial_sequence_name($schema, $table, $column) {
    return '__'.$schema['name'].'_'.$table['name'].'_'.$column['name'].'_serial_seq';
  }

  public static function get_serial_trigger_name($schema, $table, $column) {
    return '__'.$schema['name'].'_'.$table['name'].'_'.$column['name'].'_serial_trigger';
  }

  public static function get_serial_start_setval_sql($schema, $table, $column) {
    $sequence_name = static::get_serial_sequence_name($schema, $table, $column);
    return "SELECT setval('$sequence_name', {$column['serialStart']}, TRUE);";
  }

}