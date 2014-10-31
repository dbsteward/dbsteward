<?php
/**
 * View comparison management
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_diff_views {

  /**
   * Drops views in dependency order
   * @param  output_file_segmenter $ofs        Output file segmenter to write to
   * @param  SimpleXMLElement      $db_doc_old Old database document
   * @param  SimpleXMLElement      $db_doc_new New database document
   */
  public static function drop_views_ordered($ofs, $db_doc_old, $db_doc_new) {
    static::with_views_in_order($db_doc_old, function ($old_schema, $old_view) use ($db_doc_new, $ofs) {
      $new_schema = dbx::get_schema($db_doc_new, $old_schema['name']);
      $new_view = dbx::get_view($new_schema, $old_view['name']);
      if ($new_view == null || format_diff_views::is_view_modified($old_view, $new_view)) {
        $ofs->write(format_view::get_drop_sql($old_schema, $old_view) . "\n");
      }
    });
  }

  /**
   * Creates views in dependency order
   * @param  output_file_segmenter $ofs        Output file segmenter to write to
   * @param  SimpleXMLElement      $db_doc_old Old database document
   * @param  SimpleXMLElement      $db_doc_new New database document
   */
  public static function create_views_ordered($ofs, $db_doc_old, $db_doc_new) {
    static::with_views_in_order($db_doc_new, function ($new_schema, $new_view) use ($db_doc_new, $db_doc_old, $ofs) {
      $old_schema = dbx::get_schema($db_doc_old, $new_schema['name']);
      $old_view = dbx::get_view($old_schema, $new_view['name']);
      if ($old_view == null || format_diff_views::is_view_modified($old_view, $new_view)) {
        $ofs->write(format_view::get_creation_sql($db_doc_new, $new_schema, $new_view) . "\n");
      }
    });
  }

  public static function with_views_in_order($db_doc, $callback) {
    if ($db_doc != null) {
      $visited = array();

      $dfs_from = function ($schema, $view) use ($callback, &$dfs_from, $db_doc, &$visited) {
        $key = $schema['name'] . '.' . $view['name'];
        // echo "visiting $key\n";
        if (array_key_exists($key, $visited)) {
            // echo "  [visited]\n";
          return;
        }
        // echo "  remembering $key\n";
        $visited[$key] = true;

        $deps = format_view::get_dependencies($schema, $view);
        foreach ($deps as $dep) {
          list($dep_schema_name, $dep_view_name) = $dep;
          // echo "  depends on $dep_schema_name.$dep_view_name\n";
          $dep_schema = dbx::get_schema($db_doc, $dep_schema_name);
          $dep_view = dbx::get_view($dep_schema, $dep_view_name);

          $dfs_from($dep_schema, $dep_view);
        }
        call_user_func($callback, $schema, $view);
      };

      foreach (dbx::get_schemas($db_doc) as $root_schema) {
        $root_schema_name = (string)$root_schema['name'];

        foreach (dbx::get_views($root_schema) as $root_view) {
          $root_view_name = (string)$root_view['name'];
          $root_key = $root_schema_name.'.'.$root_view_name;
          // echo "starting at $root_key\n";

          if (array_key_exists($root_key, $visited)) {
            // echo "  [visited]\n";
            continue;
          }

          $dfs_from($root_schema, $root_view);
        }
      }
    }
  }

  /**
   * is old_view different than new_view?
   *
   * @param object $old_view
   * @param object $new_view
   *
   * @return boolean
   */
  public static function is_view_modified($old_view, $new_view) {
    if ( dbsteward::$always_recreate_views ) {
      return TRUE;
    }
    return strcasecmp(format_view::get_view_query($old_view), format_view::get_view_query($new_view)) != 0
        || strcasecmp($old_view['owner'], $new_view['owner']) != 0;
  }
}
