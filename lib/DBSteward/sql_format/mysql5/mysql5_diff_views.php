<?php
/**
 * View comparison management
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class mysql5_diff_views extends sql99_diff_views {
  public static function should_drop_view($old_schema, $old_view, $new_schema, $new_view) {
    // unlike sql99_diff_views, *do* drop the view if the new schema doesn't exist,
    // views aren't dropped with their respective schema
    // otherwise, drop if it changed or no longer exists
    return $new_view == null || static::is_view_modified($old_view, $new_view);
  }

}
