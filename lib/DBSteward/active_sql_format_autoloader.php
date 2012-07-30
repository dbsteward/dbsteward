<?php
/**
 * Autoloads and aliases classes out of the sqlformat directory
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

// use the explicit format loader by default
spl_autoload_register('active_sql_format_autoloader::loadFormat');
class active_sql_format_autoloader {
  private static $fallback = 'sql99';
  private static $format;
  private static $prefix = 'format';
  private static $loaded = FALSE;

  public static function init($format) {
    self::$format = $format;
    if ( ! self::$loaded ) {
      spl_autoload_register('active_sql_format_autoloader::loadActive');
      self::$loaded = TRUE;
    }
  }

  public static function loadActive($class) {
    // $class will be like active_sql_format_diff_tables
    // look for the class in self::$format/{self::$format}_diff_tables.php
    // then alias active_sql_format_diff_tables to that class

    if ( stripos($class, self::$prefix) === 0 ) {
      $actual = str_replace(self::$prefix, self::$format, $class);
      $file = SQLFORMAT_DIR . '/' . self::$format . "/$actual.php";

      if ( ! file_exists($file) ) {
        $actual = str_replace(self::$prefix, self::$fallback, $class);
        $file = SQLFORMAT_DIR . '/' . self::$fallback . "/$actual.php";

        if ( ! file_exists($file) ) {
          throw new Exception("Attempted to magic-load class $class, but it was not defined for format ". self::$format . " or fallback " .self::$fallback);
        }
      }

      require_once $file;
      if ( ! class_alias($actual, $class) ) {
        throw new Exception("Could not alias class $actual as $class");
      }
    }
  }
  public static function loadFormat($class) {
    // maybe we're looking at a raw format?
    list($format) = explode('_', $class, 2);
    if ( strtolower($format) == 'slony1' ) {
      $format = 'pgsql8';
    }
    $file = SQLFORMAT_DIR . "/$format/$class.php";
    if ( file_exists($file) ) {
      require_once $file;
    }
  }
}