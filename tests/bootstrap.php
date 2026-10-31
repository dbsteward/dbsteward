<?php

// Lightweight DI Container, based on http://twittee.org/
class DI {
  protected $storage = array();
  public function __set($k, $v) {
    $this->storage[$k] = $v;
  }
  public function __get($k) {
    if (!array_key_exists($k, $this->storage)) {
      return NULL;
    }

    $value = $this->storage[$k];

    if (is_callable($value)) {
      return $value($this);
    }
    else {
      return $value;
    }
  }
}

// set up our database connection instances

require_once __DIR__ . '/../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/dbsteward_sql99_connection.php';
require_once __DIR__ . '/dbsteward_pgsql8_connection.php';
require_once __DIR__ . '/dbsteward_mssql10_connection.php';
require_once __DIR__ . '/dbsteward_mysql5_connection.php';

$db_config = new DI;

// this is not the optimal way to make this available to dbstewardUnitTestBase, but because PHPUnit
// is PHPUnit, accessing the global variable is the best I can come up with
$GLOBALS['db_config'] = $db_config;

$db_config->pgsql8_conn = function ($c) {
  return new dbsteward_pgsql8_connection($c->pgsql8_config);
};
$db_config->mysql5_conn = function ($c) {
  return new dbsteward_mysql5_connection($c->mysql5_config);
};

/**
 * Connection settings for one SQL format, read from environment variables named
 * <FORMAT>_DBHOST, <FORMAT>_DBPORT, ... Defaults live in phpunit.xml.dist <env>;
 * CI overrides them by exporting variables with the same names.
 */
function dbsteward_test_db_config($format) {
  $get = function ($key) use ($format) {
    $value = getenv(strtoupper($format) . '_' . $key);
    return $value === FALSE ? '' : $value;
  };
  return array(
    'dbname' => $get('DBNAME'),
    'dbhost' => $get('DBHOST'),
    'dbport' => $get('DBPORT'),
    'dbuser' => $get('DBUSER'),
    'dbpass' => $get('DBPASS'),
    'dbname_mgmt' => $get('DBNAME_MANAGEMENT'),
    'dbuser_mgmt' => $get('DBUSER_MANAGEMENT'),
    'dbpass_mgmt' => $get('DBPASS_MANAGEMENT')
  );
}

$db_config->pgsql8_config = dbsteward_test_db_config('pgsql8');
$db_config->mysql5_config = dbsteward_test_db_config('mysql5');
$db_config->mssql10_config = dbsteward_test_db_config('mssql10');
$db_config->mssql10_conn = function ($c) {
  return new dbsteward_mssql10_connection($c->mssql10_config);
};

dbsteward::$DEBUG = true;
dbsteward::$LOG_LEVEL = Monolog\Level::Debug;
dbsteward::$BRING_THE_RAIN = false;
// dbsteward::$ENABLE_COLOR = false;