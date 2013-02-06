<?php
/**
 * Diffs sequences.
 *
 * @package DBSteward
 * @subpackage mysql5
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class mysql5_diff_sequences extends sql99_diff_sequences {
  private static function get_sequences($schema) {
    $sequences = dbx::get_sequences($schema);

    foreach ( dbx::get_tables($schema) as $table ) {
      $sequences = array_merge($sequences, mysql5_table::get_sequences_needed($schema, $table));
    }
    
    return $sequences;
  }

  /**
   * @todo should *all* sequences be dropped in stage3, rather than just the shim?
   *       the only reason we don't is because pgsql8 drops in stage 1...
   */
  public static function diff_sequences($ofs1, $ofs3, $old_schema, $new_schema) {
    $new_sequences = static::get_sequences($new_schema);

    if ( $old_schema != null ) {
      $old_sequences = static::get_sequences($old_schema);
    }
    else {
      $old_sequences = array();
    }


    if ( empty($new_sequences) ) {
      // there are no sequences in the new schema, so if there used to be sequences,
      // we can just drop the whole shim table
      if ( !empty($old_sequences) ) {
        $ofs3->write(mysql5_sequence::get_shim_drop_sql());
      }
    }
    else {
      // there *are* sequences in the new schema, so if there didn't used to be,
      // we need to add the shim in before adding any sequences
      if ( empty($old_sequences) ) {
        $ofs1->write(mysql5_sequence::get_shim_creation_sql()."\n\n");
        $ofs1->write(mysql5_sequence::get_creation_sql($new_schema, $new_sequences)."\n");
      }
      else {
        // there were schemas in the old schema

        $common_sequences = array();

        // only drop sequences not in the new schema
        $to_drop = array();
        foreach ( $old_sequences as $old_seq ) {
          if ( static::schema_contains_sequence($new_schema, $old_seq['name'], true) ) {
            // if the sequence *is* in the new schema, then it might have changed
            $common_sequences[(string)$old_seq['name']] = $old_seq;
          }
          else {
            $to_drop[] = $old_seq;
          }
        }
        if ( ! empty($to_drop) ) {
          $ofs1->write(mysql5_sequence::get_drop_sql($old_schema, $to_drop)."\n\n");
        }

        // only add sequences not in the old schema
        $to_insert = array();
        foreach ( $new_sequences as $new_seq ) {
          if ( static::schema_contains_sequence($old_schema, $new_seq['name'])) {
            // there used to be a sequence named $new_seq['name']
            self::diff_single($ofs1, $common_sequences[(string)$new_seq['name']], $new_seq);
          }
          elseif ( !dbsteward::$ignore_oldnames
            && !empty($new_seq['oldSequenceName'])
            && static::schema_contains_sequence($old_schema, $new_seq['oldSequenceName']) ) {
            // there used to be a sequence named $new_seq['oldSequenceName']
            self::diff_single($ofs1, $common_sequences[(string)$new_seq['oldSequenceName']], $new_seq);
          }
          else {
            $to_insert[] = $new_seq;
          }
        }
        if ( ! empty($to_insert) ) {
          $ofs1->write(mysql5_sequence::get_creation_sql($new_schema, $to_insert)."\n");
        }
      }
    }
  }

  private static function schema_contains_sequence($schema, $sequence_name, $include_oldnames=FALSE) {
    if ( mysql5_schema::contains_sequence($schema, $sequence_name) ) {
      return TRUE;
    }

    foreach ( dbx::get_tables($schema) as $table ) {
      foreach ( mysql5_table::get_sequences_needed($schema, $table) as $sequence ) {
        if ( strcasecmp($sequence['name'], $sequence_name) === 0 ) {
          return TRUE;
        }

        if ( $include_oldnames && !dbsteward::$ignore_oldnames && !empty($sequence['oldSequenceName'])
          && strcasecmp($sequence['oldSequenceName'], $sequence_name) === 0 ) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  private static function diff_single($ofs, $old_seq, $new_seq) {
    $sql = array();

    if ( !dbsteward::$ignore_oldnames && !empty($new_seq['oldSequenceName']) ) {
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::SEQ_COL) . " = '{$new_seq['name']}'";
    }

    if ( $new_seq['inc'] == null && $old_seq['inc'] != null ) {
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::INC_COL) . ' = DEFAULT';
    }
    if ( $new_seq['inc'] != null && strcasecmp($new_seq['inc'], $old_seq['inc']) != 0 ) {
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::INC_COL) . ' = ' . $new_seq['inc'];
    }

    if ( $new_seq['min'] == null && $old_seq['min'] != null ) {
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::MIN_COL) . ' = DEFAULT';
    }
    elseif ( $new_seq['min'] != null && strcasecmp($new_seq['min'], $old_seq['min']) != 0 ) {
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::MIN_COL) . ' = ' . $new_seq['min'];
    }

    if ( $new_seq['max'] == null && $old_seq['max'] != null ) {
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::MAX_COL) . ' = DEFAULT';
    }
    elseif ( $new_seq['max'] != null && strcasecmp($new_seq['max'], $old_seq['max']) != 0 ) {
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::MAX_COL) . ' = ' . $new_seq['max'];
    }

    if ( $new_seq['start'] != null && strcasecmp($new_seq['start'], $old_seq['start']) != 0 ) {
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::CUR_COL) . ' = ' . $new_seq['start'];
    }

    if ( $new_seq['cycle'] == null && $old_seq['cycle'] != null ) {
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::CYC_COL) . ' = DEFAULT';
    }
    elseif ( $new_seq['cycle'] != null && strcasecmp($new_seq['cycle'], $old_seq['cycle']) != 0 ) {
      $value = strcasecmp($new_seq['cycle'], 'false') == 0 ? 'FALSE' : 'TRUE';
      $sql[] = mysql5::get_quoted_column_name(mysql5_sequence::CYC_COL) . ' = ' . $value;
    }

    if ( ! empty($sql) ) {
      $out = "UPDATE " . mysql5::get_quoted_table_name(mysql5_sequence::TABLE_NAME);
      $out .= "\nSET " . implode(",\n    ", $sql);
      $out .= "\nWHERE " . mysql5::get_quoted_column_name(mysql5_sequence::SEQ_COL) . " = ";
      $out .= "'" . $old_seq['name'] . "'";
      $ofs->write("$out;\n");
    }
  }
}

?>
