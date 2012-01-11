<?php
/**
 * Manipulate table nodes
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: oracle10g_table.php 2267 2012-01-09 19:50:46Z nkiraly $
 */

class oracle10g_table extends sql99_table {
  
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

    $table_name = oracle10g_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . oracle10g_diff::get_quoted_name($node_table['name'], dbsteward::$quote_table_names);

    $sql = "CREATE TABLE " . $table_name . " (\n";

    foreach (dbx::get_table_columns($node_table) as $column) {
      $sql .= "\t" . oracle10g_column::get_full_definition(dbsteward::$new_database, $node_schema, $node_table, $column, FALSE) . ",\n";
    }

    $sql = substr($sql, 0, strlen($sql) - 2);
    $sql .= "\n)";
    if (isset($node_table['inherits']) && strlen($node_table['inherits']) > 0) {
      //@TODO: this does not look like it is supported in oracle10g
    }
    $sql .= ";";

    // @IMPLEMENT: $table['description'] specifier ?
    foreach (dbx::get_table_columns($node_table) as $column) {
      if (isset($column['statistics'])) {
        $sql .= "\nALTER TABLE ONLY " . $table_name . " ALTER COLUMN " . oracle10g_diff::get_quoted_name($column['name'], dbsteward::$quote_column_names) . " SET STATISTICS " . $column['statistics'] . ";\n";
      }

      // @IMPLEMENT: $column['description'] specifier ?
      
      // @TODO: should an equivalent to mssql10_column::enum_type_check() be implemented here?
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
    return "DROP TABLE " . oracle10g_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . oracle10g_diff::get_quoted_name($node_table['name'], dbsteward::$quote_table_names) . ";";
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
    $sql = "ALTER TABLE " . oracle10g_diff::get_quoted_name($constraint['schema_name'], dbsteward::$quote_schema_names) . '.' . oracle10g_diff::get_quoted_name($constraint['table_name'], dbsteward::$quote_table_names) . "\n" . "\tADD CONSTRAINT " . oracle10g_diff::get_quoted_name($constraint['name'], dbsteward::$quote_object_names) . ' ' . $constraint['type'] . ' ' . $constraint['definition'];

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
    $sql = "ALTER TABLE " . oracle10g_diff::get_quoted_name($constraint['schema_name'], dbsteward::$quote_schema_names) . '.' . oracle10g_diff::get_quoted_name($constraint['table_name'], dbsteward::$quote_table_names) . "\n\tDROP CONSTRAINT " . oracle10g_diff::get_quoted_name($constraint['name'], dbsteward::$quote_object_names) . ';';
    return $sql;
  }

}

?>
