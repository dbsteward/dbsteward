<?php

class h2_db {

  protected $pdo;
  protected $dbname;

  public static function connect($host, $port, $user, $password) {
    // mysql puts all metadata in the information_schema database, but assigns objects to their respective DBs
    return new h2_db(new PDO("mysql:host=$host;port=$port;dbname=information_schema", $user, $password));
  }

  public function __construct($pdo) {
    $this->pdo = $pdo;

    // Make sure we throw exceptions when bad stuff happens
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
  }

  public function use_database($database) {
    $this->dbname = $database;
  }

  public function get_tables() {
    $table_name = h2_sequence::TABLE_NAME;
    return $this->query("SELECT table_schema, table_name, engine,
                                table_rows, auto_increment, table_comment,
                                create_options
                          FROM tables
                          WHERE table_type = 'base table'
                            AND table_name != '$table_name'
                            AND table_schema = ?", array($this->dbname));
  }

  public function get_table($name) {
    return $this->query("SELECT table_schema, table_name, engine,
                                table_rows, auto_increment, table_comment,
                                create_options
                         FROM tables
                         WHERE table_type = 'base table'
                           AND table_schema = ?
                           AND table_name = ?
                         LIMIT 1", array($this->dbname, $name), 'one');
  }

  public function get_partition_info($table) {
    $parts = $this->query("SELECT partition_method, partition_name,
                                  partition_expression, partition_description
                           FROM partitions
                           WHERE table_schema = ?
                             AND table_name = ?
                           ORDER BY partition_ordinal_position",
                          array($this->dbname, $table->table_name));

    if (count($parts) === 0) return null;

    $method = strtoupper($parts[0]->partition_method);

    switch ($method) {
      case 'HASH':
      case 'LINEAR HASH':
        return (object)array(
          'type' => $method,
          'number' => count($parts),
          'expression' => $parts[0]->partition_expression
        );

      case 'KEY':
      case 'LINEAR KEY':
        return (object)array(
          'type' => $method,
          'number' => count($parts),
          'columns' => str_replace(h2::QUOTE_CHAR, '', $parts[0]->partition_expression)
        );

      case 'LIST':
      case 'RANGE':
      case 'RANGE COLUMNS':
        return (object)array(
          'type' => $method,
          'expression' => $method == 'RANGE COLUMNS' ?
            str_replace(h2::QUOTE_CHAR, '', $parts[0]->partition_expression)
            : $parts[0]->partition_expression,
          'segments' => array_map(function($p) {
            return (object)array(
              'name' => $p->partition_name,
              'value' => $p->partition_description
            );
          }, $parts)
        );

      default:
        dbsteward::error("Unrecognized partition method $method!");
    }
    return null;
  }

  public function get_table_options($table) {
    $create_sql = $this->query("SHOW CREATE TABLE `{$this->dbname}`.`{$table->table_name}`", array(), 'one')->{"Create Table"};
    
    $opts = $this->parse_create_table_for_options($create_sql);

    // comments handled as a description attribute, ignore here
    unset($opts['comment']);

    return $opts;
  }

  public function parse_create_table_for_options($sql) {
    $sql = preg_replace("/\s{2,}|\n/", ' ', $sql);

    // AFAIK, "SHOW CREATE TABLE x" will always show the ENGINE option first
    $option_sql = substr($sql, strripos($sql, "ENGINE="));

    // it's so big because tablespaces are a special case
    // also note that strings are single quoted, and two in a row is an escaped single quote
    preg_match_all('/(?:(?<name>tablespace) (?<value>\w+(?: storage disk)))|(?:(?<name1>\w+(?: \w+)*)=(?<value1>[\w,]+|\'(?:[^\']|\'\')*\'))/i', $option_sql, $matches);

    // merge the two sets of name=>value, lowercasing the option names
    $opts = array_merge(
      array_combine(
        array_map('strtolower',$matches['name']),
        $matches['value']),
      array_combine(
        array_map('strtolower',$matches['name1']),
        $matches['value1'])
    );

    // there will more than likely be a single ''=>'', get rid of it
    unset($opts['']);

    // now format the options
    foreach ($opts as $key => &$val) {
      // un-escape single quotes - let dbsteward handle this later
      $val = str_replace("''", "'", $val);
    }

    return $opts;
  }

  public function uses_sequences() {
    return $this->query("SELECT COUNT(*) FROM tables WHERE table_schema = ? and table_name = ?", array($this->dbname, h2_sequence::TABLE_NAME), 'scalar') != 0;
  }

  public function get_sequences() {
    if (!$this->uses_sequences()) {
      return array();
    }

    $table_name = h2_sequence::TABLE_NAME;
    $name_col = h2_sequence::SEQ_COL;
    return $this->query("SELECT *
                           FROM `{$this->dbname}`.`$table_name`
                          WHERE `$name_col` NOT LIKE '__public_%_%_serial_seq'");
  }

  public function get_columns($db_table) {
    return $this->query("SELECT table_name, column_name, column_default,
                                is_nullable, data_type, character_maximum_length, numeric_precision,
                                numeric_scale, column_type, column_key, extra, privileges,
                                extra LIKE '%auto_increment%' AS is_auto_increment,
                                extra LIKE '%current_timestamp%' AS is_auto_update, column_comment
                         FROM columns
                         WHERE table_name = ?
                           AND table_schema = ?
                         ORDER BY ordinal_position ASC",array($db_table->table_name, $this->dbname));
  }

  public function is_serial_column($db_table, $db_column) {
    static $seq_stmt, $trig_stmt;
    
    if (!$this->uses_sequences()) {
      // if we're not using sequences, it can't possibly be a serial column
      return false;
    }

    $table_name = h2_sequence::TABLE_NAME;
    $name_col = h2_sequence::SEQ_COL;

    // @TODO: unify with h2_column::get_serial_sequence_name()
    $seq_name = '__public_' . $db_table->table_name . '_' . $db_column->column_name . '_serial_seq';
    $has_seq = $this->query("SELECT COUNT(*) = 1
                               FROM `{$this->dbname}`.`$table_name`
                              WHERE `$name_col` = ?", array($seq_name), 'scalar');

    // @TODO: unify with h2_column::get_serial_trigger_name()
    $trig_name = '__public_' . $db_table->table_name . '_' . $db_column->column_name . '_serial_trigger';
    $has_trig = $this->query("SELECT COUNT(*) = 1
                                FROM triggers
                               WHERE trigger_schema = ?
                                 AND trigger_name = ?", array($this->dbname, $trig_name), 'scalar');

    if ($has_seq && $has_trig) {
      return strcasecmp($db_column->data_type,'bigint')===0 ? 'bigserial' : 'serial';
    }
    return false;
  }

  public function get_indices($db_table) {
    // only show those indexes which are not keys/constraints, unless it's a unique constraint
    $indices = $this->query("SELECT statistics.table_name,
                                          GROUP_CONCAT(DISTINCT statistics.column_name ORDER BY seq_in_index) AS columns,
                                          NOT statistics.non_unique AS 'unique', statistics.index_name,
                                          statistics.nullable, statistics.comment, statistics.index_type
                                   FROM statistics
                                     LEFT OUTER JOIN key_column_usage USING (table_schema, table_name, column_name)
                                     LEFT OUTER JOIN table_constraints USING (table_schema, table_name, constraint_name)
                                   WHERE statistics.table_schema = ?
                                     AND statistics.table_name = ?
                                   GROUP BY index_name", array($this->dbname, $db_table->table_name));
    foreach ($indices as &$idx) {
      // massage the output
      $idx->columns = explode(',',$idx->columns);
    }

    return $indices;
  }

  public function get_constraints($db_table) {
    $constraints = $this->query("SELECT constraint_type, table_constraints.table_name, table_constraints.constraint_name,
                                        GROUP_CONCAT(DISTINCT key_column_usage.column_name ORDER BY ordinal_position) as columns,
                                        key_column_usage.referenced_table_schema, key_column_usage.referenced_table_name, update_rule, delete_rule,
                                        GROUP_CONCAT(DISTINCT referenced_column_name ORDER BY ordinal_position) as referenced_columns
                                 FROM table_constraints
                                  INNER JOIN key_column_usage USING (table_schema, table_name, constraint_name)
                                  LEFT OUTER JOIN referential_constraints
                                                 ON referential_constraints.constraint_schema = table_constraints.table_schema
                                                AND referential_constraints.constraint_name = table_constraints.constraint_name
                                                AND referential_constraints.table_name = table_constraints.table_name
                                 WHERE table_constraints.table_schema = ?
                                   AND table_constraints.table_name = ?
                                   AND (table_constraints.constraint_type != 'UNIQUE')
                                 GROUP BY constraint_name;", array($this->dbname, $db_table->table_name));

    foreach ($constraints as &$constraint) {
      // massage the output
      $constraint->columns = explode(',',$constraint->columns);

      if ($constraint->referenced_columns) {
        $constraint->referenced_columns = explode(',',$constraint->referenced_columns);
      }
    }

    return $constraints;
  }

  public function get_table_grants($db_table, $username) {
    return $this->query("SELECT grantee, GROUP_CONCAT(privilege_type) as operations, is_grantable = 'YES' as is_grantable
                           FROM table_privileges
                           WHERE table_schema = ?
                             AND table_name = ?
                             AND grantee LIKE ?
                           GROUP BY is_grantable", array($this->dbname, $db_table->table_name, "'$username'@%"));
  }

  public function get_global_grants($username) {
    return $this->query("SELECT grantee, GROUP_CONCAT(DISTINCT privilege_type ORDER BY privilege_type ASC) AS operations, COUNT(DISTINCT privilege_type) AS num_ops, is_grantable='YES' as is_grantable FROM (
                           SELECT grantee, privilege_type, is_grantable FROM schema_privileges WHERE grantee LIKE :grantee AND table_schema = :schema
                           UNION
                           SELECT grantee, privilege_type, is_grantable FROM user_privileges 
                             WHERE grantee LIKE :grantee
                               -- some grants can ONLY be applied server-wide; ignore them
                               AND privilege_type NOT IN ('CREATE TABLESPACE', 'CREATE USER', 'FILE', 'PROCESS', 'RELOAD',
                                                          'REPLICATION CLIENT', 'REPLICATION SLAVE', 'SHOW DATABASES', 'SHUTDOWN', 'SUPER')
                         ) AS tbl
                         GROUP BY is_grantable;", array('schema'=>$this->dbname, 'grantee'=>"'$username'@%"));
  }

  public function get_functions() {
    if ($this->uses_sequences()) {
      $not_shim_fns = "AND routine_name NOT IN ('nextval','setval','currval','lastval')";
    }
    else {
      $not_shim_fns = "";
    }
    $sql = "SELECT routine_name, data_type, character_maximum_length, numeric_precision,
                   routine_comment, (routine_type <> 'FUNCTION') AS `procedure`,
                   numeric_scale, routine_definition, is_deterministic, security_type,
                   definer, sql_data_access, dtd_identifier
            FROM routines
            WHERE routine_schema = ? $not_shim_fns";
    $functions = $this->query($sql, array($this->dbname));

    foreach ($functions as $fn) {
      $fn->parameters = $this->query("SELECT parameter_mode, parameter_name, data_type, character_maximum_length,
                                                    numeric_precision, numeric_scale, dtd_identifier, parameter_mode as direction
                                             FROM parameters
                                             WHERE specific_schema = ?
                                               AND specific_name = ?
                                               AND parameter_name IS NOT NULL
                                             ORDER BY ordinal_position ASC", array($this->dbname, $fn->routine_name));
    }

    return $functions;
  }

  public function get_triggers() {
    return $this->query("SELECT trigger_name AS name, event_manipulation AS event, 
                                event_object_table AS 'table', action_statement AS function,
                                action_orientation AS forEach, action_timing AS 'when'
                         FROM triggers
                         WHERE trigger_schema = ?
                           AND trigger_name NOT LIKE '__public_%_%_serial_trigger'", array($this->dbname));
  }

  public function get_views() {
    return $this->query("SELECT table_name AS view_name, view_definition AS view_query,
                                        definer, 'DEFINER' AS security_type
                                 FROM views
                                 WHERE table_schema = ?", array($this->dbname));
  }

  public function parse_enum_values($enum) {
    // test to match enum('word'[, ...])
    if ( preg_match('/enum\((\'.+?\'(?:,\'.+?\')*)\)/i', $enum, $matches) == 1 ) {
      return explode(',', str_replace("'",'',$matches[1]));
    }
    else {
      throw new Exception("Invalid enum declaration: $enum");
    }
  }

  private function query($sql, $params = array(), $type='all') {
    static $stmts = array();
    if (!array_key_exists($sql, $stmts)) {
      $stmts[$sql] = $this->pdo->prepare($sql);
      if (!$stmts[$sql]) {
        throw new Exception("Could not prepare sql:\n$sql");
      }
    }

    $stmts[$sql]->execute($params);

    switch ($type) {
      case 'one': return $stmts[$sql]->fetch(PDO::FETCH_OBJ);
      case 'scalar': return $stmts[$sql]->fetchColumn();
      default: return $stmts[$sql]->fetchAll(PDO::FETCH_OBJ);
    }
  }
}
?>
