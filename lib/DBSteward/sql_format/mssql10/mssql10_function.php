<?php
/**
 * Manipulate function definition nodes
 *
 * @package DBSteward
 * @subpackage mssql10
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class mssql10_function extends pgsql8_function {

  /**
   * Returns SQL to create the function
   *
   * @return string
   */
  public function get_creation_sql($node_schema, $node_function) {
    // http://msdn.microsoft.com/en-us/library/ms186755.aspx
    $sql = 'CREATE FUNCTION ' . mssql10_function::get_declaration($node_schema, $node_function) . "\n" . 'RETURNS ' . $node_function['returns'] . "\n" . "  AS \n" . mssql10_function::get_definition($node_function) . "\n" . "; -- DBSTEWARD_FUNCTION_DEFINITION_END\n";

    // quick sanity check: GRANT EXECUTE on FUNCTION that RETURNS table
    if (strcasecmp($node_function['returns'], 'table') == 0) {
      foreach ($node_function->grant AS $grant) {
        if (strcasecmp($grant['operation'], 'EXECUTE') == 0) {
          throw new exception("function " . $node_function['name'] . " - MSSQL does not allow execute permission on functions that return the table type");
        }
      }
    }

    // MSSQL procedure mode?
    if (isset($node_function['procedure'])) {
      if (strcasecmp($node_function['returns'], 'int') != 0) {
        throw new exception("function " . $node_function['name'] . " error -- mssql procedure functions always return int, update the definition to say so");
      }
      $sql = 'CREATE PROCEDURE ' . mssql10_function::get_declaration($node_schema, $node_function) . "\n" . "  AS \n" . mssql10_function::get_definition($node_function) . "\n" . "; -- DBSTEWARD_PROCEDURE_DEFINITION_END\n";
    }

    // @IMPLEMENT: $node_function['language'] specifier ?
    // @IMPLEMENT: $node_function['cachePolicy'] specifier ?
    // @IMPLEMENT: $node_function['securityDefiner'] specifier ?
    // @IMPLEMENT: $node_function['owner'] specifier ?
    // @IMPLEMENT: $node_function['description'] specifier ?
    foreach (dbx::get_permissions($node_function) AS $permission) {
      $grant_sql = mssql10_permission::get_sql(dbsteward::$new_database, $node_schema, $node_function, $permission);
      $sql .= $grant_sql . "\n";
    }

    return $sql;
  }

  /**
   * Creates and returns SQL for dropping the function.
   *
   * @return created SQL
   */
  public function get_drop_sql($node_schema, $node_function) {
    $declaration = self::get_declaration($node_schema, $node_function);
    $declaration = str_ireplace("character varying", "varchar", $declaration);
    $declaration = str_ireplace("varying", "varchar", $declaration);

    $ddl = "DROP FUNCTION " . mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_function_name($node_function['name']) . ';';

    // MSSQL procedure mode?
    if (isset($node_function['procedure'])) {
      $ddl = "DROP PROCEDURE " . mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_function_name($node_function['name']) . ';';
    }

    return $ddl;
  }

  /**
   * Creates declaration string for the function. The string consists
   * of function name, '(', list of argument types separated by ',' and ')'.
   *
   * @param $node_function
   */
  public static function get_declaration($node_schema, $node_function) {
    $r = mssql10::get_quoted_schema_name($node_schema['name']) . '.' . mssql10::get_quoted_function_name($node_function['name']) . '(';
    $parameters = dbx::get_function_parameters($node_function);
    foreach ($parameters AS $parameter) {
      $arg = '';

      if (strlen($parameter['name']) > 0) {
        $arg .= '@' . $parameter['name'] . ' ';
      }

      $arg .= $parameter['type'];

      if (isset($parameter['direction'])) {
        $arg .= ' ' . $parameter['direction'];
      }

      $r .= $arg . ', ';
    }
    if (count($parameters) > 0) {
      $r = substr($r, 0, -2);
    }
    $r .= ')';
    return $r;
  }
}

?>
