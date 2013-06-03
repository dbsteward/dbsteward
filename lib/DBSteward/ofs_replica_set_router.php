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
    $this->ofs[$set_id] = $ofs;
    format::$current_replica_set_id = $set_id;
    return $this->ofs[$set_id];
  }
  
  public function __call($m, $a) {
    if ( !isset($this->ofs[format::$current_replica_set_id]) ) {
      throw new exception("current_replica_set_id " . format::$current_replica_set_id . " not defined");
    }
    $active_set_ofs = $this->ofs[format::$current_replica_set_id];
    return call_user_func_array(array(&$active_set_ofs, $m), $a);
  }

}
