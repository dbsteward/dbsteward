<?php
/**
 * Manipulate postgresql table index definition nodes
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_index extends sql99_index {

  /**
   * Creates and returns SQL for creation of the index.
   *
   * @return created SQL
   */
  public static function get_creation_sql($node_schema, $node_table, $node_index) {
    $sql = "CREATE ";

    if ( isset($node_index['unique']) && strcasecmp($node_index['unique'], 'true') == 0 ) {
      $sql .= "UNIQUE ";
    }

    $sql .= "INDEX "
      . pgsql8::get_quoted_object_name($node_index['name'])
      . " ON "
      . pgsql8::get_quoted_schema_name($node_schema['name']) . '.'
      . pgsql8::get_quoted_table_name($node_table['name']);
    if ( isset($node_index['using']) && strlen($node_index['using']) > 0 ) {
      $sql .= ' USING ' . $node_index['using'];
    }
    $sql .= ' (';
    foreach($node_index->indexDimension AS $dimension) {
      // if the index dimension is an expression and not a column, don't try to column quote it
      if ( preg_match('/[\(\,]+$/', $dimension) > 0 ) {
        $sql .= $dimension . ', ';
      }
      else {
        $sql .= pgsql8::get_quoted_column_name($dimension) . ', ';
      }
    }
    $sql = substr($sql, 0, -2);
    $sql .= ')';

    if ( isset($node_index->indexWhere) ) {
      $index_where_clause = NULL;
      foreach($node_index->indexWhere AS $node_index_where) {
        if ( empty($node_index_where['sqlFormat']) ) {
          throw new Exception("Attribute sqlFormat required for indexWhere definitions. See index '{$node_index['name']}' definition");
        }
        if ( $node_index_where['sqlFormat'] == dbsteward::get_sql_format() ) {
          if ( $index_where_clause !== NULL ) {
            throw new Exception("duplicate indexWhere definition for {$node_index_where['sqlFormat']} in index '{$node_index['name']}' definition");
          }
          $index_where_clause = (string)$node_index_where;
        }
      }
      if ( strlen($index_where_clause) > 0 ) {
        $sql .= " WHERE ( " . $index_where_clause . " )";
      }
    }
    
    $sql .= ';';

    return $sql;
  }

  public static function get_drop_sql($node_schema, $node_table, $node_index) {
    $ddl = "DROP INDEX " . pgsql8::get_quoted_schema_name($node_schema['name']) . "." . pgsql8::get_quoted_object_name($node_index['name']) . ";\n";
    return $ddl;
  }

  public static function equals($node_index_a, $node_index_b) {
    $equal = true;
    if ( strcasecmp($node_index_a['name'], $node_index_b['name']) != 0 ) {
      $equal = false;
    }
    else if ( strcasecmp($node_index_a['unique'], $node_index_b['unique']) != 0 ) {
      $equal = false;
    }
    else if ( strcasecmp($node_index_a['using'], $node_index_b['using']) != 0 ) {
      $equal = false;
    }
    else {
      $a_dimensions = '';
      foreach($node_index_a->indexDimension AS $dimension) {
        $a_dimensions .= $dimension . '|';
      }
      $b_dimensions = '';
      foreach($node_index_b->indexDimension AS $dimension) {
        $b_dimensions .= $dimension . '|';
      }
      if ( $a_dimensions != $b_dimensions ) {
        $equal = false;
      }
      
      // check indexWhere if the index is still determined to be equal
      if ( $equal && (isset($node_index_a->indexWhere) || isset($node_index_b->indexWhere)) ) {
        $a_where = '';
        if ( isset($node_index_a->indexWhere) ) {
          foreach($node_index_a->indexWhere AS $where) {
            if ( $where['sqlFormat'] == dbsteward::get_sql_format() ) {
              $a_where .= (string)$where;
            }
          }
        }
        $b_where = '';
        if ( isset($node_index_b->indexWhere) ) {
          foreach($node_index_b->indexWhere AS $where) {
            if ( $where['sqlFormat'] == dbsteward::get_sql_format() ) {
              $b_where .= (string)$where;
            }
          }
        }
        if ( strcmp($a_where, $b_where) != 0 ) {
          $equal = false;
        }
      }
    }
    return $equal;
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
}

?>
