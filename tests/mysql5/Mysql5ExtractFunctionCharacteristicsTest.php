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
class Mysql5ExtractFunctionCharacteristicsTest extends Mysql5ExtractionTest { 

  /**
   * Tests that function determinism and eval type are correctly extracted
   * @dataProvider characteristicsProvider
   */
  public function testExtractFunctionCharacteristics($determinism, $evalType) {
    $sql = <<<SQL
DROP FUNCTION IF EXISTS `test_fn`;
CREATE DEFINER = CURRENT_USER FUNCTION `test_fn` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
$determinism
$evalType
RETURN 'xyz';
SQL;

    $schema = $this->extract($sql);
    
    $expectedCachePolicy = mysql5_function::get_cache_policy_from_characteristics($determinism, $evalType);

    $this->assertEquals($expectedCachePolicy, (string)$schema->function['cachePolicy']);
    $this->assertEquals(str_replace(' ', '_', $evalType), (string)$schema->function['mysqlEvalType']);
  }

  public function characteristicsProvider() {
    return array(
      array('DETERMINISTIC', 'CONTAINS SQL'),
      array('DETERMINISTIC', 'NO SQL'),
      array('DETERMINISTIC', 'READS SQL DATA'),
      array('DETERMINISTIC', 'MODIFIES SQL DATA'),

      array('NOT DETERMINISTIC', 'CONTAINS SQL'),
      array('NOT DETERMINISTIC', 'NO SQL'),
      array('NOT DETERMINISTIC', 'READS SQL DATA'),
      array('NOT DETERMINISTIC', 'MODIFIES SQL DATA'),
    );
  }
}
