<?php
/**
 * Tests that views are dropped and added in the correct order
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/dbstewardUnitTestBase.php';

/**
 * @group nodb
 */
class ColumnDefaultIsFunctionTest extends dbstewardUnitTestBase {
  public function setUp() {

  }

  public function testViewsDroppedAndCreatedInCorrectOrder() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = true;
    pgsql8_diff::$as_transaction = false;

    $xml = <<<XML
<dbsteward>
  <database>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
  </database>

  <schema name="public" owner="ROLE_OWNER">
    <view name="view1" owner="ROLE_OWNER">
      <viewQuery sqlFormat="pgsql8">
        SELECT * FROM view2
      </viewQuery>
    </view>
    <view name="view2" owner="ROLE_OWNER">
      <viewQuery sqlFormat="pgsql8">
        SELECT * FROM elsewhere
      </viewQuery>
    </view>
  </schema>
</dbsteward>
XML;
    
    $actual = $this->doDiff($xml);

    $expected = <<<SQL
DROP VIEW IF EXISTS "public"."view2";
DROP VIEW IF EXISTS "public"."view1";
CREATE OR REPLACE VIEW "public"."view2" AS SELECT * FROM elsewhere;
ALTER VIEW "public"."view2" OWNER TO deployment;
CREATE OR REPLACE VIEW "public"."view1" SELECT * FROM view2;
ALTER VIEW "public"."view1" OWNER TO deployment;
SQL;

    $this->assertEquals($expected, $actual);
  }

  private function doDiff($xml) {
    $ofs = new mock_output_file_segmenter();

    dbsteward::$new_database = simplexml_load_string($xml);
    dbsteward::$old_database = simplexml_load_string($xml);
    pgsql8_diff::diff_doc_work($ofs, $ofs, $ofs, $ofs);

    $output = $ofs->_get_output();

    return trim(
            preg_replace('/\n+/', "\n",
            preg_replace('/ *\n( |\t)+/', ' ',
            preg_replace('/--.*?\n/', '', $output)))
          );
  }
}