<?php
/**
 * DBSteward unit test for mysql5 constraint diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

/**
 * @group mysql5
 */
class Mysql5ConstraintDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
  }

  private $xml_pka = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY" primaryKey="pka">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"/>
    <column name="cfke"/>
  </table>
</schema>
</dbsteward>
XML;

  private $xml_pkb = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY" primaryKey="pkb">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"/>
    <column name="cfke"/>
  </table>
</schema>
</dbsteward>
XML;

  private $xml_pka_uqc = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY" primaryKey="pka">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"/>
    <column name="cfke"/>
    <constraint name="test_uqc_idx" type="unique" definition="(`uqc`)"/>
  </table>
</schema>
</dbsteward>
XML;

  private $xml_pka_ifkd = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="pka">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"
      foreignSchema="public"
      foreignTable="other"
      foreignColumn="pka"
      foreignKeyName="test_ifkd_fk"/>
    <column name="cfke"/>
  </table>
  <table name="other" primaryKey="pka">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"/>
    <column name="cfke"/>
  </table>
</schema>
</dbsteward>
XML;

  private $xml_pka_uqc_ifkd = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="pka">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"
      foreignSchema="public"
      foreignTable="other"
      foreignColumn="pka"
      foreignKeyName="test_ifkd_fk"/>
    <column name="cfke"/>
    <constraint name="test_uqc_idx" type="unique" definition="(`uqc`)"/>
  </table>
  <table name="other" primaryKey="pka">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"/>
    <column name="cfke"/>
  </table>
</schema>
</dbsteward>
XML;

  private $xml_pkb_cfke = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="pkb">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"/>
    <column name="cfke"/>
    <constraint name="test_cfke_fk" type="foreign key" definition="(`cfke`) REFERENCES `other` (`pka`)"/>
  </table>
  <table name="other" primaryKey="pka">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"/>
    <column name="cfke"/>
  </table>
</schema>
</dbsteward>
XML;

  private $xml_pka_uqc_ifkd_cfke = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="pka">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"
      foreignSchema="public"
      foreignTable="other"
      foreignColumn="pka"
      foreignKeyName="test_ifkd_fk"/>
    <column name="cfke"/>
    <constraint name="test_cfke_fk" type="foreign key" definition="(`cfke`) REFERENCES `other` (`pka`)"/>
    <constraint name="test_uqc_idx" type="unique" definition="(`uqc`)"/>
  </table>
  <table name="other" primaryKey="pka">
    <column name="pka"/>
    <column name="pkb"/>
    <column name="uqc"/>
    <column name="ifkd"/>
    <column name="cfke"/>
  </table>
</schema>
</dbsteward>
XML;

  public function testSameToSame() {
    $this->common($this->xml_pka, $this->xml_pka, "");
  }

  public function testAddSome() {
    $expected = <<<SQL
ALTER TABLE `public`.`test`
  ADD UNIQUE INDEX `test_uqc_idx` (`uqc`);
SQL;
    $this->common($this->xml_pka, $this->xml_pka_uqc, $expected);
  }

  public function testDropSome() {
    $expected = <<<SQL
ALTER TABLE `public`.`test`
  DROP INDEX `test_uqc_idx`;
SQL;
    $this->common($this->xml_pka_uqc, $this->xml_pka, $expected);
  }

  public function testChangeOne() {
    $expected = <<<SQL
ALTER TABLE `public`.`test`
  DROP PRIMARY KEY;
ALTER TABLE `public`.`test`
  ADD PRIMARY KEY (`pkb`);
SQL;
    $this->common($this->xml_pka, $this->xml_pkb, $expected);
  }

  public function testAddSomeAndChange() {
    $expected = <<<SQL
ALTER TABLE `public`.`test`
  DROP PRIMARY KEY;
ALTER TABLE `public`.`test`
  ADD PRIMARY KEY (`pkb`),
  ADD CONSTRAINT `test_cfke_fk` FOREIGN KEY `test_cfke_fk` (`cfke`) REFERENCES `other` (`pka`);
SQL;
    $this->common($this->xml_pka, $this->xml_pkb_cfke, $expected);
  }

  public function testDropSomeAndChange() {
    $expected = <<<SQL
ALTER TABLE `public`.`test`
  DROP PRIMARY KEY,
  DROP INDEX `test_uqc_idx`,
  DROP FOREIGN KEY `test_ifkd_fk`;
ALTER TABLE `public`.`test`
  ADD PRIMARY KEY (`pkb`);
SQL;
    $this->common($this->xml_pka_uqc_ifkd_cfke, $this->xml_pkb_cfke, $expected);
  }

  public function testChangePrimaryKeyNameAndTable() {
    $old = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY" primaryKey="pka">
    <column name="pka" type="int"/>
  </table>
</schema>
</dbsteward>
XML;
    $new = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="newtable" owner="NOBODY" primaryKey="pkb" oldTableName="test">
    <column name="pkb" type="int" oldColumnName="pka"/>
  </table>
</schema>
</dbsteward>
XML;
    // drop the PK on test *before* diffing the table
    // add the renamed PK on the renamed table *after* diffing the table
    $expected = <<<SQL
ALTER TABLE `public`.`test`
  DROP PRIMARY KEY;
ALTER TABLE `public`.`newtable`
  ADD PRIMARY KEY (`pkb`);
SQL;
    $this->common($old, $new, $expected);
  }

  public function testAutoIncrement() {
    $auto = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY" primaryKey="pka">
    <column name="pka" type="int auto_increment"/>
  </table>
</schema>
</dbsteward>
XML;
    $notauto = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" owner="NOBODY" primaryKey="pka">
    <column name="pka" type="int" />
  </table>
</schema>
</dbsteward>
XML;

    // auto-increment is no longer considered a constraint, but rather part of a type, and is calculated during the tables diff
    $this->common($auto, $auto, "");

    $this->common($auto, $notauto, "");

    $this->common($notauto, $auto, "", 'primaryKey');
  }

  private function common($a, $b, $expected, $type = 'all') {
    $dbs_a = new SimpleXMLElement($a);
    $dbs_b = new SimpleXMLElement($b);

    $ofs = new mock_output_file_segmenter();

    dbsteward::$old_database = $dbs_a;
    dbsteward::$new_database = $dbs_b;

    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, $type, true);
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, $type, false);
    $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
    $this->assertEquals($expected, $actual);
  }
}
?>
