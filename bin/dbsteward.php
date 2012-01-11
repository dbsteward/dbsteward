<?php
/**
 * DBSteward - database DDL compiler and difference calculator
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
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
