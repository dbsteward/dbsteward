<?php
/**
 * DBSteward unit test mysql5 server connection management
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class dbsteward_mysql5_connection extends dbsteward_sql99_connection {

  protected static $sql_format = 'mysql5';

  public function query($sql, $throw_on_error = TRUE) {
    dbsteward::cmd(sprintf("echo '%s' | mysql -v --host=%s --port=%s --user=%s --database=%s --password=%s",
                          $sql, $this->dbhost, $this->dbport, $this->dbname, $this->dbuser, $this->dbpass));
  }

  /**
   * connect and create mysql5 database for testing
   * @return void
   */
  public function create_db() {
    dbsteward::cmd(sprintf("echo '%s' | mysql -v --host=%s --port=%s --user=%s --database=%s --password=%s",
                          'DROP DATABASE IF EXISTS '.$this->dbname.'; CREATE DATABASE '.$this->dbname.';',
                          $this->dbhost, $this->dbport, $this->dbuser_mgmt, $this->dbname_mgmt, $this->dbpass_mgmt));
  }

  protected function pipe_file_to_client($file_name) {
    dbsteward::cmd(sprintf("mysql -v --host=%s --port=%s --user=%s --database=%s --password=%s < '%s'",
                           $this->dbhost, $this->dbport, $this->dbuser, $this->dbname, $this->dbpass, $file_name));
  }
}

?>
