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

  public function query($sql, $throw_on_error = TRUE) {
    dbsteward::cmd(sprintf("echo %s | PGPASSWORD='%s' psql --host=%s --port=%s --username=%s --dbname=%s --file -",
                          escapeshellarg($sql), $this->dbpass_mgmt, $this->dbhost, $this->dbport, $this->dbuser, $this->dbname));
  }

  /**
   * connect and create mysql5 database for testing
   * @return void
   */
  public function create_db() {
    dbsteward::cmd(sprintf("echo %s | PGPASSWORD='%s' psql --host=%s --port=%s --username=%s --dbname=%s --file -",
                          escapeshellarg('DROP DATABASE IF EXISTS '.$this->dbname.'; CREATE DATABASE '.$this->dbname.';'),
                          $this->dbpass_mgmt, $this->dbhost, $this->dbport, $this->dbuser_mgmt, $this->dbname_mgmt));
  }

  protected function pipe_file_to_client($file_name) {
    dbsteward::cmd(sprintf("PGPASSWORD='%s' psql --host=%s --port=%s --username=%s --dbname=%s -v ON_ERROR_STOP=1 --file '%s' 2>&1",
                           $this->dbpass_mgmt, $this->dbhost, $this->dbport, $this->dbuser, $this->dbname, $file_name));
  }

}

?>
