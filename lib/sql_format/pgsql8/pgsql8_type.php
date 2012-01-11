<?php
/**
 * Manipulate postgresql type node
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * $Id: pgsql8_type.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_type {

  /**
   * Creates and returns DDL for creation of the type
   *
   * @return string
   */
  public function get_creation_sql($node_schema, $node_type) {
    if ( strcasecmp($node_type['type'], 'enum') == 0 ) {
      if ( !isset($node_type->enum) ) {
        throw new exception("type of type enum contains no enum children");
      }
      $values = '';
      for($i=0; $i < count($node_type->enum); $i++) {
        $value = $node_type->enum[$i]['name'];
        $values .= "'" . pg_escape_string($value) . "'";
        if ( $i < count($node_type->enum) - 1 ) {
          $values .= ",";
        }
      }
      $type_name = pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($node_type['name'], dbsteward::$quote_object_names);
      $ddl = "CREATE TYPE " . $type_name . " AS ENUM (" . $values . ");";
    }
    else {
      throw new exception("unknown type " . $node_type['name'] . ' type ' . $node_type['type']);
    }
    return $ddl;
  }

  /**
   * Return DDL for dropping the type
   *
   * @return created SQL command
   */
  public static function get_drop_sql($node_schema, $node_type) {
    $type_name = pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($node_type['name'], dbsteward::$quote_object_names);
    $ddl = "DROP TYPE " . $type_name . ";";
    return $ddl;
  }

  public static function equals($schema_a, $type_a, $schema_b, $type_b) {
    $a_create_sql = self::get_creation_sql($schema_a, $type_a, false);
    $b_create_sql = self::get_creation_sql($schema_b, $type_b, false);
    $equals = strcasecmp($a_create_sql, $b_create_sql) == 0;
    return $equals;
  }
  
  /**
   * change all tables columns thare are $node_type to a placeholder type
   *
   * @param $columns      reference columns returned by reference
   * @param $node_schema
   * @param $node_type
   * @return string DDL
   */
  public static function alter_column_type_placeholder(&$columns, $node_schema, $node_type) {
    $ddl = '';
    for($i=0; $i < count(pgsql8_diff::$new_table_dependency); $i++) {
      // find the necessary pointers
      $table_item = pgsql8_diff::$new_table_dependency[$i];
      if ( $table_item['table']['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
        // don't do anything with this table, it is a magic internal DBSteward value
        continue;
      }
      
      $new_schema = $table_item['schema'];
      $new_table = $table_item['table'];
      foreach(dbx::get_table_columns($new_table) AS $new_column) {
        // is the column the passed type?
        $unquoted_type_name = pgsql8_diff::get_quoted_name($node_schema['name'], false) . '.' . pgsql8_diff::get_quoted_name($node_type['name'], false);
        if ( strcasecmp($new_column['type'], $unquoted_type_name) == 0 ) {
          $ddl .= "ALTER TABLE " . pgsql8_diff::get_quoted_name($new_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($new_table['name'], dbsteward::$quote_table_names)
            . " ALTER COLUMN " . pgsql8_diff::get_quoted_name($new_column['name'], dbsteward::$quote_column_names)
            . " TYPE " . pgsql8_type::alter_column_type_placeholder_type($node_type) . ";\n";

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
   * A reference function: determine the placeholder type of $node_type's type type
   *
   * @param $node_type
   * @return string
   */
  protected static function alter_column_type_placeholder_type($node_type) {
    switch(strtolower($node_type['type'])) {
      case 'enum':
        $placeholder_type = 'text';
        break;
      default:
        throw new exception("type of type " . $node_type['type'] . " placeholder definition is not defined");
        break;
    }
    return $placeholder_type;
  }
  
  /**
   * alter_column_type_placeholder's companion - restore $columns list of columns to $node_type type
   *
   * @param $columns      reference columns returned by reference
   * @param $node_schema
   * @param $node_type
   * @return string DDL
   */
  public static function alter_column_type_restore($columns, $node_schema, $node_type) {
    $ddl = '';
    
    foreach($columns AS $column_map) {
      $ddl .= "ALTER TABLE " . pgsql8_diff::get_quoted_name($column_map['alter_column_schema']['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($column_map['alter_column_table']['name'], dbsteward::$quote_table_names)
        . " ALTER COLUMN " . pgsql8_diff::get_quoted_name($column_map['alter_column_column']['name'], dbsteward::$quote_column_names)
        . " TYPE " . pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($node_type['name'], dbsteward::$quote_object_names)
        . " USING " . pgsql8_diff::get_quoted_name($column_map['alter_column_column']['name'], dbsteward::$quote_column_names) . "::" . pgsql8_diff::get_quoted_name($node_schema['name'], dbsteward::$quote_schema_names) . '.' . pgsql8_diff::get_quoted_name($node_type['name'], dbsteward::$quote_object_names) . ";\n";
    }
    
    return $ddl;
  }

}

?>
