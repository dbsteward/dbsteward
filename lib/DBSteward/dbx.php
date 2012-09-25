<?php
/**
 * DBSteward database XML definition seek, list, and manipulation functions
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class dbx {

  private static $default_schema = NULL;

  public function &set_default_schema(&$node_db, $name) {
    dbx::$default_schema = dbx::get_schema($node_db, $name, TRUE);
    return dbx::$default_schema;
  }

  public function &get_default_schema() {
    return dbx::$default_schema;
  }

  public static $enum_type_regex_expression = NULL;
  /**
   * return regex fragment that can be used to match databases type to enum types
   */
  public static function enum_regex($db_doc) {
    // enum_type_regex_expression is cached, this function is called a whole lot
    if ($db_doc !== NULL && self::$enum_type_regex_expression === NULL) {
      self::$enum_type_regex_expression = '';

      $enum_type_names = array();
      $schemas = & dbx::get_schemas($db_doc);
      foreach ($schemas AS $schema) {
        $types = & dbx::get_types($schema);
        foreach ($types AS $type) {
          if (strcasecmp($type['type'], 'enum') == 0) {
            $enum_type_names[] = $schema['name'] . '\.' . $type['name'];
          }
        }
      }

      self::$enum_type_regex_expression = implode('|', $enum_type_names);
      if (strlen(self::$enum_type_regex_expression) == 0) {
        self::$enum_type_regex_expression = 'nevermatchyyyyyyzz';
      }
    }
    return self::$enum_type_regex_expression;
  }

  public static function &add_sql(&$node_db, $sql) {
    $node_sql = $node_db->addChild('sql', $sql);
    return $node_sql;
  }

  public static function &get_sql(&$node_db) {
    $nodes = $node_db->xpath("sql");
    return $nodes;
  }

  public static function &get_configuration_parameter(&$node_database, $name, $create_if_not_exist = FALSE) {
    $nodes = $node_database->xpath("configurationParameter[@name='" . $name . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // schema not found, caller wants the schema created in the db
        $node_param = $node_database->addChild('configurationParameter');
        $node_param->addAttribute('name', $name);
      }
      else {
        $node_param = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one configurationParameter named " . $name . " found. panic!");
    }
    else {
      $node_param = $nodes[0];
    }
    return $node_param;
  }

  public static function &get_configuration_parameters(&$node_database) {
    $nodes = $node_database->xpath("configurationParameter");
    return $nodes;
  }

  public static function &get_schema(&$node_db, $name, $create_if_not_exist = FALSE) {
    if (!is_object($node_db)) {
      throw new exception("node_db is not an object!");
    }
    $nodes = $node_db->xpath("schema[@name='" . $name . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // schema not found, caller wants the schema created in the db
        $node_schema = $node_db->addChild('schema');
        $node_schema->addAttribute('name', $name);
      }
      else {
        $node_schema = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one schema named " . $name . " found. panic!");
    }
    else {
      $node_schema = $nodes[0];
    }
    return $node_schema;
  }

  public static function &get_schemas(&$node_db) {
    if ( !is_object($node_db) ) {
      throw new exception("node_db is not an object, check caller context");
    }
    $nodes = $node_db->xpath("schema");
    return $nodes;
  }

  public static function &get_language(&$node_db, $name, $create_if_not_exist = FALSE) {
    $nodes = $node_db->xpath("language[@name='" . $name . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // schema not found, caller wants the language created in the db
        $node_language = $node_db->addChild('language');
        $node_language->addAttribute('name', $name);
      }
      else {
        $node_language = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one node_schema named " . $name . " found. panic!");
    }
    else {
      $node_language = $nodes[0];
    }
    return $node_language;
  }

  public static function &get_languages(&$node_db) {
    $nodes = $node_db->xpath("language");
    return $nodes;
  }

  public static function &get_table(&$node_schema, $name, $create_if_not_exist = FALSE) {
    $nodes = $node_schema->xpath("table[@name='" . $name . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // table not found, caller wants the table created in the db
        $node_table = $node_schema->addChild('table');
        $node_table->addAttribute('name', $name);
      }
      else {
        $node_table = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one table named " . $name . " found. panic!");
    }
    else {
      $node_table = $nodes[0];
    }
    return $node_table;
  }

  public static function &get_tables(&$node_schema) {
    if ( !is_object($node_schema) ) {
      var_dump($node_schema);
      throw new exception("node_schema is not an object");
    }
    $nodes = $node_schema->xpath("table");
    return $nodes;
  }

  public static function &get_table_rows(&$node_table, $create_if_not_exist = FALSE, $columns = NULL) {
    if (!is_object($node_table)) {
      var_dump($node_table);
      throw new exception("node_table is not an object");
    }
    // find or create a matching primary keyed data row
    $nodes = $node_table->xpath("rows");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // table not found, caller wants the rows element created for the table
        $node_rows = $node_table->addChild('rows');
        $node_rows->addAttribute('columns', $columns);
      }
      else {
        $node_rows = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one rows element found. panic!");
    }
    else {
      $node_rows = $nodes[0];
    }
    return $node_rows;
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

  public function &create_table_constraint(&$node_table, $name) {
    $node_constraint = $node_table->addChild('constraint');
    $node_constraint->addAttribute('name', $name);
    return $node_constraint;
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
      throw new exception("node_table is not an object, check trace for bad table pointer");
    }
    switch ($type) {
      case 'all':
      case 'primaryKey':
      case 'constraint':
      case 'foreignKey':
      break;
      default:
        throw new exception("unknown type " . $type . " encountered");
    }
    $constraints = array();
    if ($type == 'all' || $type == 'primaryKey') {
      if (isset($node_table['primaryKey'])) {
        if (isset($node_table['primaryKeyName'])
          && strlen($node_table['primaryKeyName']) > 0) {
          $primary_key_name = dbsteward::string_cast($node_table['primaryKeyName']);
        }
        else {
          $primary_key_name = pgsql8::index_name($node_table['name'], NULL, 'pkey');
        }

        // quoted column name processing for primary key definitions
        $primary_key_columns = preg_split("/[\,\s]+/", $node_table['primaryKey'], -1, PREG_SPLIT_NO_EMPTY);
        $primary_key_list = '';
        foreach ($primary_key_columns AS $primary_key_column) {
          $primary_key_list .= pgsql8::get_quoted_column_name($primary_key_column) . ', ';
        }
        $primary_key_list = substr($primary_key_list, 0, -2);

        $constraints[] = array(
          'name' => $primary_key_name,
          'schema_name' => (string)$node_schema['name'],
          'table_name' => (string)$node_table['name'],
          'type' => 'PRIMARY KEY',
          'definition' => '(' . $primary_key_list . ')'
        );
      }
    }
    if ($type == 'all'
      || $type == 'constraint'
      || $type == 'foreignKey' ) {
      $nodes = $node_table->xpath("constraint");
      foreach ($nodes AS $node_constraint) {
        // further sanity check node definition constraint types
        switch ((string)$node_constraint['type']) {
          case 'CHECK':
          case 'FOREIGN KEY':
          case 'PRIMARY KEY':
          case 'UNIQUE':
            break;
          default:
            throw new exception('unknown constraint type ' . $node_constraint['type'] . ' encountered');
            break;
        }
        
        if ( $type == 'foreignKey' && strcasecmp($node_constraint['type'], 'FOREIGN KEY') != 0 ) {
          // requested type is foreignKey yet node type is not FOREIGN KEY, continue on
          continue;
        }

        $constraints[] = array(
          'name' => (string)$node_constraint['name'],
          'schema_name' => (string)$node_schema['name'],
          'table_name' => (string)$node_table['name'],
          'type' => (string)$node_constraint['type'],
          'definition' => (string)$node_constraint['definition']
        );
      }
    }

    if ($type == 'all'
      || $type == 'constraint'
      || $type == 'foreignKey' ) {
      foreach ($node_table->column AS $column) {
        if (isset($column['foreignSchema']) || isset($column['foreignTable'])) {
          if (strlen($column['foreignSchema']) == 0
            || strlen($column['foreignTable']) == 0) {
            throw new exception("Invalid foreignSchema|foreignTable pair for " . dbsteward::string_cast($node_schema['name']) . "." . dbsteward::string_cast($node_table['name']) . "." . dbsteward::string_cast($column['name']));
          }
          if (isset($column['type'])
            || strlen($column['type']) > 0) {
            throw new exception("Foreign-Keyed columns should not specify a type for " . dbsteward::string_cast($node_schema['name']) . "." . dbsteward::string_cast($node_table['name']) . "." . dbsteward::string_cast($column['name']));
          }

          $foreign = array();
          dbx::foreign_key($db_doc, $node_schema, $node_table, $column, $foreign);
          if (isset($column['foreignKeyName'])
            && strlen($column['foreignKeyName']) > 0) {
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

          if (isset($column['foreignOnDelete']) && strlen($column['foreignOnDelete'])) {
            $column_fkey_constraint['foreignOnDelete'] = (string)$column['foreignOnDelete'];
          }
          if (isset($column['foreignOnUpdate']) && strlen($column['foreignOnUpdate'])) {
            $column_fkey_constraint['foreignOnUpdate'] = (string)$column['foreignOnUpdate'];
          }

          $constraints[] = $column_fkey_constraint;
        }
      }
    }
    return $constraints;
  }
  
  /**
   * return the constraints of other tables that refer to the table specified
   *
   * @param object  $db_doc       database XML doc
   * @param object  $node_schema  schema containing table to find references to
   * @param object  $node_table   table to find references to
   * @return array
   */
  public static function get_tables_foreign_keying_to_table($db_doc, $table_dependency, $node_schema, $node_table) {
    $constraints = array();
    for($i=0; $i<count($table_dependency); $i++) {
      // find the necessary pointers
      $table_dependency_item = $table_dependency[$i];
      
      if ( $table_dependency_item['table']['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
        // don't do anything with this table, it is a magic internal DBSteward value
        continue;
      }

      $db_schema = $table_dependency_item['schema'];
      $db_table = $table_dependency_item['table'];

      $table_constraints = static::get_table_constraints($db_doc, $db_schema, $db_table, 'foreignKey');
//dbsteward::console_line(7, $node_table['name'] . " vs " . $db_table['name'] . " constraints: " . count($table_constraints));
      foreach($table_constraints AS $table_constraint) {
        // get_table_constraints() will set foreign_key_data for dbsteward-defined foreign keys
        // these are the only ones that self define well enough to be compared here
        if ( isset($table_constraint['foreign_key_data'])
          && strcasecmp($table_constraint['foreign_key_data']['schema']['name'], $node_schema['name']) == 0
          && strcasecmp($table_constraint['foreign_key_data']['table']['name'], $node_table['name']) == 0 ) {
          // the constraint is for the table in question
          $constraints[] = $table_constraint;
        }
      }
    }
//dbsteward::console_line(7, $node_table['name'] . " applicable constraints: " . count($constraints));
    return $constraints;
  }

  public static function &get_table_column(&$node_table, $name, $create_if_not_exist = FALSE) {
    $nodes = $node_table->xpath("column[@name='" . $name . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // table not found, caller wants the table created in the db
        $node_column = $node_table->addChild('column');
        $node_column->addAttribute('name', $name);
      }
      else {
        $node_column = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one column named " . $name . " found in table " . $node_table['name'] . " -- panic!");
    }
    else {
      $node_column = $nodes[0];
    }
    return $node_column;
  }

  public static function &get_table_columns($node_table) {
    if ( !is_object($node_table) ) {
      throw new exception("node_table is not an object");
    }
    $nodes = $node_table->xpath("column");
    return $nodes;
  }

  public static function &get_function(&$node_schema, $name, $declaration = NULL, $create_if_not_exist = FALSE) {
    $node_function = NULL;
    $nodes = $node_schema->xpath("function[@name='" . $name . "']");
    // filter out versions of functon in languages that are not relevant to the current format being processed
    $filtered_nodes = array();
    foreach ($nodes as $node) {
      if (format_function::has_definition($node)) {
        $filtered_nodes[] = $node;
      }
    }
    $nodes = $filtered_nodes;

    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // function not found, caller wants the function created in the db
        $node_function = $node_schema->addChild('function');
        $node_function->addAttribute('name', $name);
      }
    }
    else if (count($nodes) > 1) {
      if (strlen($declaration) == 0) {
        throw new exception("more than one match for function " . $name . " and declaration is blank");
      }
      foreach ($nodes AS $node) {
        if (strcasecmp(pgsql8_function::get_declaration($node_schema, $node), $declaration) == 0) {
          if ($node_function == NULL) {
            $node_function = $node;
          }
          else {
            throw new exception("more than one function match " . $name . " matches passed declaration: " . $declaration);
          }
        }
      }
      if ($node_function == NULL) {
        //@DEBUG: use this to make sure function declaration comparisons are working properly
        //dbsteward::console_line(5, "NOTICE: no functions named " . $name . " match passed declaration: " . $declaration);
      }
    }
    else {
      $node_function = $nodes[0];
    }
    return $node_function;
  }

  public static function &get_functions(&$node_schema) {
    $nodes = $node_schema->xpath("function");

    // filter out versions of functon in languages that are not relevant to the current format being processed
    $filtered_nodes = array();
    foreach ($nodes as $node) {
      if (format_function::has_definition($node)) {
        $filtered_nodes[] = $node;
      }
    }
    return $filtered_nodes;
  }

  public static function &get_function_parameter(&$node_function, $name, $create_if_not_exist = FALSE) {
    $nodes = $node_function->xpath("functionParameter[@name='" . $name . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // table not found, caller wants the table created in the db
        $node_function_parameter = $node_function->addChild('functionParameter');
        $node_function_parameter->addAttribute('name', $name);
      }
      else {
        $node_function_parameter = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one functionParameter named " . $name . " found. panic!");
    }
    else {
      $node_function_parameter = $nodes[0];
    }
    return $node_function_parameter;
  }

  public static function &get_function_parameters(&$node_function) {
    $nodes = $node_function->xpath("functionParameter");
    return $nodes;
  }

  public static function get_table_index($node_schema, $node_table, $name) {
    $indexes = self::get_table_indexes($node_schema, $node_table);
    $return_index = NULL;
    foreach ($indexes AS $index) {
      if (strcasecmp($index['name'], $name) == 0) {
        if ($return_index === NULL) {
          $return_index = $index;
        }
        else {
          throw new exception("more than one table " . $node_schema['name'] . '.' . $node_table['name'] . " index called " . $name . " found");
        }
      }
    }
    return $return_index;
  }

  public function &create_table_index(&$node_table, $name) {
    $node_index = $node_table->addChild('index');
    $node_index->addAttribute('name', $name);
    return $node_index;
  }

  public static function get_table_indexes($node_schema, $node_table) {
    $nodes = $node_table->xpath("index");
    // add column unique indexes to the list
    foreach ($node_table->column AS $column) {
      if (isset($column['unique']) && strcasecmp($column['unique'], 'true') == 0) {
        $unique_index = new SimpleXMLElement('<index/>');
        $unique_index['name'] = format_index::index_name($node_table['name'], $column['name'], 'key');
        $unique_index['unique'] = 'true';
        $unique_index['using'] = 'btree';
        $unique_index->addChild('indexDimension', $column['name']);
        $nodes[] = $unique_index;
      }
    }
    return $nodes;
  }

  public static function &get_table_trigger(&$node_schema, &$node_table, $name, $create_if_not_exist = FALSE) {
    $nodes = $node_schema->xpath("trigger[@name='" . $name . "' and @table='" . $node_table['name'] . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // table not found, caller wants the table created in the db
        $node_trigger = $node_schema->addChild('trigger');
        $node_trigger->addAttribute('name', $name);
        $node_trigger->addAttribute('table', $node_table['name']);
      }
      else {
        $node_trigger = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one trigger named " . $name . " found in schema " . $node_schema['name'] . " for table " . $node_table['name'] . " -- panic!");
    }
    else {
      $node_trigger = $nodes[0];
    }
    return $node_trigger;
  }

  public static function get_table_triggers($node_schema, $node_table) {
    $nodes = $node_schema->xpath("trigger[@table='" . $node_table['name'] . "']");
    return $nodes;
  }

  public static function &get_sequence(&$node_schema, $name, $create_if_not_exist = FALSE) {
    $nodes = $node_schema->xpath("sequence[@name='" . $name . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // sequence not found, caller wants the sequence created in the schema
        $node_sequence = $node_schema->addChild('sequence');
        $node_sequence->addAttribute('name', $name);
      }
      else {
        $node_sequence = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one sequence named " . $name . " found. panic!");
    }
    else {
      $node_sequence = $nodes[0];
    }
    return $node_sequence;
  }

  public static function &get_sequences(&$node_schema) {
    $nodes = $node_schema->xpath("sequence");
    return $nodes;
  }

  public static function &get_type(&$node_schema, $name, $create_if_not_exist = FALSE) {
    if (!is_object($node_schema)) {
      throw new exception("node_schema is not an object");
    }
    $nodes = $node_schema->xpath("type[@name='" . $name . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // type not found, caller wants the type created in the schema
        $node_type = $node_schema->addChild('type');
        $node_type->addAttribute('name', $name);
      }
      else {
        $node_type = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one sequence named " . $name . " found. panic!");
    }
    else {
      $node_type = $nodes[0];
    }
    return $node_type;
  }

  public static function &get_types(&$node_schema) {
    $nodes = $node_schema->xpath("type");
    return $nodes;
  }

  public static function &get_view(&$node_schema, $name, $create_if_not_exist = FALSE) {
    $nodes = $node_schema->xpath("view[@name='" . $name . "']");
    if (count($nodes) == 0) {
      if ($create_if_not_exist) {
        // view not found, caller wants the view created in the schema
        $node_view = $node_schema->addChild('view');
        $node_view->addAttribute('name', $name);
      }
      else {
        $node_view = NULL;
      }
    }
    else if (count($nodes) > 1) {
      throw new exception("more than one sequence named " . $name . " found. panic!");
    }
    else {
      $node_view = $nodes[0];
    }
    return $node_view;
  }

  public static function &get_views(&$node_schema) {
    $nodes = $node_schema->xpath("view");
    return $nodes;
  }

  public static function set_attribute(&$node, $name, $value) {
    $node[$name] = $value;
  }

  public static function unset_attribute(&$node, $name) {
    unset($node[$name]);
  }

  public static function &get_permissions(&$node_object) {
    if (!is_object($node_object)) {
      var_dump($node_object);
      throw new exception("node_object is not an object");
    }
    $nodes = $node_object->xpath("grant | revoke");
    return $nodes;
  }

  public static function foreign_key($db_doc, $node_schema, $node_table, $column, &$foreign) {
    $foreign['schema'] = dbx::get_schema($db_doc, $column['foreignSchema']);
    if (!$foreign['schema']) {
      throw new exception("Failed to find foreign schema '" . dbsteward::string_cast($column['foreignSchema']) . "' for " . dbsteward::string_cast($node_schema['name']) . "." . dbsteward::string_cast($node_table['name']) . "." . dbsteward::string_cast($column['name']));
    }

    $foreign['table'] = dbx::get_table($foreign['schema'], $column['foreignTable']);
    if (!$foreign['table']) {
      throw new exception("Failed to find foreign table '" . $column['foreignTable'] . "' for " . $node_schema['name'] . "." . $node_table['name'] . "." . $column['name']);
    }

    // if foreignColumn is not set
    // the column is assumed to be the same name as the referring column
    if (isset($column['foreignColumn']) && strlen($column['foreignColumn'])) {
      $foreignColumn = $column['foreignColumn'];
    }
    else {
      $foreignColumn = $column['name'];
    }
    $foreign['column'] = dbx::get_table_column($foreign['table'], $foreignColumn);
    if (!$foreign['column']) {
      var_dump($foreign['column']);
      throw new exception("Failed to find foreign column '" . dbsteward::string_cast($foreignColumn) . "' for " . dbsteward::string_cast($node_schema['name']) . "." . dbsteward::string_cast($node_table['name']) . "." . dbsteward::string_cast($column['name']));
    }

    // column type is missing, and resolved foreign is also a foreign key?
    // recurse and find the cascading foreign key
    if (strlen($foreign['column']['type']) == 0 && isset($foreign['column']['foreignColumn'])) {
      //dbsteward::console_line(4, "Seeking nested foreign key for " . dbsteward::string_cast($foreign['schema']['name']) . "." . dbsteward::string_cast($foreign['table']['name']) . "." . $foreign['column']['name']);
      $nested_fkey = array();
      self::foreign_key($db_doc, $foreign['schema'], $foreign['table'], $foreign['column'], $nested_fkey);
      //var_dump($nested_fkey['column']);
      // make a separate clone of the column element because we are specifying the type only for foreign key type referencing
      $foreign['column'] = new SimpleXMLElement($foreign['column']->asXML());
      $foreign['column']['type'] = $nested_fkey['column']['type'];
    }

    $foreign['name'] = pgsql8::index_name($node_table['name'], $column['name'], 'fkey');
    $foreign['references'] = dbsteward::string_cast($foreign['schema']['name']) . "." . dbsteward::string_cast($foreign['table']['name']) . "(" . dbsteward::quote_column_name(dbsteward::string_cast($foreign['column']['name'])) . ")";
  }

  /**
   * determine SQL clause expression to match for data_row primary keys
   *
   * @return string
   */
  public static function primary_key_expression($node_schema, $node_table, $data_row_columns, $data_row) {
    $primary_keys = format_table::primary_key_columns($node_table);
    $primary_key_index = xml_parser::data_row_overlay_primary_key_index($primary_keys, $data_row_columns, $data_row_columns);

    // figure out the primary key expression
    $primary_key_expression = array();
    for ($i = 0; $i < count($primary_keys); $i++) {
      if (!isset($primary_key_index[$primary_keys[$i]])) {
        throw new exception("primar key column named " . $primary_keys[$i] . " not found in primary_key_index");
      }

      $primary_column_name = $primary_keys[$i];

      $column_index = $primary_key_index[$primary_column_name]['base_index'];

      // get the type of the column, chasing foreign keys if necessary
      $node_column = dbx::get_table_column($node_table, $primary_column_name);

      $value_type = format_column::column_type(dbsteward::$new_database, $node_schema, $node_table, $node_column, $foreign);
      $primary_key_expression[] = format::get_quoted_column_name($primary_column_name) . ' = ' . format::value_escape($value_type, $data_row->col[$column_index]);
    }

    if (count($primary_key_expression) == 0) {
      throw new exception($node_table['name'] . " primary_key_expression is empty, determinate loop failed");
    }

    return implode(' AND ', $primary_key_expression);
  }

  public static function build_staged_sql($db_doc, $ofs, $stage) {
    // push all sql stage=$stage elements to the passed $ofs output_file_segmenter
    if ($stage === NULL) {
      $ofs->write("\n-- NON-STAGED SQL COMMANDS\n");
    }
    else {
      $ofs->write("\n-- SQL STAGE " . $stage . " COMMANDS\n");
    }
    foreach ($db_doc->sql AS $sql_statement) {
      if ((isset($sql_statement['stage']) && strcasecmp($sql_statement['stage'], $stage) == 0) || (!isset($sql_statement['stage']) && $stage === NULL)) {
        if (isset($sql_statement['comment'])
          && strlen($sql_statement['comment'])) {
          $ofs->write("-- " . $sql_statement['comment'] . "\n");
        }
        $ofs->write(trim($sql_statement) . "  -- LITERAL_SQL_INCLUDE: this line should be included as-is without any parsing\n");
      }
    }
    $ofs->write("\n");
  }
  
  public static function renamed_table_check_pointer($old_schema, &$old_table, $new_schema, $new_table) {
    // if there is an oldName attribute
    if ( !dbsteward::$ignore_oldname ) {
      if ( $new_schema && $new_table && sql99_diff_tables::is_renamed_table($old_schema, $new_schema, $new_table) ) {
        // find the tabel named oldName in $old_schema and 
        // put a pointer to it in $old_table
        if ( ! $old_schema ) {
          throw new exception("Sanity failure: " . $new_table['name'] . " has oldName attribute, but old_schema is not defined");
        }
        $old_table = dbx::get_table($old_schema, $new_table['oldName']);
        if ( ! $old_table ) {
          throw new exception("Sanity failure: " . $new_table['name'] . " has oldName attribute, but table named oldName is not defined in old_schema");
        }
      }
    }
  }
  
  public static function get_renamed_table_old_table($old_schema, $old_table, $new_schema, $new_table) {
    $old_table = dbx::get_table($old_schema, $new_table['oldName']);
    return $old_table;
  }

  public static function to_array($thing, $key=false) {
    if (!($thing instanceof SimpleXMLElement)) {
      $thing = (array)$thing;
    }

    $arr = array();
    if ($key === false) {
      foreach ($thing as $child) {
        $arr[] = $child;
      }
    }
    else {
      foreach ($thing as $child) {
        $arr[] = $child[$key];
      }
    }

    return $arr;
  }
  
}

?>
