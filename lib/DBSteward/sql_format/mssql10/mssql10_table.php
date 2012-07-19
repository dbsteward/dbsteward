<?php
/**
 * Manipulate table nodes
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_table extends sql99_table {

  /**
   * Creates and returns SQL for creation of the table.
   *
   * @return created SQL command
   */
  public function get_creation_sql($node_schema, $node_table) {
    if ($node_schema->getName() != 'schema') {
      throw new exception("node_schema object element name is not schema. check stack for offending caller");
    }

    if ($node_table->getName() != 'table') {
      throw new exception("node_table object element name is not table. check stack for offending caller");
    }

    $table_name = mssql10::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . mssql10::get_quoted_name($node_table['name'], dbsteward::$quote_table_names);

    $sql = "CREATE TABLE " . $table_name . " (\n";

    foreach (dbx::get_table_columns($node_table) as $column) {
      $sql .= "\t" . mssql10_column::get_full_definition(dbsteward::$new_database, $node_schema, $node_table, $column, FALSE) . ",\n";
    }

    $sql = substr($sql, 0, strlen($sql) - 2);
    $sql .= "\n)";
    if (isset($node_table['inherits']) && strlen($node_table['inherits']) > 0) {
      //@TODO: this does not look like it is supported
    }
    $sql .= ";";

    // @IMPLEMENT: $table['description'] specifier ?
    foreach (dbx::get_table_columns($node_table) as $column) {
      if (isset($column['statistics'])) {
        $sql .= "\nALTER TABLE ONLY " . $table_name . " ALTER COLUMN " . mssql10::get_quoted_name($column['name'], dbsteward::$quote_column_names) . " SET STATISTICS " . $column['statistics'] . ";\n";
      }

      // @IMPLEMENT: $column['description'] specifier ?
      // if the column type is a defined enum, add a check constraint to enforce the pseudo-enum
      if (mssql10_column::enum_type_check(dbsteward::$new_database, $node_schema, $node_table, $column, $drop_sql, $add_sql)) {
        $sql .= $add_sql;
      }
    }

    // @IMPLMENT table ownership with $node_table['owner'] ?
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
    return "DROP TABLE " . mssql10::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . mssql10::get_quoted_name($node_table['name'], dbsteward::$quote_table_names) . ";";
  }

  /**
   * create SQL To create the constraint passed in the $constraint array
   *
   * @return string
   */
  public function get_constraint_sql($constraint) {
    if (!is_array($constraint)) {
      throw new exception("constraint is not an array?");
    }
    if (strlen($constraint['table_name']) == 0) {
      var_dump(array_keys($constraint));
      throw new exception("table_name is blank");
    }
    $sql = "ALTER TABLE " . mssql10::get_quoted_name($constraint['schema_name'], dbsteward::$quote_schema_names) . '.' . mssql10::get_quoted_name($constraint['table_name'], dbsteward::$quote_table_names) . "\n" . "\tADD CONSTRAINT " . mssql10::get_quoted_name($constraint['name'], dbsteward::$quote_object_names) . ' ' . $constraint['type'] . ' ' . $constraint['definition'];

    // FOREIGN KEY ON DELETE / ON UPDATE handling
    if (isset($constraint['foreignOnDelete']) && strlen($constraint['foreignOnDelete'])) {
      $sql .= " ON DELETE " . $constraint['foreignOnDelete'];
    }
    if (isset($constraint['foreignOnUpdate']) && strlen($constraint['foreignOnUpdate'])) {
      $sql .= " ON UPDATE " . $constraint['foreignOnUpdate'];
    }

    $sql .= ';';
    return $sql;
  }

  public function get_constraint_drop_sql($constraint) {
    if (!is_array($constraint)) {
      throw new exception("constraint is not an array?");
    }
    if (strlen($constraint['table_name']) == 0) {
      var_dump(array_keys($constraint));
      throw new exception("table_name is blank");
    }
    $sql = "ALTER TABLE " . mssql10::get_quoted_name($constraint['schema_name'], dbsteward::$quote_schema_names) . '.' . mssql10::get_quoted_name($constraint['table_name'], dbsteward::$quote_table_names) . "\n\tDROP CONSTRAINT " . mssql10::get_quoted_name($constraint['name'], dbsteward::$quote_object_names) . ';';
    return $sql;
  }

  public static function has_identity($node_table) {
    foreach (dbx::get_table_columns($node_table) as $column) {
      // only check non-fkeyed columns for the identity types
      if (isset($column['type']) && stripos($column['type'], 'IDENTITY') !== FALSE) {
        return TRUE;
      }
    }
    return FALSE;
  }
}

?>
