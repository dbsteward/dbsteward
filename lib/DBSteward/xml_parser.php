<?php
/**
 * DBSteward XML definition file parsing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
 */

class xml_parser {

  private function __construct() {
    // this class is not to be instanced
    // use the static methods only
  }

  /**
   * Composite a list of XML files into one dbsteward definition
   * @NOTICE: only 'base' XML files, those listed in the $files list should contain includeFile entries
   *
   * @param  string    $output_prefix
   * @param  array     $files array list of files to composite
   *
   * @return string    XML files contents, composited
   */
  public static function xml_composite($output_prefix, $files, &$composite_file) {
    $file_list = '';
    $composite = new SimpleXMLElement('<dbsteward></dbsteward>');

    for ($i = 0; $i < count($files); $i++) {
      $file_name = $files[$i];
      $file_list .= $file_name . ' ';
      dbsteward::console_line(1, "Loading XML " . realpath($file_name) . "..");
      $xml_contents = file_get_contents($file_name);
      if ($xml_contents === FALSE) {
        throw new exception("Failed to load XML from disk: " . $file_name);
      }
      $doc = simplexml_load_string($xml_contents);
      if ($doc === FALSE) {
        throw new Exception("failed to simplexml_load_string() contents of " . $file_name);
      }
      $doc = xml_parser::expand_tabrow_data($doc);
      $doc = xml_parser::sql_format_convert($doc);
      $xml_contents = $doc->saveXML();

      // only validate the first composite in the chain before composite has been completed
      if ($i == 0) {
        self::validate_xml($xml_contents);
      }

      dbsteward::console_line(1, "Compositing XML File " . $file_name);
      
      // if doc defines the database element
      // composite it first, to adhere to the DTD
      $doc_clone = NULL;
      if ( isset($doc->database) ) {
        $doc_clone = clone $doc;
        $doc_clone_child_nodes = $doc_clone->children();
        foreach ($doc_clone_child_nodes AS $doc_clone_child_node) {
          $doc_clone_child_names[] = $doc_clone_child_node->getName();
        }
        foreach ($doc_clone_child_names AS $doc_clone_child_name) {
          if ( strcasecmp($doc_clone_child_name, 'inlineAssembly') == 0 ) {
            // need to keep this definition at the top for DTD compliance
            // this is for MSSQL assembly support
          }
          else if ( strcasecmp($doc_clone_child_name, 'database') == 0 ) {
            // include database element and it's children first, to maintain adherence to the DTD
          }
          else {
            unset($doc_clone->{$doc_clone_child_name});
          }
        }
        // these elements do not need mainline overlaid from $doc
        unset($doc->inlineAssembly);
        unset($doc->database);
      }
      
      // if doc_clone is defined, $doc->database was found, add it after inlineAssembly entries
      // and if there were inlineAssembly elements included,
      // composite these inlineAssembly elements in very first
      if ( $doc_clone ) {
        if ( isset($doc_clone->inlineAssembly) ) {
          $doc_clone_ila = clone $doc_clone;
          unset($doc_clone_ila->database);
          self::xml_composite_children($composite, $doc_clone_ila);
        }
        
        // if not defined in the composite yet
        if ( !isset($composite->database) ) {
          //add database node to the composite so it will be in the right natural order position
          $composite->addChild('database');
        }
      }

      // do doc includes first, to allow definer to overwrite included file values at the same level
      while (isset($doc->includeFile)) {
        foreach ($doc->includeFile AS $includeFile) {
          $include_file_name = (string)($includeFile['name']);
          // if include_file_name does not appear to be absolute, make it relative to its parent
          if (substr($include_file_name, 0, 1) != '/') {
            $include_file_name = dirname($file_name) . '/' . $include_file_name;
          }
          dbsteward::console_line(1, "Compositing XML includeFile " . $include_file_name);
          $include_doc = simplexml_load_file($include_file_name);
          if ($include_doc === FALSE) {
            throw new Exception("failed to simplexml_load_file() includeFile " . $include_file_name);
          }
          $include_doc = xml_parser::expand_tabrow_data($include_doc);
          $include_doc = xml_parser::sql_format_convert($include_doc);
          self::xml_composite_children($composite, $include_doc);
        }
        unset($doc->includeFile);
        unset($composite->includeFile);
      }
      
      // if doc_clone is defined, there were database element values to overlay.
      // now that includeFile has been processed, put these values overlaid "last"
      if ( $doc_clone ) {
        self::xml_composite_children($composite->database, $doc_clone->database);
      }

      // includes done, now put $doc values in, to allow definer to overwrite included file values at the same level
      self::xml_composite_children($composite, $doc);

      // revalidate composited xml
      self::validate_xml(self::format_xml($composite->saveXML()));
    }

    $composite_file = $output_prefix . '_composite.xml';
    dbsteward::console_line(1, "XML files " . $file_list . " composited");
    dbsteward::console_line(1, "Saving as " . $composite_file);
    file_put_contents($composite_file, self::format_xml($composite->saveXML()));

    return $composite;
  }

