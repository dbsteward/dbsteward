<?php
/**
 * Diffs tables.
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_diff_tables extends pgsql8_diff_tables {

  public static function diff_clusters_table($ofs, $old_schema, $old_table, $new_schema, $new_table) {
    if ($old_table == NULL) {
      $old_cluster = NULL;
    }
    else {
      $old_cluster = isset($old_table['clusterIndex']) ? $old_table['clusterIndex'] : NULL;
    }

    $new_cluster = isset($new_table['clusterIndex']) ? $new_table['clusterIndex'] : NULL;

    if ((($old_cluster == NULL) && ($new_cluster != NULL)) || (($old_cluster != NULL)
      && ($new_cluster != NULL) && (strcmp($new_cluster, $old_cluster) != 0))) {
      $ofs->write("ALTER TABLE " . mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($new_table['name']) . " CLUSTER ON " . mssql10::get_quoted_column_name($new_cluster) . ";\n");
    }
    else if (($old_cluster != NULL) && ($new_cluster == NULL) && mssql10_table::contains_index($new_schema, $new_table, $old_cluster)) {
      $ofs->write("ALTER TABLE " . mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($table['name']) . " SET WITHOUT CLUSTER;" . "\n");
    }
  }

  /**
   * Generate the needed alter table xxx set statistics when needed.
   *
   * @param $ofs        output file pointer
   * @param $old_table  original table
   * @param $new_schema new table
   * @param $new_table  new table
   */
  protected static function add_alter_statistics($ofs, $old_table, $new_schema, $new_table) {
    $stats = array();

    foreach (dbx::get_table_columns($new_table) as $new_column) {
      $old_column = dbx::get_table_column($old_table, $new_column['name']);

      if ($old_column != NULL) {
        $new_stat_value = NULL;

        if (($new_column['statistics'] != NULL)
          && ($old_column['statistics'] == NULL || $new_column['statistics'] != $old_column['statistics'])) {
          $new_stat_value = $new_column['statistics'];
        }
        else if ($old_column['statistics'] != NULL && $new_column['statistics'] == NULL) {
          $new_stat_value = -1;
        }

        if ($new_stat_value !== NULL) {
          $stats[$new_column['name']] = $new_stat_value;
        }
      }
    }

    foreach ($stats as $key => $value) {
      $ofs->write("\n");
      $ofs->write("ALTER TABLE ONLY " . mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($key) . " ALTER COLUMN " . mssql10::get_quoted_column_name($key) . " SET STATISTICS " . $value . ";\n");
    }
  }

  /**
   * Adds commands for creation of new columns to the list of
   * commands.
   *
   * @param commands list of commands
   * @param old_table original table
   * @param new_table new table
   * @param drop_defaults_columns list for storing columns for which default value should be dropped
   */
  private static function add_create_table_columns(&$commands, $old_table, $new_schema, $new_table, &$drop_defaults_columns) {
    foreach (dbx::get_table_columns($new_table) as $new_column) {
      if (!mssql10_table::contains_column($old_table, $new_column['name'])) {
        if ( !dbsteward::$ignore_oldnames && mssql10_diff_tables::is_renamed_column($old_table, $new_table, $new_column) ) {
          // oldColumnName renamed column ? rename table column of create new one
          $renamed_column_schema_name = $new_schema['name'];
          $renamed_column_table_name = $new_table['name'];
          $old_column_name = $new_column['oldColumnName'];
          $new_column_name = $new_column['name'];
          $commands[] = array(
            'stage' => 'BEFORE1',
            'command' => "-- column rename from oldColumnName specification\n"
              . "sp_rename '"
              . $renamed_column_schema_name . "." . $renamed_column_table_name . "." . $old_column_name
              . "' , '$new_column_name', 'COLUMN' ;"
          );
          continue;
        }

        // notice $include_null_definition is false
        // this is because ADD <column definition>s with NOT NULL will fail when there are existing rows
        
        $new_column_type = mssql10_column::column_type(dbsteward::$new_database, $new_schema, $new_table, $new_column, $foreign);

        /* @DIFFTOOL - look for columns of a certain type being added
        if ( preg_match('/time|date/i', $new_column_type) > 0 ) {
        echo $new_schema . "." . $new_table['name'] . "." . $new_column['name'] . " of type " . $new_column_type . " " . $new_column['default'] . " found\n";
        }
        /**/

        $commands[] = array(
          'stage' => '1',
          'command' => "\tADD " . mssql10_column::get_full_definition(dbsteward::$new_database, $new_schema, $new_table, $new_column, mssql10_diff::$add_defaults, FALSE)
        );
        // we put the NOT NULL as an alteration in STAGE3 as data will have been updated in STAGE2
        if (!mssql10_column::null_allowed($new_table, $new_column)) {
          
          $commands[] = array(
            'stage' => '3',
            'command' => "\tALTER COLUMN " . mssql10::get_quoted_column_name($new_column['name']) . " " . $new_column_type . " NOT NULL"
          );

          // also, if it's defined, default the column in stage 1 so the SET NULL will actually pass in stage 3
          if (strlen($new_column['default']) > 0) {
            $commands[] = array(
              'stage' => 'AFTER1',
              'command' => "UPDATE " . mssql10::get_quoted_schema_name($new_schema['name']) . "." . mssql10::get_quoted_table_name($new_table['name']) . " SET " . mssql10::get_quoted_column_name($new_column['name']) . " = DEFAULT" . " WHERE " . mssql10::get_quoted_column_name($new_column['name']) . " IS NULL;"
            );
          }
        }

        // if the column type is a defined enum, add a check constraint to enforce the pseudo-enum
        if (mssql10_column::enum_type_check(dbsteward::$new_database, $new_schema, $new_table, $new_column, $drop_sql, $add_sql)) {
          $commands[] = array(
            'stage' => 'AFTER1',
            'command' => $add_sql
          );
        }

        if (mssql10_diff::$add_defaults
          && !mssql10_column::null_allowed($new_table, $new_column)) {
          $drop_defaults_columns[] = $new_column;
        }

        // some columns need filled with values before any new constraints can be applied
        // this is accomplished by defining arbitrary SQL in the column element afterAddPre/PostStageX attribute
        $db_doc_new_schema = dbx::get_schema(dbsteward::$new_database, $new_schema['name']);
        if ($db_doc_new_schema) {
          $db_doc_new_table = dbx::get_table($db_doc_new_schema, $new_table['name']);
          if ($db_doc_new_table) {
            $db_doc_new_column = dbx::get_table_column($db_doc_new_table, $new_column['name']);
            if ($db_doc_new_column) {
              if (isset($db_doc_new_column['beforeAddStage1'])) {
                $commands[] = array(
                  'stage' => 'BEFORE1',
                  'command' => trim($db_doc_new_column['beforeAddStage1']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " beforeAddStage1 definition"
                );
              }
              if (isset($db_doc_new_column['afterAddStage1'])) {
                $commands[] = array(
                  'stage' => 'AFTER1',
                  'command' => trim($db_doc_new_column['afterAddStage1']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " afterAddStage1 definition"
                );
              }
              if (isset($db_doc_new_column['beforeAddStage3'])) {
                $commands[] = array(
                  'stage' => 'BEFORE3',
                  'command' => trim($db_doc_new_column['beforeAddStage3']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " beforeAddStage3 definition"
                );
              }
              if (isset($db_doc_new_column['afterAddStage3'])) {
                $commands[] = array(
                  'stage' => 'AFTER3',
                  'command' => trim($db_doc_new_column['afterAddStage3']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " afterAddStage3 definition"
                );
              }
            }
            else {
              throw new exception("afterAddPre/PostStageX column " . $new_column['name'] . " not found");
            }
          }
          else {
            throw new exception("afterAddPre/PostStageX table " . $new_table['name'] . " not found");
          }
        }
        else {
          throw new exception("afterAddPre/PostStageX schema " . $new_schema['name'] . " not found");
        }
      }
    }
  }

  /**
   * Adds commands for removal of columns to the list of commands.
   *
   * @param commands list of commands
   * @param old_table original table
   * @param new_table new table
   */
  private static function add_drop_table_columns(&$commands, $old_table, $new_table) {
    foreach (dbx::get_table_columns($old_table) as $old_column) {
      if (!mssql10_table::contains_column($new_table, $old_column['name'])) {
        if ( !dbsteward::$ignore_oldnames && ($renamed_column_name = mssql10_table::column_name_by_old_name($new_table, $old_column['name'])) !== false ) {
          // table indicating oldTableName = table['name'] present in new schema? don't do DROP statement
          $old_table_name = mssql10::get_quoted_table_name($old_table['name']);
          $old_column_name = mssql10::get_quoted_column_name($old_column['name']);
          $commands[] = array(
            'stage' => 'AFTER3',
            'command' => "-- $old_table_name DROP COLUMN $old_column_name omitted: new column $renamed_column_name indicates it is the replacement for " . $old_column_name
          );
        }
        else {
          //echo "NOTICE: add_drop_table_columns()  " . $new_table['name'] . " does not contain " . $old_column['name'] . "\n";
          $commands[] = array(
            'stage' => '3',
            'command' => "\tDROP COLUMN " . mssql10::get_quoted_column_name($old_column['name'])
          );
          // @TODO: when dropping columns with an implicitly created default value
          // a mssql contraint to enforce the default value is created, but how can we reference it
          // and drop it, to prevent errors like 'ALTER TABLE DROP COLUMN partial failed because one or more objects access this column.'
        }
      }
    }
  }

  /**
   * Adds commands for modification of columns to the list of
   * commands.
   *
   * @param commands list of commands
   * @param old_table original table
   * @param new_table new table
   * @param drop_defaults_columns list for storing columns for which default value should be dropped
   */
  private static function add_modify_table_columns(&$commands, $old_table, $new_schema, $new_table, &$drop_defaults_columns) {
    foreach (dbx::get_table_columns($new_table) as $new_column) {
      if (!mssql10_table::contains_column($old_table, $new_column['name'])) {
        continue;
      }
      if ( !dbsteward::$ignore_oldnames && mssql10_diff_tables::is_renamed_column($old_table, $new_table, $new_column) ) {
        // oldColumnName renamed column ? skip definition diffing on it, it is being renamed
        continue;
      }
      
      $quoted_table_name = mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($new_table['name']);

      $old_column = dbx::get_table_column($old_table, $new_column['name']);
      $new_column_name = mssql10::get_quoted_column_name($new_column['name']);

      $old_column_type = null;
      if ( $old_column ) {
        $old_column_type = mssql10_column::column_type(dbsteward::$old_database, $new_schema, $old_table, $old_column, $foreign);
      }
      $new_column_type = mssql10_column::column_type(dbsteward::$new_database, $new_schema, $new_table, $new_column, $foreign);

      if (strcmp($old_column_type, $new_column_type) != 0) {
        // ALTER TYPE .. USING support by looking up the new type in the xml definition
        $type_using = '';
        $type_using_comment = '';
        if (isset($new_column['convertUsing'])) {
          $type_using = ' USING ' . $new_column['convertUsing'] . ' ';
          $type_using_comment = '- found XML convertUsing: ' . $new_column['convertUsing'] . ' ';
        }

        // if the column type is a defined enum, (re)add a check constraint to enforce the pseudo-enum
        if (mssql10_column::enum_type_check(dbsteward::$new_database, $new_schema, $new_table, $new_column, $drop_sql, $add_sql)) {
          // enum types rewritten as varchar(255)
          $new_column_type = 'varchar(255)';

          $commands[] = array(
            'stage' => 'AFTER1',
            'command' => $drop_sql
          );
          $commands[] = array(
            'stage' => 'AFTER1',
            'command' => $add_sql
          );
        }

        $commands[] = array(
          'stage' => '1',
          'command' => "\tALTER COLUMN " . $new_column_name . " " . $new_column_type . $type_using . " /* TYPE change - table: " . $new_table['name'] . " original: " . $old_column_type . " new: " . $new_column_type . ' ' . $type_using_comment . '*/'
        );
      }

      $old_default = isset($old_column['default']) ? $old_column['default'] : '';
      $new_default = isset($new_column['default']) ? $new_column['default'] : '';

      // has the default has changed?
      if (strcmp($old_default, $new_default) != 0) {
        // in MSSQL, violating the SQL standard,
        // inline column default definitions are translated to be table constraints
        // the constraint name is somewhat predictable, but not always.
        // some versions apped random numebrs when implicitly creating the constraint

        // was there a constraint before?
        if (strlen($old_default) > 0) {
          $commands[] = array(
            'stage' => 'BEFORE1',
            'command' => 'ALTER TABLE ' . $quoted_table_name . ' DROP CONSTRAINT ' . 'DF_' . $new_table['name'] . '_' . $old_column['name'] . ';'
          );
        }
        // is there now a default constraint?
        if (strlen($new_default) > 0) {
          $commands[] = array(
            'stage' => 'AFTER1',
            'command' => 'ALTER TABLE ' . $quoted_table_name . ' ADD CONSTRAINT ' . 'DF_' . $new_table['name'] . '_' . $new_column['name'] . ' DEFAULT ' . $new_default . ' FOR ' . $new_column_name . ';'          );
        }
      }

      if (strcasecmp($old_column['null'], $new_column['null']) != 0) {
        if (mssql10_column::null_allowed($new_table, $new_column)) {
          $commands[] = array(
            'stage' => '1',
            'command' => "\tALTER COLUMN " . $new_column_name . " " . $new_column_type . " NULL"
          );
        }
        else {
          if (mssql10_diff::$add_defaults) {
            $default_value = mssql10_column::get_default_value($new_column_type);

            if ($default_value != NULL) {
              $commands[] = array(
                'stage' => '1',
                'command' => "\tALTER COLUMN " . $new_column_name . " SET DEFAULT " . $default_value
              );
              $drop_defaults_columns[] = $new_column;
            }
          }
          
          // if the default value is defined in the dbsteward XML
          // set the value of the column to the default in end of stage 1 so that NOT NULL can be applied in stage 3
          // this way custom <sql> tags can be avoided for upgrade generation if defaults are specified
          if ( strlen($new_column['default']) > 0 ) {
            $commands[] = array(
              'stage' => 'AFTER1',
              'command' => "UPDATE " . mssql10::get_quoted_schema_name($new_schema['name']) . "." . mssql10::get_quoted_table_name($new_table['name'])
                . " SET " . $new_column_name . " = " . $new_column['default'] . " WHERE " . $new_column_name . " IS NULL; -- has_default_now: make modified column that is null the default value before NOT NULL hits"
            );
          }

          // before altering column, remove any constraint that would stop us from doing so
          foreach(mssql10_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, 'constraint') as $constraint) { 
            if (preg_match('/' . $new_column['name'] . '[\s,=)]/', $constraint['definition']) > 0) {
              $commands[] = array(
                'stage' => '3',
                'command' => mssql10_table::get_constraint_drop_sql_change_statement($constraint)
              );
            }
          }

          $commands[] = array(
            'stage' => '3',
            'command' => "\tALTER COLUMN " . $new_column_name . " " . $new_column_type . " NOT NULL"
          );

          // add the constraint back on
          foreach(mssql10_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, 'constraint') as $constraint) { 
            if (preg_match('/' . $new_column['name'] . '[\s,=\)]/', $constraint['definition']) > 0) {
              $commands[] = array(
                'stage' => '3',
                'command' => mssql10_table::get_constraint_sql_change_statement($constraint)
              );
            }
          }
        }
      }

      // for identity()'d columns (serial in dbsteward definition) in mssql that are to no longer be
      // we must recreate the table with out the identity attribute
      if ( preg_match('/int\sidentity.*$/', $old_column['type']) > 0 &&
           ($new_column['type'] == 'int' || $new_column['type'] == 'bigint') ) {
        dbsteward::warning("identity()d table " . $new_schema['name'] . "." . $new_table['name'] . " requires rebuild to drop identity property on " . $new_column['name']);

        // create a "deep copy" of the table so column and rows
        // references are not altered in the original old DOM
        $table_for_modifying_xml = $new_table->asXML();
        $table_for_modifying = simplexml_load_string($table_for_modifying_xml);
        // @NOTICE: we do this because of by reference nature of get_table_column()
        // any subsequent references to old table's pkey column(s) would show the type as int/bigint and not the identity() definition

        // get the column then modify the type to remove the identity
        $old_id_pkey_col = dbx::get_table_column($table_for_modifying, $old_column['name']);
        $table_for_modifying['name'] = 'tmp_identity_drop_' . $table_for_modifying['name'];
        if ( preg_match('/^int/', $old_column['type']) > 0 ) {
          $old_id_pkey_col['type'] = 'int';
        }
        else {
          $old_id_pkey_col['type'] = 'bigint';
        }
        // see FS#25730 - dbsteward not properly upgrading serial to int 
        // http://blog.sqlauthority.com/2009/05/03/sql-server-add-or-remove-identity-property-on-column/

        // section start comment
        $identity_transition_commands = array('-- DBSteward: ' . $new_schema['name'] . '.' . $new_table['name'] . ' identity column ' . $new_column['name'] . ' was redefined to ' . $old_id_pkey_col['type'] . ' - table rebuild is necessary');
        // get the creation sql for a temporary table
        $identity_transition_commands[] = mssql10_table::get_creation_sql($new_schema, $table_for_modifying);

        // copy over all the old data into new data, it's the only way
        $identity_transition_commands[] = "IF EXISTS(SELECT * FROM " . mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($new_table['name']) . ")
          EXEC('INSERT INTO " . mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($table_for_modifying['name']) . " ( " .
            implode(",", mssql10_table::get_column_list($table_for_modifying)) . ")
            SELECT " . implode(",", mssql10_table::get_column_list($table_for_modifying)) . "
            FROM " . mssql10::get_quoted_schema_name($new_schema['name']) . "." . mssql10::get_quoted_table_name($new_table['name'])
            . " WITH (HOLDLOCK TABLOCKX)');";
        
        // drop FKEYs other tables have to the table
        $other_tables_foreign_keying_constraints = dbx::get_tables_foreign_keying_to_table(dbsteward::$new_database, mssql10_diff::$new_table_dependency, $new_schema, $new_table);
        dbsteward::info("identity()d table " . $new_schema['name'] . "." . $new_table['name'] . " rebuild has " . count($other_tables_foreign_keying_constraints) . " foreign key references to drop and reapply");
        foreach($other_tables_foreign_keying_constraints as $constraint) {
          $identity_transition_commands[] = mssql10_table::get_constraint_drop_sql($constraint);
        }

        // drop the old table
        $identity_transition_commands[] = "DROP TABLE " . mssql10::get_quoted_schema_name($new_schema['name']) . "." . mssql10::get_quoted_table_name($new_table['name']) . ";";
        // rename temporary table to original name
        // NOTE: sp_rename only takes an identifier for the new name, if you schema qualify the new name it will get doubled on the table name
        $identity_transition_commands[] = "EXECUTE sp_rename '" . $new_schema['name'] . "." . $table_for_modifying['name'] . "', '" . $new_table['name'] . "', 'OBJECT';";
        
        // mssql10_table:::get_creation_sql() only creates the table
        // now that it has been renamed, recreate the table's indexes keys and triggers
        $tc_buffer = fopen("php://memory", "rw");
        $tc_ofs = new output_file_segmenter('identity_transition_command', 1, $tc_buffer, 'identity_transition_command_buffer');
        mssql10_diff_indexes::diff_indexes_table($tc_ofs, NULL, NULL, $new_schema, $new_table);
        mssql10_diff_tables::diff_constraints_table($tc_ofs, NULL, NULL, $new_schema, $new_table, 'primaryKey', FALSE);
        mssql10_diff_triggers::diff_triggers_table($tc_ofs, NULL, NULL, $new_schema, $new_table);
        mssql10_diff_tables::diff_constraints_table($tc_ofs, NULL, NULL, $new_schema, $new_table, 'constraint', FALSE);
        rewind($tc_buffer);
        while (($tc_line = fgets($tc_buffer, 4096)) !== false) {
          $identity_transition_commands[] = $tc_line;
        }
        unset($tc_ofs);
        
        // restore FKEYs other tables have to the table
        foreach($other_tables_foreign_keying_constraints as $constraint) {
          $identity_transition_commands[] = mssql10_table::get_constraint_sql($constraint);
        }
        // section end comment
        $identity_transition_commands[] = '-- DBSteward: ' . $new_schema['name'] . '.' . $new_table['name'] . ' identity column ' . $new_column['name'] . ' was redefined to ' . $old_id_pkey_col['type'] . ' - table rebuild end' . "\n";

        // put all of the identity_transition_commands into the command list as BEFORE3's
        // this will make the identity column changes occur at the beginning of stage 3
        foreach ($identity_transition_commands as $itc) {
          $commands[] = array(
            'stage' => 'BEFORE3',
            'command' => $itc
          );
        }
      }
    }
  }

  /**
   * Checks whether there is a discrepancy in INHERITS for originaland new table.
   *
   * @param $ofs      output file segmenter
   * @param old_table original table
   * @param new_table new table
   */
  protected static function check_inherits($ofs, $old_table, $new_schema, $new_table) {
    throw new exception("DONT CALL ME IN mssql10_diff_tables -- I don't know what to do!");
  }

  /**
   * Outputs commands for creation of new tables.
   *
   * @param $ofs        output file pointer
   * @param $old_schema original schema
   * @param $new_schema new schema
   */
  private static function create_tables($ofs, $old_schema, $new_schema, $old_table = NULL, $new_table = NULL) {
    foreach (dbx::get_tables($new_schema) as $table) {
      if ($new_table != NULL) {
        if (strcasecmp($table['name'], $new_table['name']) != 0) {
          continue;
        }
      }
      if (($old_schema == NULL) || !mssql10_schema::contains_table($old_schema, $table['name'])) {
        if ( !dbsteward::$ignore_oldnames && mssql10_diff_tables::is_renamed_table($new_schema, $table) ) {
          // oldTableName renamed table ? rename table instead of create new one
          $old_table_name = $new_schema['name'] . '.' . $table['oldTableName'];
          $new_table_name = $table['name'];
          $ofs->write("-- table rename from oldTableName specification" . "\n"
            . "sp_rename '$old_table_name' , '$new_table_name' ;" . "\n");
          //@TODO: <table oldSchemaName> mssql10 support =(
        }
        else {
          $ofs->write(mssql10_table::get_creation_sql($new_schema, $table) . "\n");
        }
      }
    }
  }

  /**
   * Drop tables in old_schema no longer defined in new_schema
   * 
   * @param type $ofs
   * @param type $old_schema
   * @param type $new_schema
   * @param type $old_table
   * @param type $new_table
   */
  public static function drop_tables($ofs, $old_schema, $new_schema, $old_table = null, $new_table = null) {
    if ($old_schema != null && $new_schema != null ) {
      foreach(dbx::get_tables($old_schema) as $table) {
        // if old table was defined
        if ( $old_table != null ) {
          // is this the right old table?
          if ( strcasecmp($table['name'], $old_table['name']) != 0 ) {
            continue;
          }
        }

        // does the new schema contain the old table?
        if (!mssql10_schema::contains_table($new_schema, $table['name'])) {
          // if the table was renamed, don't drop it
          if ( !dbsteward::$ignore_oldnames
            && mssql10_schema::table_formerly_known_as(dbsteward::$new_database, $new_schema, $table, $reformed_schema, $reformed_table) ) {
            $old_table_name = mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($table['name']);
            $reformed_table_name = mssql10::get_quoted_schema_name($reformed_schema['name']) . '.' . mssql10::get_quoted_table_name($reformed_table['name']);
            $ofs->write("-- DROP TABLE $old_table_name omitted: new table $reformed_table_name indicates it is her replacement\n");
          }
          else {
            $ofs->write(mssql10_table::get_drop_sql($old_schema, $table) . "\n");
          }
        }
      }
    }
  }

  /**
   * Outputs commands for addition, removal and modifications of
   * table columns.
   *
   * @param stage1 output file segmenter
   * @param stage3 output file segmenter
   * @param old_table original table
   * @param new_table new table
   */
  protected static function update_table_columns($ofs1, $ofs3, $old_table, $new_schema, $new_table) {
    $commands = array();
    $drop_defaults_columns = array();
    mssql10_diff_tables::add_drop_table_columns($commands, $old_table, $new_table);
    mssql10_diff_tables::add_create_table_columns($commands, $old_table, $new_schema, $new_table, $drop_defaults_columns);
    mssql10_diff_tables::add_modify_table_columns($commands, $old_table, $new_schema, $new_table, $drop_defaults_columns);

    if (count($commands) > 0) {
      // do 'pre' 'entire' statements before aggregate table alterations
      for ($i = 0; $i < count($commands); $i++) {
        if ($commands[$i]['stage'] == 'BEFORE1') {
          $ofs1->write($commands[$i]['command'] . "\n");
        }
        else if ($commands[$i]['stage'] == 'BEFORE3') {
          $ofs3->write($commands[$i]['command'] . "\n");
        }
      }

      $quotedTableName = mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($new_table['name']);

      $stage1_sql = '';
      $stage3_sql = '';

      for ($i = 0; $i < count($commands); $i++) {
        if (!isset($commands[$i]['stage']) || !isset($commands[$i]['command'])) {
          var_dump($commands[$i]);
          throw new exception("bad command format");
        }

        if ($commands[$i]['stage'] == '1') {
          // we have a stage 1 alteration to make
          // unlike pgsql8_diff_tables, each alteration statement is on its own line
          // this is because ADD column and ALTER COLUMN statements can't be added to n
          $stage1_sql .= "ALTER TABLE " . $quotedTableName . "\n" . $commands[$i]['command'] . ";\n";
        }
        else if ($commands[$i]['stage'] == '3') {
          // we have a stage 3 alteration to make
          // unlike pgsql8_diff_tables, each alteration statement is on its own line
          // this is because ADD column and ALTER COLUMN statements can't be added to n
          $stage3_sql .= "ALTER TABLE " . $quotedTableName . "\n" . $commands[$i]['command'] . ";\n";
        }
      }

      if (strlen($stage1_sql) > 0) {
        $ofs1->write($stage1_sql);
      }
      if (strlen($stage3_sql) > 0) {
        $ofs3->write($stage3_sql);
      }

      if (count($drop_defaults_columns) > 0) {
        $ofs1->write("\n");
        $ofs1->write("ALTER TABLE " . $quotedTableName . "\n");

        for ($i = 0; $i < count($drop_defaults_columns); $i++) {
          $ofs1->write("\tALTER COLUMN " . mssql10::get_quoted_column_name($drop_defaults_columns[$i]['name']) . " DROP DEFAULT");
          if ($i < count($drop_defaults_columns) - 1) {
            $ofs1->write(",\n");
          }
          else {
            $ofs1->write(";\n");
          }
        }
      }

      // do 'post' 'entire' statements immediately following aggregate table alterations
      for ($i = 0; $i < count($commands); $i++) {
        if ($commands[$i]['stage'] == 'BEFORE1') {
          // already taken care of in earlier entire command output loop
        }
        else if ($commands[$i]['stage'] == 'BEFORE3') {
          // already taken care of in earlier entire command output loop
        }
        else if ($commands[$i]['stage'] == '1') {
          // already taken care of in earlier command aggregate loop
        }
        else if ($commands[$i]['stage'] == '3') {
          // already taken care of in earlier command aggregate loop
        }
        else if ($commands[$i]['stage'] == 'AFTER1') {
          $ofs1->write($commands[$i]['command'] . "\n");
        }
        else if ($commands[$i]['stage'] == 'AFTER3') {
          $ofs3->write($commands[$i]['command'] . "\n");
        }
        else {
          throw new exception("Unknown stage " . $commands[$i]['stage'] . " during table " . $quotedTableName . " updates");
        }
      }
    }
  }

  public static function diff_data($ofs, $old_schema, $new_schema) {
    foreach (dbx::get_tables($new_schema) AS $new_table) {
      $old_table = NULL;
      // does the old contain the new?
      if ($old_schema != NULL && mssql10_schema::contains_table($old_schema, $new_table['name'])) {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }
      $ofs->write(mssql10_diff_tables::get_data_sql($old_schema, $old_table, $new_schema, $new_table));
    }
  }

  public static function get_data_sql($old_schema, $old_table, $new_schema, $new_table, $delete_mode = FALSE) {
    $sql = '';

    $new_table_rows = dbx::get_table_rows($new_table);

    if ($old_table == NULL) {
      if (!$delete_mode) {
        // old table doesnt exist, just pump inserts
        if ($new_table_rows) {
          $new_table_row_columns = preg_split("/[\,\s]+/", $new_table_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
          foreach ($new_table_rows->row AS $data_row) {
            // is the row marked for delete?
            if (isset($data_row['delete'])
              && strcasecmp($data_row['delete'], 'true') == 0) {
              // don't insert it, we are inserting data that should be there
            }
            else {
              $sql .= mssql10_diff_tables::get_data_row_insert($new_schema, $new_table, $new_table_row_columns, $data_row);
            }
          }
        }
      }
    }
    else {
      // data row match scenarios are based on primary key matching
      $old_table_rows = dbx::get_table_rows($old_table);
      if ($old_table_rows) {
        $old_table_row_columns = preg_split("/[\,\s]+/", $old_table_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
      }

      // is caller asking for deletes or data updates?
      if ($delete_mode) {
        // what old rows have no matches in the new rows? delete them
        if ($old_table_rows) {
          mssql10_diff_tables::table_data_rows_compare($old_table, $new_table, FALSE, $old_rows, $new_rows, $changes);
          $count_old_rows = count($old_rows);
          for ($i = 0; $i < $count_old_rows; $i++) {
            mssql10_diff_tables::get_data_row_delete($old_schema, $old_table, $old_table_row_columns, $old_rows[$i], $sql_append);
            //@REVISIT
            $sql .= $sql_append;
          }
        }
      }
      else {
        if ($new_table_rows) {
          $new_table_row_columns = preg_split("/[\,\s]+/", $new_table_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
        }

        // what columns in matching rows between old and new are different?
        if ($old_table_rows && $new_table_rows) {
          $new_table_primary_keys = preg_split("/[\,\s]+/", $new_table['primaryKey'], -1, PREG_SPLIT_NO_EMPTY);

          mssql10_diff_tables::table_data_rows_compare($old_table, $new_table, TRUE, $old_rows, $new_rows, $changes);
          $count_old_rows = count($old_rows);
          for ($i = 0; $i < $count_old_rows; $i++) {
            $new_data_row = NULL;
            $changed_columns = NULL;
            if (count($changes[$i]) > 0) {
              // changes were found between primary key matched old_table_row and new_table_row
              // get the sql to make that happen
              $sql .= mssql10_diff_tables::get_data_row_update($new_schema, $new_table, $old_table_row_columns, $old_rows[$i], $new_table_row_columns, $new_rows[$i], $changes[$i]);
            }
          }
        }

        // what new rows are missing from the old? insert them
        if ($new_table_rows) {
          mssql10_diff_tables::table_data_rows_compare($new_table, $old_table, FALSE, $new_rows, $old_rows, $changes);
          $count_new_rows = count($new_rows);
          for ($i = 0; $i < $count_new_rows; $i++) {
            $sql .= mssql10_diff_tables::get_data_row_insert($new_schema, $new_table, $new_table_row_columns, $new_rows[$i]);
          }
        }
      }
    }

    // when the new table has data rows
    // AND (
    //     new_table has an IDENTITY column
    //     OR
    //     old_table had an IDENTITY column
    // )
    // SET IDENTITY_INSERT for data row insert statements
    // this is because the table IDENTITY column will not be stripped until stage 3 changes
    // see 'identity()d table' console statements for where this is done
    // reference FS#25730 - dbsteward not properly upgrading serial to int 
    if (strlen($sql) > 0
      && $new_table_rows
      && ( mssql10_table::has_identity($new_table) || (is_object($old_table) && mssql10_table::has_identity($old_table)) ) ) {
      // this is needed for mssql to allow IDENTITY columns to be explicitly specified
      $sql = "SET IDENTITY_INSERT " . mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($new_table['name']) . " ON;\n" . 
        $sql .
             "SET IDENTITY_INSERT " . mssql10::get_quoted_schema_name($new_schema['name']) . '.' . mssql10::get_quoted_table_name($new_table['name']) . " OFF;\n";
    }

    return $sql;
  }

  /**
   * if in_both = false, what rows in A are not in B ?
   *
   * if in_both = true, what rows are in A and B ?
   * - when a is empty, all of b's are returned
   * - a's row members are the ones returned when in_both rows are found
   * this is important when comparing tables whose rows are the same but have added columns
   *
   * @return void
   */
  public static function table_data_rows_compare($table_a, $table_b, $in_both, &$a_rows, &$b_rows, &$changes) {
    $a_rows = array();
    $b_rows = array();
    $changes = array();
    $table_a_data_rows = dbx::get_table_rows($table_a);
    $table_b_data_rows = dbx::get_table_rows($table_b);
    // data_row_overlay_key_search() needs these to do the matching
    $table_a_data_rows_columns = preg_split("/[\,\s]+/", $table_a_data_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
    $table_b_data_rows_columns = preg_split("/[\,\s]+/", $table_b_data_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
    if ( $table_a_data_rows == null || $table_a_data_rows_columns == null ) {
      // table_a has no data rows
      if ( $in_both ) {
        // what rows are in A and B? none in A to compare
      }
      else {
        // what rows in A are not in B? none to list
      }
    }
    else if ( $table_b_data_rows == null || $table_b_data_rows_columns == null ) {
      // table_b has no data rows
      if ( $in_both ) {
        // what rows are in A and B? none in B to compare
      }
      else {
        // what rows in A are not in B? omg, all of them!
        $a_rows = $table_a_data_rows->row;
      }
    }
    else {
      $table_b_primary_keys = preg_split("/[\,\s]+/", $table_b['primaryKey'], -1, PREG_SPLIT_NO_EMPTY);
      // @NOTICE: in the case of table row data comparising for DIFFING, the primary keys to use are table B
      // but the table B and A columns are reversed compared to data_rows_overlay() as the comparison is B vs A not base vs overlay (AvB)
      // so the columns to match as base and overlay are reversed, comared to other calls to data_row_overlay_primary_key_index()
      $primary_key_index = xml_parser::data_row_overlay_primary_key_index($table_b_primary_keys, $table_b_data_rows_columns, $table_a_data_rows_columns);
      $base_pklookup = array();
      $i = 0;
      if (count($primary_key_index['base'])) {
        foreach ($table_b_data_rows->row as $base_row) {
          $s = '';
          foreach ($primary_key_index['base'] as $index) {
            $s .= ':'.$base_row->col[$index];
          }
          $base_pklookup[$s] = $i++;
        }
      }

      $table_b_index = 0;
      foreach($table_a_data_rows->row AS $table_a_data_row) {

        $s = '';
        foreach ($primary_key_index['overlay'] as $index) {
          $s .= ':'.$table_a_data_row->col[$index];
        }

        if (array_key_exists($s, $base_pklookup)) {
          $match = TRUE;
          $table_b_index = $base_pklookup[$s];
        }
        else {
          $match = FALSE;
        }
        
        if ( $match ) {
          // we found a match
//echo "rows match\n";  var_dump($table_a_data_row);  var_dump($table_b_data_row);

          $table_b_data_row = &$table_b_data_rows->row[$table_b_index];

          if ( $in_both ) {
            // looking for rows in both

            // is the row marked for delete in A?
            if ( self::table_data_row_deleted($table_a_data_row) ) {
              // don't return changes, we are looking for rows in_both
            }
            // is the row marked for delete in B?
            else if ( self::table_data_row_deleted($table_b_data_row) ) {
              // don't return changes, we are looking for rows in_both
            }
            else {
              // do table data row diff, add rows to return by reference rows for both A and B
              $changed_columns = array();
              mssql10_diff_tables::table_data_row_diff($table_a_data_rows_columns, $table_a_data_row, $table_b_data_rows_columns, $table_b_data_row, $changed_columns);
              $a_rows[] = $table_a_data_row;
              $b_rows[] = $table_b_data_row;
              $changes[] = $changed_columns;
            }
          }
          else {
            // is the A row marked for delete?
            if ( self::table_data_row_deleted($table_a_data_row) ) {
              // there was a match, but we are looking for A not in B, A row is marked deleted, don't return it
            }
            // is the B row marked for delete?
            else if ( self::table_data_row_deleted($table_b_data_row) ) {
              // there was a match
              // A is not deleted
              // we are looking for A not in B
              // B is deleted
              $a_rows[] = $table_a_data_row;
            }
          }
        }
        else {
// echo "rows don't match\n";  var_dump($table_a_data_row);  var_dump($table_b_data_row);
          // no match
          if ( ! $in_both ) {
            // looking for A not in B
            if ( self::table_data_row_deleted($table_a_data_row) ) {
              // but the A row is marked deleted, don't return it
            }
            else {
              $a_rows[] = $table_a_data_row;
            }
          }
        }
      }
    }
  }

  protected function table_data_row_deleted($row) {
    if ( isset($row['delete']) && strcasecmp($row['delete'], 'true') == 0 ) {
      return true;
    }
    return false;
  }

  protected static function get_data_row_delete($db_doc, $schema, $table, $data_row_columns, $data_row, &$sql) {
    $sql = sprintf(
      "DELETE FROM %s.%s WHERE (%s);\n",
      mssql10::get_quoted_schema_name($schema['name']), mssql10::get_quoted_table_name($table['name']),
      dbx::primary_key_expression(dbsteward::$old_database, $schema, $table, $data_row_columns, $data_row)
    );
  }

  protected static function get_data_row_insert($node_schema, $node_table, $data_row_columns, $data_row) {
    $columns = '';
    $values = '';
    $data_row_columns_count = count($data_row_columns);
    for ($i = 0; $i < $data_row_columns_count; $i++) {
      $data_column_name = $data_row_columns[$i];
      $columns .= mssql10::get_quoted_column_name($data_column_name) . ', ';

      $value = mssql10::column_value_default($node_schema, $node_table, $data_column_name, $data_row->col[$i]);

      $values .= $value . ', ';
    }
    $columns = substr($columns, 0, -2);
    $values = substr($values, 0, -2);

    $sql = sprintf("INSERT INTO %s.%s (%s) VALUES (%s);\n", mssql10::get_quoted_schema_name($node_schema['name']), mssql10::get_quoted_table_name($node_table['name']), $columns, $values);

    return $sql;
  }

  protected static function get_data_row_update($node_schema, $node_table, $old_data_row_columns, $old_data_row, $new_data_row_columns, $new_data_row, $changed_columns) {
    if (count($changed_columns) == 0) {
      throw new exception("empty changed_columns passed");
    }

    // what columns from new_data_row are different in old_data_row?
    // those are the ones to push through the update statement to make the database current
    $old_columns = '';
    $update_columns = '';

    foreach ($changed_columns AS $changed_column) {
      if (!isset($changed_column['old_col'])) {
        $old_columns .= 'NOTDEFINED, ';
      }
      else {
        $old_col_value = mssql10::column_value_default($node_schema, $node_table, $changed_column['name'], $changed_column['old_col']);
        $old_columns .= $changed_column['name'] . ' = ' . $old_col_value . ', ';
      }
      $update_col_name = mssql10::get_quoted_column_name($changed_column['name']);
      $update_col_value = mssql10::column_value_default($node_schema, $node_table, $changed_column['name'], $changed_column['new_col']);
      $update_columns .= $update_col_name . ' = ' . $update_col_value . ', ';
    }

    // if the computed update_columns expression is < 5 chars, complain
    if (strlen($update_columns) < 5) {
      var_dump($update_columns);
      throw new exception(sprintf("%s.%s update_columns is < 5 chars, unexpected", $node_schema['name'], $node_table['name']));
    }

    // kill trailing ', '
    $update_columns = substr($update_columns, 0, -2);
    $old_columns = substr($old_columns, 0, -2);

    // use multiline comments here, so when data has newlines they can be preserved, but upgrade scripts don't catch on fire
    $sql = sprintf(
      "UPDATE %s.%s SET %s WHERE (%s); /* old values: %s */\n",
      mssql10::get_quoted_schema_name($node_schema['name']), mssql10::get_quoted_table_name($node_table['name']),
      $update_columns,
      dbx::primary_key_expression(dbsteward::$new_database, $node_schema, $node_table, $new_data_row_columns, $new_data_row),
      $old_columns
    );

    return $sql;
  }

  /**
   * Outputs commands for differences in constraints.
   *
   * @param object  $ofs              output file pointer
   * @param object  $old_schema       original schema
   * @param object  $new_schema       new schema
   * @param string  $type             type of constraints to process
   * @param boolean $drop_constraints
   */
  public static function diff_constraints($ofs, $old_schema, $new_schema, $type, $drop_constraints) {
    foreach (dbx::get_tables($new_schema) as $new_table) {
      if ($old_schema == NULL) {
        $old_table = NULL;
      }
      else {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }

      mssql10_diff_tables::diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints);
    }
  }

  public static function diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints = FALSE) {
    if ($drop_constraints) {
      // drop constraints that no longer exist or are modified
      foreach (mssql10_diff_tables::get_drop_constraints($old_schema, $old_table, $new_schema, $new_table, $type) as $constraint) {
        $ofs->write(mssql10_table::get_constraint_drop_sql($constraint) . "\n");
      }
    }
    else {
      // if it is a renamed table, remove all constraints and recreate with new table name conventions
      if ( mssql10_diff_tables::is_renamed_table($new_schema, $new_table) ) {
        foreach(mssql10_constraint::get_table_constraints(dbsteward::$old_database, $old_schema, $old_table, $type) as $constraint) {
          // rewrite the constraint definer to refer to the new table name
          // so the constraint by the old name, but part of the new table
          // will be referenced properly in the drop statement
          $constraint['table_name'] = $new_table['name'];
          $ofs->write(mssql10_table::get_constraint_drop_sql($constraint) . "\n");
        }
        
        // add all defined constraints back to the new table
        foreach(mssql10_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type) as $constraint) {
          $ofs->write(mssql10_table::get_constraint_sql($constraint) . "\n");
        }
        return;
      }
      // END if it is a renamed table, remove all constraints and recreate with new table name conventions

      // add new constraints
      foreach (mssql10_diff_tables::get_new_constraints($old_schema, $old_table, $new_schema, $new_table, $type) as $constraint) {
        $ofs->write(mssql10_table::get_constraint_sql($constraint) . "\n");
      }
    }
  }

  /**
   * Returns list of constraints that should be dropped.
   *
   * @param old_table original table or null
   * @param new_table new table or null
   * @param type whether primary keys should be processed or other constraints should be processed
   *
   * @return array of constraints that should be dropped
   *
   * @todo Constraints that are depending on a removed field should not be
   *       added to drop because they are already removed.
   */
  protected static function get_drop_constraints($old_schema, $old_table, $new_schema, $new_table, $type) {
    $list = array();

    if (($new_table != NULL) && ($old_table != NULL)) {
      if ($old_table->getName() != 'table') {
        throw new exception("Unexpected element type: " . $old_table->getName() . " panicing");
      }
      foreach (mssql10_constraint::get_table_constraints(dbsteward::$old_database, $old_schema, $old_table, $type) as $constraint) {
        $new_constraint = mssql10_constraint::get_table_constraint(dbsteward::$new_database, $new_schema, $new_table, $constraint['name']);

        if (!mssql10_table::contains_constraint(dbsteward::$new_database, $new_schema, $new_table, $constraint['name'])
          || !mssql10_table::constraint_equals($new_constraint, $constraint)) {
          $list[] = $constraint;
        }
      }
    }

    return $list;
  }

  /**
   * Returns list of constraints that should be added.
   *
   * @param old_table original table
   * @param new_table new table
   * @param type whether primary keys should be processed or other constraints should be processed
   *
   * @return list of constraints that should be added
   */
  protected static function get_new_constraints($old_schema, $old_table, $new_schema, $new_table, $type) {
    $list = array();

    if ($new_table != NULL) {
      if ($old_table == NULL) {
        foreach (mssql10_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type) as $constraint) {
          $list[] = $constraint;
        }
      }
      else {
        foreach (mssql10_constraint::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type) as $constraint) {
          $old_constraint = mssql10_constraint::get_table_constraint(dbsteward::$old_database, $old_schema, $old_table, $constraint['name']);

          if (!mssql10_table::contains_constraint(dbsteward::$old_database, $old_schema, $old_table, $constraint['name'])
           || !mssql10_table::constraint_equals($old_constraint, $constraint)) {
            $list[] = $constraint;
          }
        }
      }
    }

    return $list;
  }

  /**
   * Outputs DDL for addition, removal and modifications of table columns
   *
   * @param $ofs1       stage1 output file segmenter
   * @param $ofs3       stage3 output file segmenter
   * @param $old_table  original table
   * @param $new_table  new table
   */
  public static function diff_tables($ofs1, $ofs3, $old_schema, $new_schema, $old_table_target = null, $new_table_target = null) {
    self::create_tables($ofs1, $old_schema, $new_schema, $old_table_target, $new_table_target);
    
    // were specific tables passed?
    if ( $old_table_target !== null || $new_table_target !== null ) {
      $old_table = $old_table_target;
      $new_table = $new_table_target;

      if ( $old_table && $new_table) {
        mssql10_diff_tables::update_table_columns($ofs1, $ofs3, $old_table, $new_schema, $new_table);
        mssql10_diff_tables::add_alter_statistics($ofs1, $old_table, $new_schema, $new_table);
      }
    }
    else {
      foreach(dbx::get_tables($new_schema) as $new_table) {
        if ( !$old_schema ) {
          // old_schema not defined
          continue;
        }
  
        $old_table = dbx::get_table($old_schema, $new_table['name']);
        
        dbx::renamed_table_check_pointer($old_schema, $old_table, $new_schema, $new_table);
        
        if ( !$old_table ) {
          // old_table not defined
          continue;
        }
  
        mssql10_diff_tables::update_table_columns($ofs1, $ofs3, $old_table, $new_schema, $new_table);
        mssql10_diff_tables::add_alter_statistics($ofs1, $old_table, $new_schema, $new_table);
      }
    }
  }
}

?>
