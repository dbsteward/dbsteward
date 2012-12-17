<?php
/**
 * Manipulate table nodes
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class sql99_table {

  /**
   * Returns true if table contains given column $name, otherwise false.
   *
   * @param $name      name of the column
   *
   * @return boolean   true if table contains given column $name, otherwise false
   */
  public function contains_column($node_table, $name) {
    $found = false;

    foreach(dbx::get_table_columns($node_table) as $column) {
      if (strcasecmp($column['name'], $name) == 0) {
        $found = true;
        break;
      }
    }

    return $found;
  }

  public function contains_table_option($node_table, $name) {
    $format = dbsteward::get_sql_format();

    foreach ($node_table->tableOption as $option) {
      if (strcasecmp($option['sqlFormat'], $format) === 0 && strcasecmp($option['name'], $name) === 0) {
        return $option;
      }
    }

    return false;
  }

  /**
   * Returns true if table contains given constraint $name
   *
   * @param $name      name of the constraint
   *
   * @return boolean   true if table contains given constraint $name otherwise false
   */
  public static function contains_constraint($db_doc, $node_schema, $node_table, $name) {
    return format_constraint::get_table_constraint($db_doc, $node_schema, $node_table, $name) != null;
  }

  /**
   * Returns true if table contains given index $name, otherwise false.
   *
   * @param $name  name of the index
   *
   * @return true  if table contains given index $name, otherwise false
   */
  public function contains_index($node_schema, $node_table, $name) {
    $found = false;
    $indexes = dbx::get_table_indexes($node_schema, $node_table);

    foreach($indexes AS $index) {
      if (strcasecmp($index['name'], $name) == 0) {
        $found = true;
        break;
      }
    }

    return $found;
  }

  // map data row into the accumulated data the table has in the dump
  public function add_data_row(&$node_table, $row) {
    // sanity check alpha keys this table expects
    $data_columns = self::get_column_list($node_table);
    $row_data_columns = array_keys($row);
    $diff = array_diff($row_data_columns, $data_columns);
    if ( count($diff) > 0 ) {
      throw new exception("table " . $node_table['name'] . " (" . implode(',',$data_columns) . ") does not contain all columns specified in row_data_columns (" . implode(',',$row_data_columns) . ") diff: (" . implode(',',$diff) .")");
    }
    $pk_cols = self::primary_key_columns($node_table);
    // only check if it has primary key columns if the table has a primary key
    if ( count($pk_cols) > 0 ) {
      $diff = array_diff($pk_cols, $row_data_columns);
      if ( count($diff) > 0 ) {
        throw new exception("table " . $node_table['name'] . " rows element missing primary key column. diff: (" . implode(',',$diff) .")");
      }
    }

    // collect rows to add in $new_rows, index by row_data_columns
    // @TODO: the caller then data_rows_overlay()'s these all at once
    $new_rows = array();
    // $new_rows contains all of the variants of rows collections to add to the table
    // it is expected that there is a low number of insert statement variants
    if ( !isset($new_rows[$row_data_columns]) ) {
      $new_rows[$row_data_columns] = new SimpleXMLElement('<rows/>');
    }
    // have the xml_parser compositor add the row with it's overlay logic
    // which compensates for additional columns, missing columns
    // and all the rest of the variants for <rows> <row> elements
    $node_new_row = $new_rows[$row_data_columns]->addChild('row');
    foreach($row AS $column_name => $column_value) {
      $new_rows[$row_data_columns]['columns'] .= $column_name . ',';
      $node_new_row->addChild('col', xml_parser::ampersand_magic(sql_parser::quoted_value_strip($column_value)));
    }
    $new_rows[$row_data_columns]['columns'] = substr($new_rows[$row_data_columns]['columns'], 0, -1);
//var_dump($new_rows[$row_data_columns]->asXML());
//die('asdbasdfffff');

    // see @TODO: above caller collation
    xml_parser::data_rows_overlay($node_table, $new_rows[$row_data_columns]);
  }

  public static function get_column_list(&$node_table) {
    $cols = array();
    foreach(dbx::get_table_columns($node_table) as $node_column) {
      $cols[] = $node_column['name'];
    }
    return $cols;
  }

  public static function primary_key_columns(&$node_table) {
    if ( empty($node_table['primaryKey']) ) {
      return array();
    }
    return preg_split("/[\,\s]+/", $node_table['primaryKey'], -1, PREG_SPLIT_NO_EMPTY);
  }

  /**
  * @TODO: this needs rewritten to delete the XML document row instead
  */
  public function delete_data_row(&$node_table, $where) {
    // break up the clause by its parenthetical () and logical AND OR segmentation
    $clause = sql_parser::clause_explode($where);
    if ( !is_array($clause) ) {
      $clause = array($clause);
    }

    $new_table_rows = dbx::get_table_rows($new_table);
    $data_rows = $new_table_rows->row;
//dbsteward::console_line(7, "CLAUSE " . $where);
//dbsteward::console_line(7, "BEFORE this->data has " . count($data_rows) . " rows");
    for($i=0; $i < count($data_rows); $i++) {
      if ( self::clause_match($data_rows[$i], $clause) ) {
        unset($data_rows[$i]);
        $data_rows = array_merge($data_rows);  // redo array keys
        $i--;
      }
    }
//dbsteward::console_line(7, "AFTER  this->data has " . count($data_rows) . " rows");
  }

  protected static function clause_match($row, $clause) {
    // by default the row doesn't match
    // notice that we also break out of the match loop as soon as it doesn't match anymore
    // but make sure the default is false here
    $result = false;

    for($i=0; $i < count($clause); $i++) {
      // scan for arrays and recurse into them
      if ( is_array($clause[$i]) ) {
        $result = self::clause_match($row, $clause[$i]);
      }
      // scan for ANDs and ORs and break them up recursively
      // after the recursion has occurred on the before and after slices, break out of this loop
      else if ( strcasecmp('AND', $clause[$i]) == 0 || strcasecmp('OR', $clause[$i]) == 0 ) {
        $before = array_slice($clause, 0, $i);
        $after = array_slice($clause, $i + 1);

        if ( strcasecmp('AND', $clause[$i]) == 0 ) {
          $result = self::clause_match($row, $before) && self::clause_match($row, $after);
        }
        else if ( strcasecmp('OR', $clause[$i]) == 0 ) {
          $result = self::clause_match($row, $before) || self::clause_match($row, $after);
        }

        break;
      }
      else if ( in_array($clause[$i], array('=', '!=', '<>')) ) {
        // evaluate the clause operator against the row

        // is the left hand item a column in this row?
        if ( in_array($clause[$i - 1], array_keys($row)) ) {
          // then compare that column to the right hand side of the oper


          // SQL to PHP comparison operator filter
          $operator = $clause[$i];
          if ( $operator == '=' ) {
            $operator = '==';
          }
          else if ( $operator = '<>' ) {
            $operator = '!=';
          }

          $left_side = pgsql8::strip_escaping_e($row[$clause[$i - 1]]);    // row[col]'s value
          $right_side = pgsql8::strip_escaping_e($clause[$i+1]);           // comparison value
          $expression = $left_side . ' ' . $operator . ' ' . $right_side;
          $eval_expression = '$result = ( ' . $expression . ' ) ;';
          if ( eval($eval_expression) === false ) {
            throw new exception("eval() failed on eval_expression: " . $eval_expression);
          }
        }

        // if the row doesn't match anymore, stop trying
        if ( ! $result ) {
          break;
        }
      }
      else {
        //dbsteward::console_line(6, "clause[$i] = " . $clause[$i]);
        //$result = rand(1,5) != 2;
      }
    }

    return $result;
  }

  public function constraint_equals($constraint_a, $constraint_b) {
    if ( strcasecmp($constraint_a['name'], $constraint_b['name']) != 0 ) {
      return false;
    }
    $a_type = null;
    $b_type = null;
    if ( isset($constraint_a['type']) ) {
      $a_type = (string)$constraint_a['type'];
    }
    if ( isset($constraint_b['type']) ) {
      $b_type = (string)$constraint_b['type'];
    }
    if ( strcasecmp($a_type, $b_type) != 0 ) {
      return false;
    }

    $a_foreign_on_delete = null;
    $b_foreign_on_delete = null;
    if ( isset($constraint_a['foreignOnDelete']) ) {
      $a_foreign_on_delete = $constraint_a['foreignOnDelete'];
    }
    if ( isset($constraint_b['foreignOnDelete']) ) {
      $b_foreign_on_delete = $constraint_b['foreignOnDelete'];
    }
    if ( strcasecmp($a_foreign_on_delete, $b_foreign_on_delete) != 0 ) {
      return false;
    }

    $a_foreign_on_update = null;
    $b_foreign_on_update = null;
    if ( isset($constraint_a['foreignOnUpdate']) ) {
      $a_foreign_on_update = $constraint_a['foreignOnUpdate'];
    }
    if ( isset($constraint_b['foreignOnUpdate']) ) {
      $b_foreign_on_update = $constraint_b['foreignOnUpdate'];
    }
    if ( strcasecmp($a_foreign_on_update, $b_foreign_on_update) != 0 ) {
      return false;
    }

    $equals = strcasecmp($constraint_a['definition'], $constraint_b['definition']) == 0;

    return $equals;
  }
  
  /**
   * Returns name of column that says it used to be called $old_name
   *
   * @param   $old_name
   *
   * @return  string
   */
  public function column_name_by_old_name($node_table, $old_name) {
    if ( dbsteward::$ignore_oldname ) {
      throw new exception("dbsteward::ignore_oldname option is on, column_name_by_old_name() should not be getting called");
    }

    $name = false;

    foreach(dbx::get_table_columns($node_table) as $column) {
      if (strcasecmp($column['oldName'], $old_name) == 0) {
        $name = $column['name'];
        break;
      }
    }

    return $name;
  }

  /**
   * Retrieves an associative array of table options defined for a table
   * @param  SimpleXMLElement $node_schema The schema
   * @param  SimpleXMLElement $node_table  The table
   * @return array            Option name => option value
   */
  public static function get_table_options($node_schema, $node_table=false) {
    if (is_array($node_schema) && !$node_table) {
      $nodes = $node_schema;
    }
    else {
      $nodes = $node_table->tableOption;
    }
    $sql_format = dbsteward::get_sql_format();
    $opts = array();

    foreach ($nodes as $node) {
      if ($node['sqlFormat'] != $sql_format) continue;

      $name = strtolower((string)($node['name']));
      $value = (string)($node['value']);
      if (empty($name)) {
        throw new Exception("tableOption of table {$node_schema['name']}.{$node_table['name']} cannot have an empty name");
      }
      if (array_key_exists($name, $opts)) {
        throw new Exception("Duplicate tableOption '$name' in table {$node_schema['name']}.{$node_table['name']} is not allowed");
      }
      $opts[$name] = $value;
    }

    return $opts;
  }

  /**
   * Returns the SQL to append to a CREATE TABLE expression
   * @param  SimpleXMLElement $node_schema The schema
   * @param  SimpleXMLElement $node_table  The table
   * @return string           SQL
   */
  public static function get_table_options_sql($node_schema, $node_table=false) {
    $opts = static::get_table_options($node_schema, $node_table);
    $opt_sqls = array();
    foreach ($opts as $name => $val) {
      $opt_sqls[] = static::format_table_option($name, $val);
    }
    return static::join_table_option_sql($opt_sqls);
  }

  /**
   * Formats a single name-value table option pair
   * The default is to turn name=>value into "NAME value"
   * You should probably override this for each format, and vary by name
   * 
   * @param  string $name  The name of the table option
   * @param  string $value The value of the table option
   * @return string        The formatted SQL
   */
  protected static function format_table_option($name, $value) {
    return strtoupper($name) . ' ' . $value;
  }

  /**
   * Joins the formatted option SQL from format_table_option()
   * The default is to implode with a space.
   * You should probably override this for each format
   * 
   * @param  array $options The formatted table option SQL entries
   * @return string         The joined table option SQL
   */
  protected static function join_table_option_sql($options) {
    return implode("\n", $options);
  }
}

?>
