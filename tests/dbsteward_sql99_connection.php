<?php
/**
 * DBSteward unit test connection management base
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

abstract class dbsteward_sql99_connection {
  
  protected static $sql_format = 'sql99';

  protected $dbhost;
  protected $dbport;
  protected $dbname;
  protected $dbuser;
  protected $dbpass;
  protected $dbname_mgmt;
  protected $dbuser_mgmt;
  protected $dbpass_mgmt;

  public function __construct($config) {
    $this->dbhost = $config['dbhost'];
    $this->dbport = $config['dbport'];
    $this->dbname = $config['dbname'];
    $this->dbuser = $config['dbuser'];
    $this->dbpass = $config['dbpass'];

    $this->dbname_mgmt = array_key_exists('dbname_mgmt', $config) ? $config['dbname_mgmt'] : $this->dbname;
    $this->dbuser_mgmt = array_key_exists('dbuser_mgmt', $config) ? $config['dbuser_mgmt'] : $this->dbuser;
    $this->dbpass_mgmt = array_key_exists('dbpass_mgmt', $config) ? $config['dbpass_mgmt'] : $this->dbpass;
  }

  public function get_dbhost() {
    return $this->dbhost;
  }
  public function get_dbport() {
    return $this->dbport;
  }
  public function get_dbname() {
    return $this->dbname;
  }
  public function get_dbuser() {
    return $this->dbuser;
  }
  public function get_dbpass() {
    return $this->dbpass;
  }
  public function get_dbname_management() {
    return $this->dbname_mgmt;
  }
  public function get_dbuser_management() {
    return $this->dbuser_mgmt;
  }
  public function get_dbpass_management() {
    return $this->dbpass_mgmt;
  }

  public function run_file($file_names) {
    foreach((array)$file_names AS $file_name) {
      echo "Running $file_name..\n";
      if (file_exists($file_name)) {
        try {
          $this->pipe_file_to_client($file_name);
        }
        catch (exception $ex) {
          // dbsteward::console_line(1, "Error with file $file_name:");
          // echo file_get_contents($file_name);
          throw $ex;
        }
      }
      else {
        throw new exception("failed to open " . $file_name);
      }
    }
  }

  protected abstract function create_db();

  protected abstract function pipe_file_to_client($file_name);
  protected abstract function query($sql, $throw_on_error = TRUE);

}

?>
