<?php
/**
 * Diff table and column constraints
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class mysql5_diff_constraints extends sql99_diff_constraints {
  public static function get_multiple_drop_sql($node_schema, $node_table, $constraints) {
    if (count($constraints) == 0) return '';
    $bits = array();
    foreach ($constraints as $constraint) {
      if (strcasecmp($constraint['type'], 'PRIMARY KEY') == 0) {
        // we're dropping the PK constraint, so we need to drop AUTO_INCREMENT on any affected columns first!
        $columns = mysql5_table::primary_key_columns($node_table);
        foreach ($columns as $col) {
          $node_column = dbx::get_table_column($node_table, $col);
          if (mysql5_column::is_auto_increment($node_column['type'])) {
            $bits[] = "MODIFY " . mysql5_column::get_full_definition(dbsteward::$new_database, $node_schema, $node_table, $node_column, FALSE, TRUE, FALSE);
            break; // there can only be one AI column per table
          }
        }
      }
      $bits[] = mysql5_constraint::get_constraint_drop_sql($constraint, FALSE);
    }
    $table = mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);
    return "ALTER TABLE $table\n  " . implode(",\n  ", $bits) . ";\n";
  }

  public static function get_multiple_create_sql($node_schema, $node_table, $constraints) {
    if (count($constraints) == 0) return '';
    $bits = array();
    foreach ($constraints as $constraint) {
      $bits[] = mysql5_constraint::get_constraint_sql($constraint, FALSE);
      if (strcasecmp($constraint['type'], 'PRIMARY KEY') == 0) {
        // we're adding the PK constraint, so we need to add AUTO_INCREMENT on any affected columns immediately after!
        $columns = mysql5_table::primary_key_columns($node_table);
        foreach ($columns as $col) {
          $node_column = dbx::get_table_column($node_table, $col);
          if (mysql5_column::is_auto_increment($node_column['type'])) {
            $bits[] = "MODIFY " . mysql5_column::get_full_definition(dbsteward::$new_database, $node_schema, $node_table, $node_column, FALSE, TRUE, TRUE);
            break; // there can only be one AI column per table
          }
        }
      }
    }
    $table = mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);
    return "ALTER TABLE $table\n  " . implode(",\n  ", $bits) . ";\n";
  }
}
?>
