<?php
/**
 * schema node manipulation
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mysql5_schema extends sql99_schema {
  
  /**
   * Creates and returns SQL for creation of the schema.
   *
   * @return created SQL
   */
  public function get_creation_sql($node_schema) {
    throw new exception('The MySQL driver currently doesn\'t support schemas other than public');
  }
  
  /**
   * returns DDL to drop specified schema
   *
   * @return string
   */
  public function get_drop_sql($node_schema) {
    throw new exception('The MySQL driver currently doesn\'t support dropping schemas');
  }

}

?>
