<?php
/**
 * Manipulate type node
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_type {

  public static function get_add_check_sql($node_schema, $node_table, $column, $node_type) {
    if (!is_object($node_type)) {
      var_dump($node_type);
      throw new exception("node_type is not an object");
    }
    if (!isset($node_type->enum)) {
      throw new exception("no enums defined for type " . $node_type['name']);
    }

    $enum_values = mssql10_type::get_enum_values($node_type);

    $table_name = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_table['name']);
    $column_name = mssql10::get_quoted_column_name($column['name']);
    $constraint_name = pgsql8_index::index_name($node_table['name'], $column['name'], '_check_enum');
    $enum_list = "'" . implode("','", $enum_values) . "'";

    $ddl = "ALTER TABLE " . $table_name . "\n";
    $ddl .= "\tADD CONSTRAINT " . $constraint_name . " CHECK ( " . $column_name . " IN (" . $enum_list . ") );\n";

    return $ddl;
  }

  public static function get_drop_check_sql($node_schema, $node_table, $column, $node_type) {
    if (!is_object($node_type)) {
      var_dump($node_type);
      throw new exception("node_type is not an object");
    }
    if (!isset($node_type->enum)) {
      throw new exception("no enums defined for type " . $node_type['name']);
    }

    $enum_values = mssql10_type::get_enum_values($node_type);

    $table_name = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_table['name']);
    $column_name = mssql10::get_quoted_column_name($column['name']);
    $constraint_name = pgsql8_index::index_name($node_table['name'], $column['name'], '_check_enum');
    $enum_list = "'" . implode("','", $enum_values) . "'";

    $ddl = "ALTER TABLE " . $table_name . "\n";
    $ddl .= "\tDROP CONSTRAINT " . $constraint_name . ";\n";

    return $ddl;
  }

  public static function equals($schema_a, $type_a, $schema_b, $type_b) {
    $a_create_sql = self::get_creation_sql($schema_a, $type_a, false);
    $b_create_sql = self::get_creation_sql($schema_b, $type_b, false);
    $equals = strcasecmp($a_create_sql, $b_create_sql) == 0;
    return $equals;
  }

  protected static function get_enum_values($node_type) {
    $enum_values = array();
    foreach ($node_type->enum AS $enum) {
      $enum_values[] = $enum['name'];
    }
    return $enum_values;
  }

  public static function get_creation_sql($node_schema, $node_type) {
    // notice in mssql10_column::column_type() enumerated types are defined as varchar(255) with a CHECK CONSTRAINT added
    // this is due to the fact there are no enumerated types in MSSQL
    // so, for application reference without VIEW DEFINITION permissions given to the application role
    // create a value reference table for the enumeration's possible values, for the application to refer to
    $reference_table_name = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_type['name'] . '_enum_values');
    // enum types rewritten as varchar(255) -- see mssql10_column::column_type()
    $ddl = "CREATE TABLE " . $reference_table_name . " (
      enum_value varchar(255)
    );\n";
    
    $ddl .= mssql10_type::get_enum_value_insert($node_schema, $node_type);

    $reference_table_node = new SimpleXMLElement('<table name="' . $node_type['name'] . '_enum_values' . '">
    <grant operation="SELECT" role="ROLE_APPLICATION"/>
    </table>');
    $ddl .= mssql10_permission::get_sql(dbsteward::$new_database, $node_schema, $reference_table_node, $reference_table_node->grant);

    return $ddl;
  }
  
  public static function get_enum_value_insert($node_schema, $node_type) {
    $ddl = '';
    $reference_table_name = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_type['name'] . '_enum_values');
    foreach (mssql10_type::get_enum_values($node_type) AS $enum_value) {
      $ddl .= "INSERT INTO " . $reference_table_name . " VALUES ( '" . $enum_value . "' );\n";
    }
    return $ddl;
  }
  
  public static function get_enum_value_delete($node_schema, $node_type) {
    $ddl = '';
    $reference_table_name = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_type['name'] . '_enum_values');
    $ddl = "DELETE FROM " . $reference_table_name . ";\n";
    return $ddl;
  }
  
  /**
   * for type defintion changes, temporarily change all tables columns that reference the type
   *
   * @param $columns      reference columns returned by reference
   * @param $node_schema
   * @param $node_type
   * @return string DDL
   */
  public static function column_constraint_temporary_drop(&$columns, $node_schema, $node_type) {
    $ddl = '';
    for($i=0; $i < count(mssql10_diff::$new_table_dependency); $i++) {
      // find the necessary pointers
      $table_item = mssql10_diff::$new_table_dependency[$i];
      if ( $table_item['table']['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
        // don't do anything with this table, it is a magic internal DBSteward value
        continue;
      }

      $new_schema = $table_item['schema'];
      $new_table = $table_item['table'];
      foreach(dbx::get_table_columns($new_table) AS $new_column) {
        // is the column the passed type?
        $unquoted_type_name = $node_schema['name'] . '.' . $node_type['name'];
        if ( strcasecmp($new_column['type'], $unquoted_type_name) == 0 ) {
          $ddl .= mssql10_type::get_drop_check_sql($new_schema, $new_table, $new_column, $node_type);

          // add column to the beginning of the list so it will be done before earlier changes (foreign key ordering)
          array_unshift(
            $columns,
            array(
              'alter_column_schema' => $new_schema,
              'alter_column_table' => $new_table,
              'alter_column_column' => $new_column
            )
          );
        }
      }
    }
    return $ddl;
  }
  
  /**
   * column_constraint_temporary_drop's companion - restore $columns list of columns to $node_type type
   *
   * @param $columns      reference columns returned by reference
   * @param $node_schema
   * @param $node_type
   * @return string DDL
   */
  public static function column_constraint_restore($columns, $node_schema, $node_type) {
    $ddl = '';
    
    foreach($columns AS $column_map) {
      $ddl .= mssql10_type::get_add_check_sql($column_map['alter_column_schema'], $column_map['alter_column_table'], $column_map['alter_column_column'], $node_type);
    }
    
    return $ddl;
  }

}

?>
