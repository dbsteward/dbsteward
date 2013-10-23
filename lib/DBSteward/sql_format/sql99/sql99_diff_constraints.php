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

      static::diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints);
    }
  }

  public static function diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints = false) {
    if ( $drop_constraints ) {
      // drop constraints that no longer exist or are modified
      $old_constraints = static::get_drop_constraints($old_schema, $old_table, $new_schema, $new_table, $type);
      if (count($old_constraints) > 0) {
        $ofs->write(static::get_multiple_drop_sql($old_schema, $old_table, $old_constraints));
      }
    }
    else {
      if (dbsteward::$old_database != NULL) {
        list($old_schema, $old_table) = dbx::find_old_table(dbsteward::$old_database, $new_schema, $new_table);
      }

      if ( $old_schema === NULL || $old_table === NULL ) {
        $new_constraints = format_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type);
      }
      else {
        $new_constraints = static::get_new_constraints($old_schema, $old_table, $new_schema, $new_table, $type);
      }

      // add new constraints
      if (count($new_constraints) > 0) {
        $ofs->write(static::get_multiple_create_sql($new_schema, $new_table, $new_constraints));
      }
    }
  }

  public static function get_multiple_drop_sql($node_schema, $node_table, $constraints) {
    $sql = '';
    foreach ($constraints as $constraint) {
      $sql .= format_constraint::get_constraint_drop_sql($constraint) . "\n";
    }
    return $sql;
  }

  public static function get_multiple_create_sql($node_schema, $node_table, $constraints) {
    $sql = '';
    foreach ($constraints as $constraint) {
      $sql .= format_constraint::get_constraint_sql($constraint) . "\n";
    }
    return $sql;
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
  protected static function get_drop_constraints($old_schema, $old_table, $new_schema, $new_table, $type) {
    $list = array();

    if (($new_table != null) && ($old_table != null)) {
      if ( $old_table->getName() != 'table' ) {
        throw new exception("Unexpected element type: " . $old_table->getName() . " panicing");
      }
      foreach(format_constraint::get_table_constraints(dbsteward::$old_database, $old_schema, $old_table, $type) as $old_constraint) {
        $new_constraint = format_constraint::get_table_constraint(dbsteward::$new_database, $new_schema, $new_table, $old_constraint['name']);

        if ( !format_table::contains_constraint(dbsteward::$new_database, $new_schema, $new_table, $old_constraint['name'])
          || !format_constraint::equals($new_constraint, $old_constraint) ) {
          $list[] = $old_constraint;
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
  protected static function get_new_constraints($old_schema, $old_table, $new_schema, $new_table, $type) {
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
?>
