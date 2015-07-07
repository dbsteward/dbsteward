<?php
/**
 * Manipulate trigger nodes
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_trigger extends pgsql8_trigger {

  /**
   * Creates and returns SQL for creation of trigger.
   *
   * @return created SQL
   */
  public static function get_creation_sql($node_schema, $node_trigger) {
    $event_chunks = preg_split("/[\,\s]+/", $node_trigger['event'], -1, PREG_SPLIT_NO_EMPTY);
    $node_table = dbx::get_table($node_schema, $node_trigger['table']);
    if ($node_table == NULL) {
      throw new exception("Failed to find trigger table " . $node_trigger['table'] . " in schema node " . $node_schema['name']);
    }
    $table_name = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_table['name']);

    $trigger_name = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_schema_name($node_trigger['name']);

    // check for when's allowed by MSSQL -- see http://msdn.microsoft.com/en-us/library/ms189799.aspx
    // { FOR | AFTER | INSTEAD OF }
    if (strcasecmp($node_trigger['when'], 'FOR') == 0) {
    }
    else if (strcasecmp($node_trigger['when'], 'AFTER') == 0) {
    }
    else {
      throw new exception("Unknown trigger when '" . $node_trigger['when'] . "' encountered on " . $trigger_name);
    }

    //AS { sql_statement  [ ; ] [ ,...n ] | EXTERNAL NAME <method specifier [ ; ] > }
    $function_definition = ' AS ' . $node_trigger['function'];
    if (isset($node_trigger['type'])) {
      if (strcasecmp($node_trigger['type'], 'EXTERNAL') == 0) {
        $function_definition = 'AS EXTERNAL NAME ' . $node_trigger['function'];
      }
      else {
        throw new exception("unknown trigger type encountered: " . $node_trigger['type']);
      }
    }

    $ddl = "CREATE TRIGGER " . $trigger_name . "
    ON " . $table_name . "
    " . $node_trigger['when'] . "
    " . implode(', ', $event_chunks) . "\n";

    if (isset($node_trigger['withAppend'])) {
      $ddl .= "\tWITH APPEND\n";
    }

    $ddl .= "\t" . $function_definition . ";\n";

    return $ddl;
  }

  /**
   * Creates and returns SQL for dropping the trigger.
   *
   * @return created SQL
   */
  public static function get_drop_sql($node_schema, $node_trigger) {
    $node_table = dbx::get_table($node_schema, $node_trigger['table']);
    if ($node_table == NULL) {
      throw new exception("Failed to find trigger table " . $node_trigger['table'] . " in schema node " . $node_schema['name']);
    }
    $table_name = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_table_name($node_table['name']);
    $ddl = "DROP TRIGGER " . pgsql8::get_quoted_object_name($node_trigger['name']) . " ON " . $table_name . ";\n";
    return $ddl;
  }
}

?>