  public static function xml_composite_children(&$base, &$overlay) {
    // overlay elements found in the overlay node
    foreach ($overlay->children() AS $child) {
      // always reset the base relative node to null to prevent loop carry-over
      $node = NULL;

      $tag_name = $child->getName();

      if (strcasecmp('function', $tag_name) == 0) {
        $nodes = $base->xpath($tag_name . "[@name='" . $child['name'] . "']");

        // doesn't exist
        if (count($nodes) == 0) {
          $node = $base->addChild($tag_name, dbsteward::string_cast($child));
          $node->addAttribute('name', $child['name']);
        }
        else {
          $node = NULL;

          // find the function that has the same parameters
          for ($i = 0; $i < count($nodes); $i++) {
            $base_node = $nodes[$i];
            // match them in cardinal order
            $base_parameters = $base_node->xpath("functionParameter");
            $overlay_parameters = $child->xpath("functionParameter");

            // only analyze further if they have the same number of parameters
            if (count($base_parameters) != count($overlay_parameters)) {
              continue;
            }

            for ($j = 0; $j < count($overlay_parameters); $j++) {
              $base_param = $base_parameters[$j];
              $overlay_param = $overlay_parameters[$j];

              // is the parameter the same type, at the same index?
              if (strcasecmp($base_param['type'], $overlay_param['type']) != 0) {
                // no they aren't, this is not the function we are looking for
                // continue 2 to go to next node in $nodes
                continue 2;
              }
            }

            // check to make sure there aren't duplicate sqlFormats
            $f = function ($n) { return strtolower($n['sqlFormat']); };
            $base_formats = array_map($f, $base_node->xpath("functionDefinition"));
            $overlay_formats = array_map($f, $child->xpath("functionDefinition"));

            // if there isn't a functionDefinition with the same sqlFormat
            if ( count(array_intersect($base_formats, $overlay_formats)) == 0 ) {
              continue;
            }

            // made it through the whole parameter list without breaking out
            // this is the function we have been looking for all the days of our lives
            $node = $nodes[$i];
            break;
          }

          // it was not found, create it
          if ($node === NULL) {
            $node = $base->addChild($tag_name, dbsteward::string_cast($child));
            $node->addAttribute('name', $child['name']);
          }
          else if ( ! dbsteward::$allow_function_redefinition ) {
            throw new Exception("function " . $child['name'] . " with identical parameters is being redefined");
          }
        }

        // the base function being replaced element's children will all be added unconditionally (see next else if)
        unset($node->functionParameter);
        // kill functionDefinition and grant tags as well, soas to keep the sane element order the DTD enforces
        unset($node->functionDefinition, $node->grant);
      }
      // functions are uniquely identified by their parameters
      // add them unconditionally as they were deleted in the parent cursor
      // note we also apply slightly more magic to functionParameter and functionDefinition CDATA tags
      else if (strcasecmp('functionParameter', $tag_name) == 0
        || strcasecmp('functionDefinition', $tag_name) == 0) {
        $node = $base->addChild($tag_name, self::ampersand_magic($child));
      }
      // viewQuery elements are identified by their sqlFormat value
      else if (strcasecmp('viewQuery', $tag_name) == 0) {
        $nodes = $base->xpath($tag_name . "[@sqlFormat='" . $child['sqlFormat'] . "']");
        // doesn't exist
        if (count($nodes) == 0) {
          $node = $base->addChild($tag_name, dbsteward::string_cast($child));
          // sqlFormat for viewQuery is optional
          if ($child['sqlFormat']) {
            $node->addAttribute('sqlFormat', $child['sqlFormat']);
          }
        }
        else {
          $node = $nodes[0];
        }
      }
      // match trigger tags by attributes that are unique in postgresql
      else if (strcasecmp('trigger', $tag_name) == 0) {
        $trigger_attributes = array('name', 'table');
        $attributes_xpath = "";
        foreach ($trigger_attributes AS $trigger_attribute) {
          $attributes_xpath .= "@" . $trigger_attribute . "='" . $child[$trigger_attribute] . "' and ";
        }
        $attributes_xpath = substr($attributes_xpath, 0, -5);
        $xpath = "trigger[" . $attributes_xpath . "]";
        $nodes = $base->xpath($xpath);
        if (count($nodes) > 1) {
          throw new exception("more than one match for " . $xpath);
        }
        // doesn't exist
        if (count($nodes) == 0) {
          //dbsteward::console_line(7, "DEBUG: Add missing trigger: " . $child->asXML());
          $node = $base->addChild($tag_name, dbsteward::string_cast($child));
          $node->addAttribute('name', $child['name']);
        }
        else {
          $node = $nodes[0];
        }
      }
      // more basic name attribute match
      else if (isset($child['name'])) {
        $xpath = $tag_name . "[@name='" . $child['name'] . "']";
        $nodes = $base->xpath($xpath);
        if (count($nodes) > 1) {
          throw new exception("more than one match for " . $xpath);
        }
        // doesn't exist
        if (count($nodes) == 0) {
          $node = $base->addChild($tag_name, dbsteward::string_cast($child));
          $node->addAttribute('name', $child['name']);
        }
        else {
          $node = $nodes[0];
        }
      }

      ////////// DATA OVERLAY ALGORITHM START //////////
      // because we are overlaying overlay onto the base side
      // we want to have a composite of lists of data, with differences included
      // rows entries - add when doesn't exist, merge when they do
      else if (strcasecmp($tag_name, 'rows') == 0) {
        self::data_rows_overlay($base, $child);
        // continue the loop so the rows element isn't recursed into below
        continue;
      }
      ////////// DATA OVERLAY ALGORITHM END   //////////

      // always add <grant> table/function permissions
      else if (strcasecmp($tag_name, 'grant') == 0) {
        $node = $base->addChild($tag_name, self::ampersand_magic($child));
      }
      // add / compare sql tags based on their contents making them unique, not their name
      else if (strcasecmp($tag_name, 'sql') == 0) {
        $nodes = $base->xpath('sql[. ="' . $child . '"]');
        if ($nodes === FALSE) {
          throw new exception("xpath to lookup sql match for sql element inner text '" . $child . "' returned error");
        }
        if (count($nodes) == 0) {
          $node = $base->addChild($tag_name, self::ampersand_magic($child));
        }
        // the node matches by contents, set the pointer to it
        else {
          $node = $nodes[0];
        }
      }
      // match replicaNode by id
      else if (strcasecmp($tag_name, 'replicaNode') == 0) {
        $xpath = $tag_name . "[@id='" . $child['id'] . "']";
        $nodes = $base->xpath($xpath);
        if (count($nodes) > 1) {
          throw new exception("more than one match for " . $xpath);
        }
        // doesn't exist
        if (count($nodes) == 0) {
          $node = $base->addChild($tag_name);
          $node->addAttribute('id', $child['id']);
        }
        else {
          $node = $nodes[0];
        }
      }
      // else, element tag name match
      else {
        $nodes = $base->xpath($tag_name);
        if (count($nodes) > 1) {
          throw new exception("more than one match for " . $tag_name);
        }
        // doesn't exist
        if (count($nodes) == 0) {
          $node = $base->addChild($tag_name, dbsteward::string_cast($child));
        }
        else {
          $node = $nodes[0];

          // we have determined that we are only matching on tag_name, so it's pretty safe to
          // abuse -> accessor to change the value of the tag with the base->node accessor to transfer the value of child tag to node
          // @NOTICE: we also assume this is a data definition tag with no children
          if (strlen((string)$child) > 0 && count($child->children()) == 0) {
            $base->{$tag_name} = (string)$child;
          }
        }
      }

      if (!is_object($node)) {
        var_dump($child);
        var_dump($node);
        throw new exception("node is not an object, panic!");
      }

      // set attributes for the element, either found or created
      foreach ($child->attributes() AS $attribute => $value) {
        if (!isset($node[$attribute])) {
          $node->addAttribute($attribute, $value);
        }
        else {
          $node[$attribute] = $value;
        }
      }

      // recurse if child has children
      if (count($child->children()) > 0) {
        self::xml_composite_children($node, $child);
      }
    }

    return TRUE;
  }

