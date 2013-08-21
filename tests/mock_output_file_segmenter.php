<?php

class mock_output_file_segmenter {
  private $header = '';
  private $output = '';
  private $footer = '';

  public function __construct() {

  }

  public function write($sql) {
    $this->output .= $sql;
  }

  public function append_header($text) {
    $this->header .= $text;
  }

  public function append_footer($text) {
    $this->footer .= $text;
  }

  public function _get_output() {
    return $this->header . "\n" . $this->output . "\n" . $this->footer;
  }

  public function _clear_output() {
    $this->header = '';
    $this->output = '';
    $this->footer = '';
  }
}
