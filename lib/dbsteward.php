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
  public static $ignore_custom_roles = TRUE;
  // when true, custom roles not found will be turned in to database->role->owner
  public static $require_verbose_interval_notation = FALSE;
  public static $quote_schema_names = FALSE;
  public static $quote_object_names = FALSE;
  public static $quote_table_names = FALSE;
  public static $quote_function_names = FALSE;
  public static $quote_column_names = TRUE;
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
    --sqlformat=<pgsql8|mssql10|mysql4|oracle10g>
XML definition diffing
    --xml=<database.xml> ...
    --newxml=<newdatabase.xml> ...
    --pgdataxml=<pgdata.xml> ...
    --onlyschemasql
    --onlydatasql
    --onlytable=<schema.table> ...
    --singlestageupgrade
    --ignoreoldname
XML utils
    --xmlsort=<database.xml> ...
    --xmldatainsert=<tabledata.xml>
SQL diffing
    --oldsql=<old.sql> ...
    --newsql=<old.sql> ...
    --outputfile=<outputfile.ext>
Slony utils
    --slonikconvert=<slonyconfig.slonik>
    --slonycompare=<database.xml> ...
    --slonydiff=<database.xml> ...  --slonydiffnew=<database.xml> ...
Postgresql slurp utils
    --pgschema
    --pgdatadiff=<againstdatabase.xml> ...
    --dbhost=<hostname>
    --dbname=<database_name>
    --dbuser=<username>
    --dbpassword=<password>
    --require-slony-id\t require table and sequence definitions to specify a valid slonyId
