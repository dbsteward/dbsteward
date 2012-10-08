<?php
/**
 * DBSteward unit test mssql server connection management
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

// @TODO: use the command-line client like pgsql8 and mysql5
class dbsteward_mssql10_connection extends dbsteward_sql99_connection {

  protected static $sql_format = 'mssql10';

  protected $conn = null;

  public function run_file($file_names) {
    if ( !is_array($file_names) ) {
      $file_names = array($file_names);
    }
    foreach($file_names AS $file_name) {
      echo "Running $file_name..\n";
      $fp = fopen($file_name, "r");
      $line_number = 0;
      if ($fp) {
        while ($line = $this->get_line($fp, 4096)) {
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

  // @TODO: use command-line client
  protected function pipe_file_to_client($file_name) { }
    
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
      if (stripos($l, 'CREATE FUNCTION') !== FALSE) {
        $end = 'DBSTEWARD_FUNCTION_DEFINITION_END';
      }
      else if (stripos($l, 'CREATE PROCEDURE') !== FALSE) {
        $end = 'DBSTEWARD_PROCEDURE_DEFINITION_END';
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
    $this->conn = mssql_connect(static::get_dbhost(), static::get_dbuser(), static::get_dbpass());
    @mssql_query('DROP DATABASE ' . static::get_dbname(), $this->conn);
    mssql_query('CREATE DATABASE ' . static::get_dbname(), $this->conn);
    mssql_select_db(static::get_dbname(), $this->conn);
    //@TODO: why won't these run? this is supposed to make the matching application role defined in the XML definition
    $this->query("CREATE USER dbsteward_phpunit_app FROM LOGIN dbsteward;");
    $this->query("EXEC sp_addrolemember 'dbsteward_phpunit_app', 'dbsteward_phpunit_app';");
  }

}

?>
