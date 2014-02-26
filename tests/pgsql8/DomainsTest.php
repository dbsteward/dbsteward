<?php
/**
 * DBSteward unit test for pgsql8 domains tests
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../mock_output_file_segmenter.php';

/**
 * @group pgsql8
 */
class DomainsTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('pgsql8');
    dbsteward::$quote_all_names = TRUE;
  }

  public function testCreationNoDomainTypeThrows() {
    $xml = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
  </type>
</schema>
XML;
    
    $schema = simplexml_load_string($xml);
    $type = $schema->type;
    
    try {
      $sql = pgsql8_type::get_creation_sql($schema, $type);
    }
    catch (exception $e) {
      $this->assertContains('no domainType element', $e->getMessage());
      return;
    }
    $this->fail("Expected 'no domainType element' exception, got DDL: $sql");
  }

  public function testCreation() {
    $xml = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
  </type>
</schema>
XML;
    
    $this->build($xml, "CREATE DOMAIN \"domains\".\"my_domain\" AS int;");
  }

  public function testCreationWithDefaultAndNotNull() {
    $xml = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int" default="5" null="false"/>
  </type>
</schema>
XML;
    
    $this->build($xml, "CREATE DOMAIN \"domains\".\"my_domain\" AS int\n  DEFAULT 5\n  NOT NULL;");
  }


  public function testCreationWithConstraint() {
    $xml = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
    <domainConstraint name="gt_five">VALUE > 5</domainConstraint>
  </type>
</schema>
XML;

    $this->build($xml, "CREATE DOMAIN \"domains\".\"my_domain\" AS int\n  CONSTRAINT \"gt_five\" CHECK(VALUE > 5);");
  }

  public function testCreationWithMultipleConstraints() {
    $xml = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int" null="false"/>
    <domainConstraint name="lt_ten">CHECK(VALUE &lt; 10)</domainConstraint>
    <domainConstraint name="gt_five">VALUE > 5</domainConstraint>
  </type>
</schema>
XML;

    $expected = <<<SQL
CREATE DOMAIN "domains"."my_domain" AS int
  NOT NULL
  CONSTRAINT "lt_ten" CHECK(VALUE < 10)
  CONSTRAINT "gt_five" CHECK(VALUE > 5);
SQL;

    $this->build($xml, $expected);
  }


  public function testCreationWithQuotedDefault() {
    $xml = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="varchar(20)" default="abc"/>
  </type>
</schema>
XML;

    $this->build($xml, "CREATE DOMAIN \"domains\".\"my_domain\" AS varchar(20)\n  DEFAULT E'abc';");
  }

  public function testDrop() {
    $xml = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="varchar(20)" default="abc"/>
  </type>
</schema>
XML;
    
    $schema = simplexml_load_string($xml);
    $type = $schema->type;

    $this->assertEquals('DROP DOMAIN "domains"."my_domain";', pgsql8_type::get_drop_sql($schema, $type));
  }

  public function testDiffBaseType() {
    $old = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
  </type>
</schema>
XML;
    
    $new = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="varchar(20)"/>
  </type>
</schema>
XML;
  
    $this->diff($old, $new, "DROP DOMAIN \"domains\".\"my_domain\";\nCREATE DOMAIN \"domains\".\"my_domain\" AS varchar(20);");
  }

  public function testDiffChangeDefault() {
    $old = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int" default="5"/>
  </type>
</schema>
XML;
    
    $new = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int" default="10"/>
  </type>
</schema>
XML;
  
    $this->diff($old, $new, "ALTER DOMAIN \"domains\".\"my_domain\" SET DEFAULT 10;");
  }


  public function testDiffDropDefault() {
    $old = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int" default="5"/>
  </type>
</schema>
XML;
    
    $new = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
  </type>
</schema>
XML;
  
    $this->diff($old, $new, "ALTER DOMAIN \"domains\".\"my_domain\" DROP DEFAULT;");
  }

  public function testDiffMakeNull() {
    $old = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int" null="false"/>
  </type>
</schema>
XML;
    
    $new = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
  </type>
</schema>
XML;
  
    $this->diff($old, $new, "ALTER DOMAIN \"domains\".\"my_domain\" DROP NOT NULL;");
  }

  public function testDiffMakeNotNull() {
    $old = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
  </type>
</schema>
XML;
    
    $new = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int" null="false"/>
  </type>
</schema>
XML;
  
    $this->diff($old, $new, "ALTER DOMAIN \"domains\".\"my_domain\" SET NOT NULL;");
  }


  public function testDiffAddDropChangeConstraints() {
    $old = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
    <domainConstraint name="gt5">VALUE > 5</domainConstraint>
    <domainConstraint name="lt10">VALUE &lt; 10</domainConstraint>
    <domainConstraint name="eq7">VALUE = 7</domainConstraint>
  </type>
</schema>
XML;
    
    $new = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
    <domainConstraint name="gt5">CHECK(VALUE > 5)</domainConstraint>
    <domainConstraint name="gt4">VALUE > 4</domainConstraint>
    <domainConstraint name="eq7">VALUE = 2</domainConstraint>
  </type>
</schema>
XML;

    $expected = <<<SQL
ALTER DOMAIN "domains"."my_domain" ADD CONSTRAINT gt4 CHECK(VALUE > 4);
ALTER DOMAIN "domains"."my_domain" DROP CONSTRAINT eq7;
ALTER DOMAIN "domains"."my_domain" ADD CONSTRAINT eq7 CHECK(VALUE = 2);
ALTER DOMAIN "domains"."my_domain" DROP CONSTRAINT lt10;
SQL;
  
    $this->diff($old, $new, $expected);
  }


  public function testDiffTablesAlter() {
    $old = <<<XML
<schema name="domains">
  <table name="some_table" primaryKey="col1">
    <column name="col1" type="int" null="false"/>
    <column name="mycol" type="my_domain"/>
  </table>
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
    <domainConstraint name="gt5">VALUE > 5</domainConstraint>
  </type>
</schema>
XML;
  
    $new = <<<XML
<schema name="domains">
  <table name="some_table" primaryKey="col1">
    <column name="col1" type="int" null="false"/>
    <column name="mycol" type="domains.my_domain"/>
  </table>
  <type name="my_domain" type="domain">
    <domainType baseType="int"/>
    <domainConstraint name="gt5">VALUE > 3</domainConstraint>
  </type>
</schema>
XML;
    
    $expected = <<<SQL
ALTER TABLE "domains"."some_table" ALTER COLUMN "mycol" TYPE int;
ALTER DOMAIN "domains"."my_domain" DROP CONSTRAINT gt5;
ALTER DOMAIN "domains"."my_domain" ADD CONSTRAINT gt5 CHECK(VALUE > 3);
ALTER TABLE "domains"."some_table" ALTER COLUMN "mycol" TYPE "domains"."my_domain" USING "mycol"::"domains"."my_domain";
SQL;

    $this->diff($old, $new, $expected);
  }


  private function build($xml, $expected) {
    $schema = simplexml_load_string($xml);
    $type = $schema->type;

    $sql = pgsql8_type::get_creation_sql($schema, $type);

    $this->assertEquals($expected, $sql);
  }

  private function diff($old, $new, $expected) {
    $ofs = new mock_output_file_segmenter();

    $old = '<dbsteward><database/>' . $old . '</dbsteward>';
    $new = '<dbsteward><database/>' . $new . '</dbsteward>';

    $old_doc = simplexml_load_string($old);
    $new_doc = simplexml_load_string($new);

    dbsteward::$old_database = $old_doc;
    dbsteward::$new_database = $new_doc;
    pgsql8_diff::$old_table_dependency = xml_parser::table_dependency_order($old_doc);
    pgsql8_diff::$new_table_dependency = xml_parser::table_dependency_order($new_doc);

    pgsql8_diff_types::apply_changes($ofs, $old_doc->schema, $new_doc->schema);
    $sql = trim(preg_replace('/\n\n+/', "\n", preg_replace('/^--.*$/m', '', $ofs->_get_output())));

    $this->assertEquals($expected, $sql);
  }
}