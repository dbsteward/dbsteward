<?php
/**
 * DBSteward - database DDL compiler and difference calculator
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: dbsteward.php 2269 2012-01-09 19:56:27Z nkiraly $
 */

ini_set('memory_limit', -1);

try {
  require_once dirname(__FILE__) . '/lib/dbsteward.php';

  if (!isset($argv[1]) || strlen($argv[1]) == 0 || $argv[1] == '--help') {
    echo dbsteward::usage();
    exit(0);
  }
  
  dbsteward::load_sql_formats();

  $dbg = new dbsteward();
  $dbg->arg_parse();
}
catch (exception $e) {
  echo "Unhandled exception:\n\n" . $e->getMessage() . "\n\n";
  echo $e->getTraceAsString() . "\n\n";
  exit(254);
}

?>
