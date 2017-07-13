# DBSteward
SQL database definition differencing tool. Structure and data is defined in a DTD-enforced, human-readable XML format. Outputs transactional SQL statement files to apply your changes.

[![Latest Stable Version](https://poser.pugx.org/dbsteward/dbsteward/v/stable.png)](https://packagist.org/packages/dbsteward/dbsteward)
[![dbsteward/dbsteward/master Build Status](https://travis-ci.org/dbsteward/dbsteward.png?branch=master)](https://travis-ci.org/dbsteward/dbsteward)
[![Coverage Status](https://coveralls.io/repos/dbsteward/dbsteward/badge.svg?branch=master)](https://coveralls.io/r/dbsteward/dbsteward?branch=master)
[![Dependency Status](https://www.versioneye.com/php/nkiraly:DBSteward/dev-master/badge.png)](https://www.versioneye.com/php/nkiraly:DBSteward/dev-master)
[![Reference Status](https://www.versioneye.com/php/nkiraly:DBSteward/reference_badge.svg)](https://www.versioneye.com/php/nkiraly:DBSteward/references)

[![GitHub Release](http://img.shields.io/github/release/dbsteward/dbsteward.svg?style=plastic)][release]
[![GitHub License](https://img.shields.io/badge/license-BSD-blue.svg?style=plastic)][license]
[![Twitter](https://img.shields.io/twitter/url/https/github.com/dbsteward/dbsteward.svg?style=plastic)][twitter]

[release]: https://github.com/dbsteward/dbsteward/releases
[license]: https://raw.githubusercontent.com/dbsteward/dbsteward/master/LICENSE.md
[twitter]: https://twitter.com/intent/tweet?text=Hey%20@dbsteward%20&url=%5Bobject%20Object%5D

NOTICE: Due to dependency updates, DBSteward 1.4.0 was the last version to support PHP 5.3 and 5.4. Please upgrade your run-times to at least PHP 5.5 before upgrading to DBSteward 1.4.2+

## Want Updates?
Subscribe to the [DBSteward Announce](https://groups.google.com/forum/#!forum/dbsteward-announce) mailing list

## Need Help?
Post your question to the [DBSteward Users](https://groups.google.com/forum/#!forum/dbsteward-users) mailing list


# What / who is DBSteward for?

Intended users are application developers and database administrators who maintain database structure changes as part of an application life cycle. Defining your SQL database in a DBSteward XML definition can greatly lower your release engineering costs by removing the need to write and test SQL changes.

Many developers maintain complete and upgrade script versions of their application databases. Upgrade headaches or data loss are reduced by only requiring a developer to maintain a complete definition file. Creating an upgrade from version A to B becomes a compile task, where you ask DBSteward to generate SQL  changes by feeding it A and B versions of your database in XML.

# Are you technical and tired of reading this FAQ already?

Using DBSteward to generate or difference a database definition: https://github.com/dbsteward/dbsteward/blob/master/docs/USING.md

Installing DBSteward with Composer / PEAR: https://github.com/dbsteward/dbsteward/blob/master/docs/INSTALLING.md

XML Format examples and ancedotes: https://github.com/dbsteward/dbsteward/blob/master/docs/XMLGUIDE.md

Software development best practices: https://github.com/dbsteward/dbsteward/blob/master/docs/DEVGUIDE.md

Slony configuration management examples: https://github.com/dbsteward/dbsteward/blob/master/docs/SLONYGUIDE.md


***

# Frequently Asked Questions

There can be nuances to working with DBSteward for the purpose of generating or differencing a database. Please review these FAQ to aide in your development efforts when employing DBSteward.

## 1. What are these input and output files?

In the following examples, the definition file is *someapp_v1.xml*. For more information on the DBSteward XML format, see https://github.com/dbsteward/dbsteward/blob/master/docs/XMLGUIDE.md

When building a full definition ( _dbsteward --xml=someapp.xml_ ), DBSteward will output a someapp_v1_full_build.sql file. This SQL file contains all of the DDL DML DCL to create a instance of your database definition, **with all operations in foreign-key dependency order**.

- someapp_v1.xml
- someapp_v2.xml
- somapp_v2_upgrade_stageN_*.sql

When generating definition difference between two definitions ( _dbsteward --oldxml=someapp_v1.xml --newxml=someapp_v2.xml_ ), DBSteward will output several upgrade files, segmenting the upgrade process, **with all operations in foreign-key dependency order**.
* Stage 1
  - someapp_v2_upgrade_stage1_schema1.sql
  - DDL ( **_CREATE_**, **_ALTER TABLE_** ) changes and additions to database structure, in foreign-key dependency order
  - DCL ( **_GRANT_** ) apply all defined permissions
* Stage 2
  - someapp_v2_upgrade_stage2_data1.sql
  - DML ( **_DELETE_**, **_UPDATE_** ) removal and modification of statically defined table data
  - DDL cleanup of constraints not enforceable at initial **_ALTER_** time
* Stage 3
  * someapp_v2_upgrade_stage3_schema1.sql
  * DDL final changes and removal of any database structure no longer defined
* Stage 4
  * someapp_v2_upgrade_stage4_data1.sql
  * DML ( **_INSERT_**, **_UPDATE_** ) insert and update of statically defined table data


## 2. How does DBSteward determine what has changed?
DBSteward's approach and expectation is that developers only need to maintain the full definition of a database. When run, DBSteward will determine what has changed between the definition XML of two different versions of the database, generating appropriate SQL commands as output.

DBSteward XML definition files can be included and overlay-composited with other DBSteward XML definition files, providing a way to overlay installation specific database structure and static data definitions.

DBSteward has 2 main output products of XML definition parsing and comparison:
1) Full - output a 'full' database definition SQL file that can be used to create a complete database based on the XML definition.
2) Upgrade - output staged SQL upgrade files which can be used to upgrade an existing database created with the first XML definition file, to be as the second XML file is defined.

DBSteward creates upgrade scripts as the result of comparing two XML definition sets. As a result, upgrade file creation does not require target database connectivity.

DBSteward is also capable of reading standard Postgresql pg_dump files or slurping a running Postgresql database and outputting a matching XML definition file.


## 3. Why use DBSteward to maintain database structure?
Maintaining database structure with DBSteward allows developers to make large or small changes and immediately be able to test a fresh database deployment against revised code. The updated definition is then also immediately useful to upgrade an older version to the current one. Being able to generate DDL / DCL / DML changes can greatly simplify and speed up database upgrade testing and deployment. At any point during a development cycle, a DBA can generate database definition changes instead of having to maintain complex upgrade scripts or hunt for developers who made a database change.


## 4. What SQL RDMS output formats does DBSteward currently support?
DBSteward currently supports output files in Postgresql 8 / 9, MySQL 5.5, and Microsoft SQL Server 2005 / 2008 compliant SQL commands. DBSteward has an extensible SQL formatting code architecture, to allow for additional SQL flavors to be supported rapidly.


## 5. How do I get started?
To start tinkering with the possibilities, install DBSteward with Composer with https://github.com/dbsteward/dbsteward/blob/master/docs/INSTALLING.md

You will also need to have the `xmllint` executable installed in your PATH, available from [libxml2](http://xmlsoft.org).

You can also of get a checkout at git://github.com/dbsteward/dbsteward.git
It is runnable in source-checkout form, as php bin/dbsteward.php


## 6. How do I convert an existing database to DBSteward definition?
## 7. I have an existing project how do I migrate to using DBSteward?
Examples of structure and data extraction can be found on the Using DBSteward article https://github.com/dbsteward/dbsteward/blob/master/docs/USING.md


## 8. Can I define static data in DBSteward XML?

Yes you can. Static data rows will be differenced and changes DML generated in stage 2 and 4 .sql files. You can find examples of defining static data in the table _user_status_list_ of the [someapp_v1.xml sample definition](https://github.com/dbsteward/dbsteward/blob/master/xml/someapp_v1.xml). Be sure to leave your static data rows each version. They are compared for changes, additions, and deletions each time you build an upgrade.


## 9. How do I define legacy object names such as columns named order or tables called group without getting 'Invalid identifier'
Use --quotecolumnnames or --quoteallnames to tell dbsteward to use identifier delimineters on all objects of that type, to allow reserved words to be used as objects.


## 10. Why are views always dropped and re-added?

SQL server implementations expand SELECT * .. and implicitly use column types when creating view definitions from query expressions. Rebuilding these views ensures the types and column lists in a view will be consistent with the dependent tables providing the data.


## 11. Where are my slonik files? Why aren't my slony configuration details being honored?

slony slonik configuration files are not output during structure defiinition or diffing unless you use the --generateslonik flag.
This is to steamline the development vs DBA replication staff roles in the development lifecycle.


## 12. Do I just pick a slonyId? What's the rhyme or reason with slonyId's?

slonyIds can be completely arbitrary, but are recommended to be allocated in segments. Example: IDs 100-199 are reserved for user tables, IDs 200-299 are for forum relationships and post data, IDs 500-599 for form full text search tables, ad nausea.


## 13. How do I define replicate, and upgrade a database I have defined with DBSteward and want to replicate with Slony?

See the Slony slonik output usage guide https://github.com/dbsteward/dbsteward/blob/master/docs/SLONYGUIDE.md for examples.


## 14. What are some recommended best practices for the software development lifecycle?

See the DBSteward Development guide https://github.com/dbsteward/dbsteward/blob/master/docs/DEVGUIDE.md for detailed examples.