  public static function data_rows_overlay(&$base, &$child) {
    $node = & dbx::get_table_rows($base, TRUE, $child['columns']);
    $node_row_count = count($node->row);

    // if the rows element columns attribute doesnt have a column that the overlay does
    if (strlen($node['columns']) == 0) {
      throw new exception("base rows element missing columns attribute - unexpected");
    }
    if (strlen($child['columns']) == 0) {
      throw new exception("overlay rows element missing columns attribute - unexpected");
    }
    $base_cols = preg_split("/[\,\s]+/", $node['columns'], -1, PREG_SPLIT_NO_EMPTY);
    $overlay_cols = preg_split("/[\,\s]+/", $child['columns'], -1, PREG_SPLIT_NO_EMPTY);
    $cols_diff = array_diff($overlay_cols, $base_cols);
    // contains any values $overlay_cols does that $base_cols didnt, so add them
    foreach ($cols_diff AS $cols_diff_col) {
      // add the missing column, padding the base's row->col entries with empty col's to match the new size
      $base_cols[] = $cols_diff_col;
      for ($i = 0; $i < $node_row_count; $i++) {
        // need to do it for each row entry, check for default for the column
        $node_col = $node->row[$i]->addChild('col', self::column_default_value($base, $cols_diff_col, $node_col));
      }
    }
    // put the new columns list back in the node
    $node['columns'] = implode(', ', $base_cols);

    // merge all row entries for the rows element
    $base_primary_keys = preg_split("/[\,\s]+/", $base['primaryKey'], -1, PREG_SPLIT_NO_EMPTY);
    $primary_key_index = self::data_row_overlay_primary_key_index($base_primary_keys, $base_cols, $overlay_cols);

    $base_row_index = 0;
    foreach ($child->row AS $overlay_row) {
      // sanity check the overlay's rows columns list against the col count of the row
      $overlay_row_count = count($overlay_row->col);
      if (count($overlay_cols) != $overlay_row_count) {
        dbsteward::console_line(1, count($overlay_cols) . " elements != " . $overlay_row_count . " elements");
        var_dump($overlay_cols);
        foreach ($overlay_row->col AS $olcol) {
          var_dump($olcol);
        }
        throw new exception("overlay_cols list count does not match overlay_row->col count");
      }

      // simple optimization:
      // if the node had no ->row's to start
      // don't try to match any of the children we are considering in this loop
      if ($node_row_count == 0) {
        $row_match = FALSE;
      }
      else {
        $row_match = self::data_row_overlay_key_search($node, $overlay_row, $primary_key_index, $base_row_index);
      }
      
/* DATA OVERLAY DEBUG TACTICAL WEAPON. UNCOMMENT TO BRING THE RAIN. Search for companion blocks of code
if ( strcasecmp($base['name'], 'app_mode') == 0 ) {
  $pkv = '';
  foreach($primary_key_index AS $pki_table => $pki_table_map) {
    $pkv .= $overlay_cols[$pki_table_map['overlay_index']] . ' = ' . $overlay_row->col[$pki_table_map['overlay_index']] . ', ';
  }
  $pkv = substr($pkv, 0, -2);
  $pkv = "(" . $pkv . ")";
  dbsteward::console_line(4, "DEBUG: " . $base['name'] . " primary key " . $pkv . " match at " . $base_row_index);
}
/**/

      if ($row_match) {
        // $base_row_index is set to $i in _match() when a match is found, so use it to overlay the matched row
        $node_row = $node->row[$base_row_index];
      }
      else {
        // not found, add the row and empty col entries
        $node_row = $node->addChild('row');
        foreach ($base_cols AS $base_col) {
          $node_col = $node_row->addChild('col');
          $node_col = self::column_default_value($base, $base_col, $node_col);
        }
        // then overlay the data in the overlay row
      }

      self::data_row_overlay_row($base, $node_row, $overlay_row, $base_cols, $overlay_cols);
    }
  }

  public static function data_row_overlay_primary_key_index($primary_key_cols, $base_cols, $overlay_cols) {
    // create a map to  find the numeric column index of each primary key
    // in the base and overlay column lists
    $primary_key_index = array();
    foreach ($primary_key_cols AS $primary_key) {
      $primary_key_index[$primary_key] = array('base_index' => array_search($primary_key, $base_cols),
        'overlay_index' => array_search($primary_key, $overlay_cols));
      if ($primary_key_index[$primary_key]['base_index'] === FALSE) {
        throw new exception("base primary_key " . $primary_key . " not found in base_cols: " . implode(', ', $base_cols));
      }
      if ($primary_key_index[$primary_key]['overlay_index'] === FALSE) {
        throw new exception("overlay primary_key " . $primary_key . " not found in overlay_cols: " . implode(', ', $overlay_cols));
      }
    }
    return $primary_key_index;
  }

  //public static $data_row_overlay_key_search_count = 0;
  public static function data_row_overlay_key_search($node_rows, $overlay_row, $primary_keys, &$base_row_index) {
    $node_row_count = count($node_rows->row);
    //dbsteward::console_line(7, "data_row_overlay_key_search() #" . ++self::$data_row_overlay_key_search_count . " node_row_count = " . $node_row_count . " base_row_index = " . $base_row_index);
    if (!is_object($node_rows)) {
      var_dump($node_rows);
      throw new exception("node_rows is not an object, check caller");
    }
    if (!is_object($overlay_row)) {
      var_dump($overlay_row);
      throw new exception("overlay_row is not an object, check caller");
    }

    // base_row_index is at max? reset to 0
    if ($base_row_index == $node_row_count - 1) {
      $base_row_index = 0;
    }

    // look for an existing row that is using the same primary keys in its cols
    // only if there were some rows to start with
    // otherwise the search algorithm primalizes into always search everything from the beginning and makes baby seals cry
    // start out with no match
    $row_match = FALSE;
    if ($node_row_count > 0) {
      // if base had rows
      for ($i = $base_row_index; $i < $node_row_count; $i++) {
        foreach ($primary_keys AS $primary_key_name => $primary_key) {
          if (strcmp($node_rows->row[$i]->col[$primary_key['base_index']], $overlay_row->col[$primary_key['overlay_index']]) != 0) {
            // doesn't match, on to the next row
            $row_match = FALSE;

            // base_row_index cached offset is > 0 ?
            if ($base_row_index > 0) {
              // i about to hit base_row_index ?
              if ($i == $base_row_index - 1) {
                // stop the primary_key match search for this child row's match
                break 2;
                // break out of the for i loop
              }
              // i is about to max? reset i to -1 so 0 index wraps back around to 0 after the b++
              if ($i == $node_row_count - 1) {
                $i = -1;
              }
            }
            continue 2; // continue looking in the for i loop
          }
          $row_match = TRUE;
        }

        if ($row_match) {
          $base_row_index = $i;
          // remember where we left off, to optimize the search for primary key matches
          break; // matching row was found, so we can break out of the for i node->row loop
        }
      }
    }
    return $row_match;
  }

