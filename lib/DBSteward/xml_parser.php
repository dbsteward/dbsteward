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
   * Find the correct sql_format based on the content of the $files passed in
   *
   * @param array $files
   * @return bool|string
   */
  public static function get_sql_format($files) {
    foreach ($files as $file) {
      $xml_contents = @file_get_contents($file);
      if ($xml_contents === FALSE) {
        throw new exception("Failed to load XML from disk: " . $file);
      }

      $doc = simplexml_load_string($xml_contents);
      if ($doc === FALSE) {
        throw new Exception("failed to simplexml_load_string() contents of " . $file);
      }

      if (!empty($doc->database->sqlformat)) {
        return (string)$doc->database->sqlformat;
      }
    }
    return FALSE;
  }

  /**
   * Composite a list of XML files into one dbsteward definition
   *
   * @param  array     $files                       list of files to composite
   * @param  integer   $xml_collect_data_addendums  number of files at the end of the list to collate for addendum
   * @param  object    $addenums_doc                addendums doc passed by reference
   *
   * @return string    XML files contents, composited
   */
  public static function xml_composite($files, $xml_collect_data_addendums = 0, &$addendums_doc = NULL) {
    $composite = new SimpleXMLElement('<dbsteward></dbsteward>');

    if ($xml_collect_data_addendums > 0) {
      $addendums_doc = new SimpleXMLElement('<dbsteward></dbsteward>');
      $start_addendums_idx = count($files) - $xml_collect_data_addendums;
    }
    else {
      $addendums_doc = NULL;
      $start_addendums_idx = FALSE;
    }

    for ($i = 0; $i < count($files); $i++) {
      $file_name = $files[$i];
      dbsteward::notice("Loading XML " . realpath($file_name) . "..");
      $xml_contents = @file_get_contents($file_name);
      if ($xml_contents === FALSE) {
        throw new exception("Failed to load XML from disk: " . $file_name);
      }

      $doc = simplexml_load_string($xml_contents);
      if ($doc === FALSE) {
        throw new Exception("failed to simplexml_load_string() contents of " . $file_name);
      }

      dbsteward::notice("Compositing XML File " . $file_name);

      $composite = self::composite_doc($composite, $doc, $i, $file_name, $start_addendums_idx, $addendums_doc);
    }
    // revalidate composited xml
    self::validate_xml(self::format_xml($composite->saveXML()));


    return $composite;
  }

  /**
   * Does the heavy lifting of compsiting two xml documents
   * @param  SimpleXMLElement  $base          The base document we are merging into. If null, uses an empty dbsteward document
   * @param  SimpleXMLElement  $overlay       The document we are merging
   * @param  int               $idx           The index of the overlay in the layering
   * @param  SimpleXMLElement  $addendums_doc The addendums document
   * @return $base
   */
  public static function composite_doc($base, $overlay, $idx = 0, $file_name = '', $start_addendums_idx = FALSE, $addendums_doc = NULL) {
    if (!$base) {
      $base = new SimpleXMLElement('<dbsteward></dbsteward>');
    }

    $overlay = xml_parser::expand_tabrow_data($overlay);
    $overlay = xml_parser::sql_format_convert($overlay);
    $xml_contents = $overlay->saveXML();

    // only validate the first composite in the chain before composite has been completed
    if ($idx == 0) {
      self::validate_xml($xml_contents);
    }
    
    // if overlay defines the database element
    // composite it first, to adhere to the DTD
    $doc_clone = NULL;
    if ( isset($overlay->database) ) {
      $doc_clone = clone $overlay;
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
      // these elements do not need mainline overlaid from $overlay
      unset($overlay->inlineAssembly);
      unset($overlay->database);
    }
    
    // if doc_clone is defined, $overlay->database was found, add it after inlineAssembly entries
    // and if there were inlineAssembly elements included,
    // composite these inlineAssembly elements in very first
    if ( $doc_clone ) {
      if ( isset($doc_clone->inlineAssembly) ) {
        $doc_clone_ila = clone $doc_clone;
        unset($doc_clone_ila->database);
        self::xml_composite_children($base, $doc_clone_ila, $file_name);
      }
      
      // if not defined in the base yet
      if ( !isset($base->database) ) {
        //add database node to the base so it will be in the right natural order position
        $base->addChild('database');
      }
    }
    
    // if doc_clone is defined, there were database element values to overlay.
    // now that includeFile has been processed, put these values overlaid "last"
    if ( $doc_clone ) {
      self::xml_composite_children($base->database, $doc_clone->database, $file_name);
    }

    // includes done, now put $overlay values in, to allow definer to overwrite included file values at the same level
    if ($addendums_doc != NULL && $i >= $start_addendums_idx) {
      self::xml_composite_children($base, $overlay, $file_name, $addendums_doc);
    }
    else {
      self::xml_composite_children($base, $overlay, $file_name);
    }

    return $base;
  }

  /**
   * Looks for a sql-format specific xml parser (mysql5_xml_parser, pgsql8_xml_parser, etc),
   * attempts to load it, and processes the XML document with it
   *
   * @param SimpleXMLElement $doc
   * @return void
   */
  public static function vendor_parse($doc) {
    $vendor_parser = dbsteward::get_sql_format() . '_xml_parser';
    if (class_exists($vendor_parser)) {
      $vendor_parser::process($doc);
    }
  }

  public static function xml_composite_children(&$base, &$overlay, $file_name, &$addendum = NULL) {
    // do overlay includes first, to allow definer to overwrite included file values at the same level
    while (isset($overlay->includeFile)) {
      foreach ($overlay->includeFile AS $includeFile) {
        $include_file_name = (string)($includeFile['name']);
        // if include_file_name does not appear to be absolute, make it relative to its parent
        if (substr($include_file_name, 0, 1) != '/') {
          $include_file_name = dirname($file_name) . '/' . $include_file_name;
        }
        dbsteward::notice("Compositing XML includeFile " . $include_file_name);
        $include_doc = simplexml_load_file($include_file_name);
        if ($include_doc === FALSE) {
          throw new Exception("failed to simplexml_load_file() includeFile " . $include_file_name);
        }
        $include_doc = xml_parser::expand_tabrow_data($include_doc);
        $include_doc = xml_parser::sql_format_convert($include_doc);
        self::xml_composite_children($base, $include_doc, $include_file_name);
      }
      unset($overlay->includeFile);
      unset($base->includeFile);
    }

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
          dbsteward::debug("DEBUG: Add missing trigger: " . $child->asXML());
          $node = $base->addChild($tag_name, dbsteward::string_cast($child));
          $node->addAttribute('name', $child['name']);
        }
        else {
          $node = $nodes[0];
        }
      }
      // make sure there are no duplicate index names
      else if (strcasecmp('index', $tag_name) == 0) {
        $xpath = "index[name='" . $child['name'] . "']";
        $nodes = $base->xpath($xpath);
        if (count($nodes) > 1) {
          throw new exception("dupliate index name, more than one match for $xpath");
        }

        if (count($nodes) == 0) {
          $node = $base->addChild($tag_name, dbsteward::string_cast($child));
          $node->addAttribute('name', $child['name']);
        }
        else {
          $node = $nodes[0];
        }
      }
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
        if ($addendum !== NULL) {
          $rows = simplexml_load_string($child->asXML());
          self::xml_join($addendum, $rows);
        }
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
        // just replace the " with &quote; since we're embedding it, DOMDocument text nodes won't escape for
        // this specific purpose
        $nodes = $base->xpath('sql[. ="' . str_replace('"', '&quote;', $child) . '"]');
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
      // DBSteward API 1.3 change: match slonyNode, slonyReplicaSet, slonyReplicaSetNode by id attribute
      else if (strcasecmp($tag_name, 'slonyNode') == 0
            || strcasecmp($tag_name, 'slonyReplicaSet') == 0
            || strcasecmp($tag_name, 'slonyReplicaSetNode') == 0) {
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

      // if we're collecting addendums, make sure we add any schemas and tables to the addendum doc
      $node2 = NULL;
      if ($addendum !== NULL) {
        $tag = $node->getName();
        if (strcasecmp($node->getName(), 'schema') == 0 || strcasecmp($node->getName(), 'table') == 0) {
          $name = (string)$node['name'];
          $nodes = $addendum->xpath("{$tag}[@name='{$name}']");
          if (count($nodes) == 0) {
            $node2 = $addendum->addChild($node->getName());
            // for the addendum, we only need the name attribute
            $node2->addAttribute('name', $node['name']);
          }
          else if (count($nodes) == 1) {
            $node2 = $nodes[0];
          }
          else {
            throw new exception("More than one $tag by name $name!? Panic!");
          }
        }
      }

      // recurse if child has children
      if (count($child->children()) > 0) {
        self::xml_composite_children($node, $child, $file_name, $node2);
      }
    }

    // when compositing table definitions
    // columns, constraints, grants, etc added after initial table definition will be out of order and therefore not DTD valid
    // if the base was a table element, rebuild it's children in DTD-valid order
    if (strcasecmp($base->getName(), 'table') == 0) {
      static::file_sort_reappend_child($base, 'tablePartition');
      static::file_sort_reappend_child($base, 'tableOption');
      static::file_sort_reappend_child($base, 'column');
      static::file_sort_reappend_child($base, 'index');
      static::file_sort_reappend_child($base, 'constraint');
      static::file_sort_reappend_child($base, 'grant');
      static::file_sort_reappend_child($base, 'rows');
    }
    // as above, when compositing database put it in right order
    else if (strcasecmp($base->getName(), 'database') == 0) {
      static::file_sort_reappend_child($base, 'role');
      static::file_sort_reappend_child($base, 'slony');
      static::file_sort_reappend_child($base, 'configurationParameter');
    }
    // keep slony order in tact for DTD validation
    else if (strcasecmp($base->getName(), 'slony') == 0) {
      static::file_sort_reappend_child($base, 'slony');
      static::file_sort_reappend_child($base, 'slonyNode');
      static::file_sort_reappend_child($base, 'slonyReplicaSet');
      static::file_sort_reappend_child($base, 'slonyReplicaSetNode');
    }

    return TRUE;
  }

  // simple utility for recursive copying/joining of SimpleXMLNodes
  // see http://www.php.net/manual/en/class.simplexmlelement.php#102361
  private static function xml_join($root, $append) {
    if ($append) {
      if (strlen(trim((string) $append))==0) {
        $xml = $root->addChild($append->getName());
        foreach($append->children() as $child) {
          self::xml_join($xml, $child);
        }
      } else {
        $xml = $root->addChild($append->getName(), (string) $append);
      }
      foreach($append->attributes() as $n => $v) {
        $xml->addAttribute($n, $v);
      }
    }
  }

  /**
   * Overlay table rows from an overlay file onto a base table element
   * 
   * @param type $base_table          base table element to put overlay into
   * @param type $overlay_table_rows  overlay table rows element
   * @throws exception
   */
  public static function data_rows_overlay(&$base_table, &$overlay_table_rows) {
    $base_table_rows = & dbx::get_table_rows($base_table, TRUE, $overlay_table_rows['columns']);
    $base_table_rows_count = count($base_table_rows->row);

    // if the rows element columns attribute doesnt have a column that the overlay does
    if (strlen($base_table_rows['columns']) == 0) {
      throw new exception("base rows element missing columns attribute - unexpected");
    }
    if (strlen($overlay_table_rows['columns']) == 0) {
      throw new exception("overlay rows element missing columns attribute - unexpected");
    }
    $base_cols = preg_split("/[\,\s]+/", $base_table_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
    $overlay_cols = preg_split("/[\,\s]+/", $overlay_table_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);
    $cols_diff = array_diff($overlay_cols, $base_cols);
    // contains any values $overlay_cols does that $base_cols didnt, so add them
    foreach ($cols_diff AS $cols_diff_col) {
      // add the missing column, padding the base's row->col entries with empty col's to match the new size
      $base_cols[] = $cols_diff_col;
      for ($i = 0; $i < $base_table_rows_count; $i++) {
        // need to do it for each row entry, check for default for the column
        $node_col = $base_table_rows->row[$i]->addChild('col', self::column_default_value($base_table, $cols_diff_col, $node_col));
      }
    }
    // put the new columns list back in the node
    $base_table_rows['columns'] = implode(', ', $base_cols);

    // determine the "natural" ordering of primary key columns, so that we can deterministically create a primary key key
    $base_primary_keys = preg_split("/[\,\s]+/", $base_table['primaryKey'], -1, PREG_SPLIT_NO_EMPTY);
    $primary_key_index = self::data_row_overlay_primary_key_index($base_primary_keys, $base_cols, $overlay_cols);

    // primary key key => row index
    $base_pklookup = array();
    $i = 0;
    foreach ($base_table_rows->row as $base_row) {
      $s = '';
      foreach ($primary_key_index['base'] as $index) {
        $s .= ':'.$base_row->col[$index];
      }
      $base_pklookup[$s] = $i++;
    }

    // merge all row entries for the rows element
    $base_row_index = 0;
    foreach ($overlay_table_rows->row AS $overlay_row) {
      // sanity check the overlay's rows columns list against the col count of the row
      $overlay_row_count = count($overlay_row->col);
      if (count($overlay_cols) != $overlay_row_count) {
        dbsteward::error(count($overlay_cols) . " overlay columns != " . $overlay_row_count . " overlay elements");
        var_dump($overlay_cols);
        foreach ($overlay_row->col AS $olcol) {
          var_dump($olcol);
        }
        throw new exception("overlay_cols list count does not match overlay_row->col count");
      }

      // simple optimization:
      // if the node had no ->row's to start
      // don't try to match any of the children we are considering in this loop
      if ($base_table_rows_count == 0) {
        dbsteward::debug("DEBUG: skipping " . $base_table['name'] . " overlay -- no base table rows");
        $row_match = FALSE;
      }
      else {
        $s = '';
        foreach ($primary_key_index['overlay'] as $index) {
          $s .= ':'.$overlay_row->col[$index];
        }
        if (array_key_exists($s, $base_pklookup)) {
          $row_match = TRUE;
          $base_row_index = $base_pklookup[$s];
        }
        else {
          $row_match = FALSE;
        }
        // $row_match = self::data_row_overlay_key_search($base_table_rows, $overlay_row, $primary_key_index, $base_row_index);
      }

      if ($row_match) {
        // $base_row_index is set to $i in _match() when a match is found, so use it to overlay the matched row
        $node_row = $base_table_rows->row[$base_row_index];
      }
      else {
        // not found, add the row and empty col entries
        $node_row = $base_table_rows->addChild('row');
        foreach ($base_cols AS $base_col) {
          $node_col = $node_row->addChild('col');
          $node_col = self::column_default_value($base_table, $base_col, $node_col);
        }
        // then overlay the data in the overlay row
      }

      self::data_row_overlay_row($base_table, $node_row, $overlay_row, $base_cols, $overlay_cols);
    }
  }

  public static function data_row_overlay_primary_key_index($primary_key_cols, $base_cols, $overlay_cols) {
    // create a map to  find the numeric column index of each primary key
    // in the base and overlay column lists
    $primary_key_index = array('overlay' => array(), 'base' => array());

    foreach ($primary_key_cols AS $primary_key) {
      $base_idx = array_search($primary_key, $base_cols);
      if ($base_idx === FALSE) {
        throw new exception("base primary_key " . $primary_key . " not found in base_cols: " . implode(', ', $base_cols));
      }
      $primary_key_index['base'][$primary_key] = $base_idx;

      $overlay_idx = array_search($primary_key, $overlay_cols);
      if ($overlay_idx === FALSE) {
        throw new exception("overlay primary_key " . $primary_key . " not found in overlay_cols: " . implode(', ', $overlay_cols));
      }
      $primary_key_index['overlay'][$primary_key] = $overlay_idx;
    }

    asort($primary_key_index['base']);
    asort($primary_key_index['overlay']);

    return $primary_key_index;
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

  /**
   * Validate the passed XML string against the DBSteward DTD
   * 
   * @param type $xml
   * @param type $echo_status
   * @throws exception
   */
  public static function validate_xml($xml, $echo_status = TRUE) {
    // because of the various ways PEAR may be installed for or by the user
    // streamline DTD use out of the box by
    // creating a list of possible relative paths where the DTD can be found
    // @NOTICE: the FIRST path that is readable will be used
    $dtd_file_paths = array(
      // source working copy location
      __DIR__ . '/../DBSteward/dbsteward.dtd',
      // is it in a FreeBSD PEAR location ?
      $dtd_file = __DIR__ . '/../data/DBSteward/DBSteward/dbsteward.dtd',
      // is it in a Mac OS user's home pear/share/pear bear mare location ?
      $dtd_file = __DIR__ . '/../../data/DBSteward/DBSteward/dbsteward.dtd',
      $dtd_file = __DIR__ . '/../../../data/DBSteward/DBSteward/dbsteward.dtd'
    );
    foreach($dtd_file_paths AS $dtd_file_path) {
      if (is_readable($dtd_file_path)) {
        $dtd_file = $dtd_file_path;
      }
    }
    if (!is_readable($dtd_file)) {
      throw new exception("DTD file dbsteward.dtd not readable from any known source paths:\n" . implode("\n", $dtd_file_paths));
    }

    // attempt to verify that xmllint exists on the system
    if ($echo_status) {
      dbsteward::debug("Locating xmllint executable...");
    }

    // which will return 1 on failure to find executable, so dbsteward::cmd will throw an exception
    try {
      $whereIsCommand = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'where' : 'which';
      dbsteward::cmd("$whereIsCommand xmllint");
    } catch (Exception $ex) {
      throw new Exception("Could not locate xmllint executable. Please ensure it is in your PATH, or install libxml2 from http://xmlsoft.org");
    }

    if ($echo_status) {
      dbsteward::debug("Found xmllint");
    }

    // realpath the dtd_file so when it is announced it is simplified to remove relative directory pathing
    $dtd_file = realpath($dtd_file);
    if ($echo_status) {
      dbsteward::info("Validating XML (size = " . strlen($xml) . ") against $dtd_file");
    }
    $tmp_file = tempnam(sys_get_temp_dir(), 'dbsteward_validate_');
    if (file_put_contents($tmp_file, $xml) === FALSE) {
      throw new exception("Failed to write to temporary validation file: " . $tmp_file);
    }
    dbsteward::cmd("xmllint --noout --dtdvalid " . $dtd_file . " " . $tmp_file . " 2>&1");
    if ($echo_status) {
      dbsteward::info("XML Validates (size = " . strlen($xml) . ") against $dtd_file OK");
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

    // table ordering version 1
    // table dependency by demoting dependents to the bottom
    // with a cap for 3x total number of tables
    // if the dependency is solvable we should get it by the second pass
    /**/
    $table_list_count = count($table_list);
    $visited = array();
    for ($i = 0; $i < $table_list_count; $i++) {
      for ($j = $i + 1; $j < $table_list_count; $j++) {
        $visited_index = $table_list[$j]['schema']['name'].$table_list[$j]['table']['name'];
        // if i has a dependence on j, move i to the bottom of the list
        if ( self::table_has_dependency($table_list[$i], $table_list[$j]) ) {
          // if the table has been visited more than 3 times the number of tables, stop trying
          if ( isset($visited[$visited_index]) && $visited[$visited_index] > $table_list_count * 3 ) {
            dbsteward::warning("table " . $table_list[$j]['schema']['name'] . "." . $table_list[$j]['table']['name'] . " has been processed as a dependency more than 3 times as many total tables; skipping further reordering");
            break 1;
          }

          dbsteward::trace("table_dependency_order i = $i j = $j    i (" . $table_list[$i]['schema']['name'] . "." . $table_list[$i]['table']['name'] . ") has dependency on j (" . $table_list[$j]['schema']['name'] . "." . $table_list[$j]['table']['name'] . ")");

          // increment that i table dependency was visited
          if ( !isset($visited[$visited_index]) ) {
            $visited[$visited_index] = 0;
          }
          $visited[$visited_index] += 1;
          
          // pull i out
          $table = $table_list[$i];
          unset($table_list[$i]);
          // append i to end
          $table_list = array_merge($table_list, array($table));

          // check the $i index again, the array has been reformed
          $i--;
          break 1;
        }
      }
    }
    /**/

    // table ordering version 2
    // table dependency by recursing to find dependents and then adding all dependents to the end each unwind
    // nkiraly@: this method guarantees dependent order, but takes much longer to branch and unwind
    //self::table_dependency_sort($table_list);
    
    
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
        if (strlen($column['foreignSchema']) === 0 || strcasecmp($column['foreignSchema'], $b['schema']['name']) == 0) {
          // talking about the same table?
          if (strcasecmp($column['foreignTable'], $b['table']['name']) == 0) {
            // so yes, a has a dependency on b via column inline foreign key definition
            dbsteward::debug($a['schema']['name'] . '.' . $a['table']['name'] . '.' . $column['name'] . "\thas inline fkey dep on\t" . $b['schema']['name'] . '.' . $b['table']['name']);
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
              dbsteward::trace($a['schema']['name'] . '.' . $a['table']['name'] . '.' . $column['name'] . "\thas constraint dep on\t" . $b['schema']['name'] . '.' . $b['table']['name']);
              return TRUE;
            }
          }
        }
      }
    }

    // does table A inherit from table B? If so, obviously dependent on it
    if (isset($a['table']->attributes()->inheritsSchema) || isset($a['table']->attributes()->inheritsTable)) {
       if (strcasecmp($a['table']->attributes()->inheritsSchema, $b['schema']['name']) == 0 &&
           strcasecmp($a['table']->attributes()->inheritsTable, $b['table']['name']) == 0) {

           return TRUE;
       }
    }

    return FALSE;
  }

  public static $table_dependency_sort_depth = 0;
  public static function table_dependency_sort(&$table_list, $recursion_index = FALSE) {
    dbsteward::debug("DEPTH " . sprintf("%03d", self::$table_dependency_sort_depth) . "\t" . "ENTER table_dependency_sort()");
    self::$table_dependency_sort_depth++;
    for ($i = 0; $i < floor(count($table_list) / 2); $i++) {
      $append_list = array();

      for ($j = $i + 1; $j < count($table_list); $j++) {
        if ($recursion_index !== FALSE) {
          $j = $recursion_index;
        }
        // i depends on j ?
        if (self::table_has_dependency($table_list[$i], $table_list[$j])) {
          dbsteward::debug("DEPTH " . sprintf("%03d", self::$table_dependency_sort_depth) . "\t" . $table_list[$i]['schema']['name'] . "." . $table_list[$i]['table']['name'] . " " . $i . "\tDEPENDS ON\t" . $table_list[$j]['schema']['name'] . "." . $table_list[$j]['table']['name'] . " " . $j);
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
    self::$table_dependency_sort_depth--;
    dbsteward::debug("DEPTH " . sprintf("%03d", self::$table_dependency_sort_depth) . "\t" . "RETURN table_dependency_sort()");
  }

  /**
   * composite postgresql schema_to_xml() database_to_xml() data outputs onto a dbsteward database definition
   *
   * @param SimpleXMLElement $base          full definition element to add data to
   * @param SimpleXMLElement $pgdatafiles   postgres table data XML from database_to_xml() to overlay in
   *
   * @return void
   */
  public static function xml_composite_pgdata(&$base, $pgdatafiles) {
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

    foreach ($pgdatafiles AS $file) {
      $file_name = realpath($file);
      dbsteward::notice("Loading postgres data XML " . $file_name);
      $xml_contents = @file_get_contents($file_name);
      if ($xml_contents === FALSE) {
        throw new exception("Failed to load postgres data XML from disk: " . $file_name);
      }
      $doc = simplexml_load_string($xml_contents);
      if ($doc === FALSE) {
        throw new Exception("failed to simplexml_load_string() contents of " . $file_name);
      }

      dbsteward::info("Compositing postgres data (size=" . strlen($xml_contents) . ")");
      foreach ($doc AS $schema) {
        foreach ($schema AS $table) {
          $table_xpath = "schema[@name='" . $schema->getName() . "']/table[@name='" . $table->getName() . "']";
          $nodes = $base->xpath($table_xpath);
          if (count($nodes) != 1 || $nodes === FALSE) {
            var_dump($nodes);
            throw new exception("xpath did not yield one table match: " . $table_xpath . " - do the schema and table exist in your DBSteward XML?");
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
  public static function ampersand_magic($s) {
    return str_replace('&', '&amp;', (string)$s);
  }

  /**
   * Go up the parent chain until the default value is found
  */
  protected static function recurse_inheritance_get_column($table_node, $column_name) {
    $inherit_schema = $table_node->attributes()->inheritsSchema;
    $inherit_table = $table_node->attributes()->inheritsTable;
    $parent = $table_node->xpath('parent::*');
    if ((string)$parent[0]->attributes()->name == (string)$inherit_schema) {
      $nodes = $table_node->xpath('parent::*/table[@name="' . $inherit_table . '"]/column[@name="' . $column_name . '"]');
      if (empty($nodes)) {
        $grandparent = $table_node->xpath('parent::*/table[@name="' . $inherit_table . '"]');
        if (count($grandparent) == 1 && isset($grandparent[0]->attributes()->inheritsSchema)) {
          return static::recurse_inheritance_get_column($grandparent[0], $column_name);
        }
      }
      return $nodes;
    }
    return NULL;
  }

  /**
   * Gets the column present within a table, if it isn't present go up the inheritance
   * chain to find it.
   *
  */
  public static function inheritance_get_column($table_node, $column_name) {
    $xpath = 'column[@name="' . $column_name . '"]';
    $nodes = $table_node->xpath($xpath);
    if (count($nodes) != 1) {
      // if couldn't be found, check the inheritance chain and see if the column exists there
      if (isset($table_node->attributes()->inheritsSchema)) {
        $nodes = static::recurse_inheritance_get_column($table_node, $column_name);
      }
      else if (count($nodes) == 0) {
        throw new exception("Could not find column " . $column_name . " in table " . $table_node['name'] . " or in its parents -- panic!");
      }
    }

    return $nodes;
  }

  /**
   * return the column default value if it is defined
   *
   * @param SimpleXMLNode  $table_name   table definition xml node
   * @param string         $column_name  name of column to look up default value
   *
   * @return string column default value, null if not defined for the $column_name
   */
  public static function column_default_value(&$table_node, $column_name, &$node) {
    // find the column node in the table
    $nodes = static::inheritance_get_column($table_node, $column_name);
    if (is_null($nodes) || count($nodes) != 1) {
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
        dbsteward::trace('column_default_value ' . $table_node['name'] . '.' . $column_node['name'] . ' default null');
        $node['null'] = 'true';
      }
      else {
        dbsteward::trace('column_default_value ' . $table_node['name'] . '.' . $column_node['name'] . ' default value ' . $column_node['default']);
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
  public static function file_sort($file_name, $sorted_file_name) {
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
        // <!ELEMENT table (column+, index*, constraint*, grant*, rows?)>
        // if column defs were not all first, they will be after this!
        self::file_sort_reappend_child($table, 'index');
        self::file_sort_reappend_child($table, 'constraint');
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
  protected static function file_sort_children(&$node, $child_name, $child_id_attribute, $child_id_map = NULL) {
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
      $child_node_node = simplexml_load_string($child_node_xml[$child_id]);
      self::file_sort_children_node_merge($new_child, $child_node_node);
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
  protected static function file_sort_reappend_child(&$node, $child_name) {
    if (isset($node->{$child_name})) {
      $child_nodes = array();
      foreach ($node->{$child_name} AS $child) {
        $child_nodes[] = $child->asXML();
      }
      unset($node->{$child_name});
      foreach ($child_nodes AS $child_node) {
        $new_child = $node->addChild($child_name);
        $child_node_node = simplexml_load_string($child_node);
        self::file_sort_children_node_merge($new_child, $child_node_node);
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
  protected static function file_sort_prepend_child(&$node, $child_name, $child_id_attrib, $child_id_attrib_values) {
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
        $child_node_node = simplexml_load_string($child_node);
        self::file_sort_children_node_merge($new_child, $child_node_node);
      }

      // add the rest
      foreach ($child_nodes AS $child_node) {
        $new_child = $node->addChild($child_name);
        $child_node_node = simplexml_load_string($child_node);
        self::file_sort_children_node_merge($new_child, $child_node_node);
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
  protected static function file_sort_children_node_merge(&$to_node, &$from_node, $copy_attributes = TRUE) {
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

  public static function save_doc($file_name, $doc) {
    return self::save_xml($file_name, $doc->saveXML());
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
        if (strcasecmp(dbsteward::get_sql_format(), 'mysql5') == 0) {
          // MySQL doesn't have a "public" role, and will attempt to create the user "PUBLIC"
          // instead, warn and alias to ROLE_APPLICATION
          $role = dbsteward::string_cast($db_doc->database->role->application);
          dbsteward::warning("Warning: MySQL doesn't support the PUBLIC role, using ROLE_APPLICATION ('$role') instead.");
        }
        else {
          $role = 'PUBLIC';
        }
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
            $owner = $db_doc->database->role->owner;
            dbsteward::warning("Warning: Ignoring custom roles. Role '$role' is being overridden by ROLE_OWNER ('$owner').");
            return $owner;
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
          $delimiter = "\t";
          if (!empty($table->rows['tabrowDelimiter'])) {
            $delimiter = (string)$table->rows['tabrowDelimiter'];
            if ($delimiter == '\t') {
              $delimiter = "\t";
            }
            elseif ($delimiter == '\n') {
              $delimiter = "\n";
            }
            unset($table->rows['tabrowDelimiter']);
          }

          if (isset($table->rows->tabrow)) {
            foreach ($table->rows->tabrow AS $tabrow) {
              $row = $table->rows->addChild('row');
              $cols = explode($delimiter, $tabrow);
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
    // @TODO: apply mssql10_type_convert to function parameters/returns as well. see below mysql5 impl
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
    // mysql5 format conversions
    elseif (strcasecmp(dbsteward::get_sql_format(), 'mysql5') == 0) {
      foreach ($doc->schema as $schema) {
        foreach ($schema->table as $table) {
          foreach ($table->column as $column) {
            if (isset($column['type'])) {
              list($column['type'], $d) = self::mysql5_type_convert($column['type'], $column['default']);
              if (isset($column['default'])) {
                $column['default'] = $d;
              }
            }
          }
        }

        foreach ($schema->function as $function) {
          list($function['returns'], $_) = self::mysql5_type_convert($function['returns']);

          foreach ($function->functionParameter as $param) {
            list($param['type'], $_) = self::mysql5_type_convert($param['type']);
          }
        }
      }
    }

    return $doc;
  }

  /** Convert from arbitrary type notations to mysql5 specific type representations */
  protected static function mysql5_type_convert($type, $value = null) {
    if ($is_ai = mysql5_column::is_auto_increment($type)) {
      $type = mysql5_column::un_auto_increment($type);
    }

    // when used in an index, varchars can only have a max of 3500 bytes
    // so when converting types, we don't know if it might be in an index,
    // so we play it safe

    if (substr($type, -2) == '[]') {
      $type = 'varchar(3500)';
    }

    switch (strtolower($type)) {
      case 'bool':
      case 'boolean':
        $type = 'tinyint(1)';
        if ($value) {
          switch (strtolower($value)) {
            case "'t'":
            case 'true':
            case '1':
              $value = '1';
              break;
            case "'f'":
            case 'false':
            case '0':
              $value = '0';
              break;
            default:
              throw new Exception("Unknown column type boolean default {$value}");
              break;
          }
        }
        break; // boolean
      case 'inet':
        $type = 'varchar(16)';
        break;
      case 'int':
      case 'integer':
        $type = 'int(11)';
        break;
      case 'interval':
        $type = 'varchar(3500)';
        break;
      case 'character varying':
      case 'varchar':
        $type = 'varchar(3500)';
        break;

      // mysql's timezone support is attrocious.
      // see: http://dev.mysql.com/doc/refman/5.5/en/datetime.html
      case 'timestamp without timezone':
      case 'timestamp with timezone':
      case 'timestamp without time zone':
      case 'timestamp with time zone':
        $type = 'timestamp';
        break;
      case 'time with timezone':
      case 'time with time zone':
        $type = 'time';
        break;
      case 'serial':
      case 'bigserial':
        // emulated with triggers and sequences later on in the process
        // mysql5 interprets the 'serial' type as "BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE"
        // which is dumb compared to the emulation with triggers/sequences to act more like pgsql's
        break;
      case 'uuid':
        // 8 digits, 3 x 4 digits, 12 digits = 32 digits + 4 hyphens = 36 chars
        $type = 'varchar(40)';
        break;
    }

    // character varying(N) => varchar(N)
    // $type = preg_replace('/character varying\((.+)\)/i','varchar($1)',$type);

    // mysql doesn't understand epoch
    if (isset($value) && strcasecmp($value, "'epoch'") == 0) {
      // 00:00:00 is reserved for the "zero" value of a timestamp field. 01 is the closest we can get.
      $value = "'1970-01-01 00:00:01'";
    }

    if ($is_ai) {
      $type = (string)$type . " AUTO_INCREMENT";
    }

    return array($type, $value);
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
  
  /**
   * Insert any missing slonyId and slonySetId as specifeid by dbsteward mode variables
   * 
   * @param SimpleXML $indoc
   * @return SimpleXML
   */
  public static function slonyid_number($indoc) {
    $outdoc = clone $indoc;
    
    // start with --slonyidsetvalue=
    $slony_set_id = dbsteward::$slonyid_set_value;
    // start slony ID at --slonyidstartvalue=
    $slony_id = dbsteward::$slonyid_start_value;
    
    foreach ($outdoc->schema AS $schema) {
      xml_parser::slonyid_number_set($schema, $slony_set_id);

      foreach ($schema->table AS $table) {
        xml_parser::slonyid_number_set($table, $slony_set_id);
        xml_parser::slonyid_number_id($table, $slony_id);

        foreach ($table->column as $column) {
          // make sure any serial columns have slonySetId
          if (sql99_column::is_serial($column['type'])) {
            xml_parser::slonyid_number_set($column, $slony_set_id);
            xml_parser::slonyid_number_id($column, $slony_id);
          }
        }
      }

      foreach ($schema->trigger AS $trigger) {
        xml_parser::slonyid_number_set($trigger, $slony_set_id);
      }

      foreach ($schema->sequence AS $sequence) {
        xml_parser::slonyid_number_set($sequence, $slony_set_id);
        xml_parser::slonyid_number_id($sequence, $slony_id);
      }

      foreach ($schema->function AS $function) {
        xml_parser::slonyid_number_set($function, $slony_set_id);
      }

      foreach ($schema->sql AS $sql) {
        xml_parser::slonyid_number_set($sql, $slony_set_id);
      }
    }

    return $outdoc;
  }
  
  protected static function slonyid_number_set(&$element, &$id) {
    // if slony set IDs are required
    if (dbsteward::$require_slony_set_id) {
      // if slonySetId is not specified
      if (!isset($element['slonySetId'])) {
        // set unspecified slonySetIds to $id
        $element['slonySetId'] = $id;
      }
    }
  }

  protected static function slonyid_number_id(&$element, &$id) {
    // if slony IDs are required
    if (dbsteward::$require_slony_id) {
      // if slonyId is not specified
      if (!isset($element['slonyId'])) {
        // set unspecified slonyIds to $id
        $element['slonyId'] = $id;
        // and increment it
        $id++;
      }
    }
  }

}
