<?php

class mock_output_file_segmenter {
  private $output = "";

  public function __construct() {

  }

  public function write($sql) {
    $this->output .= $sql;
  }

  public function _get_output() {
    return $this->output;
  }

  public function _clear_output() {
    $this->output = "";
  }
}
?>