";
    return $s;
  }

  public function arg_parse() {
    $short_opts = '';
    $long_opts = array(
      "sqlformat::",
      "xml::",
      "newxml::",
      "pgdataxml::",
      "xmldatainsert::",
      "outputfile::",
      "pgschema::",
      "slonikconvert::",
      "slonycompare::",
      "slonydiff::",
      "slonydiffnew::",
      "oldsql::",
      "newsql::",
      "dbhost::",
      "dbname::",
      "dbuser::",
      "dbpassword::",
      "require-slony-id::",
      "onlyschemasql::",
      "onlydatasql::",
      "onlytable::",
      "singlestageupgrade::",
      "ignoreoldname::",
      "pgdatadiff::",
      "xmlsort::"
    );
    $options = getopt($short_opts, $long_opts);
    //var_dump($options); die('dieoptiondump');
    $files = array(
      'old' => array(),
      'new' => array(),
      'pgdata' => array()
    );

    // option knobs
    if (isset($options["require-slony-id"])) {
      dbsteward::$require_slony_id = TRUE;
    }
    if (isset($options["onlyschemasql"])) {
      dbsteward::$only_schema_sql = TRUE;
    }
    if (isset($options["onlydatasql"])) {
      dbsteward::$only_data_sql = TRUE;
    }
    if (isset($options["sqlformat"])) {
      dbsteward::set_sql_format($options["sqlformat"]);
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

    if (isset($options["singlestageupgrade"])) {
      dbsteward::$single_stage_upgrade = TRUE;
      // don't recreate views when in single stage upgrade mode
      // @TODO: make view diffing smart enough that this doesn't need to be done
      dbsteward::$always_recreate_views = FALSE;
    }
    
    if (isset($options["ignoreoldname"])) {
      dbsteward::$ignore_oldname = TRUE;
    }

    $output_file = FALSE;
    if (isset($options["outputfile"])
      && strlen($options["outputfile"]) > 0) {
      $output_file = $options["outputfile"];
    }

    $dbhost = FALSE;
    if (isset($options["dbhost"])
      && strlen($options["dbhost"]) > 0) {
      $dbhost = $options["dbhost"];
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

    // functions NOT dependent on sql format
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

    // Microsoft SQL Server Format Generator Modes
    if (strcasecmp(dbsteward::get_sql_format(), 'mssql10') == 0) {
      // needed for MSSQL keyword-named-columns like system_user
      dbsteward::$quote_table_names = TRUE;
      dbsteward::$quote_column_names = TRUE;

      // determine build mode, based on files passed
      if (count($options['xml']) == 0) {
        throw new exception("xml file list is empty");
      }
      else if (isset($options['newxml'])) {
        mssql10::build_upgrade($options['xml'], $options['newxml']);
      }
      else {
        mssql10::build($options['xml']);
      }
    }
    // PostgreSQL SQL Format Generator Modes
    else if (strcasecmp(dbsteward::get_sql_format(), 'pgsql8') == 0) {
      // one to one mode switches
      if (isset($options["pgschema"])) {
        if (strlen($dbhost) === FALSE) {
          throw new exception("pgschema error: postgresql host not specified");
        }
        else if (strlen($dbname) === FALSE) {
          throw new exception("pgschema error: dbname not specified");
        }
        else if (strlen($dbuser) === FALSE) {
          throw new exception("pgschema error: dbuser not specified");
        }
        $output = pgsql8::fetch_pgschema($dbhost, $dbname, $dbuser, $this->cli_dbpassword);
        if ($output_file !== FALSE) {
          dbsteward::console_line(1, "Saving pgschema output to " . $output_file);
          if (!file_put_contents($output, $output_file)) {
            throw new exception("Failed to save pgschema output to " . $output_file);
          }
        }
        else {
          dbsteward::console_text(1, $output);
        }
        exit(0);
      }

      if (isset($options['pgdatadiff'])) {
        pgsql8::pgdatadiff($dbhost, $dbname, $dbuser, $this->cli_dbpassword, $options['pgdatadiff']);
        exit(0);
      }

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

      if (isset($options["slonycompare"])) {
        pgsql8::slony_compare($options["slonycompare"]);
        exit(0);
      }

      if (isset($options["slonydiff"])) {
        pgsql8::slony_diff($options["slonydiff"], $options["slonydiffnew"]);
        exit(0);
      }

      if (isset($options["oldsql"]) || isset($options["newsql"])) {
        if ($output_file === FALSE) {
          throw new exception("sql diff error: you must specify an outputfile for this mode");
        }
        pgsql8::sql_diff($options["oldsql"], $options["newsql"], $output_file);
        exit(0);
      }

      $pgdataxml = array();
      if (isset($options['pgdataxml'])) {
        $pgdataxml = $options['pgdataxml'];
      }

      // determine build mode, based on files passed
      if (count($options['xml']) == 0) {
        throw new exception("xml file list is empty");
      }
      else if (isset($options['newxml'])) {
        pgsql8::build_upgrade($options['xml'], $options['newxml'], $pgdataxml);
      }
      else {
        pgsql8::build($options['xml'], $pgdataxml);
      }
    }
    else if (strcasecmp(dbsteward::get_sql_format(), 'mysql4') == 0) {
      dbsteward::$quote_schema_names = TRUE;
      dbsteward::$quote_table_names = TRUE;
      dbsteward::$quote_column_names = TRUE;
      
      // determine build mode, based on files passed
      if (count($options['xml']) == 0) {
        throw new exception("xml file list is empty");
      }
      else if (isset($options['newxml'])) {
        mysql4::build_upgrade($options['xml'], $options['newxml']);
      }
      else {
        mysql4::build($options['xml']);
      }
    }
    else if (strcasecmp(dbsteward::get_sql_format(), 'oracle10g') == 0) {
      dbsteward::$quote_schema_names = TRUE;
      dbsteward::$quote_table_names = TRUE;
      dbsteward::$quote_column_names = TRUE;

      // determine build mode, based on files passed
      if (count($options['xml']) == 0) {
        throw new exception("xml file list is empty");
      }
      else if (isset($options['newxml'])) {
        oracle10g::build_upgrade($options['xml'], $options['newxml']);
      }
      else {
        oracle10g::build($options['xml']);
      }
    }
    else {
      throw new exception("Unknown sqlformat: " . dbsteward::get_sql_format());
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
    $quoted_column_name = $column_name;
    if (dbsteward::$quote_column_names) {
      $quoted_column_name = '"' . $quoted_column_name . '"';
    }
    return $quoted_column_name;
  }

  public function xml_sort($files) {
    if (!is_array($files)) {
      $files = array($files);
    }
    for ($i = 0; $i < count($files); $i++) {
      $file_name = $files[$i];
      $sorted_file_name = $file_name . '.xmlsorted';
      dbsteward::console_line(1, "Sorting XML definition file: " . $file_name);
      xml_parser::file_sort($file_name, $sorted_file_name);
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
