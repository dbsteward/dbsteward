<?php
/**
 * SQL99 spec compiling and differencing functions
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/sql99_column.php';
require_once dirname(__FILE__) . '/sql99_schema.php';
require_once dirname(__FILE__) . '/sql99_table.php';
require_once dirname(__FILE__) . '/sql99_diff.php';

class sql99 {
  
  /**
   * extendable:
   * translate explicit role names a meta ROLE_ enumeration, etc
   *
   * @param  string $role   username
   *
   * @return string         translated ROLE_ enumeration
   */
  public function translate_role_name($role) {
    switch (strtolower($role)) {
      /* examples for extraction extensions:
      case 'pgsql':
        $r = 'ROLE_OWNER';
      break;
      case 'dbsteward':
      case 'application1':
        $r = 'ROLE_APPLICATION';
      break;
      /**/
      default:
        // not a known translation
        $r = $role;
      break;
    }
    return $r;
  }

}

?>
