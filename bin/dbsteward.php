<?php
/**
 * DBSteward - database DDL compiler and difference calculator
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

try {
  // if a local relative /../lib/DBSteward is available, use that instead of the include-based path
  // this is mostly for development purposes
  if ( is_readable(dirname(__FILE__) . '/../lib/DBSteward/dbsteward.php') ) {
    require_once dirname(__FILE__) . '/../lib/DBSteward/dbsteward.php';
  }
  // build diretory scenario
  else if ( is_readable(dirname(__FILE__) . '/../DBSteward/dbsteward.php') ) {
    require_once dirname(__FILE__) . '/../DBSteward/dbsteward.php';
  }
  else {
    // this should be available if we are running as the PEAR installation
    require_once 'DBSteward/dbsteward.php';
  }

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
