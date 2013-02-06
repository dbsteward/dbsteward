<?php
/**
 * Diffs tables.
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class mysql5_diff_tables extends sql99_diff_tables {
  /**
   * Outputs DDL for addition, removal and modifications of table columns
   *
   * @param $ofs1       stage1 output pointer
   * @param $ofs3       stage3 output pointer
   * @param $old_schema original schema
   * @param $new_schema new schema
   */
  // @TODO: pull up
  public static function diff_tables($ofs1, $ofs3, $old_schema, $new_schema, $old_table_target = null, $new_table_target = null) {
    static::create_tables($ofs1, $old_schema, $new_schema, $old_table_target, $new_table_target);
    
    // were specific tables passed?
    if ( $old_table_target !== null || $new_table_target !== null ) {
      $old_table = $old_table_target;
      $new_table = $new_table_target;

      if ( $old_table && $new_table) {
        static::update_table_options($ofs1, $ofs3, $old_schema, $old_table, $new_schema, $new_table);
        static::update_table_columns($ofs1, $ofs3, $old_schema, $old_table, $new_schema, $new_table);
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
  
        static::update_table_options($ofs1, $ofs3, $old_schema, $old_table, $new_schema, $new_table);
        static::update_table_columns($ofs1, $ofs3, $old_schema, $old_table, $new_schema, $new_table);
      }
    }
  }

  /**
   * Outputs commands for creation of new tables.
   *
   * @param $ofs         output file pointer
   * @param $old_schema  original schema
   * @param $new_schema  new schema
   */
  // @TODO: pull up
  private static function create_tables($ofs, $old_schema, $new_schema, $old_table = null, $new_table = null) {
    foreach(dbx::get_tables($new_schema) as $table) {
      if ( $new_table != null ) {
        if ( strcasecmp($table['name'], $new_table['name']) != 0 ) {
          continue;
        }
      }
      if (($old_schema == null) || !mysql5_schema::contains_table($old_schema, $table['name'])) {
        if ( !dbsteward::$ignore_oldnames && mysql5_diff_tables::is_renamed_table($new_schema, $table) ) {
          // oldTableName renamed table ? rename table instead of create new one
          $old_table_name = mysql5::get_fully_qualified_table_name($new_schema['name'], $table['oldTableName']);
          // 
          $new_table_name = mysql5::get_quoted_table_name($table['name']);
          $ofs->write("-- table rename from oldTableName specification" . "\n"
            . "ALTER TABLE $old_table_name RENAME TO $new_table_name ;" . "\n");
        }
        else {
          $ofs->write(mysql5_table::get_creation_sql($new_schema, $table, dbsteward::$quote_column_names) . "\n");
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
  private static function update_table_columns($ofs1, $ofs3, $old_schema, $old_table, $new_schema, $new_table) {

    // arbitrary sql
    $extra = array(
      'BEFORE1' => array(),
      'AFTER1' => array(),
      'BEFORE3' => array(),
      'AFTER3' => array()
    );

    // each entry is keyed by column name, and has a 'command' key, which may be one of
    //  nothing: do nothing
    //  drop: drop this column
    //  change: rename & redefine
    //  create: create this column
    //  modify: redefine without rename
    // the 'defaults' key is whether to give the column a DEFAULT clause if it is NOT NULL
    // the 'nulls' key is whether to include NULL / NOT NULL in the column definition
    $commands = array(
      '1' => array(),
      '3' => array()
    );

    // what to do with 
    $defaults = array(
      'set' => array(),    // in stage 1, ALTER TABLE new_table ALTER COLUMN new_column SET DEFAULT new_column[default] ?: getDefaultValue(type)
      'update' => array(), // after stage 1, UPDATE new_table SET new_column = DEFAULT WHERE new_column = DEFAULT
      'drop' => array()    // after that, ALTER TABLE new_table ALTER COLUMN new_column DROP DEFAULT
    );

    foreach(dbx::get_table_columns($old_table) as $old_column) {
      if (!mysql5_table::contains_column($new_table, $old_column['name'])) {
        if ( !dbsteward::$ignore_oldnames && ($renamed_column_name = mysql5_table::column_name_by_old_name($new_table, $old_column['name'])) !== false ) {
          continue;
        }
        else {
          // echo "NOTICE: add_drop_table_columns()  " . $new_table['name'] . " does not contain " . $old_column['name'] . "\n";
          $commands['3'][(string)$old_column['name']] = array(
            'command' => 'drop',
            'column' => $old_column
          );
        }
      }
    }

    $new_columns = dbx::get_table_columns($new_table);
    foreach ($new_columns as $col_index => $new_column) {
      $cmd1 = array(
        'command' => 'nothing',
        'column' => $new_column,
        'defaults' => mysql5_diff::$add_defaults,
        'nulls' => TRUE
      );

      $cmd3 = array(
        'command' => 'nothing',
        'column' => $new_column,
        'defaults' => mysql5_diff::$add_defaults,
        'nulls' => TRUE
      );

      if (!mysql5_table::contains_column($old_table, $new_column['name'])) {
        // column not present in old table, is either renamed or new
        if (!dbsteward::$ignore_oldnames && mysql5_diff_tables::is_renamed_column($old_table, $new_table, $new_column)) {
          // renamed
          $cmd1['command'] = 'change';
          $cmd1['old'] = $new_column['oldName'];
        }
        else {
          // new
          $cmd1['command'] = 'create';
          $cmd1['nulls'] = FALSE;

          if ($col_index == 0) {
            $cmd1['first'] = TRUE;
          }
          else {
            $cmd1['after'] = $new_columns[$col_index-1]['name'];
          }

          if (!mysql5_column::null_allowed($new_table, $new_column)) {
            $cmd3['command'] = 'modify';
            $cmd3['nulls'] = FALSE;

            if (strlen($new_column['default']) > 0) {
              $defaults['update'][] = $new_column;
            }

            if (mysql5_diff::$add_defaults) {
              $defaults['drop'][] = $new_column;
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
                    $extras['BEFORE1'][] = trim($db_doc_new_column['beforeAddStage1']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " beforeAddStage1 definition";
                  }
                  if ( isset($db_doc_new_column['afterAddStage1']) ) {
                    $extras['AFTER1'][] = trim($db_doc_new_column['afterAddStage1']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " afterAddStage1 definition";
                  }
                  if ( isset($db_doc_new_column['beforeAddStage3']) ) {
                    $extras['BEFORE3'][] = trim($db_doc_new_column['beforeAddStage3']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " beforeAddStage3 definition";
                  }
                  if ( isset($db_doc_new_column['afterAddStage3']) ) {
                    $extras['AFTER3'][] = trim($db_doc_new_column['afterAddStage3']) . " -- from " . $new_schema['name'] . "." . $new_table['name'] . "." . $new_column['name'] . " afterAddStage3 definition";
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
      else if ($old_column = dbx::get_table_column($old_table, $new_column['name'])) {
        $old_column_type = mysql5_column::column_type(dbsteward::$old_database, $old_schema, $old_table, $old_column);
        $new_column_type = mysql5_column::column_type(dbsteward::$new_database, $new_schema, $new_table, $new_column);

        $old_default = isset($old_column['default']) ? $old_column['default'] : '';
        $new_default = isset($new_column['default']) ? $new_column['default'] : '';

        $new_is_nullable = mysql5_column::null_allowed($new_table, $new_column);

        $type_changed = strcasecmp($old_column_type, $new_column_type) !== 0;
        $default_changed = strcasecmp($old_default, $new_default) !== 0;
        $nullable_changed = strcasecmp($old_column['null'] ?: 'true', $new_column['null'] ?: 'true') !== 0;

        // if the type changed, we need to redefine the column
        // if the column went from NOT NULL -> NULL, redefine in stage 1
        // if the column went from NULL -> NOT NULL, redefine in stage 3
        $cmd1['command'] = $type_changed || ($nullable_changed && $new_is_nullable) ? 'modify' : 'nothing';
        $cmd3['command'] = $nullable_changed && !$new_is_nullable ? 'modify' : 'nothing';

        if ($nullable_changed) {
          if (!$new_is_nullable) {
            if (!$new_default) {
              // if the column went from NULL to NOT NULL, and there is no default
              if (mysql5_diff::$add_defaults) {
                // update from NULL to type default
                $defaults['update'][] = $new_column;
                // make sure we don't redefine with forced defaults
                $cmd3['defaults'] = FALSE;
              }
            }
            else {
              // the column went from NULL to NOT NULL and there *is* a default
              if ($default_changed && !$type_changed) {
                // if the default changed or was added (but not dropped),
                // we need to set the new default. however, if the type did change,
                // the column will be redefined, so we don't need to set the default
                $defaults['set'][] = $new_column;
              }

              // regardless of type change or default change, NULLs are no longer allowed, 
              // and so we need to update existing rows from NULL -> DEFAULT
              $defaults['update'][] = $new_column;
            }
          }
          // else {
            // the column went from NOT NULL to NULL. regardless of changes to the default,
            // it will be redefined in stage 1.
          // }
        }
        else {
          if (!$type_changed && $default_changed) {
            // if the type was changed, the column will be redefined
            if ($new_default) {
              $defaults['set'][] = $new_column;
            }
            else {
              $defaults['drop'][] = $new_column;
            }
          }
        }
      }

      $commands['1'][(string)$new_column['name']] = $cmd1;
      $commands['3'][(string)$new_column['name']] = $cmd3;
    } // end foreach column

    $table_name = mysql5::get_fully_qualified_table_name($new_schema['name'], $new_table['name']);

    $get_command_sql = function ($command) use (&$new_schema, &$new_table) {
      if ($command['command'] == 'nothing') return NULL;

      if ($command['command'] == 'drop') {
        $name = mysql5::get_quoted_column_name($command['column']['name']);
        return "DROP COLUMN $name";
      }

      $defn = mysql5_column::get_full_definition(
        dbsteward::$new_database,
        $new_schema,
        $new_table,
        $command['column'],
        $command['defaults'],
        $command['nulls']
      );

      if ($command['command'] == 'change') {
        $old = mysql5::get_quoted_column_name($command['old']);
        return "CHANGE COLUMN $old $defn";
      }

      if ($command['command'] == 'create') {
        if (array_key_exists('first', $command)) {
          return "ADD COLUMN $defn FIRST";
        }
        elseif (array_key_exists('after', $command)) {
          $col = mysql5::get_quoted_column_name($command['after']);
          return "ADD COLUMN $defn AFTER $col";
        }
        else {
          return "ADD COLUMN $defn";
        }
      }

      if ($command['command'] == 'modify') {
        return "MODIFY COLUMN $defn";
      }

      throw new Exception("Invalid column diff command '{$command['command']}'");
    };

    // pre-stage SQL
    foreach ($extra['BEFORE1'] as $sql) {
      $ofs1->write($sql."\n\n");
    }

    foreach ($extra['BEFORE3'] as $sql) {
      $ofs3->write($sql."\n\n");
    }

    // output stage 1 sql
    $stage1_commands = array();
    foreach ($commands['1'] as $column_name => $command) {
      $stage1_commands[] = $get_command_sql($command);
    }
    // we can also add SET DEFAULTs in here
    foreach ($defaults['set'] as $column) {
      $name = mysql5::get_quoted_column_name($column['name']);

      if (strlen($column['default']) > 0) {
        $default = (string)$column['default'];
      }
      else {
        $type = mysql5_column::column_type(dbsteward::$new_database, $new_schema, $new_table, $column);
        $default = mysql5_column::get_default_value($type);
      }

      $stage1_commands[] = "ALTER COLUMN $name SET DEFAULT $default";
    }
    $stage1_commands = array_filter($stage1_commands);

    if (count($stage1_commands) > 0) {
      $sql = "ALTER TABLE $table_name\n  ";
      $sql .= implode(",\n  ", $stage1_commands);
      $sql .= ";\n\n";
      $ofs1->write($sql);
    }

    // output stage 3 sql
    $stage3_commands = array();
    foreach ($commands['3'] as $column_name => $command) {
      $stage3_commands[] = $get_command_sql($command);
    }
    $stage3_commands = array_filter($stage3_commands);

    if (count($stage3_commands) > 0) {
      $sql = "ALTER TABLE $table_name\n  ";
      $sql .= implode(",\n  ", $stage3_commands);
      $sql .= ";\n\n";
      $ofs3->write($sql);
    }

    // update defaults, if any
    foreach ($defaults['update'] as $column) {
      $name = mysql5::get_quoted_column_name($column['name']);
      if (strlen($column['default']) > 0) {
        $default = (string)$column['default'];
      }
      else {
        $type = mysql5_column::column_type(dbsteward::$new_database, $new_schema, $new_table, $column);
        $default = mysql5_column::get_default_value($type);
      }
      $ofs1->write("UPDATE $table_name SET $name = $default WHERE $name IS NULL;\n\n");
    }

    // drop defaults, if any
    if (count($defaults['drop']) > 0) {
      $drops = array();
      foreach ($defaults['drop'] as $column) {
        $name = mysql5::get_quoted_column_name($column['name']);
        $drops[] = "ALTER COLUMN $name DROP DEFAULT";
      }
      $ofs1->write("ALTER TABLE $table_name\n  " . implode(",\n  ", $drops) . ";\n\n");
    }

    // post-stage SQL
    foreach ($extra['AFTER1'] as $sql) {
      $ofs1->write($sql."\n\n");
    }

    foreach ($extra['AFTER3'] as $sql) {
      $ofs3->write($sql."\n\n");
    }
  }

  /**
   * Outputs commands for dropping tables.
   *
   * @param $ofs         output file pointer
   * @param $old_schema  original schema
   * @param $new_schema  new schema
   */
  // @TODO: pull up
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
        if (!mysql5_schema::contains_table($new_schema, $table['name'])) {
          // if new schema is still defined, check for renamed table
          // new_schema will be null if the new schema is no longer defined at all
          if ( !dbsteward::$ignore_oldnames && is_object($new_schema)
            && ($renamed_table_name = mysql5_schema::table_name_by_old_name($new_schema, $table['name'])) !== false ) {
            // table indicating oldTableName = table['name'] present in new schema? don't do DROP statement
            $old_table_name = mysql5::get_fully_qualified_table_name($new_schema['name'], $table['name']);
            $ofs->write("-- DROP TABLE $old_table_name omitted: new table $renamed_table_name indicates it is the replacement for " . $old_table_name . "\n");
          }
          else {
            $ofs->write(mysql5_table::get_drop_sql($old_schema, $table) . "\n");
          }
        }
      }
    }
  }

  // @TODO: pull up to sql99_diff_tables?
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
              $sql .= static::get_data_row_insert($new_schema, $new_table, $new_table_row_columns, $data_row);
            }
          }
        }
        
        // set serial columns with serialStart defined to that value
        // this is done in get_data_sql to ensure the serial start is set post row insertion
        $sql .= mysql5_column::get_serial_start_dml($new_schema, $new_table);
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
          static::table_data_rows_compare($old_table, $new_table, false, $old_rows, $new_rows, $changes);
          $count_old_rows = count($old_rows);
          for($i = 0; $i < $count_old_rows; $i++) {
            static::get_data_row_delete($old_schema, $old_table, $old_table_row_columns, $old_rows[$i], $sql_append); //@REVISIT
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
          $new_table_primary_keys = mysql5_table::primary_key_columns($new_table);
          $primary_key_index = xml_parser::data_row_overlay_primary_key_index($new_table_primary_keys, $old_table_row_columns, $new_table_row_columns);

          static::table_data_rows_compare($old_table, $new_table, true, $old_rows, $new_rows, $changes);
          $count_old_rows = count($old_rows);
          for($i = 0; $i < $count_old_rows; $i++) {
            $new_data_row = null;
            $changed_columns = null;
            if ( count($changes[$i]) > 0 ) {
              // changes were found between primary key matched old_table_row and new_table_row
              // get the sql to make that happen
              $sql .= static::get_data_row_update($new_schema, $new_table, $primary_key_index, $old_table_row_columns, $old_rows[$i], $new_table_row_columns, $new_rows[$i], $changes[$i]);
            }
          }
        }

        // what new rows are missing from the old? insert them
        if ( $new_table_rows ) {
          static::table_data_rows_compare($new_table, $old_table, false, $new_rows, $old_rows, $changes);
          $count_new_rows = count($new_rows);
          for($i = 0; $i < $count_new_rows; $i++) {
            $sql .= static::get_data_row_insert($new_schema, $new_table, $new_table_row_columns, $new_rows[$i]);
          }
        }
      }
    }
    return $sql;
  }

  // @TODO: pull this up to sql99_diff_tables
  private static function get_data_row_insert($node_schema, $node_table, $data_row_columns, $data_row) {
    $columns = array();
    $values = array();
    $data_row_columns_count = count($data_row_columns);

    for($i=0; $i < $data_row_columns_count; $i++) {
      $columns[] = mysql5::get_quoted_column_name($data_row_columns[$i]);
      $values[] = mysql5::column_value_default($node_schema, $node_table, $data_row_columns[$i], $data_row->col[$i]);
    }

    $columns = implode(', ', $columns);
    $values = implode(', ', $values);

    return sprintf(
      "INSERT INTO %s (%s) VALUES (%s);\n",
      mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']),
      $columns,
      $values
    );
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
      $primary_keys = mysql5_table::primary_key_columns($table_a);
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
              static::table_data_row_diff($table_a_data_rows_columns, $table_a_data_row, $table_b_data_rows_columns, $table_b_data_row, $changed_columns);
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

  /**
   * is there a difference between old_row and new_row?
   *
   * also returns columns with differences in $change_columns by reference
   *
   * @return boolean   there is a difference between old and new data rows
   */
  // @TODO: pull up
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

  // @TODO: pull up
  protected function table_data_row_deleted($row) {
    if ( isset($row['delete']) && strcasecmp($row['delete'], 'true') == 0 ) {
      return true;
    }
    return false;
  }

  // @TODO: pull up
  private static function get_data_row_delete($schema, $table, $data_row_columns, $data_row, &$sql) {
    $sql = sprintf(
      "DELETE FROM %s WHERE (%s);\n",
      format::get_fully_qualified_table_name($schema['name'],$table['name']),
      dbx::primary_key_expression(dbsteward::$old_database, $schema, $table, $data_row_columns, $data_row)
    );
  }

  // @TODO: pull up
  protected static function get_data_row_update($node_schema, $node_table, $primary_key_index, $old_data_row_columns, $old_data_row, $new_data_row_columns, $new_data_row, $changed_columns) {
    if ( count($changed_columns) == 0 ) {
      throw new exception("empty changed_columns passed");
    }

    // what columns from new_data_row are different in old_data_row?
    // those are the ones to push through the update statement to make the database current
    $old_columns = array();
    $update_columns = array();

    foreach($changed_columns AS $changed_column) {
      if ( !isset($changed_column['old_col']) ) {
        $old_columns[] = 'NOTDEFINED';
      }
      else {
        $old_col_value = format::column_value_default($node_schema, $node_table, $changed_column['name'], $changed_column['old_col']);
        $old_columns[] = $changed_column['name'] . ' = ' . $old_col_value;
      }

      $update_col_name = format::get_quoted_column_name($changed_column['name']);
      $update_col_value = format::column_value_default($node_schema, $node_table, $changed_column['name'], $changed_column['new_col']);
      $update_columns[] = $update_col_name . ' = ' . $update_col_value;
    }

    // if the computed update_columns expression is < 5 chars, complain
    // if ( strlen($update_columns) < 5 ) {
    //   var_dump($update_columns);
    //   throw new exception(sprintf("%s.%s update_columns is < 5 chars, unexpected", $node_schema['name'], $node_table['name']));
    // }

    $old_columns = implode(', ', $old_columns);
    $update_columns = implode(', ', $update_columns);

    // use multiline comments here, so when data has newlines they can be preserved, but upgrade scripts don't catch on fire
    $sql = sprintf(
      "UPDATE %s SET %s WHERE (%s); /* old values: %s */\n",
      format::get_fully_qualified_table_name($node_schema['name'], $node_table['name']),
      $update_columns,
      dbx::primary_key_expression(dbsteward::$new_database, $node_schema, $node_table, $new_data_row_columns, $new_data_row),
      $old_columns
    );

    return $sql;
  }

  public static function apply_table_options_diff($ofs1, $ofs3, $schema, $table, $alter_options, $create_options, $drop_options) {
    $fq_name = mysql5::get_fully_qualified_table_name($schema['name'], $table['name']);

    if (!empty($drop_options)) {
      // if there are any that are dropped, the table must be recreated
      // don't bother adding/changing the other options, since this will include them
      $names = array_map(function($o){ return $o['name']; }, $drop_options);
      $sql = "-- Table $fq_name must be recreated to drop options: " . implode(', ', $names) . "\n";
      $sql.= mysql5_diff_tables::get_recreate_table_sql($schema, $table);
      $ofs1->write($sql."\n");
    }
    else {
      $alter_create = array_merge($alter_options, $create_options);
      if (!empty($alter_create)) {
        $sql = "ALTER TABLE $fq_name ";
        $sql.= mysql5_table::get_table_options_sql($alter_create).";";
        $ofs1->write($sql."\n");
      }
    }
  }

  private static function get_recreate_table_sql($schema, $table) {
    $fq_name = mysql5::get_fully_qualified_table_name($schema['name'], $table['name']);
    $fq_tmp_name = mysql5::get_fully_qualified_table_name($schema['name'], $table['name'].'_DBSTEWARD_MIGRATION');

    // utilize MySQL's CREATE TABLE ... SELECT syntax for cleaner recreation
    // see: http://dev.mysql.com/doc/refman/5.5/en/create-table-select.html
    $sql = "CREATE TABLE $fq_tmp_name";

    $opt_sql = mysql5_table::get_table_options_sql($schema, $table);
    if (!empty($opt_sql)) {
      $sql .= "\n" . $opt_sql;
    }

    if ( strlen($table['description']) > 0 ) {
      $sql .= "\nCOMMENT " . mysql5::quote_string_value($table['description']);
    }

    $sql .= "\nSELECT * FROM $fq_name;\n";

    $sql .= "DROP TABLE $fq_name;\n";
    $sql .= "RENAME TABLE $fq_tmp_name TO $fq_name;";

    return $sql;
  }
}
?>
