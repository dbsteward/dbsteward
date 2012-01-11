<?php
/**
 * Diff two DBSteward XML definitions, outputting SQL to get from A to B
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: sql99_diff.php 2268 2012-01-09 19:53:59Z nkiraly $
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
