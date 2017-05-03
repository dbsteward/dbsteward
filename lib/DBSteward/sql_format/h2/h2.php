<?php

class h2 extends sql99
{
    const QUOTE_CHAR = '`';
    public static $quote_function_parameters = TRUE;
    public static $swap_function_delimiters = TRUE;
    public static $use_auto_increment_table_options = FALSE;
    public static $use_schema_name_prefix = FALSE;

    public static function get_identifier_blacklist_file()
    {
        return __DIR__ . '/h2_identifier_blacklist.txt';
    }

    public static function build($output_prefix, $db_doc)
    {
        if (strlen($output_prefix) == 0) {
            throw new exception("h2::build() sanity failure: output_prefix is blank");
        }
        // build full db creation script
        $build_file = $output_prefix . '_build.sql';
        dbsteward::notice("Building complete file " . $build_file);
        $build_file_fp = fopen($build_file, 'w');
        if ($build_file_fp === FALSE) {
            throw new exception("failed to open full file " . $build_file . ' for output');
        }
        $build_file_ofs = new output_file_segmenter($build_file, 1, $build_file_fp, $build_file);
        if (count(dbsteward::$limit_to_tables) == 0) {
            $build_file_ofs->write("-- full database definition file generated " . date('r') . "\n");
        }

        // $build_file_ofs->write("START TRANSACTION;\n\n");

        dbsteward::info("Calculating table foreign key dependency order..");
        $table_dependency = xml_parser::table_dependency_order($db_doc);
        // database-specific implementation refers to dbsteward::$new_database when looking up roles/values/conflicts etc
        dbsteward::$new_database = $db_doc;

        // language defintions
        if (dbsteward::$create_languages) {
            foreach ($db_doc->language AS $language) {
                dbsteward::warning("Ignoring language {$language['name']} declaration because H2 does not support languages other than 'sql'");
            }
        }

        if (dbsteward::$only_schema_sql
            || !dbsteward::$only_data_sql
        ) {
            dbsteward::info("Defining structure");
            h2::build_schema($db_doc, $build_file_ofs, $table_dependency);
        }

        if (!dbsteward::$only_schema_sql
            || dbsteward::$only_data_sql
        ) {
            dbsteward::info("Defining data inserts");
            h2::build_data($db_doc, $build_file_ofs, $table_dependency);
        }
        dbsteward::$new_database = NULL;

        // $build_file_ofs->write("COMMIT TRANSACTION;\n\n");

        return $db_doc;
    }