  public static function data_row_overlay_row(&$base, &$node_row, &$overlay_row, $base_cols, $overlay_cols) {
    // found, put all of overlay's value in the bases's columns
    $overlay_cols_count = count($overlay_cols);
    for ($j = 0; $j < $overlay_cols_count; $j++) {
      $base_col_index = array_search($overlay_cols[$j], $base_cols);
      if ($base_col_index === FALSE) {
        var_dump($base_cols);
        var_dump($overlay_cols);
        var_dump($overlay_row);
        throw new exception("failed to find overlay_col " . $overlay_cols[$j] . " in base_cols");
      }
/* DATA OVERLAY DEBUG TACTICAL WEAPON. UNCOMMENT TO BRING THE RAIN. Search for companion blocks of code
if ( strcasecmp($base['name'], 'app_mode') == 0 && strcasecmp($overlay_cols[$j], 'is_turned_on') == 0 ) {
  dbsteward::console_line(6, "DEBUG: " . $base['name'] . " base index match for " . $overlay_cols[$j] . " at " . $base_col_index);
}
/**/

      // if has null="true" set, null the column
      if (isset($overlay_row->col[$j]['null']) && strcasecmp($overlay_row->col[$j]['null'], 'true') == 0) {
        $node_row->col[$base_col_index] = NULL;
      }
      // if has empty="true" set, empty string the column, keeping the attrib so data generator will do it
      else if (isset($overlay_row->col[$j]['empty']) && strcasecmp($overlay_row->col[$j]['empty'], 'true') == 0) {
        $node_row->col[$base_col_index] = '';
        // we are compositing, so ignore the value and obey the empty attribute
        // carry / reset the empty attribute
        unset($node_row->col[$base_col_index]['empty']);
        $node_row->col[$base_col_index]->addAttribute('empty', $overlay_row->col[$j]['empty']);
      }
      // no special attributes, so now
      // only overwrite the data in node row if the overlay col contains data
      // this way placeholder columns in install-specific overlay files don't empty out columns
      // that were intended to retain their base side value
      else if (strlen($overlay_row->col[$j]) > 0) {
        // This line was causing problems when an ampersand was included in a column
        // it seems in the late versions of PHP 5.2, that LESS ampersand magic is required, albeit some in other places still is
        // see the ampersand_magic function docblock for more confusion
        //$node_row->col[$base_col_index] = self::ampersand_magic($overlay_row->col[$j]);
/* DATA OVERLAY DEBUG TACTICAL WEAPON. UNCOMMENT TO BRING THE RAIN. Search for companion blocks of code
if ( strcasecmp($base['name'], 'app_mode') == 0 && strcasecmp($overlay_cols[$j], 'is_turned_on') == 0 ) {
  dbsteward::console_line(6, "DEBUG: overwrite " . $overlay_cols[$j] . " value " . $node_row->col[$base_col_index] . " as " . $overlay_row->col[$j]);
}
/**/
        $node_row->col[$base_col_index] = $overlay_row->col[$j];
      }
      // the overlay column is empty
      // if the base column is empty also, check for default definition on the column
      // this is for when new column overlays are created or new rows are created in the base side
      else if (strlen($node_row->col[$base_col_index]) == 0) {
        $node_row->col[$base_col_index] = self::column_default_value($base, $overlay_cols[$j], $node_row->col[$base_col_index]);
      }

      // independent of the data modes if else block above, pull in the sql attribute if it is set, and if it is true
      if (isset($overlay_row->col[$j]['sql']) && strcasecmp($overlay_row->col[$j]['sql'], 'true') == 0) {
        $node_row->col[$base_col_index]['sql'] = 'true';
      }
    }

    // always re-assign the row delete mode
    unset($node_row['delete']);
    if (isset($overlay_row['delete'])) {
      $node_row->addAttribute('delete', $overlay_row['delete']);
    }
  }

  public static function validate_xml($xml, $echo_status = TRUE) {
    $dtd_file = dirname(__FILE__) . '/../DBSteward/dbsteward.dtd';
    if (!is_readable($dtd_file)) {
      // not source mode? PEAR mode it is!
      $dtd_file = dirname(__FILE__) . '/../data/DBSteward/DBSteward/dbsteward.dtd';
    }
    if (!is_readable($dtd_file)) {
      throw new exception("DTD file $dtd_file not readable");
    }
    $dtd_file = realpath($dtd_file);
    if ($echo_status) {
      dbsteward::console_line(1, "Validating XML (size = " . strlen($xml) . ") against $dtd_file");
    }
    $tmp_file = tempnam('/var/tmp/', 'dbsteward_validate_');
    if (file_put_contents($tmp_file, $xml) === FALSE) {
      throw new exception("Failed to write to temporary validation file: " . $tmp_file);
    }
    dbsteward::cmd("xmllint --noout --dtdvalid " . $dtd_file . " " . $tmp_file . " 2>&1");
    if ($echo_status) {
      dbsteward::console_line(1, "XML Validates (size = " . strlen($xml) . ") against $dtd_file OK");
    }
    unlink($tmp_file);
  }

  public static function table_dependency_order($db_doc) {
    $table_list = array();
    $schemas = $db_doc->xpath('schema');
    foreach ($schemas AS $schema) {
      $tables = $schema->xpath('table');
      foreach ($tables AS $table) {
        $table_list[] = array(
          'schema' => $schema,
          'table' => $table
        );
      }
    }

    //  version 1
    /**/
    $table_list_count = count($table_list);
    for ($i = 0; $i < $table_list_count; $i++) {
      for ($j = $i + 1; $j < $table_list_count; $j++) {
        // if i has a dependence on j, put i at the bottom of the list
        if (self::table_has_dependency($table_list[$i], $table_list[$j])) {
          $table = $table_list[$i];
          // save the old entry outside the array
          unset($table_list[$i]);
          // delete the old entry
          $table_list = array_merge($table_list, array($table));

          // check the $i index again, the array has been reformed
          $i--;
          break;
        }
      }
    }
    /**/

    //  version 2
    // nkiraly@: there is no evidence the much more CPU / time expensive recursive sort
    // is necessary (yet) for data dependency ordering
    // but I'm not going to throw it away yet either
    //    self::table_dependency_sort($table_list);
    
    
    // pgsql8_diff::update_structure() does some pre and post changes by schema
    // but if a schema does not define any tables, it will get missed
    // to compensate, create an entry for any schema not mentioned in $table_list
    foreach($schemas AS $schema) {
      foreach($table_list AS $table_entry) {
        if ( $schema['name'] == $table_entry['schema'] ) {
          // schema is mentioned, move to next schema in the list
          continue 2;
        }
      }
      $table_list[] = array(
        'schema' => $schema,
        'table' => array(
          'name' => dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME
        )
      );
    }
    
    return $table_list;
  }

