<?php
/**
 * Diff two DBSteward XML definitions, outputting SQL to get from A to B
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

require_once dirname(__FILE__) . '/mssql10_bit_table.php';
require_once dirname(__FILE__) . '/mssql10_column.php';
require_once dirname(__FILE__) . '/mssql10_function.php';
require_once dirname(__FILE__) . '/mssql10_permission.php';
require_once dirname(__FILE__) . '/mssql10_index.php';
require_once dirname(__FILE__) . '/mssql10_schema.php';
require_once dirname(__FILE__) . '/mssql10_table.php';
require_once dirname(__FILE__) . '/mssql10_type.php';
require_once dirname(__FILE__) . '/mssql10_trigger.php';
require_once dirname(__FILE__) . '/mssql10_view.php';
require_once dirname(__FILE__) . '/mssql10_diff_indexes.php';
require_once dirname(__FILE__) . '/mssql10_diff_functions.php';
require_once dirname(__FILE__) . '/mssql10_diff_sequences.php';
require_once dirname(__FILE__) . '/mssql10_diff_tables.php';
require_once dirname(__FILE__) . '/mssql10_diff_triggers.php';
require_once dirname(__FILE__) . '/mssql10_diff_types.php';
require_once dirname(__FILE__) . '/mssql10_diff_views.php';

class mssql10_diff extends pgsql8_diff {

  /**
   * Creates SQL diff of two DBSteward XML definition documents
   *
   * @return array output files list
   */
  public static function diff_doc($old_xml_file, $new_xml_file, $old_database, $new_database, $upgrade_prefix) {
    $files = array();
    $timestamp = date('r');
    $old_set_new_set = "-- Old definition:  " . $old_xml_file . "\n" . "-- New definition:  " . $new_xml_file . "\n";

    // setup file pointers, depending on stage file mode -- single (all the same) or multiple
    if ( dbsteward::$single_stage_upgrade ) {
      $files['schema_stage1'] = $upgrade_prefix . '_single_stage.sql';
      $schema_stage1_fp = fopen($files['schema_stage1'], 'w');
      if ( $schema_stage1_fp === false ) {
        throw new exception("failed to open upgrade single stage output file " . $files['schema_stage1'] . ' for output');
      }
      fwrite($schema_stage1_fp, "-- dbsteward single upgrade file generated " . $timestamp . "\n");
      fwrite($schema_stage1_fp, $old_set_new_set);
  
      $files['schema_stage2'] = $files['schema_stage1'];
      $schema_stage2_fp =& $schema_stage1_fp;
  
      $files['data_stage1'] = $files['schema_stage1'];
      $data_stage1_fp =& $schema_stage1_fp;
  
      $files['data_stage2'] = $files['schema_stage1'];
      $data_stage2_fp =& $schema_stage1_fp;
    }
    else {
      $files['schema_stage1'] = $upgrade_prefix . '_schema_stage1.sql';
      $schema_stage1_fp = fopen($files['schema_stage1'], 'w');
      if ($schema_stage1_fp === FALSE) {
        throw new exception("failed to open upgrade schema stage 1 output file " . $files['schema_stage1'] . ' for output');
      }
      fwrite($schema_stage1_fp, "-- dbsteward schema stage 1 upgrade file generated " . $timestamp . "\n");
      fwrite($schema_stage1_fp, $old_set_new_set);
  
      $files['schema_stage2'] = $upgrade_prefix . '_schema_stage2.sql';
      $schema_stage2_fp = fopen($files['schema_stage2'], 'w');
      if ($schema_stage2_fp === FALSE) {
        throw new exception("failed to open upgrade schema stage 1 output file " . $files['schema_stage2'] . ' for output');
      }
      fwrite($schema_stage2_fp, "-- dbsteward schema stage 2 upgrade file generated " . $timestamp . "\n");
      fwrite($schema_stage2_fp, $old_set_new_set);
  
      $files['data_stage1'] = $upgrade_prefix . '_data_stage1.sql';
      $data_stage1_fp = fopen($files['data_stage1'], 'w');
      if ($data_stage1_fp === FALSE) {
        throw new exception("failed to open upgrade schema stage 1 output file " . $files['data_stage1'] . ' for output');
      }
      fwrite($data_stage1_fp, "-- dbsteward data stage 1 upgrade file generated " . $timestamp . "\n");
      fwrite($data_stage1_fp, $old_set_new_set);
  
      $files['data_stage2'] = $upgrade_prefix . '_data_stage2.sql';
      $data_stage2_fp = fopen($files['data_stage2'], 'w');
      if ($data_stage2_fp === FALSE) {
        throw new exception("failed to open upgrade schema stage 1 output file " . $files['data_stage2'] . ' for output');
      }
      fwrite($data_stage2_fp, "-- dbsteward data stage 2 upgrade file generated " . $timestamp . "\n");
      fwrite($data_stage2_fp, $old_set_new_set);
    }

    dbsteward::$old_database = $old_database;
    dbsteward::$new_database = $new_database;

    mssql10_diff::diff(
      $schema_stage1_fp,
      $schema_stage2_fp,
      $data_stage1_fp,
      $data_stage2_fp
    );

    // if we're in single stage mode, all pointers are the same, only close the file pointer once
    if ( dbsteward::$single_stage_upgrade ) {
      fclose($schema_stage1_fp);
    }
    else {
      fclose($data_stage2_fp);
      fclose($data_stage1_fp);
      fclose($schema_stage2_fp);
      fclose($schema_stage1_fp);
    }

    return $files;
  }

  /**
   * Creates diff from comparison of two database definitions
   *
   * @param fp output file pointer
   * @param old_database original database schema
   * @param new_database new database schema
   */
  private static function diff($schema_stage1_fp, $schema_stage2_fp, $data_stage1_fp, $data_stage2_fp) {
    if (mssql10_diff::$as_transaction) {
      fwrite($schema_stage1_fp, "BEGIN TRANSACTION;\n\n");
      if ( ! dbsteward::$single_stage_upgrade ) {
        fwrite($schema_stage2_fp, "BEGIN TRANSACTION;\n\n");
        fwrite($data_stage1_fp, "BEGIN TRANSACTION;\n\n");
        fwrite($data_stage2_fp, "BEGIN TRANSACTION;\n\n");
      }
    }

    // start with pre-upgrade sql statements that prepare the database to take on its changes
    dbx::build_staged_sql(dbsteward::$new_database, $schema_stage1_fp, 'SCHEMA0');
    dbx::build_staged_sql(dbsteward::$new_database, $data_stage1_fp, 'DATA0');

    dbsteward::console_line(1, "Drop Old Schemas");
    mssql10_diff::drop_old_schemas($schema_stage2_fp);
    dbsteward::console_line(1, "Create New Schemas");
    mssql10_diff::create_new_schemas($schema_stage1_fp);
    dbsteward::console_line(1, "Update Structure");
    mssql10_diff::update_structure($schema_stage1_fp, $schema_stage2_fp, mssql10_diff::$new_table_dependency);
    dbsteward::console_line(1, "Update Permissions");
    mssql10_diff::update_permissions($schema_stage1_fp, $schema_stage2_fp);

    mssql10_diff::update_database_config_parameters($schema_stage1_fp);

    dbsteward::console_line(1, "Update Data");
    mssql10_diff::update_data($data_stage1_fp, TRUE);
    mssql10_diff::update_data($data_stage1_fp, FALSE);

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
        fwrite($data_stage1_fp, $new_sql[$n] . "\n");
      }
    }

    // append stage sql statements to appropriate stage file
    dbx::build_staged_sql(dbsteward::$new_database, $schema_stage1_fp, 'SCHEMA1');
    dbx::build_staged_sql(dbsteward::$new_database, $schema_stage2_fp, 'SCHEMA2');
    dbx::build_staged_sql(dbsteward::$new_database, $data_stage1_fp, 'DATA1');
    dbx::build_staged_sql(dbsteward::$new_database, $data_stage2_fp, 'DATA2');

    if (mssql10_diff::$as_transaction) {
      fwrite($schema_stage1_fp, "\nCOMMIT TRANSACTION;\n");
      if ( ! dbsteward::$single_stage_upgrade ) {
        fwrite($schema_stage2_fp, "\nCOMMIT TRANSACTION;\n");
        fwrite($data_stage1_fp, "\nCOMMIT TRANSACTION;\n");
        fwrite($data_stage2_fp, "\nCOMMIT TRANSACTION;\n");
      }
    }
  }

  /**
   * Drops old schemas that do not exist anymore.
   *
   * @param fp output file pointer
   * @param old_database original database schema
   * @param new_database new database schema
   */
  private static function drop_old_schemas($fp) {
    foreach (dbx::get_schemas(dbsteward::$old_database) AS $old_schema) {
      if (!dbx::get_schema(dbsteward::$new_database, $old_schema['name'])) {
        dbsteward::console_line(3, "Drop Old Schema " . $old_schema['name']);
        fwrite($fp, mssql10_schema::get_drop_sql($old_schema));
      }
    }
  }

  /**
   * Creates new schemas (not the objects inside the schemas)
   *
   * @param $fp    output file pointer
   */
  private static function create_new_schemas($fp) {
    foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      if (dbx::get_schema(dbsteward::$old_database, $new_schema['name']) == NULL) {
        dbsteward::console_line(3, "Crate New Schema " . $new_schema['name']);
        fwrite($fp, mssql10_schema::get_creation_sql($new_schema));
      }
    }
  }

  /**
   * Updates objects in schemas.
   *
   * @param fp1 stage1 output pointer
   * @param fp2 stage2 output pointer
   * @param old_database original database schema
   * @param new_database new database schema
   */
  private static function update_structure($fp1, $fp2) {
    $type_modified_columns = array();
    
    // drop all views in all schemas, regardless whether dependency order is known or not
    foreach(dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
      $new_schema = dbx::get_schema(dbsteward::$new_database, $new_schema['name']);
      mssql10_diff_views::drop_views($fp1, $old_schema, $new_schema);
    }
    
    //@TODO: implement mssql10_language ? no relevant conversion exists see other TODO's stating this
    //mssql10_diff_languages::diff_languages($fp1);
    // if the table dependency order is unknown, bang them in natural order
    if (!is_array(mssql10_diff::$new_table_dependency)) {
      foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
        //@NOTICE: @TODO: this does not honor oldName attributes, does it matter?
        $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
        mssql10_diff_types::apply_changes($fp1, $old_schema, $new_schema, $type_modified_columns);
        mssql10_diff_functions::diff_functions($fp1, $fp2, $old_schema, $new_schema);
        mssql10_diff_sequences::diff_sequences($fp1, $old_schema, $new_schema);
        // remove old constraints before table contraints, so the SQL statements succeed
        mssql10_diff_tables::diff_constraints($fp1, $old_schema, $new_schema, 'constraint', TRUE);
        mssql10_diff_tables::diff_constraints($fp1, $old_schema, $new_schema, 'primaryKey', TRUE);
        mssql10_diff_tables::drop_tables($fp2, $old_schema, $new_schema);
        mssql10_diff_tables::diff_tables($fp1, $fp2, $old_schema, $new_schema);
        mssql10_diff_indexes::diff_indexes($fp1, $old_schema, $new_schema);
        mssql10_diff_tables::diff_clusters($fp1, $old_schema, $new_schema);
        mssql10_diff_tables::diff_constraints($fp1, $old_schema, $new_schema, 'primaryKey', FALSE);
        mssql10_diff_triggers::diff_triggers($fp1, $old_schema, $new_schema);
      }
      // non-primary key constraints may be inter-schema dependant, and dependant on other's primary keys
      // and therefore should be done after object creation sections
      foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
        $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
        mssql10_diff_tables::diff_constraints($fp1, $old_schema, $new_schema, 'constraint', FALSE);
      }
    }
    else {
      $processed_schemas = array();
      for ($i = 0; $i < count(mssql10_diff::$new_table_dependency); $i++) {
        // find the necessary pointers
        $item = mssql10_diff::$new_table_dependency[$i];
        // @NOTICE: dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME is NOT checked here because these are schema operations

        $new_schema = dbx::get_schema(dbsteward::$new_database, $item['schema']['name']);
        $old_schema = dbx::get_schema(dbsteward::$old_database, $item['schema']['name']);

        // do all types and functions on their own before table creation
        // see next loop for other once per schema work
        if (!in_array(trim($new_schema['name']), $processed_schemas)) {
          mssql10_diff_types::apply_changes($fp1, $old_schema, $new_schema, $type_modified_columns);
          mssql10_diff_functions::diff_functions($fp1, $fp2, $old_schema, $new_schema);
          $processed_schemas[] = trim($new_schema['name']);
        }
      }

      // remove all old constraints before new contraints, in reverse dependency order
      for ($i = count(mssql10_diff::$old_table_dependency) - 1; $i >= 0; $i--) {
        // find the necessary pointers
        $item = mssql10_diff::$old_table_dependency[$i];
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
        // as mssql10_diff_tables::diff_constraints_table() will do rename checking when recreating constraints for renamed tables

        mssql10_diff_tables::diff_constraints_table($fp1, $old_schema, $old_table, $new_schema, $new_table, 'constraint', TRUE);
        mssql10_diff_tables::diff_constraints_table($fp1, $old_schema, $old_table, $new_schema, $new_table, 'primaryKey', TRUE);
      }

      $processed_schemas = array();
      for ($i = 0; $i < count(mssql10_diff::$new_table_dependency); $i++) {
        // find the necessary pointers
        $item = mssql10_diff::$new_table_dependency[$i];

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
          mssql10_diff_sequences::diff_sequences($fp1, $old_schema, $new_schema);
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

        mssql10_diff_tables::diff_tables($fp1, $fp2, $old_schema, $new_schema, $old_table, $new_table);
        mssql10_diff_indexes::diff_indexes_table($fp1, $old_schema, $old_table, $new_schema, $new_table);
        mssql10_diff_tables::diff_clusters_table($fp1, $old_schema, $old_table, $new_schema, $new_table);
        mssql10_diff_tables::diff_constraints_table($fp1, $old_schema, $old_table, $new_schema, $new_table, 'primaryKey', FALSE);
        mssql10_diff_triggers::diff_triggers_table($fp1, $old_schema, $old_table, $new_schema, $new_table);
        mssql10_diff_tables::diff_constraints_table($fp1, $old_schema, $old_table, $new_schema, $new_table, 'constraint', FALSE);
      }

      // drop old tables in reverse dependency order
      for ($i = count(mssql10_diff::$old_table_dependency) - 1; $i >= 0; $i--) {
        // find the necessary pointers
        $item = mssql10_diff::$old_table_dependency[$i];
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

        mssql10_diff_tables::drop_tables($fp2, $old_schema, $new_schema, $old_table, $new_table);
      }
    }
    
    // create all views in all schemas, regardless whether dependency order is known or not
    foreach(dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
      $new_schema = dbx::get_schema(dbsteward::$new_database, $new_schema['name']);
      mssql10_diff_views::create_views($fp1, $old_schema, $new_schema);
    }
  }

  protected static function update_permissions($fp1, $fp2) {
    foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
      foreach (dbx::get_permissions($new_schema) AS $new_permission) {
        if ($old_schema == NULL || !mssql10_permission::has_permission($old_schema, $new_permission)) {
          fwrite($fp1, mssql10_permission::get_sql(dbsteward::$new_database, $new_schema, $new_schema, $new_permission) . "\n");
        }
      }

      foreach (dbx::get_tables($new_schema) AS $new_table) {
        $old_table = NULL;
        if ($old_schema != NULL) {
          $old_table = dbx::get_table($old_schema, $new_table['name']);
        }        
        if ( !dbsteward::$ignore_oldname && mssql10_diff_tables::is_renamed_table($old_schema, $new_schema, $new_table) ) {
          // oldName renamed table ? skip permission diffing on it, it is the same
          continue;
        }
        foreach (dbx::get_permissions($new_table) AS $new_permission) {
          if ($old_table == NULL || !mssql10_permission::has_permission($old_table, $new_permission)) {
            fwrite($fp1, mssql10_permission::get_sql(dbsteward::$new_database, $new_schema, $new_table, $new_permission) . "\n");
          }
        }
      }

      foreach (dbx::get_sequences($new_schema) AS $new_sequence) {
        $old_sequence = NULL;
        if ($old_schema != NULL) {
          $old_sequence = dbx::get_sequence($old_schema, $new_sequence['name']);
        }
        foreach (dbx::get_permissions($new_sequence) AS $new_permission) {
          if ($old_sequence == NULL || !mssql10_permission::has_permission($old_sequence, $new_permission)) {
            fwrite($fp1, mssql10_permission::get_sql(dbsteward::$new_database, $new_schema, $new_sequence, $new_permission) . "\n");
          }
        }
      }

      foreach (dbx::get_functions($new_schema) AS $new_function) {
        $old_function = NULL;
        if ($old_schema != NULL) {
          $old_function = dbx::get_function($old_schema, $new_function['name'], mssql10_function::get_declaration($new_schema, $new_function));
        }
        foreach (dbx::get_permissions($new_function) AS $new_permission) {
          if ($old_function == NULL || !mssql10_permission::has_permission($old_function, $new_permission)) {
            fwrite($fp1, mssql10_permission::get_sql(dbsteward::$new_database, $new_schema, $new_function, $new_permission) . "\n");
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
          || !mssql10_permission::has_permission($old_view, $new_permission)
          // OR if the view has changed, as that means it has been recreated
          || mssql10_diff_views::is_view_modified($old_view, $new_view) ) {
            // view permissions are in schema stage 2 file because views are (re)created in that file for SELECT * expansion
            fwrite($fp2, mssql10_permission::get_sql(dbsteward::$new_database, $new_schema, $new_view, $new_permission) . "\n");
          }
        }
      }
    }
  }

  /**
   * Updates objects in schemas.
   *
   * @param fp output file pointer
   * @param old_database original database schema
   * @param new_database new database schema
   */
  private static function update_data($fp, $delete_mode = FALSE) {
    if (mssql10_diff::$new_table_dependency != NULL && count(mssql10_diff::$new_table_dependency) > 0) {
      for ($i = 0; $i < count(mssql10_diff::$new_table_dependency); $i++) {
        // go in reverse when in delete mode
        if ($delete_mode) {
          $item = mssql10_diff::$new_table_dependency[count(mssql10_diff::$new_table_dependency) - 1 - $i];
        }
        else {
          $item = mssql10_diff::$new_table_dependency[$i];
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
        fwrite($fp, mssql10_diff_tables::get_data_sql($old_schema, $old_table, $new_schema, $new_table, $delete_mode));
      }
    }
    else {
      // dependency order unknown, hit them in natural order
      foreach (dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
        $old_schema = dbx::get_schema(dbsteward::$old_database, $new_schema['name']);
        mssql10_diff_tables::diff_data($fp, $old_schema, $new_schema);
      }
    }
  }
}

?>
