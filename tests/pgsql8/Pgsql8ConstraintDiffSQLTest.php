<?php
/**
 * DBSteward unit test for pgsql8 constraint diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 * @group nodb
 */
class Pgsql8ConstraintDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
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
    <constraint name="test_uqc_idx" type="unique" definition="(uqc)"/>
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
    <constraint name="test_uqc_idx" type="unique" definition="(uqc)"/>
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
    <constraint name="test_cfke_fk" type="foreign key" definition="(cfke) REFERENCES public.other (pka)"/>
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
    <constraint name="test_cfke_fk" type="foreign key" definition="(cfke) REFERENCES public.other (pka)"/>
    <constraint name="test_uqc_idx" type="unique" definition="(uqc)"/>
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
ALTER TABLE "public"."test"
\tADD CONSTRAINT "test_uqc_idx" UNIQUE (uqc);
SQL;
    $this->common($this->xml_pka, $this->xml_pka_uqc, $expected);
  }

  public function testDropSome() {
    $expected = <<<SQL
ALTER TABLE "public"."test"
\tDROP CONSTRAINT "test_uqc_idx";
SQL;
    $this->common($this->xml_pka_uqc, $this->xml_pka, $expected);
  }

  public function testChangeOne() {
    $expected = <<<SQL
ALTER TABLE "public"."test"
\tDROP CONSTRAINT "test_pkey";
ALTER TABLE "public"."test"
\tADD CONSTRAINT "test_pkey" PRIMARY KEY ("pkb");
SQL;
    $this->common($this->xml_pka, $this->xml_pkb, $expected);
  }

  public function testAddSomeAndChange() {
    $expected = <<<SQL
ALTER TABLE "public"."test"
\tDROP CONSTRAINT "test_pkey";
ALTER TABLE "public"."test"
\tADD CONSTRAINT "test_pkey" PRIMARY KEY ("pkb");
ALTER TABLE "public"."test"
\tADD CONSTRAINT "test_cfke_fk" FOREIGN KEY (cfke) REFERENCES public.other (pka);
SQL;
    $this->common($this->xml_pka, $this->xml_pkb_cfke, $expected);
  }

  public function testDropSomeAndChange() {
    $expected = <<<SQL
ALTER TABLE "public"."test"
\tDROP CONSTRAINT "test_pkey";
ALTER TABLE "public"."test"
\tDROP CONSTRAINT "test_uqc_idx";
ALTER TABLE "public"."test"
\tDROP CONSTRAINT "test_ifkd_fk";
ALTER TABLE "public"."test"
\tADD CONSTRAINT "test_pkey" PRIMARY KEY ("pkb");
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
  <table name="newtable" owner="NOBODY" primaryKey="pkb" oldSchemaName="public" oldTableName="test">
    <column name="pkb" type="int" oldColumnName="pka"/>
  </table>
</schema>
</dbsteward>
XML;
    // PK is dropped and added after the table is renamed
    $expected = <<<SQL
ALTER TABLE "public"."newtable"
\tDROP CONSTRAINT "test_pkey";
ALTER TABLE "public"."newtable"
\tADD CONSTRAINT "newtable_pkey" PRIMARY KEY ("pkb");
SQL;

    $dbs_a = new SimpleXMLElement($old);
    $dbs_b = new SimpleXMLElement($new);

    $ofs = new mock_output_file_segmenter();

    dbsteward::$old_database = $dbs_a;
    dbsteward::$new_database = $dbs_b;

    // in psql8_diff::update_structure() when the new schema doesn't contain the old table name,
    // $new_table is set to null for the first diff_constraints_table() call, adjusted this test accordingly
    pgsql8_diff_tables::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, null, 'primaryKey', true);
    pgsql8_diff_tables::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, 'primaryKey', false);
    $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
    $this->assertEquals($expected, $actual);
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

  public function testChangeColumnTypeWithForeignKey() {
    $old = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="pka">
    <column name="pka"/>
    <column name="ifkd"
      foreignSchema="public"
      foreignTable="other"
      foreignColumn="pka"
      foreignKeyName="test_ifkd_fk"/>
  </table>
  <table name="other" primaryKey="pka">
    <column name="pka" type="int"/>
  </table>
</schema>
</dbsteward>
XML;

    // changed type of pka in table other from int to text
    $new = <<<XML
<dbsteward>
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="pka">
    <column name="pka"/>
    <column name="ifkd"
      foreignSchema="public"
      foreignTable="other"
      foreignColumn="pka"
      foreignKeyName="test_ifkd_fk"/>
  </table>
  <table name="other" primaryKey="pka">
    <column name="pka" type="text"/>
  </table>
</schema>
</dbsteward>
XML;

    $expected = <<<SQL
ALTER TABLE "public"."test"
\tDROP CONSTRAINT "test_ifkd_fk";
ALTER TABLE "public"."test"
\tADD CONSTRAINT "test_ifkd_fk" FOREIGN KEY ("ifkd") REFERENCES "public"."other" ("pka");
SQL;

    $this->common($old, $new, $expected);
  }

  private function common($a, $b, $expected, $type = 'all') {
    $dbs_a = new SimpleXMLElement($a);
    $dbs_b = new SimpleXMLElement($b);

    $ofs = new mock_output_file_segmenter();

    dbsteward::$old_database = $dbs_a;
    dbsteward::$new_database = $dbs_b;

    pgsql8_diff_tables::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, $type, true);
    pgsql8_diff_tables::diff_constraints_table($ofs, $dbs_a->schema, $dbs_a->schema->table, $dbs_b->schema, $dbs_b->schema->table, $type, false);
    $actual = trim(preg_replace("/--.*\n/",'',$ofs->_get_output()));
    $this->assertEquals($expected, $actual);
  }
}
