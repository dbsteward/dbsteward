<?php
/**
 * slonik script file parser
 *
 * @package DBSteward
 * @subpackage pgsql8
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class slony1_slonik {

  public static function convert($file) {
    $file_name = realpath($file);
    $fp_slonik = fopen($file_name, 'r');
    if (!$fp_slonik) {
      throw new exception("failed to open slonik script " . $file . " for conversion");
    }

    $doc = new SimpleXMLElement('<dbsteward></dbsteward>');
    $line = '';
    while (($c = fgetc($fp_slonik)) !== FALSE) {
      switch ($c) {// catch lines starting with # comments and ignore them
        case '#':
          // only if line hasn't started yet
          if (strlen(trim($line)) == 0) {
            $comment = TRUE;
          }
        break; // convert newlines to spaces

        case "\n":
          $c = ' ';
          // newline encountered, so comment is over
          $comment = FALSE;
        break; // the statement terminated

        case ';':
          // not in comment state?
          if (!$comment) {
            self::parse_line($doc, $line);
            $line = '';
          }
        break; // by default, add character to the line

        default:
          // late-start # comment line
          $trimmed_line = trim($line);
          if ($trimmed_line{0} == '#') {
            $comment = TRUE;
          }
          // as long as it isn't a comment
          else if (!$comment) {
            $line .= $c;
          }
        break;
      }
    }
    fclose($fp_slonik);

    //xml_parser::validate_xml($doc->asXML());
    return xml_parser::format_xml($doc->saveXML());
  }

  public static function parse_line(&$doc, $line) {
    // ignore excess whitespace, and tokenize at the same time
    $tokens = preg_split("/[\s]+/", $line, -1, PREG_SPLIT_NO_EMPTY);

    // set
    if (strcasecmp('set', $tokens[0]) == 0) {
      // set add
      if (strcasecmp('add', $tokens[1]) == 0) {
        $cmd_tokens = $tokens;
        // kill the set add whatever
        unset($cmd_tokens[0], $cmd_tokens[1], $cmd_tokens[2]);
        $params = self::parse_params(implode(' ', $cmd_tokens));

        // set add table
        if (strcasecmp('table', $tokens[2]) == 0) {
          self::add_table($doc, $params);
        }
        // set add sequence
        else if (strcasecmp('sequence', $tokens[2]) == 0) {
          self::add_sequence($doc, $params);
        }
        else {
          throw new exception("unknown line set add token: " . $tokens[2] . ' line: ' . $line);
        }
      }
      else {
        throw new exception("unknown line set token: " . $tokens[1] . ' line: ' . $line);
      }
    }
    else {
      throw new exception("unknown line start token: " . $tokens[0] . ' line: ' . $line);
    }
  }

  public static function parse_params($line) {
    if (substr($line, 0, 1) != '(') {
      throw new exception("slonik params don't start with (: " . $line);
    }
    if (substr($line, -1, 1) != ')') {
      throw new exception("slonik params don't end with ): " . $line);
    }
    // knock off the ( and ) now that we know they are there
    $param_list = explode(',', substr($line, 1, strlen($line) - 2));

    $params = array();
    for ($i = 0; $i < count($param_list); $i++) {
      // split the params at the =
      $chunks = explode('=', trim($param_list[$i]));
      // kill whitespace again
      $name = trim($chunks[0]);
      $value = trim($chunks[1]);
      // lowercase the param name
      $name = strtolower($name);
      // if data value is enclosed in '', kill it
      $value = sql_parser::quoted_value_strip($value);
      $params[$name] = $value;
    }

    return $params;
  }

  public static function add_table(&$doc, $params) {
    if (!isset($params['id']) || !is_numeric($params['id'])) {
      throw new exception("illegal id param: " . $params['id']);
    }
    if (strlen($params['fully qualified name']) == 0) {
      throw new exception("fully qualified name param missing");
    }

    $slony_id = $params['id'];
    $fqn = $params['fully qualified name'];
    $chunks = explode('.', $fqn);
    $schema = $chunks[0];
    $table = $chunks[1];

    $nodes = $doc->xpath("schema[@name='" . $schema . "']");
    // add schema node if it doesn't exist yet
    if (count($nodes) == 0) {
      $node_schema = $doc->addChild('schema');
      $node_schema->addAttribute('name', $schema);
    }
    else {
      $node_schema = $nodes[0];
    }

    $nodes = $node_schema->xpath("table[@name='" . $table . "']");
    // add table node if it doesn't exist yet
    if (count($nodes) == 0) {
      $node_table = $node_schema->addChild('table');
      $node_table->addAttribute('name', $table);
    }
    else {
      $node_table = $nodes[0];
    }

    // sanity check to make sure table doesn't already have a slonyId
    if (isset($node_table['slonyId'])) {
      throw new exception("table " . $table . " already has slonyId " . $node_table['slonyId']);
    }

    $node_table->addAttribute('slonyId', $slony_id);
  }

  public static function add_sequence(&$doc, $params) {
    if (!isset($params['id']) || !is_numeric($params['id'])) {
      throw new exception("illegal id param: " . $params['id']);
    }
    if (strlen($params['fully qualified name']) == 0) {
      throw new exception("fully qualified name param missing");
    }

    $slony_id = $params['id'];
    $fqn = $params['fully qualified name'];
    $fqn_chunks = explode('.', $fqn);
    $schema = $fqn_chunks[0];

    $nodes = $doc->xpath("schema[@name='" . $schema . "']");
    // add schema node if it doesn't exist yet
    if (count($nodes) == 0) {
      $node_schema = $doc->addChild('schema');
      $node_schema->addAttribute('name', $schema);
    }
    else {
      $node_schema = $nodes[0];
    }

    // table primary key sequence
    if (strpos($params['comment'], 'primary key') !== FALSE) {
      // assume comment leads with table name
      // discipline.radiologic_tech_specialty_list primary key|sequence etc
      $table = trim(str_ireplace(array('primary key', 'sequence'), '', $params['comment']));
      // strip sequence prefix, if present
      $table = str_ireplace($schema . '.', '', $table);
      // table maximum len is 29
      $table = substr($table, 0, 29);

      // figure out table / column name using the fully qualified name and comment section
      $column = $fqn_chunks[1];
      // if it matches, strip off the leading table name
      if (substr($column, 0, strlen($table)) == $table) {
        $column = substr($column, strlen($table) + 1);
        // +1 because of 0 index plus _
      }
      // strip the _seq
      $column = str_ireplace('_seq', '', $column);

      $nodes = $node_schema->xpath("table[@name='" . $table . "']");
      // add table node if it doesn't exist yet
      if (count($nodes) == 0) {
        $node_table = $node_schema->addChild('table');
        $node_table->addAttribute('name', $table);
      }
      else {
        $node_table = $nodes[0];
      }

      $nodes = $node_table->xpath("column[@name='" . $column . "']");
      // add schema node if it doesn't exist yet
      if (count($nodes) == 0) {
        $node_column = $node_table->addChild('column');
        $node_column->addAttribute('name', $column);
      }
      else {
        $node_column = $nodes[0];
      }

      // sanity check to make sure column doesn't already have a slonyId
      if (isset($node_column['slonyId'])) {
        throw new exception("table " . $table . " column " . $column . " already has slonyId " . $node_column['slonyId']);
      }

      $node_column->addAttribute('slonyId', $slony_id);
    }
    // standalone sequence
    else {
      $sequence = $fqn_chunks[1];
      $nodes = $node_schema->xpath("sequence[@name='" . $sequence . "']");
      // add sequence node if it doesn't exist yet
      if (count($nodes) == 0) {
        $node_sequence = $node_schema->addChild('sequence');
        $node_sequence->addAttribute('name', $sequence);
      }
      else {
        $node_sequence = $nodes[0];
      }

      // sanity check to make sure sequence doesn't already have a slonyId
      if (isset($node_sequence['slonyId'])) {
        throw new exception("sequence " . $sequence . " already has slonyId " . $node_sequence['slonyId']);
      }

      $node_sequence->addAttribute('slonyId', $slony_id);
    }
  }

    const script_drop_table = "SET DROP TABLE (
  ORIGIN = %d,
  ID = %d
);";

    const script_drop_sequence = "SET DROP SEQUENCE (
  ORIGIN = %d,
  ID = %d
);";

    const script_add_table = "SET ADD TABLE (
  SET ID = %d,
  ORIGIN = %d,
  ID = %d,
  FULLY QUALIFIED NAME = '%s',
  COMMENT = '%s'
);";

    const script_add_sequence = "SET ADD SEQUENCE (
  SET ID = %d,
  ORIGIN = %d,
  ID = %d,
  FULLY QUALIFIED NAME = '%s',
  COMMENT = '%s'
);";

    const script_create_set = "CREATE SET (
  ID = %d,
  ORIGIN = %d,
  COMMENT = '%s'
);";

    const script_subscribe_set = "SUBSCRIBE SET (
  ID = %d,
  PROVIDER = %d,
  RECEIVER = %d,
  FORWARD = YES
);";

    const script_node_sync_wait = "SYNC (ID = %d);
WAIT FOR EVENT (
  ORIGIN = %d,
  CONFIRMED = ALL,
  WAIT ON = %d,
  TIMEOUT = 0
);
SLEEP (SECONDS=60);";

    const script_merge_set = "MERGE SET (
  ID = %d,
  ADD ID = %d,
  ORIGIN = %d
);";

}

?>
