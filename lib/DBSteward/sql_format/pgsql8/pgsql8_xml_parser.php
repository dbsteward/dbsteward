<?php
/**
 * Preprocess XML to expand nodes where appropriate.
 * 
 * Currently only does partitioned tables.
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Bill Moran <william.moran@intermedix.com>
 */

class pgsql8_xml_parser extends sql99_xml_parser {
  
  private static $part_number;
  private static $part_column;
  private static $first_slony_id;
  private static $last_slony_id;
  
  public static function process(&$doc) {
    $schemas = $doc->xpath('schema');
    foreach ($schemas AS $schema) {
      $tables = $schema->xpath('table');
      foreach ($tables AS $table) {
        if (!empty($table->tablePartition)) {
          self::expand_partitioned_table($doc, $schema, $table);
        }
      }
    }
  }
  
  private static function expand_partitioned_table(&$doc, $schema, $table) {
    // Validate
    if (!isset($table->tablePartition['type'])) {
      throw new exception('No table partiton type selected for ' . $table['name']);
    }
    if ($table->tablePartition['type'] != 'MODULO') {
      throw new exception('Invalid partition type: ' . $table->tablePartition['type']);
    }
    // Establish the partition column and number of partitions
    self::$part_number = NULL;
    self::$part_column = NULL;
    self::$first_slony_id = NULL;
    self::$last_slony_id = NULL;
    foreach ($table->tablePartition->tablePartitionOption AS $opt) {
      if ($opt['name'] == 'number') {
        self::$part_number = $opt['value'];
      }
      if ($opt['name'] == 'column') {
        self::$part_column = pgsql8::get_quoted_column_name($opt['value']);
      }
      if ($opt['name'] == 'firstSlonyId') {
        self::$first_slony_id = (int)$opt['value'];
      }
      if ($opt['name'] == 'lastSlonyId') {
        self::$last_slony_id = (int)$opt['value'];
      }
    }
    if (empty(self::$part_number)) {
      throw new exception('tablePartitionOption "number" must be specified for table ' . $table['name']);
    }
    if (empty(self::$part_column)) {
      throw new exception('tablePartitionOption "column" must be specified for table ' . $table['name']);
    }
    if (!is_null(self::$first_slony_id) && !is_null(self::$last_slony_id)) {
      $slony_ids_allocated = self::$last_slony_id - self::$first_slony_id + 1;
      if ($slony_ids_allocated != self::$part_number) {
        throw new exception('Requested ' . self::$part_number . " partitions but provided $slony_ids_allocated slony IDs");
      }
    }
    // Create the schema node for the partitions
    $new_schema = $doc->addChild('schema');
    self::create_partition_schema($schema, $table, $new_schema);
    // Clone the node as many times as needed to create the partition tables
    self::create_partition_tables($schema, $new_schema, $table);
    // Remove attributes from the main table that move to the partitions
    unset($table->index);
    // Add the trigger to the main table
    $trigger = $schema->addChild('trigger');
    $trigger->addAttribute('name', $table['name'] . '_part_trg');
    $trigger->addAttribute('sqlFormat', 'pgsql8');
    $trigger->addAttribute('event', 'INSERT');
    $trigger->addAttribute('when', 'BEFORE');
    $trigger->addAttribute('table', $table['name']);
    $trigger->addAttribute('forEach', 'ROW');
    $trigger->addAttribute('function', $new_schema['name'] . '.insert_trigger()');
    // Create the stored prodecure
    self::create_procedure($new_schema, $table);
  }
  
  private static function create_partition_schema($schema, $table, &$new_schema) {
    $new_schema['name'] = self::get_schema_name($schema, $table);
    // make sure parition schema has the same rights as parent table schema
    static::copy_object_grants($schema, $new_schema);
  }
  
  private static function create_procedure(&$schema, $table) {
    $code = "DECLARE\n\tmod_result INT;\nBEGIN\n" .
           "\tmod_result := NEW." . self::$part_column . ' % ' .
           self::$part_number . ";\n";
    $part_append = strlen(self::$part_number);
    for ($i = 0; $i < self::$part_number; $i++) {
      $code .= "\t";
      if ($i != 0) {
        $code .= 'ELSE';
      }
      $tname = pgsql8::get_quoted_schema_name($schema['name']) . '.' . pgsql8::get_quoted_table_name('partition_' . sprintf("%0{$part_append}u", $i));
      $code .= "IF (mod_result = $i) THEN\n" .
              "\t\tINSERT INTO $tname VALUES (NEW.*);\n";
    }
    $code .= "\tEND IF;\n\tRETURN NULL;\nEND;";
    $function = $schema->addChild('function');
    $def = $function->addChild('functionDefinition', $code);
    $def->addAttribute('language', 'PLPGSQL');
    $def->addAttribute('sqlFormat', 'pgsql8');
    $function->addAttribute('name', 'insert_trigger');
    $function->addAttribute('returns', 'TRIGGER');
    $function->addAttribute('owner', $table['owner']);
    $function->addAttribute('description', 'DBSteward auto-generated for table partition');
    $grant1 = $function->addChild('grant');
    $grant1->addAttribute('operation', 'EXECUTE');
    $grant1->addAttribute('role', 'ROLE_APPLICATION');
  }
  
