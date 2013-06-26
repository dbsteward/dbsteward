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
  
  /*
   * @IMPORTANT:
   * @NOTICE:
   * @TODO:
   * 
   * This implementation class is INCOMPLETE and only implemented in a few places in DBSteward code!
   * pgsql8_table and pgsql8_column still need conversion work to use pgsql8_constraint fully
   */

  /**
   * Filter the REFERENCES flag modified SQL from DBSteward DTD enumeration to SQL dialect
   * @param string $ref_opt
   * @return string
   */
  public static function get_reference_option_sql($ref_opt) {
    return strtoupper(str_replace('_',' ',$ref_opt));
  }

}
