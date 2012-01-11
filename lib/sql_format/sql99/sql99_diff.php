<?php
/**
 * Diff two DBSteward XML definitions, outputting SQL to get from A to B
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/sql99_diff_tables.php';

class sql99_diff {

  public static $as_transaction = true;
  public static $ignore_function_whitespace = true;
  public static $ignore_start_with = true;
  public static $add_defaults = false;

  public static $old_table_dependency = array();
  public static $new_table_dependency = array();

}

?>
