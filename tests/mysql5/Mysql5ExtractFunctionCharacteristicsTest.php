<?php
/**
 * DBSteward unit test for mysql5 function sql generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 */

require_once __DIR__ . '/Mysql5ExtractionTestCase.php';

#[\PHPUnit\Framework\Attributes\Group('mysql5')]
class Mysql5ExtractFunctionCharacteristicsTest extends Mysql5ExtractionTestCase { 

  /**
   * Tests that function determinism and eval type are correctly extracted
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('characteristicsProvider')]
  #[\PHPUnit\Framework\Attributes\Group('quarantine')]
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

  public static function characteristicsProvider() {
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
