# Using DBSteward Examples



## 1. Compiling someapp_v1.xml
Some application, version 1, has a database backend, defined in someapp_v1.xml

https://github.com/nkiraly/DBSteward/blob/master/xml/someapp_v1.xml

This definition of a database contains 3 tables in the public schema, a session garbage collector function, and a search_results schema for the creation of temporary results tables.

To build this table, point dbsteward at the xml definition:

    dbsteward --xml=someapp_v1.xml

Now you will see the artifacts from creating this database, and the SQL build file, someapp_v1_build.sql:

    $ ls -l
    -rwxr--r--  1 nkiraly  nkiraly  4366 Jan 11 18:00 someapp_v1.xml
    -rw-r--r--  1 nkiraly  nkiraly  4478 Jan 11 18:05 someapp_v1_build.sql
    -rw-r--r--  1 nkiraly  nkiraly  4262 Jan 11 18:05 someapp_v1_composite.xml
    -rw-r--r--  1 nkiraly  nkiraly  1067 Jan 11 18:05 someapp_v1_slony.slonik

1. **someapp_v1.xml** - definition of someapp database structure
2. **someapp_v1_composite.xml** - the composite of someapp_v1.xml as DBSteward parsed and understood it, for debugging or recycling
3. **someapp_v1_slony.slonik** - slonik script for use with Slony-I replication of someapp tables
4. **someapp_v1_build.sql** - SQL DDL to create the someapp database
  * create schemas, grants to them
  * create database functions, grants to them
  * create tables, grants to them
  * add table primary and foreign keys
  * insert statically defined table row data
  * match serial primary key to statically defined table row data



## 2. Comparing someapp_v1.xml to someapp_v2.xml
New features and database constraints have been added to someapp, in someapp version 2, defined as someapp_v2.xml.

https://github.com/nkiraly/DBSteward/blob/master/xml/someapp_v1.xml

https://github.com/nkiraly/DBSteward/blob/master/xml/someapp_v2.xml

someapp_v2.xml contains a new foreign key to a new table, user_access_level. In this example we see that a new column, email, has been added to the user table. A new table has also been added, and a new column user_access_level_id, keying to it. The new column has the same type as it's foreign key column. This is consistency enforcement by DBSteward. You cannot specify a column type when specifying a foreign keyed column.

To difference v1 and v2, feed the files to dbsteward CLI:

    dbsteward --oldxml=someapp_v1.xml --newxml=someapp_v2.xml

Examining the output files, we have several useful artifacts, and runnable SQL DDL files:

    $ ls -l
    -rwxr--r--  1 nkiraly  nkiraly  4366 Jan 11 18:00 someapp_v1.xml
    -rw-r--r--  1 nkiraly  nkiraly  4262 Jan 11 18:51 someapp_v1_composite.xml
    -rwxr--r--  1 nkiraly  nkiraly  5391 Jan 11 18:44 someapp_v2.xml
    -rw-r--r--  1 nkiraly  nkiraly  5281 Jan 11 18:51 someapp_v2_composite.xml
    -rw-r--r--  1 nkiraly  nkiraly   233 Jan 11 18:51 upgrade_stage1_slony.slonik
    -rw-r--r--  1 nkiraly  nkiraly  1211 Jan 11 18:51 upgrade_stage1_schema1.sql
    -rw-r--r--  1 nkiraly  nkiraly   894 Jan 11 18:51 upgrade_stage2_data1.sql
    -rw-r--r--  1 nkiraly  nkiraly  1387 Jan 11 18:51 upgrade_stage3_slony.slonik
    -rw-r--r--  1 nkiraly  nkiraly   519 Jan 11 18:51 upgrade_stage3_schema1.sql
    -rw-r--r--  1 nkiraly  nkiraly   440 Jan 11 18:51 upgrade_stage4_data1.sql


1. **someapp_v1.xml** - old definition of someapp database structure
2. **someapp_v2.xml** - new definition of someapp database structure
3. **someapp_v1_composite.xml** - the composite of someapp_v1.xml as DBSteward parsed and understood it, for debugging or recycling
4. **someapp_v2_composite.xml** - the composite of someapp_v2.xml as DBSteward parsed and understood it, for debugging or recycling
5. **upgrade_stage1_slony.slonik** - slonik script for use with Slony-I replication, first step: forget about old table that are about to be removed
6. **upgrade_stage1_schema1.sql** - first step in upgrading someapp to v2
  * add the new table user_access_level
  * add the new column email
  * add the new column user_access_level_id
  * because a default value is defined for user_access_level_id, a statement to update all existing rows to the default has been generated
  * add constraint that the new column is foreign keyed to new table column specified
  * add application grants to new table
  * note that the NOT NULL is not applied to the table yet
7. **upgrade_stage2_data1.sql** - second step in upgrading someapp to v2
  * add rows defined in the XML to the table user_access_level
8. **upgrade_stage3_slony.slonik** - slonik script for use with Slony-I replication, second step: add new tables that are now to be replicated
9. **upgrade_stage3_schema1.sql** - third step in upgrading someapp to v2
  * now that foreign key values are available, set user_access_level_id NOT NULL
10. **upgrade_stage4_data1.sql** - fourth step in upgrading someapp to v2
  * no statically defined data to delete in v1 to v2 upgrade



## 3. Schema Extraction

To extract the structure of a running PostgreSQL database:
>  dbsteward --sqlformat=pgsql8 --dbschemadump
>  --dbhost=db.dev --dbname=some_app --dbuser=nkiraly --dbpassword=lol
>  --outputfile=some_app_structure.xml

To extract from a MySQL database is similar:
>  dbsteward --sqlformat=mysql5 --dbschemadump
>  --dbhost=db.dev --dbname=some_app --dbuser=nkiraly --dbpassword=lol
>  --outputfile=some_app_structure.xml



## 4. Data Extraction via Database Comparison

To pull the data as XML from postgresql database, and then feed it back into DBSteward for compositing:

A) Extract the PostgreSQL XML:
>  psql -A -t -h db.dev -U deployment some_app -c "SELECT database_to_xml(true, false, 'http://dbsteward.org/pgsql8_database_to_xml');" > some_app_pg_data.xml

B) Clean the psql column headers out of the head and tail of the file:
>  vi some_app_pg_data.xml

Your psql database_to_xml() should now look like this:
```xml
    <databasename xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://dbsteward.org/pgsql8_database_to_xml" xsi:schemaLocation="http://dbsteward.org/pgsql8_database_to_xml #">
      <schemaname>
        <tablename>
          <row>
            <columnname>
            <columnname>
          </row>
          ...
        </tablename>
      </schemaname>
    </databasename>
```

Composite the pg data XML onto your structure:
>  dbsteward --xml=some_app_structure.xml --pgdataxml=some_app_pg_data.xml

Rename the composite to full.xml:
>  mv some_app_structure_composite.xml some_app_full.xml

Compare the full definition to the running database:
>  dbsteward --dbhost=db.dev --dbname=some_app --dbuser=nkiraly --dbpassword=lol --dbport=5432 --dbdatadiff=some_app_structure.xml
