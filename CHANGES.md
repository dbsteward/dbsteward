# DBSteward Changelog

## 1.4.2 - 2017-00-00

New
  - Switch to CHANGEs.md for change log entries
  - Project moved to https://github.com/dbsteward/dbsteward
  - Check out updated documentation at https://github.com/dbsteward/dbsteward

Changes
  - Fix pgsql8 partial index where clause output SQL  https://github.com/nkiraly/DBSteward/pull/105
  - Fix mysql5 type conversion for boolean to tinyint(1)  https://github.com/nkiraly/DBSteward/pull/107
  - One usage mode, with pretty colors  https://github.com/nkiraly/DBSteward/pull/116
  - Fix --dbpassword usage for blank passwords  https://github.com/nkiraly/DBSteward/pull/115
  - Add pgsql8 ltree type support  https://github.com/dbsteward/dbsteward/pull/129
  - Drop and Re-add mysql5 and pgsql8 foreign key constraints when target column type changes  https://github.com/dbsteward/dbsteward/pull/132


## 1.4.1 - 2015-09-15

New
  - Slony ID numbering tools --slonyid et al https://github.com/nkiraly/DBSteward/pull/102 issue https://github.com/nkiraly/DBSteward/pull/101

Changes
  - mysql5 foreign key index extraction and handling improvements https://github.com/nkiraly/DBSteward/pull/103 https://github.com/nkiraly/DBSteward/issues/88
  - foreign key definition additions for ON DELETE, ON UPDATE https://github.com/nkiraly/DBSteward/pull/103 https://github.com/nkiraly/DBSteward/issues/100


## 1.4.0 - 2015-08-21

New
  - Improved Logging features from switching to monolog https://github.com/nkiraly/DBSteward/pull/82
  - Drop PEAR package support. Please use composer: https://github.com/nkiraly/DBSteward/blob/v1.4.0-alpha1/docs/INSTALLING.md
  - Add unit test coverage information via Coveralls https://coveralls.io/r/nkiraly/DBSteward https://github.com/nkiraly/DBSteward/pull/83
  - Reorganized slonik output files for --generateslonik slony replication to include preamble https://github.com/nkiraly/DBSteward/pull/90
  - Include execute script and and subscribe set statements in revamped slonik output files https://github.com/nkiraly/DBSteward/pull/90
  - XML foreignKey element support and constraint definition refactoring https://github.com/nkiraly/DBSteward/pull/93 https://github.com/nkiraly/DBSteward/issues/87
  - Update docs/ to match 1.4 API CLI and output changes

Changes
  - Add additional quotereservednames flag and separate quoteillegalnames to only affect those https://github.com/nkiraly/DBSteward/pull/69
  - Make foreignSchema attribute optional and default to the current schema https://github.com/nkiraly/DBSteward/pull/81
  - Fix pgsql8 DEFAULT parenthesis wrapping https://github.com/nkiraly/DBSteward/pull/84 
  - Fix pgsql8 index name generation to not exceed maximum length https://github.com/nkiraly/DBSteward/pull/86
  - Add pgsql8 CREATE INDEX CONCURRENTLY support to definition XML

      
## 1.3.12 - 2015-04-29

Changes
  - allow infinite includeFile depth of definition fragment XML files
  - update outdated sample XML
  - View definition dependency support with new view element dependsOnViews attribute https://github.com/nkiraly/DBSteward/pull/75
  - fix SQL output file transactionality when generating stage files for execution by slonik https://github.com/nkiraly/DBSteward/pull/76
  - mysql5 column default removal fix https://github.com/nkiraly/DBSteward/pull/77
  - Contextualize pgsql8 VIEW Slony Replica Set ID DROP / CREATE
  - Drop and recreate pgsql8 functions referring to types modified in the definition https://github.com/nkiraly/DBSteward/pull/78
  - Optional tabrow delimiter specificity https://github.com/nkiraly/DBSteward/pull/79


## 1.3.11 - 2014-08-28

Changes
  - fix cross-schema references of table-inherited columns
  - fix double quote support in sql tags
  - pgsql8 fix extraction of foreign key ON actions
  - mysql5 function characteristic definition and extraction support


## 1.3.10 - 2014-06-24

Changes
  - psql8 inherited table static row definition bugfixes
  - mysql5 table partition support for extracton and diffing
  - mysql5 stored procedure extraction and diffing support


## 1.3.9 - 2014-05-18

Changes
  - mysql5 duplicate implicit index create statement bug fix
  - pgsql8 index extraction PHP runtime error
  - miscellaneous PHP runtime warning polish
  - fix for slonyId slony set association to be consistent between sequences and tables
  - fix for parent table dependency order calculations when diffing


## 1.3.8 - 2014-04-01

