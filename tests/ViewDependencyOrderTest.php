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
class ViewDependencyOrderTest extends dbstewardUnitTestBase {
  private $xml_with = <<<XML
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
    <view name="view1" owner="ROLE_OWNER" dependsOnViews="view2">
      <viewQuery sqlFormat="pgsql8">SELECT * FROM view2</viewQuery>
      <viewQuery sqlFormat="mysql5">SELECT * FROM view2</viewQuery>
    </view>
    <view name="view2" owner="ROLE_OWNER">
      <viewQuery sqlFormat="pgsql8">SELECT * FROM elsewhere</viewQuery>
      <viewQuery sqlFormat="mysql5">SELECT * FROM elsewhere</viewQuery>
    </view>
  </schema>
</dbsteward>
XML;

  private $xml_without = <<<XML
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
  </schema>
</dbsteward>
XML;

  public function setUp() {
    dbsteward::$quote_all_names = true;
    $this->doc_with = simplexml_load_string($this->xml_with);
    $this->doc_without = simplexml_load_string($this->xml_without);
  }

  /**
   * @group pgsql8
   */
  public function testDependencyOrderingPgsql8() {
    $this->doTestDependencyOrdering('pgsql8');
  }

  /**
   * @group mysql5
   */
  public function testDependencyOrderingMysql5() {
    $this->doTestDependencyOrdering('mysql5');
  }

  private function doTestDependencyOrdering($format) {
    dbsteward::set_sql_format($format);

    $schema = $this->doc_with->schema;
    $view1 = $schema->view[0];
    $view2 = $schema->view[1];

    $arr = array();
    format_diff_views::with_views_in_order($this->doc_with, function($schema, $view) use (&$arr) {
      $arr[] = array($schema, $view);
    });

    $this->assertCount(2, $arr);
    $this->assertEquals(array($schema, $view2), $arr[0], 'view2 should be visited before view1');
    $this->assertEquals(array($schema, $view1), $arr[1], 'view1 should be visited after view2');
  }

  /**
   * @group pgsql8
   */
  public function testComplexDependencyOrderingPgsql8() {
    $this->doTestComplexDependencyOrdering('pgsql8');
  }

  /**
   * @group mysql5
   */
  public function testComplexDependencyOrderingMysql5() {
    $this->doTestComplexDependencyOrdering('mysql5');
  }

  private function doTestComplexDependencyOrdering($format) {
    dbsteward::set_sql_format($format);

    $xml = <<<XML
<dbsteward>
  <schema name="s1">
    <view name="v1" dependsOnViews="v2,v4" />
    <view name="v2" dependsOnViews="v3,v5" />
    <view name="v3" />
    <view name="v4" dependsOnViews="v5,s2.v3" />
    <view name="v5" />
  </schema>
  <schema name="s2">
    <view name="v1" />
    <view name="v2" dependsOnViews="v3,v4,v5" />
    <view name="v3" />
    <view name="v4" dependsOnViews="s1.v5" />
    <view name="v5" />
  </schema>
</dbsteward>
XML;
    $doc = simplexml_load_string($xml);

    // does an in-order search, so we should see all children, as declared in order, before the parents
    $expected = array(
      's1.v3',
      's1.v5',
      's1.v2',
      's2.v3',
      's1.v4',
      's1.v1',
      's2.v1',
      's2.v4',
      's2.v5',
      's2.v2'
    );

    $actual = array();
    format_diff_views::with_views_in_order($doc, function ($schema, $view) use (&$actual) {
      $actual[] = $schema['name'] . '.' . $view['name'];
    });

    $this->assertEquals($expected, $actual);
  }

  /**
   * @group pgsql8
   */
  public function testViewsDroppedInOrderPgsql8() {
    $this->doTestViewsDroppedInOrder('pgsql8');
  }

  /**
   * @group mysql5
   */
  public function testViewsDroppedInOrderMysql5() {
    $this->doTestViewsDroppedInOrder('mysql5');
  }

  private function doTestViewsDroppedInOrder($format) {
    dbsteward::set_sql_format($format);

    // by dropping the entire schema
    $doc = $this->doc_with;
    $actual = $this->capture(function($ofs) use ($doc) {
      format_diff_views::drop_views_ordered($ofs, $doc, null);
    });

    if ($format == 'pgsql8') {
      // if we drop the whole schema, we don't need to drop individual views in it.
      $expected = <<<SQL
SQL;
    } else {
      $expected = <<<SQL
DROP VIEW IF EXISTS `view2`;
DROP VIEW IF EXISTS `view1`;
SQL;
    }
    
    $this->assertEquals($expected, $actual);

    // by dropping only the views
    $doc_without = $this->doc_without;
    $actual = $this->capture(function($ofs) use ($doc, $doc_without) {
      format_diff_views::drop_views_ordered($ofs, $doc, $doc_without);
    });

    if ($format == 'pgsql8') {
      $expected = <<<SQL
DROP VIEW IF EXISTS "public"."view2";
DROP VIEW IF EXISTS "public"."view1";
SQL;
    } else {
      $expected = <<<SQL
DROP VIEW IF EXISTS `view2`;
DROP VIEW IF EXISTS `view1`;
SQL;
    }
    
    $this->assertEquals($expected, $actual);
  }

