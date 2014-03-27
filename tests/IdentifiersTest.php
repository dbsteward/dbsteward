<?php
/**
 * Tests functions related to identifier validity and quoting
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../lib/DBSteward/sql_format/sql99/sql99.php';

class IdentifiersTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    dbsteward::$quote_all_names = FALSE;
    dbsteward::$quote_schema_names = FALSE;
    dbsteward::$quote_table_names = FALSE;
    dbsteward::$quote_column_names = FALSE;
    dbsteward::$quote_function_names = FALSE;
    dbsteward::$quote_object_names = FALSE;
    dbsteward::$quote_illegal_identifiers = FALSE;
  }


  /**
   * @group pgsql8
   * @dataProvider illegalUnquotedPgsql8Identifiers
   */
  public function testPgsql8UnquotedIllegalThrowsIfNotQuotingIllegals($ident) {
    $this->doUnquotedIllegalThrows('pgsql8', $ident);
  }


  /**
   * @group pgsql8
   * @dataProvider illegalUnquotedPgsql8Identifiers
   */
  public function testPgsql8UnquotedIllegalDoesNotThrowIfQuotingIllegals($ident) {
    $this->doUnquotedIllegalDoesNotThrow('pgsql8', $ident);
  }


  /**
   * @group mysql5
   * @dataProvider illegalUnquotedMysql5Identifiers
   */
  public function testMysql5UnquotedIllegalThrowsIfNotQuotingIllegals($ident) {
    $this->doUnquotedIllegalThrows('mysql5', $ident);
  }

  /**
   * @group mysql5
   * @dataProvider illegalUnquotedMysql5Identifiers
   */
  public function testMysql5UnquotedIllegalDoesNotThrowIfQuotingIllegals($ident) {
    $this->doUnquotedIllegalDoesNotThrow('mysql5', $ident);
  }


  private function doUnquotedIllegalThrows($format, $ident) {
    dbsteward::set_sql_format($format);
    dbsteward::$quote_illegal_identifiers = FALSE;

    try {
      $quoted = $format::get_quoted_name($ident, FALSE, $format::QUOTE_CHAR);
    }
    catch (Exception $ex) {
      $this->assertContains('invalid identifier', strtolower($ex->getMessage()));
      return;
    }
    $this->fail("Expected an exception when quoting illegal $format identifier '$ident', got no exception, returned $quoted");
  }


  private function doUnquotedIllegalDoesNotThrow($format, $ident) {
    dbsteward::set_sql_format($format);
    dbsteward::$quote_illegal_identifiers = TRUE;
    $char = $format::QUOTE_CHAR;

    $quoted = $format::get_quoted_name($ident, FALSE, $char);
    $expected = $char . $ident . $char;

    $this->assertEquals($quoted, $expected);

    // @TODO: check output for warning
  }


  /**
   * These should be illegal in any rdbms
   */
  public function illegalUnquotedIdentifiers() {
    return array(
      array('with-dash'),
      array('with space'),
      array('not_@#%^&*!_valid'),
      array("with\000null")
    );
  }


  /**
   * Illegal in pgsql8
   */
  public function illegalUnquotedPgsql8Identifiers() {
    return array_merge(
      $this->illegalUnquotedIdentifiers(),
      $this->loadBlacklistWords('pgsql8'),
      array(
        array('with$dolla'),
        array('reallyreallylongstringwhywouldsomeonedothis'),
        array('1st_number'), // srsly, why is this valid in mysql?
        array('with"quotechar')
      ));
  }


  /**
   * Illegal in mysql5
   */
  public function illegalUnquotedMysql5Identifiers() {
    return array_merge(
      $this->illegalUnquotedIdentifiers(),
      $this->loadBlacklistWords('mysql5'),
      array(
        array('with`quotechar')
      ));
  }

  private function loadBlacklistWords($format) {
    $blacklist = file($format::get_identifier_blacklist_file());
    $keys = array_rand($blacklist, 5);
    return array_map(function($word) {
      return array(trim($word));
    }, array_intersect_key($blacklist, array_flip($keys)));
  }
}