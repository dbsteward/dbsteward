<?php
/**
 * PHP to PostgreSQL connectivity
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_db.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_db {

  protected static $db_conn;

  public static function connect($db_conn_string) {
    self::$db_conn = pg_connect($db_conn_string);
    if (self::$db_conn === FALSE) {
      throw new exception("failed to connect to $db_conn_string");
    }
  }

  public function query($sql) {
    $rs = pg_query(self::$db_conn, $sql);
    if ($rs === FALSE) {
      throw new exception("query error: " . pg_last_error(self::$db_conn) . "\nquery = " . $sql);
    }
    return $rs;
  }

  public function query_str($sql) {
    $rs = self::query($sql);
    if (pg_num_rows($rs) > 1) {
      throw new exception("query returned more than one row: " . $sql);
    }
    $row = pg_fetch_row($rs);
    $s = $row[0];
    return $s;
  }
}

?>
