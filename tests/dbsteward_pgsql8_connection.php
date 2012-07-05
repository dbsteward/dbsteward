<?php
/**
 * DBSteward unit test postgresql connection management
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class dbsteward_pgsql8_connection extends dbsteward_sql99_connection {
  
  function __construct() {
    $this->sql_format = 'pgsql8';
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
