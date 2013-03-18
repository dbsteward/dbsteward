<?php
/**
 * DBSteward unit test for mysql5 tableOption diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

class Mysql5TableOptionsDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  public function testNoChange() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    
    $this->common($old, $new, "");
  }


  public function testAdd() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    
    $this->common($old, $new, "ALTER TABLE `public`.`test` ENGINE=InnoDB;");
  }

  public function testAlter() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="MyISAM"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    
    $this->common($old, $new, "ALTER TABLE `public`.`test` ENGINE=MyISAM;");
  }

  public function testAddAndAlter() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="MyISAM"/>
    <tableOption sqlFormat="mysql5" name="auto_increment" value="5"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
      
    $this->common($old, $new, "ALTER TABLE `public`.`test` ENGINE=MyISAM\nAUTO_INCREMENT=5;");
  }

  public function testDrop() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $expected = <<<SQL
-- Table `public`.`test` must be recreated to drop options: engine
CREATE TABLE `public`.`test_DBSTEWARD_MIGRATION`
SELECT * FROM `public`.`test`;
DROP TABLE `public`.`test`;
RENAME TABLE `public`.`test_DBSTEWARD_MIGRATION` TO `public`.`test`;
SQL;
      
    $this->common($old, $new, $expected);
  }

  public function testDropAddAlter() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <tableOption sqlFormat="mysql5" name="auto_increment" value="5"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="auto_increment" value="10"/>
    <tableOption sqlFormat="mysql5" name="row_format" value="compressed"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $expected = <<<SQL
-- Table `public`.`test` must be recreated to drop options: engine
CREATE TABLE `public`.`test_DBSTEWARD_MIGRATION`
AUTO_INCREMENT=10
ROW_FORMAT=compressed
SELECT * FROM `public`.`test`;
DROP TABLE `public`.`test`;
RENAME TABLE `public`.`test_DBSTEWARD_MIGRATION` TO `public`.`test`;
SQL;
      
    $this->common($old, $new, $expected);
  }

  private function common($old, $new, $expected) {
    $ofs = new mock_output_file_segmenter();

    $old_schema = new SimpleXMLElement($old);
    $old_table = $old_schema->table;

    $new_schema = new SimpleXMLElement($new);
    $new_table =$new_schema->table;

    mysql5_diff_tables::update_table_options($ofs, $ofs, $old_schema, $old_table, $new_schema, $new_table);

    $actual = trim($ofs->_get_output());
    $this->assertEquals($expected, $actual);
  }
}