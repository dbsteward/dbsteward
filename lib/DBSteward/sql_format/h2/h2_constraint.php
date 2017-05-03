<?php

class h2_constraint extends sql99_constraint {

  public static function get_primary_key_name($node_table) {
    return "PRIMARY";
  }

  public static function get_constraint_sql($constraint, $with_alter_table=FALSE) {
    if ( ! is_array($constraint) ) {
      throw new exception("constraint is not an array?");
    }

    if ( strlen($constraint['table_name']) == 0 ) {
      var_dump(array_keys($constraint));
      throw new exception("table_name is blank");
    }

    switch ( strtoupper($constraint['type']) ) {
      case 'CHECK':
        // @TODO: Implement compatibility
        dbsteward::warning("Ignoring constraint '{$constraint['name']}' on table '{$constraint['table_name']}' because MySQL doesn't support the CHECK constraint");
        return "-- Ignoring constraint '{$constraint['name']}' on table '{$constraint['table_name']}' because MySQL doesn't support the CHECK constraint";
        break;
      case 'PRIMARY KEY':
        $sql = '';
        if ($with_alter_table) {
          $sql .= "ALTER TABLE " . h2::get_fully_qualified_table_name($constraint['schema_name'], $constraint['table_name']) . " ";
        }
        $sql .= "ADD PRIMARY KEY " . $constraint['definition'];
        if ($with_alter_table) {
          $sql .= ";";
        }
        return $sql;
        break;
      case 'FOREIGN KEY':
        // MySQL considers foreign keys to be a constraint constraining an index
        // naming the FK index and not the constraint (... ADD FOREIGN KEY name ...) results in the named index and an implicitly named constraint being created
        // naming the constraint and not the index (... ADD CONSTRAINT name FOREIGN KEY ...) results in both the index and constraint having the same name

        //$constraint_name = h2::get_quoted_object_name($constraint['name']);
        //$index_name = !empty($constraint['foreignIndexName']) ? h2::get_quoted_object_name($constraint['foreignIndexName']) : $constraint_name;

        $sql = '';
        if ($with_alter_table) {
          $sql .= "ALTER TABLE " . h2::get_fully_qualified_table_name($constraint['schema_name'], $constraint['table_name']) . " ";
        }
        $sql .= "ADD FOREIGN KEY {$constraint['definition']}";

        // FOREIGN KEY ON DELETE / ON UPDATE handling
        if ( strcasecmp($constraint['type'], 'FOREIGN KEY') == 0 && !empty($constraint['foreignOnDelete']) ) {
          $sql .= " ON DELETE " . self::get_reference_option_sql($constraint['foreignOnDelete']);
        }
        if ( strcasecmp($constraint['type'], 'FOREIGN KEY') == 0 && !empty($constraint['foreignOnUpdate']) ) {
          $sql .= " ON UPDATE " . self::get_reference_option_sql($constraint['foreignOnUpdate']);
        }

        if ($with_alter_table) {
          $sql .= ";";
        }
        return $sql;
        break;
      case 'UNIQUE':
        $sql = '';
        if ($with_alter_table) {
          $sql .= "ALTER TABLE " . h2::get_fully_qualified_table_name($constraint['schema_name'], $constraint['table_name']) . " ";
        }
        $sql .= "ADD UNIQUE INDEX ";
        $sql .= h2::get_quoted_object_name($constraint['name']) . " {$constraint['definition']}";
        if ($with_alter_table) {
          $sql .= ";";
        }
        return $sql;
      default:
        // we shouldn't actually ever get here.
        throw new Exception("Unimplemented MySQL constraint {$constraint['type']}");
    }
  }

  public static function get_reference_option_sql($ref_opt) {
    // @TODO: "SET DEFAULT" is not supported by mysql
    return strtoupper(str_replace('_',' ',$ref_opt));
  }

  public static function get_constraint_drop_sql($constraint, $with_alter_table = TRUE) {
    if ( ! is_array($constraint) ) {
      throw new exception("constraint is not an array?");
    }

    if ( strlen($constraint['table_name']) == 0 ) {
      var_dump(array_keys($constraint));
      throw new exception("table_name is blank");
    }

    // because MySQL refuses to have consistent syntax
    switch ( strtoupper($constraint['type']) ) {
      case 'CHECK':
        // @TODO: Implement compatibility
        dbsteward::warning("Not dropping constraint '{$constraint['name']}' on table '{$constraint['table_name']}' because MySQL doesn't support the CHECK constraint");
        return "-- Not dropping constraint '{$constraint['name']}' on table '{$constraint['table_name']}' because MySQL doesn't support the CHECK constraint";
        break;
      case 'UNIQUE':
        $drop = "INDEX " . h2::get_quoted_object_name($constraint['name']);
        break;
      case 'PRIMARY KEY':
        $drop = "PRIMARY KEY";
        break;
      case 'FOREIGN KEY':
        $drop = "FOREIGN KEY " . h2::get_quoted_object_name($constraint['name']);
        break;
      case 'KEY':
        $drop = "KEY " . h2::get_quoted_object_name($constraint['name']);
        break;
      default:
        // we shouldn't actually ever get here.
        throw new Exception("Unimplemented MySQL constraint {$constraint['type']}");
    }

    $sql = '';
    if ($with_alter_table) {
      $sql .= "ALTER TABLE " . h2::get_fully_qualified_table_name($constraint['schema_name'], $constraint['table_name']) . " ";
    }
    $sql .= "DROP $drop";
    if ($with_alter_table) {
      $sql .= ";";
    }
    return $sql;
  }
}
