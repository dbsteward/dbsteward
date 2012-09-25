<?php
/**
 * DBSteward unit test postgresql connection management
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class dbsteward_pgsql8_connection extends dbsteward_sql99_connection {
  
  protected static $sql_format = 'pgsql8';

  public function query($sql, $throw_on_error = TRUE) {
    dbsteward::cmd(sprintf("echo '%s' | psql --host=%s --port=%s --username=%s --dbname=%s --no-password --file -",
                          $sql, static::get_dbhost(), static::get_dbport(), static::get_dbuser(), static::get_dbname()));
  }

  /**
   * connect and create mysql5 database for testing
   * @return void
   */
  public function create_db() {
    dbsteward::cmd(sprintf("echo '%s' | psql --host=%s --port=%s --username=%s --dbname=%s --no-password --file -",
                          'DROP DATABASE IF EXISTS '.static::get_dbname().'; CREATE DATABASE '.static::get_dbname().';',
                          static::get_dbhost(), static::get_dbport(), static::get_dbuser_management(), static::get_dbname_management()));
  }

  protected function pipe_file_to_client($file_name) {
    dbsteward::cmd(sprintf("psql --host=%s --port=%s --username=%s --dbname=%s --no-password --file '%s'",
                           static::get_dbhost(), static::get_dbport(), static::get_dbuser(), static::get_dbname(), $file_name));
  }

}

?>
