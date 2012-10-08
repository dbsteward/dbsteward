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
   * Setup pointers and references and then call diff_doc_work() to do the actual diffing
   *
   * @return void
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
        "-- DBSteward stage 1 pre replication alteration, structure changes - generated " . $timestamp . "\n" .
        $old_set_new_set);
        
      $stage2_ofs = new output_file_segmenter($upgrade_prefix . '_stage2_data', 1);
      $stage2_ofs->set_header(
        "-- DBSteward stage 2 pre replication alteration, data changes - generated " . $timestamp . "\n" .
        $old_set_new_set);

      $stage3_ofs = new output_file_segmenter($upgrade_prefix . '_stage3_schema', 1);
      $stage3_ofs->set_header(
        "-- DBSteward stage 3 post replication alteration, structure changes - generated " . $timestamp . "\n" .
        $old_set_new_set);
  
      $stage4_ofs = new output_file_segmenter($upgrade_prefix . '_stage4_data', 1);
      $stage4_ofs->set_header(
        "-- DBSteward stage 4 post replication alteration, data changes - generated " . $timestamp . "\n" .
        $old_set_new_set);
    }

    dbsteward::$old_database = $old_database;
    dbsteward::$new_database = $new_database;
    
    static::diff_doc_work($stage1_ofs, $stage2_ofs, $stage3_ofs, $stage4_ofs);
  }

}

?>