  /**
   * @group pgsql8
   */
  public function testViewsCreatedInOrderPgsql8() {
    $this->doTestViewsCreatedInOrder('pgsql8');
  }

  /**
   * @group mysql5
   */
  public function testViewsCreatedInOrderMysql5() {
    $this->doTestViewsCreatedInOrder('mysql5');
  }

  private function doTestViewsCreatedInOrder($format) {
    dbsteward::set_sql_format($format);

    $doc = $this->doc_with;
    $actual = $this->capture(function($ofs) use ($doc) {
      format_diff_views::create_views_ordered($ofs, null, $doc);
    });
    if ($format == 'pgsql8') {
      $expected = <<<SQL
CREATE OR REPLACE VIEW "public"."view2" AS SELECT * FROM elsewhere;
ALTER VIEW "public"."view2" OWNER TO deployment;
CREATE OR REPLACE VIEW "public"."view1" AS SELECT * FROM view2;
ALTER VIEW "public"."view1" OWNER TO deployment;
SQL;
    } else {
      $expected = <<<SQL
CREATE OR REPLACE DEFINER = deployment SQL SECURITY DEFINER VIEW `view2` AS SELECT * FROM elsewhere;
CREATE OR REPLACE DEFINER = deployment SQL SECURITY DEFINER VIEW `view1` AS SELECT * FROM view2;
SQL;
    }
    
    $this->assertEquals($expected, $actual);
  }

  /**
   * @group pgsql8
   */
  public function testBuildsInOrderPgsql8() {
    $this->doTestBuildsInOrder('pgsql8');
  }

  /**
   * @group mysql5
   */
  public function testBuildsInOrderMysql5() {
    $this->doTestBuildsInOrder('mysql5');
  }

  private function doTestBuildsInOrder($format) {
    dbsteward::set_sql_format($format);

    $doc = $this->doc_with;
    $actual = $this->capture(function($ofs) use ($doc) {
      format::build_schema($doc, $ofs, array());
    });

    if ($format == 'pgsql8') {
      $expected = <<<SQL
CREATE OR REPLACE VIEW "public"."view2" AS SELECT * FROM elsewhere;
ALTER VIEW "public"."view2" OWNER TO deployment;
CREATE OR REPLACE VIEW "public"."view1" AS SELECT * FROM view2;
ALTER VIEW "public"."view1" OWNER TO deployment;
SQL;
    } else {
      $expected = <<<SQL
CREATE OR REPLACE DEFINER = deployment SQL SECURITY DEFINER VIEW `view2` AS SELECT * FROM elsewhere;
CREATE OR REPLACE DEFINER = deployment SQL SECURITY DEFINER VIEW `view1` AS SELECT * FROM view2;
SQL;
    }

    $this->assertEquals($expected, $actual);
  }

  /**
   * @group pgsql8
   */
  public function testUpgradesInOrderPgsql8() {
    $this->doTestUpgradesInOrder('pgsql8');
  }

  /**
   * @group mysql5
   */
  public function testUpgradesInOrderMysql5() {
    $this->doTestUpgradesInOrder('mysql5');
  }

  private function doTestUpgradesInOrder($format) {
    dbsteward::set_sql_format($format);

    $doc = $this->doc_with;
    $actual = $this->capture(function($ofs) use ($doc) {
      dbsteward::$new_database = $doc;
      dbsteward::$old_database = $doc;
      dbsteward::$single_stage_upgrade = true;
      format_diff::$as_transaction = false;
      format_diff::update_structure($ofs, $ofs);
    });

    if ($format == 'pgsql8') {
      $expected = <<<SQL
DROP VIEW IF EXISTS "public"."view2";
DROP VIEW IF EXISTS "public"."view1";
CREATE OR REPLACE VIEW "public"."view2" AS SELECT * FROM elsewhere;
ALTER VIEW "public"."view2" OWNER TO deployment;
CREATE OR REPLACE VIEW "public"."view1" AS SELECT * FROM view2;
ALTER VIEW "public"."view1" OWNER TO deployment;
SQL;
    } else {
      $expected = <<<SQL
DROP VIEW IF EXISTS `view2`;
DROP VIEW IF EXISTS `view1`;
CREATE OR REPLACE DEFINER = deployment SQL SECURITY DEFINER VIEW `view2` AS SELECT * FROM elsewhere;
CREATE OR REPLACE DEFINER = deployment SQL SECURITY DEFINER VIEW `view1` AS SELECT * FROM view2;
SQL;
    }

    $this->assertEquals($expected, $actual);
  }


  private function capture($callback) {
    $ofs = new mock_output_file_segmenter();
    call_user_func($callback, $ofs);
    // return $ofs->_get_output();
    return trim(
      preg_replace('/\n+/', "\n",
        preg_replace('/ *\n( |\t)+/', ' ',
          preg_replace('/-- .*\n/', "\n",
            $ofs->_get_output()
          )
        )
      )
    );
  }
}