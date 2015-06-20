<?php
/**
 * Oracle 10g SQL Server specific compiling and differencing functions
 *
 * @package DBSteward
 * @subpackage oracle10g
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class oracle10g {

  const QUOTE_CHAR = '"';

  public static function build($files) {
    if ( strlen($output_prefix) == 0 ) {
      throw new exception("oracle10g::build() sanity failure: output_prefix is blank");
    }
    if (!is_array($files)) {
      $files = array($files);
    }
    $output_prefix = dirname($files[0]) . '/' . substr(basename($files[0]), 0, -4);
    $db_doc = xml_parser::xml_composite($output_prefix, $files, $build_composite_file);

    // build full db creation script
    $build_file = $output_prefix . '_build.sql';
    dbsteward::notice("Building complete file " . $build_file);
    $build_file_fp = fopen($build_file, 'w');
    if ($build_file_fp === FALSE) {
      throw new exception("failed to open full file " . $build_file . ' for output');
    }
    $build_file_ofs = new output_file_segmenter($build_file, 1, $build_file_fp, $build_file);
    if (count(dbsteward::$limit_to_tables) == 0) {
      $build_file_ofs->write("-- full database definition file generated " . date('r') . "\n");
    }

    $build_file_ofs->write("BEGIN TRANSACTION;\n\n");

    dbsteward::info("Calculating table foreign key dependency order..");
    $table_dependency = xml_parser::table_dependency_order($db_doc);
    // database-specific implementation refers to dbsteward::$new_database when looking up roles/values/conflicts etc
    dbsteward::$new_database = $db_doc;
    dbx::set_default_schema($db_doc, 'dbo');
    
    // language defintions
    if (dbsteward::$create_languages) {
      foreach ($db_doc->language AS $language) {
        //@TODO: implement oracle10g_language ? no relevant conversion exists see other TODO's stating this
      }
    }

    if (dbsteward::$only_schema_sql
      || !dbsteward::$only_data_sql) {
      dbsteward::info("Defining structure");
      oracle10g::build_schema($db_doc, $build_file_ofs, $table_dependency);
    }
    if (!dbsteward::$only_schema_sql
      || dbsteward::$only_data_sql) {
      dbsteward::info("Defining data inserts");
      oracle10g::build_data($db_doc, $build_file_ofs, $table_dependency);
    }
    dbsteward::$new_database = NULL;

    $build_file_ofs->write("COMMIT TRANSACTION;\n\n");

    return $db_doc;
  }

  public function build_schema($db_doc, $ofs, $table_depends) {
    // explicitly create the ROLE_APPLICATION
    // webservers connect as a user granted this role
    $ofs->write("CREATE ROLE " . $db_doc->database->role->application . ";\n");

    // schema creation
    foreach ($db_doc->schema AS $schema) {
      $ofs->write(oracle10g_schema::get_creation_sql($schema));

      // schema grants
      if (isset($schema->grant)) {
        foreach ($schema->grant AS $grant) {
          $ofs->write(oracle10g_permission::get_sql($db_doc, $schema, $schema, $grant) . "\n");
        }
      }
    }
    
    // types: enumerated list, etc
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->type AS $type) {
        $ofs->write(oracle10g_type::get_creation_sql($schema, $type) . "\n");
      }
    }

    // function definitions
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->function AS $function) {
        if (oracle10g_function::has_definition($function)) {
          $ofs->write(oracle10g_function::get_creation_sql($schema, $function));
        }
      }
    }
    $ofs->write("\n");

    // table structure creation
    foreach ($db_doc->schema AS $schema) {

      // create defined tables
      foreach ($schema->table AS $table) {
        // table definition
        $ofs->write(oracle10g_table::get_creation_sql($schema, $table) . "\n");

        // table indexes
        oracle10g_diff_indexes::diff_indexes_table($ofs, NULL, NULL, $schema, $table);

        // table grants
        if (isset($table->grant)) {
          foreach ($table->grant AS $grant) {
            $ofs->write(oracle10g_permission::get_sql($db_doc, $schema, $table, $grant) . "\n");
          }
        }

        $ofs->write("\n");
      }

      // sequences contained in the schema
      if (isset($schema->sequence)) {
        foreach ($schema->sequence AS $sequence) {
          $ofs->write(mysql5_sequence::get_creation_sql($schema, $sequence));

          // sequence permission grants
          if (isset($sequence->grant)) {
            foreach ($sequence->grant AS $grant) {
              $ofs->write(oracle10g_permission::get_sql($db_doc, $schema, $sequence, $grant) . "\n");
            }
          }
        }
      }
    }
    $ofs->write("\n");

    // define table primary keys before foreign keys so unique requirements are always met for FOREIGN KEY constraints
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->table AS $table) {
        oracle10g_diff_tables::diff_constraints_table($ofs, NULL, NULL, $schema, $table, 'primaryKey', FALSE);
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
      oracle10g_diff_tables::diff_constraints_table($ofs, NULL, NULL, $schema, $table, 'constraint', FALSE);
    }
    $ofs->write("\n");

    // trigger definitions
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->trigger AS $trigger) {
        // only do triggers set to the current sql format
        if (strcasecmp($trigger['sqlFormat'], dbsteward::get_sql_format()) == 0) {
          $ofs->write(oracle10g_trigger::get_creation_sql($schema, $trigger));
        }
      }
    }
    $ofs->write("\n");

    // view creation
    foreach ($db_doc->schema AS $schema) {
      foreach ($schema->view AS $view) {
        $ofs->write(oracle10g_view::get_creation_sql($schema, $view));

        // view permission grants
        if (isset($view->grant)) {
          foreach ($view->grant AS $grant) {
            $ofs->write(oracle10g_permission::get_sql($db_doc, $schema, $view, $grant) . "\n");
          }
        }
      }
    }
    $ofs->write("\n");

    // @TODO: database configurationParameter support needed ?
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

      $ofs->write(oracle10g_diff_tables::get_data_sql(NULL, NULL, $schema, $table, FALSE));

      // unlike the pg class, we cannot just set identity column start values here with setval without inserting a row
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
        throw new exception('Primary key ' . $table['primaryKey'] . ' does not exist as a column in table ' . $table['name']);
      }
    }

    // include all of the unstaged sql elements
    dbx::build_staged_sql($db_doc, $ofs, NULL);
    $ofs->write("\n");
  }

  public function build_upgrade($old_files, $new_files) {
    if (!is_array($old_files)) {
      $old_files = array($old_files);
    }
    if (!is_array($new_files)) {
      $new_files = array($new_files);
    }
    dbsteward::info("Compositing old XML files..");
    $old_output_prefix = dirname($old_files[0]) . '/' . substr(basename($old_files[0]), 0, -4);
    $old_db_doc = xml_parser::xml_composite($old_output_prefix, $old_files, $old_composite_file);

    dbsteward::info("Compositing new XML files..");
    $new_output_prefix = dirname($new_files[0]) . '/' . substr(basename($new_files[0]), 0, -4);
    $new_db_doc = xml_parser::xml_composite($new_output_prefix, $new_files, $new_composite_file);

    // place the upgrade files with the new_files set
    $upgrade_prefix = $new_output_prefix . '_upgrade';

    // oracle10g_diff needs these to intelligently create SQL difference statements in dependency order
    dbsteward::info("Calculating old table foreign key dependency order..");
    oracle10g_diff::$old_table_dependency = xml_parser::table_dependency_order($old_db_doc);
    dbsteward::info("Calculating new table foreign key dependency order..");
    oracle10g_diff::$new_table_dependency = xml_parser::table_dependency_order($new_db_doc);

    oracle10g_diff::diff_doc($old_composite_file, $new_composite_file, $old_db_doc, $new_db_doc, $upgrade_prefix);

    return $new_db_doc;
  }

  /**
   * escape a column's value, or return the default value if none specified
   *
   * @NOTE: it is critical to note that colmn values should always be escaped with this function
   *        as it also converts MSSQL specific values from postgresql ones
   *
   * @return string
   */
  public static function column_value_default($node_schema, $node_table, $data_column_name, $node_col) {
    // if marked, make it null or default, depending on column options
    if (isset($node_col['null'])
      && strcasecmp('true', $node_col['null']) == 0) {
      $value = 'NULL';
    }
    // columns that specify empty attribute are made empty strings
    else if (isset($node_col['empty']) && strcasecmp('true', $node_col['empty']) == 0) {
      // string escape prefix needed? -- see pgsql8::E_ESCAPE usage
      $value = "''";
    }
    // don't esacape columns marked literal sql values
    else if (isset($node_col['sql']) && strcasecmp($node_col['sql'], 'true') == 0) {
      $value = '(' . $node_col . ')';
    }
    else {
      $node_column = dbx::get_table_column($node_table, $data_column_name);
      if ($node_column === NULL) {
        throw new exception("Failed to find table " . $node_table['name'] . " column " . $data_column_name . " for default value check");
      }
      $value_type = oracle10g_column::column_type(dbsteward::$new_database, $node_schema, $node_table, $node_column, $foreign);

      // else if col is zero length, make it default, or DB NULL
      if (strlen($node_col) == 0) {
        // is there a default defined for the column?
        $dummy_data_column = new stdClass();
        $column_default_value = xml_parser::column_default_value($node_table, $data_column_name, $dummy_data_column);
        if ($column_default_value != NULL) {
          // run default value through value_escape to allow data value conversions to happen
          $value = oracle10g::value_escape($value_type, $column_default_value);
        }
        // else put a NULL in the values list
        else {
          $value = 'NULL';
        }
      }
      else {
        $value = oracle10g::value_escape($value_type, dbsteward::string_cast($node_col));
      }
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
      $PATTERN_QUOTED_TYPES = "/^char.*|^string|^date.*|^time.*|^varchar.*|^interval|^money.*|^inet" . $enum_regex . "/i";

      // strip quoting if it is a quoted type, it will be added after conditional conversion
      if (preg_match($PATTERN_QUOTED_TYPES, $type) > 0) {
        $value = oracle10g::strip_single_quoting($value);
      }

      // complain when assholes use colon time notation instead of postgresql verbose for interval expressions
      if (dbsteward::$require_verbose_interval_notation) {
        if (preg_match('/interval/i', $type) > 0) {
          if (substr($value, 0, 1) != '@') {
            throw new exception("bad interval value: " . $value . " -- interval types must be postgresql verbose format: '@ 2 hours 30 minutes' etc for cfxn comparisons to work");
          }
        }
      }

      // mssql doesn't understand epoch
      if (stripos('date', $type) !== FALSE
        && strcasecmp($value, 'epoch') == 0) {
        $value = '1970-01-01';
      }

      // special case for postgresql type value conversion
      // the boolean type for the column would have been translated to char(1) by xml_parser::oracle10g_type_convert()
      if (strcasecmp($type, 'char(1)') == 0) {
        $value = oracle10g::boolean_value_convert($value);
      }
      // convert datetimeoffset(7) columns to valid MSSQL value format
      // YYYY-MM-DDThh:mm:ss[.nnnnnnn][{+|-}hh:mm]
      else if (strcasecmp($type, 'datetimeoffset(7)') == 0) {
        $value = date('c', strtotime($value));
        // use date()'s ISO 8601 date format to be conformant
      }
      // convert datetime2 columns to valid MSSQL value format
      // YYYY-MM-DDThh:mm:ss[.nnnnnnn]
      else if (strcasecmp($type, 'datetime2') == 0) {
        $value = date('Y-m-dTG:i:s', strtotime($value));
        // use date() to make date format conformant
      }
      // time with time zone is converted to time in xml_parser::oracle10g_type_convert()
      // because of that, truncate values for time type that are > 8 chars in length
      else if (strcasecmp($type, 'time') == 0
        && strlen($value) > 8) {
        $value = substr($value, 0, 8);
      }

      if (preg_match($PATTERN_QUOTED_TYPES, $type) > 0) {
        //@TODO: is there a better way to do mssql string escaping?
        $value = "'" . str_replace("'", "''", $value) . "'";
      }
    }
    else {
      // value is zero length, make it NULL
      $value = "NULL";
    }
    return $value;
  }

  public static function strip_single_quoting($value) {
    // if the value is surrounded on the outside by 's
    // kill them, and kill escaped single quotes too
    if (strlen($value) > 2 && substr($value, 0, 1) == "'" && substr($value, -1) == "'") {
      $value = substr($value, 1, -1);
      $value = str_replace("''", "'", $value);
    }
    return $value;
  }

  public static function boolean_value_convert($value) {
    // true and false become t and f for mssql char(1) special columns
    switch (strtolower($value)) {
      case 'true':
        $value = 't';
      break;
      case 'false':
        $value = 'f';
      break;
      default:
      break;
    }
    return $value;
  }
  
  /**
   * confirm $name is a valid oracle10g identifier
   * 
   * @param string $name
   * @return boolean
   */
  public static function is_valid_identifier($name) {
    return sql99::is_valid_identifier($name);
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

  public static function get_quoted_schema_name($name) {
    return sql99::get_quoted_name($name, dbsteward::$quote_schema_names, self::QUOTE_CHAR);
  }

  public static function get_quoted_table_name($name) {
    return sql99::get_quoted_name($name, dbsteward::$quote_table_names, self::QUOTE_CHAR);
  }

  public static function get_quoted_column_name($name) {
    return sql99::get_quoted_name($name, dbsteward::$quote_column_names, self::QUOTE_CHAR);
  }

  public static function get_quoted_function_name($name) {
    return sql99::get_quoted_name($name, dbsteward::$quote_function_names, self::QUOTE_CHAR);
  }

  public static function get_quoted_object_name($name) {
    return sql99::get_quoted_name($name, dbsteward::$quote_object_names, self::QUOTE_CHAR);
  }
}

?>
