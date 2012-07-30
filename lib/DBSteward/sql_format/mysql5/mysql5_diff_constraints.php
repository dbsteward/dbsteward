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
  protected static function get_constraint_drop_sql($constraint) {
    return mysql5_constraint::get_constraint_drop_sql($constraint);
  }

  protected static function get_constraint_create_sql($constraint) {
    return mysql5_constraint::get_constraint_sql($constraint);
  }
}