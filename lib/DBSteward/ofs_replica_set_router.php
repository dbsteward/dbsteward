<?php
/**
 * DBSteward output file manager collection manager for replica sets
 * manages output file segmenter sets for replica set writes
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class ofs_replica_set_router {
  
  protected $ofs = NULL;
  
  protected $ignore_unknown_set_ids = FALSE;
  
  public function ignore_unknown_set_ids($ignore = TRUE) {
    $this->ignore_unknown_set_ids = $ignore;
  }
  
  public function __construct() {
    $this->ofs = array();
  }
  
  /**
   * Set the output file segementer for the specified set ID
   * @param integer $set_id
   * @param output_file_segmenter $ofs
   * @return object
   */
  public function set_ofs($set_id, $ofs) {
    $set_id = (string)$set_id;
    $this->ofs[$set_id] = $ofs;
    format::$current_replica_set_id = $set_id;
    return $this->ofs[$set_id];
  }
  
  public function __call($m, $a) {
    $ignore_ofs_methods = array(
      '__destruct'
    );
    if ( in_array($m, $ignore_ofs_methods) ) {
      return 'IGNORE_OFS_COMMAND_COMPLETE';
    }

    // if the command is in the list of commands to run on all ofs objects, do so
    $all_ofs_methods = array(
      'append_header',
      'append_footer'
    );
    if ( in_array($m, $all_ofs_methods) ) {
      foreach($this->ofs AS $ofs) {
        call_user_func_array(array(&$ofs, $m), $a);
      }
      return 'ALL_OFS_COMMAND_COMPLETE';
    }

    if ( !isset($this->ofs[format::$current_replica_set_id]) ) {
      if ( $this->ignore_unknown_set_ids ) {
        //dbsteward::console_line(7, "ofs replica_set_id " . format::$current_replica_set_id . " not defined, skipping ofsr call");
        return FALSE;
      }
      throw new exception("current_replica_set_id " . format::$current_replica_set_id . " not defined");
    }

    $active_set_ofs = $this->ofs[format::$current_replica_set_id];
    return call_user_func_array(array(&$active_set_ofs, $m), $a);
  }

}
