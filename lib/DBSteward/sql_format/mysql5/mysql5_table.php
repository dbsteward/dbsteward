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
  public function get_creation_sql($node_schema, $node_table) {
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
    
    if (!empty($node_table->tablePartition)) {
      if (!isset($node_table->tablePartition['type'])) {
        throw new exception('No table partiton type selected for ' . $table_name);
      }
      if ($node_table->tablePartition['type'] != 'MODULO') {
        throw new exception('Invalid partition type: ' . $node_table->tablePartition['type']);
      }
      
      foreach ($node_table->tablePartition->tablePartitionOption AS $opt) {
        if ($opt['name'] == 'number') {
          $part_number = $opt['value'];
        }
        if ($opt['name'] == 'column') {
          $part_col_name = $opt['value'];
        }
      }
      
      if (empty($part_number)) {
        throw new exception('tablePartitionOption "number" must be specified for table ' . $table_name);
      }
      if (empty($part_col_name)) {
        throw new exception('tablePartitionOption "column" must be specified for table ' . $table_name);
      }
      $cols[] = "key($part_col_name)";
      $part_sql = "\nPARTITION BY HASH($part_col_name) PARTITIONS $part_number";
    }

    $sql .= "  " . implode(",\n  ", $cols) . "\n)";
    $opt_sql = mysql5_table::get_table_options_sql($node_schema, $node_table);
    if (!empty($opt_sql)) {
      $sql .= "\n" . $opt_sql;
    }
    
    if ( strlen($node_table['description']) > 0 ) {
      $sql .= "\nCOMMENT " . mysql5::quote_string_value($node_table['description']);
    }
    
    if (!empty($part_sql)) {
      $sql .= $part_sql;
    }
    
    $sql .= ';';

    // @TODO: implement column statistics
    // @TODO: table ownership with $node_table['owner'] ?

    return $sql;
  }

  /**
   * Creates and returns SQL command for dropping the table.
   *
   * @return created SQL command
   */
  public function get_drop_sql($node_schema, $node_table) {
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
