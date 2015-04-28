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
>  psql -A -t -h db.dev -U nkiraly_dba some_app -c "SELECT database_to_xml(true, false, 'http://dbsteward.org/pgsql8_database_to_xml');" > some_app_pg_data.xml

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
>  mv some_app_structure_composite.xml some_app.xml

Compare the full definition to the running database:
>  dbsteward --dbhost=db.dev --dbname=some_app --dbuser=nkiraly --dbpassword=lol --dbport=5432 --dbdatadiff=some_app_structure.xml


## 5. Testing Active Development Branch or Working Copy Changes

How to use DBSteward to test changes in your active development branch or working copy.


### Full Definition Generation

To test changes you may be making to the schema, you can run this to output a full _build.sql file to see how DBSteward is interpreting your table and column elements. This would typically be done to create a development database. Different customer-specific files may be overlaid to create customer-specific information.
```bash
[nkiraly@bludgeon ~/public_html/someapp]$ dbsteward --xml=db/someapp.xml --xml=customers/dev/dev_data.xml
[nkiraly@bludgeon ~/public_html/someapp]$ ls -l db/someapp*
-rw-r--r--  1 nkiraly  nkiraly  2403074 Jul 29 09:59 someapp.xml
-rw-r--r--  1 nkiraly  nkiraly  3041420 Jul 29 10:56 someapp_build.sql
-rw-r--r--  1 nkiraly  nkiraly  2473194 Jul 29 10:55 someapp_composite.xml
```


### Upgrade Testing

To test how DBSteward will diff and therefore upgrade a system from one version to another:

Check out the previous branch into a reference directory with git, or checkout the reference branch somewhere if using something like subversion.
```bash
mkdir -p reference-dbs/v2.1.0

git archive remotes/origin/v2.1.0 db | tar -x -C reference-dbs/v2.1.0 -f -
git archive remotes/origin/v2.1.0 customers | tar -x -C reference-dbs/v2.1.0 -f -
```

Difference the two definitions:
```bash
[nkiraly@bludgeon ~/public_html/someapp]$ dbsteward --oldxml=reference-dbs/v2.1.0/db/someapp.xml --oldxml=reference-dbs/v2.1.0/customers/acme/acme_data.xml --newxml=db/someapp.xml --newxml=customers/acme/acme_data.xml

[nkiraly@bludgeon ~/public_html/someapp]$ ls -l db/someapp_upgrade_*
-rw-r--r--  1 nkiraly  nkiraly  36645 Mar 29 21:26 db/someapp_upgrade_stage1_schema1.sql
-rw-r--r--  1 nkiraly  nkiraly  16545 Mar 29 21:26 db/someapp_upgrade_stage2_data1.sql
-rw-r--r--  1 nkiraly  nkiraly    624 Mar 29 21:26 db/someapp_upgrade_stage3_schema3.sql
-rw-r--r--  1 nkiraly  nkiraly    622 Mar 29 21:26 db/someapp_upgrade_stage4_data1.sql
```

### Slony Support / Slonik Script Testing

When testing changes and slony configuration, the --requireslonyid sanity flag can be used to insist that all tables and sequences have slonyId's. Consequently, DBSteward will report the next available slonyId for your convienence:

```bash
[nkiraly@bludgeon ~/public_html/someapp]$ dbsteward --oldxml=reference-dbs/v2.1.0/db/someapp.xml --oldxml=reference-dbs/v2.1.0/customers/acme/acme_data.xml --newxml=db/someapp.xml --newxml=customers/acme/acme_data.xml --generateslonik --requireslonyid --quoteillegalnames

[DBSteward-1] Loading XML /home/nicholas.kiraly/public_html/someapp/db/someapp.xml..
...
[DBSteward-1] Compositing XML File db/someapp.xml
...
[DBSteward-1] Building complete file db/someapp_build.sql
...
[DBSteward-1] Warning: users.devices                                   table missing slonyId       NEXT ID = 211
Unhandled exception:
users.devices table missing slonyId and slonyIds are required!
```

With users.devices table assigned slonyId 211:

```bash
[nkiraly@bludgeon ~/public_html/someapp]$ dbsteward --oldxml=reference-dbs/v2.1.0/db/someapp.xml --oldxml=reference-dbs/v2.1.0/customers/acme/acme_data.xml --newxml=db/someapp.xml --newxml=customers/acme/acme_data.xml --generateslonik --requireslonyid --quoteillegalnames

[nkiraly@bludgeon ~/public_html/someapp]$ ls -l db/someapp_upgrade_*
-rw-r--r--  1 nkiraly  nkiraly  1543 Mar 29 21:26 db/someapp_upgrade_slony_replica_set_100_preamble.slonik
-rw-r--r--  1 nkiraly  nkiraly  1543 Mar 29 21:26 db/someapp_upgrade_slony_replica_set_100_stage1.slonik
-rw-r--r--  1 nkiraly  nkiraly  1491 Mar 29 21:26 db/someapp_upgrade_slony_replica_set_100_stage1_schema1.sql
-rw-r--r--  1 nkiraly  nkiraly  2361 Mar 29 21:26 db/someapp_upgrade_slony_replica_set_100_stage2_data1.sql
-rw-r--r--  1 nkiraly  nkiraly  1735 Mar 29 21:26 db/someapp_upgrade_slony_replica_set_100_stage3.slonik
-rw-r--r--  1 nkiraly  nkiraly  1497 Mar 29 21:26 db/someapp_upgrade_slony_replica_set_100_stage3_schema3.sql
-rw-r--r--  1 nkiraly  nkiraly  2374 Mar 29 21:26 db/someapp_upgrade_slony_replica_set_100_stage4_data1.sql
```

