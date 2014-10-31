<?php
/**
 * View comparison management
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_diff_views extends sql99_diff_views {

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
    $different = strcasecmp(pgsql8_view::get_view_query($old_view), pgsql8_view::get_view_query($new_view)) != 0;
    return $different;
  }

}
