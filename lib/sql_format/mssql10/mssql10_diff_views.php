<?php
/**
 * Diffs views.
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_diff_views {

  /**
   * Outputs commands for creation of views.
   *
   * @param fp output file pointer
   * @param old_schema original schema
   * @param new_schema new schema
   */
  public static function create_views($fp, $old_schema, $new_schema) {
    foreach (dbx::get_views($new_schema) as $new_view) {
      if ($old_schema == NULL
        || !mssql10_schema::contains_view($old_schema, $new_view['name'])
        || self::is_view_modified(dbx::get_view($old_schema, $new_view['name']), $new_view)) {
        fwrite($fp, mssql10_view::get_creation_sql($new_schema, $new_view));
      }
    }
  }

  /**
   * Outputs commands for dropping views.
   *
   * @param fp output file pointer
   * @param old_schema original schema
   * @param new_schema new schema
   */
  public static function drop_views($fp, $old_schema, $new_schema) {
    if ($old_schema != NULL) {
      foreach (dbx::get_views($old_schema) as $old_view) {
        $new_view = dbx::get_view($new_schema, $old_view['name']);
        if ($new_view == NULL || self::is_view_modified($old_view, $new_view)) {
          fwrite($fp, mssql10_view::get_drop_sql($old_schema, $old_view) . "\n");
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
    $different = strcasecmp(mssql10_view::get_view_query($old_view), mssql10_view::get_view_query($new_view)) != 0;
    return $different;
  }
}

?>