### Comparing a definition to a running database

When comparing and testing upgrades, the dbdatadiff collection arguments allow you to compare the data defined in a dbsteward composited documents to a running postgresql database:
```bash
[nkiraly@bludgeon ~/public_html/someapp]$ dbsteward --dbhost=db-someappdev --dbname=someapp_acme_nkiraly --dbuser=someapp --dbdatadiff=db/someapp.xml --dbdatadiff=customers/acme/acme_data.xml

Connecting to db-someappdev:someapp_acme_nkiraly as someapp
Password:
-- Loading XML /home/nkiraly/public_html/someapp/db/someapp.xml..
-- Validating XML (size = 2375859) against /home/nkiraly/engineering/DBSteward/lib/DBSteward/dtd/dbsteward.dtd .. OK
-- Compositing XML ..
-- Validating XML (size = 1509150) against /home/nkiraly/engineering/DBSteward/lib/DBSteward/dtd/dbsteward.dtd .. OK
-- Loading XML /home/nkiraly/public_html/someapp/customers/acme/acme_data.xml..
-- Compositing XML ..
-- Validating XML (size = 1546776) against /home/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dtd/dbsteward.dtd .. OK
-- XML files /home/nkiraly/public_html/someapp/db/someapp.xml /home/nkiraly/public_html/someapp/customers/acme/acme_data.xml  composited
-- Saving as db/someapp_composite.xml
-- Building complete file db/someapp_build.sql
-- Sorting tables for foreign key dependencies for data insert generation..
-- Building slonik file db/someapp_slony.slonik
-- Comparing composited dbsteward definition data rows to postgresql database connection table contents
scheduler.shift_status_list row column WHERE ("shift_status_list_id" = 1) can_see data does not match database row column: 'TRUE' VS ''
scheduler.shift_status_list row column WHERE ("shift_status_list_id" = 2) can_see data does not match database row column: 'TRUE' VS ''
scheduler.shift_status_list row column WHERE ("shift_status_list_id" = 3) can_see data does not match database row column: 'FALSE' VS ''
public.contact_number does not contain row WHERE "contact_number_id" = 1
public.user row column WHERE ("user_id" = 1) user_status_list_id data does not match database row column: '2' VS '1'
public.user row column WHERE ("user_id" = 1) user_status_last_update data does not match database row column: '' VS '04/17/2009 19:43:17.734498 UTC'
public.personal row column WHERE ("user_id" = 1) legal_residence_line1 data does not match database row column: '' VS '1'
public.personal row column WHERE ("user_id" = 1) city data does not match database row column: '' VS 'city'
public.personal row column WHERE ("user_id" = 1) county_list_id data does not match database row column: '' VS '2219'
public.personal row column WHERE ("user_id" = 1) state_list_id data does not match database row column: '' VS '42'
public.personal row column WHERE ("user_id" = 1) zip data does not match database row column: '' VS '15217'
public.personal row column WHERE ("user_id" = 1) height data does not match database row column: '' VS '6-0'
public.personal row column WHERE ("user_id" = 1) birth_date data does not match database row column: '' VS '03/29/1979'
public.personal row column WHERE ("user_id" = 1) gender data does not match database row column: '' VS 'Male'
public.personal row column WHERE ("user_id" = 1) residence_type data does not match database row column: '' VS 'county'
public.user_preferences row column WHERE ("user_id" = 1) results_per_page_list_id data does not match database row column: '3' VS '4'
```

Some of these data differences are not relevant to static definition base we want, such as the contact, personal, and user_preference table rows found to be missing.
But the shift_status_list values are different and should be updated in the definition XML.


## 6. Task Syntax Examples


### TSE1
Composite more than one source xml set ontop of the first, to build full sql and slonik configuration files for the acme specific implementation:
> dbsteward --xml=db/someapp.xml --xml=customers/acme/acme_data.xmlp

### TSE2
Overlay postgresql database_to_xml() output onto a dbsteward defintion, alongside xml compositing:
> psql someapp -c "select database_to_xml(true, false, 'http://dbsteward.org/pgdataxml');" > someapp_pg_data.xml

