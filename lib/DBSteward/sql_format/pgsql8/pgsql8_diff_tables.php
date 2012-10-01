<?php
/**
 * Diffs tables.
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_diff_tables extends sql99_diff_tables {

  /**
   * Generates and outputs CLUSTER specific DDL if appropriate.
   *
   * @param $ofs         output file pointer
   * @param $old_schema  original schema
   * @param $new_schema  new schema
   */
  public static function diff_clusters($ofs, $old_schema, $new_schema) {
    foreach(dbx::get_tables($new_schema) as $new_table) {
      if ($old_schema == null) {
        $old_table = null;
      } else {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }

      self::diff_clusters_table($ofs, $old_schema, $old_table, $new_schema, $new_table);
    }
  }

  public static function diff_clusters_table($ofs, $old_schema, $old_table, $new_schema, $new_table) {
    if ($old_table == null) {
      $old_cluster = null;
    } else {
      $old_cluster = isset($old_table['clusterIndex']) ? $old_table['clusterIndex'] : null;
    }

    $new_cluster = isset($new_table['clusterIndex']) ? $new_table['clusterIndex'] : null;

    if ((($old_cluster == null) && ($new_cluster != null)) ||
        (($old_cluster != null) && ($new_cluster != null) && (strcmp($new_cluster, $old_cluster) != 0) )) {
      $ofs->write("ALTER TABLE "
        . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names)
        . " CLUSTER ON "
        . pgsql8_diff::get_quoted_name($new_cluster, dbsteward::$quote_column_names)
        . ";\n");
    } else if (($old_cluster != null) && ($new_cluster == null) && pgsql8_table::contains_index($new_schema, $new_table, $old_cluster)) {
      $ofs->write("ALTER TABLE "
        . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($table['name'], dbsteward::$quote_table_names)
        . " SET WITHOUT CLUSTER;" . "\n");
    }
  }

  /**
   * Outputs DDL for addition, removal and modifications of table columns
   *
   * @param $ofs1       stage1 output pointer
   * @param $ofs3       stage3 output pointer
   * @param $old_schema original schema
   * @param $new_schema new schema
   */
  public static function diff_tables($ofs1, $ofs3, $old_schema, $new_schema, $old_table_target = null, $new_table_target = null) {
    self::create_tables($ofs1, $old_schema, $new_schema, $old_table_target, $new_table_target);
    
    // were specific tables passed?
    if ( $old_table_target !== null || $new_table_target !== null ) {
      $old_table = $old_table_target;
      $new_table = $new_table_target;

      if ( $old_table && $new_table) {
        self::update_table_columns($ofs1, $ofs3, $old_table, $new_schema, $new_table);
        self::check_inherits($ofs1, $old_table, $new_schema, $new_table);
        self::add_alter_statistics($ofs1, $old_table, $new_schema, $new_table);
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
  
        self::update_table_columns($ofs1, $ofs3, $old_table, $new_schema, $new_table);
        self::check_inherits($ofs1, $old_table, $new_schema, $new_table);
        self::add_alter_statistics($ofs1, $old_table, $new_schema, $new_table);
      }
    }
  }

  /**
   * Generate the needed alter table xxx set statistics when needed.
   *
   * @param $ofs       output file pointer
   * @param $old_table original table
   * @param $new_table new table
   */
  private static function add_alter_statistics($ofs, $old_table, $new_schema, $new_table) {
    $stats = array();

    foreach(dbx::get_table_columns($new_table) as $new_column) {
      $old_column = dbx::get_table_column($old_table, $new_column['name']);

      if ($old_column != null) {
        $new_stat_value = null;

        if (($new_column['statistics'] != null) && ($old_column['statistics'] == null || $new_column['statistics'] != $old_column['statistics']) ) {
          $new_stat_value = $new_column['statistics'];
        } else if ($old_column['statistics'] != null && $new_column['statistics'] == null) {
          $new_stat_value = -1;
        }

        if ($new_stat_value !== null) {
          $stats[$new_column['name']] = $new_stat_value;
        }
      }
    }

    foreach($stats as $key => $value) {
      $ofs->write("\n" .
        "ALTER TABLE ONLY "
        . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($key, dbsteward::$quote_table_names)
        . " ALTER COLUMN " . pgsql8_diff::get_quoted_name($key, dbsteward::$quote_column_names)
        . " SET STATISTICS "
        . $value
        . ";\n");
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
    foreach(dbx::get_table_columns($new_table) as $new_column) {
      if (!pgsql8_table::contains_column($old_table, $new_column['name'])) {
        if ( !dbsteward::$ignore_oldname && pgsql8_diff_tables::is_renamed_column($old_table, $new_table, $new_column) ) {
          // oldName renamed column ? rename column instead of create new one
          $old_column_name = pgsql8_diff::get_quoted_name($new_column['oldName'], dbsteward::$quote_column_names);
          $new_column_name = pgsql8_diff::get_quoted_name($new_column['name'], dbsteward::$quote_column_names);
          $commands[] = array(
            'stage' => 'AFTER1',
            'command' => "-- column rename from oldName specification\n"
              . "ALTER TABLE " . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . "." . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names)
              . " RENAME COLUMN $old_column_name TO $new_column_name;"
          );
          continue;
        }
        
        // notice $include_null_definition is false
        // this is because ADD COLUMNs with NOT NULL will fail when there are existing rows


/* @DIFFTOOL for FS#15997 - look for columns of a certain type being added
if ( preg_match('/time|date/i', $new_column['type']) > 0 ) {
  echo $new_schema . "." . $new_table['name'] . "." . $new_column['name'] . " TYPE " . $new_column['type'] . " " . $new_column['default'] . "\n";
}
/**/

        $commands[] = array(
          'stage' => '1',
          'command' => "\tADD COLUMN " . pgsql8_column::get_full_definition(dbsteward::$new_database, $new_schema, $new_table, $new_column, pgsql8_diff::$add_defaults, false)
        );
        // instead we put the NOT NULL defintion in stage3 schema changes once data has been updated in stage2 data
        if ( ! pgsql8_column::null_allowed($new_table, $new_column) ) {
          $commands[] = array(
            'stage' => '3',
            'command' => "\tALTER COLUMN " . pgsql8_diff::get_quoted_name($new_column['name'], dbsteward::$quote_column_names) . " SET NOT NULL"
          );
          // also, if it's defined, default the column in stage 1 so the SET NULL will actually pass in stage 3
          if ( strlen($new_column['default']) > 0 ) {
            $commands[] = array(
              'stage' => 'AFTER1',
              'command' => "UPDATE " . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . "." . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names)
                . " SET " . pgsql8_diff::get_quoted_name($new_column['name'], dbsteward::$quote_column_names) . " = DEFAULT"
                . " WHERE " . pgsql8_diff::get_quoted_name($new_column['name'], dbsteward::$quote_column_names) . " IS NULL;"
            );
          }

        }

        // FS#15997 - dbsteward - replica inconsistency on added new columns with default now()
        // slony replicas that add columns via DDL that have a default of NOW() will be out of sync
        // because the data in those columns is being placed in as a default by the local db server
        // to compensate, add UPDATE statements to make the these column's values NOW() from the master
        if ( pgsql8_column::has_default_now($new_table, $new_column) ) {
          $commands[] = array(
              'stage' => 'AFTER1',
              'command' => "UPDATE " . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . "." . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names)
                . " SET " . pgsql8_diff::get_quoted_name($new_column['name'], dbsteward::$quote_column_names) . " = " . $new_column['default'] . " ; -- has_default_now: this statement is to make sure new columns are in sync on replicas"
            );
        }

        if (pgsql8_diff::$add_defaults && !pgsql8_column::null_allowed($new_table, $new_column)) {
          $drop_defaults_columns[] = $new_column;
        }

        // some columns need filled with values before any new constraints can be applied
        // this is accomplished by defining arbitrary SQL in the column element afterAddPre/PostStageX attribute
        $db_doc_new_schema = dbx::get_schema(dbsteward::$new_database, $new_schema['name']);
        if ( $db_doc_new_schema ) {
          $db_doc_new_table = dbx::get_table($db_doc_new_schema, $new_table['name']);
          if ( $db_doc_new_table ) {
            $db_doc_new_column = dbx::get_table_column($db_doc_new_table, $new_column['name']);
            if ( $db_doc_new_column ) {
              if ( isset($db_doc_new_column['beforeAddStage1']) ) {
                $commands[] = array(
                  'stage' => 'BEFORE1',
                  'command' => trim($db_doc_new_column['beforeAddStage1']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " beforeAddStage1 definition"
                );
              }
              if ( isset($db_doc_new_column['afterAddStage1']) ) {
                $commands[] = array(
                  'stage' => 'AFTER1',
                  'command' => trim($db_doc_new_column['afterAddStage1']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " afterAddStage1 definition"
                );
              }
              if ( isset($db_doc_new_column['beforeAddStage3']) ) {
                $commands[] = array(
                  'stage' => 'BEFORE3',
                  'command' => trim($db_doc_new_column['beforeAddStage3']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " beforeAddStage3 definition"
                );
              }
              if ( isset($db_doc_new_column['afterAddStage3']) ) {
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
    foreach(dbx::get_table_columns($old_table) as $old_column) {
      if (!pgsql8_table::contains_column($new_table, $old_column['name'])) {
        if ( !dbsteward::$ignore_oldname && ($renamed_column_name = pgsql8_table::column_name_by_old_name($new_table, $old_column['name'])) !== false ) {
          // table indicating oldName = table['name'] present in new schema? don't do DROP statement
          $old_table_name = pgsql8_diff::get_quoted_name($old_table['name'], dbsteward::$quote_table_names);
          $old_column_name = pgsql8_diff::get_quoted_name($old_column['name'], dbsteward::$quote_column_names);
          $commands[] = array(
            'stage' => 'AFTER3',
            'command' => "-- $old_table_name DROP COLUMN $old_column_name omitted: new column $renamed_column_name indicates it is the replacement for " . $old_column_name
          );
        }
        else {
          //echo "NOTICE: add_drop_table_columns()  " . $new_table['name'] . " does not contain " . $old_column['name'] . "\n";

          $commands[] = array(
            'stage' => '3',
            'command' => "\tDROP COLUMN " . pgsql8_diff::get_quoted_name($old_column['name'], dbsteward::$quote_column_names)
          );
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
    foreach(dbx::get_table_columns($new_table) as $new_column) {
      if (!pgsql8_table::contains_column($old_table, $new_column['name'])) {
        continue;
      }
      if ( !dbsteward::$ignore_oldname && pgsql8_diff_tables::is_renamed_column($old_table, $new_table, $new_column) ) {
        // oldName renamed column ? skip definition diffing on it, it is being renamed
        continue;
      }

      $old_column = dbx::get_table_column($old_table, $new_column['name']);
      $new_column_name = pgsql8_diff::get_quoted_name($new_column['name'], dbsteward::$quote_column_names);

      $old_column_type = null;
      if ( $old_column ) {
        $old_column_type = pgsql8_column::column_type(dbsteward::$old_database, $new_schema, $old_table, $old_column, $foreign);
      }
      $new_column_type = pgsql8_column::column_type(dbsteward::$new_database, $new_schema, $new_table, $new_column, $foreign);

      if ( preg_match(pgsql8::PATTERN_TABLE_LINKED_TYPES, $new_column_type) > 0 && $old_column_type !== null && preg_match(pgsql8::PATTERN_TABLE_LINKED_TYPES, $old_column_type) == 0) {
        throw new Exception("Column types cannot be altered to serial. If this column cannot be recreated as part of database change control, a user defined serial should be created, and corresponding nextval() defined as the default for the column.");
      }

      if ( strcmp($old_column_type, $new_column_type) != 0 ) {
        // ALTER TYPE .. USING support by looking up the new type in the xml definition
        $type_using = '';
        $type_using_comment = '';
        if ( isset($new_column['convertUsing']) ) {
          $type_using = ' USING ' . $new_column['convertUsing'] . ' ';
          $type_using_comment = '- found XML convertUsing: ' . $new_column['convertUsing'] . ' ';
        }

        $commands[] = array(
          'stage' => '1',
          'command' => "\tALTER COLUMN " . $new_column_name
            . " TYPE " . $new_column_type
            . $type_using
            . " /* TYPE change - table: " . $new_table['name'] . " original: " . $old_column_type . " new: " . $new_column_type . ' ' . $type_using_comment . '*/'
        );
      }

      $old_default = isset($old_column['default']) ? $old_column['default'] : '';
      $new_default = isset($new_column['default']) ? $new_column['default'] : '';

      if (strcmp($old_default, $new_default) != 0) {
        if (strlen($new_default) == 0) {
          $commands[] = array(
            'stage' => '1',
            'command' => "\tALTER COLUMN " . $new_column_name . " DROP DEFAULT"
          );
        } else {
          $commands[] =
          array(
            'stage' => '1',
            'command' => "\tALTER COLUMN " . $new_column_name . " SET DEFAULT " . $new_default
          );
        }
      }

      if ( strcasecmp($old_column['null'], $new_column['null']) != 0 ) {
        if (pgsql8_column::null_allowed($new_table, $new_column)) {
          $commands[] = array(
            'stage' => '1',
            'command' => "\tALTER COLUMN " . $new_column_name . " DROP NOT NULL"
          );
        } else {
          if (pgsql8_diff::$add_defaults) {
            $default_value = pgsql8_column::get_default_value($new_column_type);

            if ($default_value != null) {
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
              'command' => "UPDATE " . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . "." . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names)
                . " SET " . $new_column_name . " = " . $new_column['default'] . " WHERE " . $new_column_name . " IS NULL; -- has_default_now: make modified column that is null the default value before NOT NULL hits"
            );
          }

          $commands[] = array(
            'stage' => '3',
            'command' => "\tALTER COLUMN " . $new_column_name . " SET NOT NULL"
          );
        }
      }

      // drop sequence and default if converting from *serial to *int
      if ( preg_match('/serial$/', $old_column['type']) > 0 &&
           ($new_column['type'] == 'int' || $new_column['type'] == 'bigint') ) {

          $commands[] = array(
            'stage' => 'BEFORE3',
            'command' => "DROP SEQUENCE IF EXISTS " . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name(pgsql8::identifier_name($new_schema['name'], $new_table['name'], $new_column['name'], '_seq'), dbsteward::$quote_table_names) . ";"
          );

          $commands[] = array(
            'stage' => '1',
            'command' => "\tALTER COLUMN " . $new_column_name . " DROP DEFAULT"
          );
      }
    }
  }

  /**
   * Checks whether there is a discrepancy in INHERITS for originaland new table.
   *
   * @param $ofs        output file pointer
   * @param $old_table  original table
   * @param $new_schema new table
   * @param $new_table  new table
   */
  private static function check_inherits($ofs, $old_table, $new_schema, $new_table) {
    $old_inherits = isset($old_table['inherits']) ? $old_table['inherits'] : null;
    $new_inherits = isset($new_table['inherits']) ? $new_table['inherits'] : null;

    if (($old_inherits == null) && ($new_inherits != null)) {
      throw new exception("Modified INHERITS on TABLE "
        . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names)
        . ": original table doesn't use INHERITS but new table uses INHERITS " . $new_inherits);
    } else if (($old_inherits != null) && ($new_inherits == null)) {
      throw new exception("Modified INHERITS on TABLE "
        . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names)
        . ": original table uses INHERITS " . $old_inherits . " but new table doesn't use INHERITS");
    } else if (($old_inherits != null) && ($new_inherits != null) && $old_inherits != $new_inherits) {
      throw new exception("Modified INHERITS on TABLE "
        . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names)
        . ": original table uses INHERITS " . $old_inherits . " but new table uses INHERITS " . $new_inherits);
    }
  }

  /**
   * Outputs commands for creation of new tables.
   *
   * @param $ofs         output file pointer
   * @param $old_schema  original schema
   * @param $new_schema  new schema
   */
  private static function create_tables($ofs, $old_schema, $new_schema, $old_table = null, $new_table = null) {
    foreach(dbx::get_tables($new_schema) as $table) {
      if ( $new_table != null ) {
        if ( strcasecmp($table['name'], $new_table['name']) != 0 ) {
          continue;
        }
      }
      if (($old_schema == null) || !pgsql8_schema::contains_table($old_schema, $table['name'])) {
        if ( !dbsteward::$ignore_oldname && pgsql8_diff_tables::is_renamed_table($old_schema, $new_schema, $table) ) {
          // oldName renamed table ? rename table instead of create new one
          $old_table_name = pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($table['oldName'], dbsteward::$quote_table_names);
          // ALTER TABLE ... RENAME TO does not accept schema qualifiers when renaming a table
          // ALTER TABLE message.message_report RENAME TO report ;
          $new_table_name = pgsql8_diff::get_quoted_name($table['name'], dbsteward::$quote_table_names);
          $ofs->write("-- table rename from oldName specification" . "\n"
            . "ALTER TABLE $old_table_name RENAME TO $new_table_name ;" . "\n");
        }
        else {
          $ofs->write(pgsql8_table::get_creation_sql($new_schema, $table, dbsteward::$quote_column_names) . "\n");
        }
      }
    }
  }

  /**
   * Outputs commands for dropping tables.
   *
   * @param $ofs         output file pointer
   * @param $old_schema  original schema
   * @param $new_schema  new schema
   */
  public static function drop_tables($ofs, $old_schema, $new_schema, $old_table = null, $new_table = null) {
    if ($old_schema != null) {
      foreach(dbx::get_tables($old_schema) as $table) {
        if ( $old_table != null ) {
          if ( strcasecmp($table['name'], $old_table['name']) != 0 ) {
            continue;
          }
        }
        // if the schema is not in the new definition
        // skip diffing table drops, they were destroyed with the schema
        if ( $new_schema == NULL ) {
          continue;
        }
        if (!pgsql8_schema::contains_table($new_schema, $table['name'])) {
          // if new schema is still defined, check for renamed table
          // new_schema will be null if the new schema is no longer defined at all
          if ( !dbsteward::$ignore_oldname && is_object($new_schema)
            && ($renamed_table_name = pgsql8_schema::table_name_by_old_name($new_schema, $table['name'])) !== false ) {
            // table indicating oldName = table['name'] present in new schema? don't do DROP statement
            $old_table_name = pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($table['name'], dbsteward::$quote_table_names);
            $ofs->write("-- DROP TABLE $old_table_name omitted: new table $renamed_table_name indicates it is the replacement for " . $old_table_name . "\n");
          }
          else {
            $ofs->write(pgsql8_table::get_drop_sql($old_schema, $table) . "\n");
          }
        }
      }
    }
  }

  /**
   * Outputs commands for addition, removal and modifications of
   * table columns.
   *
   * @param $ofs1       stage1 output file segmenter
   * @param $ofs3       stage3 output file segmenter
   * @param $old_table  original table
   * @param $new_table  new table
   */
  private static function update_table_columns($ofs1, $ofs3, $old_table, $new_schema, $new_table) {
    $commands = array();
    $drop_defaults_columns = array();
    self::add_drop_table_columns($commands, $old_table, $new_table);
    self::add_create_table_columns($commands, $old_table, $new_schema, $new_table, $drop_defaults_columns);
    self::add_modify_table_columns($commands, $old_table, $new_schema, $new_table, $drop_defaults_columns);

    if (count($commands) > 0) {
      // do 'pre' 'entire' statements before aggregate table alterations
      for($i=0; $i < count($commands); $i++) {
        if ( $commands[$i]['stage'] == 'BEFORE1' ) {
          $ofs1->write($commands[$i]['command'] . "\n");
        }
        else if ( $commands[$i]['stage'] == 'BEFORE3' ) {
          $ofs3->write($commands[$i]['command'] . "\n");
        }
      }

      $quotedTableName = pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names);

      $stage1_sql = '';
      $stage3_sql = '';

      for($i=0; $i < count($commands); $i++) {
        if ( !isset($commands[$i]['stage']) || !isset($commands[$i]['command']) ) {
          var_dump($commands[$i]);
          throw new exception("bad command format");
        }

        if ( $commands[$i]['stage'] == '1' ) {
          // we have a stage 1 alteration to make
          // do the alter table prefix if we haven't yet
          if ( strlen($stage1_sql) == 0 ) {
            $stage1_sql = "ALTER TABLE " . $quotedTableName . "\n";
          }
          $stage1_sql .= $commands[$i]['command'] . " ,\n";
        }
        else if ( $commands[$i]['stage'] == '3' ) {
          // we have a stage 3 alteration to make
          // do the alter table prefix if we haven't yet
          if ( strlen($stage3_sql) == 0 ) {
            $stage3_sql = "ALTER TABLE " . $quotedTableName . "\n";
          }
          $stage3_sql .= $commands[$i]['command'] . " ,\n";
        }
      }

      if ( strlen($stage1_sql) > 0 ) {
        // slony will make the alter table statement changes as its super user
        // which if the db owner is different,
        // implicit sequence creation will fail with:
        // ERROR:  55000: sequence must have same owner as table it is linked to
        // so if the alter statement contains a new serial column,
        // change the user to the slony user for the alter, then (see similar block below)
        if ( isset($new_table['slonyId']) && strlen($new_table['slonyId']) > 0
          && stripos($stage1_sql, 'serial') !== false ) {
          // if replication user is defined, check if ownership switch is needed
          if ( strlen(dbsteward::$new_database->database->role->replication) > 0 ) {
            if ( strcasecmp(dbsteward::$new_database->database->role->owner, dbsteward::$new_database->database->role->replication) != 0 ) {
              $alter = "ALTER TABLE " . $quotedTableName . " OWNER TO " . dbsteward::$new_database->database->role->replication . "; -- dbsteward: postgresql needs to be appeased by making the owner the user we are executing as when pushing DDL through slony\n";
              $ofs1->write($alter);
            }
          }
        }

        $stage1_sql = substr($stage1_sql, 0, -3) . ";\n";
        $ofs1->write($stage1_sql);

        // replicated table? put ownership back (see full exp above)
        if ( isset($new_table['slonyId']) && strlen($new_table['slonyId']) > 0
          && stripos($stage1_sql, 'serial') !== false ) {
          // if replication user is defined, check ownership switchback
          if ( strlen(dbsteward::$new_database->database->role->replication) > 0 ) {
            if ( strcasecmp(dbsteward::$new_database->database->role->owner, dbsteward::$new_database->database->role->replication) != 0 ) {
              $alter = "ALTER TABLE " . $quotedTableName . " OWNER TO " . dbsteward::$new_database->database->role->owner . "; -- dbsteward: postgresql has been appeased (see above)\n";
              $ofs1->write($alter);
            }
          }
        
          // we are here because a serial column was added, the application will need permissions on the sequence
          foreach(dbx::get_permissions($new_table) AS $sa_permission) {
            // re-grant all permissions, because permssions run through pgsql8_permission::get_sql()
            // will do implicit serial column permissions to match table permissions
            $ofs1->write(pgsql8_permission::get_sql(dbsteward::$new_database, $new_schema, $new_table, $sa_permission) . "\n");
          }
        }
      }
      if ( strlen($stage3_sql) > 0 ) {
        $stage3_sql = substr($stage3_sql, 0, -3) . ";\n";
        $ofs3->write($stage3_sql);
      }

      if (count($drop_defaults_columns) > 0) {
        $ofs1->write("\n");
        $ofs1->write("ALTER TABLE " . $quotedTableName . "\n");

        for ($i=0; $i < count($drop_defaults_columns); $i++) {
          $ofs1->write("\tALTER COLUMN "
            . pgsql8_diff::get_quoted_name($drop_defaults_columns[$i]['name'], dbsteward::$quote_column_names)
            . " DROP DEFAULT");
          if ($i <count($drop_defaults_columns) - 1) {
            $ofs1->write(",\n");
          } else {
            $ofs1->write(";\n");
          }
        }
      }

      // do 'post' 'entire' statements immediately following aggregate table alterations
      for($i=0; $i < count($commands); $i++) {
        if ( $commands[$i]['stage'] == 'BEFORE1' ) {
          // already taken care of in earlier entire command output loop
        }
        else if ( $commands[$i]['stage'] == 'BEFORE3' ) {
          // already taken care of in earlier entire command output loop
        }
        else if ( $commands[$i]['stage'] == '1' ) {
          // already taken care of in earlier command aggregate loop
        }
        else if ( $commands[$i]['stage'] == '3' ) {
          // already taken care of in earlier command aggregate loop
        }
        else if ( $commands[$i]['stage'] == 'AFTER1' ) {
          $ofs1->write($commands[$i]['command'] . "\n");
        }
        else if ( $commands[$i]['stage'] == 'AFTER3' ) {
          $ofs3->write($commands[$i]['command'] . "\n");
        }
        else {
          throw new exception("Unknown stage " . $commands[$i]['stage'] . " during table " . $quotedTableName . " updates");
        }
      }
    }
  }

  public static function diff_data($ofs, $old_schema, $new_schema) {
    foreach(dbx::get_tables($new_schema) AS $new_table) {
      $old_table = null;
      // does the old contain the new?
      if ( $old_schema != null && pgsql8_schema::contains_table($old_schema, $new_table['name']) ) {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }
      $ofs->write(
        self::get_data_sql($old_schema, $old_table, $new_schema, $new_table)
      );
    }
  }

  public static function get_data_sql($old_schema, $old_table, $new_schema, $new_table, $delete_mode = false) {
    $sql = '';
    if ( $old_table == null ) {
      if ( !$delete_mode ) {
        // old table doesnt exist, pump inserts
        $new_table_rows = dbx::get_table_rows($new_table);
        if ( $new_table_rows ) {
          $new_table_row_columns = preg_split("/[\,\s]+/", $new_table_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
          foreach($new_table_rows->row AS $data_row) {
            // is the row marked for delete?
            if ( isset($data_row['delete']) && strcasecmp($data_row['delete'], 'true') == 0 ) {
              // don't insert it, we are inserting data that should be there
            }
            else {
              $sql .= self::get_data_row_insert($new_schema, $new_table, $new_table_row_columns, $data_row);
            }
          }
        }
        
        // set serial columns with serialStart defined to that value
        // this is done in get_data_sql to ensure the serial start is set post row insertion
        $sql .= pgsql8_column::get_serial_start_dml($new_schema, $new_table);
      }
    }
    else {
      // data row match scenarios are based on primary key matching

      $old_table_rows = dbx::get_table_rows($old_table);
      if ( $old_table_rows ) {
        $old_table_row_columns = preg_split("/[\,\s]+/", $old_table_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
      }

      // is caller asking for deletes or data updates?
      if ( $delete_mode ) {
        // what old rows have no matches in the new rows? delete them
        if ( $old_table_rows ) {
          self::table_data_rows_compare($old_table, $new_table, false, $old_rows, $new_rows, $changes);
          $count_old_rows = count($old_rows);
          for($i = 0; $i < $count_old_rows; $i++) {
            self::get_data_row_delete($old_schema, $old_table, $old_table_row_columns, $old_rows[$i], $sql_append); //@REVISIT
            $sql .= $sql_append;
          }
        }
      }
      else {
        $new_table_rows = dbx::get_table_rows($new_table);
        if ( $new_table_rows ) {
          $new_table_row_columns = preg_split("/[\,\s]+/", $new_table_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
        }

        // what columns in matching rows between old and new are different?
        if ( $old_table_rows && $new_table_rows ) {
          $new_table_primary_keys = preg_split("/[\,\s]+/", $new_table['primaryKey'], -1, PREG_SPLIT_NO_EMPTY);
          $primary_key_index = xml_parser::data_row_overlay_primary_key_index($new_table_primary_keys, $old_table_row_columns, $new_table_row_columns);

          self::table_data_rows_compare($old_table, $new_table, true, $old_rows, $new_rows, $changes);
          $count_old_rows = count($old_rows);
          for($i = 0; $i < $count_old_rows; $i++) {
            $new_data_row = null;
            $changed_columns = null;
            if ( count($changes[$i]) > 0 ) {
              // changes were found between primary key matched old_table_row and new_table_row
              // get the sql to make that happen
              $sql .= self::get_data_row_update($new_schema, $new_table, $primary_key_index, $old_table_row_columns, $old_rows[$i], $new_table_row_columns, $new_rows[$i], $changes[$i]);
            }
          }
        }

        // what new rows are missing from the old? insert them
        if ( $new_table_rows ) {
          self::table_data_rows_compare($new_table, $old_table, false, $new_rows, $old_rows, $changes);
          $count_new_rows = count($new_rows);
          for($i = 0; $i < $count_new_rows; $i++) {
            $sql .= self::get_data_row_insert($new_schema, $new_table, $new_table_row_columns, $new_rows[$i]);
          }
        }
      }
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
      $primary_keys = preg_split("/[\,\s]+/", $table_a['primaryKey'], -1, PREG_SPLIT_NO_EMPTY);
      $primary_key_index = xml_parser::data_row_overlay_primary_key_index($primary_keys, $table_a_data_rows_columns, $table_b_data_rows_columns);
      $table_b_index = 0;
      foreach($table_a_data_rows->row AS $table_a_data_row) {

        $match = xml_parser::data_row_overlay_key_search($table_b_data_rows, $table_a_data_row, $primary_key_index, $table_b_index);

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
              pgsql8_diff_tables::table_data_row_diff($table_a_data_rows_columns, $table_a_data_row, $table_b_data_rows_columns, $table_b_data_row, $changed_columns);
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

  /**
   * is there a difference between old_row and new_row?
   *
   * also returns columns with differences in $change_columns by reference
   *
   * @return boolean   there is a difference between old and new data rows
   */
  public function table_data_row_diff($old_cols, $old_row, $new_cols, $new_row, &$changed_columns) {
    $difference = false;

    // compare the columns between the old and new rows
    // determining difference status
    // storing the difference as we go
    $difference = false;
    $changed_columns = array();
    $new_cols_count = count($new_cols);
    for($i=0; $i < $new_cols_count; $i++) {
      $old_col_index = array_search($new_cols[$i], $old_cols);

      if ( $old_col_index === false ) {
        // overlay col $i not found in $old_cols
        $difference = true;

        // record differences for caller to use
        $changed_columns[] = array(
          'name' => $new_cols[$i],
          'new_col' => $new_row->col[$i]
        );
      }
      else {
        if ( strcmp($old_row->col[$old_col_index], $new_row->col[$i]) != 0 ) {
          // base_row->col value does not match overlay_row->col value
          $difference = true;

          // record differences for caller to use
          $changed_columns[] = array(
            'name' => $new_cols[$i],
            'old_col' => $old_row->col[$old_col_index],
            'new_col' => $new_row->col[$i]
          );
        }
      }
    }
    return $difference;
  }

  private static function get_data_row_delete($schema, $table, $data_row_columns, $data_row, &$sql) {
    $sql = sprintf(
      "DELETE FROM %s.%s WHERE (%s);\n",
      pgsql8_diff::get_quoted_name($schema['name'], dbsteward::$quote_schema_names),
      pgsql8_diff::get_quoted_name($table['name'], dbsteward::$quote_table_names),
      dbx::primary_key_expression($schema, $table, $data_row_columns, $data_row)
    );
  }

  private static function get_data_row_insert($node_schema, $node_table, $data_row_columns, $data_row) {
    $columns = '';
    $values = '';
    $data_row_columns_count = count($data_row_columns);
    for($i=0; $i < $data_row_columns_count; $i++) {
      $data_column_name = $data_row_columns[$i];

      if ( dbsteward::$quote_column_names ) {
        $columns .= '"' . $data_column_name . '", ';
      }
      else {
        $columns .= $data_column_name . ', ';
      }

      $value = pgsql8::column_value_default($node_schema, $node_table, $data_column_name, $data_row->col[$i]);

      $values .= $value . ', ';
    }
    $columns = substr($columns, 0, -2);
    $values = substr($values, 0, -2);

    $sql = sprintf(
      "INSERT INTO %s.%s (%s) VALUES (%s);\n",
      pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names),
      pgsql8_diff::get_quoted_name($node_table['name'], dbsteward::$quote_table_names),
      $columns,
      $values
    );

    return $sql;
  }

  protected static function get_data_row_update($node_schema, $node_table, $primary_key_index, $old_data_row_columns, $old_data_row, $new_data_row_columns, $new_data_row, $changed_columns) {
    if ( count($changed_columns) == 0 ) {
      throw new exception("empty changed_columns passed");
    }

    // what columns from new_data_row are different in old_data_row?
    // those are the ones to push through the update statement to make the database current
    $old_columns = '';
    $update_columns = '';

    foreach($changed_columns AS $changed_column) {
      if ( !isset($changed_column['old_col']) ) {
        $old_columns .= 'NOTDEFINED, ';
      }
      else {
        $old_col_value = pgsql8::column_value_default($node_schema, $node_table, $changed_column['name'], $changed_column['old_col']);
        $old_columns .= $changed_column['name'] . ' = ' . $old_col_value . ', ';
      }
      $update_col_name = pgsql8_diff::get_quoted_name($changed_column['name'], dbsteward::$quote_column_names);
      $update_col_value = pgsql8::column_value_default($node_schema, $node_table, $changed_column['name'], $changed_column['new_col']);
      $update_columns .= $update_col_name . ' = ' . $update_col_value . ', ';
    }

    // if the computed update_columns expression is < 5 chars, complain
    if ( strlen($update_columns) < 5 ) {
      var_dump($update_columns);
      throw new exception(sprintf("%s.%s update_columns is < 5 chars, unexpected", $node_schema['name'], $node_table['name']));
    }

    // kill trailing ', '
    $update_columns = substr($update_columns, 0, -2);
    $old_columns = substr($old_columns, 0, -2);

    // use multiline comments here, so when data has newlines they can be preserved, but upgrade scripts don't catch on fire
    $sql = sprintf(
      "UPDATE %s.%s SET %s WHERE (%s); /* old values: %s */\n",
      pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names),
      pgsql8_diff::get_quoted_name($node_table['name'], dbsteward::$quote_table_names),
      $update_columns,
      dbx::primary_key_expression($node_schema, $node_table, $new_data_row_columns, $new_data_row),
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
    foreach(dbx::get_tables($new_schema) as $new_table) {
      if ($old_schema == null) {
        $old_table = null;
      } else {
        $old_table = dbx::get_table($old_schema, $new_table['name']);
      }

      self::diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints);
    }
  }

  public static function diff_constraints_table($ofs, $old_schema, $old_table, $new_schema, $new_table, $type, $drop_constraints = false) {
    if ( $drop_constraints ) {
      // drop constraints that no longer exist or are modified
      foreach(self::get_drop_constraints($old_schema, $old_table, $new_schema, $new_table, $type) as $constraint) {
        $ofs->write(pgsql8_table::get_constraint_drop_sql($constraint) . "\n");
      }
    }
    else {
      if ( !dbsteward::$ignore_oldname ) {
        // if it is a renamed table, remove all constraints and recreate with new table name conventions
        if ( pgsql8_diff_tables::is_renamed_table($old_schema, $new_schema, $new_table) ) {
          $old_named_table = dbx::get_renamed_table_old_table($old_schema, $old_table, $new_schema, $new_table);
          foreach(dbx::get_table_constraints(dbsteward::$old_database, $old_schema, $old_table, $type) as $constraint) {
            // rewrite the constraint definer to refer to the new table name
            // so the constraint by the old name, but part of the new table
            // will be referenced properly in the drop statement
            $constraint['table_name'] = $new_table['name'];
            $ofs->write(pgsql8_table::get_constraint_drop_sql($constraint) . "\n");
          }
          
          // add all defined constraints back to the new table
          foreach(dbx::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type) as $constraint) {
            $ofs->write(pgsql8_table::get_constraint_sql($constraint) . "\n");
          }
          return;
        }
        // END if it is a renamed table, remove all constraints and recreate with new table name conventions
      }

      // add new constraints
      foreach(self::get_new_constraints($old_schema, $old_table, $new_schema, $new_table, $type) as $constraint) {
        $ofs->write(pgsql8_table::get_constraint_sql($constraint) . "\n");
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
  private static function get_drop_constraints($old_schema, $old_table, $new_schema, $new_table, $type) {
    $list = array();

    if (($new_table != null) && ($old_table != null)) {
      if ( $old_table->getName() != 'table' ) {
        throw new exception("Unexpected element type: " . $old_table->getName() . " panicing");
      }
      foreach(dbx::get_table_constraints(dbsteward::$old_database, $old_schema, $old_table, $type) as $constraint) {
        $new_constraint = dbx::get_table_constraint(dbsteward::$new_database, $new_schema, $new_table, $constraint['name']);

        if ( !pgsql8_table::contains_constraint(dbsteward::$new_database, $new_schema, $new_table, $constraint['name'])
          || !pgsql8_table::constraint_equals($new_constraint, $constraint) ) {
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
  private static function get_new_constraints($old_schema, $old_table, $new_schema, $new_table, $type) {
    $list = array();

    if ($new_table != null) {
      if ($old_table == null) {
        foreach(dbx::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type) as $constraint) {
          $list[] = $constraint;
        }
      } else {
        foreach(dbx::get_table_constraints(dbsteward::$new_database, $new_schema, $new_table, $type) as $constraint) {
          $old_constraint = dbx::get_table_constraint(dbsteward::$old_database, $old_schema, $old_table, $constraint['name']);

          if ( !pgsql8_table::contains_constraint(dbsteward::$old_database, $old_schema, $old_table, $constraint['name'])
            || !pgsql8_table::constraint_equals($old_constraint, $constraint) ) {
            $list[] = $constraint;
          }
        }
      }
    }

    return $list;
  }

}

?>
