<?php
/**
 * Diff two DBSteward XML definitions, outputting SQL to get from A to B
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class sql99_diff {

  public static $as_transaction = true;
  public static $ignore_function_whitespace = true;
  public static $ignore_start_with = true;
  public static $add_defaults = false;

  public static $old_table_dependency = array();
  public static $new_table_dependency = array();
  
  /**
   * Setup file pointers then call diff_doc_work() to do the actual diffing
   * @param string $old_xml_file
   * @param string $new_xml_file
   * @param SimpleXMLElement $old_database
   * @param SimpleXMLElement $new_database
   * @param string $upgrade_prefix
   */
  public static function diff_doc($old_xml_file, $new_xml_file, $old_database, $new_database, $upgrade_prefix) {
    $timestamp = date('r');
    $old_set_new_set = "-- Old definition:  " . $old_xml_file . "\n" . "-- New definition:  " . $new_xml_file . "\n";

    // setup file pointers, depending on stage file mode -- single (all the same) or multiple
    if ( dbsteward::$single_stage_upgrade ) {
      $single_stage_upgrade_file = $upgrade_prefix . '_single_stage.sql';
      $single_stage_fp = fopen($single_stage_upgrade_file, 'w');
      if ( $single_stage_fp === false ) {
        throw new exception("failed to open upgrade single stage output file " . $single_stage_upgrade_file . ' for write');
      }

      $stage1_ofs = new output_file_segmenter($single_stage_upgrade_file, 1, $single_stage_fp, $single_stage_upgrade_file);
      $stage1_ofs->set_header(
        "-- DBSteward single stage upgrade changes - generated " . $timestamp . "\n" .
        $old_set_new_set);
      $stage2_ofs = &$stage1_ofs;
      $stage3_ofs = &$stage1_ofs;
      $stage4_ofs = &$stage1_ofs;
    }
    else {
      $stage1_ofs = new output_file_segmenter($upgrade_prefix . '_stage1_schema', 1);
      $stage1_ofs->set_header(
        "-- DBSteward stage 1 structure additions and modifications - generated " . $timestamp . "\n" .
        $old_set_new_set);
        
      $stage2_ofs = new output_file_segmenter($upgrade_prefix . '_stage2_data', 1);
      $stage2_ofs->set_header(
        "-- DBSteward stage 2 data definitions removed - generated " . $timestamp . "\n" .
        $old_set_new_set);

      $stage3_ofs = new output_file_segmenter($upgrade_prefix . '_stage3_schema', 1);
      $stage3_ofs->set_header(
        "-- DBSteward stage 3 structure changes, constraints and removals - generated " . $timestamp . "\n" .
        $old_set_new_set);
  
      $stage4_ofs = new output_file_segmenter($upgrade_prefix . '_stage4_data', 1);
      $stage4_ofs->set_header(
        "-- DBSteward stage 4 data definition changes and additions - generated " . $timestamp . "\n" .
        $old_set_new_set);
    }

    dbsteward::$old_database = $old_database;
    dbsteward::$new_database = $new_database;
    
    static::diff_doc_work($stage1_ofs, $stage2_ofs, $stage3_ofs, $stage4_ofs);
  }

  /**
   * Drops old schemas that do not exist anymore.
   *
   * @param  object  $ofs output file pointer
   * @return void
   */
  protected static function drop_old_schemas($ofs) {
    foreach(dbx::get_schemas(dbsteward::$old_database) AS $old_schema) {
      if ( ! dbx::get_schema(dbsteward::$new_database, $old_schema['name']) ) {
        dbsteward::info("Drop Old Schema " . $old_schema['name']);
        pgsql8::set_context_replica_set_id($old_schema);
        $ofs->write(format_schema::get_drop_sql($old_schema));
      }
    }
  }

  /**
   * Creates new schemas (not the objects inside the schemas)
   *
   * @param  object  $ofs output file pointer
   * @return void
   */
  protected static function create_new_schemas($ofs) {
    foreach(dbx::get_schemas(dbsteward::$new_database) AS $new_schema) {
      if (dbx::get_schema(dbsteward::$old_database, $new_schema['name']) == null) {
        dbsteward::info("Create New Schema " . $new_schema['name']);
        pgsql8::set_context_replica_set_id($new_schema);
        $ofs->write(format_schema::get_creation_sql($new_schema));
      }
    }
  }
}

?>
