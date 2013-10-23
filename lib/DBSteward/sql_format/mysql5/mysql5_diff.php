<?php
/**
 * Diff two DBSteward XML definitions, outputting SQL to get from A to B
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mysql5_diff extends sql99_diff {

  /**
   * @NOTICE: sql_format specific!
   * Compare dbsteward::$old_database to dbsteward::$new_database
   * Generate DDL / DML / DCL statements to upgrade old to new
   *
   * Changes are outputted to output_file_segementer members of this class
   *
   * @param  object  $stage1_ofs  stage 1 output file segmentor
   * @param  object  $stage2_ofs  stage 2 output file segmentor
   * @param  object  $stage3_ofs  stage 3 output file segmentor
   * @param  object  $stage4_ofs  stage 4 output file segmentor
   * @return void
   */
  public static function diff_doc_work($stage1_ofs, $stage2_ofs, $stage3_ofs, $stage4_ofs) {
    if (mysql5_diff::$as_transaction) {
      dbsteward::console_line(1, "Most MySQL DDL implicitly commits transactions, so using them is pointless.");
    }

    // start with pre-upgrade sql statements that prepare the database to take on its changes
    dbx::build_staged_sql(dbsteward::$new_database, $stage1_ofs, 'STAGE1BEFORE');
    dbx::build_staged_sql(dbsteward::$new_database, $stage2_ofs, 'STAGE2BEFORE');


    dbsteward::console_line(1, "Revoke Permissions");
    self::revoke_permissions($stage1_ofs, $stage3_ofs);

    dbsteward::console_line(1, "Update Structure");
    self::update_structure($stage1_ofs, $stage3_ofs, self::$new_table_dependency);

    dbsteward::console_line(1, "Update Permissions");
    self::update_permissions($stage1_ofs, $stage3_ofs);

    // self::update_database_config_parameters($stage1_ofs);

    dbsteward::console_line(1, "Update Data");
    self::update_data($stage2_ofs, TRUE);
    self::update_data($stage4_ofs, FALSE);

    // append any literal SQL in new not in old at the end of data stage 1
    $old_sql = dbx::get_sql(dbsteward::$old_database);
    $new_sql = dbx::get_sql(dbsteward::$new_database);
    for ($n = 0; $n < count($new_sql); $n++) {
      if (isset($new_sql[$n]['stage'])) {
        // ignore upgrade staged sql elements
        continue;
      }
      // is this new statement in the old database?
      $found = FALSE;
      for ($o = 0; $o < count($old_sql); $o++) {
        if (isset($old_sql[$o]['stage'])) {
          // ignore upgrade staged sql elements
          continue;
        }
        if (strcmp($new_sql[$n], $old_sql[$o]) == 0) {
          $found = TRUE;
        }
      }
      if (!$found) {
        $stage2_ofs->write($new_sql[$n] . "\n");
      }
    }

    // append stage defined sql statements to appropriate stage file
    dbx::build_staged_sql(dbsteward::$new_database, $stage1_ofs, 'STAGE1');
    dbx::build_staged_sql(dbsteward::$new_database, $stage2_ofs, 'STAGE2');
    dbx::build_staged_sql(dbsteward::$new_database, $stage3_ofs, 'STAGE3');
    dbx::build_staged_sql(dbsteward::$new_database, $stage4_ofs, 'STAGE4');
  }

  public static function revoke_permissions($ofs1, $ofs3) {
    foreach (dbx::get_schemas(dbsteward::$old_database) as $old_schema) {
      $old_permissions = $old_schema->xpath('.//grant');
      foreach ( $old_permissions as $node_permission ) {
        $node_object = $node_permission->xpath('parent::*');
        $node_object = $node_object[0];
        if ( $node_object === null ) {
          // I have a hard time imagining this could actually happen this far along, but better safe than sorry...
          throw new Exception("Could not find parent node of permission GRANT {$node_permission['operation']} TO {$node_permission['role']}");
        }

        $ofs1->write(mysql5_permission::get_permission_sql(dbsteward::$old_database, $old_schema, $node_object, $node_permission, 'revoke')."\n");
      }
    }
  }


  public static function update_permissions($ofs1, $ofs3) {
    foreach (dbx::get_schemas(dbsteward::$new_database) as $new_schema) {
      $permissions = $new_schema->xpath('.//grant');
      foreach ( $permissions as $node_permission ) {
        $node_object = $node_permission->xpath('parent::*');
        $node_object = $node_object[0];
        if ( $node_object === null ) {
          // I have a hard time imagining this could actually happen this far along, but better safe than sorry...
          throw new Exception("Could not find parent node of permission GRANT {$node_permission['operation']} TO {$node_permission['role']}");
        }
        
        $ofs1->write(mysql5_permission::get_permission_sql(dbsteward::$new_database, $new_schema, $node_object, $node_permission, 'grant')."\n");
      }
    }
  }

  protected static function drop_old_schemas($ofs) {
    $drop_sequences = array();

    if (is_array(mysql5_diff::$old_table_dependency)) {
      $deps = mysql5_diff::$old_table_dependency;
      $processed_schemas = array();

      foreach ($deps as $dep) {
        $old_schema = $dep['schema'];

        if (!dbx::get_schema(dbsteward::$new_database, $old_schema['name'])) {
          // this schema is being dropped, drop all children objects in it
          
          if (!in_array(trim($old_schema['name']), $processed_schemas)) {
            // this schema hasn't been processed yet, go ahead and drop views, types, functions, sequences
            // only do it once per schema
            foreach ($old_schema->view as $node_view) {
              $ofs->write(mysql5_view::get_drop_sql($old_schema, $node_view) . "\n");
            }
            foreach ($old_schema->type as $node_type) {
              $ofs->write(mysql5_type::get_drop_sql($old_schema, $node_type) . "\n");
            }
            foreach ($old_schema->function as $node_function) {
              $ofs->write(mysql5_function::get_drop_sql($old_schema, $node_function) . "\n");
            }
            foreach ($old_schema->sequence as $node_sequence) {
              $ofs->write(mysql5_sequence::get_drop_sql($old_schema, $node_sequence) . "\n");
            }

            $processed_schemas[] = trim($old_schema['name']);
          }

          if ( $dep['table']['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
            // don't do anything with this table, it is a magic internal DBSteward value
            continue;
          }

          // constraints, indexes, triggers will be deleted along with the tables they're attached to
          // tables will drop themselves later on
          // $ofs->write(mysql5_table::get_drop_sql($old_schema, $dep['table']) . "\n");
          $table_name = mysql5::get_fully_qualified_table_name($dep['schema']['name'], $dep['table']['name']);
          $ofs->write("-- $table_name triggers, indexes, constraints will be implicitly dropped when the table is dropped\n");
          $ofs->write("-- $table_name will be dropped later according to table dependency order\n");

          // table sequences need dropped separately
          foreach (mysql5_table::get_sequences_needed($old_schema, $dep['table']) as $node_sequence) {
            $ofs->write(mysql5_sequence::get_drop_sql($old_schema, $node_sequence) . "\n");
          }
        }
      }
    }
    else {
      foreach (dbsteward::$old_database->schema as $old_schema) {
        if (!dbx::get_schema(dbsteward::$new_database, $old_schema['name'])) {
          foreach ($old_schema->view as $node_view) {
            $ofs->write(mysql5_view::get_drop_sql($old_schema, $node_view) . "\n");
          }
          foreach ($old_schema->type as $node_type) {
            $ofs->write(mysql5_type::get_drop_sql($old_schema, $node_type) . "\n");
          }
          foreach ($old_schema->function as $node_function) {
            $ofs->write(mysql5_function::get_drop_sql($old_schema, $node_function) . "\n");
          }
          foreach ($old_schema->sequence as $node_sequence) {
            $ofs->write(mysql5_sequence::get_drop_sql($old_schema, $node_sequence) . "\n");
          }
          foreach ($old_schema->table as $node_table) {
            // tables will drop themselves later on
            // $ofs->write(mysql5_table::get_drop_sql($old_schema, $node_table) . "\n");
            $table_name = mysql5::get_fully_qualified_table_name($old_schema['name'], $node_table['name']);
            $ofs->write("-- $table_name triggers, indexes, constraints will be implicitly dropped when the table is dropped\n");
            $ofs->write("-- $table_name will be dropped later according to table dependency order\n");
            foreach (mysql5_table::get_sequences_needed($old_schema, $node_table) as $node_sequence) {
              $ofs->write(mysql5_sequence::get_drop_sql($old_schema, $node_sequence) . "\n");
            }
          }
        }
      }
    }
  }

  /**
   * Updates objects in schemas.
   *
   * @param $ofs1  stage1 output file segmenter
   * @param $ofs3  stage3 output file segmenter
   */
  public static function update_structure($ofs1, $ofs3) {
    if (!mysql5::$use_schema_name_prefix) {
      if (count(dbsteward::$new_database->schema) > 1) {
        throw new Exception("You cannot use more than one schema in mysql5 without schema name prefixing\nPass the --useschemaprefix flag to turn this on");
      }
      if (count(dbsteward::$old_database->schema) > 1) {
        throw new Exception("You cannot use more than one schema in mysql5 without schema name prefixing\nPass the --useschemaprefix flag to turn this on");
      }
    }
    else {
      dbsteward::console_line(1, "Drop Old Schemas");
      self::drop_old_schemas($ofs3);
    }

    // drop all views in all schemas, regardless whether dependency order is known or not
    foreach(dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
      $new_schema = dbx::get_schema(dbsteward::$new_database, $new_schema['name']);
      mysql5_diff_views::drop_views($ofs1, $old_schema, $new_schema);
    }
    
    //@TODO: implement mysql5_language ? no relevant conversion exists see other TODO's stating this
    //mysql5_diff_languages::diff_languages($ofs1);
    // if the table dependency order is unknown, bang them in natural order
    if (!is_array(mysql5_diff::$new_table_dependency)) {
      foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
        //@NOTICE: @TODO: this does not honor old*Name attributes, does it matter?
        $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
        
        mysql5_diff_types::apply_changes($ofs1, $old_schema, $new_schema);
        mysql5_diff_functions::diff_functions($ofs1, $ofs3, $old_schema, $new_schema);
        mysql5_diff_sequences::diff_sequences($ofs1, $ofs3, $old_schema, $new_schema);
        // remove old constraints before table contraints, so the SQL statements succeed
        mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'constraint', TRUE);
        mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'primaryKey', TRUE);
        mysql5_diff_tables::drop_tables($ofs3, $old_schema, $new_schema);
        mysql5_diff_tables::diff_tables($ofs1, $ofs3, $old_schema, $new_schema);
        // mysql5_diff_indexes::diff_indexes($ofs1, $old_schema, $new_schema);
        mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'primaryKey', FALSE);
        mysql5_diff_triggers::diff_triggers($ofs1, $old_schema, $new_schema);
      }
      // non-primary key constraints may be inter-schema dependant, and dependant on other's primary keys
      // and therefore should be done after object creation sections
      foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
        $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
        mysql5_diff_constraints::diff_constraints($ofs1, $old_schema, $new_schema, 'constraint', FALSE);
      }
    }
    else {
      $processed_schemas = array();
      for ($i = 0; $i < count(mysql5_diff::$new_table_dependency); $i++) {
        // find the necessary pointers
        $item = mysql5_diff::$new_table_dependency[$i];
        // @NOTICE: dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME is NOT checked here because these are schema operations

        $new_schema = dbx::get_schema(dbsteward::$new_database, $item['schema']['name']);
        $old_schema = dbx::get_schema(dbsteward::$old_database, $item['schema']['name']);

        // do all types and functions on their own before table creation
        // see next loop for other once per schema work
        if (!in_array(trim($new_schema['name']), $processed_schemas)) {
          mysql5_diff_types::apply_changes($ofs1, $old_schema, $new_schema);
          mysql5_diff_functions::diff_functions($ofs1, $ofs3, $old_schema, $new_schema);
          $processed_schemas[] = trim($new_schema['name']);
        }
      }

      // remove all old constraints before new contraints, in reverse dependency order
      for ($i = count(mysql5_diff::$old_table_dependency) - 1; $i >= 0; $i--) {
        // find the necessary pointers
        $item = mysql5_diff::$old_table_dependency[$i];
        if ( $item['table']['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
          // don't do anything with this table, it is a magic internal DBSteward value
          continue;
        }
        
        $new_schema = dbx::get_schema(dbsteward::$new_database, $item['schema']['name']);
        $new_table = NULL;
        if ($new_schema != NULL) {
          $new_table = dbx::get_table($new_schema, $item['table']['name']);
        }
        $old_schema = dbx::get_schema(dbsteward::$old_database, $item['schema']['name']);
        $old_table = NULL;
        if ($old_schema != NULL) {
          $old_table = dbx::get_table($old_schema, $item['table']['name']);
        }

        if ($old_table == NULL) {
          throw new exception("old_table " . $item['schema']['name'] . "." . $item['table']['name'] . " not found. This is not expected as this reverse constraint loop was based on the old_table_dependency list!");
        }
        
        // @NOTICE: when dropping constraints, dbx::renamed_table_check_pointer() is not called for $old_table
        // as mysql5_diff_tables::diff_constraints_table() will do rename checking when recreating constraints for renamed tables

        mysql5_diff_constraints::diff_constraints_table($ofs1, $old_schema, $old_table, $new_schema, $new_table, 'constraint', TRUE);
        mysql5_diff_constraints::diff_constraints_table($ofs1, $old_schema, $old_table, $new_schema, $new_table, 'primaryKey', TRUE);
      }

      $processed_schemas = array();
      for ($i = 0; $i < count(mysql5_diff::$new_table_dependency); $i++) {
        // find the necessary pointers
        $item = mysql5_diff::$new_table_dependency[$i];

        $new_schema = dbx::get_schema(dbsteward::$new_database, $item['schema']['name']);
        $new_table = NULL;
        if ($new_schema != NULL) {
          $new_table = dbx::get_table($new_schema, $item['table']['name']);
        }
        $old_schema = dbx::get_schema(dbsteward::$old_database, $item['schema']['name']);

        // schema level stuff should only be done once, keep track of which ones we have done
        // see above for pre table creation stuff
        // see below for post table creation stuff
        if (!in_array($new_schema['name'], $processed_schemas)) {
          mysql5_diff_sequences::diff_sequences($ofs1, $ofs3, $old_schema, $new_schema);
          $processed_schemas[] = $new_schema['name'];
        }

        if ( $item['table']['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
          // don't do anything with this table, it is a magic internal DBSteward value
          continue;
        }
        
        $old_table = NULL;
        if ($old_schema != NULL) {
          $old_table = dbx::get_table($old_schema, $item['table']['name']);
        }
        
        dbx::renamed_table_check_pointer($old_schema, $old_table, $new_schema, $new_table);

        mysql5_diff_tables::diff_tables($ofs1, $ofs3, $old_schema, $new_schema, $old_table, $new_table);
        // mysql5_diff_indexes::diff_indexes_table($ofs1, $old_schema, $old_table, $new_schema, $new_table);
        mysql5_diff_constraints::diff_constraints_table($ofs1, $old_schema, $old_table, $new_schema, $new_table, 'primaryKey', FALSE);
        mysql5_diff_triggers::diff_triggers_table($ofs1, $old_schema, $old_table, $new_schema, $new_table);
        mysql5_diff_constraints::diff_constraints_table($ofs1, $old_schema, $old_table, $new_schema, $new_table, 'constraint', FALSE);
      }

      // drop old tables in reverse dependency order
      for ($i = count(mysql5_diff::$old_table_dependency) - 1; $i >= 0; $i--) {
        // find the necessary pointers
        $item = mysql5_diff::$old_table_dependency[$i];
        if ( $item['table']['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
          // don't do anything with this table, it is a magic internal DBSteward value
          continue;
        }

        $new_schema = dbx::get_schema(dbsteward::$new_database, $item['schema']['name']);
        $new_table = NULL;
        if ($new_schema != NULL) {
          $new_table = dbx::get_table($new_schema, $item['table']['name']);
        }
        $old_schema = dbx::get_schema(dbsteward::$old_database, $item['schema']['name']);
        $old_table = NULL;
        if ($old_schema != NULL) {
          $old_table = dbx::get_table($old_schema, $item['table']['name']);
        }

        if ($old_table == NULL) {
          throw new exception("old_table " . $item['schema']['name'] . "." . $item['table']['name'] . " not found. This is not expected as this reverse constraint loop was based on the old_table_dependency list!");
        }

        mysql5_diff_tables::drop_tables($ofs3, $old_schema, $new_schema, $old_table, $new_table);
      }
    }
    
    // create all views in all schemas, regardless whether dependency order is known or not
    foreach(dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
      $new_schema = dbx::get_schema(dbsteward::$new_database, $new_schema['name']);
      mysql5_diff_views::create_views($ofs1, $old_schema, $new_schema);
    }
  }

  /**
   * Updates objects in schemas.
   *
   * @param $ofs          output file segmenter
   * @param $old_database original database
   * @param $new_database new database
   */
  private static function update_data($ofs, $delete_mode = FALSE) {
    if (mysql5_diff::$new_table_dependency != NULL && count(mysql5_diff::$new_table_dependency) > 0) {
      for ($i = 0; $i < count(mysql5_diff::$new_table_dependency); $i++) {
        // go in reverse when in delete mode
        if ($delete_mode) {
          $item = mysql5_diff::$new_table_dependency[count(mysql5_diff::$new_table_dependency) - 1 - $i];
        }
        else {
          $item = mysql5_diff::$new_table_dependency[$i];
        }
        if ( $item['table']['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME ) {
          // don't do anything with this table, it is a magic internal DBSteward value
          continue;
        }

        $old_schema = dbx::get_schema(dbsteward::$old_database, $item['schema']['name']);
        $old_table = NULL;
        if ($old_schema != NULL) {
          $old_table = dbx::get_table($old_schema, $item['table']['name']);
        }
        $new_schema = dbx::get_schema(dbsteward::$new_database, $item['schema']['name']);
        if ($new_schema == NULL) {
          throw new exception("schema " . $item['schema']['name'] . " not found in new database");
        }

        $new_table = dbx::get_table($new_schema, $item['table']['name']);
        if ($new_table == NULL) {
          throw new exception("table " . $item['table']['name'] . " not found in new database schema " . $new_schema['name']);
        }
        $ofs->write(mysql5_diff_tables::get_data_sql($old_schema, $old_table, $new_schema, $new_table, $delete_mode));
      }
    }
    else {
      // dependency order unknown, hit them in natural order
      foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
        $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
        mysql5_diff_tables::diff_data($ofs, $old_schema, $new_schema);
      }
    }
  }


}
?>
