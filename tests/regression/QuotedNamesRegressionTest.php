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

    foreach ( array('schema','table','column','object','function') as $type ) {
      foreach ( array(TRUE,FALSE) as $quote ) {
        dbsteward::${"quote_{$type}_names"} = $quote;

        // valid identifiers match /[a-zA-Z_]\w*/
        $valid_name = "valid_{$type}_" . ($quote ? 'quoted' : 'unquoted') . "_identifier123";
        $invalid_names = array("in\$$valid_name","0in$valid_name");

        // attempt a valid identifier
        $expected = $quote ? "\"$valid_name\"" : $valid_name;
        $this->assertEquals($expected, pgsql8::get_quoted_name($valid_name, dbsteward::${"quote_{$type}_names"}));

        // attempt invalid identifiers
        foreach ( $invalid_names as $invalid_name ) {
          try {
            pgsql8::get_quoted_name($invalid_name, dbsteward::${"quote_{$type}_names"});
          }
          catch ( Exception $ex ) {
            if ( stripos($ex->getMessage(), 'Invalid identifier') === FALSE ) {
              $this->fail("Expected 'Invalid identifier' exception for identifier '$invalid_name', got '" . $ex->getMessage() . "'");
            }
            continue;
          }
          $this->fail("Expected 'Invalid identifier' exception, but no exception was thrown for identifier '$invalid_name'");
        }
      }
    }
  }
}