  /**
   * Is a dependant on b?
   *
   * @param tablenode $a
   * @param tablenode $b
   * @return boolean
   */
  public static function table_has_dependency($a, $b) {
    // does b contain a column that a references?
    $columns = $a['table']->xpath('column');
    foreach ($columns AS $column) {
      // column is a foreignKey column ?
      if (isset($column['foreignTable'])) {
        // talking about the same schema?
        if (strcasecmp($column['foreignSchema'], $b['schema']['name']) == 0) {
          // talking about the same table?
          if (strcasecmp($column['foreignTable'], $b['table']['name']) == 0) {
            // so yes, a has a dependency on b via column inline foreign key definition
            //dbsteward::console_line(7, $a['schema']['name'] . '.' . $a['table']['name'] . '.' . $column['name'] . "\thas inline fkey dep on\t" . $b['schema']['name'] . '.' . $b['table']['name']);
            return TRUE;
          }
        }
      }
    }
    // does b contain a column that a constraints against?
    $constraints = $a['table']->xpath('constraint');
    foreach ($constraints AS $constraint) {
      // FOREIGN KEY constraint?
      if ( strcasecmp($constraint['type'], "FOREIGN KEY") == 0 ) {
        // constraint specifies what table it is dependent on?
        if (isset($constraint['foreignTable'])) {
          // talking about the same schema?
          if (strcasecmp($constraint['foreignSchema'], $b['schema']['name']) == 0) {
            // talking about the same table?
            if (strcasecmp($constraint['foreignTable'], $b['table']['name']) == 0) {
              // so yes, a has a dependency on b via constraint definition
              //dbsteward::console_line(7, $a['schema']['name'] . '.' . $a['table']['name'] . '.' . $column['name'] . "\thas constraint dep on\t" . $b['schema']['name'] . '.' . $b['table']['name']);
              return TRUE;
            }
          }
        }
      }
    }
    return FALSE;
  }

  public static $table_dependency_sort_depth = 0;
  public static function table_dependency_sort(&$table_list, $recursion_index = FALSE) {
    //dbsteward::console_line(6, "ENTER table_dependency_sort()");
    //self::$table_dependency_sort_depth++;
    for ($i = 0; $i < floor(count($table_list) / 2); $i++) {
      $append_list = array();

      for ($j = $i + 1; $j < count($table_list); $j++) {
        if ($recursion_index !== FALSE) {
          $j = $recursion_index;
        }
        // i depends on j ?
        if (self::table_has_dependency($table_list[$i], $table_list[$j])) {
          //dbsteward::console_line(6, "DEPTH " . self::$table_dependency_sort_depth . "\t" . $table_list[$i]['schema']['name'] . "." . $table_list[$i]['table']['name'] . " " . $i . "\tDEPENDS ON\t" . $table_list[$j]['schema']['name'] . "." . $table_list[$j]['table']['name'] . " " . $j);
          $append_list = array_merge($append_list, array($table_list[$i]));
          // discard the i entry in main array
          unset($table_list[$i]);
          // reindex main list
          $table_list = array_merge($table_list);

          // the table_list is one smaller, decrement the indices used on it
          $i--;
          if ($i < 0) {
            $i = 0;
          }
          $j--;

          // check for things j is dependant on
          self::table_dependency_sort($table_list, $j);
        }
        if ($recursion_index !== FALSE) {
          break;
        }
      }

      if (count($append_list) > 0) {
        // j's dependencies have been added to the end from recursion
        // now can add the i dependent on j append_list
        $table_list = array_merge($table_list, $append_list);

        // do this i index again since the array was reformed
        $i--;
      }
    }
    //dbsteward::console_line(6, "RETURN table_dependency_sort()");
    //self::$table_dependency_sort_depth--;
  }

  /**
   * composite postgresql schema_to_xml() database_to_xml() data outputs onto a dbsteward database definition
   *
   * @param SimpleXMLElement base        full definition element to add data to
   * @param SimpleXMLElement pgoverlay   postgres-style data output xml data to add
   *
   * @return void
   */
  public function xml_composite_pgdata($output_prefix, &$base, $pgdatafiles) {
    // psql -U deployment megatrain_nkiraly -c "select database_to_xml(true, false, 'http://dbsteward.org/pgdataxml');"
    /*
    <megatrain_nkiraly xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://dbsteward.org/pgdataxml">
    <public>
    <system_status_list>
    <row>
    <system_status_list_id>1</system_status_list_id>
    <system_status>Active</system_status>
    </row>
    </system_status_list>
    </public>
    <search_results>
    </search_results>
    </megatrain_nkiraly>
    /**/

    $file_list = '';

    foreach ($pgdatafiles AS $file) {
      $file_name = realpath($file);
      $file_list .= $file_name . ' ';
      dbsteward::console_line(1, "Loading postgres data XML " . $file_name);
      $xml_contents = file_get_contents($file_name);
      if ($xml_contents === FALSE) {
        throw new exception("Failed to load postgres data XML from disk: " . $file_name);
      }
      $doc = simplexml_load_string($xml_contents);
      if ($doc === FALSE) {
        throw new Exception("failed to simplexml_load_string() contents of " . $file_name);
      }

      dbsteward::console_line(1, "Compositing postgres data (size=" . strlen($xml_contents) . ")");
      foreach ($doc AS $schema) {
        foreach ($schema AS $table) {
          $table_xpath = "schema[@name='" . $schema->getName() . "']/table[@name='" . $table->getName() . "']";
          $nodes = $base->xpath($table_xpath);
          if (count($nodes) != 1 || $nodes === FALSE) {
            var_dump($nodes);
            throw new exception("xpath did not yield one table match: " . $table_xpath);
          }
          $node = $nodes[0];

          // check for actual row children
          $rows = $table->children();
          if (count($rows) == 0) {
            //throw new exception("table " . $table->getName() . " has no row children");
          }
          else {
            // first row members used to designate columns attribute
            $row = $rows[0];
            $columns = '';
            $i = 0;
            foreach ($row AS $column) {
              $columns .= $column->getName();
              if ($i < count($row) - 1) {
                $columns .= ', ';
              }
              $i++;
            }
            // switch rows pointer back to the document
            $rows = $node->addChild('rows');
            $rows->addAttribute('columns', $columns);

            // pump all of the row cols
            foreach ($table AS $row) {
              if (strcasecmp($row->getName(), 'row') != 0) {
                throw new exception("schema->table->row iterator expected row tag but found " . $row->getName());
              }
              $node_row = $rows->addChild('row');
              foreach ($row AS $column) {
                $node_row->addChild('col', self::ampersand_magic($column));
              }
            }
          }
        }
      }

      // revalidate composited xml
      self::validate_xml($base->asXML());
    }

    $composite_file = $output_prefix . '_composite.xml';
    dbsteward::console_line(1, "postgres data XML files [" . $file_list . "] composited. Saving as " . $composite_file);
    xml_parser::save_xml($composite_file, $base->saveXML());
  }

