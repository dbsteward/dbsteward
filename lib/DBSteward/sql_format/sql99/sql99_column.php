<?php
/**
 * Manipulate postgresql column definition nodes
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class sql99_column {

  public static function null_allowed($node_table, $node_column) {
    if ( !is_object($node_column) ) {
      var_dump($node_column);
      throw new exception("node_column passed is not an object");
    }

    if ( !isset($node_column['null']) ) {
      $null_allowed = true;
    }
    else {
      // if null value is not false then nulls are allowed
      $null_allowed = strcasecmp($node_column['null'], 'false') != 0;
    }

    return $null_allowed;
  }
  
  /**
   * Returns default value for given column type. If no default value
   * is specified then null is returned.
   *
   * @param type column type
   *
   * @return found default value or null
   */
  public static function get_default_value($type) {
    $default_value = NULL;

    if ( preg_match("/^smallint$|^(tiny|big)?int.*|^decimal.*|^numeric.*|^real|^double precision$|^float.*|^double.*|^money.*/i", $type) > 0 ) {
      $default_value = "0";
    }
    else if ( preg_match("/^character varying.*|^varchar.*|^char.*|^text.*/i", $type) > 0 ) {
      $default_value = "''";
    }
    else if ( preg_match("/^boolean$/i", $type) > 0 ) {
      $default_value = "false";
    }
    
    /*
    if ( $default_value === NULL ) {
      throw new exception("could not calculate default value for type $type");
    }
    /**/

    return $default_value;
  }

  public static function is_serial($type) {
    return preg_match('/^serial[48]?|bigserial$/i', $type) > 0;
  }

  public static function convert_serial($type) {
    if ( strcasecmp($type, 'serial') == 0 || strcasecmp($type, 'serial4') == 0 ) {
      return 'int';
    }
    elseif ( strcasecmp($type, 'bigserial') == 0 || strcasecmp($type, 'serial8') == 0 ) {
      return 'bigint';
    }
    else {
      throw new Exception("Attempted to convert non-serial type to integral type");
    }
  }
  
  /**
   * Return DML to set serial start value if defined
   *
   * @param  $schema
   * @param  $table
   * @param  $column
   *
   * @return string DML to set serial starts
   */
  public static function get_serial_start_dml($schema, $table, $column = NULL) {
    $sql = NULL;
    if ( $column === NULL ) {
      foreach ($table->column AS $column) {
        $sql .= static::get_serial_start_dml($schema, $table, $column);
      }
    }
    else if (isset($column['serialStart'])) {
      if (static::is_serial($column['type'])) {
        $sql = "-- serialStart " . $column['serialStart'] . " specified for " . $schema['name'] . "." . $table['name'] . "." . $column['name'] . "\n";
        $sql .= static::get_serial_start_setval_sql($schema, $table, $column) . "\n";
      }
      else {
        throw new exception("Unknown column type " . $column['type'] . " for column " . $column['serialStart'] . " specified for " . $schema['name'] . "." . $table['name'] . "." . $column['name'] . " is specifying serialStart");
      }
    }
    return $sql;
  }
}
?>
