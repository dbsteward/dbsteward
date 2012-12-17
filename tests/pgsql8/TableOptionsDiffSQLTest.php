<?php
/**
 * DBSteward unit test for pgsql8 tableOption diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

class TableOptionsDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
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
    <tableOption sqlFormat="pgsql8" name="tablespace" value="foo"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="pgsql8" name="tablespace" value="foo"/>
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
    <tableOption sqlFormat="pgsql8" name="with" value="(fillfactor=70)"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    
    $expected = <<<SQL
ALTER TABLE "public"."test"
  SET WITHOUT OIDS,
  SET (fillfactor=70);
SQL;
    
    $this->common($old, $new, $expected);
  }

  public function testAlter() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="pgsql8" name="with" value="(oids=true,fillfactor=70)"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="pgsql8" name="with" value="(fillfactor=70)"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $expected = <<<SQL
ALTER TABLE "public"."test"
  SET WITHOUT OIDS,
  SET (fillfactor=70);
SQL;
    
    $this->common($old, $new, $expected);
  }

  public function testAddAndAlter() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="pgsql8" name="with" value="(oids=true,fillfactor=70)"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="pgsql8" name="with" value="(oids=false,fillfactor=70)"/>
    <tableOption sqlFormat="pgsql8" name="tablespace" value="foo"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $expected = <<<SQL
CREATE FUNCTION __dbsteward_migrate_move_index_tablespace(TEXT,TEXT,TEXT) RETURNS void AS $$
  DECLARE idx RECORD;
BEGIN
  -- need to move the tablespace of the indexes as well
  FOR idx IN SELECT index_pgc.relname FROM pg_index
               INNER JOIN pg_class index_pgc ON index_pgc.oid = pg_index.indexrelid
               INNER JOIN pg_class table_pgc ON table_pgc.oid = pg_index.indrelid AND table_pgc.relname=$2
               INNER JOIN pg_namespace ON pg_namespace.oid = table_pgc.relnamespace AND pg_namespace.nspname=$1 LOOP
    EXECUTE 'ALTER INDEX ' || quote_ident($1) || '.' || quote_ident(idx.relname) || ' SET TABLESPACE ' || quote_ident($3) || ';';
  END LOOP;
END $$ LANGUAGE plpgsql;
SELECT __dbsteward_migrate_move_index_tablespace('public','test','foo');
DROP FUNCTION __dbsteward_migrate_move_index_tablespace(TEXT,TEXT,TEXT);

ALTER TABLE "public"."test"
  SET TABLESPACE "foo",
  SET WITHOUT OIDS,
  SET (fillfactor=70);
SQL;
      
    $this->common($old, $new, $expected);
  }

  public function testDrop() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="pgsql8" name="with" value="(oids=false,fillfactor=70)"/>
    <tableOption sqlFormat="pgsql8" name="tablespace" value="foo"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="pgsql8" name="with" value="(oids=false,fillfactor=70)"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $expected = <<<SQL
CREATE OR REPLACE FUNCTION __dbsteward_migrate_reset_tablespace(TEXT,TEXT) RETURNS void AS $$
  DECLARE tbsp TEXT;
  DECLARE idx RECORD;
BEGIN
  SELECT setting FROM pg_settings WHERE name='default_tablespace' INTO tbsp;

  IF tbsp = '' THEN
    tbsp := 'pg_default';
  END IF;

  EXECUTE 'ALTER TABLE ' || quote_ident($1) || '.' || quote_ident($2) || ' SET TABLESPACE ' || quote_ident(tbsp) || ';';

  -- need to move the tablespace of the indexes as well
  FOR idx IN SELECT index_pgc.relname FROM pg_index
               INNER JOIN pg_class index_pgc ON index_pgc.oid = pg_index.indexrelid
               INNER JOIN pg_class table_pgc ON table_pgc.oid = pg_index.indrelid AND table_pgc.relname=$2
               INNER JOIN pg_namespace ON pg_namespace.oid = table_pgc.relnamespace AND pg_namespace.nspname=$1 LOOP
    EXECUTE 'ALTER INDEX ' || quote_ident($1) || '.' || quote_ident(idx.relname) || ' SET TABLESPACE ' || quote_ident(tbsp) || ';';
  END LOOP;
END $$ LANGUAGE plpgsql;
SELECT __dbsteward_migrate_reset_tablespace('public','test');
DROP FUNCTION __dbsteward_migrate_reset_tablespace(TEXT,TEXT);
SQL;
      
    $this->common($old, $new, $expected);
  }

  public function testDropAdd() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="pgsql8" name="tablespace" value="foo"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="pgsql8" name="with" value="(oids=false,fillfactor=70)"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $expected = <<<SQL
CREATE OR REPLACE FUNCTION __dbsteward_migrate_reset_tablespace(TEXT,TEXT) RETURNS void AS $$
  DECLARE tbsp TEXT;
  DECLARE idx RECORD;
BEGIN
  SELECT setting FROM pg_settings WHERE name='default_tablespace' INTO tbsp;

  IF tbsp = '' THEN
    tbsp := 'pg_default';
  END IF;

  EXECUTE 'ALTER TABLE ' || quote_ident($1) || '.' || quote_ident($2) || ' SET TABLESPACE ' || quote_ident(tbsp) || ';';

  -- need to move the tablespace of the indexes as well
  FOR idx IN SELECT index_pgc.relname FROM pg_index
               INNER JOIN pg_class index_pgc ON index_pgc.oid = pg_index.indexrelid
               INNER JOIN pg_class table_pgc ON table_pgc.oid = pg_index.indrelid AND table_pgc.relname=$2
               INNER JOIN pg_namespace ON pg_namespace.oid = table_pgc.relnamespace AND pg_namespace.nspname=$1 LOOP
    EXECUTE 'ALTER INDEX ' || quote_ident($1) || '.' || quote_ident(idx.relname) || ' SET TABLESPACE ' || quote_ident(tbsp) || ';';
  END LOOP;
END $$ LANGUAGE plpgsql;
SELECT __dbsteward_migrate_reset_tablespace('public','test');
DROP FUNCTION __dbsteward_migrate_reset_tablespace(TEXT,TEXT);

ALTER TABLE "public"."test"
  SET WITHOUT OIDS,
  SET (fillfactor=70);
SQL;
      
    $this->common($old, $new, $expected);
  }

  private function common($old, $new, $expected) {
    $ofs = new mock_output_file_segmenter();

    $old_schema = new SimpleXMLElement($old);
    $old_table = $old_schema->table;

    $new_schema = new SimpleXMLElement($new);
    $new_table =$new_schema->table;

    pgsql8_diff_tables::update_table_options($ofs, $ofs, $old_schema, $old_table, $new_schema, $new_table);

    $actual = trim($ofs->_get_output());
    $this->assertEquals($expected, $actual);
  }
}