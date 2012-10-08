<?php
/**
 * Manipulate trigger nodes
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class mysql5_trigger extends sql99_trigger {
  public static function get_creation_sql($node_schema, $node_trigger) {
    $events = self::get_events($node_trigger);

    if ( strcasecmp($node_trigger['sqlFormat'], dbsteward::get_sql_format()) ) {
      $note = "Ignoring {$node_trigger['sqlFormat']} trigger '{$node_trigger['name']}'";
      dbsteward::console_line(1, $note);
      return "-- $note\n";
    }

    if ( empty($node_trigger['function']) ) {
      throw new Exception("No trigger body defined for trigger '{$node_trigger['name']}'");
    }

    if ( ! ($when = self::validate_when($node_trigger['when'])) ) {
      throw new Exception("Invalid WHEN clause for trigger '{$node_trigger['name']}': '{$node_trigger['when']}'");
    }

    $notes = "";
    if ( count($events) == 0 ) {
      throw new Exception("No events were given for trigger {$node_trigger['name']}");
    }
    elseif ( count($events) > 1) {
      $notes .= "-- You specified more than one event for trigger {$node_trigger['name']}, but MySQL only supports a single event a time\n";
      $notes .= "--   generating separate triggers for each event\n";
      dbsteward::console_line(1, "You specified more than one event for trigger {$node_trigger['name']}, but MySQL only supports a single event a time");
      dbsteward::console_line(1, "  generating separate triggers for each event");
    }

    if ( !empty($node_trigger['forEach']) && strcasecmp($node_trigger['forEach'], 'row') ) {
      dbsteward::console_line(1, $notes .= "-- You specified a forEach value of {$node_trigger['forEach']} on trigger {$node_trigger['name']} but MySQL only supports ROW - ignoring\n");
    }

    $node_table = dbx::get_table($node_schema, $node_trigger['table']);
    if ( $node_table == null ) {
      throw new exception("Failed to find trigger's table '{$node_trigger['table']}' in schema node '{$node_schema['name']}'");
    }
    $table_name = mysql5::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);

    // always drop triggers before creating them
    $ddl = static::get_drop_sql($node_schema, $node_trigger);
    $single = count($events) == 1;
    foreach ( $events as $event ) {
      if ( ! ($event = self::validate_event($event)) ) {
        throw new Exception("Invalid event on trigger '{$node_trigger['name']}': '{$event}'");
      }

      $trigger_name = mysql5::get_quoted_object_name($node_trigger['name'] . ($single? '' : "_$event"));
      $trigger_fn = trim($node_trigger['function']);
      if ( substr($trigger_fn, -1) != ';' ) {
        $trigger_fn .= ';';
      }
      $ddl .= <<<SQL
CREATE TRIGGER $trigger_name $when $event ON $table_name
FOR EACH ROW $trigger_fn

SQL;
    }

    return $notes.$ddl;
  }

  public static function get_drop_sql($node_schema, $node_trigger) {
    if ( strcasecmp($node_trigger['sqlFormat'], dbsteward::get_sql_format()) ) {
      $note = "Ignoring {$node_trigger['sqlFormat']} trigger '{$node_trigger['name']}'";
      dbsteward::console_line(1, $note);
      return "-- $note\n";
    }

    $events = self::get_events($node_trigger);
    if ( count($events) == 1 ) {
      return "DROP TRIGGER IF EXISTS " . mysql5::get_quoted_object_name($node_trigger['name']) . ";\n";
    }
    else {
      $ddl = "";
      foreach ( $events as $event ) {
        if ( $event = self::validate_event($event) ) {
          $ddl .= "DROP TRIGGER IF EXISTS " . mysql5::get_quoted_object_name($node_trigger['name'] . "_$event") . ";\n";
        }
      }
      return $ddl;
    }
  }

  public static function validate_when($when) {
    switch ( $upper = strtoupper($when) ) {
      case 'BEFORE':
      case 'AFTER':
        return $upper;
      default:
        return FALSE;
    }
  }

  public static function validate_event($event) {
    switch ( $upper = strtoupper($event) ) {
      case 'INSERT':
      case 'UPDATE':
      case 'DELETE':
        return $upper;
      default:
        return FALSE;
    }
  }
}

?>
