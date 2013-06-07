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
  
  protected $skip_unknown_set_ids = FALSE;
  
  public function skip_unknown_set_ids($ignore = TRUE) {
    $this->skip_unknown_set_ids = $ignore;
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
  public function set_replica_set_ofs($set_id, $ofs) {
    if ( !is_numeric($set_id) || $set_id < 1 ) {
      throw new exception("set_replica_set_ofs() passed invalid replica set id");
    }
    $set_id = (integer)$set_id;
    $this->ofs[$set_id] = $ofs;
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

    $use_replica_set_id = format::get_context_replica_set_id();

    if ( $use_replica_set_id == -10 ) {
      // context_replica_set_id -10 means object does not have slonySetId defined
      // use the natural first replica set as the replica context
      $first_replica_set = pgsql8::get_slony_replica_set_natural_first(dbsteward::$new_database);
      $use_replica_set_id = (integer)($first_replica_set['id']);
    }
    
    // make sure replica set id to use is known
    if ( !isset($this->ofs[$use_replica_set_id]) ) {
      if ( $this->skip_unknown_set_ids ) {
        dbsteward::console_line(7, "[OFS RSR] context replica set ID is " . $use_replica_set_id . ", but no replica set by that ID, skipping output");
        return FALSE;
      }
      throw new exception("context replica set ID " . $use_replica_set_id . " not defined");
    }
    
    $active_set_ofs = $this->ofs[$use_replica_set_id];
    return call_user_func_array(array(&$active_set_ofs, $m), $a);
  }

}
