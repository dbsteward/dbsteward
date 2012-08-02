<?php
/**
 * Manipulate table and column constraints
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_constraint {

  public static function foreign_key_lookup($db_doc, $node_schema, $node_table, $column, $visited = array()) {
    $foreign = array();
    $foreign['schema'] = dbx::get_schema($db_doc, $column['foreignSchema']);
    if ( ! $foreign['schema'] ) {
      throw new Exception("Failed to find foreign schema '{$column['foreignSchema']}' for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
    }

    $foreign['table'] = dbx::get_table($foreign['schema'], $column['foreignTable']);
    if ( ! $foreign['table'] ) {
      throw new Exception("Failed to find foreign table '{$column['foreignTable']}' for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
    }

    // if foreignColumn is not set
    // the column is assumed to be the same name as the referring column
    if ( ! empty($column['foreignColumn']) ) {
      $foreignColumn = $column['foreignColumn'];
    }
    else {
      $foreignColumn = $column['name'];
    }

    $foreign['column'] = dbx::get_table_column($foreign['table'], $foreignColumn);
    if ( ! $foreign['column'] ) {
      var_dump($foreign['column']);
      throw new Exception("Failed to find foreign column '{$foreignColumn}' for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
    }

    // column type is missing, and resolved foreign is also a foreign key?
    // recurse and find the cascading foreign key
    if ( empty($foreign['column']['type']) && !empty($foreign['column']['foreignColumn']) ) {
      // make sure we don't visit the same column twice
      $foreign_col = format::get_fully_qualified_column_name($foreign['schema']['name'], $foreign['table']['name'], $foreign['column']['name']);
      if ( in_array($foreign_col, $visited) ) {
        $local = format::get_fully_qualified_column_name($node_schema['name'], $node_table['name'], $column['name']);
        throw new Exception("Foreign key cyclic dependency detected! Local column $local pointing to foreign column $foreign_col");
      }
      $visited[] = $foreign_col;

      $nested_fkey = self::foreign_key_lookup($db_doc, $foreign['schema'], $foreign['table'], $foreign['column'], $visited);

      // make a separate clone of the column element because we are specifying the type only for foreign key type referencing
      $foreign['column'] = new SimpleXMLElement($foreign['column']->asXML());
      $foreign['column']['type'] = $nested_fkey['column']['type'];
    }

    $foreign['name'] = format_index::index_name($node_table['name'], $column['name'], 'fkey');
    $foreign['references'] = static::get_foreign_key_reference_sql($foreign);

    return $foreign;
  }

  public static function get_foreign_key_reference_sql($foreign) {
    return format::get_fully_qualified_table_name($foreign['schema']['name'], $foreign['table']['name']) . ' (' . format::get_quoted_column_name($foreign['column']['name']) . ')';
  }
  
  /**
   * @NOTE: because this gets the defintion from the composite list returned by get_table_constraints
   * the constraint is not returned by reference as it is not modifiable like other get functions in this class
   * when saving changes to constraints, need to lookup the child where they would come from explicitly
   */
  public static function get_table_constraint($db_doc, $node_schema, $node_table, $name) {
    $constraints = self::get_table_constraints($db_doc, $node_schema, $node_table, 'all');
    $return_constraint = NULL;
    foreach ($constraints AS $constraint) {
      if (strcasecmp($constraint['name'], $name) == 0) {
        if ($return_constraint == NULL) {
          $return_constraint = $constraint;
        }
        else {
          var_dump($constraints);
          throw new exception("more than one table " . $node_schema['name'] . '.' . $node_table['name'] . " constraint called " . $name . " found");
        }
      }
    }
    return $return_constraint;
  }

  /**
   * return collection of arrays representing all of the constraints on a table
   * this is more than just the <constraint> discret children of a table element
   * this is also primary key, inline column foreign keys, and inline column unique constraints
   * everything comparing the constraints of a table should be calling this
   */
  public static function get_table_constraints($db_doc, $node_schema, $node_table, $type = 'all') {
    if ( !is_object($node_table) ) {
      var_dump($node_table);
      throw new Exception("node_table is not an object, check trace for bad table pointer");
    }
    switch ($type) {
      case 'all':
      case 'primaryKey':
      case 'constraint':
      case 'foreignKey':
      break;
      default:
        throw new Exception("unknown type " . $type . " encountered");
    }
    $constraints = array();

    if ($type == 'all' || $type == 'primaryKey') {
      if (isset($node_table['primaryKey'])) {
        $pk_name = static::get_primary_key_name($node_table);
        $pk_def = static::get_primary_key_definition($node_table);

        $constraints[] = array(
          'name' => $pk_name,
          'schema_name' => (string)$node_schema['name'],
          'table_name' => (string)$node_table['name'],
          'type' => 'PRIMARY KEY',
          'definition' => $pk_def
        );
      }
      else {
        throw new Exception("Every table must have a primaryKey!");
      }
    }

    if ( $type == 'all' || $type == 'constraint' || $type == 'foreignKey' ) {
      // look for constraints in <constraint> elements
      foreach ( $node_table->constraint AS $node_constraint ) {
        // further sanity check node definition constraint types
        switch ( strtoupper((string)$node_constraint['type']) ) {
          case 'PRIMARY KEY':
            throw new Exception("Primary keys are not allowed to be defined in a <constraint>");
            break;

          default:
            throw new Exception('unknown constraint type ' . $node_constraint['type'] . ' encountered');
            break;

          case 'CHECK':
          case 'UNIQUE':
            // if we're ONLY looking for foreign keys, ignore everything else
            if ( $type == 'foreignKey' ) {
              continue;
            }
            // fallthru
          case 'FOREIGN KEY':
            $constraints[] = array(
              'name' => (string)$node_constraint['name'],
              'schema_name' => (string)$node_schema['name'],
              'table_name' => (string)$node_table['name'],
              'type' => strtoupper((string)$node_constraint['type']),
              'definition' => (string)$node_constraint['definition']
            );
            break;
        }
      }

      // look for constraints in columns: foreign key and unique
      foreach ($node_table->column AS $column) {
        if ( isset($column['foreignSchema']) || isset($column['foreignTable']) ) {

          if ( empty($column['foreignSchema']) || empty($column['foreignTable']) ) {
            throw new Exception("Invalid foreignSchema|foreignTable pair for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
          }
          if ( ! empty($column['type']) ) {
            throw new exception("Foreign-Keyed columns should not specify a type for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
          }

          $foreign = static::foreign_key_lookup($db_doc, $node_schema, $node_table, $column);
          if ( ! empty($column['foreignKeyName']) > 0) {
            // explicitly name the foreign key if specified in the node
            $foreign['name'] = (string)$column['foreignKeyName'];
          }

          $column_fkey_constraint = array(
            'name' => (string)$foreign['name'],
            'schema_name' => (string)$node_schema['name'],
            'table_name' => (string)$node_table['name'],
            'type' => 'FOREIGN KEY',
            'definition' => '(' . dbsteward::quote_column_name($column['name']) . ') REFERENCES ' . $foreign['references'],
            'foreign_key_data' => $foreign
          );

          if ( ! empty($column['foreignOnDelete']) ) {
            $column_fkey_constraint['foreignOnDelete'] = strtoupper((string)$column['foreignOnDelete']);
          }
          if ( ! empty($column['foreignOnUpdate']) ) {
            $column_fkey_constraint['foreignOnUpdate'] = strtoupper((string)$column['foreignOnUpdate']);
          }

          $constraints[] = $column_fkey_constraint;
        }
      }
    }
    return $constraints;
  }

  public function equals($constraint_a, $constraint_b) {
    if ( strcasecmp($constraint_a['name'], $constraint_b['name']) != 0 ) {
      return false;
    }
    $a_type = null;
    $b_type = null;
    if ( isset($constraint_a['type']) ) {
      $a_type = (string)$constraint_a['type'];
    }
    if ( isset($constraint_b['type']) ) {
      $b_type = (string)$constraint_b['type'];
    }
    if ( strcasecmp($a_type, $b_type) != 0 ) {
      return false;
    }

    $a_foreign_on_delete = null;
    $b_foreign_on_delete = null;
    if ( isset($constraint_a['foreignOnDelete']) ) {
      $a_foreign_on_delete = $constraint_a['foreignOnDelete'];
    }
    if ( isset($constraint_b['foreignOnDelete']) ) {
      $b_foreign_on_delete = $constraint_b['foreignOnDelete'];
    }
    if ( strcasecmp($a_foreign_on_delete, $b_foreign_on_delete) != 0 ) {
      return false;
    }

    $a_foreign_on_update = null;
    $b_foreign_on_update = null;
    if ( isset($constraint_a['foreignOnUpdate']) ) {
      $a_foreign_on_update = $constraint_a['foreignOnUpdate'];
    }
    if ( isset($constraint_b['foreignOnUpdate']) ) {
      $b_foreign_on_update = $constraint_b['foreignOnUpdate'];
    }
    if ( strcasecmp($a_foreign_on_update, $b_foreign_on_update) != 0 ) {
      return false;
    }

    $equals = strcasecmp($constraint_a['definition'], $constraint_b['definition']) == 0;

    return $equals;
  }

  /**
   * Split the primary key up into an array of columns
   *
   * @param string $primary_key_string The primary key string (e.g. "schema_name, table_name, column_name")
   * @return array The primary key(s) split into an array
   */
  public static function primary_key_split($primary_key_string) {
    return preg_split("/[\,\s]+/", $primary_key_string, -1, PREG_SPLIT_NO_EMPTY);
  }

  public static function get_primary_key_name($node_table) {
    if ( ! empty($node_table['primaryKeyName']) ) {
      return dbsteward::string_cast($node_table['primaryKeyName']);
    }
    else {
      return format_index::index_name($node_table['name'], NULL, 'pkey');
    }
  }

  public static function get_primary_key_definition($node_table) {
    return '(' . implode(', ', array_map('format::get_quoted_column_name', static::primary_key_split($node_table['primaryKey']))) . ')';
  }

  /**
   * Converts referential integrity options (NO_ACTION, RESTRICT, CASCADE, SET_NULL, SET_DEFAULT) to syntax-dependent SQL
   */
  public static function get_reference_option_sql($ref_opt) {
    return strtoupper($ref_opt);
  }
}