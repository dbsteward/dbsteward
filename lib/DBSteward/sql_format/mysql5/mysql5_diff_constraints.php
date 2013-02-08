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
  public static function diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints = false) {
    parent::diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints);

    // check for potential auto-increment fields - because we apply primary keys only after creating the table, and auto-increment
    // fields need to be a primary key, we need to wait until just after applying primary keys to mark the column as auto-increment
    // you could consider the AUTO_INCREMENT to be a form of constraint
    if ( !$drop_constraints && $type == 'primaryKey' ) {
      foreach ( $new_table->column as $column ) {
        // only do it if the column is auto_increment and has changed
        if ( mysql5_column::is_auto_increment($column['type'])
             && ( $old_schema === null || $old_table === null
                  || ($old_column = dbx::get_table_column($old_table, $column['name'])) === null
                  || strcasecmp(
                      $old_defn = mysql5_column::get_full_definition(dbsteward::$old_database, $old_schema, $old_table, $old_column, FALSE, TRUE, TRUE),
                      $new_defn = mysql5_column::get_full_definition(dbsteward::$new_database, $new_schema, $new_table, $column, FALSE, TRUE, TRUE)
                     ) !== 0 )
        ) {
          $table_name = mysql5::get_fully_qualified_table_name($new_schema['name'],$new_table['name']);
          $defn = mysql5_column::get_full_definition(dbsteward::$new_database, $new_schema, $new_table, $column, FALSE, TRUE, TRUE);
          $ofs->write("ALTER TABLE $table_name MODIFY COLUMN $defn;\n");
        }
      }
    }
  }

  public static function get_multiple_drop_sql($node_schema, $node_table, $constraints) {
    if (count($constraints) == 0) return '';
    $bits = array();
    foreach ($constraints as $constraint) {
      $bits[] = format_constraint::get_constraint_drop_sql($constraint, FALSE);
    }
    $table = mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);
    return "ALTER TABLE $table\n  " . implode(",\n  ", $bits) . ";\n";
  }

  public static function get_multiple_create_sql($node_schema, $node_table, $constraints) {
    if (count($constraints) == 0) return '';
    $bits = array();
    foreach ($constraints as $constraint) {
      $bits[] = format_constraint::get_constraint_sql($constraint, FALSE);
    }
    $table = mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);
    return "ALTER TABLE $table\n  " . implode(",\n  ", $bits) . ";\n";
  }
}
?>
