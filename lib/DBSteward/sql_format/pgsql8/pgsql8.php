<?php
/**
 * PostgreSQL specific compiling and differencing functions
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8 extends sql99 {

  const QUOTE_CHAR = '"';

  const PATTERN_SERIAL_COLUMN = '/^serial|bigserial$/i';

  const PATTERN_REPLICATED_COLUMN = '/^serial|bigserial$/i';

  const PATTERN_TABLE_LINKED_TYPES = '/^serial|bigserial$/i';

  const E_ESCAPE = TRUE;

  const MAX_IDENTIFIER_LENGTH = 63;

  /**
   * Pretty much the same as sql99::VALID_IDENTIFIER_REGEX, except it limits it to 63 chars
   * @const string
   */
  const VALID_IDENTIFIER_REGEX = '/^[a-z_]\w{0,62}$/i';

  public static $table_slony_ids = array();
  public static $sequence_slony_ids = array();

  public static function get_identifier_blacklist_file() {
    return __DIR__ . '/pgsql8_identifier_blacklist.txt';
  }
  
  /**
   * Current replica set ID context
   * @var type
   */
  protected static $context_replica_set_id;
  
  /**
   * If the passed $obj has a slonySetId, set it as the context_replica_set_id
   * @param SimpleXMLElement $obj
   * @return integer determined slonySetId
   */
  public static function set_context_replica_set_id($obj) {
    if ( $obj === NULL ) {
      // do not do contexting when passed PHP NULL
      return FALSE;
    }
    
    // if not explicit NULL, must be a SimpleXMLElement
    if ( !is_object($obj) || strcasecmp(get_class($obj), 'SimpleXMLElement') != 0 ) {
      throw new exception("set_context_replica_set_id passed non-SimpleXMLElement object");
    }
    
    // must be a schema table column trigger sequence type function view element to be defining slonySetId context attribute
    if ( !in_array(strtolower($obj->getName()), array('schema', 'table', 'column', 'trigger', 'sequence', 'sql', 'type', 'function', 'view')) ) {
      throw new exception("set_context_replica_set_id passed element that is not a schema table column trigger sequence sql type function view element -- " . $obj->getName());
    }

    if ( !isset($obj['slonySetId']) ) {
      // context_replica_set_id -10 means object does not have slonySetId defined
      return self::$context_replica_set_id = -10;
    }
    $set_id = (integer)($obj['slonySetId']);
    return self::$context_replica_set_id = $set_id;
  }
  
  /**
   * Return the current context replica set id
   * @return integer
   */
  public static function get_context_replica_set_id() {
    return self::$context_replica_set_id;
  }
  
  public static function set_context_replica_set_to_natural_first($db_doc) {
    if ( ! dbsteward::$generate_slonik ) {
      // if not generating slonik, don't do anything
      return FALSE;
    }
    $replica_set = pgsql8::get_slony_replica_set_natural_first($db_doc);
    if ( $replica_set ) {
      $set_id = (string)$replica_set['id'];
      return self::$context_replica_set_id = $set_id;
    }
    return FALSE;
  }
  
  public static function get_active_replica_set($db_doc) {
    return pgsql8::get_slony_replica_set($db_doc, self::$context_replica_set_id);
  }

  public static $track_pg_identifiers = FALSE;
  public static $known_pg_identifiers = array();
  public static function identifier_name($schema, $table, $column, $suffix) {
    // make sure SimpleXML attributes cast into strings as these are used for array keys
    $schema = trim($schema);
    $table = trim($table);
    $column = trim($column);

    // figure out the name of the sequence
    $ident_table = $table;
    $ident_column = $column;

    // maxlen of pg identifiers is 63
    $max_len = 63;
    $max_len = $max_len - 1 - strlen($suffix);

    $table_maxlen = ceil($max_len / 2);
    $column_maxlen = $max_len - $table_maxlen;
    if ($table_maxlen + $column_maxlen > $max_len) {
      $column_maxlen = $column_maxlen - 1;
    }

    // table is longer, column is shorter
    if (strlen($ident_table) > $table_maxlen && strlen($ident_column) < $column_maxlen) {
      // give column excess to table_maxlen
      $table_maxlen += $column_maxlen - strlen($ident_column);
    }
    // table is shorter, column is longer
    if (strlen($ident_table) < $table_maxlen && strlen($ident_column) > $column_maxlen) {
      // give table excess to column_maxlen
      $column_maxlen += $table_maxlen - strlen($ident_table);
    }

    if (strlen($ident_table) > $table_maxlen) {
      $ident_table = substr($ident_table, 0, $table_maxlen);
    }

    if (strlen($ident_column) > $column_maxlen) {
      $ident_column = substr($ident_column, 0, $column_maxlen);
    }

    $ident_name = $ident_table . '_' . $ident_column . $suffix;

    if (self::$track_pg_identifiers) {
      if (!isset(self::$known_pg_identifiers[$schema])) {
        self::$known_pg_identifiers[$schema] = array();
      }
      if (!isset(self::$known_pg_identifiers[$schema][$table])) {
        self::$known_pg_identifiers[$schema][$table] = array();
      }
      if (in_array($ident_name, self::$known_pg_identifiers[$schema][$table])) {
        dbsteward::trace("rename ident_name FROM " . $ident_name);
        $inc = 1;
        $ident_column = substr($ident_column, 0, $column_maxlen - 1);
        $ident_name = $ident_table . '_' . $ident_column . $suffix . $inc;
        dbsteward::trace("rename ident_name TO " . $ident_name);
        while (in_array($ident_name, self::$known_pg_identifiers[$schema][$table])) {
          dbsteward::trace("rename ident_name FROM " . $ident_name);
          $inc++;
          $ident_name = $ident_table . '_' . $ident_column . '_' . $suffix . $inc;
          dbsteward::trace("rename ident_name TO " . $ident_name);
        }
      }
      self::$known_pg_identifiers[$schema][$table][] = $ident_name;
      //var_dump(self::$known_pg_identifiers);
    }

    return $ident_name;
  }

  public static function strip_escaping_e($value) {
    if (strlen($value) > 2 && substr($value, 0, 2) == "E'" && substr($value, -1) == "'") {
      // just cut off the E, as we still want the data to be ' quoted
      $value = substr($value, 1);
    }
    return $value;
  }

  public static function strip_string_quoting($value) {
    // 'string' becomes string
    if (strlen($value) > 2 && substr($value, 0, 1) == "'" && substr($value, -1) == "'") {
      $value = substr($value, 1);
      $value = substr($value, 0, -1);
    }
    return $value;
  }

  /**
   * escape a column's value, or return the default value if none specified
   *
   * @return string
   */
  public static function column_value_default($node_schema, $node_table, $data_column_name, $node_col) {
    // if marked, make it null or default, depending on column options
    if (isset($node_col['null']) && strcasecmp('true', $node_col['null']) == 0) {
      $value = 'NULL';
    }
    // columns that specify empty attribute are made empty strings
    else if (isset($node_col['empty']) && strcasecmp('true', $node_col['empty']) == 0) {
      if (pgsql8::E_ESCAPE) {
        $value = "E''";
      }
      else {
        $value = "''";
      }
    }
    // don't esacape columns marked literal sql values
    else if (isset($node_col['sql']) && strcasecmp($node_col['sql'], 'true') == 0) {
      if (strcasecmp($node_col, 'default') === 0) {
        $value = 'DEFAULT';
      } else {
        $value = '(' . $node_col . ')';
      }
    }
    // else if col is zero length, make it default, or DB NULL
    else if (strlen($node_col) == 0) {
      // is there a default defined for the column?
      $dummy_data_column = new stdClass();
      $column_default_value = xml_parser::column_default_value($node_table, $data_column_name, $dummy_data_column);
      if ($column_default_value != NULL) {
        $value = $column_default_value;
      }
      // else put a NULL in the values list
      else {
        $value = 'NULL';
      }
    }
    else {
      //$node_column = dbx::get_table_column($node_table, $data_column_name);
      $node_column = xml_parser::inheritance_get_column($node_table, $data_column_name);
      $node_column = $node_column[0];
      if ($node_column === NULL) {
        throw new exception("Failed to find table " . $node_table['name'] . " column " . $data_column_name . " for default value check");
      }
      $value_type = pgsql8_column::column_type(dbsteward::$new_database, $node_schema, $node_table, $node_column, $foreign);

      $value = pgsql8::value_escape($value_type, dbsteward::string_cast($node_col));
    }
    return $value;
  }

  /**
   * escape data types that need it
   *
   * @param mixed $value value to check for escaping
   *
   * @value mixed value, escaped as necessary
   */
  public static function value_escape($type, $value, $db_doc = NULL) {
    if (strlen($value) > 0) {
      // data types that should be quoted
      $enum_regex = dbx::enum_regex($db_doc);
      if (strlen($enum_regex) > 0) {
        $enum_regex = '|' . $enum_regex;
      }

      // complain when assholes use colon time notation instead of postgresql verbose for interval expressions
      if (dbsteward::$require_verbose_interval_notation) {
        if (preg_match('/interval/i', $type) > 0) {
          if (substr($value, 0, 1) != '@') {
            throw new exception("bad interval value: " . $value . " -- interval types must be postgresql verbose format: '@ 2 hours 30 minutes' etc for cfxn comparisons to work");
          }
        }
      }

      // data types that should be quoted
      if (preg_match("/^bool.*|^character.*|^string|^text|^date|^time.*|^(?:var)?char.*|^interval|^money.*|^inet|uuid|ltree" . $enum_regex . "/i", $type) > 0) {
        $value = "'" . pg_escape_string($value) . "'";

        // data types that should have E prefix to their quotes
        if (pgsql8::E_ESCAPE
          && preg_match("/^character.*|^string|^text|^(?:var)?char.*/", $type) > 0) {
          $value = 'E' . $value;
        }
      }
    }
    else {
      // value is zero length, make it NULL
      $value = "NULL";
    }
    return $value;
  }

  public static function build($output_prefix, $db_doc) {
    if ( strlen($output_prefix) == 0 ) {
      throw new exception("pgsql8::build() sanity failure: output_prefix is blank");
    }
    // build full db creation script
    $build_file = $output_prefix . '_build.sql';
    dbsteward::info("Building complete file " . $build_file);
    $build_file_fp = fopen($build_file, 'w');
    if ($build_file_fp === FALSE) {
      throw new exception("failed to open full file " . $build_file . ' for output');
    }
    $build_file_ofs = new output_file_segmenter($build_file, 1, $build_file_fp, $build_file);
    if (count(dbsteward::$limit_to_tables) == 0) {
      $build_file_ofs->write("-- full database definition file generated " . date('r') . "\n");
    }
    if (!dbsteward::$generate_slonik) {
      $build_file_ofs->write("BEGIN;\n\n");
    }

    dbsteward::info("Calculating table foreign key dependency order..");
    $table_dependency = xml_parser::table_dependency_order($db_doc);

    // database-specific implementation code refers to dbsteward::$new_database when looking up roles/values/conflicts etc
    dbsteward::$new_database = $db_doc;
    dbx::set_default_schema($db_doc, 'public');

    // language defintions
    if (dbsteward::$create_languages) {
      foreach ($db_doc->language AS $language) {
        $build_file_ofs->write(pgsql8_language::get_creation_sql($language));
      }
    }

    // by default, postgresql will validate the contents of LANGUAGE SQL functions during creation
    // because we are creating all functions before tables, this doesn't work when LANGUAGE SQL functions
    // refer to tables yet to be created.
    // scan language="sql" functions for <functionDefiniton>s that contain FROM (<TABLE>) statements
    $set_check_function_bodies = TRUE; // on in default postgresql configs
    dbx::set_default_schema($db_doc, 'public');
    foreach($db_doc->schema AS $schema) {
      foreach($schema->function AS $function) {
        if ( pgsql8_function::has_definition($function) ) {
          $definition = pgsql8_function::get_definition($function);
          if ( strcasecmp($definition['language'], 'sql') == 0
            && $definition['sqlFormat'] == 'pgsql8'
            && !is_null($referenced_table_name = static::function_definition_references_table($definition))) {
            $table_schema_name = sql_parser::get_schema_name($referenced_table_name, $db_doc);
            $node_schema = dbx::get_schema($db_doc, $table_schema_name);
            $node_table = dbx::get_table($node_schema, sql_parser::get_object_name($referenced_table_name));
            if ( $node_table ) {
              // the referenced table is in the definition
              // turn off check_function_bodies
              $set_check_function_bodies = FALSE;
              $set_check_function_bodies_info = "Detected LANGUAGE SQL function " . $schema['name'] . '.' . $function['name'] . " referring to table " . $table_schema_name . '.' . $node_table['name'] . " in the database definition";
              dbsteward::info($set_check_function_bodies_info);
              break 2;
            }
          }
        }
      }
    }
    if ( !$set_check_function_bodies ) {
      $build_file_ofs->write("\n");
      $build_file_ofs->write("SET check_function_bodies = FALSE; -- DBSteward " . $set_check_function_bodies_info . "\n\n");
    }

    if (dbsteward::$only_schema_sql
      || !dbsteward::$only_data_sql) {
      dbsteward::info("Defining structure");
      pgsql8::build_schema($db_doc, $build_file_ofs, $table_dependency);
    }
    if (!dbsteward::$only_schema_sql
      || dbsteward::$only_data_sql) {
      dbsteward::info("Defining data inserts");
      pgsql8::build_data($db_doc, $build_file_ofs, $table_dependency);
    }
    dbsteward::$new_database = NULL;
    
    if (!dbsteward::$generate_slonik) {
      $build_file_ofs->write("COMMIT;\n\n");
    }

    if ( dbsteward::$generate_slonik ) {
      $replica_sets = static::get_slony_replica_sets($db_doc);
      foreach($replica_sets AS $replica_set) {
        // output preamble file standalone for tool chains that use the preamble to do additional slonik commands
        pgsql8::build_slonik_preamble($db_doc, $replica_set, $output_prefix . "_slony_replica_set_" . $replica_set['id'] . "_preamble.slonik");
        // output paths specificity standalone for tool chains that use the store path slonik statements separately
        pgsql8::build_slonik_paths($db_doc, $replica_set, $output_prefix . "_slony_replica_set_" . $replica_set['id'] . "_paths.slonik");
        // output create set file standalone for tool chains that use the create_set slonik separately
        $create_set_filename = $output_prefix . '_slony_replica_set_' . $replica_set['id'] . '_create_set.slonik';
        pgsql8::build_slonik_create_set($db_doc, $replica_set, $create_set_filename);
        
        pgsql8::build_slonik_preamble($db_doc, $replica_set, $output_prefix . "_slony_replica_set_" . $replica_set['id'] . "_create_nodes.slonik");
        pgsql8::build_slonik_store_nodes($db_doc, $replica_set, $output_prefix . "_slony_replica_set_" . $replica_set['id'] . "_create_nodes.slonik");
        pgsql8::build_slonik_paths($db_doc, $replica_set, $output_prefix . "_slony_replica_set_" . $replica_set['id'] . "_create_nodes.slonik");

        // build full subscribe steps that creates sets and subscribes nodes
        $subscribe_filename = $output_prefix . "_slony_replica_set_" . $replica_set['id'] . "_subscribe.slonik";
        pgsql8::build_slonik_preamble($db_doc, $replica_set, $subscribe_filename);
        // create_set does one time slony configuration comparison.
        // so append the content of _create_set into _subscribe built earlier
        file_put_contents($subscribe_filename, file_get_contents($create_set_filename), FILE_APPEND);
        
        foreach($replica_set->slonyReplicaSetNode AS $replica_set_node) {
          pgsql8::build_slonik_subscribe_set_node($db_doc, $replica_set, $output_prefix . "_slony_replica_set_" . $replica_set['id'] . "_subscribe.slonik", $replica_set_node);
        }
        
        static::slony_ids_required_during_build($replica_set, $db_doc);
      }

      $count = 0;
      foreach (array_keys(self::$sequence_slony_ids) as $slony_set_id) {
        $count += count(self::$sequence_slony_ids[$slony_set_id]);
      }

      dbsteward::notice("[slony] ID summary: " . count(self::$table_slony_ids) . " tables " . $count . " sequences");
      dbsteward::notice("[slony] table ID segments: " . static::slony_id_segment_summary(self::$table_slony_ids));
      
      // keep this from bombing on there being no ids in $sequence_slony_ids
      // if there were none returned (i.e. either there weren't any defined
      // or they were all set to IGNORE_REQUIRED which hopefully doesn't happen
      // because why would you do that for all of them)
      if (!empty(self::$sequence_slony_ids)) {
        foreach (array_keys(self::$sequence_slony_ids) as $slony_set_id) {
          $console_line = "[slony] sequence ID segments";
          if ($slony_set_id != 'NoSlonySet') {
            $console_line .= " for slonySetId $slony_set_id";
          }
          $console_line .= ": ";
          dbsteward::notice($console_line . static::slony_id_segment_summary(self::$sequence_slony_ids[$slony_set_id]));
        }
      }
    }

    return $db_doc;
  }
  
  /**
   * We're not worried about actually setting slony info here because this is
   * only called during build, which will do so on its own... what we
   * are interested in is making sure exceptions are thrown if requireSlonyId
   * is set TRUE and a table / sequence 
   * @param type $replica_set
   * @param type $db_doc
   */
  protected static function slony_ids_required_during_build($replica_set, $db_doc) {
    foreach ($db_doc->schema as $schema) {
      foreach ($schema->table as $table) {
        static::slony_replica_set_contains_table($db_doc, $replica_set, $schema, $table);
      }
      foreach ($schema->sequence as $sequence) {
        static::slony_replica_set_contains_sequence($db_doc, $replica_set, $schema, $sequence);
      }
    }
  }
  
  

  public static function build_schema($db_doc, $ofs, $table_depends) {
    // schema creation
    foreach ($db_doc->schema AS $schema) {
      $ofs->write(pgsql8_schema::get_creation_sql($schema));

      // schema grants
      if (isset($schema->grant)) {
        foreach ($schema->grant AS $grant) {
          $ofs->write(pgsql8_permission::get_sql($db_doc, $schema, $schema, $grant) . "\n");
        }
      }
    }

    // types: enumerated list, etc
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->type AS $type) {
        $ofs->write(pgsql8_type::get_creation_sql($schema, $type) . "\n");
      }
    }

    // table structure creation
    foreach ($db_doc->schema AS $schema) {

      // create defined tables
      pgsql8_table::$include_column_default_nextval_in_create_sql = FALSE;
      foreach ($schema->table AS $table) {
        // table definition
        $ofs->write(pgsql8_table::get_creation_sql($schema, $table) . "\n");

        // table indexes
        pgsql8_diff_indexes::diff_indexes_table($ofs, NULL, NULL, $schema, $table);

        // table grants
        if (isset($table->grant)) {
          foreach ($table->grant AS $grant) {
            $ofs->write(pgsql8_permission::get_sql($db_doc, $schema, $table, $grant) . "\n");
          }
        }

        $ofs->write("\n");
      }
      pgsql8_table::$include_column_default_nextval_in_create_sql = TRUE;

      // sequences contained in the schema
      if (isset($schema->sequence)) {
        foreach ($schema->sequence AS $sequence) {
          $ofs->write(pgsql8_sequence::get_creation_sql($schema, $sequence));

          // sequence permission grants
          if (isset($sequence->grant)) {
            foreach ($sequence->grant AS $grant) {
              $ofs->write(pgsql8_permission::get_sql($db_doc, $schema, $sequence, $grant) . "\n");
            }
          }
        }
      }

      // add table nextvals that were omitted
      foreach ($schema->table AS $table) {
        if ( pgsql8_table::has_default_nextval($table) ) {
          $ofs->write(pgsql8_table::get_default_nextval_sql($schema, $table) . "\n");
        }
      }
    }
    $ofs->write("\n");    
    
    // function definitions
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->function AS $function) {
        if (pgsql8_function::has_definition($function)) {
          $ofs->write(pgsql8_function::get_creation_sql($schema, $function));
          // when pg:build_schema() is doing its thing for straight builds, include function permissions
          // they are not included in pg_function::get_creation_sql()
          foreach(dbx::get_permissions($function) AS $function_permission) {
            $ofs->write(pgsql8_permission::get_sql($db_doc, $schema, $function, $function_permission) . "\n");
          }
        }
      }
    }
    $ofs->write("\n");

    // maybe move this but here we're defining column defaults fo realz
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->table AS $table) {
        $ofs->write(pgsql8_table::define_table_column_defaults($schema, $table));
      }
    }

    // define table primary keys before foreign keys so unique requirements are always met for FOREIGN KEY constraints
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->table AS $table) {
        pgsql8_diff_tables::diff_constraints_table($ofs, NULL, NULL, $schema, $table, 'primaryKey', FALSE);
      }
    }
    $ofs->write("\n");

    // foreign key references
    // use the dependency order to specify foreign keys in an order that will satisfy nested foreign keys and etc
    for ($i = 0; $i < count($table_depends); $i++) {
      $schema = $table_depends[$i]['schema'];
      $table = $table_depends[$i]['table'];
      if ( $table['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
        // don't do anything with this table, it is a magic internal DBSteward value
        continue;
      }
      pgsql8_diff_tables::diff_constraints_table($ofs, NULL, NULL, $schema, $table, 'constraint', FALSE);
    }
    $ofs->write("\n");

    // trigger definitions
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->trigger AS $trigger) {
        // only do triggers set to the current sql format
        if (strcasecmp($trigger['sqlFormat'], dbsteward::get_sql_format()) == 0) {
          $ofs->write(pgsql8_trigger::get_creation_sql($schema, $trigger));
        }
      }
    }
    $ofs->write("\n");

    pgsql8_diff_views::create_views_ordered($ofs, null, $db_doc);

    // view permission grants
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->view AS $view) {
        if (isset($view->grant)) {
          foreach ($view->grant AS $grant) {
            $ofs->write(pgsql8_permission::get_sql($db_doc, $schema, $view, $grant) . "\n");
          }
        }
      }
    }
    $ofs->write("\n");

    // use pgdiff to add any configurationParameters that are defined
    pgsql8_diff::update_database_config_parameters($ofs, null, $db_doc);
  }

  public static function build_data($db_doc, $ofs, $tables) {
    // use the dependency order to then write out the actual data inserts into the data sql file
    $tables_count = count($tables);
    $limit_to_tables_count = count(dbsteward::$limit_to_tables);
    for ($i = 0; $i < $tables_count; $i++) {
      $schema = $tables[$i]['schema'];
      $table = $tables[$i]['table'];
      if ( $table['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
        // don't do anything with this table, it is a magic internal DBSteward value
        continue;
      }

      if ($limit_to_tables_count > 0) {
        if (in_array($schema['name'], array_keys(dbsteward::$limit_to_tables))) {
          if (in_array($table['name'], dbsteward::$limit_to_tables[(string)($schema['name']) ])) {
            // table is to be included
          }
          else {
            continue;
          }
        }
        else {
          continue;
        }
      }

      $ofs->write(pgsql8_diff_tables::get_data_sql(NULL, NULL, $schema, $table, FALSE));

      // set serial primary keys to the max value after inserts have been performed
      // only if the PRIMARY KEY is not a multi column
      $node_rows = & dbx::get_table_rows($table);
      $columns = preg_split("/,|\s/", $node_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
      if (isset($table['primaryKey'])
        && strlen($table['primaryKey']) > 0 && in_array(dbsteward::string_cast($table['primaryKey']), $columns)) {
        $pk_column = dbsteward::string_cast($table['primaryKey']);
        // only do it if the primary key column is also a serial/bigserial
        $nodes = xml_parser::inheritance_get_column($table, $pk_column);
        if (count($nodes) != 1) {
          var_dump($nodes);
          throw new exception("Failed to find primary key column '" . $pk_column . "' for " . $schema['name'] . "." . $table['name']);
        }
        $pk = $nodes[0];
        $pk_column_type = strtolower(dbsteward::string_cast($pk['type']));
        if (preg_match(pgsql8::PATTERN_TABLE_LINKED_TYPES, $pk_column_type) > 0) {
          // only set the pkey to MAX() if serialStart is not defined
          if ( !isset($pk['serialStart']) ) {
            $sql = "SELECT setval(pg_get_serial_sequence('" . $schema['name'] . "." . $table['name'] . "', '" . $pk_column . "'), MAX($pk_column), TRUE) FROM " . $schema['name'] . "." . $table['name'] . ";\n";
            $ofs->write($sql);
          }
        }
      }

      // check if primary key is a column of this table - FS#17481
      $primary_keys_exist = self::primary_key_split($table['primaryKey']);
      foreach ($table->column AS $column) {
        // while looping through columns, check to see if primary key is one of them
        // if it is remove it from the primary keys array, at the end of loop array should be empty
        $key = array_search($column['name'], $primary_keys_exist);
        if (is_numeric($key)) {
          unset($primary_keys_exist[$key]);
        }
      }

      // throw an error if the table is using a primaryKey column that does not actually exist
      if (!empty($primary_keys_exist)) {
        if (empty($table['inheritsTable'])) {
          throw new exception('Primary key ' . $table['primaryKey'] . ' does not exist as a column in table ' . $table['name']);
        }
        else {
          dbsteward::info('Primary key ' . $table['primaryKey'] . ' does not exist as a column in child table ' . $table['name'] . ', but may exist in parent table');
        }
      }
    }

    // include all of the unstaged sql elements
    dbx::build_staged_sql($db_doc, $ofs, NULL);
    $ofs->write("\n");
  }

  /**
   * Build the slonik commands to create the specified replica set
   *
   * @param SimpleXMLElement $db_doc
   * @param SimpleXMLElement $replica_set
   * @param string           $slonik_file
   * @throws exception
   */
  public static function build_slonik_create_set($db_doc, $replica_set, $slonik_file) {
    dbsteward::notice("Building slonik CREATE SET for replica set ID " . $replica_set['id'] . " output file " . $slonik_file);
    $slonik_fp = fopen($slonik_file, 'a');
    if ($slonik_fp === FALSE) {
      throw new exception("failed to open slonik file " . $slonik_file . ' for output');
    }
    $slonik_ofs = new output_file_segmenter($slonik_file, 1, $slonik_fp, $slonik_file);
    $slonik_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    $generation_date = date('r');
    $slonik_ofs->write("# DBSteward slony replica set ID " . $replica_set['id'] . " " . $replica_set['comment'] . " create commands generated " . $generation_date . "\n\n");
    $slonik_ofs->write("ECHO 'DBSteward slony replica set ID " . $replica_set['id'] . " " . $replica_set['comment'] . " create commands generated " . $generation_date . "';\n\n");
    
    $slonik_ofs->write("CREATE SET (ID = " . $replica_set['id'] . ", ORIGIN = " . $replica_set['originNodeId'] . ", COMMENT = '" . $replica_set['comment'] . "');\n\n");

    // schema and table structure
    foreach ($db_doc->schema AS $schema) {

      // table definitions
      foreach ($schema->table AS $table) {
        foreach ($table->column AS $column) {
          // is this table column replicated in this replica set?
          if ( static::slony_replica_set_contains_table_column_serial_sequence($db_doc, $replica_set, $schema, $table, $column) ) {
            self::check_duplicate_sequence_slony_id((string)$column['name'], 'column', (string)$column['slonyId']);
            
            self::set_sequence_slony_ids($column, $db_doc);

            $col_sequence = pgsql8::identifier_name($schema['name'], $table['name'], $column['name'], '_seq');
            $slonik_ofs->write(sprintf(slony1_slonik::script_add_sequence, $replica_set['id'], $replica_set['originNodeId'], $column['slonyId'], $schema['name'] . '.' . $col_sequence, $schema['name'] . '.' . $col_sequence . ' serial sequence column replication') . "\n\n");
          }
        }

        // is this table replicated in this replica set?
        if ( static::slony_replica_set_contains_table($db_doc, $replica_set, $schema, $table) ) {
          if (in_array(dbsteward::string_cast($table['slonyId']), self::$table_slony_ids)) {
            throw new exception("table " . $table['name'] . " slonyId " . $table['slonyId'] . " already in table_slony_ids -- duplicates not allowed");
          }
          self::$table_slony_ids[] = dbsteward::string_cast($table['slonyId']);

          $slonik_ofs->write(sprintf(slony1_slonik::script_add_table, $replica_set['id'], $replica_set['originNodeId'], $table['slonyId'], $schema['name'] . '.' . $table['name'], $schema['name'] . '.' . $table['name'] . ' table replication') . "\n\n");
        }
      }

      // sequence slony replication configuration
      if (isset($schema->sequence)) {
        foreach ($schema->sequence AS $sequence) {
          // is this sequence replicated in this replica set?
          if ( static::slony_replica_set_contains_sequence($db_doc, $replica_set, $schema, $sequence) ) {
            self::check_duplicate_sequence_slony_id((string)$sequence['name'], 'sequence', (string)$sequence['slonyId']);
            self::set_sequence_slony_ids($sequence, $db_doc);

            $slonik_ofs->write(sprintf(slony1_slonik::script_add_sequence, $replica_set['id'], $replica_set['originNodeId'], $sequence['slonyId'], $schema['name'] . '.' . $sequence['name'], $schema['name'] . '.' . $sequence['name'] . ' sequence replication') . "\n\n");
          }
        }
      }
    }
  }
  
  /**
   * Build the slonik commands to subscribe the specified node the specified replica set
   *
   * @param SimpleXMLElement $db_doc
   * @param SimpleXMLElement $replica_set
   * @param string           $slonik_file
   * @param SimpleXMLElement $replica_set_node
   * @throws exception
   */
  public static function build_slonik_subscribe_set_node($db_doc, $replica_set, $slonik_file, $replica_set_node) {
    dbsteward::notice("Building slonik replica set ID " . $replica_set['id'] . " node ID " . $replica_set_node['id'] . " subscription output file " . $slonik_file);
    $slonik_fp = fopen($slonik_file, 'a');
    if ($slonik_fp === FALSE) {
      throw new exception("failed to open slonik file " . $slonik_file . ' for output');
    }
    $slonik_ofs = new output_file_segmenter($slonik_file, 1, $slonik_fp, $slonik_file);
    $slonik_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    $generation_date = date('r');
    $slonik_ofs->write("# DBSteward slony replica set ID " . $replica_set['id'] . " node " . $replica_set_node['id'] . " subscription commands generated " . $generation_date . "\n\n");
    $slonik_ofs->write("ECHO 'DBSteward slony replica set ID " . $replica_set['id'] . " node " . $replica_set_node['id'] . " subscription commands generated " . $generation_date . "';\n\n");

    // on the subscriber node
    // wait for sync to come from primary node to provider node
    // subscribe the node to provider
    // wait for sync to come from primary node to subscriber node
    $subscription =
"ECHO 'Subscribing replicaNode " . $replica_set_node['id'] . " to providerNodeID " . $replica_set_node['providerNodeId'] . " replica set ID " . $replica_set['id'] . "';
SYNC (ID = " . $replica_set['originNodeId'] . ");
WAIT FOR EVENT (
  ORIGIN = " . $replica_set['originNodeId'] . ",
  CONFIRMED = ALL,
  WAIT ON = " . $replica_set_node['providerNodeId'] . ",
  TIMEOUT = 0
);
SLEEP (SECONDS=30);

SUBSCRIBE SET (
  ID = " . $replica_set['id'] . ",
  PROVIDER = " . $replica_set_node['providerNodeId'] . ",
  RECEIVER = " . $replica_set_node['id'] . ",
  FORWARD = YES
);
SLEEP (SECONDS=30);

ECHO 'Waiting for replicaNode " . $replica_set_node['id'] . " subscription to providerNodeID " . $replica_set_node['providerNodeId'] . " replica set ID " . $replica_set['id'] . "';
SYNC (ID = " . $replica_set['originNodeId'] . ");
WAIT FOR EVENT (
  ORIGIN = " . $replica_set['originNodeId'] . ",
  CONFIRMED = ALL,
  WAIT ON = " . $replica_set_node['id'] . ",
  TIMEOUT = 0
);

";
    $slonik_ofs->write($subscription);
  }
  
  /***
   * Summarize the ID segments in the array of ids passed
   * @param integer $ids
   * @return string
   */
  protected static function slony_id_segment_summary($ids) {
    sort($ids, SORT_NUMERIC);
    $last_id = (int)($ids[0]);
    $streak = 0;
    $s = $ids[0];
    for($i = 1; $i <= count($ids) - 1; $i++) {
      if ( (int)($ids[$i]) == (int)($last_id) + 1 ) {
        $streak++;
      }
      else {
        if ( $streak >= 1 ) {
          $s .= "-" . $ids[$i - 1];
        }
        $s .= ", " . $ids[$i];
        $streak = 0;
      }
      $last_id = (int)($ids[$i]);
    }
    if ( $streak > 0 ) {
      $s .= "-" . $ids[count($ids) - 1];
    }
    return $s;
  }

  public static function get_slony_next_id_dialogue($doc) {
    // it seems more people just want to be told what the next slonyId is
    // than what the next slonyId is for tables and schemas
    // make it so, number one
    /*
    $s = "NEXT table\tslonyId " . self::get_slony_next_table_id($doc) . "\t"
      . "sequence\tslonyId " . self::get_slony_next_sequence_id($doc);
    */
    $next_slony_id = $next_table_id = self::get_slony_next_table_id($doc);
    $next_sequence_id = self::get_slony_next_sequence_id($doc);
    if ($next_slony_id < $next_sequence_id) {
      $next_slony_id = $next_sequence_id;
    }

    $s = "NEXT ID = " . $next_slony_id;
    return $s;
  }

  public static function get_slony_next_table_id($doc) {
    $max_slony_id = 0;
    foreach ($doc->schema AS $schema) {
      foreach ($schema->table AS $table) {
        if (isset($table['slonyId'])
          && strcasecmp('IGNORE_REQUIRED', $table['slonyId']) != 0) {
          if (trim($table['slonyId']) > $max_slony_id) {
            $max_slony_id = trim($table['slonyId']);
          }
        }
      }
    }
    return $max_slony_id + 1;
  }

  public static function get_slony_next_sequence_id($doc) {
    $max_slony_id = 0;
    foreach ($doc->schema AS $schema) {
      foreach ($schema->table AS $table) {
        foreach ($table->column AS $column) {
          // serial column sequence slony configuration
          if (preg_match(pgsql8::PATTERN_SERIAL_COLUMN, $column['type']) > 0) {
            if (isset($column['slonyId'])
              && strcasecmp('IGNORE_REQUIRED', $column['slonyId']) != 0) {
              if (trim($column['slonyId']) > $max_slony_id) {
                $max_slony_id = trim($column['slonyId']);
              }
            }
          }
        }
      }
      foreach ($schema->sequence AS $sequence) {
        if (isset($sequence['slonyId']) && strcasecmp('IGNORE_REQUIRED', $sequence['slonyId']) != 0) {
          if (trim($sequence['slonyId']) > $max_slony_id) {
            $max_slony_id = trim($sequence['slonyId']);
          }
        }
      }
    }
    return $max_slony_id + 1;
  }

  public static function build_upgrade($old_output_prefix, $old_composite_file, $old_db_doc, $old_files, $new_output_prefix, $new_composite_file, $new_db_doc, $new_files) {
    // place the upgrade files with the new_files set
    $upgrade_prefix = $new_output_prefix . '_upgrade';

    // pgsql8_diff needs these to intelligently create SQL difference statements in dependency order
    dbsteward::info("Calculating old table foreign key dependency order..");
    pgsql8_diff::$old_table_dependency = xml_parser::table_dependency_order($old_db_doc);
    dbsteward::info("Calculating new table foreign key dependency order..");
    pgsql8_diff::$new_table_dependency = xml_parser::table_dependency_order($new_db_doc);

    pgsql8_diff::diff_doc($old_composite_file, $new_composite_file, $old_db_doc, $new_db_doc, $upgrade_prefix);

    if ( dbsteward::$generate_slonik ) {
      $replica_sets = pgsql8::get_slony_replica_sets($new_db_doc);
      foreach($replica_sets AS $replica_set) {
        dbsteward::info("Generating replica set " . $replica_set['id'] . " upgrade slonik");
        // separate upgrade slonik file sets for each replica set
        $slonik_upgrade_prefix = $upgrade_prefix . "_slony_replica_set_" . $replica_set['id'];
        // generate upgrade slonik to apply generated sql changes
        $old_new_slonik_header = "# Old definition:  " . implode(', ', $old_files) . "\n"
          . "# New definition:  " . implode(', ', $new_files) . "\n"
          . "# Replica set ID " . $replica_set['id'] . "\n";
        $old_replica_set = pgsql8::get_slony_replica_set($old_db_doc, (string)($replica_set['id']));
        pgsql8::build_upgrade_slonik_replica_set($old_db_doc, $new_db_doc, $old_replica_set, $replica_set, $slonik_upgrade_prefix, $old_new_slonik_header);
      }
    }

    return $new_db_doc;
  }
  
  public static function build_slonik_preamble($db_doc, $replica_set, $slony_preamble_file) {
    dbsteward::notice("Building slonik pramble for replica set ID " . $replica_set['id'] . " output file " . $slony_preamble_file);
    $timestamp = date('r');

    // all the other slonik file writers use mode a
    // have the preamble function to w to overwrite previous slonik command file sets
    // as the preamble is always the first thing in a slonik file
    $slony_preamble_fp = fopen($slony_preamble_file, 'w');
    if ($slony_preamble_fp === FALSE) {
      throw new exception("failed to open slony preamble output file " . $slony_preamble_file);
    }
    $slony_preamble_ofs = new output_file_segmenter($slony_preamble_file, 1, $slony_preamble_fp, $slony_preamble_file);
    $slony_preamble_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    // don't write the fixed file header file name. slony preamble must start with the CLUSTER NAME directive
    $slony_preamble_ofs->disable_fixed_file_header();
    
    if ( !isset($db_doc->database->slony->slonyNode) ) {
      $slony_preamble_ofs->write("DBSTEWARD: NO SLONY NODES DEFINED\n");
      return FALSE;
    }

    $slony_preamble_ofs->write("CLUSTER NAME = " . $db_doc->database->slony['clusterName'] . ";\n");

    // define the connection info for each node this replica set uses
    foreach($db_doc->database->slony->slonyNode AS $slony_node) {
      // the set uses this node, include it
      if ( pgsql8::slony_replica_set_uses_node($replica_set, $slony_node) ) {
        $slony_preamble_ofs->write(
          "NODE " . $slony_node['id'] . " ADMIN CONNINFO = '"
          . "dbname=" . $slony_node['dbName']
          . " host=" . $slony_node['dbHost']
          . " user=" . $slony_node['dbUser']
          . " password=" . $slony_node['dbPassword'] . "';\n"
        );
      }
    }

    $slony_preamble_ofs->write("\n");
    $slony_preamble_ofs->write("# " . $slony_preamble_file . "\n");
    $slony_preamble_ofs->write("# DBSteward slony preamble generated " . $timestamp . "\n");
    $slony_preamble_ofs->write("# Replica Set: " . $replica_set['id'] . "\n\n");
  }
  
  public static function build_slonik_store_nodes($db_doc, $replica_set, $slony_store_nodes_file) {
    dbsteward::notice("Building slonik STORE NODEs for replica set ID " . $replica_set['id'] . " output file " . $slony_store_nodes_file);
    $timestamp = date('r');

    $slony_paths_fp = fopen($slony_store_nodes_file, 'a');
    if ($slony_paths_fp === FALSE) {
      throw new exception("failed to open slony paths output file " . $slony_store_nodes_file);
    }
    $slony_paths_ofs = new output_file_segmenter($slony_store_nodes_file, 1, $slony_paths_fp, $slony_store_nodes_file);
    $slony_paths_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    // don't write the fixed file header file name. slony preamble must start with the CLUSTER NAME directive
    $slony_paths_ofs->disable_fixed_file_header();
    
    $slony_paths_ofs->write("# " . $slony_store_nodes_file . "\n");
    $slony_paths_ofs->write("# DBSteward slony store nodes generated " . $timestamp . "\n");
    $slony_paths_ofs->write("# Replica Set: " . $replica_set['id'] . "\n\n");
    
    if ( ! isset($db_doc->database->slony->slonyNode) ) {
      $slony_paths_ofs->write("DBSTEWARD: NO SLONY NODES DEFINED\n");
      return FALSE;
    }
    
    $origin_node = $replica_set['originNodeId'];
    $slony_paths_ofs->write("# Initialize Cluster with origin node for Replica Set " . $replica_set['id'] . "\n");
    $slony_paths_ofs->write(
          "INIT CLUSTER ( ID = " . $origin_node
          . ", COMMENT = '" . pgsql8::get_slony_replica_set_node_attribute($db_doc, $replica_set, $origin_node, 'comment') . "'"
          . " );\n\n");
    
    $slony_paths_ofs->write("# Store Cluster Nodes for Replica Set " . $replica_set['id'] . "\n");
    $node_ids = pgsql8::get_slony_replica_set_node_ids($replica_set);
    for($i = 0; $i < count($node_ids); $i++) {
      $node_i = $node_ids[$i];
      // don't STORE NODE the origin node, it was created during INIT CLUSTER
      if ( $node_i != $origin_node ) {
        $slony_paths_ofs->write(
          "STORE NODE ( EVENT NODE = " . $origin_node . ", ID = " . $node_i
          . ", COMMENT = '" . pgsql8::get_slony_replica_set_node_attribute($db_doc, $replica_set, $node_i, 'comment') . "'"
          . " );\n");
      }
    }

    $slony_paths_ofs->write("\n");
  }
  
  public static function build_slonik_paths($db_doc, $replica_set, $slony_paths_file) {
    dbsteward::notice("Building slonik STORE PATHs for replica set ID " . $replica_set['id'] . " output file " . $slony_paths_file);
    $timestamp = date('r');

    $slony_paths_fp = fopen($slony_paths_file, 'a');
    if ($slony_paths_fp === FALSE) {
      throw new exception("failed to open slony paths output file " . $slony_paths_file);
    }
    $slony_paths_ofs = new output_file_segmenter($slony_paths_file, 1, $slony_paths_fp, $slony_paths_file);
    $slony_paths_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    // don't write the fixed file header file name. slony preamble must start with the CLUSTER NAME directive
    $slony_paths_ofs->disable_fixed_file_header();
    
    $slony_paths_ofs->write("# " . $slony_paths_file . "\n");
    $slony_paths_ofs->write("# DBSteward slony paths generated " . $timestamp . "\n");
    $slony_paths_ofs->write("# Replica Set: " . $replica_set['id'] . "\n\n");
    
    if ( ! isset($db_doc->database->slony->slonyNode) ) {
      $slony_paths_ofs->write("DBSTEWARD: NO SLONY NODES DEFINED\n");
      return FALSE;
    }
    
    $node_ids = pgsql8::get_slony_replica_set_node_ids($replica_set);
      
    for($i = 0; $i < count($node_ids); $i++) {
      $node_i = $node_ids[$i];
      for($j = 0; $j < count($node_ids); $j++) {
        $node_j = $node_ids[$j];
        // if we are not talking about the same node for both server and client
        // write the path
        if ( $node_i != $node_j ) {
          $slony_paths_ofs->write("STORE PATH (SERVER = " . $node_j . ", CLIENT = " . $node_i
            . ", CONNINFO = '"
            . "dbname=" . pgsql8::get_slony_replica_set_node_attribute($db_doc, $replica_set, $node_j, 'dbName')
            . " host=" . pgsql8::get_slony_replica_set_node_attribute($db_doc, $replica_set, $node_j, 'dbHost')
            . " user=" . pgsql8::get_slony_replica_set_node_attribute($db_doc, $replica_set, $node_j, 'dbUser')
            . " password=" . pgsql8::get_slony_replica_set_node_attribute($db_doc, $replica_set, $node_j, 'dbPassword')
            . "');\n");
        }
      }
    }

    $slony_paths_ofs->write("\n");
  }

  public static function build_upgrade_slonik_replica_set($old_db_doc, $new_db_doc, $old_replica_set, $new_replica_set, $slonik_file_prefix, $origin_header = '') {
    dbsteward::notice("Building slonik upgrade replica set ID " . $new_replica_set['id']);
    $timestamp = date('r');

    // output preamble file standalone for tool chains that use the preamble to do additional slonik commands
    $slony_preamble_file = $slonik_file_prefix . '_preamble.slonik';
    pgsql8::build_slonik_preamble($new_db_doc, $new_replica_set, $slony_preamble_file);

    $slony_stage1_file = $slonik_file_prefix . '_stage1.slonik';
    pgsql8::build_slonik_preamble($new_db_doc, $new_replica_set, $slony_stage1_file);
    $slony_stage1_fp = fopen($slony_stage1_file, 'a');
    if ($slony_stage1_fp === FALSE) {
      throw new exception("failed to open upgrade slony stage 1 output file " . $slony_stage1_file);
    }
    $slony_stage1_ofs = new output_file_segmenter($slony_stage1_file, 1, $slony_stage1_fp, $slony_stage1_file);
    $slony_stage1_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    $slony_stage1_ofs->write("# DBSteward slony stage 1 upgrade generated " . $timestamp . "\n");
    $slony_stage1_ofs->write($origin_header . "\n");
    $slony_stage1_ofs->write("ECHO 'DBSteward slony upgrade replica set " . $new_replica_set['id'] . " stage 1 generated " . date('r') . "';\n\n");

    $slony_stage3_file = $slonik_file_prefix . '_stage3.slonik';
    pgsql8::build_slonik_preamble($new_db_doc, $new_replica_set, $slony_stage3_file);
    $slony_stage3_fp = fopen($slony_stage3_file, 'a');
    if ($slony_stage3_fp === FALSE) {
      throw new exception("failed to open upgrade slony stage 3 output file " . $slony_stage3_file . ' for output');
    }
    $slony_stage3_ofs = new output_file_segmenter($slony_stage3_file, 1, $slony_stage3_fp, $slony_stage3_file);
    $slony_stage3_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    $slony_stage3_ofs->write("# DBSteward slony stage 3 upgrade generated " . $timestamp . "\n");
    $slony_stage3_ofs->write($origin_header . "\n");
    $slony_stage3_ofs->write("ECHO 'DBSteward slony upgrade replica set " . $new_replica_set['id'] . " stage 3 generated " . date('r') . "';\n\n");

    // slony replication configuration changes
    // SLONY STAGE 1
    // unsubscribe to abandoned tables/sequences
    foreach ($old_db_doc->schema AS $old_schema) {
      // look for the schema in the new definition
      $new_schema = dbx::get_schema($new_db_doc, $old_schema['name']);

      // slony replicated tables that are no longer present
      foreach ($old_schema->table AS $old_table) {
        if ( ! pgsql8::slony_replica_set_contains_table($old_db_doc, $old_replica_set, $old_schema, $old_table) ) {
          // this old table is not contained in the old replica set being processed
          continue;
        }
        $new_table = NULL;
        if ( $new_schema ) {
          $new_table = dbx::get_table($new_schema, $old_table['name']);
        }

        if ($new_schema === NULL || $new_table === NULL) {
          // schema or table no longer exists
          // drop sequence subscriptions owned by the table
          foreach ($old_table->column AS $old_column) {
            // is a replicated type?
            if (preg_match(pgsql8::PATTERN_REPLICATED_COLUMN, $old_column['type']) > 0
              && isset($old_column['slonyId']) && strcasecmp('IGNORE_REQUIRED', $old_column['slonyId']) != 0) {
              $slony_stage1_ofs->write("# replicated table column " . $old_schema['name'] . '.' . $old_table['name'] . '.' . $old_column['name'] . " slonyId " . $old_table['slonyId'] . " no longer defined, dropping\n");
              $slony_stage1_ofs->write(sprintf(slony1_slonik::script_drop_sequence, dbsteward::string_cast($old_replica_set['originNodeId']), dbsteward::string_cast($old_column['slonyId'])) . "\n\n");
            }
          }

          if (isset($old_table['slonyId'])
            && strcasecmp('IGNORE_REQUIRED', $old_table['slonyId']) != 0) {
            // drop table subscription to the table
            $slony_stage1_ofs->write("# replicated table " . $old_schema['name'] . '.' . $old_table['name'] . " slonyId " . $old_table['slonyId'] . " no longer defined, dropping\n");
            $slony_stage1_ofs->write(sprintf(slony1_slonik::script_drop_table, dbsteward::string_cast($old_replica_set['originNodeId']), dbsteward::string_cast($old_table['slonyId'])) . "\n\n");
          }
        }
        if ($new_table !== NULL) {
          // table exists, look for replicated columns that have been abandoned or are no longer replicated types
          foreach ($old_table->column AS $old_column) {
            // it was previously a replicated column type?
            if (preg_match(pgsql8::PATTERN_REPLICATED_COLUMN, $old_column['type']) > 0) {
              $nodes = $new_table->xpath("column[@name='" . dbsteward::string_cast($old_column['name']) . "']");
              $new_column = NULL;
              if (count($nodes) == 1) {
                $new_column = $nodes[0];
                if (preg_match(pgsql8::PATTERN_REPLICATED_COLUMN, $new_column['type']) == 0) {
                  // not replicated type anymore
                  $new_column = NULL;
                }
              }

              if ($new_column === NULL
                && strcasecmp('IGNORE_REQUIRED', $old_column['slonyId']) != 0) {
                $slony_stage1_ofs->write(sprintf(slony1_slonik::script_drop_sequence, dbsteward::string_cast($old_replica_set['originNodeId']), dbsteward::string_cast($old_column['slonyId'])) . "\n\n");
              }
            }
          }
        }
      }

      // slony replicated stand-alone sequences that are no longer present
      foreach ($old_schema->sequence AS $old_sequence) {
        if ( ! static::slony_replica_set_contains_sequence($old_db_doc, $old_replica_set, $old_schema, $old_sequence) ) {
          // this old sequence is not contained in the old replica set being processed
          continue;
        }
        $new_sequence = NULL;
        if ($new_schema !== NULL) {
          $new_sequence = dbx::get_sequence($new_schema, $old_sequence['name']);
        }

        if (($new_schema === NULL || $new_sequence === NULL) && strcasecmp('IGNORE_REQUIRED', $old_sequence['slonyId']) != 0) {
          // schema or sequence no longer exists, drop the sequence subscription
          $slony_stage1_ofs->write(sprintf(slony1_slonik::script_drop_sequence, dbsteward::string_cast($old_replica_set['originNodeId']), dbsteward::string_cast($old_sequence['slonyId'])) . "\n\n");
        }
      }
    }

    $upgrade_set_created = FALSE;

    // SLONY STAGE 3
    // new table replication
    foreach ($new_db_doc->schema AS $new_schema) {
      // look for the schema in the old definition
      $old_schema = dbx::get_schema($old_db_doc, $new_schema['name']);

      // new tables that were not previously present
      // new replicated columns that were not previously present
      foreach ($new_schema->table AS $new_table) {
        // is this table replicated in this replica set?
        if ( pgsql8::slony_replica_set_contains_table($new_db_doc, $new_replica_set, $new_schema, $new_table) ) {
          
          if (!is_numeric(dbsteward::string_cast($new_table['slonyId']))) {
            throw new exception('table ' . $new_table['name'] . " slonyId " . $new_table['slonyId'] . " is not numeric");
          }
          if (in_array(dbsteward::string_cast($new_table['slonyId']), self::$table_slony_ids)) {
            throw new exception("table " . $new_table['name'] . " slonyId " . $new_table['slonyId'] . " already in table_slony_ids -- duplicates not allowed");
          }
          self::$table_slony_ids[] = dbsteward::string_cast($new_table['slonyId']);

          $old_table = NULL;
          if ( $old_schema ) {
            $old_table = dbx::get_table($old_schema, $new_table['name']);

            if ($old_table
             && isset($old_table['slonyId'])
             && strcasecmp('IGNORE_REQUIRED', $old_table['slonyId']) !== 0
             && strcasecmp('IGNORE_REQUIRED', $new_table['slonyId']) !== 0
             && (string)$new_table['slonyId'] != (string)$old_table['slonyId']) {
              throw new Exception("table slonyId {$new_table['slonyId']} in new does not match slonyId {$old_table['slonyId']} in old");
            }
          }

          if (($old_schema === NULL || $old_table === NULL) && strcasecmp('IGNORE_REQUIRED', $new_table['slonyId']) != 0) {
            // if it has not been declared, create the upgrade set to be merged
            if (!$upgrade_set_created) {
              self::create_slonik_upgrade_set($slony_stage3_ofs, $new_db_doc, $new_replica_set);
              $upgrade_set_created = TRUE;
            }

            // schema or table did not exist before, add it
            $slony_stage3_ofs->write(sprintf(slony1_slonik::script_add_table, dbsteward::string_cast($new_replica_set['upgradeSetId']), dbsteward::string_cast($new_replica_set['originNodeId']), dbsteward::string_cast($new_table['slonyId']), $new_schema['name'] . '.' . $new_table['name'], $new_schema['name'] . '.' . $new_table['name'] . ' table replication') . "\n\n");
          }
        }

        // add table owned sequence subscriptions for any not already present
        foreach ($new_table->column AS $new_column) {
          // is this column sequence replicated in this replica set?
          if ( pgsql8::slony_replica_set_contains_table_column_serial_sequence($new_db_doc, $new_replica_set, $new_schema, $new_table, $new_column) ) {

            self::check_duplicate_sequence_slony_id((string)$new_column['name'], 'column', (string)$new_column['slonyId']);
            self::set_sequence_slony_ids($new_column, $new_db_doc);

            // resolve $old_table on our own -- the table itself may not be replicated
            $old_table = NULL;
            if ( $old_schema ) {
              $old_table = dbx::get_table($old_schema, $new_table['name']);

              if ($old_table
               && isset($old_table['slonyId'])
               && strcasecmp('IGNORE_REQUIRED', $old_table['slonyId']) !== 0
               && strcasecmp('IGNORE_REQUIRED', $new_table['slonyId']) !== 0
               && (string)$new_table['slonyId'] != (string)$old_table['slonyId']) {
                throw new Exception("table slonyId {$new_table['slonyId']} in new does not match slonyId {$old_table['slonyId']} in old");
              }
            }

            // schema/table/column not present before
            $old_column = NULL;
            if ($old_table !== NULL) {
              $nodes = $old_table->xpath("column[@name='" . dbsteward::string_cast($new_column['name']) . "']");
              if (count($nodes) == 1) {
                // column is in new schema
                $old_column = $nodes[0];
              }
            }

            if ($old_column
             && isset($old_column['slonyId'])
             && strcasecmp('IGNORE_REQUIRED', $old_column['slonyId']) !== 0
             && strcasecmp('IGNORE_REQUIRED', $new_column['slonyId']) !== 0
             && (string)$new_column['slonyId'] != (string)$old_column['slonyId']) {
              throw new Exception("column sequence slonyId {$new_column['slonyId']} in new does not match slonyId {$old_column['slonyId']} in old");
            }

            if (($old_schema === NULL || $old_table === NULL || $old_column === NULL)
              && strcasecmp('IGNORE_REQUIRED', $new_column['slonyId']) != 0) {
              // if it has not been declared, create the upgrade set to be merged
              if (!$upgrade_set_created) {
                self::create_slonik_upgrade_set($slony_stage3_ofs, $new_db_doc, $new_replica_set);
                $upgrade_set_created = TRUE;
              }

              $col_sequence = pgsql8::identifier_name($new_schema['name'], $new_table['name'], $new_column['name'], '_seq');
              $slony_stage3_ofs->write(sprintf(slony1_slonik::script_add_sequence, dbsteward::string_cast($new_replica_set['upgradeSetId']), dbsteward::string_cast($new_replica_set['originNodeId']), dbsteward::string_cast($new_column['slonyId']), $new_schema['name'] . '.' . $col_sequence, $new_schema['name'] . '.' . $col_sequence . ' serial sequence column replication') . "\n\n");
            }
            
            // also check to make sure that additions of slonyIds in new XML
            // where the column did exist before also generates slonik changes
            if (($old_schema !== NULL && $old_table !== NULL && $old_column !== NULL)
                && strcasecmp('IGNORE_REQUIRED', $new_column['slonyId']) !== 0
                && !isset($old_column['slonyId'])) {
              if (!$upgrade_set_created) {
                self::create_slonik_upgrade_set($slony_stage3_ofs, $new_db_doc, $new_replica_set);
                $upgrade_set_created = TRUE;
              }

              $col_sequence = pgsql8::identifier_name($new_schema['name'], $new_table['name'], $new_column['name'], '_seq');
              $slony_stage3_ofs->write(sprintf(slony1_slonik::script_add_sequence, dbsteward::string_cast($new_replica_set['upgradeSetId']), dbsteward::string_cast($new_replica_set['originNodeId']), dbsteward::string_cast($new_column['slonyId']), $new_schema['name'] . '.' . $col_sequence, $new_schema['name'] . '.' . $col_sequence . ' serial sequence column replication') . "\n\n");              
              
            }
          }
        }
      }

      // new stand alone sequences not owned by tables that were not previously present
      foreach ($new_schema->sequence AS $new_sequence) {
        // is this sequence replicated in this replica set?
        if ( pgsql8::slony_replica_set_contains_sequence($new_db_doc, $new_replica_set, $new_schema, $new_sequence) ) {
          self::check_duplicate_sequence_slony_id((string)$new_sequence['name'], 'sequence', (string)$new_sequence['slonyId']);
          self::set_sequence_slony_ids($new_sequence, $new_db_doc);
        }

        $old_sequence = NULL;
        if ( $old_schema ) {
          $old_sequence = dbx::get_sequence($old_schema, $new_sequence['name']);
        }

        if ($old_sequence
         && isset($old_sequence['slonyId'])
         && strcasecmp('IGNORE_REQUIRED', $old_sequence['slonyId']) !== 0
         && strcasecmp('IGNORE_REQUIRED', $new_sequence['slonyId']) !== 0
         && (string)$new_sequence['slonyId'] != (string)$old_sequence['slonyId']) {
          throw new Exception("sequence slonyId {$new_sequence['slonyId']} in new does not match slonyId {$old_sequence['slonyId']} in old");
        }

        if (($old_schema === NULL || $old_sequence === NULL) && strcasecmp('IGNORE_REQUIRED', $new_sequence['slonyId']) != 0) {
          // if it has not been declared, create the upgrade set to be merged
          if (!$upgrade_set_created) {
            self::create_slonik_upgrade_set($slony_stage3_ofs, $new_db_doc, $new_replica_set);
            $upgrade_set_created = TRUE;
          }

          // sequence did not previously exist, add it
          $slony_stage3_ofs->write(sprintf(slony1_slonik::script_add_sequence, dbsteward::string_cast($new_replica_set['upgradeSetId']), dbsteward::string_cast($new_replica_set['originNodeId']), dbsteward::string_cast($new_sequence['slonyId']), $new_schema['name'] . '.' . $new_sequence['name'], $new_schema['name'] . '.' . $new_sequence['name'] . ' sequence replication') . "\n\n");
        }
      }
    }

    // if we created an upgrade set, subscribe and merge it
    if ($upgrade_set_created) {
      $slony_stage3_ofs->write("ECHO 'Waiting for merge set creation';\n");
      $slony_stage3_ofs->write(sprintf(
          slony1_slonik::script_node_sync_wait,
          $new_replica_set['originNodeId'],
          $new_replica_set['originNodeId'],
          $new_replica_set['originNodeId']
        ) . "\n\n");

      //
      foreach($new_replica_set->slonyReplicaSetNode AS $replica_node) {
        // subscribe replicaNode to its provider node providerId
        $slony_stage3_ofs->write("ECHO 'Subscribing replicaNode " . $replica_node['id'] . " to providerNodeId " . $replica_node['providerNodeId'] . " set ID " . $new_replica_set['upgradeSetId'] . "';\n");
        $slony_stage3_ofs->write(sprintf(
            slony1_slonik::script_subscribe_set,
            $new_replica_set['upgradeSetId'],
            $replica_node['providerNodeId'],
            $replica_node['id']
          ) . "\n\n");
        // do a sync and wait for it on the subscribing node
        $slony_stage3_ofs->write("ECHO 'Waiting for replicaNode " . $replica_node['id'] . " subscription to providerNodeId " . $replica_node['providerNodeId'] . " set ID " . $new_replica_set['upgradeSetId'] . "';\n");
        $slony_stage3_ofs->write(sprintf(
            slony1_slonik::script_node_sync_wait,
            $new_replica_set['originNodeId'],
            $new_replica_set['originNodeId'],
            $replica_node['id']
          ) . "\n\n");
      }

      // now we can merge the upgrade set to the main
      $slony_stage3_ofs->write("ECHO 'Merging replicationUpgradeSet " . $new_replica_set['upgradeSetId'] . " to set " . $new_replica_set['id'] . "';\n");
      $slony_stage3_ofs->write(sprintf(slony1_slonik::script_merge_set,
          $new_replica_set['id'],
          $new_replica_set['upgradeSetId'],
          $new_replica_set['originNodeId']
        ) . "\n\n");
    }
    
    // execute post-slony shaping SQL DDL / DCL commands at the end of stage 1 and 3 .slonik files
    $sql_stage1_file = $slonik_file_prefix . '_stage1_schema1.sql';
    // TODO: need to collect sql_stage1_file names from diff_doc() if there is 
    // more than one as a result of many changes between definition files
    $slony_stage1_ofs->write("ECHO 'DBSteward upgrade replica set " . $new_replica_set['id'] . " stage 1 SQL EXECUTE SCRIPT';\n");
    $slony_stage1_ofs->write("EXECUTE SCRIPT (
  FILENAME = '" . basename($sql_stage1_file) . "',
  EVENT NODE = " . $new_replica_set['originNodeId'] . "
);\n\n");
    $sql_stage3_file = $slonik_file_prefix . '_stage3_schema1.sql';
    $slony_stage3_ofs->write("ECHO 'DBSteward upgrade replica set " . $new_replica_set['id'] . " stage 3 SQL EXECUTE SCRIPT';\n");
    $slony_stage3_ofs->write("EXECUTE SCRIPT (
  FILENAME = '" . basename($sql_stage3_file) . "',
  EVENT NODE = " . $new_replica_set['originNodeId'] . "
);\n\n");
  }

  protected static function create_slonik_upgrade_set($ofs, $doc, $replica_set) {
    $ofs->write(sprintf(slony1_slonik::script_create_set, dbsteward::string_cast($replica_set['upgradeSetId']), dbsteward::string_cast($replica_set['originNodeId']), 'temp upgrade set') . "\n\n");
  }

  public static function slony_compare($files) {
    if (!is_array($files)) {
      $files = array($files);
    }
    $output_prefix = dirname($files[0]) . '/' . substr(basename($files[0]), 0, -4);

    $db_doc = xml_parser::xml_composite($output_prefix, $files, $slony_composite_file);

    $slony_compare_file = $output_prefix . '_slonycompare.sql';
    dbsteward::notice("Building slony comparison script " . $slony_compare_file);
    $slony_compare_file_fp = fopen($slony_compare_file, 'w');
    if ($slony_compare_file_fp === FALSE) {
      throw new exception("failed to open slony comparison script " . $slony_compare_file . ' for output');
    }
    $slony_compare_ofs = new output_file_segmenter($slony_compare_file, 1, $slony_compare_file_fp, $slony_compare_file);
    $slony_compare_ofs->write("-- slony comparison script generated " . date('r') . "\n");
    $slony_compare_ofs->write("-- source files: " . implode(', ', $files) . "\n\n");
    $slony_compare_ofs->write("-- Uniformly compare dates and timezones in UTC\n");
    $slony_compare_ofs->write("SET timezone='UTC';\n\n");

    foreach ($db_doc->schema AS $schema) {
      // select all table column data, in predictable order (via primary key sort)
      foreach ($schema->table AS $table) {
        $table_ident = $schema['name'] . '.' . $table['name'];
        if (isset($table['primaryKey'])
          && strlen($table['primaryKey']) > 0) {
          $order_by = "ORDER BY " . dbsteward::string_cast($table['primaryKey']);
        }
        else {
          throw new exception($table_ident . ' has no primary key, cannot create slony comparison script without it');
        }

        // analyze table columns
        $table_columns = '';
        foreach ($table->column AS $column) {
          // select any table column sequence values for comparison
          if (preg_match(pgsql8::PATTERN_SERIAL_COLUMN, $column['type']) > 0) {
            $sequence_name = pgsql8::identifier_name($schema['name'], $table['name'], $column['name'], '_seq');
            $sql = 'SELECT last_value FROM ' . $schema['name'] . '.' . $sequence_name . ';';
            $slony_compare_ofs->write($sql . "\n");
          }
          // explicitly name columns, so that column order is homogenized between replicas of any age/source
          $table_columns .= pgsql8::get_quoted_column_name($column['name']) . ', ';
        }
        $table_columns = substr($table_columns, 0, -2);

        $sql = 'SELECT ' . $table_columns . ' FROM ' . $table_ident . ' ' . $order_by . ';';

        $slony_compare_ofs->write($sql . "\n");
      }

      // select any standalone sequences' value for comparison
      foreach ($schema->sequence AS $sequence) {
        $sql = 'SELECT last_value FROM ' . $schema['name'] . '.' . $sequence['name'] . ';';
        $slony_compare_ofs->write($sql . "\n");
      }
    }

    return $db_doc;
  }

  public static function slony_diff($old_files, $new_files) {
    if (!is_array($old_files)) {
      $old_files = array($old_files);
    }
    $old_output_prefix = dirname($old_files[0]) . '/' . substr(basename($old_files[0]), 0, -4);
    if (!is_array($new_files)) {
      $new_files = array($new_files);
    }
    $new_output_prefix = dirname($new_files[0]) . '/' . substr(basename($new_files[0]), 0, -4);

    $old_db_doc = xml_parser::xml_composite($old_output_prefix, $old_files, $old_slony_composite_file);
    $new_db_doc = xml_parser::xml_composite($new_output_prefix, $new_files, $new_slony_composite_file);

    foreach ($old_db_doc->schema AS $old_schema) {
      $new_schema = dbx::get_schema($new_db_doc, $old_schema['name']);
      if (!$new_schema) {
        dbsteward::warning("new definition missing schema " . $old_schema['name']);
        continue 1;
      }
      foreach ($old_schema->table AS $old_table) {
        $new_table = dbx::get_table($new_schema, $old_table['name']);
        if (!$new_table) {
          dbsteward::warning("new definition missing table " . $old_schema['name'] . "." . $old_table['name']);
          continue 1;
        }
        if (strcmp($old_table['slonyId'], $new_table['slonyId']) != 0) {
          dbsteward::info("table " . $old_schema['name'] . "." . $old_table['name'] . "\told slonyId " . $old_table['slonyId'] . " new slonyId " . $new_table['slonyId']);
          continue 1;
        }
      }
      foreach ($old_schema->sequence AS $old_sequence) {
        $new_sequence = dbx::get_sequence($new_schema, $old_sequence['name']);
        if (!$new_sequence) {
          dbsteward::warning("new definition missing sequence " . $old_schema['name'] . "." . $old_sequence['name']);
          continue 1;
        }
        if (strcmp($old_sequence['slonyId'], $new_sequence['slonyId']) != 0) {
          dbsteward::info("sequence " . $old_schema['name'] . "." . $old_sequence['name'] . "\told slonyId " . $old_sequence['slonyId'] . " new slonyId " . $new_sequence['slonyId']);
          continue 1;
        }
      }
    }
  }

  /**
   * diff the xml files on disk to create an upgrade sql that gets us from a to b
   *
   */
  public static function sql_diff($old, $new, $upgrade_prefix) {
    if (!is_array($old)) {
      $old = array($old);
    }
    if (!is_array($new)) {
      $new = array($new);
    }
    dbsteward::notice("Calculating sql differences:");
    dbsteward::notice("Old set:  " . implode(', ', $old));
    dbsteward::notice("New set:  " . implode(', ', $new));
    dbsteward::notice("Upgrade:  " . $upgrade_prefix);

    return pgsql8_diff::diff_sql($old, $new, $upgrade_prefix);
  }

  /**
   * extract db schema from pg_catalog
   * based on http://www.postgresql.org/docs/8.3/static/catalogs.html documentation
   *
   * @return string pulled db schema from database, in dbsteward format
   */
  public static function extract_schema($host, $port, $database, $user, $password) {
    // serials that are implicitly created as part of a table, no need to explicitly create these
    $table_serials = array();

    dbsteward::notice("Connecting to pgsql8 host " . $host . ':' . $port . ' database ' . $database . ' as ' . $user);
    // if not supplied, ask for the password
    if ($password === FALSE) {
      // @TODO: mask the password somehow without requiring a PHP extension
      echo "Password: ";
      $password = fgets(STDIN);
    }

    pgsql8_db::connect("host=$host port=$port dbname=$database user=$user password=$password");

    $doc = new SimpleXMLElement('<dbsteward></dbsteward>');
    // set the document to contain the passed db host, name, etc to meet the DTD and for reference
    $node_database = $doc->addChild('database');
    $node_database->addChild('sqlformat', 'pgsql8');
    $node_role = $node_database->addChild('role');
    $node_role->addChild('application', $user);
    $node_role->addChild('owner', $user);
    $node_role->addChild('replication', $user);
    $node_role->addChild('readonly', $user);

    // find all tables in the schema that aren't in the built-in schemas
    $sql = "SELECT t.schemaname, t.tablename, t.tableowner, t.tablespace,
                   sd.description as schema_description, td.description as table_description,
                   ( SELECT array_agg(cd.objsubid::text || ';' ||cd.description)
                     FROM pg_catalog.pg_description cd
                     WHERE cd.objoid = c.oid AND cd.classoid = c.tableoid AND cd.objsubid > 0 ) AS column_descriptions
            FROM pg_catalog.pg_tables t
            LEFT JOIN pg_catalog.pg_namespace n ON (n.nspname = t.schemaname)
            LEFT JOIN pg_catalog.pg_class c ON (c.relname = t.tablename AND c.relnamespace = n.oid)
            LEFT JOIN pg_catalog.pg_description td ON (td.objoid = c.oid AND td.classoid = c.tableoid AND td.objsubid = 0)
            LEFT JOIN pg_catalog.pg_description sd ON (sd.objoid = n.oid)
            WHERE schemaname NOT IN ('information_schema', 'pg_catalog')
            ORDER BY schemaname, tablename;";
    $rs = pgsql8_db::query($sql);
    $sequence_cols = array();
    while (($row = pg_fetch_assoc($rs)) !== FALSE) {

      dbsteward::info("Analyze table options " . $row['schemaname'] . "." . $row['tablename']);

      // schemaname     |        tablename        | tableowner | tablespace | hasindexes | hasrules | hastriggers
      // create the schema if it is missing
      $nodes = $doc->xpath("schema[@name='" . $row['schemaname'] . "']");
      if (count($nodes) == 0) {
        $node_schema = $doc->addChild('schema');
        $node_schema['name'] = $row['schemaname'];

        $sql = "SELECT schema_owner FROM information_schema.schemata WHERE schema_name = '" . $row['schemaname'] . "'";
        $schema_owner = pgsql8_db::query_str($sql);
        $node_schema['owner'] = self::translate_role_name($schema_owner);

        if ($row['schema_description']) {
          $node_schema['description'] = $row['schema_description'];
        }
      }
      else {
        $node_schema = $nodes[0];
      }

      // create the table in the schema space
      $nodes = $node_schema->xpath("table[@name='" . $row['tablename'] . "']");
      if (count($nodes) == 0) {
        $node_table = $node_schema->addChild('table');
        $node_table['name'] = $row['tablename'];
        $node_table['owner'] = self::translate_role_name($row['tableowner']);
        $node_table['description'] = $row['table_description'];

        // extract tablespace as a tableOption
        if (!empty($row['tablespace'])) {
          $node_option = $node_table->addChild('tableOption');
          $node_option->addAttribute('sqlFormat', 'pgsql8');
          $node_option->addAttribute('name', 'tablespace');
          $node_option->addAttribute('value', $row['tablespace']);
        }

        // extract storage parameters as a tableOption
        $sql = "SELECT reloptions, relhasoids
          FROM pg_catalog.pg_class
          WHERE relname = '".$node_table['name']."' AND relnamespace = (
            SELECT oid FROM pg_catalog.pg_namespace WHERE nspname = '".$node_schema['name']."')";
        $params_rs = pgsql8_db::query($sql);
        $params_row = pg_fetch_assoc($params_rs);
        $params = array();
        if (!empty($params_row['reloptions'])) {
          // reloptions is formatted as {name=value,name=value}
          $params = explode(',', substr($params_row['reloptions'],1,-1));
        }
        $params[] = "oids=" . (strcasecmp('t',$params_row['relhasoids']) === 0 ? 'true' : 'false');

        $node_option = $node_table->addChild('tableOption');
        $node_option->addAttribute('sqlFormat', 'pgsql8');
        $node_option->addAttribute('name', 'with');
        $node_option->addAttribute('value', '('.implode(',',$params).')');

        dbsteward::info("Analyze table columns " . $row['schemaname'] . "." . $row['tablename']);

        $column_descriptions_raw = self::parse_sql_array($row['column_descriptions']);
        $column_descriptions = array();
        foreach ($column_descriptions_raw as $desc) {
          list($idx, $description) = explode(';', $desc, 2);
          $column_descriptions[$idx] = $description;
        }

        //hasindexes | hasrules | hastriggers  handled later
        // get columns for the table
        $sql = "SELECT
            column_name, data_type,
            column_default, is_nullable,
            ordinal_position, numeric_precision,
            format_type(atttypid, atttypmod) as attribute_data_type
          FROM information_schema.columns
            JOIN pg_class pgc ON (pgc.relname = table_name AND pgc.relkind='r')
            JOIN pg_namespace nsp ON (nsp.nspname = table_schema AND nsp.oid = pgc.relnamespace)
            JOIN pg_attribute pga ON (pga.attrelid = pgc.oid AND columns.column_name = pga.attname)
          WHERE table_schema='" . $node_schema['name'] . "' AND table_name='" . $node_table['name'] . "'
            AND attnum > 0
            AND NOT attisdropped";
        $col_rs = pgsql8_db::query($sql);

        while (($col_row = pg_fetch_assoc($col_rs)) !== FALSE) {
          $node_column = $node_table->addChild('column');
          $node_column->addAttribute('name', $col_row['column_name']);

          if (array_key_exists($col_row['ordinal_position'], $column_descriptions)) {
            $node_column['description'] = $column_descriptions[$col_row['ordinal_position']];
          }

          // look for serial columns that are primary keys and collapse them down from integers with sequence defualts into serials
          // type int or bigint
          // is_nullable = NO
          // column_default starts with nextval and contains iq_seq
          if ((strcasecmp('integer', $col_row['attribute_data_type']) == 0 || strcasecmp('bigint', $col_row['attribute_data_type']) == 0)
            && strcasecmp($col_row['is_nullable'], 'NO') == 0
            && (stripos($col_row['column_default'], 'nextval') === 0 && stripos($col_row['column_default'], '_seq') !== FALSE)) {
            $col_type = 'serial';
            if (strcasecmp('bigint', $col_row['attribute_data_type']) == 0) {
              $col_type = 'bigserial';
            }
            $node_column->addAttribute('type', $col_type);

            // store sequences that will be implicitly genreated during table create
            // could use pgsql8::identifier_name and fully qualify the table but it will just truncate "for us" anyhow, so manually prepend schema
            $identifier_name = $node_schema['name'] . '.' . pgsql8::identifier_name($node_schema['name'], $node_table['name'], $col_row['column_name'], '_seq');
            $table_serials[] = $identifier_name;

            $seq_name = explode("'", $col_row['column_default']);
            $sequence_cols[] = $seq_name[1];
          }
          // not serial column
          else {
            $col_type = $col_row['attribute_data_type'];
            $node_column->addAttribute('type', $col_type);
            if (strcasecmp($col_row['is_nullable'], 'NO') == 0) {
              $node_column->addAttribute('null', 'false');
            }
            if (strlen($col_row['column_default']) > 0) {
              $node_column->addAttribute('default', $col_row['column_default']);
            }
          }
        }

        dbsteward::info("Analyze table indexes " . $row['schemaname'] . "." . $row['tablename']);
        // get table INDEXs
        $sql = "SELECT ic.relname, i.indisunique, (
                  -- get the n'th dimension's definition
                  SELECT array_agg(pg_catalog.pg_get_indexdef(i.indexrelid, n, true))
                  FROM generate_series(1, i.indnatts) AS n
                ) AS dimensions
                FROM pg_index i
                LEFT JOIN pg_class ic ON ic.oid = i.indexrelid
                LEFT JOIN pg_class tc ON tc.oid = i.indrelid
                LEFT JOIN pg_catalog.pg_namespace n ON n.oid = tc.relnamespace
                WHERE tc.relname = '{$node_table['name']}'
                  AND n.nspname = '{$node_schema['name']}'
                  AND i.indisprimary != 't'
                  AND ic.relname NOT IN (
                    SELECT constraint_name
                    FROM information_schema.table_constraints
                    WHERE table_schema = '{$node_schema['name']}'
                      AND table_name = '{$node_table['name']}');";
        $index_rs = pgsql8_db::query($sql);
        while (($index_row = pg_fetch_assoc($index_rs)) !== FALSE) {
          $dimensions = self::parse_sql_array($index_row['dimensions']);

          // only add a unique index if the column was
          $index_name = $index_row['relname'];
          $node_index = $node_table->addChild('index');
          $node_index->addAttribute('name', $index_name);
          $node_index->addAttribute('using', 'btree');
          $node_index->addAttribute('unique', $index_row['indisunique']=='t'?'true':'false');
          $dim_i = 1;
          foreach ($dimensions as $dim) {
            $node_index->addChild('indexDimension', $dim)
              ->addAttribute('name', $index_name . '_' . $dim_i++);
          }
        }
      }
      else {
        // complain if it is found, it should have been
        throw new exception("table " . $row['schemaname'] . '.' . $row['tablename'] . " already defined in XML object -- unexpected");
      }
    }

    $schemas = &dbx::get_schemas($doc);
    foreach ($sequence_cols as $idx => $seq_col) {
      $seq_col = "'" . $seq_col . "'";
      $sequence_cols[$idx] = $seq_col;
    }
    $sequence_str = implode(',', $sequence_cols);

    foreach ($schemas as $schema) {
      dbsteward::info("Analyze isolated sequences in schema " . $schema['name']);
      // filter by sequences we've defined as part of a table already
      // and get the owner of each sequence
      $seq_list_sql = "
        SELECT s.relname, r.rolname
          FROM pg_statio_all_sequences s
          JOIN pg_class c ON (s.relname = c.relname)
          JOIN pg_roles r ON (c.relowner = r.oid)
          WHERE schemaname = '" . $schema['name'] . "'"; //. " AND s.relname NOT IN (" . $sequence_str. ");";
      if (strlen($sequence_str) > 0) {
        $seq_list_sql .=  " AND s.relname NOT IN (" . $sequence_str . ")";
      }

      $seq_list_sql .= " GROUP BY s.relname, r.rolname;";
      $seq_list_rs = pgsql8_db::query($seq_list_sql);

      while (($seq_list_row = pg_fetch_assoc($seq_list_rs)) !== FALSE) {
        $seq_sql = "SELECT cache_value, start_value, min_value, max_value,
                    increment_by, is_cycled FROM \"" . $schema['name'] . "\"." . $seq_list_row['relname'] . ";";
        $seq_rs = pgsql8_db::query($seq_sql);
        while (($seq_row = pg_fetch_assoc($seq_rs)) !== FALSE) {
          $nodes = $schema->xpath("sequence[@name='" . $seq_list_row['relname'] . "']");
          if (count($nodes) == 0) {
            // is sequence being implictly generated? If so skip it  
            if (in_array($schema['name'] . '.' . $seq_list_row['relname'], $table_serials)) {
              continue;
            }

            $node_sequence = $schema->addChild('sequence');
            $node_sequence->addAttribute('name', $seq_list_row['relname']);
            $node_sequence->addAttribute('owner', $seq_list_row['rolname']);
            $node_sequence->addAttribute('cache', $seq_row['cache_value']);
            $node_sequence->addAttribute('start', $seq_row['start_value']);
            $node_sequence->addAttribute('min', $seq_row['min_value']);
            $node_sequence->addAttribute('max', $seq_row['max_value']);
            $node_sequence->addAttribute('inc', $seq_row['increment_by']);
            $node_sequence->addAttribute('cycle', $seq_row['is_cycled'] === 't' ? 'true' : 'false');
          }
        }
      }
    }

    // extract views
    $sql = "SELECT *
      FROM pg_catalog.pg_views
      WHERE schemaname NOT IN ('information_schema', 'pg_catalog')
      ORDER BY schemaname, viewname;";
    $rc_views = pgsql8_db::query($sql);
    while (($view_row = pg_fetch_assoc($rc_views)) !== FALSE) {
      dbsteward::info("Analyze view " . $view_row['schemaname'] . "." . $view_row['viewname']);

      // create the schema if it is missing
      $nodes = $doc->xpath("schema[@name='" . $view_row['schemaname'] . "']");
      if (count($nodes) == 0) {
        $node_schema = $doc->addChild('schema');
        $node_schema->addAttribute('name', $view_row['schemaname']);
        $sql = "SELECT schema_owner FROM information_schema.schemata WHERE schema_name = '" . $view_row['schemaname'] . "'";
        $schema_owner = pgsql8_db::query_str($sql);
        $node_schema->addAttribute('owner', self::translate_role_name($schema_owner));
      }
      else {
        $node_schema = $nodes[0];
      }

      $nodes = $node_schema->xpath("view[@name='" . $view_row['viewname'] . "']");
      if (count($nodes) !== 0) {
        throw new exception("view " . $view_row['schemaname'] . "." . $view_row['viewname'] . " already defined in XML object -- unexpected");
      }

      $node_view = $node_schema->addChild('view');
      $node_view->addAttribute('name', $view_row['viewname']);
      $node_view->addAttribute('owner', self::translate_role_name($view_row['viewowner']));
      $node_query = $node_view->addChild('viewQuery', $view_row['definition']);
      $node_query->addAttribute('sqlFormat', 'pgsql8');
    }

    // for all schemas, all tables - get table constraints that are not type 'FOREIGN KEY'
    dbsteward::info("Analyze table constraints " . $row['schemaname'] . "." . $row['tablename']);
    $sql = "SELECT constraint_name, constraint_type, table_schema, table_name, array_agg(columns) AS columns
      FROM (
      SELECT tc.constraint_name, tc.constraint_type, tc.table_schema, tc.table_name, kcu.column_name::text AS columns
      FROM information_schema.table_constraints tc
      LEFT JOIN information_schema.key_column_usage kcu ON tc.constraint_catalog = kcu.constraint_catalog AND tc.constraint_schema = kcu.constraint_schema AND tc.constraint_name = kcu.constraint_name
      WHERE tc.table_schema NOT IN ('information_schema', 'pg_catalog')
        AND tc.constraint_type != 'FOREIGN KEY'
      GROUP BY tc.constraint_name, tc.constraint_type, tc.table_schema, tc.table_name, kcu.column_name
      ORDER BY kcu.column_name, tc.table_schema, tc.table_name) AS results
      GROUP BY results.constraint_name, results.constraint_type, results.table_schema, results.table_name;";
    $rc_constraint = pgsql8_db::query($sql);
    while (($constraint_row = pg_fetch_assoc($rc_constraint)) !== FALSE) {
      $nodes = $doc->xpath("schema[@name='" . $constraint_row['table_schema'] . "']");
      if (count($nodes) != 1) {
        throw new exception("failed to find constraint analysis schema '" . $constraint_row['table_schema'] . "'");
      }
      else {
        $node_schema = $nodes[0];
      }

      $nodes = $node_schema->xpath("table[@name='" . $constraint_row['table_name'] . "']");
      if (count($nodes) != 1) {
        throw new exception("failed to find constraint analysis table " . $constraint_row['table_schema'] . " table '" . $constraint_row['table_name'] . "'");
      }
      else {
        $node_table = $nodes[0];
      }

      $column_names = self::parse_sql_array($constraint_row['columns']);

      if (strcasecmp('PRIMARY KEY', $constraint_row['constraint_type']) == 0) {
        $node_table['primaryKey'] = implode(', ', $column_names);

        $node_table['primaryKeyName'] = $constraint_row['constraint_name'];
      }
      else if (strcasecmp('UNIQUE', $constraint_row['constraint_type']) == 0) {
        $node_constraint = $node_table->addChild('constraint');
        $node_constraint['name'] = $constraint_row['constraint_name'];
        $node_constraint['type'] = 'UNIQUE';
        $node_constraint['definition'] = '("' . implode('", "', $column_names) . '")';
      }
      else if (strcasecmp('CHECK', $constraint_row['constraint_type']) == 0) {
        // @TODO: implement CHECK constraints
      }
      else {
        throw new exception("unknown constraint_type " . $constraint_row['constraint_type']);
      }
    }

    // We cannot accurately retrieve FOREIGN KEYs via information_schema
    // We must rely on getting them from pg_catalog instead
    // See http://stackoverflow.com/questions/1152260/postgres-sql-to-list-table-foreign-keys
    $sql = "SELECT con.constraint_name, con.update_rule, con.delete_rule,
                   lns.nspname AS local_schema, lt_cl.relname AS local_table, array_to_string(array_agg(lc_att.attname), ' ') AS local_columns,
                   fns.nspname AS foreign_schema, ft_cl.relname AS foreign_table, array_to_string(array_agg(fc_att.attname), ' ') AS foreign_columns
            FROM
              -- get column mappings
              (SELECT local_constraint.conrelid AS local_table, unnest(local_constraint.conkey) AS local_col,
                      local_constraint.confrelid AS foreign_table, unnest(local_constraint.confkey) AS foreign_col,
                      local_constraint.conname AS constraint_name, local_constraint.confupdtype AS update_rule, local_constraint.confdeltype as delete_rule
               FROM pg_class cl
               INNER JOIN pg_namespace ns ON cl.relnamespace = ns.oid
               INNER JOIN pg_constraint local_constraint ON local_constraint.conrelid = cl.oid
               WHERE ns.nspname NOT IN ('pg_catalog','information_schema')
                 AND local_constraint.contype = 'f'
              ) con
            INNER JOIN pg_class lt_cl ON lt_cl.oid = con.local_table
            INNER JOIN pg_namespace lns ON lns.oid = lt_cl.relnamespace
            INNER JOIN pg_attribute lc_att ON lc_att.attrelid = con.local_table AND lc_att.attnum = con.local_col
            INNER JOIN pg_class ft_cl ON ft_cl.oid = con.foreign_table
            INNER JOIN pg_namespace fns ON fns.oid = ft_cl.relnamespace
            INNER JOIN pg_attribute fc_att ON fc_att.attrelid = con.foreign_table AND fc_att.attnum = con.foreign_col
            GROUP BY con.constraint_name, lns.nspname, lt_cl.relname, fns.nspname, ft_cl.relname, con.update_rule, con.delete_rule;";
    $rc_fk = pgsql8_db::query($sql);
    $rules = array(
      'a' => 'NO_ACTION',
      'r' => 'RESTRICT',
      'c' => 'CASCADE',
      'n' => 'SET_NULL',
      'd' => 'SET_DEFAULT'
    );
    while (($fk_row = pg_fetch_assoc($rc_fk)) !== FALSE) {
      $local_cols = explode(' ', $fk_row['local_columns']);
      $foreign_cols = explode(' ', $fk_row['foreign_columns']);

      if (count($local_cols) != count($foreign_cols)) {
        throw new Exception(sprintf("Unexpected: Foreign key columns (%s) on %s.%s are mismatched with columns (%s) on %s.%s",
          implode(', ', $local_cols),
          $fk_row['local_schema'], $fk_row['local_table'],
          implode(', ', $foreign_cols),
          $fk_row['foreign_schema'], $fk_row['foreign_table']));
      }

      $nodes = $doc->xpath("schema[@name='" . $fk_row['local_schema'] . "']");
      if (count($nodes) != 1) {
        throw new exception("failed to find constraint analysis schema '" . $fk_row['local_schema'] . "'");
      }
      else {
        $node_schema = $nodes[0];
      }

      $nodes = $node_schema->xpath("table[@name='" . $fk_row['local_table'] . "']");
      if (count($nodes) != 1) {
        throw new exception("failed to find constraint analysis table " . $fk_row['local_schema'] . " table '" . $fk_row['local_table'] . "'");
      }
      else {
        $node_table = $nodes[0];
      }

      if (count($local_cols) === 1) {
        // inline on column

        $nodes = $node_table->xpath("column[@name='" . $local_cols[0] . "']");
        if (strlen($local_cols[0]) > 0) {
          if (count($nodes) != 1) {
            throw new exception("failed to find constraint analysis column " . $fk_row['local_schema'] . " table '" . $fk_row['local_table'] . "' column '" . $local_cols[0]);
          }
          else {
            $node_column = $nodes[0];
          }
        }

        $node_column['foreignSchema'] = $fk_row['foreign_schema'];
        $node_column['foreignTable'] = $fk_row['foreign_table'];
        $node_column['foreignColumn'] = $foreign_cols[0];
        $node_column['foreignKeyName'] = $fk_row['constraint_name'];
        $node_column['foreignOnUpdate'] = $rules[$fk_row['update_rule']];
        $node_column['foreignOnDelete'] = $rules[$fk_row['delete_rule']];

        // dbsteward fkey columns aren't supposed to specify a type, they will determine it from the foreign reference
        unset($node_column['type']);
      }
      elseif (count($local_cols) > 1) {
        $node_fkey = $node_table->addChild('foreignKey');
        $node_fkey['columns'] = implode(', ', $local_cols);
        $node_fkey['foreignSchema'] = $fk_row['foreign_schema'];
        $node_fkey['foreignTable'] = $fk_row['foreign_table'];
        $node_fkey['foreignColumns'] = implode(', ', $foreign_cols);
        $node_fkey['constraintName'] = $fk_row['constraint_name'];
        $node_fkey['onUpdate'] = $rules[$fk_row['update_rule']];
        $node_fkey['onDelete'] = $rules[$fk_row['delete_rule']];
      }
    }


    // get function info for all functions
    // this is based on psql 8.4's \df+ query
    // that are not language c
    // that are not triggers
    $sql = "SELECT p.oid, n.nspname as schema, p.proname as name,
       pg_catalog.pg_get_function_result(p.oid) as return_type,
       CASE
         WHEN p.proisagg THEN 'agg'
         WHEN p.proiswindow THEN 'window'
         WHEN p.prorettype = 'pg_catalog.trigger'::pg_catalog.regtype THEN 'trigger'
         ELSE 'normal'
       END as type,
       CASE
         WHEN p.provolatile = 'i' THEN 'IMMUTABLE'
         WHEN p.provolatile = 's' THEN 'STABLE'
         WHEN p.provolatile = 'v' THEN 'VOLATILE'
       END as volatility,
       pg_catalog.pg_get_userbyid(p.proowner) as owner,
       l.lanname as language,
       p.prosrc as source,
       pg_catalog.obj_description(p.oid, 'pg_proc') as description
FROM pg_catalog.pg_proc p
LEFT JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace
LEFT JOIN pg_catalog.pg_language l ON l.oid = p.prolang
WHERE n.nspname NOT IN ('pg_catalog', 'information_schema')
  AND l.lanname NOT IN ( 'c' )
  AND pg_catalog.pg_get_function_result(p.oid) NOT IN ( 'trigger' );";
    $rs_functions = pgsql8_db::query($sql);
    while (($row_fxn = pg_fetch_assoc($rs_functions)) !== FALSE) {
      dbsteward::info("Analyze function " . $row_fxn['schema'] . "." . $row_fxn['name']);
      $node_schema = dbx::get_schema($doc, $row_fxn['schema'], TRUE);
      if ( !isset($node_schema['owner']) ) {
        $sql = "SELECT schema_owner FROM information_schema.schemata WHERE schema_name = '" . $row_fxn['schema'] . "'";
        $schema_owner = pgsql8_db::query_str($sql);
        $node_schema->addAttribute('owner', self::translate_role_name($schema_owner));
      }
      if ( !$node_schema ) {
        throw new exception("failed to find function schema " . $row_fxn['schema']);
      }

      $node_function = $node_schema->addChild('function');
      $node_function['name'] = $row_fxn['name'];

      // unnest the proargtypes (which are in ordinal order) and get the correct format for them.
      // information_schema.parameters does not contain enough information to get correct type (e.g. ARRAY)
      //   Note: * proargnames can be empty (not null) if there are no parameters names
      //         * proargnames will contain empty strings for unnamed parameters if there are other named
      //                       parameters, e.g. {"", parameter_name}       
      //         * proargtypes is an oidvector, enjoy the hackery to deal with NULL proargnames
      //         * proallargtypes is NULL when all arguments are IN.
      $sql = "SELECT UNNEST(COALESCE(proargnames, ARRAY_FILL(''::text, ARRAY[(SELECT COUNT(*) FROM UNNEST(COALESCE(proallargtypes, proargtypes)))]::int[]))) as parameter_name,
                     FORMAT_TYPE(UNNEST(COALESCE(proallargtypes, proargtypes)), NULL) AS data_type
              FROM pg_proc pr
              WHERE oid = {$row_fxn['oid']}";
      $rs_args = pgsql8_db::query($sql);
      while (($row_arg = pg_fetch_assoc($rs_args)) !== FALSE) {
        $node_param = $node_function->addChild('functionParameter');
        if (!empty($row_arg['parameter_name'])) {
          $node_param['name'] = $row_arg['parameter_name'];
        }
        $node_param['type'] = $row_arg['data_type'];
      }

      $node_function['returns'] = $row_fxn['return_type'];
      $node_function['cachePolicy'] = $row_fxn['volatility'];
      $node_function['owner'] = self::translate_role_name($row_fxn['owner']);
      // @TODO: how is / figure out how to express securityDefiner attribute in the functions query
      $node_function['description'] = $row_fxn['description'];
      $node_definition = $node_function->addChild('functionDefinition', xml_parser::ampersand_magic($row_fxn['source']));
      $node_definition['language'] = $row_fxn['language'];
      $node_definition['sqlFormat'] = 'pgsql8';
    }


    // specify any user triggers we can find in the information_schema.triggers view
    $sql = "SELECT *
      FROM information_schema.triggers
      WHERE trigger_schema NOT IN ('pg_catalog', 'information_schema');";
    $rc_trigger = pgsql8_db::query($sql);
    while (($row_trigger = pg_fetch_assoc($rc_trigger)) !== FALSE) {
      dbsteward::info("Analyze trigger " . $row_trigger['event_object_schema'] . "." . $row_trigger['trigger_name']);
      $nodes = $doc->xpath("schema[@name='" . $row_trigger['event_object_schema'] . "']");
      if (count($nodes) != 1) {
        throw new exception("failed to find trigger schema '" . $row_trigger['event_object_schema'] . "'");
      }
      else {
        $node_schema = $nodes[0];
      }

      $nodes = $node_schema->xpath("table[@name='" . $row_trigger['event_object_table'] . "']");
      if (count($nodes) != 1) {
        throw new exception("failed to find trigger schema " . $row_trigger['event_object_schema'] . " table '" . $row_trigger['event_object_table'] . "'");
      }
      else {
        $node_table = $nodes[0];
      }

      // there is a row for each event_manipulation, so we need to aggregate them, see if the trigger already exists
      $nodes = $node_schema->xpath("trigger[@name='{$row_trigger['trigger_name']}' and @table='{$row_trigger['event_object_table']}']");
      if (count($nodes) == 0) {
        $node_trigger = $node_schema->addChild('trigger');
        $node_trigger->addAttribute('name', dbsteward::string_cast($row_trigger['trigger_name']));
        $node_trigger['event'] = dbsteward::string_cast($row_trigger['event_manipulation']);
        $node_trigger['sqlFormat'] = 'pgsql8';
      }
      else {
        $node_trigger = $nodes[0];
        // add to the event if the trigger already exists
        $node_trigger['event'] .= ', ' . dbsteward::string_cast($row_trigger['event_manipulation']);
      }

      if (isset($row_trigger['condition_timing'])) {
        $when = $row_trigger['condition_timing'];
      }
      else {
        $when = $row_trigger['action_timing'];
      }
      $node_trigger['when'] = dbsteward::string_cast($when);
      $node_trigger['table'] = dbsteward::string_cast($row_trigger['event_object_table']);
      $node_trigger['forEach'] = dbsteward::string_cast($row_trigger['action_orientation']);
      $trigger_function = trim(str_ireplace('EXECUTE PROCEDURE', '', $row_trigger['action_statement']));
      $node_trigger['function'] = dbsteward::string_cast($trigger_function);
    }


    // find table grants and save them in the xml document
    dbsteward::info("Analyze table permissions ");
    $sql = "SELECT *
      FROM information_schema.table_privileges
      WHERE table_schema NOT IN ('pg_catalog', 'information_schema');";
    $rc_grant = pgsql8_db::query($sql);
    while (($row_grant = pg_fetch_assoc($rc_grant)) !== FALSE) {
      $nodes = $doc->xpath("schema[@name='" . $row_grant['table_schema'] . "']");
      if (count($nodes) != 1) {
        throw new exception("failed to find grant schema '" . $row_grant['table_schema'] . "'");
      }
      else {
        $node_schema = $nodes[0];
      }

      $nodes = $node_schema->xpath("(table|view)[@name='" . $row_grant['table_name'] . "']");
      if (count($nodes) != 1) {
        throw new exception("failed to find grant schema " . $row_grant['table_schema'] . " table '" . $row_grant['table_name'] . "'");
      }
      else {
        $node_table = $nodes[0];
      }

      // aggregate privileges by role
      $nodes = $node_table->xpath("grant[@role='" . self::translate_role_name(dbsteward::string_cast($row_grant['grantee'])) . "']");
      if (count($nodes) == 0) {
        $node_grant = $node_table->addChild('grant');
        $node_grant->addAttribute('role', self::translate_role_name(dbsteward::string_cast($row_grant['grantee'])));
        $node_grant->addAttribute('operation', dbsteward::string_cast($row_grant['privilege_type']));
      }
      else {
        $node_grant = $nodes[0];
        // add to the when if the trigger already exists
        $node_grant['operation'] .= ', ' . dbsteward::string_cast($row_grant['privilege_type']);
      }

      if (strcasecmp('YES', dbsteward::string_cast($row_grant['is_grantable'])) == 0) {
        if (!isset($node_grant['with'])) {
          $node_grant->addAttribute('with', 'GRANT');
        }
        $node_grant['with'] = 'GRANT';
      }
    }

    // analyze sequence grants and assign those to the xml document as well
    dbsteward::info("Analyze isolated sequence permissions ");
    foreach ($schemas as $schema) {
      $sequences = &dbx::get_sequences($schema);
      foreach ($sequences as $sequence) {
        $seq_name = $sequence['name'];
        $grant_sql = "SELECT relacl FROM pg_class WHERE relname = '" . $seq_name . "';";
        $grant_rc = pgsql8_db::query($grant_sql);
        while (($grant_row = pg_fetch_assoc($grant_rc)) !== FALSE) {
          // privileges for unassociated sequences are not listed in
          // information_schema.sequences; i think this is probably the most
          // accurate way to get sequence-level grants
          if ($grant_row['relacl'] === NULL) {
            continue;
          }
          $grant_perm = self::parse_sequence_relacl($grant_row['relacl']);
          foreach ($grant_perm as $user => $perms) {
            foreach ($perms as $perm) {
              $nodes = $sequence->xpath("grant[@role='" . self::translate_role_name($user) . "']");
              if (count($nodes) == 0) {
                $node_grant = $sequence->addChild('grant');
                $node_grant->addAttribute('role', self::translate_role_name($user));
                $node_grant->addAttribute('operation', $perm);
              }
              else {
                $node_grant = $nodes[0];
                // add to the when if the trigger already exists
                $node_grant['operation'] .= ', ' . $perm;
              }
            }
          }

        }
      }
    }
    
    pgsql8_db::disconnect();

    // scan all now defined tables
    $schemas = & dbx::get_schemas($doc);
    foreach ($schemas AS $schema) {
      $tables = & dbx::get_tables($schema);
      foreach($tables AS $table) {
        // if table does not have a primary key defined
        // add a placeholder for DTD validity
        if ( !isset($table['primaryKey']) ) {
          $table->addAttribute('primaryKey', 'dbsteward_primary_key_not_found');
          $table_notice_desc = 'DBSTEWARD_EXTRACTION_WARNING: primary key definition not found for ' . $table['name'] . ' - placeholder has been specified for DTD validity';
          dbsteward::warning("WARNING: " . $table_notice_desc);
          if ( !isset($table['description']) ) {
            $table['description'] = $table_notice_desc;
          }
          else {
            $table['description'] .= '; ' . $table_notice_desc;
          }
        }

        // check owner and grant role definitions
        if ( !self::is_custom_role_defined($doc, $table['owner']) ) {
          self::add_custom_role($doc, $table['owner']);
        }
        if ( isset($table->grant) ) {
          foreach($table->grant AS $grant) {
            if ( !self::is_custom_role_defined($doc, $grant['role']) ) {
              self::add_custom_role($doc, $grant['role']);
            }
          }
        }
      }
    }

    xml_parser::validate_xml($doc->asXML());
    return xml_parser::format_xml($doc->saveXML());
  }

  public static function parse_sql_array($sql_arr) {
    $input = trim($sql_arr, "{}");
   
    // {} signifies an empty array
    if (strlen($input) === 0) {
      return array();
    }
    
    $rv = array();
    $nextval = '';
    $inquote = FALSE;
    for ($i = 0; $i < strlen($input); $i++) {
      //echo "$i, $inquote, $nextval\n";
      if ($inquote) {
        if ($input{$i} == '"') {
          $inquote = FALSE;
        }
        else {
          if ($input{$i} == "\\") {
            $i++;
            $nextval .= $input{$i};
          }
          else {
            $nextval .= $input{$i};
          }
        }
      }
      else {
        if ($input{$i} == ',') {
          $rv[] = $nextval;
          $nextval = '';
        }
        else {
          if ($input{$i} == '"') {
            $inquote = TRUE;
          }
          else {
            $nextval .= $input{$i};
          }
        }
      }
    }
    $rv[] = $nextval;
    return $rv;
  }

  /**
   * Parse the relacl entry from pg_class into users and their associated perms
   *
   */
  protected static function parse_sequence_relacl($grant_str) {
    // will be receiving something like '{superuser=rwU/superuser_role,normal_role=rw/superuser_role}'
    // first, split into each array entry
    if (empty($grant_str)) {
      return array();
    }
    $perm_trim = trim($grant_str, '{}');
    $perm_list = explode(',', $perm_trim);

    if (count($perm_list) == 1) {
    }

    $grants = array();

    // split each entry into user / perm
    foreach ($perm_list as $perm_entry) {
      $single_perm = explode('=', $perm_entry);
      // permission entry is empty? skip it
      if (count($single_perm) < 1) {
        continue;
      }
      $user = $single_perm[0];
      if (count($single_perm) != 2) {
        // we can't parse this, something is wrong
        throw new Exception("Can't parse permissions! Offending grant string was $grant_str.");
      }
      $permissions = $single_perm[1];
      $grants[$user] = self::make_grant_list($permissions);
    }
    return $grants;
  }

  /**
   * Parse a permission string (i.e. 'rw/deployment') into permission types DBSteward will understand
   */
  protected static function make_grant_list($perm_set) {
    // AFAICT the only permissions allowed for sequences are SELECT/UPDATE/USAGE
    $mappings = array(
      'a' => 'UPDATE',
      'r' => 'SELECT',
      'U' => 'USAGE'
    );

    // perm set will be something like rwU/superuser_role, split on /
    $grant_str = explode('/', $perm_set);
    if (count($grant_str) == 0) {
      return FALSE;
    }
    $grant_chars = str_split($grant_str[0]);

    $perms = array();
    foreach ($grant_chars as $grant_char) {
      if (array_key_exists($grant_char, $mappings)) {
        $perms[] = $mappings[$grant_char];
      }
    }
    return $perms;
  }


  /**
   * compare composite db doc to specified database
   *
   * @return string XML
   */
  public static function compare_db_data($db_doc, $host, $port, $database, $user, $password) {
    dbsteward::notice("Connecting to pgsql8 host " . $host . ':' . $port . ' database ' . $database . ' as ' . $user);
    // if not supplied, ask for the password
    if ($password === FALSE) {
      // @TODO: mask the password somehow without requiring a PHP extension
      echo "Password: ";
      $password = fgets(STDIN);
    }
    pgsql8_db::connect("host=$host port=$port dbname=$database user=$user password=$password");

    dbsteward::info("Comparing composited dbsteward definition data rows to postgresql database connection table contents");
    // compare the composited dbsteward document to the established database connection
    // effectively looking to see if rows are found that match primary keys, and if their contents are the same
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->table AS $table) {
        if (isset($table->rows)) {
          $table_name = dbsteward::string_cast($schema['name']) . '.' . dbsteward::string_cast($table['name']);
          $primary_key_cols = self::primary_key_split($table['primaryKey']);
          $cols = preg_split("/[\,\s]+/", $table->rows['columns'], -1, PREG_SPLIT_NO_EMPTY);

          $col_types = array();
          foreach ($table->column AS $table_column) {
            $type = '';

            // foreign keyed columns inherit their foreign reference type
            if (isset($table_column['foreignTable'])
              && isset($table_column['foreignColumn'])) {
              if (strlen($type) > 0) {
                throw new exception("type of " . $type . " was found for " . dbsteward::string_cast($cols[$j]) . " in table " . dbsteward::string_cast($table['name']) . " but it is foreign keyed!");
              }
              $foreign = array();
              dbx::foreign_key($db_doc, $schema, $table, $table_column, $foreign);
              // don't need to error-check, foreign_key() is self-checking if it doesnt find the fkey col it will complain
              $type = $foreign['column']['type'];
            }
            else {
              $type = dbsteward::string_cast($table_column['type']);
            }

            if (strlen($type) == 0) {
              throw new exception($table_name . " column " . $table_column['name'] . " type not found!");
            }

            $col_types[dbsteward::string_cast($table_column['name'])] = $type;
          }

          foreach ($table->rows->row AS $row) {
            // glue the primary key expression together for the where
            $primary_key_expression = '';
            for ($k = 0; $k < count($primary_key_cols); $k++) {
              $column_name = pgsql8::get_quoted_column_name($primary_key_cols[$k]);

              $pk_index = array_search($primary_key_cols[$k], $cols);
              if ($pk_index === FALSE) {
                throw new exception("failed to find " . $schema['name'] . "." . $table['name'] . " primary key column " . $primary_key_cols[$k] . " in cols list (" . implode(", ", $cols) . ")");
              }

              $primary_key_expression .= $column_name . " = " . pgsql8::value_escape($col_types[$primary_key_cols[$k]], $row->col[$pk_index], $db_doc);
              if ($k < count($primary_key_cols) - 1) {
                $primary_key_expression .= ' AND ';
              }
            }

            $sql = "SELECT *
              FROM " . $table_name . "
              WHERE " . $primary_key_expression;
            $rs = pgsql8_db::query($sql);

            // is the row supposed to be deleted?
            if (strcasecmp('true', $row['delete']) == 0) {
              if (pg_num_rows($rs) > 0) {
                dbsteward::notice($table_name . " row marked for DELETE found WHERE " . $primary_key_expression);
              }
            }
            else if (pg_num_rows($rs) == 0) {
              dbsteward::notice($table_name . " does not contain row WHERE " . $primary_key_expression);
            }
            else if (pg_num_rows($rs) > 1) {
              dbsteward::notice($table_name . " contains more than one row WHERE " . $primary_key_expression);
              while (($db_row = pg_fetch($rs)) !== FALSE) {
                dbsteward::notice("\t" . implode(', ', $db_row));
              }
            }
            else {
              $db_row = pg_fetch_assoc($rs);
              // make sure any aspects of the $row are present in the $db_row
              for ($i = 0; $i < count($cols); $i++) {
                $xml_value = self::pgdata_homogenize($col_types[$cols[$i]], dbsteward::string_cast($row->col[$i]));
                $db_value = self::pgdata_homogenize($col_types[$cols[$i]], dbsteward::string_cast($db_row[$cols[$i]]));

                $values_match = FALSE;
                // evaluate if they are equal
                $values_match = ($xml_value == $db_value);
                // if they are not PHP equal, and are alternate expressionable, ask the database
                if (!$values_match && preg_match('/^time.*|^date.*|^interval/i', $col_types[$cols[$i]]) > 0) {
                  // do both describe atleast some value (greater than zero len?)
                  if (strlen($xml_value) > 0
                    && strlen($db_value) > 0) {
                    $sql = "SELECT '$xml_value'::" . $col_types[$cols[$i]] . " = '$db_value'::" . $col_types[$cols[$i]] . " AS equal_eval";
                    $values_match = (pgsql8_db::query_str($sql) == 't');
                  }
                }

                if (!$values_match) {
                  dbsteward::warning($table_name . " row column WHERE (" . $primary_key_expression . ") " . $cols[$i] . " data does not match database row column: '" . $xml_value . "' VS '" . $db_value . "'");
                }
              }
            }
          }
        }
      }
    }

    //xml_parser::validate_xml($db_doc->asXML());
    return xml_parser::format_xml($db_doc->saveXML());
  }

  public static function pgdata_homogenize($type, $value) {
    // boolean homogenizing
    if (preg_match('/boolean/i', $type) > 0) {
      if (strcasecmp('true', $value) == 0
        || $value == 't') {
        $value = 'TRUE';
      }
      else if (strcasecmp('false', $value) == 0
        || $value == 'f') {
        $value = 'FALSE';
      }
    }

    return $value;
  }

  /**
   * Split the primary key up into an array of columns
   *
   * @param string $primary_key_string The primary key string (e.g. "schema_name, table_name, column_name")
   * @return array The primary key(s) split into an array
   */
  public static function primary_key_split($primary_key_string) {
    return preg_split("/[\,\s]+/", $primary_key_string, -1, PREG_SPLIT_NO_EMPTY);
  }

  /**
   * Parse a function definition and determine if it references a table
   *
   * @param string $definition The SQL used to create a function
   * @return string The matched table name the function references, or NULL if no reference
   */
  protected static function function_definition_references_table($definition) {
    $valid_table_name = "[\w\.]";
    $table_name = NULL;
    if ( preg_match("/SELECT\s+(.+)\s+FROM\s+($valid_table_name+)/i", $definition, $matches) > 0 ) {
      $table_name = $matches[2];
    }
    else if (preg_match("/INSERT\s+INTO\s+($valid_table_name+)/i", $definition, $matches) > 0) {
      $table_name = $matches[1];
    }
    else if (preg_match("/DELETE\s+FROM\s+(?:ONLY)?\s*($valid_table_name+)/i", $definition, $matches) > 0) {
      $table_name = $matches[1];
    }
    else if (preg_match("/UPDATE\s+(?:ONLY)?\s*($valid_table_name+)\s+SET\s+/i", $definition, $matches) > 0) {
      $table_name = $matches[1];
    }
    return $table_name;
  }
  
  /**
   * Sanity check and return the Slony replica set nodes of the definition
   * 
   * @param SimpleXMLElement $db_doc
   * @return SimpleXMLElement[]
   * @throws exception
   */
  public static function &get_slony_replica_sets($db_doc) {
    if ( !isset($db_doc->database->slony) ) {
      throw new exception("slony section not found in database definition");
    }
    if ( !isset($db_doc->database->slony->slonyReplicaSet) ) {
      throw new exception("no slonyReplicaSet elements in database definition");
    }
    $srs = $db_doc->database->slony->slonyReplicaSet;
    return $srs;
  }
  
  /**
   * Get the first natural order replica set definition node from the provided definition document
   * 
   * @param SimpleXMLElement $db_doc
   * @return SimpleXMLElement
   */
  public static function &get_slony_replica_set_natural_first($db_doc) {
    $replica_sets = static::get_slony_replica_sets($db_doc);
    foreach($replica_sets AS $replica_set) {
      return $replica_set;
    }
    return FALSE;
  }
  
  /**
   * Get the replica set node of the specified ID
   * 
   * @param SimpleXMLElement $db_doc
   * @param integer $id
   * @return SimpleXMLElement
   */
  protected static function get_slony_replica_set($db_doc, $id) {
    if ( $id == 0 || !is_numeric($id) ) {
      throw new exception("replica set id '" . $id . "' is not a number");
    }
    $replica_sets = static::get_slony_replica_sets($db_doc);
    foreach($replica_sets AS $replica_set) {
      if ( strcmp($replica_set['id'], $id) == 0 ) {
        return $replica_set;
      }
    }
    return FALSE;
  }
  
  /**
   * Return an array of the nodes in the specified replica set
   * @param SimpleXMLElement $replica_set
   * @return array
   */
  protected static function get_slony_replica_set_node_ids($replica_set) {
    $node_ids = array();
    $node_ids[] = (integer)($replica_set['originNodeId']);
    foreach($replica_set->slonyReplicaSetNode AS $set_node) {
      $node_ids[] = (integer)($set_node['id']);
    }
    return $node_ids;
  }
  
  /**
   * Get the attribute of the specified replica set node
   * Ths resolves node inheritance and explicit configuration data 
   * such as alternate database service addresses in slony configurations
   * @param SimpleXMLElement $replica_set
   * @param integer $node_id
   * @param string $attribute
   */
  protected static function get_slony_replica_set_node_attribute($db_doc, $replica_set, $node_id, $attribute) {
    $replica_node = NULL;
    // is the node_id specified the origin node?
    if ( (integer)($replica_set['originNodeId']) == $node_id ) {
      $replica_node = $replica_set;
    }
    else {
      foreach($replica_set->slonyReplicaSetNode AS $set_node) {
        if ( (integer)($set_node['id']) == $node_id ) {
          $replica_node = $set_node;
        }
      }
    }
    if ( !is_object($replica_node) ) {
      throw new exception("Replica set " . $replica_set['id'] . " node " . $node_id . " not found");
    }
    // if the replica_node defines this attribute, return it, if not return master node definition value
    if ( isset($replica_node[$attribute]) ) {
      return (string)($replica_node[$attribute]);
    }
    $slony_node = pgsql8::get_slony_node($db_doc, $node_id);
    return (string)($slony_node[$attribute]);
  }
  
  /**
   * Return the slony node element for the specified id
   * @param type $db_doc
   * @param type $node_id
   * @return SimpleXMLElement
   */
  protected static function &get_slony_node($db_doc, $node_id) {
    if ( !isset($db_doc->database->slony->slonyNode) ) {
      throw new exception("no slonyNode elements in database definition");
    }
    foreach($db_doc->database->slony->slonyNode AS $slony_node) {
      if ( (integer)($slony_node['id']) == $node_id ) {
        return $slony_node;
      }
    }
    // return FALSE reference variable
    $node_not_found = FALSE;
    return $node_not_found;
  }
  
  public static function slony_replica_set_contains_table($db_doc, $replica_set, $schema, $table) {
    if (isset($table['slonyId']) && strlen($table['slonyId']) > 0) {
      if (strcasecmp('IGNORE_REQUIRED', $table['slonyId']) == 0) {
        // the slonyId IGNORE_REQUIRED magic value allows for slonyId's to be required
        // but also allow for some tables to not be replicated even with the flag on
        return FALSE;
      }
      else if (!is_numeric(dbsteward::string_cast($table['slonyId']))) {
        throw new exception('table ' . $table['name'] . " slonyId " . $table['slonyId'] . " is not numeric");
      }
      else if ( strcmp($replica_set['id'], $table['slonySetId']) == 0 ) {
        // this table is for the intended replica set
        return TRUE;
      }
      else if ( !isset($table['slonySetId']) ) {
        if (dbsteward::$require_slony_set_id) {
          throw new exception("Table " . $schema['name'] . '.' . $table['name'] . " has a slonyId without a slonySetId, and --requiremissing slonyId and slonyIds are required");
        }

        // this table has no replica set
        $first_replica_set = static::get_slony_replica_set_natural_first($db_doc);
        if ( strcmp($replica_set['id'], $first_replica_set['id']) == 0 ) {
          // but the $replica_set passed is the FIRST NATURAL ORDER replica set
          // so yes this table pertains to this replica_set
          return TRUE;
        }
      }
    }
    else {
      dbsteward::warning("Warning: " . str_pad($schema['name'] . '.' . $table['name'], 44) . " table missing slonyId\t" . self::get_slony_next_id_dialogue($db_doc));
      if (dbsteward::$require_slony_id) {
        throw new exception("Table " . $schema['name'] . '.' . $table['name'] . " missing slonyId and slonyIds are required");
      }
    }
    return FALSE;
  }
  
  public static function slony_replica_set_contains_sequence($db_doc, $replica_set, $schema, $sequence) {
    if (isset($sequence['slonyId']) && strlen($sequence['slonyId']) > 0) {
      if (strcasecmp('IGNORE_REQUIRED', $sequence['slonyId']) == 0) {
        // the slonyId IGNORE_REQUIRED magic value allows for slonyId's to be required
        // but also allow for some sequences to not be replicated even with the flag on
        return FALSE;
      }
      else if (!is_numeric(dbsteward::string_cast($sequence['slonyId']))) {
        throw new exception('sequence ' . $sequence['name'] . " slonyId " . $sequence['slonyId'] . " is not numeric");
      }
      else if ( strcmp($replica_set['id'], $sequence['slonySetId']) == 0 ) {
        // this sequence is for the intended replica set
        return TRUE;
      }
      else if ( !isset($sequence['slonySetId']) ) {
        if (dbsteward::$require_slony_set_id) {
          throw new exception("Sequence " . $schema['name'] . '.' . $sequence['name'] . " has a slonyId without a slonySetId, and --requiremissing slonyId and slonyIds are required");
        }
        // this sequence has no replica set
        $first_replica_set = static::get_slony_replica_set_natural_first($db_doc);
        if ( strcmp($replica_set['id'], $first_replica_set['id']) == 0 ) {
          // but the $replica_set passed is the FIRST NATURAL ORDER replica set
          // so yes this sequence pertains to this replica_set
          return TRUE;
        }
      }
    }
    else {
      dbsteward::warning("Warning: " . str_pad($schema['name'] . '.' . $sequence['name'], 44) . " sequence missing slonyId\t" . self::get_slony_next_id_dialogue($db_doc));
      if (dbsteward::$require_slony_id) {
        throw new exception("Sequence " . $schema['name'] . '.' . $sequence['name'] . " missing slonyId and slonyIds are required");
      }
    }
    return FALSE;
  }
  
  public static function slony_replica_set_contains_table_column_serial_sequence($db_doc, $replica_set, $schema, $table, $column) {    
    // is it a serial column and therefore an implicit sequence to replicate?
    if (preg_match(pgsql8::PATTERN_SERIAL_COLUMN, $column['type']) > 0) {

      if (isset($column['slonyId']) && strlen($column['slonyId']) > 0) {
        if (strcasecmp('IGNORE_REQUIRED', $column['slonyId']) == 0) {
          // the slonyId IGNORE_REQUIRED magic value allows for slonyId's to be required
          // but also allow for some table columns to not be replicated even with the flag on
          return FALSE;
        }
        else if (!is_numeric(dbsteward::string_cast($column['slonyId']))) {
          throw new exception("serial column " . $column['name'] . " slonyId " . $column['slonyId'] . " is not numeric");
        }
        else if ( strcmp($replica_set['id'], $column['slonySetId']) == 0 ) {
          // this sequence is for the intended replica set
          return TRUE;
        }
        else if ( !isset($column['slonySetId']) ) {
          if (dbsteward::$require_slony_set_id) {
            throw new exception($schema['name'] . '.' . $table['name'] . '.' . $column['name'] . " has a slonyId without a slonySetId, and --requiremissing slonyId and slonyIds are required");
          }

          // this column has no replica set
          $first_replica_set = static::get_slony_replica_set_natural_first($db_doc);
          if ( strcmp($replica_set['id'], $first_replica_set['id']) == 0 ) {
            // but the $replica_set passed is the FIRST NATURAL ORDER replica set
            // so yes this column pertains to this replica_set
            return TRUE;
          }
        }
      }
      else {
        dbsteward::warning("Warning: " . str_pad($schema['name'] . '.' . $table['name'] . '.' . $column['name'], 44) . " serial column missing slonyId\t" . self::get_slony_next_id_dialogue($db_doc));
        if (dbsteward::$require_slony_id) {
          throw new exception($schema['name'] . '.' . $table['name'] . '.' . $column['name'] . " serial column missing slonyId and slonyIds are required");
        }
      }

    }
    else if (isset($column['slonyId'])) {
      throw new exception($schema['name'] . '.' . $table['name'] . " non-serial column " . $column['name'] . " has slonyId specified. I do not understand");
    }
    
    return FALSE;
  }
  
  /**
   * Does the specified replica_set use the specified slony node?
   * @param SimpleXMLElement $replica_set
   * @param SimpleXMLElement $node
   * @return boolean
   */
  public static function slony_replica_set_uses_node($replica_set, $node) {
    // if replica_set has no replica nodes don't continue
    if ( !isset($replica_set->slonyReplicaSetNode) ) {
      return FALSE;
    }

    // is it the set origin node?
    if ( strcmp($replica_set['originNodeId'], $node['id']) == 0 ) {
      return TRUE;
    }

    // is it a replica node?
    foreach($replica_set->slonyReplicaSetNode AS $rsn){
      if ( strcmp($rsn['id'], $node['id']) == 0 ) {
        return TRUE;
      }
    }
    
    return FALSE;
  }

  protected static function check_duplicate_sequence_slony_id($name, $type, $slony_id) {
    $name = (string)$name;
    $slony_id = (string)$slony_id;
    if ($type == 'column') {
      $type = 'column sequence';
    }
    foreach (self::$sequence_slony_ids as $set_ids) {
      if (in_array($slony_id, $set_ids)) {
        throw new exception("$type $name slonyId $slony_id already in sequence_slony_ids -- duplicates not allowed");
      }
    }
  }
  
  /**
   * Function for placing the slonyids into their Sets, or not if they have no set
  */
  protected static function set_sequence_slony_ids(SimpleXMLElement $column, $db_doc) {
    if (isset($column['slonySetId']) && !is_null($column['slonySetId'])) {
      if (isset(self::$sequence_slony_ids[(int)$column['slonySetId']])) {
        self::$sequence_slony_ids[(int)$column['slonySetId']][] = dbsteward::string_cast($column['slonyId']);
      }
      else {
        self::$sequence_slony_ids[(int)$column['slonySetId']] = array(dbsteward::string_cast($column['slonyId']));
      }
    }
    else {
      // if no slonySetId is defined, put it into the first natural order
      $first_replica_set = static::get_slony_replica_set_natural_first($db_doc);
      if ((int)$first_replica_set['id'] > 0) {
        if (isset(self::$sequence_slony_ids[(int)$first_replica_set['id']])) {
          self::$sequence_slony_ids[(int)$first_replica_set['id']][] = dbsteward::string_cast($column['slonyId']);
        }
        else {
          self::$sequence_slony_ids[(int)$first_replica_set['id']] = array(dbsteward::string_cast($column['slonyId']));
        }
      }
      else {
        // only use if there is no default natural order replica set,
        // not a huge fan of magic values but don't want to let PHP default to 0
        if (isset(self::$sequence_slony_ids['NoSlonySet'])) {
          self::$sequence_slony_ids['NoSlonySet'][] = dbsteward::string_cast($column['slonyId']);
        }
        else {
          self::$sequence_slony_ids['NoSlonySet'] = array(dbsteward::string_cast($column['slonyId']));
        }
      }
    }
  }
  
}
