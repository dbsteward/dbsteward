<?php
/**
 * Regression test for moving quoted names from diff classes into base format class
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../../lib/DBSteward/sql_format/pgsql8/pgsql8.php';

class QuotedNamesRegressionTest extends PHPUnit_Framework_TestCase {
  public function testPgsql8() {
    dbsteward::set_sql_format('pgsql8');

    $name = '_$valid_1d3ntifier$';

    foreach ( array('schema','table','column','object','function') as $type ) {
      foreach ( array(TRUE,FALSE) as $quote ) {
        dbsteward::${"quote_{$type}_names"} = $quote;
        $expected = $quote ? "\"$name\"" : $name;
        $this->assertEquals($expected, pgsql8::get_quoted_name($name, dbsteward::${"quote_{$type}_names"}));
      }
    }
  }
}