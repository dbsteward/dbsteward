<?php
/**
 * Manipulate table and column constraints
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class mysql5_constraint extends sql99_constraint {

  public static function get_primary_key_name($node_table) {
    return "PRIMARY";
  }

  /**
   * create SQL To create the constraint passed in the $constraint array
   *
   * @return string
   */
  public static function get_constraint_sql($constraint) {
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
        dbsteward::console_line(1, "Ignoring constraint '{$constraint['name']}' on table '{$constraint['table_name']}' because MySQL doesn't support the CHECK constraint");
        return "-- Ignoring constraint '{$constraint['name']}' on table '{$constraint['table_name']}' because MySQL doesn't support the CHECK constraint";
        break;
      case 'PRIMARY KEY':
        return "ALTER TABLE " . mysql5::get_quoted_table_name($constraint['table_name']) . " ADD PRIMARY KEY " . $constraint['definition'] . ';';
        break;
      case 'FOREIGN KEY':
        // MySQL considers foreign keys to be a constraint constraining an index
        // naming the FK index and not the constraint (... ADD FOREIGN KEY name ...) results in the named index and an implicitly named constraint being created
        // naming the constraint and not the index (... ADD CONSTRAINT name FOREIGN KEY ...) results in both the index and constraint having the same name

        $constraint_name = mysql5::get_quoted_object_name($constraint['name']);
        $index_name = !empty($constraint['foreignIndexName']) ? mysql5::get_quoted_object_name($constraint['foreignIndexName']) : $constraint_name;

        $sql = "ALTER TABLE " . mysql5::get_quoted_table_name($constraint['table_name']) . " ADD CONSTRAINT ";
        $sql.= "$constraint_name FOREIGN KEY $index_name {$constraint['definition']}";

        // FOREIGN KEY ON DELETE / ON UPDATE handling
        if ( strcasecmp($constraint['type'], 'FOREIGN KEY') == 0 && !empty($constraint['foreignOnDelete']) ) {
          $sql .= " ON DELETE " . self::get_reference_option_sql($constraint['foreignOnDelete']);
        }
        if ( strcasecmp($constraint['type'], 'FOREIGN KEY') == 0 && !empty($constraint['foreignOnUpdate']) ) {
          $sql .= " ON UPDATE " . self::get_reference_option_sql($constraint['foreignOnUpdate']);
        }

        return $sql.';';
        break;
      case 'UNIQUE':
        $sql = "ALTER TABLE " . mysql5::get_quoted_table_name($constraint['table_name']) . " ADD UNIQUE INDEX ";
        $sql.= mysql5::get_quoted_object_name($constraint['name']) . " {$constraint['definition']}";
        return $sql.';';
      default:
        // we shouldn't actually ever get here.
        throw new Exception("Unimplemented MySQL constraint {$constraint['type']}");
    }
  }

  public static function get_reference_option_sql($ref_opt) {
    // @TODO: "ON UPDATE|DELETE SET DEFAULT" is not supported by mysql
    return strtoupper(str_replace('_',' ',$ref_opt));
  }

  public static function get_constraint_drop_sql($constraint) {
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
        dbsteward::console_line(1, "Not dropping constraint '{$constraint['name']}' on table '{$constraint['table_name']}' because MySQL doesn't support the CHECK constraint");
        return "-- Not dropping constraint '{$constraint['name']}' on table '{$constraint['table_name']}' because MySQL doesn't support the CHECK constraint";
        break;
      case 'UNIQUE':
        $drop = "INDEX " . mysql5::get_quoted_object_name($constraint['name']);
        break;
      case 'PRIMARY KEY':
        $drop = "PRIMARY KEY";
        break;
      case 'FOREIGN KEY':
        // dropping a foreign key only drops the constraint
        // we need to drop its index as well
        $constraint_name = mysql5::get_quoted_object_name($constraint['name']);
        $index_name = !empty($constraint['foreignIndexName']) ? mysql5::get_quoted_object_name($constraint['foreignIndexName']) : $constraint_name;
        $drop = "FOREIGN KEY $constraint_name, DROP INDEX $index_name";
        break;
      default:
        // we shouldn't actually ever get here.
        throw new Exception("Unimplemented MySQL constraint {$constraint['type']}");
    }

    $sql = "ALTER TABLE " . mysql5::get_fully_qualified_table_name($constraint['schema_name'], $constraint['table_name']) . " DROP $drop;";
    return $sql;
  }
}
?>
