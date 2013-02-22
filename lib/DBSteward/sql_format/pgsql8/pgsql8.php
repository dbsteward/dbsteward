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
  // @TODO: let's not do this right now, it breaks diffs
  public static $table_slony_ids = array();
  public static $sequence_slony_ids = array();

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
        //dbsteward::console_line(7, "rename ident_name FROM " . $ident_name);
        $inc = 1;
        $ident_column = substr($ident_column, 0, $column_maxlen - 1);
        $ident_name = $ident_table . '_' . $ident_column . $suffix . $inc;
        //dbsteward::console_line(7, "rename ident_name TO " . $ident_name);
        while (in_array($ident_name, self::$known_pg_identifiers[$schema][$table])) {
          //dbsteward::console_line(7, "rename ident_name FROM " . $ident_name);
          $inc++;
          $ident_name = $ident_table . '_' . $ident_column . '_' . $suffix . $inc;
          //dbsteward::console_line(1, "rename ident_name TO " . $ident_name);
        }
      }
      self::$known_pg_identifiers[$schema][$table][] = $ident_name;
      //var_dump(self::$known_pg_identifiers);
    }

    return $ident_name;
  }

  public static function index_name($table, $column, $suffix) {
    // figure out the name of the index from table and column names
    // maxlen of pg identifiers is 63
    // so the table and column are each limited to 29 chars, if they both longer
    $table_maxlen = 29;
    $column_maxlen = 29;
    // but if one is shorter pg seems to bonus the longer with the remainder from the shorter:
    // background_check_status_list_background_check_status_list_i_seq
    // program_membership_status_lis_program_membership_status_lis_seq
    // Shift/re calculate maxes based on one side being oversized:
    if (strlen($table) > $table_maxlen
      && strlen($column) < $column_maxlen) {
      // table is longer than max, column is not
      $table_maxlen += $column_maxlen - strlen($column);
    }
    else if (strlen($column) > $column_maxlen && strlen($table) < $table_maxlen) {
      // column is longer than max, table is not
      $column_maxlen += $table_maxlen - strlen($table);
    }

    if (strlen($table) > $table_maxlen) {
      $table = substr($table, 0, $table_maxlen);
    }

    if (strlen($column) > $column_maxlen) {
      $column = substr($column, 0, $column_maxlen);
    }

    $index_name = (string)$table;
    if (strlen($column) > 0) {
      $index_name .= '_' . $column;
    }
    $index_name .= '_' . $suffix;
    return $index_name;
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
      $value = '(' . $node_col . ')';
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
      $node_column = dbx::get_table_column($node_table, $data_column_name);
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
      if (preg_match("/^bool.*|^character.*|^string|^text|^date|^time.*|^varchar.*|^interval|^money.*|^inet|uuid" . $enum_regex . "/i", $type) > 0) {
        $value = "'" . pg_escape_string($value) . "'";

        // data types that should have E prefix to their quotes
        if (pgsql8::E_ESCAPE
          && preg_match("/^character.*|^string|^text|^varchar.*/", $type) > 0) {
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

  public static function build($files, $pgdatafiles = array()) {
    if (!is_array($files)) {
      $files = array($files);
    }
    if (!is_array($pgdatafiles)) {
      $pgdatafiles = array($pgdatafiles);
    }
    $output_prefix = dirname($files[0]) . '/' . substr(basename($files[0]), 0, -4);
    $db_doc = xml_parser::xml_composite($output_prefix, $files, $build_composite_file);
    if (count($pgdatafiles) > 0) {
      xml_parser::xml_composite_pgdata($output_prefix, $db_doc, $pgdatafiles);
    }

    // build full db creation script
    $build_file = $output_prefix . '_build.sql';
    dbsteward::console_line(1, "Building complete file " . $build_file);
    $build_file_fp = fopen($build_file, 'w');
    if ($build_file_fp === FALSE) {
      throw new exception("failed to open full file " . $build_file . ' for output');
    }
    $build_file_ofs = new output_file_segmenter($build_file, 1, $build_file_fp, $build_file);
    if (count(dbsteward::$limit_to_tables) == 0) {
      $build_file_ofs->write("-- full database definition file generated " . date('r') . "\n");
    }
    $build_file_ofs->write("BEGIN; -- STRIP_SLONY: SlonyI installs/upgrades strip this line, the rest need to keep the install transactional\n\n");

    dbsteward::console_line(1, "Calculating table foreign key dependency order..");
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
          if ( $definition['language'] == 'sql'
            && $definition['sqlFormat'] == 'pgsql8'
            && preg_match('/\s+FROM ([\w.]+)\s+/i', $definition, $matches) > 0 ) {
            $referenced_table_name = $matches[1];
            $table_schema_name = sql_parser::get_schema_name($referenced_table_name, $db_doc);
            $node_schema = dbx::get_schema($db_doc, $table_schema_name);
            $node_table = dbx::get_table($node_schema, sql_parser::get_object_name($referenced_table_name));
            if ( $node_table ) {
              // the referenced table is in the definition
              // turn off check_function_bodies
              $set_check_function_bodies = FALSE;
              $set_check_function_bodies_info = "Detected LANGUAGE SQL function " . $schema['name'] . '.' . $function['name'] . " referring to table " . $table_schema_name . '.' . $node_table['name'] . " in the database definition";
              dbsteward::console_line(2, $set_check_function_bodies_info);
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
      dbsteward::console_line(1, "Defining structure");
      pgsql8::build_schema($db_doc, $build_file_ofs, $table_dependency);
    }
    if (!dbsteward::$only_schema_sql
      || dbsteward::$only_data_sql) {
      dbsteward::console_line(1, "Defining data inserts");
      pgsql8::build_data($db_doc, $build_file_ofs, $table_dependency);
    }
    dbsteward::$new_database = NULL;

    $build_file_ofs->write("COMMIT; -- STRIP_SLONY: SlonyI installs/upgrades strip this line, the rest need to keep the install transactional\n\n");

    pgsql8::build_slonik($db_doc, $output_prefix . '_slony_subscription.slonik');

    return $db_doc;
  }

  public function build_schema($db_doc, $ofs, $table_depends) {
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

    // view creation
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->view AS $view) {
        $ofs->write(pgsql8_view::get_creation_sql($schema, $view));

        // view permission grants
        if (isset($view->grant)) {
          foreach ($view->grant AS $grant) {
            $ofs->write(pgsql8_permission::get_sql($db_doc, $schema, $view, $grant) . "\n");
          }
        }
      }
    }
    $ofs->write("\n");

    // use pgdiff to add any configurationParameters that are defined
    // dbsteward::$new_database is already set in the caller, build()
    pgsql8_diff::update_database_config_parameters($ofs);
  }

  public function build_data($db_doc, $ofs, $tables) {
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
        $nodes = $table->xpath("column[@name='" . $pk_column . "']");
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
          dbsteward::console_line(1, 'Primary key ' . $table['primaryKey'] . ' does not exist as a column in child table ' . $table['name'] . ', but may exist in parent table');
        }
      }
    }

    // include all of the unstaged sql elements
    dbx::build_staged_sql($db_doc, $ofs, NULL);
    $ofs->write("\n");
  }

  public function build_slonik($db_doc, $slonik_file) {
    dbsteward::console_line(1, "Building slonik file " . $slonik_file);
    $slonik_fp = fopen($slonik_file, 'w');
    if ($slonik_fp === FALSE) {
      throw new exception("failed to open slonik file " . $slonik_file . ' for output');
    }
    $slonik_ofs = new output_file_segmenter($slonik_file, 1, $slonik_fp, $slonik_file);
    $slonik_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    $slonik_ofs->write("# dbsteward slony full configuration file generated " . date('r') . "\n\n");
    $slonik_ofs->write("ECHO 'dbsteward slony full configuration file generated " . date('r') . " starting';\n\n");

    // schema and table structure
    foreach ($db_doc->schema AS $schema) {

      // table definitions
      foreach ($schema->table AS $table) {
        foreach ($table->column AS $column) {
          // serial column sequence slony configuration
          if (preg_match(pgsql8::PATTERN_SERIAL_COLUMN, $column['type']) > 0) {
            if (isset($column['slonyId']) && strlen($column['slonyId']) > 0) {
              if (strcasecmp('IGNORE_REQUIRED', $column['slonyId']) == 0) {
                // the slonyId IGNORE_REQUIRED magic value allows for slonyId's to be required
                // but also allow for some tables to not be replicated even with the flag on
              }
              else {
                if (!is_numeric(dbsteward::string_cast($column['slonyId']))) {
                  throw new exception("serial column " . $column['name'] . " slonyId " . $column['slonyId'] . " is not numeric");
                }
                if (in_array(dbsteward::string_cast($column['slonyId']), self::$sequence_slony_ids)) {
                  throw new exception("column sequence slonyId " . $column['slonyId'] . " already in sequence_slony_ids -- duplicates not allowed");
                }
                self::$sequence_slony_ids[] = dbsteward::string_cast($column['slonyId']);

                $col_sequence = pgsql8::identifier_name($schema['name'], $table['name'], $column['name'], '_seq');
                $slonik_ofs->write(sprintf(slony1_slonik::script_add_sequence, dbsteward::string_cast($db_doc->database->slony->replicationSet['id']), dbsteward::string_cast($db_doc->database->slony->masterNode['id']), dbsteward::string_cast($column['slonyId']), $schema['name'] . '.' . $col_sequence, $schema['name'] . '.' . $col_sequence . ' serial sequence column replication') . "\n\n");
              }
            }
            else {
              dbsteward::console_line(1, "Warning: " . str_pad($schema['name'] . '.' . $table['name'] . '.' . $column['name'], 44) . " serial column missing slonyId\t" . self::get_next_slony_id_dialogue($db_doc));
              if (dbsteward::$require_slony_id) {
                throw new exception($schema['name'] . '.' . $table['name'] . '.' . $column['name'] . " serial column missing slonyId and slonyIds are required!");
              }
            }
          }
          else if (isset($column['slonyId'])) {
            throw new exception($schema['name'] . '.' . $table['name'] . " non-serial column " . $column['name'] . " has slonyId specified. I do not understand");
          }
        }

        // table slony replication configuration
        if (isset($table['slonyId']) && strlen($table['slonyId']) > 0) {
          if (strcasecmp('IGNORE_REQUIRED', $table['slonyId']) == 0) {
            // the slonyId IGNORE_REQUIRED magic value allows for slonyId's to be required
            // but also allow for some tables to not be replicated even with the flag on
          }
          else {
            if (!is_numeric(dbsteward::string_cast($table['slonyId']))) {
              throw new exception('table ' . $table['name'] . " slonyId " . $table['slonyId'] . " is not numeric");
            }
            if (in_array(dbsteward::string_cast($table['slonyId']), self::$table_slony_ids)) {
              throw new exception("table slonyId " . $table['slonyId'] . " already in table_slony_ids -- duplicates not allowed");
            }
            self::$table_slony_ids[] = dbsteward::string_cast($table['slonyId']);
            $slonik_ofs->write(sprintf(slony1_slonik::script_add_table, dbsteward::string_cast($db_doc->database->slony->replicationSet['id']), dbsteward::string_cast($db_doc->database->slony->masterNode['id']), dbsteward::string_cast($table['slonyId']), $schema['name'] . '.' . $table['name'], $schema['name'] . '.' . $table['name'] . ' table replication') . "\n\n");
          }
        }
        else {
          dbsteward::console_line(1, "Warning: " . str_pad($schema['name'] . '.' . $table['name'], 44) . " table missing slonyId\t" . self::get_next_slony_id_dialogue($db_doc));
          if (dbsteward::$require_slony_id) {
            throw new exception($schema['name'] . '.' . $table['name'] . " table missing slonyId and slonyIds are required!");
          }
        }
      }

      // sequence slony replication configuration
      if (isset($schema->sequence)) {
        foreach ($schema->sequence AS $sequence) {
          if (isset($sequence['slonyId'])
            && strlen($sequence['slonyId']) > 0) {
            if (strcasecmp('IGNORE_REQUIRED', $sequence['slonyId']) == 0) {
              // the slonyId IGNORE_REQUIRED magic value allows for slonyId's to be required
              // but also allow for some tables to not be replicated even with the flag on
            }
            else {
              if (!is_numeric(dbsteward::string_cast($sequence['slonyId']))) {
                throw new exception('sequence ' . $sequence['name'] . " slonyId " . $sequence['slonyId'] . " is not numeric");
              }
              if (in_array(dbsteward::string_cast($sequence['slonyId']), self::$sequence_slony_ids)) {
                throw new exception("sequence slonyId " . $sequence['slonyId'] . " already in sequence_slony_ids -- duplicates not allowed");
              }
              self::$sequence_slony_ids[] = dbsteward::string_cast($sequence['slonyId']);

              $slonik_ofs->write(sprintf(slony1_slonik::script_add_sequence, dbsteward::string_cast($db_doc->database->slony->replicationSet['id']), dbsteward::string_cast($db_doc->database->slony->masterNode['id']), dbsteward::string_cast($sequence['slonyId']), $schema['name'] . '.' . $sequence['name'], $schema['name'] . '.' . $sequence['name'] . ' sequence replication') . "\n\n");
            }
          }
          else {
            dbsteward::console_line(1, "Warning: " . str_pad($schema['name'] . '.' . $sequence['name'], 44) . " sequence missing slonyId\t" . self::get_next_slony_id_dialogue($db_doc));
            if (dbsteward::$require_slony_id) {
              throw new exception($schema['name'] . '.' . $sequence['name'] . " sequence missing slonyId and slonyIds are required!");
            }
          }
        }
      }
    }
    
    $highest_table_slony_id = self::get_next_table_slony_id($db_doc) - 1;
    dbsteward::console_line(1, "-- Highest table slonyId: " . $highest_table_slony_id);
    //$highest_sequence_slony_id = self::get_next_sequence_slony_id($db_doc) - 1;
    //dbsteward::console_line(1, "Highest sequence slonyId: " . $highest_sequence_slony_id);
  }

  public static function get_next_slony_id_dialogue($doc) {
    // it seems more people just want to be told what the next slonyId is
    // than what the next slonyId is for tables and schemas
    // make it so, number one
    /**
     $s = "NEXT table\tslonyId " . self::get_next_table_slony_id($doc) . "\t"
     . "sequence\tslonyId " . self::get_next_sequence_slony_id($doc);
     /*
     */
    $next_slony_id = $next_table_id = self::get_next_table_slony_id($doc);
    $next_sequence_id = self::get_next_sequence_slony_id($doc);
    if ($next_slony_id < $next_sequence_id) {
      $next_slony_id = $next_sequence_id;
    }

    $s = "NEXT ID = " . $next_slony_id;
    return $s;
  }

  public static function get_next_table_slony_id($doc) {
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

  public static function get_next_sequence_slony_id($doc) {
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

  public function build_upgrade($old_files, $new_files, $pgdatafiles = array()) {
    if (!is_array($old_files)) {
      $old_files = array($old_files);
    }
    if (!is_array($new_files)) {
      $new_files = array($new_files);
    }
    if (!is_array($pgdatafiles)) {
      $pgdatafiles = array($pgdatafiles);
    }
    dbsteward::console_line(1, "Compositing old XML files..");
    $old_output_prefix = dirname($old_files[0]) . '/' . substr(basename($old_files[0]), 0, -4);
    $old_db_doc = xml_parser::xml_composite($old_output_prefix, $old_files, $old_composite_file);

    dbsteward::console_line(1, "Compositing new XML files..");
    $new_output_prefix = dirname($new_files[0]) . '/' . substr(basename($new_files[0]), 0, -4);
    $new_db_doc = xml_parser::xml_composite($new_output_prefix, $new_files, $new_composite_file);
    if (count($pgdatafiles) > 0) {
      dbsteward::console_line(1, "Compositing pgdata XML files ontop of new XML composite..");
      xml_parser::xml_composite_pgdata($new_output_prefix, $new_db_doc, $pgdatafiles);
    }

    // place the upgrade files with the new_files set
    $upgrade_prefix = dirname($new_output_prefix) . '/upgrade';

    // pgdiff needs these to intelligently create SQL difference statements in dependency order
    dbsteward::console_line(1, "Calculating old table foreign key dependency order..");
    pgsql8_diff::$old_table_dependency = xml_parser::table_dependency_order($old_db_doc);
    dbsteward::console_line(1, "Calculating new table foreign key dependency order..");
    pgsql8_diff::$new_table_dependency = xml_parser::table_dependency_order($new_db_doc);

    pgsql8_diff::diff_doc($old_composite_file, $new_composite_file, $old_db_doc, $new_db_doc, $upgrade_prefix);

    // figure out slony replication differences
    $slonik_header = "# Old set:  " . implode(', ', $old_files) . "\n" . "# New set:  " . implode(', ', $new_files) . "\n";
    self::build_upgrade_slonik($old_db_doc, $new_db_doc, $upgrade_prefix, $slonik_header);

    return $new_db_doc;
  }

  public function build_upgrade_slonik($old_db_doc, $new_db_doc, $slonik_file_prefix, $old_set_new_set = '') {
    $timestamp = date('r');

    $slony_stage1_file = $slonik_file_prefix . '_stage1_slony.slonik';
    $slony_stage1_fp = fopen($slony_stage1_file, 'w');
    if ($slony_stage1_fp === FALSE) {
      throw new exception("failed to open upgrade slony stage 1 output file " . $slony_stage1_file . ' for output');
    }
    $slony_stage1_ofs = new output_file_segmenter($slony_stage1_file, 1, $slony_stage1_fp, $slony_stage1_file);
    $slony_stage1_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    $slony_stage1_ofs->write("# dbsteward slony stage 1 upgrade file generated " . $timestamp . "\n");
    $slony_stage1_ofs->write($old_set_new_set . "\n");
    $slony_stage1_ofs->write("ECHO 'dbsteward slony stage 1 upgrade file generated " . date('r') . " starting';\n\n");

    $slony_stage3_file = $slonik_file_prefix . '_stage3_slony.slonik';
    $slony_stage3_fp = fopen($slony_stage3_file, 'w');
    if ($slony_stage3_fp === FALSE) {
      throw new exception("failed to open upgrade slony stage 3 output file " . $slony_stage3_file . ' for output');
    }
    $slony_stage3_ofs = new output_file_segmenter($slony_stage3_file, 1, $slony_stage3_fp, $slony_stage3_file);
    $slony_stage3_ofs->set_comment_line_prefix("#");  // keep slonik file comment lines consistent
    $slony_stage3_ofs->write("# dbsteward slony stage 3 upgrade file generated " . $timestamp . "\n");
    $slony_stage3_ofs->write($old_set_new_set . "\n");
    $slony_stage3_ofs->write("ECHO 'dbsteward slony stage 3 upgrade file generated " . date('r') . " starting';\n\n");

    // slony replication configuration changes
    // SLONY STAGE 1
    // unsubscribe to abandoned tables/sequences
    foreach ($old_db_doc->schema AS $old_schema) {
      // look for the schema in the new definition
      $new_schema = dbx::get_schema($new_db_doc, $old_schema['name']);

      // slony replicated tables that are no longer present
      foreach ($old_schema->table AS $old_table) {
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
              $slony_stage1_ofs->write(sprintf(slony1_slonik::script_drop_sequence, dbsteward::string_cast($old_db_doc->database->slony->masterNode['id']), dbsteward::string_cast($old_column['slonyId'])) . "\n\n");
            }
          }

          if (isset($old_table['slonyId'])
            && strcasecmp('IGNORE_REQUIRED', $old_table['slonyId']) != 0) {
            // drop table subscription to the table
            $slony_stage1_ofs->write("# replicated table " . $old_schema['name'] . '.' . $old_table['name'] . " slonyId " . $old_table['slonyId'] . " no longer defined, dropping\n");
            $slony_stage1_ofs->write(sprintf(slony1_slonik::script_drop_table, dbsteward::string_cast($old_db_doc->database->slony->masterNode['id']), dbsteward::string_cast($old_table['slonyId'])) . "\n\n");
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
                $slony_stage1_ofs->write(sprintf(slony1_slonik::script_drop_sequence, dbsteward::string_cast($old_db_doc->database->slony->masterNode['id']), dbsteward::string_cast($old_column['slonyId'])) . "\n\n");
              }
            }
          }
        }
      }

      // slony replicated stand-alone sequences that are no longer present
      foreach ($old_schema->sequence AS $old_sequence) {
        $new_sequence = NULL;
        if ($new_schema !== NULL) {
          $new_sequence = dbx::get_sequence($new_schema, $old_sequence['name']);
        }

        if (($new_schema === NULL || $new_sequence === NULL) && strcasecmp('IGNORE_REQUIRED', $old_sequence['slonyId']) != 0) {
          // schema or sequence no longer exists, drop the sequence subscription
          $slony_stage1_ofs->write(sprintf(slony1_slonik::script_drop_sequence, dbsteward::string_cast($old_db_doc->database->slony->masterNode['id']), dbsteward::string_cast($old_sequence['slonyId'])) . "\n\n");
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
        if (isset($new_table['slonyId']) && strlen($new_table['slonyId']) > 0) {
          if (strcasecmp('IGNORE_REQUIRED', $new_table['slonyId']) == 0) {
            // the slonyId IGNORE_REQUIRED magic value allows for slonyId's to be required
            // but also allow for some tables to not be replicated even with the flag on
          }
          else {
            if (!is_numeric(dbsteward::string_cast($new_table['slonyId']))) {
              throw new exception('table ' . $new_table['name'] . " slonyId " . $new_table['slonyId'] . " is not numeric");
            }
            if (in_array(dbsteward::string_cast($new_table['slonyId']), self::$table_slony_ids)) {
              throw new exception("table slonyId " . $new_table['slonyId'] . " already in table_slony_ids -- duplicates not allowed");
            }
            self::$table_slony_ids[] = dbsteward::string_cast($new_table['slonyId']);
          }
        }
        else {
          dbsteward::console_line(1, "Warning: " . str_pad($new_schema['name'] . '.' . $new_table['name'], 44) . " table missing slonyId\t" . self::get_next_slony_id_dialogue($new_db_doc));
          if (dbsteward::$require_slony_id) {
            throw new exception($new_schema['name'] . '.' . $new_table['name'] . " table missing slonyId and slonyIds are required!");
          }
        }

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
            self::create_slonik_upgrade_set($slony_stage3_ofs, $new_db_doc);
            $upgrade_set_created = TRUE;
          }

          // schema or table did not exist before, add it
          $slony_stage3_ofs->write(sprintf(slony1_slonik::script_add_table, dbsteward::string_cast($new_db_doc->database->slony->replicationUpgradeSet['id']), dbsteward::string_cast($new_db_doc->database->slony->masterNode['id']), dbsteward::string_cast($new_table['slonyId']), $new_schema['name'] . '.' . $new_table['name'], $new_schema['name'] . '.' . $new_table['name'] . ' table replication') . "\n\n");
        }

        // add table owned sequence subscriptions for any not already present
        foreach ($new_table->column AS $new_column) {
          // is a replicated sequence type
          if (preg_match(pgsql8::PATTERN_REPLICATED_COLUMN, $new_column['type']) > 0) {
            if (isset($new_column['slonyId']) && strlen($new_column['slonyId']) > 0) {
              if (strcasecmp('IGNORE_REQUIRED', $new_column['slonyId']) == 0) {
                // the slonyId IGNORE_REQUIRED magic value allows for slonyId's to be required
                // but also allow for some tables to not be replicated even with the flag on
              }
              else {
                if (!is_numeric(dbsteward::string_cast($new_column['slonyId']))) {
                  throw new exception("serial column " . $new_column['name'] . " slonyId " . $new_column['slonyId'] . " is not numeric");
                }
                if (in_array(dbsteward::string_cast($new_column['slonyId']), self::$sequence_slony_ids)) {
                  throw new exception("column sequence slonyId " . $new_column['slonyId'] . " already in sequence_slony_ids -- duplicates not allowed");
                }
                self::$sequence_slony_ids[] = dbsteward::string_cast($new_column['slonyId']);
              }
            }
            else {
              dbsteward::console_line(1, "Warning: " . str_pad($new_schema['name'] . '.' . $new_table['name'] . '.' . $new_column['name'], 44) . " serial column missing slonyId\t" . self::get_next_slony_id_dialogue($new_db_doc));
              if (dbsteward::$require_slony_id) {
                throw new exception($new_schema['name'] . '.' . $new_table['name'] . '.' . $new_column['name'] . " serial column missing slonyId and slonyIds are required!");
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
                self::create_slonik_upgrade_set($slony_stage3_ofs, $new_db_doc);
                $upgrade_set_created = TRUE;
              }

              $col_sequence = pgsql8::identifier_name($new_schema['name'], $new_table['name'], $new_column['name'], '_seq');
              $slony_stage3_ofs->write(sprintf(slony1_slonik::script_add_sequence, dbsteward::string_cast($new_db_doc->database->slony->replicationUpgradeSet['id']), dbsteward::string_cast($new_db_doc->database->slony->masterNode['id']), dbsteward::string_cast($new_column['slonyId']), $new_schema['name'] . '.' . $col_sequence, $new_schema['name'] . '.' . $col_sequence . ' serial sequence column replication') . "\n\n");
            }
          }
        }
      }

      // new stand alone sequences not owned by tables that were not previously present
      foreach ($new_schema->sequence AS $new_sequence) {
        if (isset($new_sequence['slonyId'])
          && strlen($new_sequence['slonyId']) > 0) {
          if (strcasecmp('IGNORE_REQUIRED', $new_sequence['slonyId']) == 0) {
            // the slonyId IGNORE_REQUIRED magic value allows for slonyId's to be required
            // but also allow for some tables to not be replicated even with the flag on
          }
          else {
            if (!is_numeric(dbsteward::string_cast($new_sequence['slonyId']))) {
              throw new exception('sequence ' . $new_sequence['name'] . " slonyId " . $new_sequence['slonyId'] . " is not numeric");
            }
            if (in_array(dbsteward::string_cast($new_sequence['slonyId']), self::$sequence_slony_ids)) {
              throw new exception("sequence slonyId " . $new_sequence['slonyId'] . " already in sequence_slony_ids -- duplicates not allowed");
            }
            self::$sequence_slony_ids[] = dbsteward::string_cast($new_sequence['slonyId']);
          }
        }
        else {
          dbsteward::console_line(1, "Warning: " . str_pad($new_schema['name'] . '.' . $new_sequence['name'], 44) . " sequence missing slonyId\t" . self::get_next_slony_id_dialogue($new_db_doc));
          if (dbsteward::$require_slony_id) {
            throw new exception($new_schema['name'] . '.' . $new_sequence['name'] . " sequence missing slonyId and slonyIds are required!");
          }
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
            self::create_slonik_upgrade_set($slony_stage3_ofs, $new_db_doc);
            $upgrade_set_created = TRUE;
          }

          // sequence did not previously exist, add it
          $slony_stage3_ofs->write(sprintf(slony1_slonik::script_add_sequence, dbsteward::string_cast($new_db_doc->database->slony->replicationUpgradeSet['id']), dbsteward::string_cast($new_db_doc->database->slony->masterNode['id']), dbsteward::string_cast($new_sequence['slonyId']), $new_schema['name'] . '.' . $new_sequence['name'], $new_schema['name'] . '.' . $new_sequence['name'] . ' sequence replication') . "\n\n");
        }
      }
    }

    // if we created an upgrade set, subscribe and merge it
    if ($upgrade_set_created) {
      $slony_stage3_ofs->write("ECHO 'Waiting for merge set creation';\n");
      $slony_stage3_ofs->write(sprintf(
          slony1_slonik::script_node_sync_wait,
          $new_db_doc->database->slony->masterNode['id'],
          $new_db_doc->database->slony->masterNode['id'],
          $new_db_doc->database->slony->masterNode['id']
        ) . "\n\n");

      //
      foreach($new_db_doc->database->slony->replicaNode AS $replica_node) {
        // subscribe replicaNode to its provider node providerId
        $slony_stage3_ofs->write("ECHO 'Subscribing replicaNode " . $replica_node['id'] . " to providerId " . $replica_node['providerId'] . " set ID " . $new_db_doc->database->slony->replicationUpgradeSet['id'] . "';\n");
        $slony_stage3_ofs->write(sprintf(
            slony1_slonik::script_subscribe_set,
            $new_db_doc->database->slony->replicationUpgradeSet['id'],
            $replica_node['providerId'],
            $replica_node['id']
          ) . "\n\n");
        // do a sync and wait for it on the subscribing node
        $slony_stage3_ofs->write("ECHO 'Waiting for replicaNode " . $replica_node['id'] . " subscription to providerId " . $replica_node['providerId'] . " set ID " . $new_db_doc->database->slony->replicationUpgradeSet['id'] . "';\n");
        $slony_stage3_ofs->write(sprintf(
            slony1_slonik::script_node_sync_wait,
            $new_db_doc->database->slony->masterNode['id'],
            $new_db_doc->database->slony->masterNode['id'],
            $replica_node['id']
          ) . "\n\n");
      }

      // now we can merge the upgrade set to the main
      $slony_stage3_ofs->write("ECHO 'Merging replicationUpgradeSet " . $new_db_doc->database->slony->replicationUpgradeSet['id'] . " to set " . $new_db_doc->database->slony->replicationSet['id'] . "';\n");
      $slony_stage3_ofs->write(sprintf(slony1_slonik::script_merge_set,
          $new_db_doc->database->slony->replicationSet['id'],
          $new_db_doc->database->slony->replicationUpgradeSet['id'],
          $new_db_doc->database->slony->masterNode['id']
        ) . "\n\n");
    }
  }

  protected static function create_slonik_upgrade_set($ofs, $doc) {
    $ofs->write(sprintf(slony1_slonik::script_create_set, dbsteward::string_cast($doc->database->slony->replicationUpgradeSet['id']), dbsteward::string_cast($doc->database->slony->masterNode['id']), 'temp upgrade set') . "\n\n");
  }

  public static function slony_compare($files) {
    if (!is_array($files)) {
      $files = array($files);
    }
    $output_prefix = dirname($files[0]) . '/' . substr(basename($files[0]), 0, -4);

    $db_doc = xml_parser::xml_composite($output_prefix, $files, $slony_composite_file);

    $slony_compare_file = $output_prefix . '_slonycompare.sql';
    dbsteward::console_line(1, "Building slony comparison script " . $slony_compare_file);
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
          if (dbsteward::$quote_column_names) {
            $table_columns .= '"' . $column['name'] . '"' . ', ';
          }
          else {
            $table_columns .= $column['name'] . ', ';
          }
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
        dbsteward::console_line(1, "new definition missing schema " . $old_schema['name']);
        continue 1;
      }
      foreach ($old_schema->table AS $old_table) {
        $new_table = dbx::get_table($new_schema, $old_table['name']);
        if (!$new_table) {
          dbsteward::console_line(1, "new definition missing table " . $old_schema['name'] . "." . $old_table['name']);
          continue 1;
        }
        if (strcmp($old_table['slonyId'], $new_table['slonyId']) != 0) {
          dbsteward::console_line(1, "table " . $old_schema['name'] . "." . $old_table['name'] . "\told slonyId " . $old_table['slonyId'] . " new slonyId " . $new_table['slonyId']);
          continue 1;
        }
      }
      foreach ($old_schema->sequence AS $old_sequence) {
        $new_sequence = dbx::get_sequence($new_schema, $old_sequence['name']);
        if (!$new_sequence) {
          dbsteward::console_line(1, "new definition missing sequence " . $old_schema['name'] . "." . $old_sequence['name']);
          continue 1;
        }
        if (strcmp($old_sequence['slonyId'], $new_sequence['slonyId']) != 0) {
          dbsteward::console_line(1, "sequence " . $old_schema['name'] . "." . $old_sequence['name'] . "\told slonyId " . $old_sequence['slonyId'] . " new slonyId " . $new_sequence['slonyId']);
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
    dbsteward::console_line(1, "Calculating sql differences:");
    dbsteward::console_line(1, "Old set:  " . implode(', ', $old));
    dbsteward::console_line(1, "New set:  " . implode(', ', $new));
    dbsteward::console_line(1, "Upgrade:  " . $upgrade_prefix);

    return pgsql8_diff::diff_sql($old, $new, $upgrade_prefix);
  }

  /**
   * extract db schema from pg_catalog
   * based on http://www.postgresql.org/docs/8.3/static/catalogs.html documentation
   *
   * @return string pulled db schema from database, in dbsteward format
   */
  public function extract_schema($host, $port, $database, $user, $password) {
    dbsteward::console_line(1, "Connecting to pgsql8 host " . $host . ':' . $port . ' database ' . $database . ' as ' . $user);
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
    $node_database->addChild('host', $host);
    $node_database->addChild('name', $database);
    $node_role = $node_database->addChild('role');
    $node_role->addChild('application', $user);
    $node_role->addChild('owner', $user);
    $node_role->addChild('replication', $user);
    $node_role->addChild('readonly', $user);
    $node_slony = $node_database->addChild('slony');
    $node_slony_master_node = $node_slony->addChild('masterNode');
    $node_slony_master_node->addAttribute('id', '1');
    $node_slony_replication_set = $node_slony->addChild('replicationSet');
    $node_slony_replication_set->addAttribute('id', '1');
    $node_slony_replication_upgrade_set = $node_slony->addChild('replicationUpgradeSet');
    $node_slony_replication_upgrade_set->addAttribute('id', '2');

    // find all tables in the schema that aren't in the built-in schemas
    $sql = "SELECT *
      FROM pg_catalog.pg_tables
      WHERE schemaname NOT IN ('information_schema', 'pg_catalog')
      ORDER BY schemaname, tablename;";
    $rs = pgsql8_db::query($sql);
    while (($row = pg_fetch_assoc($rs)) !== FALSE) {
      dbsteward::console_line(3, "Analyze table options " . $row['schemaname'] . "." . $row['tablename']);
      // schemaname     |        tablename        | tableowner | tablespace | hasindexes | hasrules | hastriggers
      // create the schema if it is missing
      $nodes = $doc->xpath("schema[@name='" . $row['schemaname'] . "']");
      if (count($nodes) == 0) {
        $node_schema = $doc->addChild('schema');
        $node_schema->addAttribute('name', $row['schemaname']);
        $sql = "SELECT schema_owner FROM information_schema.schemata WHERE schema_name = '" . $row['schemaname'] . "'";
        $schema_owner = pgsql8_db::query_str($sql);
        $node_schema->addAttribute('owner', self::translate_role_name($schema_owner));
      }
      else {
        $node_schema = $nodes[0];
      }

      // create the table in the schema space
      $nodes = $node_schema->xpath("table[@name='" . $row['tablename'] . "']");
      if (count($nodes) == 0) {
        $node_table = $node_schema->addChild('table');
        $node_table->addAttribute('name', $row['tablename']);
        $node_table->addAttribute('owner', self::translate_role_name($row['tableowner']));

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

        dbsteward::console_line(3, "Analyze table columns " . $row['schemaname'] . "." . $row['tablename']);
        //hasindexes | hasrules | hastriggers  handled later
        // get columns for the table
        $sql = "SELECT
            column_name, data_type, character_maximum_length,
            column_default, is_nullable,
            ordinal_position, numeric_precision
          FROM information_schema.columns
          WHERE table_schema='" . $node_schema['name'] . "' AND table_name='" . $node_table['name'] . "'";
        $col_rs = pgsql8_db::query($sql);

        while (($col_row = pg_fetch_assoc($col_rs)) !== FALSE) {
          $node_column = $node_table->addChild('column');
          $node_column->addAttribute('name', $col_row['column_name']);
          // look for serial columns that are primary keys and collapse them down from integers with sequence defualts into serials
          // type int or bigint
          // is_nullable = NO
          // column_default starts with nextval and contains iq_seq
          if ((strcasecmp('int', $col_row['data_type']) == 0 || strcasecmp('bigint', $col_row['data_type']) == 0)
            && strcasecmp($col_row['is_nullable'], 'NO') == 0
            && (stripos($col_row['column_default'], 'nextval') === 0 && stripos($col_row['column_default'], '_seq') !== FALSE)) {
            $col_type = 'serial';
            if (strcasecmp('bigint', $col_row['data_type']) == 0) {
              $col_type = 'bigserial';
            }
            $node_column->addAttribute('type', $col_type);

            // hmm, this is taken care of by the constraint iterator
            //$node_table->addAttribute('primaryKey', $col_row['column_name']);
          }
          // not serial column
          else {
            $col_type = $col_row['data_type'];
            if (is_numeric($col_row['character_maximum_length'])
              && $col_row['character_maximum_length'] > 0) {
              $col_type .= "(" . $col_row['character_maximum_length'] . ")";
            }
            $node_column->addAttribute('type', $col_type);
            if (strcasecmp($col_row['is_nullable'], 'NO') == 0) {
              $node_column->addAttribute('null', 'false');
            }
            if (strlen($col_row['column_default']) > 0) {
              $node_column->addAttribute('default', $col_row['column_default']);
            }
          }
        }

        dbsteward::console_line(3, "Analyze table indexes " . $row['schemaname'] . "." . $row['tablename']);
        // get table INDEXs
        $sql = "SELECT ic.relname, i.indisunique, array_to_string((
                  SELECT array_agg(attname)
                  FROM unnest(i.indkey) AS key
                  LEFT JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = key
                ), ' ') AS dimensions
                FROM pg_index i
                LEFT JOIN pg_class ic ON ic.oid = i.indexrelid
                LEFT JOIN pg_class tc ON tc.oid = i.indrelid
                WHERE tc.relname = '{$node_table['name']}'
                  AND i.indisprimary != 't'
                  AND ic.relname NOT IN (
                    SELECT constraint_name
                    FROM information_schema.table_constraints
                    WHERE table_schema = '{$node_schema['name']}'
                      AND table_name = '{$node_table['name']}');";
        $index_rs = pgsql8_db::query($sql);
        while (($index_row = pg_fetch_assoc($index_rs)) !== FALSE) {
          $dimensions = explode(' ', $index_row['dimensions']);

          // only add a unique index if the column was 
          $index_name = $index_row['relname'];
          $node_index = $node_table->addChild('index');
          $node_index->addAttribute('name', $index_name);
          $node_index->addAttribute('using', 'btree');
          $node_index->addAttribute('unique', $index_row['indisunique']=='t'?'true':'false');
          foreach ($dimensions as $dim) {
            $node_index->addChild('indexDimension', $dim);
          }
        }
      }
      else {
        // complain if it is found, it should have been
        throw new exception("table " . $row['schemaname'] . '.' . $row['tablename'] . " already defined in XML object -- unexpected");
      }
    }

    // extract views
    $sql = "SELECT *
      FROM pg_catalog.pg_views
      WHERE schemaname NOT IN ('information_schema', 'pg_catalog')
      ORDER BY schemaname, viewname;";
    $rc_views = pgsql8_db::query($sql);
    while (($view_row = pg_fetch_assoc($rc_views)) !== FALSE) {
      dbsteward::console_line(3, "Analyze view " . $view_row['schemaname'] . "." . $view_row['viewname']);

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

    // for all schemas, all tables - get table constraints .. PRIMARY KEYs, FOREIGN KEYs
    // makes the loop much faster to do it for the whole schema cause of crappy joins
    // @TODO: known bug - multi-column primary keys can be out of order with this query
    dbsteward::console_line(3, "Analyze table constraints " . $row['schemaname'] . "." . $row['tablename']);
    $sql = "SELECT tc.constraint_name, tc.constraint_type, tc.table_schema, tc.table_name, kcu.column_name, tc.is_deferrable, tc.initially_deferred, rc.match_option AS match_type, rc.update_rule AS on_update, rc.delete_rule AS on_delete, ccu.table_schema AS references_schema, ccu.table_name AS references_table, ccu.column_name AS references_field
      FROM information_schema.table_constraints tc
      LEFT JOIN information_schema.key_column_usage kcu ON tc.constraint_catalog = kcu.constraint_catalog AND tc.constraint_schema = kcu.constraint_schema AND tc.constraint_name = kcu.constraint_name
      LEFT JOIN information_schema.referential_constraints rc ON tc.constraint_catalog = rc.constraint_catalog AND tc.constraint_schema = rc.constraint_schema AND tc.constraint_name = rc.constraint_name
      LEFT JOIN information_schema.constraint_column_usage ccu ON rc.unique_constraint_catalog = ccu.constraint_catalog AND rc.unique_constraint_schema = ccu.constraint_schema AND rc.unique_constraint_name = ccu.constraint_name
      WHERE tc.table_schema NOT IN ('information_schema', 'pg_catalog')
      ORDER BY tc.table_schema, tc.table_name;";
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

      $node_column = NULL;
      if (strlen($constraint_row['column_name']) > 0) {
        $nodes = $node_table->xpath("column[@name='" . $constraint_row['column_name'] . "']");
        if (count($nodes) != 1) {
          throw new exception("failed to find constraint analysis column " . $constraint_row['table_schema'] . " table '" . $constraint_row['table_name'] . "' column '" . $constraint_row['column_name']);
        }
        else {
          $node_column = $nodes[0];
        }
      }

      if (strcasecmp('PRIMARY KEY', $constraint_row['constraint_type']) == 0) {
        if (!isset($node_table['primaryKey'])) {
          $node_table->addAttribute('primaryKey', '');
        }

        if (strlen($node_table['primaryKey']) == 0) {
          $node_table['primaryKey'] = $constraint_row['column_name'];
        }
        else {
          // reverse the standing order, this seems to work for most pk's by abusing natural order returned from the db
          $node_table['primaryKey'] = $constraint_row['column_name'] . ', ' . $node_table['primaryKey'];
        }

        if (!isset($node_table['primaryKeyName'])) {
          $node_table->addAttribute('primaryKeyName', $constraint_row['constraint_name']);
        } else {
          $node_table['primaryKeyName'] = $constraint_row['constraint_name'];
        }
      }
      else if (strcasecmp('UNIQUE', $constraint_row['constraint_type']) == 0) {
        if (!isset($node_column['unique'])) {
          $node_column->addAttribute('unique', 'true');
        }
        $node_column['unique'] = 'true';
      }
      else if (strcasecmp('FOREIGN KEY', $constraint_row['constraint_type']) == 0) {
        if (!isset($node_column['foreignSchema'])) {
          $node_column->addAttribute('foreignSchema', $constraint_row['references_schema']);
        }
        $node_column['foreignSchema'] = $constraint_row['references_schema'];

        if (!isset($node_column['foreignTable'])) {
          $node_column->addAttribute('foreignTable', $constraint_row['references_table']);
        }
        $node_column['foreignTable'] = $constraint_row['references_table'];

        if (!isset($node_column['foreignColumn'])) {
          $node_column->addAttribute('foreignColumn', $constraint_row['references_field']);
        }
        $node_column['foreignColumn'] = $constraint_row['references_field'];

        if (!isset($node_column['foreignKeyName'])) {
          $node_column->addAttribute('foreignKeyName', $constraint_row['constraint_name']);
        }
        $node_column['foreignKeyName'] = $constraint_row['constraint_name'];

        // dbsteward fkey columns aren't supposed to specify a type, they will determine it from the foreign reference
        unset($node_column['type']);
      }
      else if (strcasecmp('CHECK', $constraint_row['constraint_type']) == 0) {
        // @TODO: implement CHECK constraints
      }
      else {
        throw new exception("unknown constraint_type " . $constraint_row['constraint_type']);
      }
    }


    // get function info for all functions
    // this is based on psql 8.4's \df+ query
    // that are not language c
    // that are not triggers
    $sql = "SELECT n.nspname as \"Schema\",
  p.proname as \"Name\",
  pg_catalog.pg_get_function_result(p.oid) as \"Result data type\",
  pg_catalog.pg_get_function_arguments(p.oid) as \"Argument data types\",
 CASE
  WHEN p.proisagg THEN 'agg'
  WHEN p.proiswindow THEN 'window'
  WHEN p.prorettype = 'pg_catalog.trigger'::pg_catalog.regtype THEN 'trigger'
  ELSE 'normal'