  /**
   * that's right. perform magic on strings intended for SimpleXMLNode's that may contain explicit ampersands
   *
   * lol @ PHP developers: http://bugs.php.net/bug.php?id=44458
   * we must escape ampersands explicitly ... or something...
   *
   * @param  string  $s  string data
   *
   * @return string      string data, with magic applied
   */
  public function ampersand_magic($s) {
    return str_replace('&', '&amp;', (string)$s);
  }

  /**
   * return the column default value if it is defined
   *
   * @param SimpleXMLNode  $table_name   table definition xml node
   * @param string         $column_name  name of column to look up default value
   *
   * @return string column default value, null if not defined for the $column_name
   */
  public function column_default_value(&$table_node, $column_name, &$node) {
    // find the column node in the table
    $xpath = 'column[@name="' . $column_name . '"]';
    $nodes = $table_node->xpath($xpath);
    if (count($nodes) != 1) {
      throw new exception(count($nodes) . " column elements found via xpath '" . 'column[@name="' . $column_name . '"]' . "' - unexpected!");
    }
    $column_node = &$nodes[0];

    $default_value = NULL;

    // if it has a default value defined, use it
    if (isset($column_node['default']) && strlen($column_node['default']) > 0) {
      $default_value = dbsteward::string_cast($column_node['default']);
      if (strcasecmp($column_node['default'], 'null') == 0) {
        // the column is allowed to be null and the definition of default is null
        // so instead of setting the value, mark the column null
        //dbsteward::console_line(7, 'column_default_value ' . $table_node['name'] . '.' . $column_node['name'] . ' default null');
        $node['null'] = 'true';
      }
      else {
        ////dbsteward::console_line(7, 'column_default_value ' . $table_node['name'] . '.' . $column_node['name'] . ' default value ' . $column_node['default']);
        $default_value = format::strip_string_quoting($default_value);
      }
    }

    return $default_value;
  }

