<?php
/**
 * Manipulate table nodes
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mysql5_table extends sql99_table {
  /**
   * Creates and returns SQL for creation of the table.
   *
   * @return created SQL command
   */
  public static function get_creation_sql($node_schema, $node_table) {
    if ( $node_schema->getName() != 'schema' ) {
      throw new exception("node_schema object element name is not schema. check stack for offending caller");
    }

    if ( $node_table->getName() != 'table' ) {
      throw new exception("node_table object element name is not table. check stack for offending caller");
    }

    if ( strlen($node_table['inherits']) > 0 ) {
      //@TODO: implement compatibility with pgsql table inheritance
      dbsteward::console_line(1, "Skipping table '{$node_table['name']}' because MySQL does not support table inheritance");
      return "-- Skipping table '{$node_table['name']}' because MySQL does not support table inheritance";
    }

    $table_name = mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);

    $sql = "CREATE TABLE $table_name (\n";

    $cols = array();
    foreach ( $node_table->column as $column ) {
      $cols[] = mysql5_column::get_full_definition(dbsteward::$new_database, $node_schema, $node_table, $column, false);

    }
    
    $part_sql = static::get_partition_sql($node_schema, $node_table);

    $sql .= "  " . implode(",\n  ", $cols) . "\n)";
    $opt_sql = mysql5_table::get_table_options_sql(mysql5_table::get_table_options($node_schema, $node_table));
    if (!empty($opt_sql)) {
      $sql .= "\n" . $opt_sql;
    }
    
    if ( strlen($node_table['description']) > 0 ) {
      $sql .= "\nCOMMENT " . mysql5::quote_string_value($node_table['description']);
    }
    
    if (!empty($part_sql)) {
      $sql .= "\n" . $part_sql;
    }
    
    $sql .= ';';

    // @TODO: implement column statistics
    // @TODO: table ownership with $node_table['owner'] ?

    return $sql;
  }

  public static function get_partition_sql($node_schema, $node_table) {
    $table_name = mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);

    foreach ($node_table->tablePartition as $tablePartition) {
      if (!isset($tablePartition['sqlFormat']) || strcasecmp($tablePartition['sqlFormat'], 'mysql5') === 0) {
        if (!isset($tablePartition['type'])) {
          throw new exception("No table partiton type selected for $table_name");
        }

        $options = static::get_partition_options($table_name, $tablePartition);

        $type = trim(strtoupper($tablePartition['type']));
        switch ($type) {
          case 'MODULO':
            $type = 'HASH';
          case 'HASH':
          case 'LINEAR HASH':
            $number = static::get_partition_number($table_name, $type, $options);
            $expr = static::get_partition_column_expression($node_table, $table_name, $type, $options);
            return "PARTITION BY $type ($expr) PARTITIONS $number";

          case 'KEY':
          case 'LINEAR KEY':
            $number = static::get_partition_number($table_name, $type, $options);
            $expr = static::get_partition_column_list($node_table, $table_name, $type, $options);            
            return "PARTITION BY $type ($expr) PARTITIONS $number";

          case 'LIST':
          case 'RANGE':
          case 'RANGE COLUMNS':
            if ($type === 'RANGE COLUMNS') {
              $expr = static::get_partition_column_list($node_table, $table_name, $type, $options);
            }
            else {
              $expr = static::get_partition_column_expression($node_table, $table_name, $type, $options);
            }

            $cond = $type == 'LIST' ? 'IN' : 'LESS THAN';

            $segs = static::map_partition_segments($table_name, $tablePartition, function($name, $value) use ($cond) {
              return "PARTITION $name VALUES $cond ($value)";
            });

            return "PARTITION BY $type ($expr) (\n  " . implode(",\n  ", $segs) . "\n)";

          default:
            throw new exception("Unknown tablePartition type '$type'");
        }
      }
    }

    return null;
  }

  protected function get_partition_options($table_name, $tablePartition) {
    $options = array();
    foreach ($tablePartition->tablePartitionOption as $opt) {
      $name = isset($opt['name']) ? trim(strtolower($opt['name'])) : '';
      $value = isset($opt['value']) ? trim($opt['value']) : '';

      if (strlen($name) === 0) {
        throw new exception("No tablePartitionOption name given for table $table_name");
      }
      if (strlen($value) === 0) {
        throw new exception("No tablePartitionOption value given for tablePartitionOption $name on table $table_name");
      }

      $options[$name] = $value;
    }
    return $options;
  }

  protected function get_partition_number($table_name, $type, $options) {
    if (!isset($options['number'])) {
      throw new exception("tablePartitionOption 'number' must be specified for $type partition on table $table_name");
    }

    $number = $options['number'] + 0;
    if (!is_int($number) || $number <= 0) {
      throw new exception("tablePartitionOption 'number' must be an integer greater than 0 for $type partition on $table_name");
    }

    return $number;
  }

  protected function get_partition_column_expression($node_table, $table_name, $type, $options) {
    if (isset($options['column']) && strlen(trim($options['column'])) !== 0) {
      $col = trim($options['column']);
      if (!static::contains_column($node_table, $col)) {
        throw new exception("Invalid column partition option: there is no column named '$col' on table $table_name");
      }
      return mysql5::get_quoted_column_name($col);
    }
    elseif (isset($options['expression']) && strlen(trim($options['expression'])) !== 0) {
      return trim($options['expression']);
    }

    throw new exception("tablePartitionOption 'column' or 'expression' must be specified for $type partition on $table_name");
  }

  protected function get_partition_column_list($node_table, $table_name, $type, $options) {
    if (isset($options['columns']) && strlen(trim($options['columns']))) {
      $cols = preg_split('/\s*,\s*/', trim($options['columns']), -1, PREG_SPLIT_NO_EMPTY);
    }
    elseif (isset($options['column']) && strlen(trim($options['column']))) {
      $cols = array(trim($options['column']));
    }
    else {
      throw new exception("tablePartitionOption 'column' or 'columns' must be specified for $type partition on $table_name");
    }

    if (count($cols) === 0) {
      throw new exception("Invalid column partition option: you must specify at least one column for $type partition on $table_name");
    }

    foreach ($cols as &$col) {
      $col = trim($col);
      if (!static::contains_column($node_table, $col)) {
        throw new exception("Invalid column partition option: there is no column named '$col' on table $table_name");
      }
      $col = mysql5::get_quoted_column_name($col);
    }
    return implode(', ', $cols);
  }

  protected function map_partition_segments($table_name, $tablePartition, $callback) {
    $segs = array();
    foreach ($tablePartition->tablePartitionSegment as $segment) {
      $name = isset($segment['name']) ? trim($segment['name']) : '';
      $value = isset($segment['value']) ? trim($segment['value']) : '';

      if (strlen($name) === 0) {
        throw new exception("No tablePartitionSegment name given for table $table_name");
      }
      if (strlen($value) === 0) {
        throw new exception("No tablePartitionSegment value given for tablePartitionSegment $name on table $table_name");
      }

      $name = mysql5::get_quoted_object_name($name);
      $segs[] = call_user_func($callback, $name, $value);
    }

    if (count($segs) === 0) {
      throw new exception("At least one tablePartitionSegment must be defined for $type partition on table $table_name");
    }

    return $segs;
  }

  /**
   * Creates and returns SQL command for dropping the table.
   *
   * @return created SQL command
   */
  public static function get_drop_sql($node_schema, $node_table) {
    if ( !is_object($node_schema) ) {
      var_dump($node_schema);
      throw new exception("node_schema is not an object");
    }
    if ($node_schema->getName() != 'schema') {
      var_dump($node_schema);
      throw new exception("node_schema element type is not schema. check stack for offending caller");
    }
    if ($node_table->getName() != 'table') {
      var_dump($node_schema);
      var_dump($node_table);
      throw new exception("node_table element type is not table. check stack for offending caller");
    }
    return "DROP TABLE " . mysql5::get_fully_qualified_table_name($node_schema['name'],$node_table['name']) . ";";
  }

  public static function get_sequences_needed($schema, $table) {
    $sequences = array();
    $owner = $table['owner'];

    foreach ( $table->column as $column ) {
      // we need a sequence for each serial column
      if ( mysql5_column::is_serial($column['type']) ) {
        $sequence_name = mysql5_column::get_serial_sequence_name($schema, $table, $column);
        $sequence = new SimpleXMLElement("<sequence name=\"$sequence_name\" owner=\"$owner\"/>");

        if ( !empty($column['oldColumnName']) && !dbsteward::$ignore_oldnames ) {
          $realname = (string)$column['name'];
          $column['name'] = (string)$column['oldColumnName'];
          $sequence['oldSequenceName'] = mysql5_column::get_serial_sequence_name($schema, $table, $column);
          $column['name'] = $realname;
        }

        $sequences[] = $sequence;
      }
    }

    return $sequences;
  }

  public static function contains_table_option($node_table, $name) {
    if (!mysql5::$use_auto_increment_table_options && strcasecmp($name, 'auto_increment') === 0) {
      // these are not the droids you're looking for....
      return false;
    }

    return parent::contains_table_option($node_table, $name);
  }

  /**
   * Retrieves an associative array of table options defined for a table
   * @param  SimpleXMLElement $node_schema The schema
   * @param  SimpleXMLElement $node_table  The table
   * @return array            Option name => option value
   */
  public static function get_table_options($node_schema, $node_table) {
    $opts = parent::get_table_options($node_schema, $node_table);

    if (!mysql5::$use_auto_increment_table_options && array_key_exists('auto_increment', $opts)) {
      dbsteward::console_line(1, 'WARNING: Ignoring auto_increment tableOption on table ' . mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']));
      dbsteward::console_line(1, '         Setting the auto_increment value is unreliable. If you want to use it, pass the --useautoincrementoptions commandline flag');
      unset($opts['auto_increment']);
    }

    return $opts;
  }

  public static function get_triggers_needed($schema, $table) {
    $triggers = array();

    foreach ( $table->column as $column ) {
      // we need a trigger for each serial column
      if ( mysql5_column::is_serial($column['type']) ) {
        $trigger_name = mysql5_column::get_serial_trigger_name($schema, $table, $column);
        $sequence_name = mysql5_column::get_serial_sequence_name($schema, $table, $column);
        $table_name = $table['name'];
        $column_name = mysql5::get_quoted_column_name($column['name']);
        $xml = <<<XML
<trigger name="$trigger_name"
         sqlFormat="mysql5"
         when="BEFORE"
         event="INSERT"
         table="$table_name"
         forEach="ROW"
         function="SET NEW.$column_name = COALESCE(NEW.$column_name, nextval('$sequence_name'));"/>
XML;
        $triggers[] = new SimpleXMLElement($xml);
      }

      // @TODO: convert DEFAULT expressions (not constants) to triggers for pgsql compatibility
    }

    return $triggers;
  }

  public static function format_table_option($name, $value) {
    return strtoupper($name) . '=' . $value;
  }
}
?>
