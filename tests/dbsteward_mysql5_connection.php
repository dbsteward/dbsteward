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
                          $sql, static::get_dbhost(), static::get_dbport(), static::get_dbname(), static::get_dbuser(), static::get_dbpass()));
  }

  /**
   * connect and create mysql5 database for testing
   * @return void
   */
  public function create_db() {
    dbsteward::cmd(sprintf("echo '%s' | mysql -v --host=%s --port=%s --user=%s --database=%s --password=%s",
                          'DROP DATABASE IF EXISTS '.static::get_dbname().'; CREATE DATABASE '.static::get_dbname().';',
                          static::get_dbhost(), static::get_dbport(), static::get_dbuser_management(), static::get_dbname_management(), static::get_dbpass_management()));
  }

  protected function pipe_file_to_client($file_name) {
    dbsteward::cmd(sprintf("mysql -v --host=%s --port=%s --user=%s --database=%s --password=%s < '%s'",
                           static::get_dbhost(), static::get_dbport(), static::get_dbuser(), static::get_dbname(), static::get_dbpass(), $file_name));
  }
}

?>
