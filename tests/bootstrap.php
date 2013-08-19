<?php

// Since PHP 3.6, the output buffering functionality offered by PHPUnit_Extensions_OutputTestCase
// has been merged into PHPUnit_Framework_TestCase. The methods are identical, so we can just
// class_alias the class, and any test cases that extend OutputTestCase will just do the right thing
if (!class_exists('PHPUnit_Extensions_OutputTestCase')) {
  class_alias('PHPUnit_Framework_TestCase', 'PHPUnit_Extensions_OutputTestCase', TRUE);
}

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

// If the tests are being executed as part of Travis CI, use different DB config than the default
if (strtolower(getenv('TRAVIS')) === 'true') {
  echo "Travis CI environment detected - using TRAVIS_* database configuration\n";
  $db_config->pgsql8_config = array(
    'dbname' => 'dbsteward_phpunit',
    'dbhost' => getenv('TRAVIS_PGSQL8_DBHOST'),
    'dbport' => getenv('TRAVIS_PGSQL8_DBPORT'),
    'dbuser' => getenv('TRAVIS_PGSQL8_DBUSER'),
    'dbpass' => getenv('TRAVIS_PGSQL8_DBPASS'),
    'dbname_mgmt' => 'postgres',
    'dbuser_mgmt' => getenv('TRAVIS_PGSQL8_DBUSER'),
    'dbpass_mgmt' => getenv('TRAVIS_PGSQL8_DBPASS')
  );

  $db_config->mysql5_config = array(
    'dbname' => 'dbsteward_phpunit',
    'dbhost' => getenv('TRAVIS_MYSQL5_DBHOST'),
    'dbport' => getenv('TRAVIS_MYSQL5_DBPORT'),
    'dbuser' => getenv('TRAVIS_MYSQL5_DBUSER'),
    'dbpass' => getenv('TRAVIS_MYSQL5_DBPASS'),
    'dbname_mgmt' => 'information_schema',
    'dbuser_mgmt' => getenv('TRAVIS_MYSQL5_DBUSER'),
    'dbpass_mgmt' => getenv('TRAVIS_MYSQL5_DBPASS')
  );

  $db_config->mssql10_conn = NULL;
}
// Otherwise, use constants defined in phpunit.xml
else {
  echo "Normal operating environment detected, using phpunit.xml database configuration\n";

  $db_config->pgsql8_config = array(
    'dbname' => constant('PGSQL8_DBNAME'),
    'dbhost' => constant('PGSQL8_DBHOST'),
    'dbport' => constant('PGSQL8_DBPORT'),
    'dbuser' => constant('PGSQL8_DBUSER'),
    'dbpass' => constant('PGSQL8_DBPASS'),
    'dbname_mgmt' => constant('PGSQL8_DBNAME_MANAGEMENT'),
    'dbuser_mgmt' => constant('PGSQL8_DBUSER_MANAGEMENT'),
    'dbpass_mgmt' => constant('PGSQL8_DBPASS_MANAGEMENT')
  );

  $db_config->mysql5_config = array(
    'dbname' => constant('MYSQL5_DBNAME'),
    'dbhost' => constant('MYSQL5_DBHOST'),
    'dbport' => constant('MYSQL5_DBPORT'),
    'dbuser' => constant('MYSQL5_DBUSER'),
    'dbpass' => constant('MYSQL5_DBPASS'),
    'dbname_mgmt' => constant('MYSQL5_DBNAME_MANAGEMENT'),
    'dbuser_mgmt' => constant('MYSQL5_DBUSER_MANAGEMENT'),
    'dbpass_mgmt' => constant('MYSQL5_DBPASS_MANAGEMENT')
  );

  $db_config->mssql10_config = array(
    'dbname' => constant('MSSQL10_DBNAME'),
    'dbhost' => constant('MSSQL10_DBHOST'),
    'dbport' => constant('MSSQL10_DBPORT'),
    'dbuser' => constant('MSSQL10_DBUSER'),
    'dbpass' => constant('MSSQL10_DBPASS'),
    'dbname_mgmt' => constant('MSSQL10_DBNAME_MANAGEMENT'),
    'dbuser_mgmt' => constant('MSSQL10_DBUSER_MANAGEMENT'),
    'dbpass_mgmt' => constant('MSSQL10_DBPASS_MANAGEMENT')
  );

  $db_config->mssql10_conn = function ($c) {
    return new dbsteward_mssql10_connection($c->mssql10_config);
  };
}
