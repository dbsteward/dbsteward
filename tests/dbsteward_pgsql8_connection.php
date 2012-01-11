<?php
/**
 * DBSteward unit test postgresql connection management
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @subpackage Tests
 * @version $Id: dbsteward_pgsql8_connection.php 2266 2012-01-09 18:53:12Z nkiraly $
 */

class dbsteward_pgsql8_connection {

  protected $management_connection_string = "host=db-white.dev port=5432 dbname=postgres user=deployment password=password1";
  protected $connection_string = "host=db-white.dev port=5432 dbname=dbsteward_phpunit user=deployment password=password1";
  protected $db_name = "dbsteward_phpunit";
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
    $this->conn = pg_connect($this->management_connection_string);
    @pg_query($this->conn, "DROP DATABASE " . $this->db_name);
    pg_query($this->conn, "CREATE DATABASE " . $this->db_name);
    $this->conn = pg_connect($this->connection_string);
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
