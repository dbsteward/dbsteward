<?php

require_once __DIR__ . '/h2.php';
require_once __DIR__ . '/../sql99/sql99_index.php';

class h2_index extends sql99_index
{

    public static function get_table_index($node_schema, $node_table, $name)
    {
        $indexes = self::get_table_indexes($node_schema, $node_table);
        $return_index = NULL;
        foreach ($indexes AS $index) {
            if (strcasecmp($index['name'], $name) == 0) {
                if ($return_index === NULL) {
                    $return_index = $index;
                } else {
                    throw new exception("more than one table " . $node_schema['name'] . '.' . $node_table['name'] . " index called " . $name . " found");
                }
            }
        }
        return $return_index;
    }

    public static function get_table_indexes($node_schema, $node_table)
    {
        $nodes = $node_table->xpath("index");

        // add column unique indexes and column fkeys to the list
        foreach ($node_table->column AS $column) {
            if (isset($column['unique']) && strcasecmp($column['unique'], 'true') == 0) {
                $unique_index = new SimpleXMLElement('<index/>');
                // For MySQL unique indexes, this is as simple as the column name: http://dev.mysql.com/doc/refman/5.5/en/create-table.html (third of the way down the page)
                // duplicate index names get a suffix: _2, _3, _4
                $unique_index['name'] = static::get_index_name($column['name'], $nodes);
                $unique_index['unique'] = 'true';
                $unique_index['using'] = 'btree';
                $unique_index->addChild('indexDimension', $column['name'])
                    ->addAttribute('name', $column['name'] . '_unq');
                $nodes[] = $unique_index;
            } elseif (isset($column['foreignColumn'])) {
                // mysql automatically creates indexes for foreign keys if there isn't one already,
                // so we need to create one as well to avoid invalid diffs

                // first, see if there's already an index of JUST this column
                $found = (string)$node_table['primaryKey'] == (string)$column['name'];
                if (!$found) {
                    foreach ($nodes as $node) {
                        if ((string)$node->indexDimension[0] == (string)$column['name']) {
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found) {
                    // no? then create one
                    $fkey_index = new SimpleXMLElement('<index/>');
                    $fkey_index['name'] = (string)$column['foreignIndexName']
                        ?: (string)$column['foreignKeyName']
                            ?: static::get_index_name((string)$column['name'], $nodes);
                    $fkey_index['unique'] = 'false';
                    $fkey_index['using'] = 'btree';
                    $fkey_index->addChild('indexDimension', (string)$column['name'])
                        ->addAttribute('name', $column['name'] . '_1');
                    $nodes[] = $fkey_index;
                }
            }
        }

        // Add explicit foreignKeys to the list
        foreach ($node_table->foreignKey as $fkey) {
            $cols = preg_split('/[\s,]+/', $fkey['columns'], -1, PREG_SPLIT_NO_EMPTY);

            $fkey_index = new SimpleXMLElement('<index/>');
            $fkey_index['name'] = (string)$fkey['indexName'] ?: (string)$fkey['constraintName'] ?: static::get_index_name(implode('_', $cols), $nodes);
            $fkey_index['unique'] = 'false';
            $fkey_index['using'] = 'btree';

            foreach ($cols as $i => $col) {
                $fkey_index->addChild('indexDimension', $col)
                    ->addAttribute('name', $col . '_' . ($i + 1));
            }

            $nodes[] = $fkey_index;
        }

        $names = array();
        foreach ($nodes as $node) {
            if (in_array((string)$node['name'], $names)) {
                throw new Exception("Duplicate index name {$node['name']} on table {$node_schema['name']}.{$node_table['name']}");
            } else {
                $names[] = (string)$node['name'];
            }
        }
        return $nodes;
    }

    protected static function get_index_name($column, $index_nodes)
    {
        $need_suffix = false;
        $suffix = 0;

        foreach ($index_nodes as $index_node) {
            if (preg_match('/^' . preg_quote($column) . '(?:_(\d+))?$/i', $index_node['name'], $matches) > 0) {
                if (isset($matches[1])) {
                    $suffix = max($suffix, $matches[1] + 1);
                } else {
                    $need_suffix = true;
                    $suffix = max($suffix, 2);
                }
            }
        }

        if ($need_suffix) {
            return $column . '_' . $suffix;
        } else {
            return $column;
        }
    }

    public static function get_creation_sql($node_schema, $node_table, $node_index)
    {
        $index_note = "-- note that MySQL does not support indexed expressions or named dimensions\n";
        $sql = "CREATE ";

        if (isset($node_index['unique']) && strcasecmp($node_index['unique'], 'true') == 0) {
            $sql .= "UNIQUE ";
        }

        $sql .= "INDEX "
            . h2::get_quoted_object_name($node_index['name'])
            . " ON "
            . h2::get_fully_qualified_table_name($node_schema['name'], $node_table['name']);

        $dimensions = static::get_dimension_list($node_schema, $node_table, $node_index);
        $sql .= ' (' . implode(', ', $dimensions) . ')';

        if (!empty($node_index['using'])) {
            $sql .= ' USING ' . static::get_using_option_sql($node_index['using']);
        }

        //@TODO: h2 partial indexes with indexWhere - see pgsql8_index

        return $index_note . $sql . ';';
    }

    public static function get_drop_sql($node_schema, $node_table, $node_index)
    {
        return "DROP INDEX " . h2::get_quoted_object_name($node_index['name']) . " ON " . h2::get_fully_qualified_table_name($node_schema['name'], $node_table['name']) . ";";
    }

    public static function get_alter_add_sql($node_schema, $node_table, $node_index)
    {
        $dimensions = static::get_dimension_list($node_schema, $node_table, $node_index);
        $unique = '';
        if (isset($node_index['unique']) && strcasecmp($node_index['unique'], 'true') == 0) {
            $unique = 'UNIQUE ';
        }

        $using = '';
        if (!empty($node_index['using'])) {
            $using = ' USING ' . static::get_using_option_sql($node_index['using']);
        }

        $ind = str_replace("`", "", h2::get_quoted_object_name($node_index['name']));
        return 'ADD ' . $unique . 'INDEX ' . $node_table['name'] . "_" . $ind . ' (' . implode(', ', $dimensions) . ')' . $using;
    }

    /**
     * Get drop SQL suitable for use in an ALTER TABLE statement
     * @param  SimpleXMLElement $node_schema
     * @param  SimpleXMLElement $node_table
     * @param  SimpleXMLElement $node_index
     * @return string
     */
    public static function get_alter_drop_sql($node_schema, $node_table, $node_index)
    {
        return 'DROP INDEX ' . h2::get_quoted_object_name($node_index['name']);
    }

    protected static function get_dimension_list($node_schema, $node_table, $node_index)
    {
        $dimensions = array();
        foreach ($node_index->indexDimension as $dimension) {
            // mysql only supports indexed columns, not indexed expressions like in pgsql or mssql
            if (!h2_table::contains_column($node_table, $dimension)) {
                throw new Exception("Table " . h2::get_fully_qualified_table_name($node_schema['name'], $node_table['name']) . " does not contain column '$dimension'");
            }

            $dimensions[] = h2::get_quoted_column_name($dimension);
        }
        return $dimensions;
    }

    public static function get_using_option_sql($using)
    {
        $using = strtoupper((string)$using);

        switch ($using) {
            case 'HASH':
            case 'BTREE':
                return $using;
                break;

            default:
                dbsteward::warning("MySQL does not support the $using index type, defaulting to BTREE");
                return 'BTREE';
                break;
        }
    }
}

?>
