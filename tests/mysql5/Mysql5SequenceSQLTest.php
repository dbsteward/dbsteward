<?php
/**
 * DBSteward unit test for mysql5 sequences sql generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/../../lib/DBSteward/sql_format/mysql5/mysql5_sequence.php';

class Mysql5SequenceSQLTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
  }

  public function testCreationDefaults() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="the_sequence" owner="NOBODY" />
</schema>
XML;
    
    $expected = $this->getExpectedSequenceDDL('the_sequence','DEFAULT','DEFAULT','DEFAULT','DEFAULT','DEFAULT');

    $schema = new SimpleXMLElement($xml);

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_creation_sql($schema, $schema->sequence)));

    $this->assertEquals($expected, $actual);
  }

  public function testCreationStandard() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="the_sequence" owner="NOBODY" max="10" cycle="true" inc="3" start="2"/>
</schema>
XML;

    $expected = $this->getExpectedSequenceDDL('the_sequence',3,'DEFAULT',10,2,'TRUE');

    $schema = new SimpleXMLElement($xml);

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_creation_sql($schema, $schema->sequence)));

    $this->assertEquals($expected, $actual);
  }

  public function testCreationExceptions() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="seq1" owner="NOBODY" start="abc" inc="abc" min="abc" max="abc" />
  <sequence name="seq2" owner="NOBODY" start="1" inc="abc" min="abc" max="abc" />
  <sequence name="seq3" owner="NOBODY" start="1" inc="1" min="abc" max="abc" />
  <sequence name="seq4" owner="NOBODY" start="1" inc="1" min="1" max="abc" />
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    try {
      mysql5_sequence::get_creation_sql($schema, $schema->sequence[0]);
    }
    catch ( Exception $ex ) {
      if ( stripos($ex->getMessage(), 'start value') !== FALSE ) {
        try {
          mysql5_sequence::get_creation_sql($schema, $schema->sequence[1]);
        }
        catch ( Exception $ex ) {
          if ( stripos($ex->getMessage(), 'increment by value') !== FALSE ) {
            try {
              mysql5_sequence::get_creation_sql($schema, $schema->sequence[2]);
            }
            catch ( Exception $ex ) {
              if ( stripos($ex->getMessage(), 'minimum value') !== FALSE ) {
                try {
                  mysql5_sequence::get_creation_sql($schema, $schema->sequence[3]);
                }
                catch ( Exception $ex ) {
                  if ( stripos($ex->getMessage(), 'maximum value') === FALSE ) {
                    $this->fail("Expected maximum value exception, got '" . $ex->getMessage() . "'");
                  }
                }
              }
              else {
                $this->fail("Expected minimum value exception, got '" . $ex->getMessage() . "'");
              }
            }
          }
          else {
            $this->fail("Expected increment value exception, got '" . $ex->getMessage() . "'");
          }
        }
      }
      else {
        $this->fail("Expected start value exception, got '" . $ex->getMessage() . "'");
      }
    }
  }

  public function testCreationTricky() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="the_sequence" owner="NOBODY" inc="0" min="-1" max="0" start="-1" cycle="x"/>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_creation_sql($schema, $schema->sequence)));

    $this->assertEquals($this->getExpectedSequenceDDL('the_sequence',0,'DEFAULT','DEFAULT','DEFAULT','TRUE'), $actual);
  }

  public function testDropSql() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="the_sequence" owner="NOBODY" inc="0" min="-1" max="0" start="-1" cycle="x"/>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);

    $expected = "DELETE FROM `__sequences` WHERE `name` = 'the_sequence';";

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_drop_sql($schema, $schema->sequence)));

    $this->assertEquals($expected, $actual);
  }

  public function testMultipleSequences() {
    $xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="seq0" max="10" cycle="true" inc="3" start="2"/>
  <sequence name="seq1" max="10" cycle="true" inc="3" start="2"/>
  <sequence name="seq2" max="10" cycle="true" inc="3" start="2"/>
</schema>
XML;

    $schema = new SimpleXMLElement($xml);

    $expected = '';

    for ( $i=0; $i<3; $i++ ) {
      $expected .= $this->getExpectedSequenceDDL("seq$i", 3, 'DEFAULT', 10, 2, 'TRUE');
    }

    $actual = '';
    foreach ( $schema->sequence as $seq ) {
      $actual .= trim(preg_replace('/--.*/','',mysql5_sequence::get_creation_sql($schema, $seq)));
    }

    $this->assertEquals($expected, $actual);
  }

  protected function getExpectedSequenceDDL($name, $inc, $min, $max, $start, $cycle) {
    return <<<SQL
INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `cycle`)
VALUE
  ('$name', $inc, $min, $max, $start, $cycle);
SQL;
  }

  protected function getExpectedSequenceShimDDL() {
    return <<<SQL
CREATE TABLE `__sequences` (
  `name` varchar(100) NOT NULL,
  `increment` int(11) unsigned NOT NULL DEFAULT 1,
  `min_value` int(11) unsigned NOT NULL DEFAULT 1,
  `max_value` bigint(20) unsigned NOT NULL DEFAULT 18446744073709551615,
  `cur_value` bigint(20) unsigned DEFAULT 1,
  `cycle` boolean NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`name`)
) ENGINE=MyISAM;

DELIMITER $$
CREATE FUNCTION `nextval` (`seq_name` varchar(100))
RETURNS bigint(20) NOT DETERMINISTIC
BEGIN
  DECLARE cur_val bigint(20);

  SELECT `cur_value` INTO cur_val FROM `__sequences` WHERE `name` = seq_name;
   
  IF cur_val IS NOT NULL THEN
    UPDATE `__sequences`
    SET `cur_value` = IF (
      (`cur_value` + `increment`) > `max_value`,
      IF (`cycle` = TRUE, `min_value`, NULL),
      `cur_value` + `increment`
    )
    WHERE `name` = seq_name;
  END IF;

  RETURN cur_val;
END$$
DELIMITER ;
SQL;
  }
}