> dbsteward --xml=db/someapp.xml --xml=customers/rolly/rolly_data.xml --pgdataxml=someapp_pg_data.xml
```

### TSE3
Generate DBSteward-formatted postgresql database schema structure from a running postgresql database:
> dbsteward --dbschemadump --dbhost=db-stable.dev --dbname=someapp_40 --dbuser=nkiraly_dba --outputfile=out.xml

### TSE4
Generate slony comparison SQL script for nagios monitoring, etc
> dbsteward --slonycompare=db/someapp.xml


### TSE5

Sort XML definition by schema->table->column as well as sort table->rows row entries:
> dbsteward --xmlsort=db/schema_jobs.xml
-- Sorting XML definition file: db/schema_jobs.xml

### TSE6
Append columns in a table's rows collection, based on a simplified XML definition of what to insert:
```XML
<?xml version="1.0"?>
<!-- example course_list_app_mode.xml -->
<dbsteward>
  <schema name="public">
    <table name="course_list">
      <rows columns="app_mode">
        <row>
          <col>{SCHD,CMS}</col>
        </row>
      </rows>
    </table>
  </schema>
</dbsteward>
```
> dbsteward --xml=customers/acme/acme_data.xml --xmldatainsert=tools/dbsteward/converters/course_list_app_mode.xml

```bash
-- Automatic insert data into customers/acme/acme_data.xml from tools/dbsteward/converters/course_list_app_mode.xml
-- Adding rows column app_mode to definition table course_list
-- Saving modified dbsteward definition as customers/acme/acme_data.xml.xmldatainserted
[nkiraly@bludgeon ~/public_html/someapp]$ diff customers/acme/acme_data.xml customers/acme/acme_data.xml.xmldatainserted
3958c3958
<       <rows columns="course_list_id, course, course_short_name, course_description, course_create_time, can_apply, can_manage, record_order">
---
>       <rows columns="course_list_id, course, course_short_name, course_description, course_create_time, can_apply, can_manage, record_order, app_mode">
3970a3971
>           <col>{SCHD,CMS}</col>
3983a3985
>           <col>{SCHD,CMS}</col>
3996a3999
>           <col>{SCHD,CMS}</col>
...

```

 
### TSE7
Composite more than one source and upgrade xml sets ontop of the leader of each set, and then build upgrade sql and slonik files to take a database from the build composite to the upgrade composite:
> dbsteward --oldxml=reference-dbs/v2.1.0/db/someapp.xml --oldxml=reference-dbs/v2.1.0/customers/acme/acme_data.xml --newxml=db/someapp.xml --newxml=customers/acme/acme_data.xml

 
### TSE8
Need to rip a table's contents for customer inclusion? Use query_to_xml() and DBSteward's pgdata composite mode:
```bash
# use query_to_xml() to enforce primary key sorting
# the SELECT with explicit columns are the columns that match the existing table rows definition we are trying to fix/replace
[nkiraly@quickswitch ~/public_html/someapp]$ psql -h db01 -U nkiraly_dba someapp_gaunt_build -c "SELECT query_to_xml('SELECT course_list_id, can_manage, course, course_description, course_create_time, can_apply, course_group_id, record_order FROM course_list ORDER BY course_list_id', true, false, 'http://tempuri.org');" > someapp_gaunt_build_course_list.xml
[nkiraly@bludgeon ~/public_html/someapp]$ scp quickswitch:/home/nkiraly/public_html/someapp/someapp_gaunt_build_course_list.xml .
 
# we needed the null column placeholders, but the pgdata parser
# doesn't respect namespaces, so kill the ' xsi:nil="true"' attributes
sed -i  "s/ xsi:nil=\"true\"//" someapp_gaunt_build_course_list.xml
 
# trash the header and footer garbage from query_to_xml()
# and replace it with database->schema->table elements that the pgdata parser understands
vim someapp_gaunt_build_course_list.xml
 
<someapp_gaunt_build>
<public>
<course_list>
 
... <row> collection ...
 
</course_list>
</public>
</someapp_gaunt_build>


# composite overlay the pgdata file ontop of base someapp
[nkiraly@bludgeon ~/public_html/someapp]$ dbsteward --xml=db/someapp.xml --pgdataxml=someapp_gaunt_build_course_list.xml
 
# now you can take the composite file and lay the <rows> data definition in the gaunt_data.xml file
[nkiraly@bludgeon ~/public_html/someapp]$ vim db/someapp_composite.xml
[nkiraly@bludgeon ~/public_html/someapp]$ vim customers/gaunt/gaunt_data.xml
```

### TSE9
Addendums, to specify that for the last N XML files specified collect all changes and additions to defined XML table data in a file called BASE_addendums.xml
> dbsteward --xmlcollectdataaddendums=1 --xml=db/someapp.xml  --xml=customers/rolly/rolly_data.xml  --xml=someapp_rolly_config_export.xml
 
> --xmlcollectdataaddendums=N to specify that
> For the purpose of someapp config exports, this allows developers to separate SomeApp config exports from SomeApp BASE + customer definitions

