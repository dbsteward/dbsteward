<?php
/**
 * DBSteward unit test for mysql5 sequences behavior
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';

require_once __DIR__ . '/../../lib/DBSteward/dbsteward.php';

/**
 * @group mysql5
 */
class Mysql5SequenceShimTest extends PHPUnit_Framework_TestCase {
  private $pdo;
  private $config;

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    mysql5::$swap_function_delimiters = FALSE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;

    $this->config = $GLOBALS['db_config']->mysql5_config;

    $this->connect();
    $this->setup_shim();
  }

  public function tearDown() {
    $this->disconnect();
  }

  public function testNextVal() {
    $this->createOne('a',1,8,true,2,3);
    $this->nextval('a', 5);
    $this->nextval('a', 7);
    $this->nextval('a', 1);
    $this->nextval('a', 3);

    $this->createOne('b', 1, 3, false, 2);
    $this->nextval('b', 3);
    $this->nextval('b', null);
    $this->nextval('b', null);

    $this->createOne('c', 1, 3, true, 2);
    $this->nextval('c', 3);
    $this->nextval('c', 1);
    $this->nextval('c', 3);
  }

  public function testCurVal() {
    $this->createOne('testseq', 1, 5);
    $this->currval('testseq', null);
    $this->nextval('testseq', 2);
    $this->currval('testseq', 2);

    $this->disconnect();
    $this->connect();

    $this->currval('testseq', null);
    $this->nextval('testseq', 3);
    $this->currval('testseq', 3);
  }

  public function testLastVal() {
    $this->createOne('seq_a', 1, 5, true, 3);
    $this->lastval(null);

    $this->createOne('seq_b', 10, 15);
    $this->lastval(null);

    $this->nextval('seq_a', 4);
    $this->lastval(4);

    $this->nextval('seq_b', 11);
    $this->lastval(11);

    $this->disconnect();
    $this->connect();

    $this->lastval(null);
  }

  public function testSetVal() {
    $this->createOne('testseq', 1, 5);

    // setval('testseq', 2, false) should set the sequences current value to 2,
    // and the next time nextval is called, it should be 2
    $this->setval('testseq', 2, false);

    // ensure setval set currval and lastval correctly
    $this->currval('testseq', 2);
    $this->lastval(2);

    // nextval should return 2 because it was set to NOT advance
    $this->nextval('testseq', 2);

    // setval('testseq', 2, true) should set the sequences current value to 2,
    // and the next time nextval is called, it should advance and return 3
    $this->setval('testseq', 2, true);

    // ensure setval set currval and lastval correctly
    $this->currval('testseq', 2);
    $this->lastval(2);

    // nextval should return 3 because it was set to advance
    $this->nextval('testseq', 3);
  }

  private function createOne($name, $min, $max, $cycle=TRUE, $inc=1, $start=FALSE) {
    $cyc_n = $cycle ? '1' : '0';
    $cycle = $cycle ? 'true':'false';
    $start = $start===FALSE ? $min : $start;
$xml = <<<XML
<schema name="test" owner="NOBODY">
  <sequence name="$name" owner="NOBODY" min="$min" max="$max" cycle="$cycle" inc="$inc" start="$start"/>
</schema>
XML;
    $schema = new SimpleXMLElement($xml);

    $this->assertEquals(1, $this->pdo->exec(mysql5_sequence::get_creation_sql($schema, $schema->sequence)));
    $stmt = $this->pdo->prepare("SELECT * FROM __sequences WHERE name = ?");
    $this->assertTrue($stmt->execute(array($name)));
    $this->assertEquals(
      array(
        'name'=>$name,
        'increment'=>"$inc",
        'min_value'=>"$min",
        'max_value'=>"$max",
        'cur_value'=>"$start",
        'start_value'=>"$start",
        'cycle'=>$cyc_n,
        'should_advance'=>true
      ),
      $stmt->fetch(PDO::FETCH_ASSOC)
    );
  }

  /**
   * From the PostgreSQL 8.4 manual: 
   * Advance the sequence object to its next value and return that value.
   * This is done atomically: even if multiple sessions execute nextval concurrently,
   * each will safely receive a distinct sequence value.
   */
  private function nextval($seq, $expected) {
    $stmt = $this->pdo->prepare("SELECT nextval(?)");
    $this->assertTrue($stmt->execute(array($seq)));
    $this->assertEquals($expected, $stmt->fetchColumn());
  }

  /**
   * From the PostgreSQL 8.4 manual:
   * Return the value most recently obtained by nextval for this sequence in the current session.
   * (An error is reported if nextval has never been called for this sequence in this session.)
   * Because this is returning a session-local value, it gives a predictable answer whether
   * or not other sessions have executed nextval since the current session did.
   */
  private function currval($seq, $expected) {
    $stmt = $this->pdo->prepare("SELECT currval(?)");
    if ($expected !== null) {
      $this->assertTrue($stmt->execute(array($seq)));
      $this->assertEquals($expected, $stmt->fetchColumn());
      return;
    }

    try {
      $stmt->execute(array($seq));
    }
    catch (PDOException $ex) {
      $this->assertEquals('SQLSTATE[45000]: <<Unknown error>>: 1644 nextval() has not been called yet this session', $ex->getMessage());
      return;
    }
    $this->fail("Expected SQLSTATE 45000 because nextval() has not yet been called");
  }

  /**
   * From the PostgreSQL 8.4 manual:
   * Return the value most recently returned by nextval in the current session. This function is
   * identical to currval, except that instead of taking the sequence name as an argument it fetches
   * the value of the last sequence used by nextval in the current session.
   * It is an error to call lastval if nextval has not yet been called in the current session.
   */
  private function lastval($expected) {
    $stmt = $this->pdo->prepare("SELECT lastval()");
    if ($expected !== null) {
      $this->assertTrue($stmt->execute(array()));
      $this->assertEquals($expected, $stmt->fetchColumn());
      return;
    }

    try {
      $stmt->execute();
    }
    catch (PDOException $ex) {
      $this->assertEquals('SQLSTATE[45000]: <<Unknown error>>: 1644 nextval() has not been called yet this session', $ex->getMessage());
      return;
    }
    $this->fail("Expected SQLSTATE 45000 because nextval() has not yet been called");
  }

  /**
   * From the PostgreSQL 8.4 manual:
   * Reset the sequence object's counter value. The two-parameter form sets the sequence's
   * last_value field to the specified value and sets its is_called field to true, meaning that the
   * next nextval will advance the sequence before returning a value. The value reported by currval
   * is also set to the specified value. In the three-parameter form, is_called can be set to either
   * true or false. true has the same effect as the two-parameter form. If it is set to false, the
   * next nextval will return exactly the specified value, and sequence advancement commences with
   * the following nextval. Furthermore, the value reported by currval is not changed in this case
   * (this is a change from pre-8.3 behavior).
   * The result returned by setval is just the value of its second argument.
   * 
   * We only allow the 3-parameter form, because MySQL doesn't support optional or default parameters
   */
  private function setval($seq, $value, $advance=TRUE) {
    $stmt = $this->pdo->prepare("SELECT setval(?, ?, ?)");
    $this->assertTrue($stmt->execute(array($seq, $value, $advance)));
    $this->assertEquals($value, $stmt->fetchColumn());
  }

  private function connect() {
    $dsn = "mysql:host=".$this->config['dbhost'].";port=".$this->config['dbport'].";dbname=mysql";
    // echo "Connecting to $dsn\n";

    $this->pdo = new PDO($dsn, $this->config['dbuser'], $this->config['dbpass']);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $this->config['dbname']);
    $this->pdo->exec('USE ' . $this->config['dbname']);
  }
  private function disconnect() {
    $this->pdo = NULL;
  }
  private function setup_shim() {
    $this->pdo->exec($sql=mysql5_sequence::get_shim_drop_sql());
    // echo $sql . "\n\n";
    $this->pdo->exec($sql=mysql5_sequence::get_shim_creation_sql());
    // echo $sql . "\n\n";
  }
}
?>
