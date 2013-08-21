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
    dbsteward::cmd(sprintf("mysql -v --host=%s --port=%s --user=%s --database=%s --password=%s -e %s",
                          $this->dbhost, $this->dbport, $this->dbuser, $this->dbname, $this->dbpass, escapeshellarg($sql)));
  }

  /**
   * connect and create mysql5 database for testing
   * @return void
   */
  public function create_db() {
    $sql = sprintf('DROP DATABASE IF EXISTS %1$s; CREATE DATABASE %1$s; GRANT ALL ON %1$s.* to %2$s WITH GRANT OPTION;', 
            $this->dbname, $this->dbuser);
    dbsteward::cmd(sprintf("mysql -v --host=%s --port=%s --user=%s --database=%s --password=%s -e %s",
                          $this->dbhost, $this->dbport, $this->dbuser_mgmt, $this->dbname_mgmt, $this->dbpass_mgmt, escapeshellarg($sql)));
  }

  protected function pipe_file_to_client($file_name) {
    dbsteward::cmd(sprintf("mysql -v --host=%s --port=%s --user=%s --database=%s --password=%s < '%s'",
                           $this->dbhost, $this->dbport, $this->dbuser, $this->dbname, $this->dbpass, $file_name));
  }
}

?>
