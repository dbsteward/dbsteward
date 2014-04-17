<?php
/**
 * Manipulate table index definition nodes
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_index extends pgsql8_index {

  /**
   * Creates and returns SQL for creation of the index.
   *
   * @return created SQL
   */
  public static function get_creation_sql($node_schema, $node_table, $node_index) {
    try {
      $sql = "CREATE ";

      if (isset($node_index['unique']) && strcasecmp($node_index['unique'], 'true') == 0) {
        $sql .= "UNIQUE ";
      }

      $sql .= "INDEX " . mssql10::get_quoted_object_name($node_index['name']) . " ON " . mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_table['name']);
      if (isset($node_index['using']) && strlen($node_index['using']) > 0) {
        switch (strtolower($node_index['using'])) {
          case 'btree':
            // no knobs to turn in MSSQL for this pgsql index method

          break;
          default:
            throw new exception($node_schema['name'] . '.' . $node_table['name'] . " has index " . $node_index['name'] . " with unknown using type: " . $node_index['using']);
          break;
        }
      }

      $dimension_list = self::index_dimension_scan($node_schema, $node_table, $node_index, $add_column_sql);
      $sql .= '(' . $dimension_list . ')';

      $sql .= ';';

      // if index_dimension_scan determined computed columns were needed to add the index
      // insert the sql to create them before the create index sql
      if (strlen($add_column_sql) > 0) {
        $sql = $add_column_sql . $sql;
      }
    }
    catch (Exception $e) {
      if (stripos("nulled column index found", $e->getMessage()) !== FALSE) {
        // mssql indexes are different from postgresql in that they cannot contain null values
        // compenstate by adding a constraint to act like a postgresql UNIQUE INDEX composed of a nullable column
        //@IMPLEMENT: need to figure out a check constraint or trigger for this scenario
        $sql = '';
      }
      else {
        throw $e;
      }
    }

    return $sql;
  }

  public function get_drop_sql($node_schema, $node_table, $node_index) {
    $ddl = "DROP INDEX "
      . mssql10::get_quoted_schema_name($node_schema['name'])
      . "." . mssql10::get_quoted_table_name($node_table['name'])
      . "." . mssql10::get_quoted_object_name($node_index['name'])
      . ";\n";
    //@TODO: drop computed columns after DROP INDEX
    return $ddl;
  }

  /**
   * in MSSQL indexes must contain column references, value expressions are not allowed
   *
   */
  public static function index_dimension_scan($node_schema, $node_table, $node_index, &$add_column_sql) {
    $dimension_list = '';
    $add_column_sql = '';

    // in MSSQL, index dimensions that are not explicit columns must be converted to computed columns to make the index work like it does in postgresql
    $i = 0;
    foreach ($node_index->indexDimension AS $dimension) {
      $i++;
      $dimension_name = (string)$dimension;
      if (mssql10_table::contains_column($node_table, $dimension_name)) {
        // dimension is an explicit column
        // check unique index indexDimensions for nulled columns
        // mssql index constraint engine will not ignore null values for nullable columns
        if (isset($node_index['unique'])
          && strcasecmp($node_index['unique'], 'true') == 0) {
          $node_column = dbx::get_table_column($node_table, $dimension_name);
          if (mssql10_column::null_allowed($node_table, $node_column)) {
            //dbsteward::console_line(7, "dimension_name = " . $dimension_name);
            //var_dump($node_column);
            throw new exception("nulled column index found");
          }
        }
      }
      else {
        // not an explicit column, so create one
        $dimension_name = $node_index['name'] . '_' . $i;
        $add_column_sql .= "ALTER TABLE " . mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_table['name']) . "\n" . "  ADD " . $dimension_name . " AS " . (string)$dimension . ";\n";
      }

      $dimension_list .= $dimension_name . ', ';
    }
    $dimension_list = substr($dimension_list, 0, -2);

    return $dimension_list;
  }
}

?>
