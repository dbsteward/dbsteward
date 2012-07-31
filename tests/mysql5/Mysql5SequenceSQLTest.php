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

    $expected = "DELETE FROM `__sequences` WHERE `name` IN ('the_sequence');";

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

    $expected = <<<SQL
INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `cycle`)
VALUES
  ('seq0', 3, DEFAULT, 10, 2, TRUE),
  ('seq1', 3, DEFAULT, 10, 2, TRUE),
  ('seq2', 3, DEFAULT, 10, 2, TRUE);
SQL;

    $seqs = array();
    foreach ( $schema->sequence as $seq ) {
      $seqs[] = $seq;
    }

    $actual = trim(preg_replace('/--.*/','',mysql5_sequence::get_creation_sql($schema, $seqs)));

    $this->assertEquals($expected, $actual);

    $expected_drop = "DELETE FROM `__sequences` WHERE `name` IN ('seq0', 'seq1', 'seq2');";
    $actual_drop = trim(preg_replace('/--.*/','',mysql5_sequence::get_drop_sql($schema, $seqs)));

    $this->assertEquals($expected_drop, $actual_drop);
  }

  protected function getExpectedSequenceDDL($name, $inc, $min, $max, $start, $cycle) {
    return <<<SQL
INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `cycle`)
VALUES
  ('$name', $inc, $min, $max, $start, $cycle);
SQL;
  }
}