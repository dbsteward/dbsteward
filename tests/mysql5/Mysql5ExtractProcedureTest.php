<?php
/**
 * DBSteward unit test for mysql5 function sql generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 */

require_once __DIR__ . '/Mysql5ExtractionTest.php';

/**
 * @group mysql5
 */
class Mysql5ExtractProcedureTest extends Mysql5ExtractionTest { 

  public function testExtractProcedure() {
    $sql = <<<ENDSQL
DROP PROCEDURE IF EXISTS why_would_i_do_this;
DELIMITER __;
CREATE DEFINER = deployment PROCEDURE why_would_i_do_this (IN `str` varchar(25), OUT `len` int(11))
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY DEFINER
BEGIN
  SELECT length(str)
    INTO len;
END;__
ENDSQL;

  $expected = <<<XML
<function name="why_would_i_do_this" owner="ROLE_OWNER" returns="" description="" procedure="true" cachePolicy="VOLATILE" mysqlEvalType="MODIFIES_SQL_DATA" securityDefiner="true">
      <functionParameter name="str" type="varchar(25)" direction="IN"/>
      <functionParameter name="len" type="int(11)" direction="OUT"/>
      <functionDefinition language="sql" sqlFormat="mysql5">BEGIN
  SELECT length(str)
    INTO len;
END</functionDefinition>
    </function>
XML;

    $schema = $this->extract($sql);
    $this->assertEquals($expected, $schema->function->asXML());
  }
}
