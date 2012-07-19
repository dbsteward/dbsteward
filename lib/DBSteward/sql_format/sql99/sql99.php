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
  
  protected static function is_custom_role_defined($doc, $role) {
    if (isset($doc->database->role->addChild->customRole)) {
      $custom_roles = array();
    }
    else {
      $custom_roles = preg_split("/[\,\s]+/", strtolower($doc->database->role->customRole), -1, PREG_SPLIT_NO_EMPTY);
    }

    $macro_roles = array(
      'PGSQL',
      'PUBLIC',
      'ROLE_OWNER',
      'ROLE_APPLICATION',
      'ROLE_REPLICATION',
      'ROLE_READONLY'
    );
    if ( in_array(strtoupper($role), $macro_roles) ) {
      // macro role, say it is defined
      return TRUE;
    }
    return in_array(strtolower($role), $custom_roles);
  }
  
  protected static function add_custom_role($doc, $role) {
    if (!isset($doc->database->role->customRole)) {
      $doc->database->role->addChild('customRole', $role);
    }
    else {
      $doc->database->role->customRole .= ',' . $role;
    }
  }

  /**
   * returns if quote_name is true then returns quoted name otherwise returns the original name
   *
   * @param name name
   * @param quote_name whether the name should be quoted
   *
   * @return string
   */
  public static function get_quoted_name($name, $quoted, $quote_char) {
    if ( ! preg_match('/^[a-zA-Z_]\w*$/', $name) ) {
      throw new exception("Invalid identifier: '$name'");
    }

    if ( $quoted ) {
      return ($quote_char . $name . $quote_char);
    } else {
      return $name;
    }
  }

}

?>
