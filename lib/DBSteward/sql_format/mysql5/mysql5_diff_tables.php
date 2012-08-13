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