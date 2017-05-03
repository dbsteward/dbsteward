<?php

class h2_view extends sql99_view {
  public static function get_creation_sql($db_doc, $node_schema, $node_view) {
    if ( isset($node_view['description']) && strlen($node_view['description']) > 0 ) {
      $ddl = "-- {$node_view['description']}\n";
    }

    $view_name = h2::get_fully_qualified_table_name($node_schema['name'],$node_view['name']);

    $definer = (strlen($node_view['owner']) > 0) ? xml_parser::role_enum($db_doc, $node_view['owner']) : 'CURRENT_USER';

    $ddl = "CREATE OR REPLACE DEFINER = $definer SQL SECURITY DEFINER VIEW $view_name\n";
    $ddl.= "  AS " . static::get_view_query($node_view) . ";";

    return $ddl;
  }

  public static function get_drop_sql($node_schema, $node_view) {
    return "DROP VIEW IF EXISTS " . h2::get_fully_qualified_table_name($node_schema['name'], $node_view['name']) . ";";
  }
}
?>
