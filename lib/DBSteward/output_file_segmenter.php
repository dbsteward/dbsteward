<?php
/**
 * DBSteward output file manager
 * manages line limits for dbsteward::$output_file_statement_limit segmenting
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class output_file_segmenter {

  protected $comment_line_prefix = "--";
  protected $base_file_name;
  protected $file_segment;
  protected $file_pointer = NULL;
  protected $current_output_file;
  protected $statement_count;
  protected $segmenting_enabled = TRUE;
  protected $content_header = NULL;
  protected $content_footer = NULL;
  protected $fixed_file_pointer = FALSE;
  protected $wrote_fixed_file_header = FALSE;
  protected $write_was_called_ever = FALSE;

  /**
   * constructor
   *
   * @param  string    $base_file_name         file name for output, to append file segment number to
   * @param  integer   $starting_file_segment  starting segment number for file segments
   * @param  resource  $file_pointer           static file pointer to use for output -- specifying this will disable internal file segmenting
   * @param  resource  $current_output_file    matching file name for static file pointer to use for all output
   *
   */
  function __construct($base_file_name, $starting_file_segment = 1, $file_pointer = NULL, $current_output_file = NULL) {
    $this->base_file_name = $base_file_name;
    $this->file_segment = $starting_file_segment;
    $this->statement_count = 0;
    if ( $file_pointer !== NULL ) {
      if ( strlen($current_output_file) == 0 ) {
        throw new exception("if file_pointer is specified, current_output_file must also be passed");
      }
      $this->file_pointer =& $file_pointer;
      $this->current_output_file = $current_output_file;
      dbsteward::notice("[File Segment] Fixed output file: " . $this->current_output_file);
      $this->segmenting_enabled = FALSE;
      $this->fixed_file_pointer = TRUE;
      $this->wrote_fixed_file_header = FALSE;
    }
  }
  
  function __destruct() {
    // before we insist on writing the footer
    // if write was never called then the file segmenting has not initialized
    // and will blow up when write_footer() calls write()
    if ( !$this->write_was_called_ever ) {
      $this->write("\n");
    }
    $this->write_footer();
    fclose($this->file_pointer);
  }

  public function set_comment_line_prefix($prefix) {
    $this->comment_line_prefix = $prefix;
  }
  
  public function set_header($text) {
    $this->content_header = $text;
  }
  
  public function append_header($text) {
    $this->content_header .= $text;
  }
  
  public function disable_fixed_file_header() {
    if ( ! $this->fixed_file_pointer ) {
      throw new exception("fixed file pointer not in user and tried to disable_fixed_file_header()");
    }
    $this->wrote_fixed_file_header = TRUE;
  }
  
  protected function write_header() {
    $se = $this->segmenting_enabled;
    $this->segmenting_enabled = FALSE;

    $this->write($this->comment_line_prefix . " " . $this->current_output_file . "\n");
    $this->write($this->content_header);

    $this->segmenting_enabled = $se;
  }
  
  public function append_footer($text) {
    $this->content_footer .= $text;
  }
  
  protected function write_footer() {
    $se = $this->segmenting_enabled;
    $this->segmenting_enabled = FALSE;

    $this->write($this->content_footer);

    $this->segmenting_enabled = $se;
  }
  
  /**
   * determine the next file segment and open that file and file pointer for all future writes
   *
   * @return void
   */
  protected function next_file_segment() {
    if ( ! $this->segmenting_enabled ) {
      throw new exception("next_file_segment called while segmenting_enabled is false. base_file_name = " . $this->base_file_name);
    }
    if ( $this->file_pointer !== NULL ) {
      $this->write_footer();
      fclose($this->file_pointer);
      $this->file_segment += 1;
    }
    $this->current_output_file = $this->base_file_name . $this->file_segment . '.sql';
    dbsteward::notice("[File Segment] Opening output file segement " . $this->current_output_file);
    $this->file_pointer = fopen($this->current_output_file, 'w');
    $this->write_header();
    $this->statement_count = 0;
  }
  
  /**
   * Check to see if the statement count has reached the maximum per file segment
   * If it has, move to the next file segment
   *
   * @return void
   */
  protected function check_statement_count() {
    if ( ! $this->segmenting_enabled ) {
      return;
    }
    if ( $this->statement_count >= dbsteward::$output_file_statement_limit ) {
      $this->next_file_segment();
    }
  }
  
  /**
   * Count SQL statements in $sql and increment the statement counter accordingly
   *
   * @param  $sql
   * @return void
   */
  protected function count_statements($sql) {
    //@TODO is this method adequate?
    $lines = explode("\n", $sql);
    foreach($lines as $line) {
      // strip comments off end of line
      if ( strpos($line, '--') !== FALSE ) {
        $line = substr($line, 0, strpos($line, '--'));
      }
      // kill whitespace at end of line
      $line = rtrim($line);
      // does the line end in a semicolon?
      if ( substr($line, -1) == ';' ) {
        $this->statement_count += 1;
      }
    }
  }

  /**
   * Write $sql to current file segment
   *
   * @param  string    $sql
   *
   * @return void
   */
  public function write($sql) {
    // do the next segment if the pointer has not been set
    // this is for first file header setup between set_header() / append_header() and write time
    if ( $this->file_pointer === NULL ) {
      $this->next_file_segment();
    }
    // if this segmenter is using a fixed file pointer
    // need to do write_header() because next_file_segment() isn't going to get called
    if ( $this->fixed_file_pointer && !$this->wrote_fixed_file_header ) {
      $this->wrote_fixed_file_header = TRUE;
      $this->write_header();
    }
    if ( ($bytes_written = fwrite($this->file_pointer, $sql)) === FALSE ) {
      throw new exception("failed to write to file_pointer: " . var_export($this->file_pointer, TRUE) . " text: " . $sql);
    }
    $this->count_statements($sql);
    $this->check_statement_count();
    
    $this->write_was_called_ever = TRUE;
  }
  
  /**
   * Write line $line to current file segment
   *
   * @param  string    $line
   *
   * @return void
   */
  public function write_line($line) {
    self::write($line . "\n");
  }

}

?>