END as \"Type\",
 CASE
  WHEN p.provolatile = 'i' THEN 'IMMUTABLE'
  WHEN p.provolatile = 's' THEN 'STABLE'
  WHEN p.provolatile = 'v' THEN 'VOLATILE'
END as \"Volatility\",
  pg_catalog.pg_get_userbyid(p.proowner) as \"Owner\",
  l.lanname as \"Language\",
  p.prosrc as \"Source code\",
  pg_catalog.obj_description(p.oid, 'pg_proc') as \"Description\"
FROM pg_catalog.pg_proc p
     LEFT JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace
     LEFT JOIN pg_catalog.pg_language l ON l.oid = p.prolang
WHERE n.nspname NOT IN ('pg_catalog', 'information_schema')
  AND l.lanname NOT IN ( 'c' )
  AND pg_catalog.pg_get_function_result(p.oid) NOT IN ( 'trigger' )";
    $rs_functions = pgsql8_db::query($sql);
    while (($row_fxn = pg_fetch_assoc($rs_functions)) !== FALSE) {
      dbsteward::console_line(3, "Analyze function " . $row_fxn['Schema'] . "." . $row_fxn['Name']);
      $node_schema = dbx::get_schema($doc, $row_fxn['Schema'], TRUE);
      if ( !isset($node_schema['owner']) ) {
        $sql = "SELECT schema_owner FROM information_schema.schemata WHERE schema_name = '" . $row_fxn['Schema'] . "'";
        $schema_owner = pgsql8_db::query_str($sql);
        $node_schema->addAttribute('owner', self::translate_role_name($schema_owner));
      }
      if ( !$node_schema ) {
        throw new exception("failed to find function schema " . $row_fxn['Schema']);
      }

      $node_function = $node_schema->addChild('function');
      $node_function['name'] = $row_fxn['Name'];

      $args = preg_split("/[\,]/", $row_fxn['Argument data types'], -1, PREG_SPLIT_NO_EMPTY);
      foreach($args AS $arg) {
        $arg_pieces = preg_split("/(?<!character)\s+/", trim($arg), -1, PREG_SPLIT_NO_EMPTY);
        $node_function_parameter = $node_function->addChild('functionParameter');
        if ( count($arg_pieces) > 1 ) {
          // if the function parameter is named, retain the name
          $arg_name = array_shift($arg_pieces);
          $arg_type = implode(' ', $arg_pieces);
          $node_function_parameter['name'] = $arg_name;
          $node_function_parameter['type'] = $arg_type;
        }
        else {
          $node_function_parameter['type'] = trim($arg);
        }
      }

      $node_function['returns'] = $row_fxn['Result data type'];
      $node_function['cachePolicy'] = $row_fxn['Volatility'];
      $node_function['owner'] = $row_fxn['Owner'];
      // @TODO: how is / figure out how to express securityDefiner attribute in the functions query
      $node_function['description'] = $row_fxn['Description'];
      $node_definition = $node_function->addChild('functionDefinition', $row_fxn['Source code']);
      $node_definition['language'] = $row_fxn['Language'];
      $node_definition['sqlFormat'] = 'pgsql8';
    }


    // specify any user triggers we can find in the information_schema.triggers view
    $sql = "SELECT *
      FROM information_schema.triggers
      WHERE trigger_schema NOT IN ('pg_catalog', 'information_schema');";
    $rc_trigger = pgsql8_db::query($sql);
    while (($row_trigger = pg_fetch_assoc($rc_trigger)) !== FALSE) {
      dbsteward::console_line(3, "Analyze trigger " . $row_trigger['event_object_schema'] . "." . $row_trigger['trigger_name']);
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

      $node_trigger['when'] = dbsteward::string_cast($row_trigger['condition_timing']);
      $node_trigger['table'] = dbsteward::string_cast($row_trigger['event_object_table']);
      $node_trigger['forEach'] = dbsteward::string_cast($row_trigger['action_orientation']);
      $trigger_function = trim(str_ireplace('EXECUTE PROCEDURE', '', $row_trigger['action_statement']));
      $node_trigger['function'] = dbsteward::string_cast($trigger_function);
    }


    // find table grants and save them in the xml document
    dbsteward::console_line(3, "Analyze table permissions ");
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
          dbsteward::console_line(1, "WARNING: " . $table_notice_desc);
          if ( !isset($table['description']) ) {
            $table['description'] = $table_notice_desc;
          }
          else {
            $table['description'] .= $table_notice_desc;
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

  /**
   * compare composite db doc to specified database
   *
   * @return string
   */
  public function compare_db_data($host, $port, $database, $user, $password, $files) {
    if (!is_array($files)) {
      $files = array($files);
    }
    dbsteward::console_line(1, "Connecting to pgsql8 host " . $host . ':' . $port . ' database ' . $database . ' as ' . $user);
    // if not supplied, ask for the password
    if ($password === FALSE) {
      // @TODO: mask the password somehow without requiring a PHP extension
      echo "Password: ";
      $password = fgets(STDIN);
    }

    pgsql8_db::connect("host=$host port=$port dbname=$database user=$user password=$password");

    $db_doc = pgsql8::build($files);

    dbsteward::console_line(1, "Comparing composited dbsteward definition data rows to postgresql database connection table contents");

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

              if (dbsteward::$quote_column_names) {
                $column_name = '"' . $primary_key_cols[$k] . '"';
              }
              else {
                $column_name = $primary_key_cols[$k];
              }

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
                dbsteward::console_line(3,  $table_name . " row marked for DELETE found WHERE " . $primary_key_expression);
              }
            }
            else if (pg_num_rows($rs) == 0) {
              dbsteward::console_line(3, $table_name . " does not contain row WHERE " . $primary_key_expression);
            }
            else if (pg_num_rows($rs) > 1) {
              dbsteward::console_line(3, $table_name . " contains more than one row WHERE " . $primary_key_expression);
              while (($db_row = pg_fetch($rs)) !== FALSE) {
                dbsteward::console_line(3,  "\t" . implode(', ', $db_row));
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
                  dbsteward::console_line(1, $table_name . " row column WHERE (" . $primary_key_expression . ") " . $cols[$i] . " data does not match database row column: '" . $xml_value . "' VS '" . $db_value . "'");
                }
              }
            }
          }
        }
      }
    }
    
    xml_parser::validate_xml($db_doc->asXML());
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
}

?>
