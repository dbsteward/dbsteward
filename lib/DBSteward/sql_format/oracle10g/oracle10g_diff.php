<?php
/**
 * Diff two DBSteward XML definitions, outputting SQL to get from A to B
 *
 * @package DBSteward
 * @subpackage oracle10g
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class oracle10g_diff extends sql99_diff {

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
  protected static function diff_doc_work($stage1_ofs, $stage2_ofs, $stage3_ofs, $stage4_ofs) {
    if (oracle10g_diff::$as_transaction) {
      $stage1_ofs->append_header("SET TRANSACTION READ WRITE NAME 'dbsteward_stage1';\n\n");
      $stage1_ofs->append_footer("\nCOMMIT;\n");
      if ( ! dbsteward::$single_stage_upgrade ) {
        $stage2_ofs->append_header("SET TRANSACTION READ WRITE NAME 'dbsteward_stage2';\n\n");
        $stage3_ofs->append_header("SET TRANSACTION READ WRITE NAME 'dbsteward_stage3';\n\n");
        $stage4_ofs->append_header("SET TRANSACTION READ WRITE NAME 'dbsteward_stage4';\n\n");
        $stage2_ofs->append_footer("\nCOMMIT;\n");
        $stage3_ofs->append_footer("\nCOMMIT;\n");
        $stage4_ofs->append_footer("\nCOMMIT;\n");
      }
    }

    // start with pre-upgrade sql statements that prepare the database to take on its changes
    dbx::build_staged_sql(dbsteward::$new_database, $stage1_ofs, 'STAGE1BEFORE');
    dbx::build_staged_sql(dbsteward::$new_database, $stage2_ofs, 'STAGE2BEFORE');

    dbsteward::console_line(1, "Drop Old Schemas");
    oracle10g_diff::drop_old_schemas($stage3_ofs);
    dbsteward::console_line(1, "Create New Schemas");
    oracle10g_diff::create_new_schemas($stage1_ofs);
    dbsteward::console_line(1, "Update Structure");
    oracle10g_diff::update_structure($stage1_ofs, $stage3_ofs, oracle10g_diff::$new_table_dependency);
    dbsteward::console_line(1, "Update Permissions");
    oracle10g_diff::update_permissions($stage1_ofs, $stage3_ofs);

    oracle10g_diff::update_database_config_parameters($stage1_ofs);

    dbsteward::console_line(1, "Update Data");
    oracle10g_diff::update_data($stage2_ofs, TRUE);
    oracle10g_diff::update_data($stage2_ofs, FALSE);

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

  /**
   * Updates objects in schemas.
   *
   * @param $ofs1  stage1 output file segmenter
   * @param $ofs3  stage3 output file segmenter
   */
  private static function update_structure($ofs1, $ofs3) {
    $type_modified_columns = array();
    
    // drop all views in all schemas, regardless whether dependency order is known or not
    foreach(dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
      $new_schema = dbx::get_schema(dbsteward::$new_database, $new_schema['name']);
      oracle10g_diff_views::drop_views($ofs1, $old_schema, $new_schema);
    }
    
    //@TODO: implement oracle10g_language ? no relevant conversion exists see other TODO's stating this
    //oracle10g_diff_languages::diff_languages($ofs1);
    // if the table dependency order is unknown, bang them in natural order
    if (!is_array(oracle10g_diff::$new_table_dependency)) {
      foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
        //@NOTICE: @TODO: this does not honor oldName attributes, does it matter?
        $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
        oracle10g_diff_types::apply_changes($ofs1, $old_schema, $new_schema, $type_modified_columns);
        oracle10g_diff_functions::diff_functions($ofs1, $ofs3, $old_schema, $new_schema);
        oracle10g_diff_sequences::diff_sequences($ofs1, $old_schema, $new_schema);
        // remove old constraints before table contraints, so the SQL statements succeed
        oracle10g_diff_tables::diff_constraints($ofs1, $old_schema, $new_schema, 'constraint', TRUE);
        oracle10g_diff_tables::diff_constraints($ofs1, $old_schema, $new_schema, 'primaryKey', TRUE);
        oracle10g_diff_tables::drop_tables($ofs3, $old_schema, $new_schema);
        oracle10g_diff_tables::diff_tables($ofs1, $ofs3, $old_schema, $new_schema);
        oracle10g_diff_indexes::diff_indexes($ofs1, $old_schema, $new_schema);
        oracle10g_diff_tables::diff_clusters($ofs1, $old_schema, $new_schema);
        oracle10g_diff_tables::diff_constraints($ofs1, $old_schema, $new_schema, 'primaryKey', FALSE);
        oracle10g_diff_triggers::diff_triggers($ofs1, $old_schema, $new_schema);
      }
      // non-primary key constraints may be inter-schema dependant, and dependant on other's primary keys
      // and therefore should be done after object creation sections
      foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
        $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
        oracle10g_diff_tables::diff_constraints($ofs1, $old_schema, $new_schema, 'constraint', FALSE);
      }
    }
    else {
      $processed_schemas = array();
      for ($i = 0; $i < count(oracle10g_diff::$new_table_dependency); $i++) {
        // find the necessary pointers
        $item = oracle10g_diff::$new_table_dependency[$i];
        // @NOTICE: dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME is NOT checked here because these are schema operations

        $new_schema = dbx::get_schema(dbsteward::$new_database, $item['schema']['name']);
        $old_schema = dbx::get_schema(dbsteward::$old_database, $item['schema']['name']);

        // do all types and functions on their own before table creation
        // see next loop for other once per schema work
        if (!in_array(trim($new_schema['name']), $processed_schemas)) {
          oracle10g_diff_types::apply_changes($ofs1, $old_schema, $new_schema, $type_modified_columns);
          oracle10g_diff_functions::diff_functions($ofs1, $ofs3, $old_schema, $new_schema);
          $processed_schemas[] = trim($new_schema['name']);
        }
      }

      // remove all old constraints before new contraints, in reverse dependency order
      for ($i = count(oracle10g_diff::$old_table_dependency) - 1; $i >= 0; $i--) {
        // find the necessary pointers
        $item = oracle10g_diff::$old_table_dependency[$i];
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
        // as oracle10g_diff_tables::diff_constraints_table() will do rename checking when recreating constraints for renamed tables

        oracle10g_diff_tables::diff_constraints_table($ofs1, $old_schema, $old_table, $new_schema, $new_table, 'constraint', TRUE);
        oracle10g_diff_tables::diff_constraints_table($ofs1, $old_schema, $old_table, $new_schema, $new_table, 'primaryKey', TRUE);
      }

      $processed_schemas = array();
      for ($i = 0; $i < count(oracle10g_diff::$new_table_dependency); $i++) {
        // find the necessary pointers
        $item = oracle10g_diff::$new_table_dependency[$i];

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
          oracle10g_diff_sequences::diff_sequences($ofs1, $old_schema, $new_schema);
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

        oracle10g_diff_tables::diff_tables($ofs1, $ofs3, $old_schema, $new_schema, $old_table, $new_table);
        oracle10g_diff_indexes::diff_indexes_table($ofs1, $old_schema, $old_table, $new_schema, $new_table);
        oracle10g_diff_tables::diff_clusters_table($ofs1, $old_schema, $old_table, $new_schema, $new_table);
        oracle10g_diff_tables::diff_constraints_table($ofs1, $old_schema, $old_table, $new_schema, $new_table, 'primaryKey', FALSE);
        oracle10g_diff_triggers::diff_triggers_table($ofs1, $old_schema, $old_table, $new_schema, $new_table);
        oracle10g_diff_tables::diff_constraints_table($ofs1, $old_schema, $old_table, $new_schema, $new_table, 'constraint', FALSE);
      }

      // drop old tables in reverse dependency order
      for ($i = count(oracle10g_diff::$old_table_dependency) - 1; $i >= 0; $i--) {
        // find the necessary pointers
        $item = oracle10g_diff::$old_table_dependency[$i];
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

        oracle10g_diff_tables::drop_tables($ofs3, $old_schema, $new_schema, $old_table, $new_table);
      }
    }
    
    // create all views in all schemas, regardless whether dependency order is known or not
    foreach(dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
      $new_schema = dbx::get_schema(dbsteward::$new_database, $new_schema['name']);
      oracle10g_diff_views::create_views($ofs1, $old_schema, $new_schema);
    }
  }

  protected static function update_permissions($ofs1, $ofs3) {
    foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
      foreach (dbx::get_permissions($new_schema) AS $new_permission) {
        if ($old_schema == NULL || !oracle10g_permission::has_permission($old_schema, $new_permission)) {
          $ofs1->write(oracle10g_permission::get_sql(dbsteward::$new_database, $new_schema, $new_schema, $new_permission) . "\n");
        }
      }

      foreach (dbx::get_tables($new_schema) AS $new_table) {
        $old_table = NULL;
        if ($old_schema != NULL) {
          $old_table = dbx::get_table($old_schema, $new_table['name']);
        }        
        if ( !dbsteward::$ignore_oldname && oracle10g_diff_tables::is_renamed_table($old_schema, $new_schema, $new_table) ) {
          // oldName renamed table ? skip permission diffing on it, it is the same
          continue;
        }
        foreach (dbx::get_permissions($new_table) AS $new_permission) {
          if ($old_table == NULL || !oracle10g_permission::has_permission($old_table, $new_permission)) {
            $ofs1->write(oracle10g_permission::get_sql(dbsteward::$new_database, $new_schema, $new_table, $new_permission) . "\n");
          }
        }
      }

      foreach (dbx::get_sequences($new_schema) AS $new_sequence) {
        $old_sequence = NULL;
        if ($old_schema != NULL) {
          $old_sequence = dbx::get_sequence($old_schema, $new_sequence['name']);
        }
        foreach (dbx::get_permissions($new_sequence) AS $new_permission) {
          if ($old_sequence == NULL || !oracle10g_permission::has_permission($old_sequence, $new_permission)) {
            $ofs1->write(oracle10g_permission::get_sql(dbsteward::$new_database, $new_schema, $new_sequence, $new_permission) . "\n");
          }
        }
      }

      foreach (dbx::get_functions($new_schema) AS $new_function) {
        $old_function = NULL;
        if ($old_schema != NULL) {
          $old_function = dbx::get_function($old_schema, $new_function['name'], oracle10g_function::get_declaration($new_schema, $new_function));
        }
        foreach (dbx::get_permissions($new_function) AS $new_permission) {
          if ($old_function == NULL || !oracle10g_permission::has_permission($old_function, $new_permission)) {
            $ofs1->write(oracle10g_permission::get_sql(dbsteward::$new_database, $new_schema, $new_function, $new_permission) . "\n");
          }
        }
      }

      foreach (dbx::get_views($new_schema) AS $new_view) {
        $old_view = NULL;
        if ($old_schema != NULL) {
          $old_view = dbx::get_view($old_schema, $new_view['name']);
        }
        foreach (dbx::get_permissions($new_view) AS $new_permission) {
          // if always_recreate_views flag is on, always grant all view permissions, as the view was recreated
          if ( dbsteward::$always_recreate_views
          // OR if the view did not exist before
          || $old_view == NULL
          // OR if the view did not have the permission before
          || !oracle10g_permission::has_permission($old_view, $new_permission)
          // OR if the view has changed, as that means it has been recreated
          || oracle10g_diff_views::is_view_modified($old_view, $new_view) ) {
            // view permissions are in stage 3 file because views are (re)created in that file for SELECT * expansion
            $ofs3->write(oracle10g_permission::get_sql(dbsteward::$new_database, $new_schema, $new_view, $new_permission) . "\n");
          }
        }
      }
    }
  }

  /**
   * Updates objects in schemas.
   *
   * @param $ofs          output file segmenter
   * @param $old_database original database schema
   * @param $new_database new database schema
   */
  private static function update_data($ofs, $delete_mode = FALSE) {
    if (oracle10g_diff::$new_table_dependency != NULL && count(oracle10g_diff::$new_table_dependency) > 0) {
      for ($i = 0; $i < count(oracle10g_diff::$new_table_dependency); $i++) {
        // go in reverse when in delete mode
        if ($delete_mode) {
          $item = oracle10g_diff::$new_table_dependency[count(oracle10g_diff::$new_table_dependency) - 1 - $i];
        }
        else {
          $item = oracle10g_diff::$new_table_dependency[$i];
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
        $ofs->write(oracle10g_diff_tables::get_data_sql($old_schema, $old_table, $new_schema, $new_table, $delete_mode));
      }
    }
    else {
      // dependency order unknown, hit them in natural order
      foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
        $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
        oracle10g_diff_tables::diff_data($fp, $old_schema, $new_schema);
      }
    }
  }
}

?>
