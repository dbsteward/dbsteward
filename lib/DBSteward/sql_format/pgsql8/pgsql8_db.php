<?php
/**
 * PHP to PostgreSQL connectivity
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_db {

  protected static $db_conn;

  public static function connect($db_conn_string) {
    self::$db_conn = pg_connect($db_conn_string);
    if (self::$db_conn === FALSE) {
      throw new exception("failed to connect to $db_conn_string");
    }
  }

  public static function query($sql) {
    $rs = pg_query(self::$db_conn, $sql);
    if ($rs === FALSE) {
      throw new exception("query error: " . pg_last_error(self::$db_conn) . "\nquery = " . $sql);
    }
    return $rs;
  }

  public static function query_str($sql) {
    $rs = self::query($sql);
    if (pg_num_rows($rs) > 1) {
      throw new exception("query returned more than one row: " . $sql);
    }
    $row = pg_fetch_row($rs);
    $s = $row[0];
    return $s;
  }

  public static function disconnect() {
    self::$db_conn = pg_close(self::$db_conn);
  }  

}

?>
