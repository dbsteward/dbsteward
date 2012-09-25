<?php
/**
 * View comparison management
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_diff_views {
  /**
   * Create all new or modified views
   *
   * @param $ofs         output file segmenter
   * @param $old_schema  original schema
   * @param $new_schema  new schema
   */
  public static function create_views($ofs, $old_schema, $new_schema) {
    foreach (dbx::get_views($new_schema) as $new_view) {
      if ($old_schema == null
        || !format_schema::contains_view($old_schema, $new_view['name'])
        || static::is_view_modified(dbx::get_view($old_schema, $new_view['name']), $new_view)) {
        $ofs->write(format_view::get_creation_sql($new_schema, $new_view));
      }
    }
  }

  /**
   * Drop all missing or modified views
   *
   * @param $ofs         output file segmenter
   * @param $old_schema  original schema
   * @param $new_schema  new schema
   */
  public static function drop_views($ofs, $old_schema, $new_schema) {
    if ($old_schema != NULL) {
      foreach (dbx::get_views($old_schema) as $old_view) {
        $new_view = dbx::get_view($new_schema, $old_view['name']);
        if ($new_view == NULL || static::is_view_modified($old_view, $new_view)) {
          $ofs->write(format_view::get_drop_sql($old_schema, $old_view) . "\n");
        }
      }
    }
  }

  /**
   * is old_view different than new_view?
   *
   * @param object $old_view
   * @param object $new_view
   *
   * @return boolean
   */
  public static function is_view_modified($old_view, $new_view) {
    if ( dbsteward::$always_recreate_views ) {
      return TRUE;
    }
    return strcasecmp(format_view::get_view_query($old_view), format_view::get_view_query($new_view)) != 0
        || strcasecmp($old_view['owner'], $new_view['owner']) != 0;
  }
}
?>