    public static function build_schema($db_doc, $ofs, $table_depends)
    {
        // schema creation
        if (static::$use_schema_name_prefix) {
            dbsteward::info("H2 schema name prefixing mode turned on");
        } else {
            if (count($db_doc->schema) > 1) {
                throw new Exception("You cannot use more than one schema in mysql5 without schema name prefixing\nPass the --useschemaprefix flag to turn this on");
            }
        }

        foreach ($db_doc->schema as $schema) {
            // database grants
            foreach ($schema->grant AS $grant) {
                $ofs->write(h2_permission::get_permission_sql($db_doc, $schema, $schema, $grant) . "\n");
            }

            // enums
            foreach ($schema->type AS $type) {
                $ofs->write(h2_type::get_creation_sql($schema, $type) . "\n");
            }

            // function definitions
            foreach ($schema->function AS $function) {
                if (h2_function::has_definition($function)) {
                    $ofs->write(h2_function::get_creation_sql($schema, $function) . "\n\n");
                }
                // function grants
                foreach ($function->grant AS $grant) {
                    $ofs->write(h2_permission::get_permission_sql($db_doc, $schema, $function, $grant) . "\n");
                }
            }

            $sequences = array();
            $triggers = array();

            // create defined tables
            foreach ($schema->table AS $table) {
                // get sequences and triggers needed to make this table work
                $sequences = array_merge($sequences, h2_table::get_sequences_needed($schema, $table));
                $triggers = array_merge($triggers, h2_table::get_triggers_needed($schema, $table));

                // table definition
                $ofs->write(h2_table::get_creation_sql($schema, $table) . "\n\n");

                // table indexes
                // mysql5_diff_indexes::diff_indexes_table($ofs, NULL, NULL, $schema, $table);

                // table grants
                if (isset($table->grant)) {
                    foreach ($table->grant AS $grant) {
                        $ofs->write(h2_permission::get_permission_sql($db_doc, $schema, $table, $grant) . "\n");
                    }
                }

                $ofs->write("\n");
            }

            // sequences contained in the schema + sequences used by serials
            $sequences = array_merge($sequences, dbx::to_array($schema->sequence));
            if (count($sequences) > 0) {
                $ofs->write(h2_sequence::get_shim_creation_sql() . "\n\n");
                $ofs->write(h2_sequence::get_creation_sql($schema, $sequences) . "\n\n");

                // sequence grants
                foreach ($sequences as $sequence) {
                    foreach ($sequence->grant AS $grant) {
                        $ofs->write("-- grant for the {$sequence['name']} sequence applies to ALL sequences\n");
                        $ofs->write(h2_permission::get_permission_sql($db_doc, $schema, $sequence, $grant) . "\n");
                    }
                }
            }

            // trigger definitions + triggers used by serials
            $triggers = array_merge($triggers, dbx::to_array($schema->trigger));
            $unique_triggers = array();
            foreach ($triggers AS $trigger) {
                // only do triggers set to the current sql format
                if (strcasecmp($trigger['sqlFormat'], dbsteward::get_sql_format()) == 0) {
                    // check that this table/timing/event combo hasn't been defined, because MySQL only
                    // allows one trigger per table per BEFORE/AFTER per action
                    $unique_name = "{$trigger['table']}-{$trigger['when']}-{$trigger['event']}";
                    if (array_key_exists($unique_name, $unique_triggers)) {
                        throw new Exception("MySQL will not allow trigger {$trigger['name']} to be created because it happens on the same table/timing/event as trigger {$unique_triggers[$unique_name]}");
                    }

                    $unique_triggers[$unique_name] = $trigger['name'];
                    $ofs->write(h2_trigger::get_creation_sql($schema, $trigger) . "\n");
                }
            }
        }

        foreach ($db_doc->schema as $schema) {
            // define table primary keys before foreign keys so unique requirements are always met for FOREIGN KEY constraints
            foreach ($schema->table AS $table) {
                h2_diff_constraints::diff_constraints_table($ofs, NULL, NULL, $schema, $table, 'primaryKey', FALSE);
            }
            $ofs->write("\n");
        }

        // foreign key references
        // use the dependency order to specify foreign keys in an order that will satisfy nested foreign keys and etc
        for ($i = 0; $i < count($table_depends); $i++) {
            $dep_schema = $table_depends[$i]['schema'];
            $table = $table_depends[$i]['table'];
            if ($table['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME) {
                // don't do anything with this table, it is a magic internal DBSteward value
                continue;
            }
            h2_diff_constraints::diff_constraints_table($ofs, NULL, NULL, $dep_schema, $table, 'constraint', FALSE);
        }

        $ofs->write("\n");

        h2_diff_views::create_views_ordered($ofs, null, $db_doc);

        // view permission grants
        foreach ($db_doc->schema as $schema) {
            foreach ($schema->view AS $view) {
                if (isset($view->grant)) {
                    foreach ($view->grant AS $grant) {
                        $ofs->write(h2_permission::get_permission_sql($db_doc, $schema, $view, $grant) . "\n");
                    }
                }
            }
        }

        // @TODO: database configurationParameter support
    }

    public static function build_data($db_doc, $ofs, $tables)
    {
        // use the dependency order to then write out the actual data inserts into the data sql file
        $limit_to_tables_count = count(dbsteward::$limit_to_tables);
        foreach ($tables as $dep_table) {
            $schema = $dep_table['schema'];
            $table = $dep_table['table'];
            if ($table['name'] === dbsteward::TABLE_DEPENDENCY_IGNORABLE_NAME) {
                // don't do anything with this table, it is a magic internal DBSteward value
                continue;
            }

            if ($limit_to_tables_count > 0) {
                if (in_array($schema['name'], array_keys(dbsteward::$limit_to_tables))) {
                    if (in_array($table['name'], dbsteward::$limit_to_tables[(string)($schema['name'])])) {
                        // table is to be included
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }
            }

            $ofs->write(h2_diff_tables::get_data_sql(NULL, NULL, $schema, $table, FALSE));


            $table_primary_keys = h2_table::primary_key_columns($table);
            $table_column_names = dbx::to_array($table->column, 'name');
            $node_rows =& dbx::get_table_rows($table); // the <rows> element
            $data_column_names = preg_split("/,|\s/", $node_rows['columns'], -1, PREG_SPLIT_NO_EMPTY);

            // set serial primary keys to the max value after inserts have been performed
            // only if the PRIMARY KEY is not a multi column


            if (count($table_primary_keys) == 1 && in_array($table_primary_keys[0], $data_column_names)) {
                $pk_column_name = $table_primary_keys[0];
                $node_pk_column = dbx::get_table_column($table, $pk_column_name);

                if ($node_pk_column == NULL) {
                    throw new exception("Failed to find primary key column '" . $pk_column_name . "' for " . $schema['name'] . "." . $table['name']);
                }

                // only set the pkey to MAX() if the primary key column is also a serial/bigserial and if serialStart is not defined
                if (h2_column::is_serial($node_pk_column['type']) && !isset($node_pk_column['serialStart'])) {
                    $fqtn = h2::get_fully_qualified_table_name($schema['name'], $table['name']);
                    $qcol = h2::get_quoted_column_name($pk_column_name);
                    $setval = h2_sequence::get_setval_call(h2_column::get_serial_sequence_name($schema, $table, $node_pk_column), "MAX($qcol)", "TRUE");
                    $sql = "SELECT $setval FROM $fqtn;\n";
                    $ofs->write($sql);
                }
            }

            // unlike the pg class, we cannot just set identity column start values here with setval without inserting a row
            // check if primary key is a column of this table - FS#17481
            if (count(array_diff($table_primary_keys, $table_column_names)) != 0) {
                throw new exception('Primary key ' . $table['primaryKey'] . ' does not exist as a column in table ' . $table['name']);
            }
        }

        // include all of the unstaged sql elements
        dbx::build_staged_sql($db_doc, $ofs, NULL);
        $ofs->write("\n");
    }

    public static function build_upgrade($old_output_prefix, $old_composite_file, $old_db_doc, $old_files, $new_output_prefix, $new_composite_file, $new_db_doc, $new_files)
    {
        // place the upgrade files with the new_files set
        $upgrade_prefix = $new_output_prefix . '_upgrade';

        // mysql5_diff needs these to intelligently create SQL difference statements in dependency order
        dbsteward::info("Calculating old table foreign key dependency order..");
        h2_diff::$old_table_dependency = xml_parser::table_dependency_order($old_db_doc);
        dbsteward::info("Calculating new table foreign key dependency order..");
        h2_diff::$new_table_dependency = xml_parser::table_dependency_order($new_db_doc);

        h2_diff::diff_doc($old_composite_file, $new_composite_file, $old_db_doc, $new_db_doc, $upgrade_prefix);

        return $new_db_doc;
    }

    public static function extract_schema($host, $port, $database, $user, $password)
    {
        $databases = explode(',', $database);

        dbsteward::notice("Connecting to mysql5 host " . $host . ':' . $port . ' database ' . $database . ' as ' . $user);
        // if not supplied, ask for the password
        if ($password === FALSE) {
            echo "Password: ";
            $password = fgets(STDIN);
        }

        $db = h2_db::connect($host, $port, $user, $password);

        $doc = new SimpleXMLElement('<dbsteward></dbsteward>');
        // set the document to contain the passed db host, name, etc to meet the DTD and for reference
        $node_database = $doc->addChild('database');
        $node_database->addChild('sqlformat', 'h2');
        $node_role = $node_database->addChild('role');
        $node_role->addChild('application', $user);
        $node_role->addChild('owner', $user);
        $node_role->addChild('replication', $user);
        $node_role->addChild('readonly', $user);

        foreach ($databases as $database) {
            dbsteward::info("Analyzing database $database");
            $db->use_database($database);

            $node_schema = $doc->addChild('schema');
            $node_schema['name'] = $database;
            $node_schema['owner'] = 'ROLE_OWNER';

            // extract global and schema permissions under the public schema
            foreach ($db->get_global_grants($user) as $db_grant) {
                $node_grant = $node_schema->addChild('grant');
                // There are 28 permissions encompassed by the GRANT ALL statement
                $node_grant['operation'] = $db_grant->num_ops == 28 ? 'ALL' : $db_grant->operations;
                $node_grant['role'] = self::translate_role_name($user, $doc);

                if ($db_grant->is_grantable) {
                    $node_grant['with'] = 'GRANT';
                }
            }

            $enum_types = array();
            $enum_type = function ($obj, $mem, $values) use (&$enum_types) {
                // if that set of values is defined by a previous enum, use that
                foreach ($enum_types as $name => $enum) {
                    if ($enum === $values) {
                        return $name;
                    }
                }

                // otherwise, make a new one
                $name = "enum_" . md5(implode('_', $values));
                $enum_types[$name] = $values;

                return $name;
            };
            foreach ($db->get_tables() as $db_table) {
                dbsteward::info("Analyze table options/partitions " . $db_table->table_name);
                $node_table = $node_schema->addChild('table');
                $node_table['name'] = $db_table->table_name;
                $node_table['owner'] = 'ROLE_OWNER'; // because mysql doesn't have object owners
                $node_table['description'] = $db_table->table_comment;
                $node_table['primaryKey'] = '';

                if (stripos($db_table->create_options, 'partitioned') !== FALSE &&
                    ($partition_info = $db->get_partition_info($db_table))
                ) {

                    $node_partition = $node_table->addChild('tablePartition');
                    $node_partition['sqlFormat'] = 'h2';
                    $node_partition['type'] = $partition_info->type;
                    switch ($partition_info->type) {
                        case 'HASH':
                        case 'LINEAR HASH':
                            $opt = $node_partition->addChild('tablePartitionOption');
                            $opt->addAttribute('name', 'expression');
                            $opt->addAttribute('value', $partition_info->expression);

                            $opt = $node_partition->addChild('tablePartitionOption');
                            $opt->addAttribute('name', 'number');
                            $opt->addAttribute('value', $partition_info->number);
                            break;

                        case 'KEY':
                        case 'LINEAR KEY':
                            $opt = $node_partition->addChild('tablePartitionOption');
                            $opt->addAttribute('name', 'columns');
                            $opt->addAttribute('value', $partition_info->columns);

                            $opt = $node_partition->addChild('tablePartitionOption');
                            $opt->addAttribute('name', 'number');
                            $opt->addAttribute('value', $partition_info->number);
                            break;

                        case 'LIST':
                        case 'RANGE':
                        case 'RANGE COLUMNS':
                            $opt = $node_partition->addChild('tablePartitionOption');
                            $opt->addAttribute('name', $partition_info->type == 'RANGE COLUMNS' ? 'columns' : 'expression');
                            $opt->addAttribute('value', $partition_info->expression);

                            foreach ($partition_info->segments as $segment) {
                                $node_seg = $node_partition->addChild('tablePartitionSegment');
                                $node_seg->addAttribute('name', $segment->name);
                                $node_seg->addAttribute('value', $segment->value);
                            }
                            break;
                    }
                }

                foreach ($db->get_table_options($db_table) as $name => $value) {
                    if (strcasecmp($name, 'auto_increment') === 0 && !static::$use_auto_increment_table_options) {
                        // don't extract auto_increment tableOptions if we're not using them
                        continue;
                    }
                    $node_option = $node_table->addChild('tableOption');
                    $node_option['sqlFormat'] = 'h2';
                    $node_option['name'] = $name;
                    $node_option['value'] = $value;
                }

                dbsteward::info("Analyze table columns " . $db_table->table_name);
                foreach ($db->get_columns($db_table) as $db_column) {
                    $node_column = $node_table->addChild('column');
                    $node_column['name'] = $db_column->column_name;

                    if (!empty($db_column->column_comment)) {
                        $node_column['description'] = $db_column->column_comment;
                    }

                    // returns FALSE if not serial, int/bigint if it is
                    $type = $db->is_serial_column($db_table, $db_column);
                    if (!$type) {
                        $type = $db_column->column_type;

                        if (stripos($type, 'enum') === 0) {
                            $values = $db->parse_enum_values($db_column->column_type);
                            $type = $enum_type($db_table->table_name, $db_column->column_name, $values);
                        }

                        if ($db_column->is_auto_increment) {
                            $type .= ' AUTO_INCREMENT';
                        }
                    }

                    if ($db_column->is_auto_update) {
                        $type .= ' ON UPDATE CURRENT_TIMESTAMP';
                    }

                    $node_column['type'] = $type;

                    // @TODO: if there are serial sequences/triggers for the column then convert to serial

                    if ($db_column->column_default !== NULL) {
                        $node_column['default'] = h2::escape_default_value($db_column->column_default);
                    } elseif (strcasecmp($db_column->is_nullable, 'YES') === 0) {
                        $node_column['default'] = 'NULL';
                    }

                    $node_column['null'] = strcasecmp($db_column->is_nullable, 'YES') === 0 ? 'true' : 'false';
                }

                // get all plain and unique indexes
                dbsteward::info("Analyze table indexes " . $db_table->table_name);
                foreach ($db->get_indices($db_table) as $db_index) {

                    // don't process primary key indexes here
                    if (strcasecmp($db_index->index_name, 'PRIMARY') === 0) {
                        continue;
                    }

                    // implement unique indexes on a single column as unique column, but only if the index name is the column name
                    if ($db_index->unique && count($db_index->columns) == 1 && strcasecmp($db_index->columns[0], $db_index->index_name) === 0) {
                        $column = $db_index->columns[0];
                        $node_column = dbx::get_table_column($node_table, $column);
                        if (!$node_column) {
                            throw new Exception("Unexpected: Could not find column node $column for unique index {$db_index->index_name}");
                        } else {
                            $node_column = $node_column[0];
                        }

                        $node_column['unique'] = 'true';
                    } else {
                        $node_index = $node_table->addChild('index');
                        $node_index['name'] = $db_index->index_name;
                        $node_index['using'] = strtolower($db_index->index_type);
                        $node_index['unique'] = $db_index->unique ? 'true' : 'false';

                        $i = 1;
                        foreach ($db_index->columns as $column_name) {
                            $node_index->addChild('indexDimension', $column_name)
                                ->addAttribute('name', $column_name . '_' . $i++);
                        }
                    }
                }

                // get all primary/foreign keys
                dbsteward::info("Analyze table constraints " . $db_table->table_name);
                foreach ($db->get_constraints($db_table) as $db_constraint) {
                    if (strcasecmp($db_constraint->constraint_type, 'primary key') === 0) {
                        $node_table['primaryKey'] = implode(',', $db_constraint->columns);
                    } elseif (strcasecmp($db_constraint->constraint_type, 'foreign key') === 0) {
                        // mysql sees foreign keys as indexes pointing at indexes.
                        // it's therefore possible for a compound index to point at a compound index

                        if (!$db_constraint->referenced_columns || !$db_constraint->referenced_table_name) {
                            throw new Exception("Unexpected: Foreign key constraint {$db_constraint->constraint_name} does not refer to any foreign columns");
                        }

                        if (count($db_constraint->referenced_columns) == 1 && count($db_constraint->columns) == 1) {
                            // not a compound index, define the FK inline in the column
                            $column = $db_constraint->columns[0];
                            $ref_column = $db_constraint->referenced_columns[0];
                            $node_column = dbx::get_table_column($node_table, $column);
                            if (!$node_column) {
                                throw new Exception("Unexpected: Could not find column node $column for foreign key constraint {$db_constraint->constraint_name}");
                            }
                            $node_column['foreignSchema'] = $db_constraint->referenced_table_schema;
                            $node_column['foreignTable'] = $db_constraint->referenced_table_name;
                            $node_column['foreignColumn'] = $ref_column;
                            unset($node_column['type']); // inferred from referenced column
                            $node_column['foreignKeyName'] = $db_constraint->constraint_name;

                            // RESTRICT is the default, leave it implicit if possible
                            if (strcasecmp($db_constraint->delete_rule, 'restrict') !== 0) {
                                $node_column['foreignOnDelete'] = str_replace(' ', '_', $db_constraint->delete_rule);
                            }
                            if (strcasecmp($db_constraint->update_rule, 'restrict') !== 0) {
                                $node_column['foreignOnUpdate'] = str_replace(' ', '_', $db_constraint->update_rule);
                            }
                        } elseif (count($db_constraint->referenced_columns) > 1
                            && count($db_constraint->referenced_columns) == count($db_constraint->columns)
                        ) {
                            $node_fkey = $node_table->addChild('foreignKey');
                            $node_fkey['columns'] = implode(', ', $db_constraint->columns);
                            $node_fkey['foreignSchema'] = $db_constraint->referenced_table_schema;
                            $node_fkey['foreignTable'] = $db_constraint->referenced_table_name;
                            $node_fkey['foreignColumns'] = implode(', ', $db_constraint->referenced_columns);
                            $node_fkey['constraintName'] = $db_constraint->constraint_name;

                            // RESTRICT is the default, leave it implicit if possible
                            if (strcasecmp($db_constraint->delete_rule, 'restrict') !== 0) {
                                $node_fkey['onDelete'] = str_replace(' ', '_', $db_constraint->delete_rule);
                            }
                            if (strcasecmp($db_constraint->update_rule, 'restrict') !== 0) {
                                $node_fkey['onUpdate'] = str_replace(' ', '_', $db_constraint->update_rule);
                            }
                        } else {
                            var_dump($db_constraint);
                            throw new Exception("Unexpected: Foreign key constraint {$db_constraint->constraint_name} has mismatched columns");
                        }
                    } elseif (strcasecmp($db_constraint->constraint_type, 'unique') === 0) {
                        dbsteward::warning("Ignoring UNIQUE constraint '{$db_constraint->constraint_name}' because they are implemented as indices");
                    } elseif (strcasecmp($db_constraint->constraint_type, 'check') === 0) {
                        // @TODO: implement CHECK constraints
                    } else {
                        throw new exception("unknown constraint_type {$db_constraint->constraint_type}");
                    }
                }

                foreach ($db->get_table_grants($db_table, $user) as $db_grant) {
                    dbsteward::info("Analyze table permissions " . $db_table->table_name);
                    $node_grant = $node_table->addChild('grant');
                    $node_grant['operation'] = $db_grant->operations;
                    $node_grant['role'] = self::translate_role_name($user, $doc);

                    if ($db_grant->is_grantable) {
                        $node_grant['with'] = 'GRANT';
                    }
                }
            }

            foreach ($db->get_sequences() as $db_seq) {
                $node_seq = $node_schema->addChild('sequence');
                $node_seq['name'] = $db_seq->name;
                $node_seq['owner'] = 'ROLE_OWNER';
                $node_seq['start'] = $db_seq->start_value;
                $node_seq['min'] = $db_seq->min_value;
                $node_seq['max'] = $db_seq->max_value;
                $node_seq['inc'] = $db_seq->increment;
                $node_seq['cycle'] = $db_seq->cycle ? 'true' : 'false';

                // the sequences table is a special case, since it's not picked up in the tables loop
                $seq_table = $db->get_table(h2_sequence::TABLE_NAME);
                foreach ($db->get_table_grants($seq_table, $user) as $db_grant) {
                    $node_grant = $node_seq->addChild('grant');
                    $node_grant['operation'] = $db_grant->operations;
                    $node_grant['role'] = self::translate_role_name($doc, $user);

                    if ($db_grant->is_grantable) {
                        $node_grant['with'] = 'GRANT';
                    }
                }
            }

            foreach ($db->get_functions() as $db_function) {
                dbsteward::info("Analyze function " . $db_function->routine_name);
                $node_fn = $node_schema->addChild('function');
                $node_fn['name'] = $db_function->routine_name;
                $node_fn['owner'] = 'ROLE_OWNER';
                $node_fn['returns'] = $type = $db_function->dtd_identifier;
                if (strcasecmp($type, 'enum') === 0) {
                    $node_fn['returns'] = $enum_type($db_function->routine_name,
                        'returns',
                        $db->parse_enum_values($db_function->dtd_identifier));
                }
                $node_fn['description'] = $db_function->routine_comment;

                if (isset($db_function->procedure) && $db_function->procedure) {
                    $node_fn['procedure'] = 'true';
                }

                // $node_fn['procedure'] = 'false';

                $eval_type = $db_function->sql_data_access;
                // srsly mysql? is_deterministic varchar(3) not null default '', contains YES or NO
                $determinism = strcasecmp($db_function->is_deterministic, 'YES') === 0 ? 'DETERMINISTIC' : 'NOT DETERMINISTIC';

                $node_fn['cachePolicy'] = h2_function::get_cache_policy_from_characteristics($determinism, $eval_type);
                $node_fn['mysqlEvalType'] = str_replace(' ', '_', $eval_type);

                // INVOKER is the default, leave it implicit when possible
                if (strcasecmp($db_function->security_type, 'definer') === 0) {
                    $node_fn['securityDefiner'] = 'true';
                }

                foreach ($db_function->parameters as $param) {
                    $node_param = $node_fn->addChild('functionParameter');
                    // not supported in mysql functions, even though it's provided?
                    // $node_param['direction'] = strtoupper($param->parameter_mode);
                    $node_param['name'] = $param->parameter_name;
                    $node_param['type'] = $type = $param->dtd_identifier;
                    if (strcasecmp($type, 'enum') === 0) {
                        $node_param['type'] = $enum_type($db_function->routine_name,
                            $param->parameter_name,
                            $db->parse_enum_values($param->dtd_identifier));
                    }
                    if (isset($param->direction)) {
                        $node_param['direction'] = $param->direction;
                    }
                }

                $node_def = $node_fn->addChild('functionDefinition', $db_function->routine_definition);
                $node_def['language'] = 'sql';
                $node_def['sqlFormat'] = 'h2';
            }

            foreach ($db->get_triggers() as $db_trigger) {
                dbsteward::info("Analyze trigger " . $db_trigger->name);
                $node_trigger = $node_schema->addChild('trigger');
                foreach ((array)$db_trigger as $k => $v) {
                    $node_trigger->addAttribute($k, $v);
                }
                $node_trigger->addAttribute('sqlFormat', 'h2');
            }

            foreach ($db->get_views() as $db_view) {
                dbsteward::info("Analyze view " . $db_view->view_name);
                if (!empty($db_view->view_name) && empty($db_view->view_query)) {
                    throw new Exception("Found a view in the database with an empty query. User '$user' problaby doesn't have SELECT permissions on tables referenced by the view.");
                }

                $node_view = $node_schema->addChild('view');
                $node_view['name'] = $db_view->view_name;
                $node_view['owner'] = 'ROLE_OWNER';
                $node_view
                    ->addChild('viewQuery', $db_view->view_query)
                    ->addAttribute('sqlFormat', 'h2');
            }

            foreach ($enum_types as $name => $values) {
                $node_type = $node_schema->addChild('type');
                $node_type['type'] = 'enum';
                $node_type['name'] = $name;

                foreach ($values as $v) {
                    $node_type->addChild('enum')->addAttribute('name', $v);
                }
            }
        }

        xml_parser::validate_xml($doc->asXML());
        return xml_parser::format_xml($doc->saveXML());
    }

    public static function translate_role_name($role, $doc = null)
    {
        if ($doc === null) {
            throw new exception('Expected $doc param to not be null');
        }

        $node_role = $doc->database->role;

        if (strcasecmp($role, $node_role->application) == 0) {
            return 'ROLE_APPLICATION';
        }

        if (strcasecmp($role, $node_role->owner) == 0) {
            return 'ROLE_OWNER';
        }

        if (strcasecmp($role, $node_role->replication) == 0) {
            return 'ROLE_REPLICATION';
        }

        if (strcasecmp($role, $node_role->readonly) == 0) {
            return 'ROLE_READONLY';
        }

        return $role;
    }

    public static function column_value_default($node_schema, $node_table, $data_column_name, $node_col)
    {
        // if marked, make it null or default, depending on column options
        if (isset($node_col['null']) && strcasecmp('true', $node_col['null']) == 0) {
            return 'NULL';
        }

        // columns that specify empty attribute are made empty strings
        if (isset($node_col['empty']) && strcasecmp('true', $node_col['empty']) == 0) {
            return "''";
        }

        // don't esacape columns marked literal sql values
        if (isset($node_col['sql']) && strcasecmp($node_col['sql'], 'true') == 0) {
            return '(' . $node_col . ')';
        }

        $node_column = dbx::get_table_column($node_table, $data_column_name);
        if ($node_column === NULL) {
            throw new exception("Failed to find table " . $node_table['name'] . " column " . $data_column_name . " for default value check");
        }
        $value_type = h2_column::column_type(dbsteward::$new_database, $node_schema, $node_table, $node_column);

        // else if col is zero length, make it default, or DB NULL
        if (strlen($node_col) == 0) {
            // is there a default defined for the column?
            $dummy_data_column = new stdClass();
            $column_default_value = xml_parser::column_default_value($node_table, $data_column_name, $dummy_data_column);
            if ($column_default_value != NULL) {
                // run default value through value_escape to allow data value conversions to happen
                $value = h2::value_escape($value_type, $column_default_value);
            } // else put a NULL in the values list
            else {
                $value = 'NULL';
            }
        } else {
            $value = h2::value_escape($value_type, dbsteward::string_cast($node_col));
        }

        return $value;
    }

    public static function escape_default_value($value)
    {
        // mysql accepts any value as quoted, except for CURRENT_TIMESTAMP
        if (strcasecmp($value, 'CURRENT_TIMESTAMP') === 0) {
            return strtoupper($value);
        }
        // if $value is numeric, it doesn't need to be quoted, although it can be
        // if we do, though, diffing barfs because "'1'" !== "1"
        if (is_numeric($value)) {
            return $value;
        }
        return h2::quote_string_value($value);
    }

    public static function value_escape($type, $value, $db_doc = NULL)
    {
        if (strlen($value) > 0) {
            // data types that should be quoted
            $enum_regex = dbx::enum_regex($db_doc);
            if (strlen($enum_regex) > 0) {
                $enum_regex = '|' . $enum_regex;
            }
            $PATTERN_QUOTED_TYPES = "/^char.*|^string|^date.*|^time.*|^varchar.*|^interval|^money.*|^inet" . $enum_regex . "/i";

            // strip quoting if it is a quoted type, it will be added after conditional conversion
            if (preg_match($PATTERN_QUOTED_TYPES, $type) > 0) {
                $value = h2::strip_single_quoting($value);
            }

            // complain when assholes use colon time notation instead of postgresql verbose for interval expressions
            if (dbsteward::$require_verbose_interval_notation) {
                if (preg_match('/interval/i', $type) > 0) {
                    if (substr($value, 0, 1) != '@') {
                        throw new exception("bad interval value: " . $value . " -- interval types must be postgresql verbose format: '@ 2 hours 30 minutes' etc for cfxn comparisons to work");
                    }
                }
            }

            // mssql doesn't understand epoch
            if (stripos('date', $type) !== FALSE
                && strcasecmp($value, 'epoch') == 0
            ) {
                $value = '1970-01-01';
            }

            // special case for postgresql type value conversion
            // the boolean type for the column would have been translated to char(1) by xml_parser::h2_type_convert()
            if (strcasecmp($type, 'char(1)') == 0) {
                $value = h2::boolean_value_convert($value);
            }
            // convert datetimeoffset(7) columns to valid MSSQL value format
            // YYYY-MM-DDThh:mm:ss[.nnnnnnn][{+|-}hh:mm]
            else if (strcasecmp($type, 'datetimeoffset(7)') == 0) {
                $value = date('c', strtotime($value));
                // use date()'s ISO 8601 date format to be conformant
            }
            // convert datetime2 columns to valid MSSQL value format
            // YYYY-MM-DDThh:mm:ss[.nnnnnnn]
            else if (strcasecmp($type, 'datetime2') == 0) {
                $value = date('Y-m-dTG:i:s', strtotime($value));
                // use date() to make date format conformant
            }
            // time with time zone is converted to time in xml_parser::h2_type_convert()
            // because of that, truncate values for time type that are > 8 chars in length
            else if (strcasecmp($type, 'time') == 0
                && strlen($value) > 8
            ) {
                $value = substr($value, 0, 8);
            }

            if (preg_match($PATTERN_QUOTED_TYPES, $type) > 0) {
                $value = static::quote_string_value($value);
            }
        } else {
            // value is zero length, make it NULL
            $value = "NULL";
        }
        return $value;
    }

    public static function strip_single_quoting($value)
    {
        // if the value is surrounded on the outside by 's
        // kill them, and kill escaped single quotes too
        if (strlen($value) > 2 && substr($value, 0, 1) == "'" && substr($value, -1) == "'") {
            $value = substr($value, 1, -1);
            $value = str_replace("''", "'", $value);
            $value = str_replace("\'", "'", $value);
        }
        return $value;
    }

    public static function boolean_value_convert($value)
    {
        // true and false become t and f for mssql char(1) special columns
        switch (strtolower($value)) {
            case 'true':
                $value = 't';
                break;
            case 'false':
                $value = 'f';
                break;
            default:
                break;
        }
        return $value;
    }

    public static function primary_key_split($primary_key_string)
    {
        return preg_split("/[\,\s]+/", $primary_key_string, -1, PREG_SPLIT_NO_EMPTY);
    }


    public static function get_quoted_function_parameter($name)
    {
        return sql99::get_quoted_name($name, self::$quote_function_parameters, self::QUOTE_CHAR);
    }

    public static function get_fully_qualified_object_name($schema_name, $object_name, $type = 'object')
    {
        if (static::$use_schema_name_prefix && strcasecmp($object_name, h2_sequence::TABLE_NAME) !== 0) {
            $object_name = $schema_name . '_' . $object_name;
        }
        $f = 'get_quoted_' . $type . '_name';
        return self::$f($object_name);
    }

    public static function get_fully_qualified_table_name($schema_name, $table_name)
    {
        if (static::$use_schema_name_prefix && strcasecmp($table_name, h2_sequence::TABLE_NAME) !== 0) {
            $table_name = $schema_name . '_' . $table_name;
        }
        return self::get_quoted_table_name($table_name);
    }

    public static function quote_string_value($value)
    {
        return "'" . str_replace("'", "\"", $value) . "'";
    }

    public static function strip_string_quoting($value)
    {
        // 'string' becomes string
        if (strlen($value) > 2 && $value[0] == "'" && substr($value, -1) == "'") {
            $value = substr($value, 1, -1);
            $value = str_replace("''", "'", $value);
            $value = str_replace("\\'", "'", $value);
        }
        return $value;
    }
}

?>
