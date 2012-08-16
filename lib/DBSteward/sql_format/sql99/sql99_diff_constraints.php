<?php
/**
 * Diff table and column constraints
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_diff_constraints {
  /**
   * Outputs commands for differences in constraints.
   *
   * @param object  $ofs              output file pointer
   * @param object  $old_schema       original schema
   * @param object  $new_schema       new schema
   * @param string  $type             type of constraints to process
   * @param boolean $drop_constraints
   */
  public static function diff_constraints($ofs, $old_schema, $new_schema, $type, $drop_constraints) {
    foreach(dbx::get_tables($new_schema) as $new_table) {
      if ($old_schema == null) {
        $old_table = null;
      } else {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }

      self::diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints);
    }
  }

  public static function diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints = false) {
    if ( $drop_constraints ) {
      // drop constraints that no longer exist or are modified
      foreach(self::get_drop_constraints($old_schema, $old_table, $new_schema, $new_table, $type) as $constraint) {
        $ofs->write(format_constraint::get_constraint_drop_sql($constraint) . "\n");
      }
    }
    else {
      if ( !dbsteward::$ignore_oldname ) {
        // if it is a renamed table, remove all constraints and recreate with new table name conventions
        if ( format_diff_tables::is_renamed_table($old_schema, $new_schema, $new_table) ) {
          $old_named_table = dbx::get_renamed_table_old_table($old_schema, $old_table, $new_schema, $new_table);
          foreach(format_constraint::get_table_constraints(dbsteward::$old_database, $old_schema, $old_table, $type) as $constraint) {
            // rewrite the constraint definer to refer to the new table name
            // so the constraint by the old name, but part of the new table
            // will be referenced properly in the drop statement
            $constraint['table_name'] = $new_table['name'];
            $ofs->write(format_constraint::get_constraint_drop_sql($constraint) . "\n");
          }
          
          // add all defined constraints back to the new table
          foreach(sql99_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type) as $constraint) {
            $ofs->write(format_constraint::get_constraint_sql($constraint) . "\n");
          }
          return;
        }
        // END if it is a renamed table, remove all constraints and recreate with new table name conventions
      }

      // add new constraints
      foreach(self::get_new_constraints($old_schema, $old_table, $new_schema, $new_table, $type) as $constraint) {
        $ofs->write(format_constraint::get_constraint_sql($constraint) . "\n");
      }
    }
  }

  /**
   * Returns list of constraints that should be dropped.
   *
   * @param old_table original table or null
   * @param new_table new table or null
   * @param type whether primary keys should be processed or other constraints should be processed
   *
   * @return array of constraints that should be dropped
   *
   * @todo Constraints that are depending on a removed field should not be
   *       added to drop because they are already removed.
  */
  private static function get_drop_constraints($old_schema, $old_table, $new_schema, $new_table, $type) {
    $list = array();

    if (($new_table != null) && ($old_table != null)) {
      if ( $old_table->getName() != 'table' ) {
        throw new exception("Unexpected element type: " . $old_table->getName() . " panicing");
      }
      foreach(format_constraint::get_table_constraints(dbsteward::$old_database, $old_schema, $old_table, $type) as $constraint) {
        $new_constraint = format_constraint::get_table_constraint(dbsteward::$new_database, $new_schema, $new_table, $constraint['name']);

        if ( !format_table::contains_constraint(dbsteward::$new_database, $new_schema, $new_table, $constraint['name'])
          || !format_table::constraint_equals($new_constraint, $constraint) ) {
          $list[] = $constraint;
        }
      }
    }

    return $list;
  }

  /**
   * Returns list of constraints that should be added.
   *
   * @param old_table original table
   * @param new_table new table
   * @param type whether primary keys should be processed or other constraints should be processed
   *
   * @return list of constraints that should be added
   */
  private static function get_new_constraints($old_schema, $old_table, $new_schema, $new_table, $type) {
    $list = array();

    if ($new_table != null) {
      if ($old_table == null) {
        foreach(format_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type) as $constraint) {
          $list[] = $constraint;
        }
      } else {
        foreach(format_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type) as $constraint) {
          $old_constraint = format_constraint::get_table_constraint(dbsteward::$old_database, $old_schema, $old_table, $constraint['name']);

          if ( !format_table::contains_constraint(dbsteward::$old_database, $old_schema, $old_table, $constraint['name'])
            || !format_constraint::equals($old_constraint, $constraint) ) {
            $list[] = $constraint;
          }
        }
      }
    }

    return $list;
  }
}