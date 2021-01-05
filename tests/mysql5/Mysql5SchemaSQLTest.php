<?php
/**
 * DBSteward unit test for mysql5 database generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group mysql5
 * @group nodb
 */
class Mysql5SchemaSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_all_names = true;
    mysql5::$swap_function_delimiters = TRUE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
  }

  public function testBuildWithoutPrefixes() {
    mysql5::$use_schema_name_prefix = FALSE;

    $xml = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
    </role>
  </database>
  <schema name="schema1" owner="ROLE_OWNER">
    <table name="table1" owner="ROLE_OWNER" primaryKey="col1">
      <column name="col1" type="int"/>
      <column name="col2" type="type1"/>
    </table>
    <type name="type1" type="enum">
      <enum name="A"/>
      <enum name="B"/>
    </type>
    <function name="function1" returns="text">
      <functionParameter name="a" type="text"/>
      <functionParameter name="b" type="int"/>
      <functionParameter name="c" type="date"/>
      <functionDefinition language="sql" sqlFormat="mysql5">
        RETURN 'xyz';
      </functionDefinition>
    </function>
  </schema>
</dbsteward>
XML;

    $expected = <<<'ENDSQL'
DROP FUNCTION IF EXISTS `function1`;
DELIMITER $_$
CREATE DEFINER = CURRENT_USER FUNCTION `function1` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz'$_$
DELIMITER ;

CREATE TABLE `table1` (
  `col1` int,
  `col2` ENUM('A','B')
);

ALTER TABLE `table1`
  ADD PRIMARY KEY (`col1`);

ENDSQL;
    
    $this->common($xml, $expected);
  }

  public function testBuildWithPrefixes() {
    mysql5::$use_schema_name_prefix = TRUE;

    $xml = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
    </role>
  </database>
  <schema name="schema1" owner="ROLE_OWNER">
    <table name="table1" owner="ROLE_OWNER" primaryKey="col1">
      <column name="col1" type="int"/>
      <column name="col2" type="type1"/>
    </table>
    <type name="type1" type="enum">
      <enum name="A"/>
      <enum name="B"/>
    </type>
    <function name="function1" returns="text">
      <functionParameter name="a" type="text"/>
      <functionParameter name="b" type="int"/>
      <functionParameter name="c" type="date"/>
      <functionDefinition language="sql" sqlFormat="mysql5">
        RETURN 'xyz';
      </functionDefinition>
    </function>
  </schema>
  <schema name="schema2" owner="ROLE_OWNER">
    <table name="table2" owner="ROLE_OWNER" primaryKey="col2">
      <column name="col2" type="int"/>
    </table>
    <sequence name="sequence1" owner="ROLE_OWNER" />
    <trigger name="trigger1" sqlFormat="mysql5" table="table2" when="before" event="insert" function="EXECUTE xyz"/>
    <view name="view" owner="ROLE_OWNER">
      <viewQuery sqlFormat="mysql5">SELECT * FROM table2</viewQuery>
    </view>
  </schema>
</dbsteward>
XML;

    $expected = <<<'ENDSQL'
DROP FUNCTION IF EXISTS `schema1_function1`;
DELIMITER $_$
CREATE DEFINER = CURRENT_USER FUNCTION `schema1_function1` (`a` text, `b` int, `c` date)
RETURNS text
LANGUAGE SQL
MODIFIES SQL DATA
NOT DETERMINISTIC
SQL SECURITY INVOKER
RETURN 'xyz'$_$
DELIMITER ;

CREATE TABLE `schema1_table1` (
  `col1` int,
  `col2` ENUM('A','B')
);

CREATE TABLE `schema2_table2` (
  `col2` int
);

CREATE TABLE IF NOT EXISTS `__sequences` (
  `name` VARCHAR(100) NOT NULL,
  `increment` INT(11) unsigned NOT NULL DEFAULT 1,
  `min_value` INT(11) unsigned NOT NULL DEFAULT 1,
  `max_value` BIGINT(20) unsigned NOT NULL DEFAULT 18446744073709551615,
  `cur_value` BIGINT(20) unsigned DEFAULT 1,
  `start_value` BIGINT(20) unsigned DEFAULT 1,
  `cycle` BOOLEAN NOT NULL DEFAULT FALSE,
  `should_advance` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`name`)
) ENGINE = MyISAM;

DELIMITER $_$
DROP FUNCTION IF EXISTS `currval`$_$
CREATE FUNCTION `currval` (`seq_name` varchar(100))
RETURNS BIGINT(20) NOT DETERMINISTIC
BEGIN
  DECLARE val BIGINT(20);
  IF @__sequences_lastval IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'nextval() has not been called yet this session';
  ELSE
    SELECT `currval` INTO val FROM  `__sequences_currvals` WHERE `name` = seq_name;
    RETURN val;
  END IF;
