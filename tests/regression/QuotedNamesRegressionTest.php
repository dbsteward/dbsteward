<?php
/**
 * Regression test for moving quoted names from diff classes into base format class
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

class QuotedNamesRegressionTest extends PHPUnit_Framework_TestCase {

  /**
   * @group pgsql8
   */
  public function testPgsql8() {
    $this->quoteTestCommon('pgsql8');
  }

  /**
   * @group mssql10
   */
  public function testMssql10() {
    $this->quoteTestCommon('mssql10');
  }

  /**
   * @group mysql5
   */
  public function testMysql5() {
    $this->quoteTestCommon('mysql5', array('in ', 'in-', 'in'.mysql5::QUOTE_CHAR, 'in.'));
  }

  protected function quoteTestCommon($format, $additional_invalid = array()) {
    dbsteward::set_sql_format($format);
    dbsteward::$quote_all_names = FALSE;
    dbsteward::$quote_illegal_identifiers = FALSE;
    $invalid_prefixes = array_merge(array('in$','0in'), $additional_invalid);

    foreach ( array('schema','table','column','object','function') as $object ) {
      foreach ( array(TRUE,FALSE) as $quoted ) {
        dbsteward::${"quote_{$object}_names"} = $quoted;

        // attempt valid identifiers
        $valid_name = "valid_{$format}_{$object}_" . ($quoted ? 'quoted' : 'unquoted') . "_identifier123";
        $expected = $quoted ? ($format::QUOTE_CHAR . $valid_name . $format::QUOTE_CHAR) : $valid_name;

        $this->assertEquals($expected, call_user_func("$format::get_quoted_{$object}_name",$valid_name), "During call to $format::get_quoted_{$object}_name");

        // attempt invalid identifiers - expect exceptions
        $invalid_names = array_map(function ($prefix) use ($valid_name) { return $prefix . $valid_name; }, $invalid_prefixes);
        $invalid_names[] = ($format::QUOTE_CHAR . $valid_name . $format::QUOTE_CHAR);

        foreach ( $invalid_names as $invalid_name ) {
          if ($quoted) {
            // only expect an exception if not quoted...
          }
          else {
            try {
              call_user_func("$format::get_quoted_{$object}_name",$invalid_name);
            }
            catch ( Exception $ex ) {
              $this->assertContains('Illegal identifier', $ex->getMessage());
              continue;
            }
            $this->fail("Expected 'Illegal identifier' exception, but no exception was thrown for identifier '$invalid_name'");
          }
        }

      }
    }
  }
}
?>
