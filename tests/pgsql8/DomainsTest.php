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
    
    $schema = simplexml_load_string($xml);
    $type = $schema->type;

    $sql = pgsql8_type::get_creation_sql($schema, $type);

    $this->assertEquals("CREATE DOMAIN \"domains\".\"my_domain\" AS int;", $sql);
  }

  public function testCreationWithDefaultAndNotNull() {
    $xml = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="int" default="5" null="false"/>
  </type>
</schema>
XML;
    
    $schema = simplexml_load_string($xml);
    $type = $schema->type;

    $sql = pgsql8_type::get_creation_sql($schema, $type);

    $this->assertEquals("CREATE DOMAIN \"domains\".\"my_domain\" AS int\n  DEFAULT 5\n  NOT NULL;", $sql);
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
    
    $schema = simplexml_load_string($xml);
    $type = $schema->type;

    $sql = pgsql8_type::get_creation_sql($schema, $type);

    $this->assertEquals("CREATE DOMAIN \"domains\".\"my_domain\" AS int\n  CONSTRAINT \"gt_five\" CHECK(VALUE > 5);", $sql);
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
    
    $schema = simplexml_load_string($xml);
    $type = $schema->type;

    $sql = pgsql8_type::get_creation_sql($schema, $type);

    $expected = <<<SQL
CREATE DOMAIN "domains"."my_domain" AS int
  NOT NULL
  CONSTRAINT "lt_ten" CHECK(VALUE < 10)
  CONSTRAINT "gt_five" CHECK(VALUE > 5);
SQL;

    $this->assertEquals($expected, $sql);
  }


  public function testCreationWithQuotedDefault() {
    $xml = <<<XML
<schema name="domains">
  <type name="my_domain" type="domain">
    <domainType baseType="varchar(20)" default="abc"/>
  </type>
</schema>
XML;
    
    $schema = simplexml_load_string($xml);
    $type = $schema->type;

    $sql = pgsql8_type::get_creation_sql($schema, $type);

    $this->assertEquals("CREATE DOMAIN \"domains\".\"my_domain\" AS varchar(20)\n  DEFAULT E'abc';", $sql);
  }
}