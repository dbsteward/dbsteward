<?php
/**
 * Diffs create language definitions
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class pgsql8_diff_languages {

  /**
   * Creates diff of languages between database definitions.
   *
   * @param $ofs          output file segmenter
   * @param $old_database original database
   * @param $new_database new database
   */
  public static function diff_languages($ofs) {
    self::drop_languages($ofs);
    self::create_languages($ofs);

    // no alter for languages
    foreach(dbx::get_languages(dbsteward::$new_database) as $new_language) {
      if ( dbsteward::$old_database == null || dbx::get_language(dbsteward::$old_database, $new_language['name']) == null ) {
        continue;
      }

      $old_language = dbx::get_language(dbsteward::$old_database, $new_language['name']);
      if( strcmp(pgsql8_language::get_creation_sql($old_language, false), pgsql8_language::get_creation_sql($new_language, false)) != 0){
        $ofs->write("\n");
        $ofs->write(pgsql8_language::get_creation_sql($old_language) . "\n");
        $ofs->write("\n");
        $ofs->write(pgsql8_language::get_creation_sql($new_language) . "\n");
      }

    }
  }

  /**
   * Outputs commands for creation of new types.
   *
   * @param $ofs          output file segmenter
   * @param $old_database original database
   * @param $new_database new database
   */
  private static function create_languages($ofs) {
    foreach(dbx::get_languages(dbsteward::$new_database) as $language) {
      if ( dbsteward::$old_database == null || dbx::get_language(dbsteward::$old_database, $language['name']) == null ) {
        $ofs->write(pgsql8_language::get_creation_sql($language) . "\n");
      }
    }
  }

  /**
   * Outputs commands for dropping languages.
   *
   * @param $ofs          output file segmenter
   * @param $old_database original database
   * @param $new_database new database
   */
  private static function drop_languages($ofs) {
    if (dbsteward::$old_database != null) {
      foreach(dbx::get_languages(dbsteward::$old_database) as $language) {
        if (dbx::get_language(dbsteward::$new_database, $language['name']) == null) {
          $ofs->write(pgsql8_language::get_creation_sql($language) . "\n");
        }
      }
    }
  }

}

?>