  private static function create_partition_tables($orig_schema, &$schema, $orig_table) {
    $part_append = strlen(self::$part_number);
    for ($i = 0; $i < self::$part_number; $i++) {
      $table = $schema->addChild('table');
      $table->addAttribute('name', 'partition_' . sprintf("%0{$part_append}u", $i));
      $table->addAttribute('owner', $orig_table['owner']);
      $table->addAttribute('primaryKey', $orig_table['primaryKey']);
      $table->addAttribute('inheritsTable', $orig_table['name']);
      $table->addAttribute('inheritsSchema', $orig_schema['name']);
      // Add slony IDs to the table
      if (!is_null(self::$first_slony_id)) {
        $table->addAttribute('slonyId', self::$first_slony_id + $i);
      }
      // Add the check constraint
      $check = $table->addChild('constraint');
      $check->addAttribute('type', 'CHECK');
      $check->addAttribute('name', $orig_table['name'] . '_p_' . sprintf("%0{$part_append}u", $i) . '_chk');
      $check->addAttribute('definition', '((' . self::$part_column . ' % ' . self::$part_number . ") = $i)");
      // Copy any indexes here
      foreach ($orig_table->index AS $orig_index) {
        $index = $table->addChild('index');
        $attributes = $orig_index->attributes();
        foreach ($attributes AS $att_name => $att_value) {
          if ($att_name == 'name') {
            $index->addAttribute('name', self::get_child_index_name($att_value, $i));
          }
          else {
            $index->addAttribute($att_name, $att_value);
          }
        }
        $odi = 1;
        foreach ($orig_index->indexDimension AS $orig_dimension) {
          $index->addChild('indexDimension', (string)$orig_dimension)
            ->addAttribute('name', ((string)$orig_dimension) . '_' . $odi++);
        }
      }
      // Copy unique constraints
      // Other types of constraints are inherited from the parent table,
      // and PRIMARY KEY are copied with the table definition
      foreach ($orig_table->constraint as $orig_constraint) {
        if (strtoupper($orig_constraint['type']) == 'UNIQUE') {
          $constraint = $table->addChild('constraint');
          $attributes = $orig_constraint->attributes();
          foreach ($attributes AS $att_name => $att_value) {
            if ($att_name == 'name') {
              $constraint->addAttribute('name', self::get_child_unique_name($i, $att_value));
            }
            else {
              $constraint->addAttribute($att_name, $att_value);
            }
          }
        }
      }
      // Copy FOREIGN KEY constraints: this is a little weird because these
      // normally aren't defined as "constraint"s ... but DBSteward is
      // flexible enough that it works just fine.
      $num = 0;
      foreach ($orig_table->column AS $orig_column) {
        if (isset($orig_column['foreignSchema'])) {
          $constraint = $table->addChild('constraint');
          $constraint->addAttribute('name', self::get_child_fk_name($i, $orig_column['name']));
          $constraint->addAttribute('type', 'FOREIGN KEY');
          $constraint->addAttribute(
            'definition',
            '(' . $orig_column['name'] . ') REFERENCES ' .
            $orig_column['foreignSchema'] . '.' . $orig_column['foreignTable'] .
            '(' . $orig_column['foreignColumn'] . ')'
            );
        }
        $num++;
      }
      // Copy table grants
      static::copy_object_grants($orig_table, $table);
    }
  }
  
  private static function copy_object_grants($from_object, &$to_object) {
    foreach($from_object->grant AS $from_grant) {
      $to_grant = $to_object->addChild('grant');
      $to_grant['operation'] = $from_grant['operation'];
      $to_grant['role'] = $from_grant['role'];
      if ( isset($from_grant['with']) ) {
        $from_grant['with'] = $from_grant['with'];
      }
    }
  }
  
  private static function get_child_index_name($old_name, $partition) {
    $remaining = 63 - strlen($partition) - 2;
    return substr($old_name, 0, $remaining) . "_p$partition";
  }
  
  private static function get_child_unique_name($partition, $old_name) {
    $name = "p{$partition}_";
    $remaining = 63 - strlen($name);
    $old_name = substr($old_name, 0, $remaining);
    return $name . $old_name;
  }
  
  /**
   * Return the name of a foreign key constraint, trimming down the column
   * name as needed to fit in the 63 character limit
   * 
   * @param type $partition
   * @param type $column
   * @return type
   */
  private static function get_child_fk_name($partition, $column) {
    $name = "p{$partition}_";
    $remaining = 63 - strlen($name) - 3;
    $column = substr($column, 0, $remaining);
    return $name . $column . '_fk';
  }
  
  private static function get_schema_name($node_schema, $node_table) {
    return substr('_p_' . $node_schema['name'] . '_' . $node_table['name'], 0, 63);
  }
}

?>
