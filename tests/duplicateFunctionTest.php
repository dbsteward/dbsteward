<?php
/**
 * DBSteward duplicate function compositing test
 *
 * Items tested:
 *  function compositing
 *  function compositing function overwriting knob that controls wether it is allowed
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once __DIR__ . '/../lib/DBSteward/dbsteward.php';
require_once __DIR__ . '/mock_output_file_segmenter.php';

class duplicateFunctionTest extends PHPUnit_Framework_TestCase {

  private $pgsql8_xml_a = <<<XML
<dbsteward>
  <database>
    <host>db-host</host>
    <name>dbsteward</name>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
    <slony>
      <masterNode id="1"/>
      <replicaNode id="2" providerId="1"/>
      <replicaNode id="3" providerId="2"/>
      <replicationSet id="1"/>
      <replicationUpgradeSet id="2"/>
    </slony>
  </database>
  <schema name="someschema" owner="ROLE_OWNER">
    <table name="othertable" owner="ROLE_OWNER" primaryKey="othertable_id" description="othertable for other data" slonyId="50">
      <column name="othertable_id" type="int"/>
      <column name="othertable_name" type="varchar(100)" null="false"/>
      <column name="othertable_detail" type="text" null="false"/>
      <grant role="ROLE_APPLICATION" operation="SELECT"/>
    </table>
    <function name="lpad" returns="text" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for PGSQL">
      <functionParameter type="text"/>
      <functionParameter type="int"/>
      <functionParameter type="text"/>
      <functionDefinition language="sql" sqlFormat="pgsql8">
        SELECT LPAD($1, $2, $3);
      </functionDefinition>
      <grant role="PUBLIC" operation="EXECUTE"/>
    </function>
    <function name="lpad" returns="text" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for PGSQL">
      <functionParameter type="text"/>
      <functionParameter type="int"/>
      <functionParameter type="text"/>
      <functionDefinition language="sql" sqlFormat="pgsql8">
        SELECT LPAD($1, $2, $3);
      </functionDefinition>
      <grant role="PUBLIC" operation="EXECUTE"/>
    </function>
    <function name="lpad" returns="varchar(MAX)" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for MSSQL">
      <functionParameter name="base_str" type="varchar(MAX)"/>
      <functionParameter name="str_len" type="int"/>
      <functionParameter name="pad_str" type="varchar(MAX)"/>
      <functionDefinition language="tsql" sqlFormat="mssql10">
        BEGIN
          DECLARE @base_str_len int,
                  @pad_len int,
                  @padded_str VARCHAR(MAX)
          IF @str_len &lt; 1
          BEGIN
            RETURN ''
          END
          IF len(@pad_str) = 0 AND datalength(@pad_str) = 0
          BEGIN
            RETURN substring(@base_str, 1, @str_len)
          END
          SET @base_str_len = LEN(@base_str)
          SET @pad_len = ((@str_len-@base_str_len) / len(@pad_str)) + 1
          RETURN right(REPLICATE(@pad_str, @pad_len) + @base_str, @str_len)
        END
      </functionDefinition>
      <grant role="ROLE_APPLICATION" operation="EXECUTE"/>
      <grant role="PUBLIC" operation="EXECUTE"/>
    </function>
    <function name="lpad" returns="varchar(MAX)"  owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for MSSQL">
      <functionParameter name="base_str" type="varchar(MAX)"/>
      <functionParameter name="str_len" type="int"/>
      <functionParameter name="pad_str" type="varchar(MAX)"/>
      <functionDefinition language="tsql" sqlFormat="mssql10">
        BEGIN
          DECLARE @base_str_len int,
                  @pad_len int,
                  @padded_str VARCHAR(MAX)
          IF @str_len &lt; 1
          BEGIN
            RETURN ''
          END
          IF len(@pad_str) = 0 AND datalength(@pad_str) = 0
          BEGIN
            RETURN substring(@base_str, 1, @str_len)
          END
          SET @base_str_len = LEN(@base_str)
          SET @pad_len = ((@str_len-@base_str_len) / len(@pad_str)) + 1
          RETURN right(REPLICATE(@pad_str, @pad_len) + @base_str, @str_len)
        END
      </functionDefinition>
      <grant role="ROLE_APPLICATION" operation="EXECUTE"/>
      <grant role="PUBLIC" operation="EXECUTE"/>
    </function>
  </schema>
</dbsteward>
XML;

  private $pgsql8_xml_b = <<<XML
<dbsteward>
  <database>
    <host>db-host</host>
    <name>dbsteward</name>
    <role>
      <application>dbsteward_phpunit_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
    <slony>
      <masterNode id="1"/>
      <replicaNode id="2" providerId="1"/>
      <replicaNode id="3" providerId="2"/>
      <replicationSet id="1"/>
      <replicationUpgradeSet id="2"/>
    </slony>
  </database>
  <schema name="someschema" owner="ROLE_OWNER">
    <table name="othertable" owner="ROLE_OWNER" primaryKey="othertable_id" description="othertable for other data" slonyId="50">
      <column name="othertable_id" type="int"/>
      <column name="othertable_name" type="varchar(100)" null="false"/>
      <column name="othertable_detail" type="text" null="false"/>
      <grant role="ROLE_APPLICATION" operation="SELECT"/>
    </table>
    <function name="lpad" returns="text" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for PGSQL">
      <functionParameter type="text"/>
      <functionParameter type="int"/>
      <functionParameter type="text"/>
      <functionDefinition language="sql" sqlFormat="pgsql8">
        SELECT LPAD($1, $2, $3);
      </functionDefinition>
      <grant role="PUBLIC" operation="EXECUTE"/>
    </function>
    <function name="lpad" returns="text" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for PGSQL -- whitespace altered to make function diff upgrade happen, only in section definition">
      <functionParameter type="text"/>
      <functionParameter type="int"/>
      <functionParameter type="text"/>
      <functionDefinition language="sql" sqlFormat="pgsql8">
        SELECT LPAD(
          $1,
          $2,
          $3
        );
      </functionDefinition>
      <grant role="PUBLIC" operation="EXECUTE"/>
    </function>
    <function name="lpad" returns="varchar(MAX)" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for MSSQL">
      <functionParameter name="base_str" type="varchar(MAX)"/>
      <functionParameter name="str_len" type="int"/>
      <functionParameter name="pad_str" type="varchar(MAX)"/>
      <functionDefinition language="tsql" sqlFormat="mssql10">
        BEGIN
          DECLARE @base_str_len int,
                  @pad_len int,
                  @padded_str VARCHAR(MAX)
          IF @str_len &lt; 1
          BEGIN
            RETURN ''
          END
          IF len(@pad_str) = 0 AND datalength(@pad_str) = 0
          BEGIN
            RETURN substring(
              @base_str,
              1,
              @str_len
            )
          END
          SET @base_str_len = LEN(@base_str)
          SET @pad_len = ((@str_len-@base_str_len) / len(@pad_str)) + 1
          RETURN right(REPLICATE(@pad_str, @pad_len) + @base_str, @str_len)
        END
      </functionDefinition>
      <grant role="ROLE_APPLICATION" operation="EXECUTE"/>
      <grant role="PUBLIC" operation="EXECUTE"/>
    </function>
    <function name="lpad" returns="varchar(MAX)" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for MSSQL -- whitespace altered to make function diff upgrade happen, only in section definition">
      <functionParameter name="base_str" type="varchar(MAX)"/>
      <functionParameter name="str_len" type="int"/>
      <functionParameter name="pad_str" type="varchar(MAX)"/>
      <functionDefinition language="tsql" sqlFormat="mssql10">
        BEGIN
          DECLARE @base_str_len int,
                  @pad_len int,
                  @padded_str VARCHAR(MAX)
          IF @str_len &lt; 1
          BEGIN
            RETURN ''
          END
          IF len(@pad_str) = 0 AND datalength(@pad_str) = 0
          BEGIN
            RETURN substring(@base_str, 1, @str_len)
          END
          SET @base_str_len = LEN(@base_str)
          SET @pad_len = ((@str_len-@base_str_len) / len(@pad_str)) + 1
          RETURN right(REPLICATE(@pad_str, @pad_len) + @base_str, @str_len)
        END
      </functionDefinition>
      <grant role="ROLE_APPLICATION" operation="EXECUTE"/>
      <grant role="PUBLIC" operation="EXECUTE"/>
    </function>
  </schema>
</dbsteward>
XML;

  private $mssql10_xml_a; // = $pgsql8_xml_a
  private $mssql10_xml_b; // = $pgsql8_xml_b

  private $mysql5_xml_a = <<<XML
<dbsteward>
  <database>
    <host>db-host</host>
    <name>dbsteward</name>
    <role>
      <application>dbsteward_pu_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
    <slony>
      <masterNode id="1"/>
      <replicaNode id="2" providerId="1"/>
      <replicaNode id="3" providerId="2"/>
      <replicationSet id="1"/>
      <replicationUpgradeSet id="2"/>
    </slony>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="othertable" owner="ROLE_OWNER" primaryKey="othertable_id" description="othertable for other data" slonyId="50">
      <column name="othertable_id" type="int"/>
      <column name="othertable_name" type="varchar(100)" null="false"/>
      <column name="othertable_detail" type="text" null="false"/>
      <grant role="ROLE_APPLICATION" operation="SELECT"/>
    </table>
    <function name="lpad" returns="text" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for MySQL">
      <functionParameter name="one" type="text"/>
      <functionParameter name="two" type="int"/>
      <functionParameter name="three" type="text"/>
      <functionDefinition language="sql" sqlFormat="mysql5">
        BEGIN
          DECLARE val text;
          SELECT LPAD($1, $2, $3) INTO val;
          RETURN val;
        END;
      </functionDefinition>
      <grant role="ROLE_APPLICATION" operation="EXECUTE"/>
    </function>
    <function name="lpad" returns="text" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for MySQL">
      <functionParameter name="one" type="text"/>
      <functionParameter name="two" type="int"/>
      <functionParameter name="three" type="text"/>
      <functionDefinition language="sql" sqlFormat="mysql5">
        BEGIN
          DECLARE val text;
          SELECT LPAD($1, $2, $3) INTO val;
          RETURN val;
        END;
      </functionDefinition>
      <grant role="ROLE_APPLICATION" operation="EXECUTE"/>
    </function>
  </schema>
</dbsteward>
XML;

  private $mysql5_xml_b = <<<XML
<dbsteward>
  <database>
    <host>db-host</host>
    <name>dbsteward</name>
    <role>
      <application>dbsteward_pu_app</application>
      <owner>deployment</owner>
      <replication/>
      <readonly/>
    </role>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="othertable" owner="ROLE_OWNER" primaryKey="othertable_id" description="othertable for other data" slonyId="50">
      <column name="othertable_id" type="int"/>
      <column name="othertable_name" type="varchar(100)" null="false"/>
      <column name="othertable_detail" type="text" null="false"/>
      <grant role="ROLE_APPLICATION" operation="SELECT"/>
    </table>
    <function name="lpad" returns="text" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for MySQL">
      <functionParameter name="one" type="text"/>
      <functionParameter name="two" type="int"/>
      <functionParameter name="three" type="text"/>
      <functionDefinition language="sql" sqlFormat="mysql5">
        BEGIN
          DECLARE val text;
          SELECT LPAD($1, $2, $3) INTO val;
          RETURN val;
        END;
      </functionDefinition>
      <grant role="ROLE_APPLICATION" operation="EXECUTE"/>
    </function>
    <function name="lpad" returns="text" owner="ROLE_OWNER" cachePolicy="IMMUTABLE" description="lpad unified implementation for MySQL -- whitespace altered to make function diff upgrade happen, only in section definition">
      <functionParameter name="one" type="text"/>
      <functionParameter name="two" type="int"/>
      <functionParameter name="three" type="text"/>
      <functionDefinition language="sql" sqlFormat="mysql5">
        BEGIN
          DECLARE val text;
          SELECT LPAD(
            $1,
            $2,
            $3
          ) INTO val;
          RETURN val;
        END;
      </functionDefinition>
      <grant role="ROLE_APPLICATION" operation="EXECUTE"/>
    </function>
  </schema>
</dbsteward>
XML;

  protected function setUp() {
    $this->mssql10_xml_a = $this->pgsql8_xml_a;
    $this->mssql10_xml_b = $this->pgsql8_xml_b;
  }
  
  /**
   * @group pgsql8
   */
  public function testDuplicationFunctionDefinitionAllowedPGSQL8() {
    $this->do_allowed('pgsql8');
  }
  
  /**
   * @group mssql10
   */
  public function testDuplicationFunctionDefinitionAllowedMSSQL10() {
    $this->do_allowed('mssql10');
  }

  /**
   * @group mysql5
   */
  public function testDuplicationFunctionDefinitionAllowedMYSQL5() {
    $this->do_allowed('mysql5');
  }
  
  /**
   * @group pgsql8
   */
  public function testDuplicationFunctionDefinitionNotAllowedPGSQL8() {
    $this->do_not_allowed('pgsql8');
  }
  
  /**
   * @group mssql10
   */
  public function testDuplicationFunctionDefinitionNotAllowedMSSQL10() {
    $this->do_not_allowed('mssql10');
  }
  
  /**
   * @group mysql5
   */
  public function testDuplicationFunctionDefinitionNotAllowedMYSQL5() {
    $this->do_not_allowed('mysql5');
  }

  protected function build_db($format) {
    dbsteward::set_sql_format($format);
    $ofs = new mock_output_file_segmenter();

    $doc_a = new SimpleXMLElement($this->{$format.'_xml_a'});
    dbsteward::$new_database = $doc_a;
    $table_dependency_a = xml_parser::table_dependency_order($doc_a);

    $format::build_schema($doc_a, $ofs, $table_dependency_a);
  }

  protected function upgrade_db($format) {
    dbsteward::set_sql_format($format);
    $ofs = new mock_output_file_segmenter();

    $doc_a = new SimpleXMLElement($this->{$format.'_xml_a'});
    $doc_b = new SimpleXMLElement($this->{$format.'_xml_b'});

    dbsteward::$old_database = $doc_a;
    dbsteward::$new_database = $doc_b;

    mysql5_diff::diff_doc_work($ofs, $ofs, $ofs, $ofs);
  }

  private function do_allowed($format) {
    dbsteward::$allow_function_redefinition = true;

    $this->build_db($format);
    $this->upgrade_db($format);
  }

  private function do_not_allowed($format) {
    dbsteward::$allow_function_redefinition = false;

    try {
      $this->build_db($format);
    }
    catch(Exception $e) {
      $this->assertEquals(
        'function lpad with identical parameters is being redefined',
        $e->getMessage()
      );
    }
    
    // make sure differencing doesn't want to do it either
    try {
      $this->upgrade_db($format);
    }
    catch(Exception $e) {
      $this->assertEquals(
        'function lpad with identical parameters is being redefined',
        $e->getMessage()
      );
    }
  }
}

?>
