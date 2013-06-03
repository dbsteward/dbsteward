<?php
/**
 * SQL99 spec compiling and differencing functions
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class sql99 {
  
  /**
   * Current replica set ID context
   * @var type
   */
  public static $current_replica_set_id;
  
  /**
   * If the passed $db_object has a slonySetId
   * set it as the current_replica_set_id
   * @param SimpleXMLElement $obj
   * @return integer determined slonySetId
   */
  public static function set_active_replica_set($obj) {
    if ( !is_object($obj) || !isset($obj['slonySetId']) ) {
      return static::$current_replica_set_id = -1;
    }
    $set_id = (integer)($obj['slonySetId']);
    return static::$current_replica_set_id = $set_id;
  }
  
  public function set_default_replica_set($db_doc) {
    $replica_set = pgsql8::get_slony_replica_set_first($db_doc);
    if ( $replica_set ) {
      $set_id = (string)$replica_set['id'];
      return static::$current_replica_set_id = $set_id;
    }
    return FALSE;
  }

  const QUOTE_CHAR = '"';
  
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
    $quoted = $quoted || dbsteward::$quote_all_names;
    
    // only verify identifier correctness if we aren't quoting it
    if ( !$quoted && preg_match('/^[a-zA-Z_]\w*$/', $name) == 0 ) {
      throw new exception("Invalid identifier: '$name' - You will need to quote this schema/table/column/type identifier with --quotecolumnnames etc");
    }

    if ( $quoted ) {
      return ($quote_char . $name . $quote_char);
    } else {
      return $name;
    }
  }

  public static function get_quoted_schema_name($name) {
    return self::get_quoted_name($name, dbsteward::$quote_schema_names, static::QUOTE_CHAR);
  }

  public static function get_quoted_table_name($name) {
    return self::get_quoted_name($name, dbsteward::$quote_table_names, static::QUOTE_CHAR);
  }

  public static function get_quoted_column_name($name) {
    return self::get_quoted_name($name, dbsteward::$quote_column_names, static::QUOTE_CHAR);
  }

  public static function get_quoted_function_name($name) {
    return self::get_quoted_name($name, dbsteward::$quote_function_names, static::QUOTE_CHAR);
  }

  public static function get_quoted_object_name($name) {
    return self::get_quoted_name($name, dbsteward::$quote_object_names, static::QUOTE_CHAR);
  }

  public static function get_fully_qualified_table_name($schema_name, $table_name) {
    return static::get_quoted_schema_name($schema_name) . '.' . static::get_quoted_table_name($table_name);
  }

  public static function get_fully_qualified_column_name($schema_name, $table_name, $column_name) {
    return static::get_fully_qualified_table_name($schema_name, $table_name) . '.' . static::get_quoted_column_name($column_name);
  }
}

?>
