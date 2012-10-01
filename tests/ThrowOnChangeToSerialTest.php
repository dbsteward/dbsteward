<?php
/**
 * Tests that in postgres, you cannot change a non-serial column to a serial one
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/mock_output_file_segmenter.php';
require_once __DIR__ . '/../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../lib/DBSteward/sql_format/pgsql8/pgsql8.php';
require_once __DIR__ . '/../lib/DBSteward/sql_format/pgsql8/pgsql8_diff.php';

class ThrowOnChangeToSerial extends PHPUnit_Framework_TestCase {
  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;

    $xml = <<<XML
<dbsteward>
  <database>
    <host>db-host</host>
    <name>dbsteward</name>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
  </database>
</dbsteward>
XML;
    $db = new SimpleXMLElement($xml);
    dbsteward::$new_database = $db;
    dbsteward::$old_database = $db;
  }

  public function testThrowWhenChangedToSerial() {
    $none = <<<XML
<schema name="public" owner="ROLE_OWNER">
</schema>
XML;

    $old = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="sometable" owner="ROLE_OWNER" primaryKey="id">
    <column name="id" type="int"/>
  </table>
</schema>
XML;
    $new = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <table name="sometable" owner="ROLE_OWNER" primaryKey="id">
    <column name="id" type="serial"/>
  </table>
</schema>
XML;

    $none_schema = new SimpleXMLElement($none);
    $old_schema = new SimpleXMLElement($old);
    $new_schema = new SimpleXMLElement($new);

    $ofs = new mock_output_file_segmenter();

    // make sure that creating a *new* serial *doesn't* throw
    pgsql8_diff_tables::diff_tables($ofs, $ofs, $none_schema, $new_schema, $none_schema->table, $new_schema->table);

    try {
      // changing int -> serial *should* throw
      pgsql8_diff_tables::diff_tables($ofs, $ofs, $old_schema, $new_schema, $old_schema->table, $new_schema->table);
    }
    catch (Exception $ex) {
      $this->assertEquals("Column types cannot be altered to serial. If this column cannot be recreated as part of database change control, a user defined serial should be created, and corresponding nextval() defined as the default for the column.", $ex->getMessage());
      return;
    }
    $this->fail('Expected exception because of changing to a serial type');
  }
}
?>
