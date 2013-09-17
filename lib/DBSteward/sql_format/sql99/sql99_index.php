<?php
/**
 * Manipulate table index definition nodes
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_index {
  public static function equals($node_index_a, $node_index_b) {
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
      else {
        $equal = true;
      }
    }
    return $equal;
  }

  public static function get_using_option_sql($using) {
    $using = strtoupper((string)$using);
    return $using;
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

  public static function get_table_indexes($node_schema, $node_table) {
    $nodes = $node_table->xpath("index");
    // add column unique indexes to the list
    foreach ($node_table->column AS $column) {
      if (isset($column['unique']) && strcasecmp($column['unique'], 'true') == 0) {
        $unique_index = new SimpleXMLElement('<index/>');
        $unique_index['name'] = static::index_name($node_table['name'], $column['name'], 'key');
        $unique_index['unique'] = 'true';
        $unique_index['using'] = 'btree';
        $unique_index->addChild('indexDimension', $column['name'])
          ->addAttribute('name', $column['name'] . '_unq');
        $nodes[] = $unique_index;
      }
    }

    $names = array();
    foreach ($nodes as $node) {
      if (in_array((string)$node['name'], $names)) {
        throw new Exception("Duplicate index name {$node['name']} on table {$node_schema['name']}.{$node_table['name']}");
      }
      else {
        $names[] = (string)$node['name'];
      }
    }
    return $nodes;
  }
  
}
