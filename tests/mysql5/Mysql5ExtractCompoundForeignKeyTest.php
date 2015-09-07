<?php
/**
 * DBSteward unit test for mysql5 foreign key extraction
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 */

require_once __DIR__ . '/Mysql5ExtractionTest.php';

/**
 * @group mysql5
 */
class Mysql5ExtractCompoundForeignKeyTest extends Mysql5ExtractionTest { 

  public function testCompoundFKeyExtraction() {
    $sql = <<<SQL
CREATE TABLE t1 (c1 int, c2 int);
CREATE TABLE t2 (c1 int, c2 int);
CREATE INDEX t2_fkey_idx ON t2 (c1, c2);
ALTER TABLE t1 ADD FOREIGN KEY (c1, c2) REFERENCES t2 (c1, c2);
SQL;

  $expected = <<<XML
<foreignKey columns="c1, c2" foreignSchema="Mysql5ExtractionTest" foreignTable="t2" foreignColumns="c1, c2" constraintName="t1_ibfk_1"/>
XML;

    $schema = $this->extract($sql);
    $this->assertEquals(simplexml_load_string($expected), $schema->table[0]->foreignKey);
  }
}
