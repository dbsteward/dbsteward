<?php

class h2_column extends sql99_column
{

    public static function is_timestamp($node_column)
    {
        return stripos($node_column['type'], 'timestamp') === 0;
    }

    public static function is_current_timestamp_on_update($node_column)
    {
        return static::is_timestamp($node_column) && stripos($node_column['type'], 'ON UPDATE CURRENT_TIMESTAMP') !== FALSE;
    }

    public static function null_allowed($node_table, $node_column)
    {
        if (static::is_serial($node_column['type'])) {
            // serial columns are not allowed to be null
            return false;
        } elseif (static::is_timestamp($node_column) && !isset($node_column['null'])) {
            return false;
        } else {
            return parent::null_allowed($node_table, $node_column);
        }
    }

    public static function get_full_definition($db_doc, $node_schema, $node_table, $node_column, $add_defaults, $include_null_definition = true, $include_auto_increment = false)
    {
        // ignore AUTO_INCREMENT flags for now
        $is_auto_increment = static::is_auto_increment($node_column['type']);
        $orig_type = (string)$node_column['type'];
        $node_column['type'] = static::un_auto_increment($node_column['type']);

        $column_type = static::column_type($db_doc, $node_schema, $node_table, $node_column);
        $definition = h2::get_quoted_column_name($node_column['name']) . ' ' . $column_type;
        $nullable = static::null_allowed($node_table, $node_column);
        $is_timestamp = static::is_timestamp($node_column);
        $is_current_timestamp_on_update = static::is_current_timestamp_on_update($node_column);

        if ($include_null_definition && !$nullable) {
            $definition .= " NOT NULL";
        }

        if ($include_auto_increment && $is_auto_increment) {
            $definition .= " AUTO_INCREMENT";
        }

        if (strlen($node_column['default']) > 0) {
            if (static::is_serial($node_column['type'])) {
                $note = "Ignoring default '{$node_column['default']}' on {$node_schema['name']}.{$node_table['name']}.{$node_column['name']} because it is a serial type";
                dbsteward::warning($note . "\n");
            } else {
                if ($is_current_timestamp_on_update) {
                    $definition = static::current_timestamp_on_update_for_h2($node_column, $definition);
                } else {
                    $definition .= " DEFAULT " . $node_column['default'];
                }
            }
        } else
            if ($add_defaults && $is_timestamp) {
                if ($nullable) {
                    $definition .= " DEFAULT NULL";
                } else {
                    if ($is_current_timestamp_on_update) {
                        $definition = static::current_timestamp_on_update_for_h2($node_column, $definition);
                    } else {
                        $definition .= " DEFAULT CURRENT_TIMESTAMP";
                    }
                }
            } else
                if (!$nullable && $add_defaults) {
                    ;
                    $default_col_value = self::get_default_value($node_column['type']);
                    if ($default_col_value != null) {
                        $definition .= " DEFAULT " . $default_col_value;
                    }
                }

        if (strlen($node_column['description']) > 0) {
            $definition .= " COMMENT " . h2::quote_string_value($node_column['description']);
        }

        // restore the original type of the column
        $node_column['type'] = $orig_type;

        return $definition;
    }

    public static function is_auto_increment($type)
    {
        return stripos($type, 'auto_increment') !== FALSE;
    }

    public static function un_auto_increment($type)
    {
        return preg_replace('/\s*auto_increment\s*/i', '', $type);
    }

    public static function column_type($db_doc, $node_schema, $node_table, $node_column, &$foreign = NULL)
    {
        // if the column is a foreign key, solve for the foreignKey type
        if (isset($node_column['foreignTable'])) {
            $foreign = format_constraint::foreign_key_lookup($db_doc, $node_schema, $node_table, $node_column);
            $foreign_type = static::un_auto_increment($foreign['column']['type']);
            if (static::is_serial($foreign_type)) {
                return static::convert_serial($foreign_type);
            }

            return $foreign_type;
        }

        // if there's no type specified, that's a problem
        if (!isset($node_column['type'])) {
            throw new Exception("column missing type -- " . $table['name'] . "." . $column['name']);
        }

        // get the type of the column, ignoring any possible auto-increment flag
        $type = static::un_auto_increment($node_column['type']);

        // if the column type matches an enum type, inject the enum declaration here
        if (($node_type = h2_type::get_type_node($db_doc, $node_schema, $type))) {
            return h2_type::get_enum_type_declaration($node_type);
        }

        // translate serials to their corresponding int types
        if (static::is_serial($type)) {
            return static::convert_serial($type);
        }

        // remove zerofill
        if (strpos(strtolower($type), "zerofill")) {
            $type = str_replace("zerofill", "", $type);
        }

        // change enums to varchar
        if (strpos(strtolower($type), "enum") !== FALSE) {
            $type = "varchar(255)";
        }

        // nothing special about this type
        return $type;
    }

    public static function get_serial_sequence_name($schema, $table, $column)
    {
        return '__' . $schema['name'] . '_' . $table['name'] . '_' . $column['name'] . '_serial_seq';
    }

    public static function get_serial_trigger_name($schema, $table, $column)
    {
        return '__' . $schema['name'] . '_' . $table['name'] . '_' . $column['name'] . '_serial_trigger';
    }

    public static function get_serial_start_setval_sql($schema, $table, $column)
    {
        $sequence_name = static::get_serial_sequence_name($schema, $table, $column);
        $setval = h2_sequence::get_setval_call($sequence_name, $column['serialStart'], 'TRUE');
        return "SELECT $setval;";
    }

    // AS CURRENT_TIMESTAMP only updates timestamp, and not allows to insert specific value.
    // There is no better solution for H2 with this issue
    public static function current_timestamp_on_update_for_h2($node_column, $definition)
    {
        return str_replace("ON UPDATE CURRENT_TIMESTAMP", "AS CURRENT_TIMESTAMP", $definition);
    }

}

?>
