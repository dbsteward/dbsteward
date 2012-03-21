<?php
/**
 * DBSteward unit test postgresql connection management
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class dbsteward_pgsql8_connection {

  public function get_dbhost() {
    return constant('PGSQL8_DBHOST');
  }
  public function get_dbport() {
    return constant('PGSQL8_DBPORT');
  }
  public function get_dbname() {
    return constant('PGSQL8_DBNAME');
  }
  public function get_dbuser() {
    return constant('PGSQL8_DBUSER');
  }
  public function get_dbpass() {
    return constant('PGSQL8_DBPASS');
  }
  public function get_dbname_management() {
    return constant('PGSQL8_DBNAME_MANAGEMENT');
  }
  public function get_dbuser_management() {
    return constant('PGSQL8_DBUSER_MANAGEMENT');
  }
  public function get_dbpass_management() {
    return constant('PGSQL8_DBPASS_MANAGEMENT');
  }
  
  protected function get_management_connection_string() {
    $c = "host=" . $this->get_dbhost() . " port=" . $this->get_dbport() . " dbname=" 
      . $this->get_dbname_management() . " user=" . $this->get_dbuser_management() . " password=" . $this->get_dbpass_management() . "";
    return $c;
  }
  protected function get_connection_string() {
    $c = "host=" . $this->get_dbhost() . " port=" . $this->get_dbport() . " dbname=" 
      . $this->get_dbname() . " user=" . $this->get_dbuser() . " password=" . $this->get_dbpass() . "";
    return $c;
  }
  protected $management_conn = null;
  protected $conn = null;
  
  /**
   * used by test tearDowns
   * make sure connection is closed to DB can be dropped
   * when running multiple tests
   */
  public function close_connection() {
    // do is_resource() instead of error suppression
    // because if $this->conn is valid and pg_close() fails, it should make the test fail
    if ( is_resource($this->conn) ) {
      pg_close($this->conn);
    }
  }

  public function query($sql, $throw_on_error = TRUE) {
    $result = pg_query($this->conn, $sql);
    if ( $throw_on_error && !is_resource($result) ) {
      throw new exception("pg_query() failed:\n" . $sql . "\n\nError message: " . pg_last_error($this->conn));
    }
    return $result;
  }

  /**
   * connect and create pgsql database for testing
   * @return void
   */
  public function create_db() {
    $this->conn = pg_connect($this->get_management_connection_string());
    @pg_query($this->conn, "DROP DATABASE " . $this->get_dbname());
    pg_query($this->conn, "CREATE DATABASE " . $this->get_dbname());
    $this->conn = pg_connect($this->get_connection_string());
  }

  public function run_file($file_names) {
    if ( !is_array($file_names) ) {
      $file_names = array($file_names);
    }
    foreach($file_names AS $file_name) {
      echo "Running $file_name..\n";
      $fp = fopen($file_name, "r");
      $line_number = 0;
      if ($fp) {
        while ($line = $this->get_line($fp)) {
          $line_number++;
          if ($line_number % 100 == 0) {
            echo ".";
          }
          $rs = $this->query($line);
        }
        fclose($fp);
      }
      else {
        throw new exception("failed to open " . $file_name);
      }
    }
  }
  
  protected function get_line($fp) {
    $l = '';

    // skip blank lines, skip lines that start with comments
    while (trim($l) == '' || substr(trim($l), 0, 2) == '--') {
      if ($l === FALSE) {
        return '';
      }
      $l = fgets($fp);
    }
    $rv = $l;
    $l = trim($l);

    // multiline function definition detection, using dbsteward function definition termination anchors
    if (stripos($l, 'CREATE') !== FALSE) {
      $end = FALSE;
      if (stripos($l, 'CREATE OR REPLACE FUNCTION') !== FALSE) {
        $end = 'DBSTEWARD_FUNCTION_DEFINITION_END';
      }
      if ($end !== FALSE) {
        while (stripos($l, $end) === FALSE) {
          $l = fgets($fp);
          if ($l === FALSE) {
            break;
          }
          $rv .= $l;
        }
        return $rv;
      }
    }
    // BEGIN; -- STRIP_SLONY: SlonyI installs/upgrades strip this line, the rest need to keep the install transactional
    else if (stripos($l, '; --') !== FALSE) {
      $rv = substr($l, 0, stripos($l, '; --') + 1);
      return $rv;
    }

    // plain semicolon terminated lines
    while (substr($l, -1) !== ';') {
      $l = fgets($fp);
      if ($l === FALSE) {
        break;
      }
      $rv .= $l;
      $l = trim($l);
    }
    return $rv;
  }

}

?>