  /**
   * sort elements / levels of $file_name, save it as $sorted_file_name
   *
   * @param    string    $file_name          dbsteward definition file to sort
   * @param    string    $sorted_file_name   sorted dbsteward definition save as
   * @return   boolean   success
   */
  public function file_sort($file_name, $sorted_file_name) {
    $doc = simplexml_load_file($file_name);

    // create a list of schemas and tables to get around simplexml iterator nodes - they can't be sorted and/or unset/set like PHP array()s
    $table_map = array();

    // sort the various levels of db definition
    // sort schema order
    self::file_sort_children($doc, 'schema', 'name');
    foreach ($doc->schema AS $schema) {
      self::file_sort_children($schema, 'table', 'name');
      foreach ($schema->table AS $table) {
        // on each table, sort columns in it
        self::file_sort_children($table, 'column', 'name');

        // resort the column children so that primary keys are first
        $pkey_cols = preg_split("/[\,\s]+/", $table['primaryKey'], -1, PREG_SPLIT_NO_EMPTY);
        self::file_sort_prepend_child($table, 'column', 'name', $pkey_cols);

        // if there are data rows, sort them by primary key
        if (isset($table->rows)) {
          // the_pkey_indexes list is a list of row->col's that are primary key cols, in primaryKey order
          if (!isset($table['primaryKey'])) {
            // if the primaryKey isn't defined, specify col 0 and 1 as the pkey
            $pkey_indexes = array(0, 1);
          }
          else {
            $rows_cols = preg_split("/[\,\s]+/", $table->rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
            $pkey_indexes = array();
            foreach ($pkey_cols AS $pkey_col) {
              $pkey_indexes[] = array_search($pkey_col, $rows_cols);
            }
          }
          self::file_sort_children($table->rows, 'row', 'col', $pkey_indexes);
        }

        // make sure table definition order still follows the DTD:
        // <!ELEMENT table (column+, index*, constraint*, privilege_function*, grant*, rows?)>
        // if column defs were not all first, they will be after this!
        self::file_sort_reappend_child($table, 'index');
        self::file_sort_reappend_child($table, 'constraint');
        self::file_sort_reappend_child($table, 'privilege_function');
        self::file_sort_reappend_child($table, 'grant');
        self::file_sort_reappend_child($table, 'rows');
      }

      // reappend miscellany tags in the schema, so that they are after tables, and in order
      // note that they are not file_sort_childeren()'d as they have special compositing rules
      // that we don't get into in file_sort() and it's helpers
      self::file_sort_reappend_child($schema, 'sequence', 'name');
      self::file_sort_reappend_child($schema, 'function', 'name');
      self::file_sort_reappend_child($schema, 'trigger', 'name');
      self::file_sort_reappend_child($schema, 'grant', 'role');
    }

    return xml_parser::save_xml($sorted_file_name, $doc->saveXML());
  }

  /**
   * sort children of $node, identified by $child_id_attribute
   * this is done because the simplexml iterator can't be swap-sorted
   *
   * @param simplexmlelement $node                 node to sort children of
   * @param string           $child_name           children element name to sort
   * @param string           $child_id_attribute   unqiue identifier attribute of each child by that name
   * @param string           $child_id_map         if specified, the child_id will be composed of the values of the nth columns in the $child_id_attribute list
   *                                               this is used to sort <rows> <row> definitions by primary key
   *
   * @return void
   */
  protected function file_sort_children(&$node, $child_name, $child_id_attribute, $child_id_map = NULL) {
    // this is used to sget around simplexml iterator nodes - they can't be sorted and/or unset/set like PHP array()s
    // get a list of children identified by id_attribute
    $child_ids = array();
    $child_node_xml = array();
    foreach ($node->{$child_name} AS $child_node) {
      if ($child_id_map === NULL) {
        $child_id = (string)($child_node[$child_id_attribute]);
      }
      else {
        $child_id = '';
        foreach ($child_id_map AS $child_id_index) {
          // example:  row->col[0]  for rows collection with primary key in first col
          $col_id = (string)($child_node->{$child_id_attribute}[$child_id_index]);
          // zero-pad numerics so sorting is numerically accurate as well as with strings
          if (is_numeric($col_id)) {
            $col_id = sprintf('%07d', $col_id);
          }
          $child_id .= $col_id . '_';
        }
        $child_id = substr($child_id, 0, -1);
      }
      $child_ids[] = $child_id;
      // copy each child XML to an array, indexed by their id_attribute
      // this array is used for readding the chidlren to the parent $node
      $child_node_xml[$child_id] = $child_node->asXML();
    }

    // sort the list alphabetically
    sort($child_ids, SORT_STRING);

    // kill existing child nodes
    unset($node->{$child_name});

    // use the sorted list to re-add child nodes in order
    foreach ($child_ids AS $child_id) {
      $new_child = $node->addChild($child_name);
      self::file_sort_children_node_merge($new_child, simplexml_load_string($child_node_xml[$child_id]));
    }
  }

  /**
   * re-append specific children of a node at the end
   * this is used to keep element children in compliance with the dtd
   *
   * @param simplexmlelement   $node          root node to work with
   * @param simplexmlelement   $child_name    children element name to reappend
   *
   * @return void
   */
  protected function file_sort_reappend_child(&$node, $child_name) {
    if (isset($node->{$child_name})) {
      $child_nodes = array();
      foreach ($node->{$child_name} AS $child) {
        $child_nodes[] = $child->asXML();
      }
      unset($node->{$child_name});
      foreach ($child_nodes AS $child_node) {
        $new_child = $node->addChild($child_name);
        self::file_sort_children_node_merge($new_child, simplexml_load_string($child_node));
      }
    }
  }

  /**
   * pre-pend specific child nodes (identified by id attribute) at the beginning of the node collection
   *
   * @param simplexmlelement   $node          root node to work with
   * @param simplexmlelement   $child_name    children element name to reappend
   *
   * @return void
   */
  protected function file_sort_prepend_child(&$node, $child_name, $child_id_attrib, $child_id_attrib_values) {
    if (isset($node->{$child_name})) {
      if (!is_array($child_id_attrib_values)) {
        $child_id_attrib_values = array($child_id_attrib_values);
      }
      $prepend_child_nodes = array();
      $child_nodes = array();
      foreach ($node->{$child_name} AS $child) {
        // is this the node to prepend?
        if (isset($child[$child_id_attrib]) && in_array($child[$child_id_attrib], $child_id_attrib_values)) {
          // we key it with the child_id_attrib_value to keep the order that was given for the attribs
          $prepend_child_nodes[(string)($child[$child_id_attrib]) ] = $child->asXML();
        }
        else {
          // no it's one of the others
          $child_nodes[] = $child->asXML();
        }
      }
      // kill all children
      unset($node->{$child_name});

      // add prependers first, in child_id_attrib_values order
      foreach ($child_id_attrib_values AS $child_id_attrib_value) {
        $child_node = $prepend_child_nodes[$child_id_attrib_value];
        $new_child = $node->addChild($child_name);
        self::file_sort_children_node_merge($new_child, simplexml_load_string($child_node));
      }

      // add the rest
      foreach ($child_nodes AS $child_node) {
        $new_child = $node->addChild($child_name);
        self::file_sort_children_node_merge($new_child, simplexml_load_string($child_node));
      }
    }
  }

  /**
   * sort children of $node, identified by $child_id_attribute
   * this is done because the simplexml iterator can't be swap-sorted
   *
   * @param simplexmlelement   $to_node          node to sort children of
   * @param simplexmlelement   $from_node        children element name to sort
   * @param boolean            $copy_attributes  should the attributes of from_node be copied to to_node
   *
   * @return void
   */
  protected function file_sort_children_node_merge(&$to_node, &$from_node, $copy_attributes = TRUE) {
    // merge 'from' attributes onto 'to' node
    if ($copy_attributes) {
      foreach ($from_node->attributes() as $attr_key => $attr_value) {
        $to_node->addAttribute($attr_key, $attr_value);
      }
    }

    foreach ($from_node->children() as $simplexml_child) {
      $simplexml_temp = $to_node->addChild($simplexml_child->getName(), self::ampersand_magic($simplexml_child));
      foreach ($simplexml_child->attributes() as $attr_key => $attr_value) {
        $simplexml_temp->addAttribute($attr_key, $attr_value);
      }
      self::file_sort_children_node_merge($simplexml_temp, $simplexml_child, FALSE);
    }
  }

  public static function format_xml($xml) {
    $dom_doc = new DOMDocument();
    $dom_doc->preserveWhiteSpace = FALSE;
    $dom_doc->formatOutput = TRUE;
    $dom_doc->loadXML($xml);
    return $dom_doc->saveXML();
  }

  public static function save_xml($file_name, $xml) {
    return file_put_contents($file_name, self::format_xml($xml));
  }

  public static function role_enum($db_doc, $role) {
    if (!is_object($db_doc)) {
      var_dump($db_doc);
      throw new exception("invalid db_doc passed");
    }
    switch ($role) {
      // PUBLIC is accepted as a special placeholder for public
      case 'PUBLIC':
        $role = 'PUBLIC';
      break;
      case 'ROLE_APPLICATION':
        $role = dbsteward::string_cast($db_doc->database->role->application);
      break;
      case 'ROLE_OWNER':
        $role = dbsteward::string_cast($db_doc->database->role->owner);
      break;
      case 'ROLE_SLONY':
        $role = dbsteward::string_cast($db_doc->database->role->replication);
      break;
      default:
        if (isset($db_doc->database->role->customRole)) {
          $custom_roles = preg_split("/[\,\s]+/", $db_doc->database->role->customRole, -1, PREG_SPLIT_NO_EMPTY);
          for ($i = 0; $i < count($custom_roles); $i++) {
            if (strcasecmp($role, $custom_roles[$i]) == 0) {
              return $custom_roles[$i];
            }
          }
          if (!dbsteward::$ignore_custom_roles) {
            throw new exception("Failed to confirm custom role: " . $role);
          }
          else {
            // this is cleverville:
            // without having to modify the 450000 calls to role_enum
            // return role->owner when a role macro is not found and there is no custom role called $role
            return $db_doc->database->role->owner;
          }
        }
        throw new exception("Unknown role enumeration: " . $role);
      break;
    }
    return $role;
  }

  /**
   * the tabrow conversion feature is for human readable, condensed static data definitions
   *
   * expand tabrow entries (not DTD compliant) into row->col elements for dbsteward to reference
   *
   * @return converted $doc
   */
  protected static function expand_tabrow_data($doc) {
    foreach ($doc->schema AS $schema) {
      foreach ($schema->table as $table) {
        if (isset($table->rows)) {
          if (isset($table->rows->tabrow)) {
            foreach ($table->rows->tabrow AS $tabrow) {
              $row = $table->rows->addChild('row');
              $cols = explode("\t", $tabrow);
              foreach ($cols AS $col) {
                // similar to pgsql \N notation, make the column value explicitly null
                if (strcmp($col, '\N') == 0) {
                  $col_node = $row->addChild('col');
                  $col_node['null'] = 'true';
                }
                else {
                  $col_node = $row->addChild('col', (string)$col);
                }
              }
            }
            unset($table->rows->tabrow);
          }
        }
      }
    }
    return $doc;
  }

  /**
   * Database system convertions for specific supported sqlFormats
   *
   * @return converted $doc
   */
  public static function sql_format_convert($doc) {
    // legacy 1.0 column add directive attribute conversion
    foreach ($doc->schema AS $schema) {
      foreach ($schema->table AS $table) {
        foreach ($table->column AS $column) {
          if ( isset($column['afterAddPreStage1']) ) {
            $column['beforeAddStage1'] = (string)($column['afterAddPreStage1']);
            unset($column['afterAddPreStage1']);
          }
          if ( isset($column['afterAddPostStage1']) ) {
            $column['afterAddStage1'] = (string)($column['afterAddPostStage1']);
            unset($column['afterAddPostStage1']);
          }
          if ( isset($column['afterAddPreStage2']) ) {
            $column['beforeAddStage3'] = (string)($column['afterAddPreStage2']);
            unset($column['afterAddPreStage2']);
          }
          if ( isset($column['afterAddPostStage2']) ) {
            $column['afterAddStage3'] = (string)($column['afterAddPostStage2']);
            unset($column['afterAddPostStage2']);
          }
          if ( isset($column['afterAddPreStage3']) ) {
            $column['beforeAddStage3'] = (string)($column['afterAddPreStage3']);
            unset($column['afterAddPreStage3']);
          }
          if ( isset($column['afterAddPostStage3']) ) {
            $column['afterAddStage3'] = (string)($column['afterAddPostStage3']);
            unset($column['afterAddPostStage3']);
          }
        }
      }
    }

    // mssql10 sql format conversions
    if (strcasecmp(dbsteward::get_sql_format(), 'mssql10') == 0) {
      foreach ($doc->schema AS $schema) {
        // if objects are being placed in the public schema, move the schema definition to dbo
        if (strcasecmp($schema['name'], 'public') == 0) {
          if (dbx::get_schema($doc, 'dbo')) {
            throw new exception("sql_format_convert() attempting to move public schema to dbo but dbo schema already exists");
          }
          $schema['name'] = 'dbo';
        }
        foreach ($schema->table AS $table) {
          foreach ($table->column AS $column) {
            if (isset($column['foreignSchema']) && strcasecmp($column['foreignSchema'], 'public') == 0) {
              $column['foreignSchema'] = 'dbo';
            }

            // column type conversion
            if (isset($column['type'])) {
              self::mssql10_type_convert($column);
            }
          }
        }
      }
    }

    return $doc;
  }

  protected static function mssql10_type_convert(&$column) {
    // all arrays to varchar(896) - our accepted varchar key max for mssql databases
    // the reason this is done to varchar(896) instead of varchar(MAX)
    // is that mssql will not allow binary blobs or long string types to be keys of indexes and foreign keys
    // attempting to do so results in errors like
    // Column 'app_mode' in table 'dbo.registration_step_list' is of a type that is invalid for use as a key column in an index.
    if (substr($column['type'], -2) == '[]') {
      $column['type'] = 'varchar(896)';
    }

    switch (strtolower($column['type'])) {
      case 'boolean':
        $column['type'] = 'char(1)';
        if (isset($column['default'])) {
          switch (strtolower($column['default'])) {
            case "'t'":
            case 'true':
              $column['default'] = "'t'";
            break;
            case "'f'":
            case 'false':
              $column['default'] = "'f'";
            break;
            default:
              throw new exception("unknown column type boolean default " . $column['default']);
            break;
          }
        }
      break;
      case 'inet':
        $column['type'] = 'varchar(16)';
      break;
      case 'interval':
        $column['type'] = 'varchar(MAX)';
      break;
      case 'character varying':
      case 'varchar':
      case 'text':
        $column['type'] = 'varchar(MAX)';
      break;
      case 'timestamp':
      case 'timestamp without time zone':
        $column['type'] = 'datetime2';
      break;
      case 'timestamp with time zone':
        $column['type'] = 'datetimeoffset(7)';
      break;
      case 'time with time zone':
        //@TODO: is this conversion safe or atleast acceptable?
        $column['type'] = 'time';
      break;
      case 'serial':
        // pg serial = ms int identity
        // see http://msdn.microsoft.com/en-us/library/ms186775.aspx
        $column['type'] = 'int identity(1, 1)';
        if (isset($column['serialStart'])) {
          $column['type'] = 'int identity(' . $column['serialStart'] . ', 1)';
        }
      break;
      case 'bigserial':
        $column['type'] = 'bigint identity(1, 1)';
        if (isset($column['serialStart'])) {
          $column['type'] = 'bigint identity(' . $column['serialStart'] . ', 1)';
        }
      break;
      case 'uuid':
        // PostgreSQL's type uuid adhering to RFC 4122 -- see http://www.postgresql.org/docs/8.4/static/datatype-uuid.html
        // MSSQL almost equivalent known as uniqueidentifier -- see http://msdn.microsoft.com/en-us/library/ms187942.aspx
        // the column type is "a 16-byte GUID", 36 characters in length -- it does not claim to be, but appears to be their RFC 4122 implementation
        $column['type'] = 'uniqueidentifier';
      break;
      default:
        // no match to postgresql built-in types
        // check for custom types in the public schema
        // these should be changed to dbo
        if (stripos($column['type'], 'public.') !== FALSE) {
          $column['type'] = str_ireplace('public.', 'dbo.', $column['type']);
        }
      break;
    }

    // mssql doesn't understand epoch
    if (isset($column['default']) && strcasecmp($column['default'], "'epoch'") == 0) {
      $column['default'] = "'1970-01-01'";
    }
  }
}

?>
