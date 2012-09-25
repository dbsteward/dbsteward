<?php
/**
 * Manipulate type node
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_type {
  public static function equals($schema_a, $type_a, $schema_b, $type_b) {
    $a_create_sql = self::get_creation_sql($schema_a, $type_a, false);
    $b_create_sql = self::get_creation_sql($schema_b, $type_b, false);
    return strcasecmp($a_create_sql, $b_create_sql) == 0;
  }
}
?>
