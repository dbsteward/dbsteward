# What / who is DBSteward for?
DBSteward is a database definition differencing tool. Database structure and data is defined in a DTD-enforced, human-readable XML format.

Intended users are application developers and database administrators who maintain database structure changes as part of an application life cycle. Defining your SQL database in a DBSteward XML definition can greatly lower your release engineering costs by removing the need to write and test SQL changes.

Many developers maintain complete and upgrade script versions of their application databases. Upgrade headaches or data loss are reduced by only requiring a developer to maintain a complete definition file. Creating an upgrade from version A to B becomes a compile task, where you ask DBSteward to generate SQL  changes by feeding it A and B versions of your database in XML.

## **Are you technical and tired of reading this FAQ already?** Try reading the Crash Course guide before continuing: https://github.com/nkiraly/DBSteward/wiki/Crash-course


***


# What are these output files?
## someapp.xml -> someapp_full.sql
When building a full definition ( _dbsteward --xml=someapp.xml_ ), DBSteward will output a someapp_full.sql file. This SQL file contains all of the DDL DML DCL to create a instance of your database definition, **with all operations in foreign-key dependency order**.
## someapp_v1.xml + someapp_v2.xml -> update_stageN_*.sql
When generating definition difference between two definitions ( _dbsteward --oldxml=someapp_v1.xml --newxml=someapp_v2.xml_ ), DBSteward will output several upgrade files, segmenting the upgrade process, **with all operations in foreign-key dependency order**.
* Stage 1
  - upgrade_stage1_schema1.sql
  - DDL ( **_CREATE_**, **_ALTER TABLE_** ) changes and additions to database structure, in foreign-key dependency order
  - DCL ( **_GRANT_** ) apply all defined permissions
* Stage 2
  - upgrade_stage2_data1.sql
  - DML ( **_INSERT_**, **_UPDATE_** ) changes and additions to staticly defined table data
  - DDL cleanup of constraints not enforceable at initial **_ALTER_** time
* Stage 3
  * upgrade_stage3_schema1.sql
  * DDL final changes and removal of any database structure no longer defined
* Stage 4
  * upgrade_stage4_data1.sql
  * DML ( **_UPDATE_**, **_DELETE_** ) of data changed and removed


# How does DBSteward determine what has changed?
DBSteward's approach and expectation is that developers only need to maintain the full definition of a database. When run, DBSteward will determine what has changed between the definition XML of two different versions of the database, generating appropriate SQL commands as output.

DBSteward XML definition files can be included and overlay-composited with other DBSteward XML definition files, providing a way to overlay installation specific database structure and static data definitions.

DBSteward has 2 main output products of XML definition parsing and comparison:
1) Full - output a 'full' database definition SQL file that can be used to create a complete database based on the XML definition.
2) Upgrade - output staged SQL upgrade files which can be used to upgrade an existing database created with the first XML definition file, to be as the second XML file is defined.

DBSteward creates upgrade scripts as the result of comparing two XML definition sets. As a result, upgrade file creation does not require target database connectivity.

DBSteward is also capable of reading standard Postgresql pg_dump files or slurping a running Postgresql database and outputting a matching XML definition file.


# Why use DBSteward to maintain database structure?
Maintaining database structure with DBSteward allows developers to make large or small changes and immediately be able to test a fresh database deployment against revised code. The updated definition is then also immediately useful to upgrade an older version to the current one. Being able to generate DDL / DCL / DML changes can greatly simplify and speed up database upgrade testing and deployment. At any point during a development cycle, a DBA can generate database definition changes instead of having to maintain complex upgrade scripts or hunt for developers who made a database change.


# What SQL RDMS output formats does DBSteward currently support?
DBSteward currently supports output files in Postgresql 8 / 9, MySQL 5.5, and Microsoft SQL Server 2005 / 2008 compliant SQL commands. DBSteward has an extensible SQL formatting code architecture, to allow for additional SQL flavors to be supported rapidly.


# How do I get started?
To start tinkering with the possibilities, download and install the PEAR package by following the [[Crash Course]] guide for more information and real world examples.

You can also of get a checkout at git://github.com/nkiraly/DBSteward.git
It is runnable in source-checkout form, via php bin/dbsteward.php


# How do I convert an existing database to DBSteward definition?
An example of this process is documented in the wiki page [[Extracting Existing Database Structure]]