END$_$
DROP FUNCTION IF EXISTS `lastval`$_$
CREATE FUNCTION `lastval` ()
RETURNS BIGINT(20) NOT DETERMINISTIC
BEGIN
  IF @__sequences_lastval IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'nextval() has not been called yet this session';
  ELSE
    RETURN @__sequences_lastval;
  END IF;
END$_$
DROP FUNCTION IF EXISTS `nextval`$_$
CREATE FUNCTION `nextval` (`seq_name` varchar(100))
RETURNS BIGINT(20) NOT DETERMINISTIC
BEGIN
  DECLARE advance BOOLEAN;
  CREATE TEMPORARY TABLE IF NOT EXISTS `__sequences_currvals` (
    `name` VARCHAR(100) NOT NULL,
    `currval` BIGINT(20),
    PRIMARY KEY (`name`)
  );

  SELECT `cur_value` INTO @__sequences_lastval FROM `__sequences` WHERE `name` = seq_name;
  SELECT `should_advance` INTO advance FROM `__sequences` WHERE `name` = seq_name;
  
  IF @__sequences_lastval IS NOT NULL THEN
    IF advance = TRUE THEN
      UPDATE `__sequences`
      SET `cur_value` = IF (
        (`cur_value` + `increment`) > `max_value`,
        IF (`cycle` = TRUE, `min_value`, NULL),
        `cur_value` + `increment`
      )
      WHERE `name` = seq_name;

      SELECT `cur_value` INTO @__sequences_lastval FROM `__sequences` WHERE `name` = seq_name;
    ELSE
      UPDATE `__sequences`
      SET `should_advance` = TRUE
      WHERE `name` = seq_name;
    END IF;
    REPLACE INTO `__sequences_currvals` (`name`, `currval`)
    VALUE (seq_name, @__sequences_lastval);
  END IF;

  RETURN @__sequences_lastval;
END$_$
DROP FUNCTION IF EXISTS `setval`$_$
CREATE FUNCTION `setval` (`seq_name` varchar(100), `value` bigint(20), `advance` BOOLEAN)
RETURNS bigint(20) NOT DETERMINISTIC
BEGIN
  UPDATE `__sequences`
  SET `cur_value` = value,
      `should_advance` = advance
  WHERE `name` = seq_name;

  IF advance = FALSE THEN
    CREATE TEMPORARY TABLE IF NOT EXISTS `__sequences_currvals` (
      `name` VARCHAR(100) NOT NULL,
      `currval` BIGINT(20),
      PRIMARY KEY (`name`)
    );
    REPLACE INTO `__sequences_currvals` (`name`, `currval`)
    VALUE (seq_name, value);
    SET @__sequences_lastval = value;
  END IF;

  RETURN value;
END$_$
DELIMITER ;
INSERT INTO `__sequences`
  (`name`, `increment`, `min_value`, `max_value`, `cur_value`, `start_value`, `cycle`)
VALUES
  ('sequence1', DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT, DEFAULT);

DROP TRIGGER IF EXISTS `schema2_trigger1`;
CREATE TRIGGER `schema2_trigger1` BEFORE INSERT ON `schema2_table2`
FOR EACH ROW EXECUTE xyz;

ALTER TABLE `schema1_table1`
  ADD PRIMARY KEY (`col1`);

ALTER TABLE `schema2_table2`
  ADD PRIMARY KEY (`col2`);

CREATE OR REPLACE DEFINER = the_owner SQL SECURITY DEFINER VIEW `schema2_view`
  AS SELECT * FROM table2;
ENDSQL;
    
    $this->common($xml, $expected);
  }

  private function common($xml, $expected) {
    $dbs = new SimpleXMLElement($xml);
    $ofs = new mock_output_file_segmenter();

    dbsteward::$new_database = $dbs;
    $table_dependency = xml_parser::table_dependency_order($dbs);

    mysql5::build_schema($dbs, $ofs, $table_dependency);

    $actual = $ofs->_get_output();
// var_dump($actual);    
    // get rid of comments
    // $expected = preg_replace('/\s*-- .*(\n\s*)?/','',$expected);
    // // get rid of extra whitespace
    // $expected = trim(preg_replace("/\n\n/","\n",$expected));
    $expected = preg_replace("/^ +/m","",$expected);
    $expected = trim(preg_replace("/\n+/","\n",$expected));

    // echo $actual;

    // get rid of comments
    $actual = preg_replace("/\s*-- .*$/m",'',$actual);
    // get rid of extra whitespace
    // $actual = trim(preg_replace("/\n\n+/","\n",$actual));
    $actual = preg_replace("/^ +/m","",$actual);
    $actual = trim(preg_replace("/\n+/","\n",$actual));

    $this->assertEquals($expected, $actual);
  }
}
?>
