<?php
/**
 * DBSteward unit test postgresql connection management
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class dbsteward_pgsql8_connection extends dbsteward_sql99_connection {
  
  // protected static $sql_format = 'pgsql8';

  /**
   * Query the database
   * 
   * @param string $sql
   * @param boolean $throw_on_error
   */
  public function query($sql, $throw_on_error = TRUE) {
    dbsteward::cmd(sprintf("PGPASSWORD='%s' psql --host=%s --port=%s --username=%s --dbname=%s -c %s  2>&1",
                          $this->dbpass, $this->dbhost, $this->dbport, $this->dbuser, $this->dbname, escapeshellarg($sql)), $throw_on_error);
  }
  
  /**
   * Query the database as management user and database
   * 
   * @param string $sql
   * @param boolean $throw_on_error
   */
  public function query_mgmt($sql, $throw_on_error = TRUE) {
    dbsteward::cmd(sprintf("PGPASSWORD='%s' psql --host=%s --port=%s --username=%s --dbname=%s -c %s  2>&1",
                          $this->dbpass_mgmt, $this->dbhost, $this->dbport, $this->dbuser_mgmt, $this->dbname_mgmt, escapeshellarg($sql)), $throw_on_error);
  }

  /**
   * connect and create pgsql8 dbname database for testing
   * NOTICE: will drop database if already exists
   * 
   * @return void
   */
  public function create_db() {
    // disconnect any users connected to dbname
    //$this->query_mgmt(sprintf("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE pid <> pg_backend_pid() AND datname = '%s'", $this->dbname));
    $this->query_mgmt(sprintf("DROP DATABASE IF EXISTS %s", $this->dbname), FALSE);
    $this->query_mgmt(sprintf("CREATE DATABASE %s", $this->dbname));
  }

  protected function pipe_file_to_client($file_name) {
    dbsteward::cmd(sprintf("PGPASSWORD='%s' psql --host=%s --port=%s --username=%s --dbname=%s -v ON_ERROR_STOP=1 --file '%s' 2>&1",
                           $this->dbpass_mgmt, $this->dbhost, $this->dbport, $this->dbuser, $this->dbname, $file_name));
  }

}

?>
