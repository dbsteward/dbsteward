<?php
/**
 * DBSteward unit test mssql server connection management
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class dbsteward_mssql10_connection extends dbsteward_sql99_connection {
  
  function __construct() {
    $this->sql_format = 'mssql10';
  }

  /**
   * used by test tearDowns
   * make sure connection is closed to DB can be dropped
   * when running multiple tests
   */
  public function close_connection() {
    // do is_resource() instead of error suppression
    // because if $this->conn is valid and mssql_close() fails, it should make the test fail
    if ( is_resource($this->conn) ) {
      mssql_close($this->conn);
    }
  }

  public function query($sql, $throw_on_error = TRUE) {
    $rs = @mssql_query($sql, $this->conn);
    if ( $throw_on_error && !is_resource($rs) ) {
      throw new exception("mssql_query() failed:\n" . $sql . "\n\nError message: " . mssql_get_last_message());
    }
    return $rs;
  }

  /**
   * connect and create mssql database for testing
   * @return void
   */
  public function create_db() {
    $this->conn = mssql_connect($this->get_dbhost(), $this->get_dbuser(), $this->get_dbpass());
    @mssql_query('DROP DATABASE ' . $this->get_dbname(), $this->conn);
    mssql_query('CREATE DATABASE ' . $this->get_dbname(), $this->conn);
    mssql_select_db($this->get_dbname(), $this->conn);
    //@TODO: why won't these run? this is supposed to make the matching application role defined in the XML definition
    $this->query("CREATE USER dbsteward_phpunit_app FROM LOGIN dbsteward;");
    $this->query("EXEC sp_addrolemember 'dbsteward_phpunit_app', 'dbsteward_phpunit_app';");
  }

}

?>
