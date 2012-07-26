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
require_once __DIR__ . '/../../lib/DBSteward/sql_format/mssql10/mssql10.php';
require_once __DIR__ . '/../../lib/DBSteward/sql_format/mysql4/mysql4.php';
require_once __DIR__ . '/../../lib/DBSteward/sql_format/oracle10g/oracle10g.php';

class QuotedNamesRegressionTest extends PHPUnit_Framework_TestCase {
  public function testPgsql8() {
    $this->quoteTestCommon('pgsql8');
  }

  public function testMssql10() {
    $this->quoteTestCommon('mssql10');
  }

  public function testMysql4() {
    $this->quoteTestCommon('mysql4', array('in ', 'in-', 'in'.mysql4::QUOTE_CHAR, 'in.'));
  }

  public function testOracle10g() {
    $this->quoteTestCommon('oracle10g');
  }

  protected function quoteTestCommon($format, $additional_invalid = array()) {
    dbsteward::set_sql_format($format);
    $invalid_prefixes = array_merge(array('in$','0in'), $additional_invalid);

    foreach ( array('schema','table','column','object','function') as $object ) {
      foreach ( array(TRUE,FALSE) as $quoted ) {
        dbsteward::${"quote_{$object}_names"} = $quoted;

        // attempt valid identifiers
        $valid_name = "valid_{$format}_{$object}_" . ($quoted ? 'quoted' : 'unquoted') . "_identifier123";
        $expected = $quoted ? ($format::QUOTE_CHAR . $valid_name . $format::QUOTE_CHAR) : $valid_name;

        $this->assertEquals($expected, call_user_func("$format::get_quoted_{$object}_name",$valid_name));

        // attempt invalid identifiers - expect exceptions
        $invalid_names = array_map(function ($prefix) use ($valid_name) { return $prefix . $valid_name; }, $invalid_prefixes);
        $invalid_names[] = ($format::QUOTE_CHAR . $valid_name . $format::QUOTE_CHAR);

        foreach ( $invalid_names as $invalid_name ) {
          try {
            call_user_func("$format::get_quoted_{$object}_name",$invalid_name);
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