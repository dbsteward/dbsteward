<?php
/**
 * DBSteward unit test connection management base
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class dbsteward_sql99_connection {
  
  protected $sql_format = 'sql99';
  
  public function get_dbhost() {
    return constant(strtoupper($this->sql_format) . '_DBHOST');
  }
  public function get_dbport() {
    return constant(strtoupper($this->sql_format) . '_DBPORT');
  }
  public function get_dbname() {
    return constant(strtoupper($this->sql_format) . '_DBNAME');
  }
  public function get_dbuser() {
    return constant(strtoupper($this->sql_format) . '_DBUSER');
  }
  public function get_dbpass() {
    return constant(strtoupper($this->sql_format) . '_DBPASS');
  }
  public function get_dbname_management() {
    return constant(strtoupper($this->sql_format) . '_DBNAME_MANAGEMENT');
  }
  public function get_dbuser_management() {
    return constant(strtoupper($this->sql_format) . '_DBUSER_MANAGEMENT');
  }
  public function get_dbpass_management() {
    return constant(strtoupper($this->sql_format) . '_DBPASS_MANAGEMENT');
  }
  
  protected $management_conn = null;
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

}

?>
