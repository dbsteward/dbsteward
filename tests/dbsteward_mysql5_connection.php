<?php
/**
 * DBSteward unit test mysql5 server connection management
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class dbsteward_mysql5_connection extends dbsteward_sql99_connection {
  
  function __construct() {
    $this->sql_format = 'mysql5';
  }

  /**
   * used by test tearDowns
   * make sure connection is closed to DB can be dropped
   * when running multiple tests
   */
  public function close_connection() {
    // do is_resource() instead of error suppression
    // because if $this->conn is valid and mysqli_close() fails, it should make the test fail
    if ( is_resource($this->conn) ) {
      mysqli_close($this->conn);
    }
  }

  public function query($sql, $throw_on_error = TRUE) {
    $rs = @mysqli_query($this->conn, $sql);
    if ( $throw_on_error && !is_resource($rs) ) {
      throw new exception("mysqli_query() failed:\n" . $sql . "\n\nError message: " . mysqli_error($this->conn));
    }
    return $rs;
  }

  /**
   * connect and create mysql5 database for testing
   * @return void
   */
  public function create_db() {
    $this->conn = mysqli_connect($this->get_dbhost(), $this->get_dbuser(), $this->get_dbpass());
    @mysqli_query($this->conn, 'DROP DATABASE ' . $this->get_dbname());
    mysqli_query($this->conn, 'CREATE DATABASE ' . $this->get_dbname());
    mysqli_select_db($this->conn, $this->get_dbname());
  }

}

?>
