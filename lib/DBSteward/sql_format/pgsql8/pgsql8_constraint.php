<?php
/**
 * PostgreSQL specific table and column constraints
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_constraint extends sql99_constraint {

  public static function get_reference_option_sql($ref_opt) {
    return strtoupper(str_replace('_',' ',$ref_opt));
  }

}
