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
ALTER TABLE `test` ADD CONSTRAINT `test_cfke_fk` FOREIGN KEY `test_cfke_fk` (`cfke`) REFERENCES `other` (`pka`);
SQL;
    $this->common($this->xml_pka, $this->xml_pkb_cfke, $expected);
  }

  public function testDropSomeAndChange() {
    $expected = <<<SQL
ALTER TABLE `test` DROP PRIMARY KEY;
ALTER TABLE `test` DROP INDEX `test_uqc_idx`;
ALTER TABLE `test` DROP FOREIGN KEY `test_ifkd_fk`, DROP INDEX `test_ifkd_fk`;
ALTER TABLE `test` ADD PRIMARY KEY (`pkb`);
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
  <table name="newtable" owner="NOBODY" primaryKey="pkb" oldName="test">
    <column name="pkb" type="int" oldName="pka"/>
  </table>
</schema>
</dbsteward>
XML;
    // drop the PK on test *before* diffing the table
    // add the renamed PK on the renamed table *after* diffing the table
    $expected = <<<SQL
ALTER TABLE `test` DROP PRIMARY KEY;
ALTER TABLE `newtable` ADD PRIMARY KEY (`pkb`);
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

    $this->common($auto, $auto, "");

    $this->common($auto, $notauto, "");

    $this->common_type($notauto, $auto, "ALTER TABLE `test` MODIFY COLUMN `pka` int AUTO_INCREMENT;", 'primaryKey');
  }

  private function common($a, $b, $expected) {
    $this->common_type($a, $b, $expected, 'all');
    $this->common_type($a, $b, $expected, 'primaryKey');
    $this->common_type($a, $b, $expected, 'constraint');
    $this->common_type($a, $b, $expected, 'foreignKey');
  }

  private function common_type($a, $b, $expected, $type) {
    $dbs_a = new SimpleXMLElement($a);
    $dbs_b = new SimpleXMLElement($b);

    $ofs = new mock_output_file_segmenter();

    dbsteward::$old_database = $dbs_a;
    dbsteward::$new_database = $dbs_b;

    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, $type, true);
    mysql5_diff_constraints::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, $type, false);

    switch ($type) {
      case 'all':
        $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
        $this->assertEquals($expected, $actual, "all constraints diff");
        break;
      case 'primaryKey':
        $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
        $pk_expected = trim(preg_replace("/.*(INDEX|FOREIGN).*\n?/",'',$expected));
        $this->assertEquals($pk_expected, $actual, "primaryKey diff");
        break;
      case 'constraint':
        $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
        $constraint_expected = trim(preg_replace("/.*(PRIMARY).*\n?/",'',$expected));
        $this->assertEquals($constraint_expected, $actual, "constraint-only diff");
        break;
      case 'foreignKey':
        $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
        $fk_expected = trim(preg_replace("/.*(PRIMARY|(?<!, DROP )INDEX).*\n?/",'',$expected));
        $this->assertEquals($fk_expected, $actual, "foreignKey diff");
        break;
      default:
        $this->fail("Unknown type");
    }
  }
}
?>
