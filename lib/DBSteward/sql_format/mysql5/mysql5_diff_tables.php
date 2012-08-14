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
        static::update_table_columns($ofs1, $ofs3, $old_table, $new_schema, $new_table);
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
  
        static::update_table_columns($ofs1, $ofs3, $old_table, $new_schema, $new_table);
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
        if ( !dbsteward::$ignore_oldname && mysql5_diff_tables::is_renamed_table($old_schema, $new_schema, $table) ) {
          // oldName renamed table ? rename table instead of create new one
          $old_table_name = mysql5::get_fully_qualified_table_name($new_schema['name'], $table['oldName']);
          // ALTER TABLE ... RENAME TO does not accept schema qualifiers when renaming a table
          // ALTER TABLE message.message_report RENAME TO report ;
          $new_table_name = mysql5::get_quoted_table_name($table['name']);
          $ofs->write("-- table rename from oldName specification" . "\n"
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
  private static function update_table_columns($ofs1, $ofs3, $old_table, $new_schema, $new_table) {
    $commands = array();
    $drop_defaults_columns = array();
    static::add_drop_table_columns($commands, $old_table, $new_table);
    static::add_create_table_columns($commands, $old_table, $new_schema, $new_table, $drop_defaults_columns);
    static::add_modify_table_columns($commands, $old_table, $new_schema, $new_table, $drop_defaults_columns);

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

      $quotedTableName = mysql5::get_fully_qualified_table_name($new_schema['name'], $new_table['name']);

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
        $stage1_sql = substr($stage1_sql, 0, -3) . ";\n";
        $ofs1->write($stage1_sql);
      }
      if ( strlen($stage3_sql) > 0 ) {
        $stage3_sql = substr($stage3_sql, 0, -3) . ";\n";
        $ofs3->write($stage3_sql);
      }

      if (count($drop_defaults_columns) > 0) {
        $ofs1->write("\n");
        $ofs1->write("ALTER TABLE " . $quotedTableName . "\n");

        $alters = array_map(function($c){
          return "\tALTER COLUMN " . mysql5::get_quoted_column_name($c['name']) . " DROP DEFAULT";
        }, $drop_defaults_columns);
        $ofs->write(implode(",\n", $alters).";\n");
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

  /**
   * Adds commands for removal of columns to the list of commands.
   *
   * @param commands list of commands
   * @param old_table original table
   * @param new_table new table
   */
  // @TODO: pull up
  private static function add_drop_table_columns(&$commands, $old_table, $new_table) {
    foreach(dbx::get_table_columns($old_table) as $old_column) {
      if (!mysql5_table::contains_column($new_table, $old_column['name'])) {
        if ( !dbsteward::$ignore_oldname && ($renamed_column_name = mysql5_table::column_name_by_old_name($new_table, $old_column['name'])) !== false ) {
          // table indicating oldName = table['name'] present in new schema? don't do DROP statement
          $old_table_name = mysql5::get_quoted_table_name($old_table['name']);
          $old_column_name = mysql5::get_quoted_column_name($old_column['name']);
          $commands[] = array(
            'stage' => 'AFTER3',
            'command' => "-- $old_table_name DROP COLUMN $old_column_name omitted: new column $renamed_column_name indicates it is the replacement for " . $old_column_name
          );
        }
        else {
          //echo "NOTICE: add_drop_table_columns()  " . $new_table['name'] . " does not contain " . $old_column['name'] . "\n";
          $commands[] = array(
            'stage' => '3',
            'command' => "\tDROP COLUMN " . mysql5::get_quoted_column_name($old_column['name'])
          );
        }
      }
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
  // @TODO: pull up
  private static function add_create_table_columns(&$commands, $old_table, $new_schema, $new_table, &$drop_defaults_columns) {
    foreach(dbx::get_table_columns($new_table) as $new_column) {
      if (!mysql5_table::contains_column($old_table, $new_column['name'])) {
        if ( !dbsteward::$ignore_oldname && mysql5_diff_tables::is_renamed_column($old_table, $new_table, $new_column) ) {
          // oldName renamed column ? rename column instead of create new one
          $old_column_name = mysql5::get_quoted_column_name($new_column['oldName']);
          // $new_column_name = mysql5::get_quoted_column_name($new_column['name']);
          $to = mysql5_column::get_full_definition(dbsteward::$new_database, $new_schema, $new_table, $new_column, mysql5_diff::$add_defaults);
          $commands[] = array(
            'stage' => 'AFTER1',
            'command' => "-- column rename from oldName specification\n"
              . "ALTER TABLE " . mysql5::get_fully_qualified_table_name($new_schema['name'], $new_table['name'])
              . " CHANGE COLUMN $old_column_name $to;"
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
          'command' => "\tADD COLUMN " . mysql5_column::get_full_definition(dbsteward::$new_database, $new_schema, $new_table, $new_column, mysql5_diff::$add_defaults, false)
        );
        // instead we put the NOT NULL defintion in stage3 schema changes once data has been updated in stage2 data
        if ( ! pgsql8_column::null_allowed($new_table, $new_column) ) {
          $commands[] = array(
            'stage' => '3',
            'command' => "\tALTER COLUMN " . mysql5::get_quoted_column_name($new_column['name']) . " SET NOT NULL"
          );
          // also, if it's defined, default the column in stage 1 so the SET NULL will actually pass in stage 3
          if ( strlen($new_column['default']) > 0 ) {
            $commands[] = array(
              'stage' => 'AFTER1',
              'command' => "UPDATE " . mysql5::get_fully_qualified_table_name($new_schema['name'], $new_table['name'])
                . " SET " . mysql5::get_quoted_column_name($new_column['name']) . " = DEFAULT"
                . " WHERE " . mysql5::get_quoted_column_name($new_column['name']) . " IS NULL;"
            );
          }

        }

        if (mysql5_diff::$add_defaults && !mysql5_column::null_allowed($new_table, $new_column)) {
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
   * Adds commands for modification of columns to the list of
   * commands.
   *
   * @param commands list of commands
   * @param old_table original table
   * @param new_table new table
   * @param drop_defaults_columns list for storing columns for which default value should be dropped
   */
  // @TODO: pull up
  private static function add_modify_table_columns(&$commands, $old_table, $new_schema, $new_table, &$drop_defaults_columns) {
    foreach(dbx::get_table_columns($new_table) as $new_column) {
      if (!mysql5_table::contains_column($old_table, $new_column['name'])) {
        continue;
      }
      if ( !dbsteward::$ignore_oldname && mysql5_diff_tables::is_renamed_column($old_table, $new_table, $new_column) ) {
        // oldName renamed column ? skip definition diffing on it, it is being renamed
        continue;
      }

      $old_column = dbx::get_table_column($old_table, $new_column['name']);
      $new_column_name = mysql5::get_quoted_column_name($new_column['name']);

      $old_column_type = null;
      if ( $old_column ) {
        $old_column_type = mysql5_column::column_type(dbsteward::$old_database, $new_schema, $old_table, $old_column);
      }
      $new_column_type = mysql5_column::column_type(dbsteward::$new_database, $new_schema, $new_table, $new_column);

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
        if (mysql5_column::null_allowed($new_table, $new_column)) {
          $commands[] = array(
            'stage' => '1',
            'command' => "\tALTER COLUMN " . $new_column_name . " DROP NOT NULL"
          );
        } else {
          if (mysql5_diff::$add_defaults) {
            $default_value = mysql5_column::get_default_value($new_column_type);

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
              'command' => "UPDATE " . mysql5::get_fully_qualified_table_name($new_schema['name'], $new_table['name'])
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
      if ( mysql5_column::is_serial($old_column['type']) &&
           ($new_column['type'] == 'int' || $new_column['type'] == 'bigint') ) {

          $commands[] = array(
            'stage' => 'BEFORE3',
            'command' => mysql5_sequence::get_drop_sql($new_schema, mysql5_column::get_serial_sequence_name($new_schema, $new_table, $new_column))
          );

          $commands[] = array(
            'stage' => '1',
            'command' => "\tALTER COLUMN " . $new_column_name . " DROP DEFAULT"
          );
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
          if ( !dbsteward::$ignore_oldname && is_object($new_schema)
            && ($renamed_table_name = mysql5_schema::table_name_by_old_name($new_schema, $table['name'])) !== false ) {
            // table indicating oldName = table['name'] present in new schema? don't do DROP statement
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
      dbx::primary_key_expression($schema, $table, $data_row_columns, $data_row)
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
      dbx::primary_key_expression($node_schema, $node_table, $new_data_row_columns, $new_data_row),
      $old_columns
    );

    return $sql;
  }
}