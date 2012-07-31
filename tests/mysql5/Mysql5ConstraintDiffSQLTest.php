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
ALTER TABLE `test` ADD UNIQUE INDEX `test_uqc_idx` (`uqc`);
SQL;
    $this->common($this->xml_pka, $this->xml_pka_uqc, $expected);
  }

  public function testDropSome() {
    $expected = <<<SQL
ALTER TABLE `test` DROP INDEX `test_uqc_idx`;
SQL;
    $this->common($this->xml_pka_uqc, $this->xml_pka, $expected);
  }

  public function testChangeOne() {
    $expected = <<<SQL
ALTER TABLE `test` DROP PRIMARY KEY;
ALTER TABLE `test` ADD PRIMARY KEY (`pkb`);
SQL;
    $this->common($this->xml_pka, $this->xml_pkb, $expected);
  }

  public function testAddSomeAndChange() {
    $expected = <<<SQL
ALTER TABLE `test` DROP PRIMARY KEY;
ALTER TABLE `test` ADD PRIMARY KEY (`pkb`);
ALTER TABLE `test` ADD FOREIGN KEY `test_cfke_fk` (`cfke`) REFERENCES `other` (`pka`);
SQL;
    $this->common($this->xml_pka, $this->xml_pkb_cfke, $expected);
  }

  public function testDropSomeAndChange() {
    $expected = <<<SQL
ALTER TABLE `test` DROP PRIMARY KEY;
ALTER TABLE `test` DROP INDEX `test_uqc_idx`;
ALTER TABLE `test` DROP FOREIGN KEY `test_ifkd_fk`;
ALTER TABLE `test` ADD PRIMARY KEY (`pkb`);
SQL;
    $this->common($this->xml_pka_uqc_ifkd_cfke, $this->xml_pkb_cfke, $expected);
  }

  public function common($a, $b, $expected) {
    $dbs_a = new SimpleXMLElement($a);
    $dbs_b = new SimpleXMLElement($b);

    $ofs = new mock_output_file_segmenter();

    dbsteward::$old_database = $dbs_a;
    dbsteward::$new_database = $dbs_b;

    // all constraints
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, 'all', true);
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, 'all', false);
    $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
    $this->assertEquals($expected, $actual);
    $ofs->_clear_output();

    // primary keys
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, 'primaryKey', true);
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, 'primaryKey', false);
    $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
    $pk_expected = trim(preg_replace("/.*(INDEX|FOREIGN).*\n?/",'',$expected));
    $this->assertEquals($pk_expected, $actual, "primaryKey diff");
    $ofs->_clear_output();

    // constraints with drops
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, 'constraint', true);
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, 'constraint', false);
    $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
    $constraint_expected = trim(preg_replace("/.*(PRIMARY).*\n?/",'',$expected));
    $this->assertEquals($constraint_expected, $actual, "constraint-only diff");
    $ofs->_clear_output();

    // foreign keys with drops
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, 'foreignKey', true);
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, 'foreignKey', false);
    $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
    $fk_expected = trim(preg_replace("/.*(PRIMARY|INDEX).*\n?/",'',$expected));
    $this->assertEquals($fk_expected, $actual, "foreignKey diff");
    $ofs->_clear_output();
  }
}