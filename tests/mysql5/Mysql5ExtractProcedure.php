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
class Mysql5ExtractProcedure extends Mysql5ExtractionTest { 

  public function testExtractProcedure() {
    $sql = <<<SQL
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
SQL;

  $expected = <<<XML
<schema name="Mysql5ExtractionTest" owner="ROLE_OWNER">
    <grant operation="ALTER,ALTER ROUTINE,CREATE,CREATE ROUTINE,CREATE TEMPORARY TABLES,CREATE VIEW,DELETE,DROP,EVENT,EXECUTE,INDEX,INSERT,LOCK TABLES,REFERENCES,SELECT,SHOW VIEW,TRIGGER,UPDATE" role="ROLE_APPLICATION"/>
    <function name="why_would_i_do_this" owner="ROLE_OWNER" returns="" description="" procedure="true" cachePolicy="VOLATILE" securityDefiner="true">
      <functionParameter name="str" type="varchar(25)" direction="IN"/>
      <functionParameter name="len" type="int(11)" direction="OUT"/>
      <functionDefinition language="sql" sqlFormat="mysql5">BEGIN
  SELECT length(str)
    INTO len;
END</functionDefinition>
    </function>
  </schema>
XML;

    $schema = $this->extract($sql);
    $this->assertEquals($expected, $schema->asXML());
  }
}
