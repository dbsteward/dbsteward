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

class QuotedNamesRegressionTest extends PHPUnit_Framework_TestCase {
  public function testPgsql8() {
    dbsteward::set_sql_format('pgsql8');

    foreach ( array('schema','table','column','object','function') as $object ) {
      foreach ( array(TRUE,FALSE) as $quoted ) {
        // valid identifiers match /[a-zA-Z_]\w*/
        $valid_name = "valid_{$object}_" . ($quoted ? 'quoted' : 'unquoted') . "_identifier123";
        $expected = $quoted ? "\"$valid_name\"" : $valid_name;

        // test dollar signs (valid in pgsql8, but we don't want them),
        //      identifiers starting with a digit
        //      quote characters
        $invalid_names = array("in\$$valid_name","0in$valid_name", "\"in$valid_name\"");

        $this->quoteTestCommon('pgsql8', $object, $quoted, $valid_name, $expected, $invalid_names);
      }
    }
  }

  public function testMssql10() {
    dbsteward::set_sql_format('mssql10');

    foreach ( array('schema','table','column','object','function') as $object ) {
      foreach ( array(TRUE,FALSE) as $quoted ) {
        // valid identifiers match /[a-zA-Z_]\w*/
        $valid_name = "valid_{$object}_" . ($quoted ? 'quoted' : 'unquoted') . "_identifier123";
        $expected = $quoted ? "\"$valid_name\"" : $valid_name;

        // test dollar signs (valid in pgsql8, but we don't want them),
        //      identifiers starting with a digit
        //      quote characters
        $invalid_names = array("in\$$valid_name","0in$valid_name", "\"in$valid_name\"");

        $this->quoteTestCommon('mssql10', $object, $quoted, $valid_name, $expected, $invalid_names);
      }
    }
  }

  public function testMysql4() {
    dbsteward::set_sql_format('mysql4');

    foreach ( array('schema','table','column','object','function') as $object ) {
      foreach ( array(TRUE,FALSE) as $quoted ) {
        // valid identifiers match /[a-zA-Z_]\w*/
        $valid_name = "valid_{$object}_" . ($quoted ? 'quoted' : 'unquoted') . "_identifier123";
        $expected = $quoted ? "`$valid_name`" : $valid_name;

        // test dollar signs (valid in pgsql8, but we don't want them),
        //      identifiers starting with a digit
        //      quote characters
        $invalid_names = array("in\$$valid_name","0in$valid_name", "`in$valid_name`");

        $this->quoteTestCommon('mysql4', $object, $quoted, $valid_name, $expected, $invalid_names);
      }
    }
  }

  protected function quoteTestCommon($format, $object, $quoted, $valid_name, $expected, $invalid_names) {
    dbsteward::${"quote_{$object}_names"} = $quoted;

    $this->assertEquals($expected, $format::get_quoted_name($valid_name, dbsteward::${"quote_{$object}_names"}));

    // attempt invalid identifiers
    foreach ( $invalid_names as $invalid_name ) {
      try {
        $format::get_quoted_name($invalid_name, dbsteward::${"quote_{$object}_names"});
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