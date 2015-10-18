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

  public static function is_timestamp($node_column) {
    return stripos($node_column['type'], 'timestamp') === 0;
  }

  public static function null_allowed($node_table, $node_column) {
    if ( static::is_serial($node_column['type']) ) {
      // serial columns are not allowed to be null
      return false;
    }
    elseif ( static::is_timestamp($node_column) && !isset($node_column['null']) ) {
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
  public static function get_full_definition($db_doc, $node_schema, $node_table, $node_column, $add_defaults, $include_null_definition = true, $include_auto_increment = false) {
    // ignore AUTO_INCREMENT flags for now
    $is_auto_increment = static::is_auto_increment($node_column['type']);
    $orig_type = (string)$node_column['type'];
    $node_column['type'] = static::un_auto_increment($node_column['type']);

    $column_type = static::column_type($db_doc, $node_schema, $node_table, $node_column);

    $definition = mysql5::get_quoted_column_name($node_column['name']) . ' ' . $column_type;

    $nullable = static::null_allowed($node_table, $node_column);

    $is_timestamp = static::is_timestamp($node_column);

    if ($include_null_definition ) {
      if ( $nullable ) {
        if ( $is_timestamp ) {
          $definition .= " NULL";
        }
      }
      else {
        $definition .= " NOT NULL";
      }
    }

    if ( $include_auto_increment && $is_auto_increment ) {
      $definition .=  " AUTO_INCREMENT";
    }

    if ( strlen($node_column['default']) > 0 ) {
      if ( static::is_serial($node_column['type']) ) {
        $note = "Ignoring default '{$node_column['default']}' on {$node_schema['name']}.{$node_table['name']}.{$node_column['name']} because it is a serial type";
        dbsteward::warning($note."\n");
      }
      else {
        $definition .= " DEFAULT " . $node_column['default'];
      }
    }
    else if ( $add_defaults && $is_timestamp ) {
      if ( $nullable ) {
        $definition .= " DEFAULT NULL";
      }
      else {
        $definition .= " DEFAULT CURRENT_TIMESTAMP";
      }
    }
    else if ( !$nullable && $add_defaults ) {;
      $default_col_value = self::get_default_value($node_column['type']);
      if ($default_col_value != null) {
        $definition .= " DEFAULT " . $default_col_value;
      }
    }

    if ( strlen($node_column['description']) > 0 ) {
      $definition .= " COMMENT " . mysql5::quote_string_value($node_column['description']);
    }

    // restore the original type of the column
    $node_column['type'] = $orig_type;

    return $definition;
  }

  /** Check to see if the given type is marked AUTO_INCREMENT */
  public static function is_auto_increment($type) {
    return stripos($type,'auto_increment') !== FALSE;
  }

  /** Remove any AUTO_INCREMENT flag in the given type */
  public static function un_auto_increment($type) {
    return preg_replace('/\s*auto_increment\s*/i','',$type);
  }

  public static function column_type($db_doc, $node_schema, $node_table, $node_column, &$foreign=NULL) {
    // if the column is a foreign key, solve for the foreignKey type
    if ( isset($node_column['foreignTable']) ) {
      $foreign = format_constraint::foreign_key_lookup($db_doc, $node_schema, $node_table, $node_column);
      $foreign_type = static::un_auto_increment($foreign['column']['type']);
      if ( static::is_serial($foreign_type) ) {
        return static::convert_serial($foreign_type);
      }

      return $foreign_type;
    }

    // if there's no type specified, that's a problem
    if ( ! isset($node_column['type']) ) {
      throw new Exception("column missing type -- " . $table['name'] . "." . $column['name']);
    }

    // get the type of the column, ignoring any possible auto-increment flag
    $type = static::un_auto_increment($node_column['type']);

    // if the column type matches an enum type, inject the enum declaration here
    if ( ($node_type = mysql5_type::get_type_node($db_doc, $node_schema, $type)) ) {
     return mysql5_type::get_enum_type_declaration($node_type);
    }

    // translate serials to their corresponding int types
    if ( static::is_serial($type) ) {
      return static::convert_serial($type);
    }

    // nothing special about this type
    return $type;
  }

  public static function get_serial_sequence_name($schema, $table, $column) {
    return '__'.$schema['name'].'_'.$table['name'].'_'.$column['name'].'_serial_seq';
  }

  public static function get_serial_trigger_name($schema, $table, $column) {
    return '__'.$schema['name'].'_'.$table['name'].'_'.$column['name'].'_serial_trigger';
  }

  public static function get_serial_start_setval_sql($schema, $table, $column) {
    $sequence_name = static::get_serial_sequence_name($schema, $table, $column);
    $setval = mysql5_sequence::get_setval_call($sequence_name, $column['serialStart'], 'TRUE');
    return "SELECT $setval;";
  }

}
?>