Changes
  - Composer support - project inclusion and installation as global binary
  - DBSteward now reports it's version in the CLI help text
  - DTD validation improvements for partial definitions and extracted definitions
  - pgsql8 --dbport is now honored
  - selective identifier quoting with --quoteillegalnames flag
  - require slonySetId with --requireslonysetid flag
  - Duplicate sequence definiton fix for certain pgsql8 serial structure extractions
  - Improve the slonyID summary text
  - Remove extraneous DDL when diffing MySQL tables for changed column attributes
  - Fix for duplicate index creation during pgsql8 diff


## 1.3.7 - 2014-03-11

Changes
  - Fix sequence slonyId replication add and removal
  - Fix object quoting rules for functions
  - STRIP_SLONY anchors removed from upgrade scripts due to advent of --generateslonik mode delineator
  - Output directory and file prefix specificity
  - Tune pgsql8_index dimension quoting
  - pgsql8 function definition extraction fix for array contains value operator


## 1.3.6 - 2014-01-02

Changes
  - Windows PHP runtime / xmllint compatibility
  - pgsql8 index quoting fix
  - pgsql8 type quoting fix
  - mysql5/pgsql8 table foreign key name quoting consistency


## 1.3.5 - 2013-10-23

Changes
- Data table rows element overlay fix
- Column Defaults and NOT NULL enforcement for new tables during diffs
- Improve implicit index management for mysql5 foreign keys


## 1.3.4 - 2013-09-26

Changes
  - Fix false negative sequence replication detection when pgsql8 table is partitioned
  - pgsql8 composite type definition support
  - mysql5 identifier case sensitivity when diffing
  - pgsql8 duplicate and multi-dimensional index extraction bugs fixed
  - Command-line switch handling improved to not override with defaults in some contexts
  - Vast improvement on static data row diffing speed when overlaying large static datas
  - pgsql8 Reorder function defintion location for pgsql8 %TYPE usage when defining functions
  - mysql5 don't try to process multiple schemas when not in mysql5 schema prefix mode


## 1.3.3 - 2013-08-18

Changes
  - Fix XML data rows compositing: overlays for rows with the same primary key with the intention of being overwritten were not being applied
  - Ignore mysql5 table auto increment options by default
  - Handle mysql5 table extraction column default values better for values like '' and 0
  - Improve mysql5 null and timestamp rules for timestamps specifying ON UPDATE clauses
  - Improve mysql5 index + foreign key dependency scenarios when upgrading between definitions that replace dependency indexes


## 1.3.2 - 2013-08-07

Changes
  - mysql5 index name collision fix
  - pgsql8 index differencing code inheritance fix

      
## 1.3.1 - 2013-07-23

Changes
 - Fix mysql5 AUTO_INCREMENT diffing where AUTO_INCREMENT modifier would not be applied
 - Fix mysql5 index extraction as it pertains to table primary keys
 - Fix mysql5 index auto-naming and name extraction

      
## 1.3.0 - 2013-07-09

New
  - Format handler PHP fatals when certain CLI combos are used
  - Data row differencing when pkeys change in the same release
  - Addendum artifact creation mode
  - Allow definitions to contain sqlformat hints for platform specific definitions
  - refactor slony definition elements, make slony elements not required (see --generateslonik)

Changes
  - pgsql8 index quoting fixes
  - slony replica set management expansion
  - mysql5 index and column quoting fixes


## 1.2.4 - 2013-03-26

Changes
  - diff language create / drop bug
  - preserve ENUM case during extraction
  - extract columns timestamp ON UPDATE configuration properly
  - Quoting of some reserved words in pgsql8 and mysql5

      
## 1.2.3 - 2013-02-26

New
  - Partitioned table defintion support for mysql5 and pgsql8

Changes
  - pgsql8 Extraction, Differencing Improvements
  - mysql5 Extraction, Differencing Fixes, Optimizations
  - Fix DTD load failures under Mac OS
  - Fix bin/ references under SE-Linux, Arch Linux


## 1.2.2 - 2012-12-17

New
  - Identifier Quoting flag
  - Table Options extraction and differencing
  - --requireslonyid enforcement during pgsql8 diffing


## 1.2.1 - 2012-11-20

Changes
  -  API 1.2 consistency updates


## 1.2.0 - 2012-11-06

New
  - API refactoring


## 1.1.2 - 2012-05-03

Changes
  - --dbschemadump mode fixes for converting existing databases to DBSteward definitions


## 1.1.1 - 2012-04-05

New
  - API 1.1 includes CLI and XML definition changes for stage management, based on user group feedback.


## 1.0.1 - 2012-02-13

Maintenance release


## 1.0.0 - 2012-01-11

Initial release
