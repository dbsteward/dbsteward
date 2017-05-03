<?php

class h2_diff_constraints extends sql99_diff_constraints
{

    public static function diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints = false)
    {
        if ($drop_constraints) {
            // drop constraints that no longer exist or are modified
            $old_constraints = static::get_drop_constraints($old_schema, $old_table, $new_schema, $new_table, $type);
            if (count($old_constraints) > 0) {
                $ofs->write(static::get_multiple_drop_sql($old_schema, $old_table, $old_constraints));
            }
        } else {
            if (dbsteward::$old_database != NULL) {
                list($old_schema, $old_table) = dbx::find_old_table(dbsteward::$old_database, $new_schema, $new_table);
            }

            if ($old_schema === NULL || $old_table === NULL) {
                $new_constraints = format_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type);
            } else {
                $new_constraints = static::get_new_constraints($old_schema, $old_table, $new_schema, $new_table, $type);
            }

            $bits = self::get_multiple_create_bits($new_schema, $new_table, $new_constraints);
            if ($type == 'primaryKey') {
                $index_bits = h2_diff_indexes::diff_indexes_table_bits($old_schema, $old_table, $new_schema, $new_table);
                $bits = array_merge($index_bits, $bits);
            }

            // add new constraints
            if (count($bits) > 0) {
                $table = h2::get_fully_qualified_table_name($new_schema['name'], $new_table['name']);
               // $ofs->write("ALTER TABLE $table\n  " . implode(",\n  ", $bits) . ";\n");
                $ofs->write("ALTER TABLE $table\n  " . implode(";\nALTER TABLE $table\n  ", $bits) . ";\n");
            }
        }
    }

    public static function get_multiple_drop_sql($node_schema, $node_table, $constraints)
    {
        if (count($constraints) == 0) return '';
        $bits = array();
        foreach ($constraints as $constraint) {
            if (strcasecmp($constraint['type'], 'PRIMARY KEY') == 0) {
                // we're dropping the PK constraint, so we need to drop AUTO_INCREMENT on any affected columns first!
                $columns = h2_table::primary_key_columns($node_table);
                foreach ($columns as $col) {
                    $node_column = dbx::get_table_column($node_table, $col);
                    if (h2_column::is_auto_increment($node_column['type'])) {
                        $bits[] = "MODIFY " . h2_column::get_full_definition(dbsteward::$new_database, $node_schema, $node_table, $node_column, FALSE, TRUE, FALSE);
                        break; // there can only be one AI column per table
                    }
                }
            }
            $bits[] = h2_constraint::get_constraint_drop_sql($constraint, FALSE);
        }
        $table = h2::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);
      //  return "ALTER TABLE $table\n  " . implode(",\n  ", $bits) . ";\n";
        return implode(",\n  ", $bits) . ";\n";
    }

    public static function get_multiple_create_bits($node_schema, $node_table, $constraints)
    {
        $bits = array();
        foreach ($constraints as $constraint) {
            $bits[] = h2_constraint::get_constraint_sql($constraint, FALSE);
            if (strcasecmp($constraint['type'], 'PRIMARY KEY') == 0) {
                // we're adding the PK constraint, so we need to add AUTO_INCREMENT on any affected columns immediately after!
                $columns = h2_table::primary_key_columns($node_table);
                foreach ($columns as $col) {
                    $node_column = dbx::get_table_column($node_table, $col);
                    if (h2_column::is_auto_increment($node_column['type'])) {
                        $bits[] = "MODIFY " . h2_column::get_full_definition(dbsteward::$new_database, $node_schema, $node_table, $node_column, FALSE, TRUE, TRUE);
                        break; // there can only be one AI column per table
                    }
                }
            }
        }
        return $bits;
    }

    public static function get_multiple_create_sql($node_schema, $node_table, $constraints)
    {

        if (count($constraints) == 0) return '';
        $bits = self::get_multiple_create_bits($node_schema, $node_table, $constraints);
        $table = h2::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);

        //return "ALTER TABLE $table\n  " . implode(",\n  ", $bits) . ";\n";
        return implode(",\n  ", $bits) . ";\n";
    }

}

?>
