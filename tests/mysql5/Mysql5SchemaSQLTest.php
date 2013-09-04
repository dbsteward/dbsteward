<?php
/**
 * DBSteward unit test for mysql5 database generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

/**
 * @group mysql5
 */
class Mysql5SchemaSQLTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_all_names = true;
  }

  // @NOTICE: Tests removed due to changing schema support
  // @TODO: Add tests to verify schema prefixing
}
?>