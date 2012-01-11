<?php
/**
 * Diffs create language definitions
 *
 * @copyright 2011 Collaborative Fusion, Inc.
 * @package DBSteward
 * @author Nicholas Kiraly <kiraly.nicholas@gmail.com>
 * @version $Id: pgsql8_diff_languages.php 2261 2012-01-09 08:37:44Z nkiraly $
 */

class pgsql8_diff_languages {

  /**
   * Creates diff of languages between database definitions.
   *
   * @param fp output file pointer
   * @param old_database original database
   * @param new_database new database
   */
  public static function diff_languages($fp) {
    self::drop_languages($fp);
    self::create_languages($fp);

    // no alter for languages
    foreach(dbx::get_languages(dbsteward::$new_database) as $new_language) {
      if ( dbsteward::$old_database == null || dbx::get_language(dbsteward::$old_database, $new_language['name']) == null ) {
        continue;
      }

      $old_language = dbx::get_language(dbsteward::$old_database, $new_language['name']);
      if( strcmp(pgsql8_language::get_creation_sql($old_language, false), pgsql8_language::get_creation_sql($new_language, false)) != 0){
        fwrite($fp, "\n");
        fwrite($fp, pgsql8_language::get_creation_sql($old_language) . "\n");
        fwrite($fp, "\n");
        fwrite($fp, pgsql8_language::get_creation_sql($new_language) . "\n");
      }

    }
  }

  /**
   * Outputs commands for creation of new types.
   *
   * @param fp output file pointer
   * @param old_database original database
   * @param new_database new database
   */
  private static function create_languages($fp) {
    foreach(dbx::get_languages(dbsteward::$new_database) as $language) {
      if ( dbsteward::$old_database == null || dbx::get_language(dbsteward::$old_database, $language['name']) == null ) {
        fwrite($fp, pgsql8_language::get_creation_sql($language) . "\n");
      }
    }
  }

  /**
   * Outputs commands for dropping languages.
   *
   * @param fp output file pointer
   * @param old_database original database
   * @param new_database new database
   */
  private static function drop_languages($fp) {
    if (dbsteward::$old_database != null) {
      foreach(dbx::get_languages(dbsteward::$old_database) as $language) {
        if (dbx::get_language(dbsteward::$new_database, $language['name']) == null) {
          fwrite($fp, pgsql8_language::get_creation_sql($language) . "\n");
        }
      }
    }
  }

}

?>
