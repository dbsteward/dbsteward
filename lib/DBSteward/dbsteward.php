<?php
/**
 * database sql generation from xml
 * with definition validation
 * full creation script generation
 * sql upgrade script generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

error_reporting(E_ALL);

require_once dirname(__FILE__) . '/dbx.php';
require_once dirname(__FILE__) . '/xml_parser.php';
require_once dirname(__FILE__) . '/sql_parser.php';
require_once dirname(__FILE__) . '/output_file_segmenter.php';

class dbsteward {

  const PATTERN_KNOWN_TYPES = "/^bigint.*|^bigserial|^bool.*|^bytea.*|^char.*|^date|^double precision|^inet$|^interval|^int.*|^oid|^smallint|^serial|^string|^text|^time$|^time with.*|^timestamp.*|^varchar.*|^uuid$/i";

  const PATTERN_SPLIT_OPERATION = "/\,\s*/";
  const PATTERN_SPLIT_ROLE = "/\,/";
  
  const TABLE_DEPENDENCY_IGNORABLE_NAME = 'DBSTEWARD_PLACEHOLDER_TABLE_UNMATCHABLE_TABLE_NAME_DO_NOT_PROCESS_DO_NOT_PANIC_KEEP_HEAD_DOWN';
  
  protected static $sql_format = 'pgsql8';

  /**
   * set SQL formatting mode
   *
   * @param string $format
   * @return void
   */
  public function set_sql_format($format) {
    if ( dbsteward::sql_format_exists($format) ) {
      dbsteward::$sql_format= $format;
    }
    else {
      throw new exception("Unknown SQL format mode: " . $format);
    }
  }

  /**
   * get SQL formatting mode
   *
   * @param string $format
   * @return void
   */
  public function get_sql_format() {
    return dbsteward::$sql_format;
  }

  public static $create_languages = FALSE;
  public static $require_slony_id = FALSE;
  public static $output_file_statement_limit = 900;
  public static $ignore_custom_roles = TRUE;
  // when true, custom roles not found will be turned in to database->role->owner
  public static $require_verbose_interval_notation = FALSE;
  public static $quote_schema_names = FALSE;
  public static $quote_object_names = FALSE;
  public static $quote_table_names = FALSE;
  public static $quote_function_names = FALSE;
  public static $quote_column_names = FALSE;
  public static $only_schema_sql = FALSE;
  public static $only_data_sql = FALSE;
  public static $limit_to_tables = array();
  public static $single_stage_upgrade = FALSE;
  /**
   * Should oldName attributes be validated and traced out, or ignored?
   */
  public static $ignore_oldname = FALSE;
  /**
   * Allow functions to be redefined?
   * Functions are unique by name AND parameter
   */
  public static $allow_function_redefinition = FALSE;

  /**
   * a knob to always recreate defined views no matter what
   * because underlying tables can change
   * and postgresql auto expand columns upon view creation
   * for views whose query definition is SELECT * FROM
   *
   * @var boolean
   * @access public
   */
  public static $always_recreate_views = TRUE;

  protected $cli_dbpassword = NULL;

  public static $old_database = NULL;
  public static $new_database = NULL;

  function __construct() {
  }

  public static function usage() {
    $s = "DBSteward Usage:
Global Switches and Flags
  --sqlformat=<pgsql8|mssql10|mysql5|oracle10g>
  --requireslonyid                  require tables and sequences to specify a valid slonyId
  --quoteschemanames                quote schema names in SQL output
  --quotetablenames                 quote table names in SQL output
  --quotecolumnnames                quote column names in SQL output
Generating SQL DDL / DML / DCL
  --xml=<database.xml> ...
  --pgdataxml=<pgdata.xml> ...      postgresql SELECT database_to_xml() result to overlay in composite definition
Generating SQL DDL / DML / DCL difference statements to upgrade an 'old' database to the 'new' XML definition
  --oldxml=<database.xml> ...
  --newxml=<newdatabase.xml> ...
  --onlyschemasql
  --onlydatasql
  --onlytable=<schema.table> ...
  --singlestageupgrade              combine upgrade stages into one file
  --maxstatementsperfile            how many DDL / DML / DCL statements per upgrade file segment
  --ignoreoldname                   ignore table oldName values when differencing
XML utilities
  --xmlsort=<database.xml> ...
  --xmlconvert=<database.xml> ...
  --xmldatainsert=<tabledata.xml>
";
    return $s;
  }
  
  public static function usage_extended() {
    $s = dbsteward::usage();
    $s .= "DBSteward Conversion and Comparison tools:
SQL diffing
  --oldsql=<old.sql> ...
  --newsql=<old.sql> ...
  --outputfile=<outputfile.ext>
Slony utils
  --slonikconvert=<slonyconfig.slonik>
  --slonycompare=<database.xml> ...     generate table SELECT statements for database health comparison between replicas
  --slonydiffold=<olddatabase.xml> ...  compare table slonyId assignment between two versions of a database definition
  --slonydiffnew=<newdatabase.xml> ...
Database definition extraction utilities
  --dbschemadump
  --dbdatadiff=<againstdatabase.xml> ...
    --dbhost=<hostname>
    --dbport=<TCP-port>
    --dbname=<database_name>
    --dbuser=<username>
    --dbpassword=<password>
";
    return $s;
  }

  public function arg_parse() {
    $short_opts = '';
    $long_opts = array(
      "sqlformat::",
      "xml::",
      "oldxml::",
      "newxml::",
      "pgdataxml::",
      "xmldatainsert::",
      "outputfile::",
      "dbschemadump::",
      "slonikconvert::",
      "slonycompare::",
      "slonydiffold::",
      "slonydiffnew::",
      "oldsql::",
      "newsql::",
      "dbhost::",
      "dbport::",
      "dbname::",
      "dbuser::",
      "dbpassword::",
      "requireslonyid::",
      "quoteschemanames::",
      "quotetablenames::",
      "quotecolumnnames::",
      "onlyschemasql::",
      "onlydatasql::",
      "onlytable::",
      "singlestageupgrade::",
      "maxstatementsperfile::",
      "ignoreoldname::",
      "dbdatadiff::",
      "xmlsort::",
      "xmlconvert::"
    );
    $options = getopt($short_opts, $long_opts);
    //var_dump($options); die('dieoptiondump');
    $files = array(
      'old' => array(),
      'new' => array(),
      'pgdata' => array()
    );

    
    ///// set the global SQL format
    if (isset($options["sqlformat"])) {
      dbsteward::set_sql_format($options["sqlformat"]);
    }


    ///// common parameter for output file for converter functions
    // for modes that can do it, omitting this parameter will cause output to be directed to stdout
    $output_file = FALSE;
    if (isset($options["outputfile"])
      && strlen($options["outputfile"]) > 0) {
      $output_file = $options["outputfile"];
    }
    
    if (isset($options["maxstatementsperfile"])) {
      if (!is_numeric($options["maxstatementsperfile"])) {
        throw new exception("maxstatementsperfile passed is not a number");
      }
      dbsteward::$output_file_statement_limit = $options["maxstatementsperfile"];
    }


    ///// XML parsing switches
    if (isset($options["singlestageupgrade"])) {
      dbsteward::$single_stage_upgrade = TRUE;
      // don't recreate views when in single stage upgrade mode
      // @TODO: make view diffing smart enough that this doesn't need to be done
      dbsteward::$always_recreate_views = FALSE;
    }
    
    if (isset($options["ignoreoldname"])) {
      dbsteward::$ignore_oldname = TRUE;
    }
    
    if (isset($options["requireslonyid"])) {
      dbsteward::$require_slony_id = TRUE;
    }


    ///// sql_format-specific default options
    if (strcasecmp(dbsteward::get_sql_format(), 'pgsql8') == 0) {
      dbsteward::$quote_schema_names = FALSE;
      dbsteward::$quote_table_names = FALSE;
      dbsteward::$quote_column_names = FALSE;
    }
    else if (strcasecmp(dbsteward::get_sql_format(), 'mssql10') == 0) {
      // needed for MSSQL keyword-named-columns like system_user
      dbsteward::$quote_table_names = TRUE;
      dbsteward::$quote_column_names = TRUE;
    }
    else if (strcasecmp(dbsteward::get_sql_format(), 'mysql5') == 0) {
      dbsteward::$quote_schema_names = TRUE;
      dbsteward::$quote_table_names = TRUE;
      dbsteward::$quote_column_names = TRUE;
    }
    else if (strcasecmp(dbsteward::get_sql_format(), 'oracle10g') == 0) {
      dbsteward::$quote_schema_names = TRUE;
      dbsteward::$quote_table_names = TRUE;
      dbsteward::$quote_column_names = TRUE;
    }
    
    // user-specified overrides for identifier quoting
    if (isset($options["quoteschemanames"])) {
      dbsteward::$quote_schema_names = TRUE;
    }
    if (isset($options["quotetablenames"])) {
      dbsteward::$quote_table_names = TRUE;
    }
    if (isset($options["quotecolumnnames"])) {
      dbsteward::$quote_column_names = TRUE;
    }
    
    if (strcasecmp(dbsteward::get_sql_format(), 'pgsql8') != 0) {
      if (isset($options['pgdataxml'])) {
        dbsteward::console_line(0, "pgdataxml parameter is not supported by " . dbsteward::get_sql_format() . " driver");
        exit(1);
      }
    }


    ///// SQL DDL DML DCL output flags
    if (isset($options["onlyschemasql"])) {
      dbsteward::$only_schema_sql = TRUE;
    }
    if (isset($options["onlydatasql"])) {
      dbsteward::$only_data_sql = TRUE;
    }
    if (isset($options['onlytable'])) {
      $onlytables = $options['onlytable'];
      if (!is_array($onlytables)) {
        $onlytables = array($onlytables);
      }
      foreach ($onlytables AS $onlytable) {
        $onlytable_schema = 'public';
        $onlytable_table = $onlytable;
        if (strpos($onlytable_table, '.') !== FALSE) {
          $chunks = explode('.', $onlytable_table);
          $onlytable_schema = $chunks[0];
          $onlytable_table = $chunks[1];
        }
        if (!isset(dbsteward::$limit_to_tables[$onlytable_schema])) {
          dbsteward::$limit_to_tables[$onlytable_schema] = array();
        }
        dbsteward::$limit_to_tables[$onlytable_schema][] = $onlytable_table;
      }
    }


    ///// database connectivity values
    $dbhost = FALSE;
    if (isset($options["dbhost"])
      && strlen($options["dbhost"]) > 0) {
      $dbhost = $options["dbhost"];
    }
    $dbport = '5432';
    if (isset($options["dbport"])
      && strlen($options["dbport"]) > 0) {
      $dbport = $options["dbport"];
    }
    $dbname = FALSE;
    if (isset($options["dbname"])
      && strlen($options["dbname"]) > 0) {
      $dbname = $options["dbname"];
    }
    $dbuser = FALSE;
    if (isset($options["dbuser"])
      && strlen($options["dbuser"]) > 0) {
      $dbuser = $options["dbuser"];
    }
    if (isset($options['dbpassword'])
      && strlen($options['dbpassword']) > 0) {
      $this->cli_dbpassword = $options['dbpassword'];
    }


    ///// XML utility functions
    if (isset($options['xmldatainsert'])) {
      if (!isset($options['xml'])) {
        throw new exception("xmldatainsert needs xml parameter defined");
      }
      dbsteward::xml_data_insert($options['xml'], $options['xmldatainsert']);
      exit(0);
    }

    if (isset($options["xmlsort"])) {
      dbsteward::xml_sort($options["xmlsort"]);
      exit(0);
    }
    
    if (isset($options["xmlconvert"])) {
      dbsteward::xml_convert($options["xmlconvert"]);
      exit(0);
    }

    
    ///// XML file parameter sanity checks
    if ( isset($options['xml']) ) {
      if (count($options['xml']) > 0 && isset($options['oldxml']) && count($options['oldxml']) > 0) {
        dbsteward::console_line(0, "Parameter error: xml and oldxml options are not to be mixed. Did you mean newxml?");
        exit(1);
      }
      if (count($options['xml']) > 0 && isset($options['newxml']) && count($options['newxml']) > 0) {
        dbsteward::console_line(0, "Parameter error: xml and newxml options are not to be mixed. Did you mean oldxml?");
        exit(1);
      }
    }
    if ((isset($options['oldxml']) && count($options['oldxml']) > 0) && (!isset($options['newxml']) || count($options['newxml']) == 0)) {
      dbsteward::console_line(0, "Parameter error: oldxml needs newxml specified for differencing to occur");
      exit(1);
    }
    if ((!isset($options['oldxml']) || count($options['oldxml']) == 0) && (isset($options['newxml']) && count($options['newxml']) > 0)) {
      dbsteward::console_line(0, "Parameter error: oldxml needs newxml specified for differencing to occur");
      exit(1);
    }
    
    
    ///// determine sql_format before getting into SQL functional modes
    $sql_format = dbsteward::get_sql_format();
    if ( ! class_alias($sql_format, 'sql_format_class') ) {
      throw new exception("Failed to alias $sql_format as sql_format_class");
    }



    ///// --[new|old]xml option(s) specificity - generate database DDL DML DCL
    if ( isset($options['xml']) && count($options['xml']) > 0 ) {
      if (isset($options['pgdataxml'])) {
        $pgdataxml = $options['pgdataxml'];
        sql_format_class::build($options['xml'], $pgdataxml);
      }
      else {
        sql_format_class::build($options['xml']);
      }
    }
    else if ( isset($options['newxml']) && count($options['newxml']) > 0 ) {
      if (isset($options['pgdataxml'])) {
        $pgdataxml = $options['pgdataxml'];
        sql_format_class::build_upgrade($options['oldxml'], $options['newxml'], $pgdataxml);
      }
      else {
        sql_format_class::build_upgrade($options['oldxml'], $options['newxml']);
      }
    }
    
    
    ///// special comparison and output modes

    // dump the schema from a running database
    if (isset($options["dbschemadump"])) {
      if (strlen($dbhost) === FALSE) {
        throw new exception("dbschemadump error: dbhost not specified");
      }
      else if (strlen($dbname) === FALSE) {
        throw new exception("dbschemadump error: dbname not specified");
      }
      else if (strlen($dbuser) === FALSE) {
        throw new exception("dbschemadump error: dbuser not specified");
      }
      else if ($output_file === FALSE) {
        throw new exception("dbschemadump error: outputfile not specified");
      }

      $output = sql_format_class::extract_schema($dbhost, $dbport, $dbname, $dbuser, $this->cli_dbpassword);
      
      dbsteward::console_line(1, "Saving extracted database schema to " . $output_file);
      if (!file_put_contents($output_file, $output)) {
        throw new exception("Failed to save extracted database schema to " . $output_file);
      }
      exit(0);
    }

    // difference a schema definition against a running database
    if (isset($options['dbdatadiff'])) {
      if (strlen($dbhost) === FALSE) {
        throw new exception("dbdatadiff error: dbhost not specified");
      }
      else if (strlen($dbname) === FALSE) {
        throw new exception("dbdatadiff error: dbname not specified");
      }
      else if (strlen($dbuser) === FALSE) {
        throw new exception("dbdatadiff error: dbuser not specified");
      }

      $output = sql_format_class::compare_db_data($dbhost, $dbport, $dbname, $dbuser, $this->cli_dbpassword, $options['dbdatadiff']);
      if (!file_put_contents($output_file, $output)) {
        throw new exception("Failed to save extracted database schema to " . $output_file);
      }
      exit(0);
    }


    // difference two SQL database dump files
    if (isset($options["oldsql"]) || isset($options["newsql"])) {
      if ($output_file === FALSE) {
        dbsteward::console_line(0, "sql diff error: you must specify an outputfile for this mode");
        exit(1);
      }
      sql_format_class::sql_diff($options["oldsql"], $options["newsql"], $output_file);
      exit(0);
    }

    // convert a slonik configuration file into DBSteward database definition XML
    if (isset($options["slonikconvert"])) {
      $output = slony1_slonik::convert($options["slonikconvert"]);
      if ($output_file !== FALSE) {
        dbsteward::console_line(1, "Saving slonikconvert output to " . $output_file);
        if (!file_put_contents($output, $output_file)) {
          throw new exception("Failed to save slonikconvert output to " . $output_file);
        }
      }
      else {
        echo $output;
      }
      exit(0);
    }

    // generate table SELECT statements for database health comparison between replicas
    if (isset($options["slonycompare"])) {
      pgsql8::slony_compare($options["slonycompare"]);
      exit(0);
    }

    // compare table slonyId assignment between two versions of a database definition
    if (isset($options["slonydiffold"])) {
      pgsql8::slony_diff($options["slonydiffold"], $options["slonydiffnew"]);
      exit(0);
    }
  }

  public static function cmd($command, $error_fatal = TRUE) {
    //dbsteward::console_line(3,  "dbsteward::cmd( " . $command . " )");
    $output = array();
    $return_value = 0;
    $last_line = exec($command, $output, $return_value);
    if ($return_value > 0) {
      if ($error_fatal) {
        dbsteward::console_line(1, "ERROR(" . $return_value . ") with command: " . $command);
        dbsteward::console_line(1, implode("\n", $output));
        throw new exception("ERROR(" . $return_value . ") with command: " . $command);
      }
    }
    return TRUE;
  }

  /**
   * cast whatever obj is to string
   * this is functionalized incase it needs to be improved further
   *
   * @param object $obj
   * @return string
   */
  public static function string_cast($obj) {
    return ((string)$obj);
  }

  public static function quote_column_name($column_name) {
    $format = self::$sql_format;
    return $format::get_quoted_column_name($column_name);
  }

  public function xml_sort($files) {
    if (!is_array($files)) {
      $files = array($files);
    }
    for ($i = 0; $i < count($files); $i++) {
      $file_name = $files[$i];
      $sorted_file_name = $file_name . '.xmlsorted';
      dbsteward::console_line(1, "Sorting XML definition file: " . $file_name);
      dbsteward::console_line(1, "Sorted XML output file: " . $sorted_file_name);
      xml_parser::file_sort($file_name, $sorted_file_name);
    }
  }
  
  public function xml_convert($files) {
    if (!is_array($files)) {
      $files = array($files);
    }
    for ($i = 0; $i < count($files); $i++) {
      $file_name = $files[$i];
      $converted_file_name = $file_name . '.xmlconverted';
      dbsteward::console_line(1, "Upconverting XML definition file: " . $file_name);
      dbsteward::console_line(1, "Upconvert XML output file: " . $converted_file_name);
      $doc = simplexml_load_file($file_name);
      xml_parser::sql_format_convert($doc);
      $converted_xml = xml_parser::format_xml($doc->asXML());
      $converted_xml = str_replace('pgdbxml>', 'dbsteward>', $converted_xml);
      file_put_contents($converted_file_name, $converted_xml);
    }
  }

  public static function supported_function_language($function) {
    $language = strtolower($function['language']);
    switch ($language) {
      case 'sql':
      case 'plpgsql':
        if (strcasecmp(dbsteward::get_sql_format(), 'pgsql8') == 0) {
          return TRUE;
        }
      break;
      case 'tsql':
        if (strcasecmp(dbsteward::get_sql_format(), 'mssql10') == 0) {
          return TRUE;
        }
      break;
      default:
        throw new exception("Unknown function language encountered: " . $language);
      break;
    }
    return FALSE;
  }

  public function xml_data_insert($def_file, $data_file) {
    dbsteward::console_line(1, "Automatic insert data into " . $def_file . " from " . $data_file);
    $def_doc = simplexml_load_file($def_file);
    if (!$def_doc) {
      throw new exception("Failed to load " . $def_file);
    }
    $data_doc = simplexml_load_file($data_file);
    if (!$data_doc) {
      throw new exception("Failed to load " . $data_file);
    }

    // for each of the tables defined, act on rows addColumns definitions
    foreach ($data_doc->schema AS $data_schema) {
      $xpath = "schema[@name='" . $data_schema['name'] . "']";
      $def_schema = $def_doc->xpath($xpath);
      if (count($def_schema) == 0) {
        throw new exception("definition " . $xpath . " not found");
      }
      if (count($def_schema) > 1) {
        throw new exception("more than one " . $xpath . " found");
      }
      $def_schema = $def_schema[0];
      foreach ($data_schema->table AS $data_table) {
        $xpath = "table[@name='" . $data_table['name'] . "']";
        $def_table = $def_schema->xpath($xpath);
        if (count($def_table) == 0) {
          throw new exception("definition " . $xpath . " not found");
        }
        if (count($def_table) > 1) {
          throw new exception("more than one " . $xpath . " found");
        }
        $def_table = $def_table[0];

        if (!isset($data_table->rows) || !isset($data_table->rows->row)) {
          throw new exception($xpath . " rows->row definition is incomplete");
        }
        if (count($data_table->rows->row) > 1) {
          throw new exception("Unexpected: more than one rows->row found in " . $xpath . " definition");
        }

        $definition_columns = preg_split("/[\,\s]+/", $def_table->rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
        $new_columns = preg_split("/[\,\s]+/", $data_table->rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
        for ($i = 0; $i < count($new_columns); $i++) {
          $new_column = $new_columns[$i];
          dbsteward::console_line(3, "Adding rows column " . $new_column . " to definition table " . $def_table['name']);
          if (in_array($new_column, $definition_columns)) {
            throw new exception("new column " . $new_column . " is already defined in dbsteward definition file");
          }
          $def_table->rows['columns'] = $def_table->rows['columns'] . ", " . $new_column;

          $col_value = $data_table->rows->row->col[$i];

          // add the value to each row col set in def_table->rows
          foreach ($def_table->rows->row AS $row) {
            $row->addChild('col', $col_value);
          }
        }
      }
    }

    $def_file_modified = $def_file . '.xmldatainserted';
    dbsteward::console_line(1, "Saving modified dbsteward definition as " . $def_file_modified);
    return xml_parser::save_xml($def_file_modified, $def_doc->saveXML());
  }
  
  public static function console_line($level, $text) {
    echo "[DBSteward-" . $level . "] " . $text . "\n";
  }
  
  public static function console_text($level, $text) {
    echo $text;
  }
  
  public static function get_sql_formats() {
    $sql_format_dir = dirname(__FILE__) . "/sql_format/";
    if ( ! ($dh = opendir($sql_format_dir)) ) {
      throw new exception("Failed to open sql_format directory " . $sql_format_dir);
    }
    $format_list = array();
    while( ($file = readdir($dh)) !== false ) {
      if ( strlen($file) > 0 && $file{0} != '.' ) {
        if ( is_dir($sql_format_dir . "/" . $file) ) {
          $format_list[] = $file;
        }
      }
    }
    closedir($dh);
    return $format_list;
  }
  
  public static function sql_format_exists($format) {
    return in_array($format, dbsteward::get_sql_formats());
  }
  
  public static function load_sql_formats() {
    $sql_format_dir = dirname(__FILE__) . "/sql_format/";
    $formats = dbsteward::get_sql_formats();
    foreach($formats AS $format) {
      require_once($sql_format_dir . "/" . $format . "/" . $format . ".php");
    }
  }

}

?>
