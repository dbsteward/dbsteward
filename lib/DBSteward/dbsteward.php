<?php
/**
 * DBSteward
 * Database SQL compiler and differencing via XML definition
 * full creation script generation
 * sql upgrade script generation
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

error_reporting(E_ALL);

define('SQLFORMAT_DIR', __DIR__ . '/sql_format');

// composer autoloader
if (file_exists($fs[] = $f = __DIR__ . '/../../vendor/autoload.php')) {
  // in a dbsteward checkout
  require_once $f;
} else if (file_exists($fs[] = $f = __DIR__ . '/../../../../autoload.php')) {
  // in a composer install
  require_once $f;
} else {
  throw new Exception("Cannot find composer autoload file, checked:\n- " . implode("\n- ", $fs));
}

require_once __DIR__ . '/dbx.php';
require_once __DIR__ . '/xml_parser.php';
require_once __DIR__ . '/sql_parser.php';
require_once __DIR__ . '/output_file_segmenter.php';
require_once __DIR__ . '/ofs_replica_set_router.php';
require_once __DIR__ . '/active_sql_format_autoloader.php';
require_once __DIR__ . '/DBStewardConsoleLogFormatter.php';

class dbsteward {

  const VERSION = '1.4.2';
  const API_VERSION = '1.4';

  const PATTERN_KNOWN_TYPES = "/^bigint.*|^bigserial|^bool.*|^bytea.*|^char.*|^date|^double precision|^inet$|^interval|^int.*|^oid|^smallint|^serial|^string|^text|^time$|^time with.*|^timestamp.*|^varchar.*|^uuid$/i";

  const PATTERN_SPLIT_OPERATION = "/\,\s*/";
  const PATTERN_SPLIT_ROLE = "/\,/";
  
  const TABLE_DEPENDENCY_IGNORABLE_NAME = 'DBSTEWARD_PLACEHOLDER_TABLE_UNMATCHABLE_TABLE_NAME_DO_NOT_PROCESS_DO_NOT_PANIC_KEEP_HEAD_DOWN';

  const MODE_UNKNOWN = 0;
  const MODE_XML_DATA_INSERT = 1;
  const MODE_XML_SORT = 2;
  const MODE_XML_CONVERT = 4;
  const MODE_BUILD = 8;
  const MODE_DIFF = 16;
  const MODE_EXTRACT = 32;
  const MODE_DB_DATA_DIFF = 64;
  const MODE_XML_SLONY_ID = 73;
  const MODE_SQL_DIFF = 128;
  const MODE_SLONIK_CONVERT = 256;
  const MODE_SLONY_COMPARE = 512;
  const MODE_SLONY_DIFF = 1024;

  const DEFAULT_SQL_FORMAT = 'pgsql8';
  
  protected static $sql_format = NULL;

  /**
   * set SQL formatting mode
   *
   * @param string $format
   * @return void
   */
  public static function set_sql_format($format) {
    if ( dbsteward::sql_format_exists($format) ) {
      active_sql_format_autoloader::init($format);
      dbsteward::$sql_format = $format;
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
  public static function get_sql_format() {
    return dbsteward::$sql_format;
  }

  public static $create_languages = FALSE;
  public static $require_slony_id = FALSE;
  public static $require_slony_set_id = FALSE;
  public static $generate_slonik = FALSE;
  public static $slonyid_start_value = 1;
  public static $slonyid_set_value = 1;
  public static $output_file_statement_limit = 900;
  public static $ignore_custom_roles = FALSE;
  public static $ignore_primary_key_errors = FALSE;
  // when true, custom roles not found will be turned in to database->role->owner
  public static $require_verbose_interval_notation = FALSE;
  public static $quote_schema_names = FALSE;
  public static $quote_object_names = FALSE;
  public static $quote_table_names = FALSE;
  public static $quote_function_names = FALSE;
  public static $quote_column_names = FALSE;
  public static $quote_all_names = FALSE;
  public static $quote_illegal_identifiers = FALSE;
  public static $quote_reserved_identifiers = FALSE;
  public static $only_schema_sql = FALSE;
  public static $only_data_sql = FALSE;
  public static $limit_to_tables = array();
  public static $single_stage_upgrade = FALSE;

  public static $ENABLE_COLOR = true;
  public static $BRING_THE_RAIN = false; // Hardcore mode
  public static $DEBUG = false;
  public static $LOG_LEVEL = Monolog\Logger::NOTICE;
  protected static $logger = null;
  
  
  /**
   * directory to write all output files
   * @var string
   */
  public static $file_output_directory = FALSE;
  /**
   * output file prefix to use for artifact files
   * @var string
   */
  public static $file_output_prefix = FALSE;

  /**
   * Should old*Name attributes be validated and traced out, or ignored?
   */
  public static $ignore_oldnames = FALSE;
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

  /**
   * the password to use for connecting for database extraction
   * default FALSE. if FALSE is passed for password to extract_schema, it will prompt for password
   * @var boolean
   */
  protected $cli_dbpassword = FALSE;

  public static $old_database = NULL;
  public static $new_database = NULL;

  function __construct() {
  }

  public static function usage() {
    $VERSION = self::VERSION;
    ob_start();
    include __DIR__ . '/usage.php';
    return ob_get_clean();
  }

  public function arg_parse($argv) {
    $short_opts = 'hvq';
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
      "slonyidin::",
      "slonyidout::",
      "slonyidstartvalue::",
      "slonyidsetvalue::",
      "oldsql::",
      "newsql::",
      "dbhost::",
      "dbport::",
      "dbname::",
      "dbuser::",
      "dbpassword::",
      "requireslonyid::",
      "requireslonysetid::",
      "generateslonik::",
      "quoteschemanames::",
      "quotetablenames::",
      "quotecolumnnames::",
      "quoteallnames::",
      "quoteillegalnames::",
      "quotereservednames::",
      "onlyschemasql::",
      "onlydatasql::",
      "onlytable::",
      "singlestageupgrade::",
      "maxstatementsperfile::",
      "ignoreoldnames::",
      "ignorecustomroles::",
      "ignoreprimarykeyerrors::",
      "dbdatadiff::",
      "xmlsort::",
      "xmlconvert::",
      "xmlcollectdataaddendums::",
      "useautoincrementoptions::",
      "useschemaprefix::",
      "outputdir::",
      "outputfileprefix::",
      "debug"
    );
    $options = getopt($short_opts, $long_opts);
    self::set_verbosity($options);

    if (count($argv) == 1 || isset($options['help']) || isset($options['h'])) {
      $c = new Colors\Color();
      $c->setTheme(array(
        'header' => array('underline', 'dark_gray'),
        'keyword' => array('green'),
        'value' => array('yellow')
      ));
      echo $c->colorize(self::usage()) . PHP_EOL;
      exit(1);
    }

    $files = array(
      'old' => array(),
      'new' => array(),
      'pgdata' => array()
    );


    ///// XML file parameter sanity checks
    if ( isset($options['xml']) ) {
      if (count($options['xml']) > 0 && isset($options['oldxml']) && count($options['oldxml']) > 0) {
        dbsteward::error("Parameter error: xml and oldxml options are not to be mixed. Did you mean newxml?");
        exit(1);
      }
      if (count($options['xml']) > 0 && isset($options['newxml']) && count($options['newxml']) > 0) {
        dbsteward::error("Parameter error: xml and newxml options are not to be mixed. Did you mean oldxml?");
        exit(1);
      }
    }
    if ((isset($options['oldxml']) && count($options['oldxml']) > 0) && (!isset($options['newxml']) || count($options['newxml']) == 0)) {
      dbsteward::error("Parameter error: oldxml needs newxml specified for differencing to occur");
      exit(1);
    }
    if ((!isset($options['oldxml']) || count($options['oldxml']) == 0) && (isset($options['newxml']) && count($options['newxml']) > 0)) {
      dbsteward::error("Parameter error: oldxml needs newxml specified for differencing to occur");
      exit(1);
    }

    ///// database connectivity values
    $dbhost = FALSE;
    if (isset($options["dbhost"])
      && strlen($options["dbhost"]) > 0) {
      $dbhost = $options["dbhost"];
    }
    // $dbport set in sql_format defaults section
    $dbport = NULL;
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
    if (isset($options['dbpassword'])) {
      if ($options['dbpassword'] === false) {
        // treat --dbpassword as the empty password, because
        // --dbpassword='' doesn't show up in $options
        $this->cli_dbpassword = '';
      } else {
        $this->cli_dbpassword = $options['dbpassword'];
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
    
    if (isset($options["ignoreoldnames"])) {
      dbsteward::$ignore_oldnames = TRUE;
    }

    if (isset($options["ignorecustomroles"])) {
      dbsteward::$ignore_custom_roles = TRUE;
    }
    
    if (isset($options["ignoreprimarykeyerrors"])) {
      dbsteward::$ignore_primary_key_errors = TRUE;
    }
    
    if (isset($options["requireslonyid"])) {
      dbsteward::$require_slony_id = TRUE;
    }
    if (isset($options["requireslonysetid"])) {
      dbsteward::$require_slony_set_id = TRUE;
    }
    if (isset($options["generateslonik"])) {
      dbsteward::$generate_slonik = TRUE;
    }
    if (isset($options["slonyidstartvalue"])) {
      if ( $options["slonyidstartvalue"] < 1 ) {
        throw new exception("slonyidstartvalue must be greater than 0");
      }
      dbsteward::$slonyid_start_value = $options["slonyidstartvalue"];
    }
    if (isset($options["slonyidsetvalue"])) {
      if ( $options["slonyidsetvalue"] < 1 ) {
        throw new exception("slonyidsetvalue must be greater than 0");
      }
      dbsteward::$slonyid_set_value = $options["slonyidsetvalue"];
    }

    ///// determine the operation and check arguments for each
    $mode = dbsteward::MODE_UNKNOWN;
    if (isset($options['xmldatainsert'])) {
      if (!isset($options['xml'])) {
        throw new exception("xmldatainsert needs xml parameter defined");
      }
      $mode = dbsteward::MODE_XML_DATA_INSERT;
    }
    elseif (isset($options["xmlsort"])) {
      $mode = dbsteward::MODE_XML_SORT;
    }
    elseif (isset($options["xmlconvert"])) {
      $mode = dbsteward::MODE_XML_CONVERT;
    }
    elseif ( isset($options['xml']) && count($options['xml']) > 0 ) {
      $mode = dbsteward::MODE_BUILD;
    }
    elseif ( isset($options['newxml']) && count($options['newxml']) > 0 ) {
      $mode = dbsteward::MODE_DIFF;
    }
    elseif (isset($options["dbschemadump"])) {
      if (strlen($dbhost) === FALSE) {
        throw new exception("dbschemadump error: dbhost not specified");
      }
      elseif (strlen($dbname) === FALSE) {
        throw new exception("dbschemadump error: dbname not specified");
      }
      elseif (strlen($dbuser) === FALSE) {
        throw new exception("dbschemadump error: dbuser not specified");
      }
      elseif ($output_file === FALSE) {
        throw new exception("dbschemadump error: outputfile not specified");
      }
      $mode = dbsteward::MODE_EXTRACT;
    }
    elseif (isset($options['dbdatadiff'])) {
      if (strlen($dbhost) === FALSE) {
        throw new exception("dbdatadiff error: dbhost not specified");
      }
      elseif (strlen($dbname) === FALSE) {
        throw new exception("dbdatadiff error: dbname not specified");
      }
      elseif (strlen($dbuser) === FALSE) {
        throw new exception("dbdatadiff error: dbuser not specified");
      }
      $mode = dbsteward::MODE_DB_DATA_DIFF;
    }
    elseif (isset($options["oldsql"]) || isset($options["newsql"])) {
      if ($output_file === FALSE) {
        throw new exception("sql diff error: you must specify an outputfile for this mode");
      }
      $mode = dbsteward::MODE_SQL_DIFF;
    }
    elseif (isset($options["slonikconvert"])) {
      $mode = dbsteward::MODE_SLONIK_CONVERT;
    }
    elseif (isset($options["slonycompare"])) {
      $mode = dbsteward::MODE_SLONY_COMPARE;
    }
    elseif (isset($options["slonydiffold"])) {
      $mode = dbsteward::MODE_SLONY_DIFF;
    }
    elseif (isset($options["slonyidin"])) {
      // check to make sure output is not same as input
      if (isset($options["slonyidout"])) {
        if (strcmp($options["slonyidin"], $options["slonyidout"]) == 0) {
          throw new exception("slonyidin and slonyidout file paths should not be the same");
        }
      }
      $mode = dbsteward::MODE_XML_SLONY_ID;
    }

    ///// File output location specificity
    if ( isset($options['outputdir']) ) {
      if ( strlen($options['outputdir']) == 0 ) {
        throw new exception("outputdir is blank, must specify a value for this option");
      }
      if ( !is_dir($options['outputdir']) ) {
        throw new exception("outputdir is not a directory; this must be a writable directory");
      }
      dbsteward::$file_output_directory = $options['outputdir'];
    }
    if ( isset($options['outputfileprefix']) ) {
      if ( strlen($options['outputfileprefix']) == 0 ) {
        throw new exception("outputfileprefix is blank, must specify a value for this option");
      }
      dbsteward::$file_output_prefix = $options['outputfileprefix'];
    }


    ///// For the appropriate modes, composite the input XML
    ///// and figure out the SQL format of it
    $force_sql_format = FALSE;
    if (isset($options['sqlformat'])) {
      $force_sql_format = $options['sqlformat'];
    }

    $target_sql_format = FALSE;
    switch ($mode) {
      case dbsteward::MODE_BUILD:
        $files = (array)$options['xml'];

        $target_sql_format = xml_parser::get_sql_format($files);
        break;

      case dbsteward::MODE_DIFF:
        $old_files = (array)$options['oldxml'];
        $new_files = (array)$options['newxml'];

        $old_target = xml_parser::get_sql_format($old_files);
        $new_target = xml_parser::get_sql_format($new_files);

        // prefer the new sql_format
        $target_sql_format = $new_target ?: $old_target;
        break;
    }
    
    $xml_collect_data_addendums = 0;
    if (isset($options["xmlcollectdataaddendums"]) && $options["xmlcollectdataaddendums"] > 0) {
      $xml_collect_data_addendums = (integer)$options["xmlcollectdataaddendums"];
      if ($mode != dbsteward::MODE_BUILD) {
        throw new Exception("--xmlcollectdataaddendums is only supported for fresh builds");
      }
      if ($xml_collect_data_addendums > count($files)) {
        throw new Exception("Cannot collect more data addendums then files provided");
      }
    }

    // announce our defined version before doing any configuration announcements or tasks
    dbsteward::notice("DBSteward Version " . self::VERSION);

    ///// set the global SQL format
    $sql_format = dbsteward::reconcile_sql_format($target_sql_format, $force_sql_format);
    dbsteward::notice("Using sqlformat=$sql_format");
    dbsteward::set_sql_format($sql_format);

    if (is_null($dbport)) {
      $dbport = dbsteward::define_sql_format_default_values($sql_format, $options);
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
    if (isset($options["quoteallnames"])) {
      dbsteward::$quote_all_names = TRUE;
    }
    if (isset($options["quoteillegalnames"])) {
      dbsteward::$quote_illegal_identifiers = TRUE;
    }
    if (isset($options["quotereservednames"])) {
      dbsteward::$quote_reserved_identifiers = TRUE;
    }
    
    switch ($mode) {
      case dbsteward::MODE_XML_DATA_INSERT:
        dbsteward::xml_data_insert($options['xml'], $options['xmldatainsert']);
        break;

      case dbsteward::MODE_XML_SORT:
        dbsteward::xml_sort($options['xmlsort']);
        break;

      case dbsteward::MODE_XML_CONVERT:
        dbsteward::xml_convert($options['xmlconvert']);
        break;
      
      case dbsteward::MODE_XML_SLONY_ID:
        dbsteward::info("Compositing XML file for Slony ID processing..");
        $files = (array)$options['slonyidin'];
        $db_doc = xml_parser::xml_composite($files);
        dbsteward::info("XML files " . implode(' ', $files) . " composited");

        $output_prefix = dbsteward::calculate_file_output_prefix($files);
        $composite_file = $output_prefix . '_composite.xml';
        $db_doc = xml_parser::sql_format_convert($db_doc);
        xml_parser::vendor_parse($db_doc);
        dbsteward::notice("Saving composite as " . $composite_file);
        xml_parser::save_doc($composite_file, $db_doc);
        
        dbsteward::notice("Slony ID numbering any missing attributes");
        dbsteward::info("slonyidstartvalue = " . dbsteward::$slonyid_start_value);
        dbsteward::info("slonyidsetvalue = " . dbsteward::$slonyid_set_value);
        $slonyid_doc = xml_parser::slonyid_number($db_doc);
        $slonyid_numbered_file = $output_prefix . '_slonyid_numbered.xml';
        // if specified, use output file value instead of auto suffix
        if (isset($options["slonyidout"])) {
          $slonyid_numbered_file = $options["slonyidout"];
        }
        dbsteward::notice("Saving Slony ID numbered XML as " . $slonyid_numbered_file);
        xml_parser::save_doc($slonyid_numbered_file, $slonyid_doc);
        break;

      case dbsteward::MODE_BUILD:
        dbsteward::info("Compositing XML files..");
        $addendums_doc = NULL;
        if ($xml_collect_data_addendums > 0) {
          dbsteward::info("Collecting $xml_collect_data_addendums data addendums");
        }
        $db_doc = xml_parser::xml_composite($files, $xml_collect_data_addendums, $addendums_doc);

        if (isset($options['pgdataxml']) && count($options['pgdataxml'])) {
          $pg_data_files = (array)$options['pgdataxml'];
          dbsteward::info("Compositing pgdata XML files on top of XML composite..");
          xml_parser::xml_composite_pgdata($db_doc, $pg_data_files);
          dbsteward::info("postgres data XML files [" . implode(' ', $pg_data_files) . "] composited.");
        }

        dbsteward::info("XML files " . implode(' ', $files) . " composited");

        $output_prefix = dbsteward::calculate_file_output_prefix($files);
        $composite_file = $output_prefix . '_composite.xml';
        $db_doc = xml_parser::sql_format_convert($db_doc);
        xml_parser::vendor_parse($db_doc);
        dbsteward::notice("Saving composite as " . $composite_file);
        xml_parser::save_doc($composite_file, $db_doc);

        if ($addendums_doc !== NULL) {
          $addendums_file = $output_prefix . '_addendums.xml';
          dbsteward::notice("Saving addendums as $addendums_file");
          xml_parser::save_doc($addendums_file, $addendums_doc);
        }

        format::build($output_prefix, $db_doc);
        break;

      case dbsteward::MODE_DIFF:
        dbsteward::info("Compositing old XML files..");
        $old_db_doc = xml_parser::xml_composite($old_files);

        dbsteward::info("Old XML files " . implode(' ', $old_files) . " composited");

        dbsteward::info("Compositing new XML files..");
        $new_db_doc = xml_parser::xml_composite($new_files);

        if (isset($options['pgdataxml']) && count($options['pgdataxml'])) {
          $pg_data_files = (array)$options['pgdataxml'];
          dbsteward::info("Compositing pgdata XML files on top of new XML composite..");
          xml_parser::xml_composite_pgdata($new_db_doc, $pg_data_files);
          dbsteward::info("postgres data XML files [" . implode(' ', $pg_data_files) . "] composited");
        }

        dbsteward::info("New XML files " . implode(' ', $new_files) . " composited");

        $old_output_prefix = dbsteward::calculate_file_output_prefix($old_files);
        $old_composite_file = $old_output_prefix . '_composite.xml';
        $old_db_doc = xml_parser::sql_format_convert($old_db_doc);
        xml_parser::vendor_parse($old_db_doc);
        dbsteward::notice("Saving oldxml composite as " . $old_composite_file);
        xml_parser::save_doc($old_composite_file, $old_db_doc);

        $new_output_prefix = dbsteward::calculate_file_output_prefix($new_files);
        $new_composite_file = $new_output_prefix . '_composite.xml';
        $new_db_doc = xml_parser::sql_format_convert($new_db_doc);
        xml_parser::vendor_parse($new_db_doc);
        dbsteward::notice("Saving newxml composite as " . $new_composite_file);
        xml_parser::save_doc($new_composite_file, $new_db_doc);

        format::build_upgrade($old_output_prefix, $old_composite_file, $old_db_doc, $old_files, $new_output_prefix, $new_composite_file, $new_db_doc, $new_files);
        break;

      case dbsteward::MODE_EXTRACT:
        $output = format::extract_schema($dbhost, $dbport, $dbname, $dbuser, $this->cli_dbpassword);
        dbsteward::notice("Saving extracted database schema to " . $output_file);
        if (!file_put_contents($output_file, $output)) {
          throw new exception("Failed to save extracted database schema to " . $output_file);
        }
        break;

      case dbsteward::MODE_DB_DATA_DIFF:
        // dbdatadiff files are defined with --dbdatadiff not --xml
        $dbdatadiff_files = (array)$options['dbdatadiff'];
        
        dbsteward::info("Compositing XML files..");
        $addendums_doc = NULL;
        if ($xml_collect_data_addendums > 0) {
          dbsteward::info("Collecting $xml_collect_data_addendums data addendums");
        }
        $db_doc = xml_parser::xml_composite($dbdatadiff_files, $xml_collect_data_addendums, $addendums_doc);

        if (isset($options['pgdataxml']) && count($options['pgdataxml'])) {
          $pg_data_files = (array)$options['pgdataxml'];
          dbsteward::info("Compositing pgdata XML files on top of XML composite..");
          xml_parser::xml_composite_pgdata($db_doc, $pg_data_files);
          dbsteward::info("postgres data XML files [" . implode(' ', $pg_data_files) . "] composited.");
        }

        dbsteward::info("XML files " . implode(' ', $dbdatadiff_files) . " composited");

        $output_prefix = dbsteward::calculate_file_output_prefix($dbdatadiff_files);
        $composite_file = $output_prefix . '_composite.xml';
        $db_doc = xml_parser::sql_format_convert($db_doc);
        xml_parser::vendor_parse($db_doc);
        dbsteward::notice("Saving composite as " . $composite_file);
        xml_parser::save_doc($composite_file, $db_doc);
        
        $output = format::compare_db_data($db_doc, $dbhost, $dbport, $dbname, $dbuser, $this->cli_dbpassword);
        if (!file_put_contents($output_file, $output)) {
          throw new exception("Failed to save extracted database schema to " . $output_file);
        }
        break;

      case dbsteward::MODE_SQL_DIFF:
        format::sql_diff($options["oldsql"], $options["newsql"], $output_file);
        break;

      case dbsteward::MODE_SLONIK_CONVERT:
        $output = slony1_slonik::convert($options["slonikconvert"]);
        if ($output_file !== FALSE) {
          dbsteward::notice("Saving slonikconvert output to " . $output_file);
          if (!file_put_contents($output, $output_file)) {
            throw new exception("Failed to save slonikconvert output to " . $output_file);
          }
        }
        else {
          echo $output;
        }
        break;

      case dbsteward::MODE_SLONY_COMPARE:
        pgsql8::slony_compare($options["slonycompare"]);
        break;

      case dbsteward::MODE_SLONY_DIFF:
        pgsql8::slony_diff($options["slonydiffold"], $options["slonydiffnew"]);
        break;

      case dbsteward::MODE_UNKNOWN:
      default:
        throw new Exception("No operation specified!");
    }
  }

  protected static function set_verbosity($options) {
    static $levels = array(Monolog\Logger::ERROR, Monolog\Logger::WARNING, Monolog\Logger::NOTICE, Monolog\Logger::INFO, Monolog\Logger::DEBUG);

    $debug = isset($options['debug']);
    $v = isset($options['v']) ? count((array)$options['v']) : 0;
    $q = isset($options['q']) ? count((array)$options['q']) : 0;
    $n = $v - $q + 2;

    if ($debug) {
      self::$DEBUG = true;
      self::$LOG_LEVEL = Monolog\Logger::DEBUG;
      if ($v) {
        self::$BRING_THE_RAIN = true;
      }
    } else {
      self::$LOG_LEVEL = $levels[min(count($levels)-1, max(0, $n))];
      if ($n > 2) {
        self::$BRING_THE_RAIN = true;
      }
    }
  }

  protected static function define_sql_format_default_values($sql_format, $options) {
///// sql_format-specific default options
    $dbport = FALSE;
    if (strcasecmp($sql_format, 'pgsql8') == 0) {
      dbsteward::$create_languages = TRUE;
      dbsteward::$quote_schema_names = FALSE;
      dbsteward::$quote_table_names = FALSE;
      dbsteward::$quote_column_names = FALSE;
      $dbport = '5432';
    }
    else if (strcasecmp($sql_format, 'mssql10') == 0) {
      // needed for MSSQL keyword-named-columns like system_user
      dbsteward::$quote_table_names = TRUE;
      dbsteward::$quote_column_names = TRUE;
      $dbport = '1433';
    }
    else if (strcasecmp($sql_format, 'mysql5') == 0) {
      dbsteward::$quote_schema_names = TRUE;
      dbsteward::$quote_table_names = TRUE;
      dbsteward::$quote_column_names = TRUE;
      $dbport = '3306';
      
      if (isset($options['useautoincrementoptions'])) {
        mysql5::$use_auto_increment_table_options = TRUE;
      }

      if (isset($options['useschemaprefix'])) {
        mysql5::$use_schema_name_prefix = TRUE;
      }
    }
    
    if (strcasecmp($sql_format, 'pgsql8') != 0) {
      if (isset($options['pgdataxml'])) {
        dbsteward::error("pgdataxml parameter is not supported by " . dbsteward::get_sql_format() . " driver");
        exit(1);
      }
    }
    return $dbport;
  }

  /**
   * Given an (optional) target sql format, and (optional) requested sql format,
   * determine what sql format to use.
   *
   * The logic below should be fairly straight-forward, but here's an English version:
   *   * The "target" format is what the XML says it's targeted for
   *   * The "requested" format is what the user requested on the command line
   *   * If both are present and agree, there are no problems
   *   * If both are present and disagree, warn the user and go with the requested
   *   * If one is missing, go with the given one
   *   * If both are missing, go with dbsteward::DEFAULT_SQL_FORMAT
   *
   * @param string $target
   * @param string $requested
   * @return string
   */
  public static function reconcile_sql_format($target, $requested) {
    if ($target !== FALSE) {
      if ($requested !== FALSE) {
        if (strcasecmp($target, $requested) == 0) {
          $use_sql_format = $target;
        }
        else {
          dbsteward::warning("WARNING: XML is targeted for $target, but you are forcing $requested. Things will probably break!");
          $use_sql_format = $requested;
        }
      }
      else {
        // not forcing a sql_format, use target
        dbsteward::notice("XML file(s) are targeted for sqlformat=$target");
        $use_sql_format = $target;
      }
    }
    elseif ($requested !== FALSE) {
      $use_sql_format = $requested;
    }
    else {
      $use_sql_format = dbsteward::DEFAULT_SQL_FORMAT;
    }
    return $use_sql_format;
  }

  public static function cmd($command, $error_fatal = TRUE) {
    dbsteward::debug("dbsteward::cmd( " . $command . " )");
    $output = array();
    $return_value = 0;
    $last_line = exec($command, $output, $return_value);
    if ($return_value > 0) {
      if ($error_fatal) {
        dbsteward::error("ERROR(" . $return_value . ") with command: " . $command);
        dbsteward::error(implode("\n", $output));
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
      dbsteward::info("Sorting XML definition file: " . $file_name);
      dbsteward::info("Sorted XML output file: " . $sorted_file_name);
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
      dbsteward::info("Upconverting XML definition file: " . $file_name);
      dbsteward::info("Upconvert XML output file: " . $converted_file_name);
      $doc = simplexml_load_file($file_name);
      xml_parser::sql_format_convert($doc);
      $converted_xml = xml_parser::format_xml($doc->asXML());
      $converted_xml = str_replace('pgdbxml>', 'dbsteward>', $converted_xml);
      file_put_contents($converted_file_name, $converted_xml);
    }
  }

  public function xml_data_insert($def_file, $data_file) {
    dbsteward::info("Automatic insert data into " . $def_file . " from " . $data_file);
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
          dbsteward::info("Adding rows column " . $new_column . " to definition table " . $def_table['name']);
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
    dbsteward::notice("Saving modified dbsteward definition as " . $def_file_modified);
    return xml_parser::save_xml($def_file_modified, $def_doc->saveXML());
  }
  
  public static function xml_slony_id_number($infile, $outfile) {
    
  }

  public static function get_logger() {
    if (!self::$logger) {
      self::$logger = new Monolog\Logger('dbsteward');
      self::$logger->pushHandler($sh = new Monolog\Handler\StreamHandler('php://stderr', static::$LOG_LEVEL));
      $sh->setFormatter(new DBStewardConsoleLogFormatter);
    }
    return self::$logger;
  }

  private static function log($level, $text) { 
    self::get_logger()->log($level, $text);
  }
  public static function trace($text) {
    if (self::$BRING_THE_RAIN) {
      self::log(Monolog\Logger::DEBUG, $text);
    }
  }
  public static function debug($text) {
    self::log(Monolog\Logger::DEBUG, $text);
  }
  public static function info($text) {
    self::log(Monolog\Logger::INFO, $text);
  }
  public static function notice($text) {
    self::log(Monolog\Logger::NOTICE, $text);
  }
  public static function warning($text) {
    self::log(Monolog\Logger::WARNING, $text);
  }
  public static function error($text) {
    self::log(Monolog\Logger::ERROR, $text);
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
  
  public static function calculate_file_output_directory($context_file = FALSE) {
    if ( $context_file !== FALSE ) {
      $output_dir = dirname($context_file);
    }
    if ( dbsteward::$file_output_directory !== FALSE ) {
      $output_dir = dbsteward::$file_output_directory;
    }
    return $output_dir;
  }
  
  /**
   * Consistency function to return the directory and extensionless basename of the first of a list of files
   *
   * @param array|string $files
   * @return string
   */
  public static function calculate_file_output_prefix($files) {
    $files = (array)$files;
    $output_prefix = dbsteward::calculate_file_output_directory($files[0]) . '/' . basename($files[0], '.xml');
    if ( dbsteward::$file_output_prefix !== FALSE ) {
      $output_prefix = dbsteward::calculate_file_output_directory($files[0]) . '/' . dbsteward::$file_output_prefix;
    }
    return $output_prefix;
  }

}

?>
