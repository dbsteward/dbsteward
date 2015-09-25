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
    $table_len = strlen($table);
    $column_len = strlen($column);
    $suffix_len = strlen($suffix);

    // figure out how to build "{$table}_{$column}_{$suffix}"
    
    // reserve space for the suffix, at least one underscore
    $maxlen = pgsql8::MAX_IDENTIFIER_LENGTH - $suffix_len - 1;
    if ($column_len > 0) {
      // if there's a column, add another underscore
      $maxlen -= 1;
    }

    $table_max = ceil($maxlen / 2);
    $column_max = floor($maxlen / 2);

    // table is longer than max, but column is shorter
    if ($table_len > $table_max && $column_len < $column_max) {
      // give table the extra room from column
      $table_max += $column_max - $column_len;
    }
    // table is shorter than max, but table is longer
    elseif ($table_len < $table_max && $column_len > $column_max) {
      // give column the extra room from table
      $column_max += $table_max - $table_len;
    }

    $table = substr($table, 0, min($table_max, $table_len));
    $column = substr($column, 0, min($column_max, $column_len));

    if ($column_len > 0) {
      return "{$table}_{$column}_{$suffix}";
    }
    return "{$table}_{$suffix}";
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
