# Using DBSteward Examples



## 1. Compiling someapp_v1.xml
Some application, version 1, has a database backend, defined in someapp_v1.xml

https://github.com/dbsteward/dbsteward/blob/master/xml/someapp_v1.xml

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

https://github.com/dbsteward/dbsteward/blob/master/xml/someapp_v1.xml

https://github.com/dbsteward/dbsteward/blob/master/xml/someapp_v2.xml

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
>  --dbhost=db-someappdev.dev.facilt1 --dbname=some_app --dbuser=nkiraly --dbpassword=lol
>  --outputfile=some_app_structure.xml

To extract from a MySQL database is similar:
>  dbsteward --sqlformat=mysql5 --dbschemadump
>  --dbhost=db-someappdev.dev.facilt1 --dbname=some_app --dbuser=nkiraly --dbpassword=lol
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
>  dbsteward --dbhost=db-someappdev.dev.facilt1 --dbname=some_app --dbuser=nkiraly --dbpassword=lol --dbport=5432 --dbdatadiff=some_app_structure.xml


## 5. Testing Active Development Branch or Working Copy Changes

How to use DBSteward to test changes in your active development branch or working copy.


### Full Definition Generation

To test changes you may be making to the schema, you can run this to output a full _build.sql file to see how DBSteward is interpreting your table and column elements. This would typically be done to create a development database. Different customer-specific files may be overlaid to create customer-specific information.
```bash
[nicholas.kiraly@bludgeon (master) ~/engineering/DBSteward/xml]$

dbsteward --xml=someapp_v1.xml --xml=customers/acme/acme_data.xml
NOTICE   DBSteward Version 1.4.0
NOTICE   Using sqlformat=pgsql8
NOTICE   Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/someapp_v1.xml..
NOTICE   Compositing XML File someapp_v1.xml
NOTICE   Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/customers/acme/acme_data.xml..
NOTICE   Compositing XML File customers/acme/acme_data.xml
NOTICE   Saving composite as ./someapp_v1_composite.xml
NOTICE   [File Segment] Fixed output file: ./someapp_v1_build.sql

ls -l someapp_v1*
-rwxr-xr-x  1 nicholas.kiraly  6035   6413 Jun 29 22:12 someapp_v1.xml
-rw-r--r--  1 nicholas.kiraly  6035  10776 Aug 18 13:44 someapp_v1_build.sql
-rw-r--r--  1 nicholas.kiraly  6035  10074 Aug 18 13:44 someapp_v1_composite.xml
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
[nicholas.kiraly@bludgeon (master) ~/engineering/DBSteward/xml]$

dbsteward --oldxml=reference-dbs/v1.0.0/db/someapp_v1.xml --oldxml=reference-dbs/v1.0.0/customers/acme/acme_data.xml --newxml=someapp_v2.xml --newxml=customers/acme/acme_data.xml

NOTICE   DBSteward Version 1.4.0
NOTICE   Using sqlformat=pgsql8
NOTICE   Loading XML reference-dbs/v1.0.0/db/someapp_v1.xml..
NOTICE   Compositing XML File someapp_v1.xml
NOTICE   Loading XML someapp_v2.xml..
NOTICE   Compositing XML File someapp_v2.xml
NOTICE   Saving oldxml composite as ./someapp_v1_composite.xml
NOTICE   Saving newxml composite as ./someapp_v2_composite.xml
NOTICE   [File Segment] Opening output file segement ./someapp_v2_upgrade_stage1_schema1.sql
NOTICE   [File Segment] Opening output file segement ./someapp_v2_upgrade_stage2_data1.sql
NOTICE   [File Segment] Opening output file segement ./someapp_v2_upgrade_stage3_schema1.sql
NOTICE   [File Segment] Opening output file segement ./someapp_v2_upgrade_stage4_data1.sql

[nkiraly@bludgeon ~/public_html/someapp]$ ls -l db/someapp_upgrade_*
-rw-r--r--  1 nicholas.kiraly  6035  2802 Aug 18 13:42 someapp_v2_upgrade_stage1_schema1.sql
-rw-r--r--  1 nicholas.kiraly  6035   311 Aug 18 13:42 someapp_v2_upgrade_stage2_data1.sql
-rw-r--r--  1 nicholas.kiraly  6035   688 Aug 18 13:42 someapp_v2_upgrade_stage3_schema1.sql
-rw-r--r--  1 nicholas.kiraly  6035   696 Aug 18 13:42 someapp_v2_upgrade_stage4_data1.sql
```

### Slony Support / Slonik Script Testing

When testing changes and slony configuration, the --requireslonyid sanity flag can be used to insist that all tables and sequences have slonyId's. Consequently, DBSteward will report the next available slonyId for your convienence:

```bash
[nicholas.kiraly@bludgeon (master) ~/public_html/someapp]$

dbsteward --oldxml=reference-dbs/v2.1.0/db/someapp.xml --oldxml=reference-dbs/v2.1.0/customers/acme/acme_data.xml --newxml=db/someapp.xml --newxml=customers/acme/acme_data.xml --generateslonik --requireslonyid --quotereservednames

NOTICE   Loading XML /home/nicholas.kiraly/public_html/someapp/db/someapp.xml..
...
NOTICE   Compositing XML File db/someapp.xml
...
WARNING  Warning: users.devices                                table missing slonyId    NEXT ID = 211
ERROR    Table users.devices missing slonyId and slonyIds are required
```

With users.devices table assigned slonyId 211:

```bash
[nkiraly@bludgeon ~/public_html/someapp]$ dbsteward --oldxml=reference-dbs/v2.1.0/db/someapp.xml --oldxml=reference-dbs/v2.1.0/customers/acme/acme_data.xml --newxml=db/someapp.xml --newxml=customers/acme/acme_data.xml --generateslonik --requireslonyid --quotereservednames

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

When comparing and testing upgrades, the dbdatadiff collection arguments allow you to compare the data defined in a dbsteward composited documents to a running postgresql database. The database someapp_acme_nkiraly was created with someapp_v2.xml without the acme_data.xml additions. These files are in the dbsteward xml/ sample directory for examination.

```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward/xml $

# create database for examination by dbdatadiff
createdb -U deployment someapp_acme_nkiraly
dbsteward --xml=someapp_v2.xml
psql -U deployment someapp_acme_nkiraly -f someapp_v2_build.sql

# compare running database to someapp_v2 with acme data additions
dbsteward --dbhost=db-someappdev.dev.facilt1 --dbname=someapp_acme_nkiraly --dbuser=someapp --dbdatadiff=someapp_v2.xml --dbdatadiff=customers/acme/acme_data.xml --outputfile=acme_extracted.xml -v
NOTICE   DBSteward Version 1.4.0
NOTICE   Using sqlformat=pgsql8
INFO     Compositing XML files..
NOTICE   Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/someapp_v2.xml..
NOTICE   Compositing XML File someapp_v2.xml
INFO     Validating XML (size = 8073) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
INFO     XML Validates (size = 8073) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
NOTICE   Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/customers/acme/acme_data.xml..
NOTICE   Compositing XML File customers/acme/acme_data.xml
INFO     Validating XML (size = 7553) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
INFO     XML Validates (size = 7553) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
INFO     XML files someapp_v2.xml customers/acme/acme_data.xml composited
NOTICE   Saving composite as ./someapp_v2_composite.xml
NOTICE   Connecting to pgsql8 host db-someappdev:5432 database someapp_acme_nkiraly as someapp
Password: password1
INFO     Comparing composited dbsteward definition data rows to postgresql database connection table contents
WARNING  public.sql_user row column WHERE (user_id = 1) register_date data does not match database row column: 'NOW()' VS '2015-08-18 13:27:30.253881-04'
NOTICE   public.user_status_list does not contain row WHERE user_status_list_id = 5
```

look at the comparison XML artifact for rows that you might want to include in your repository definition
> vi acme_extracted.xml
```xml
...
    <table name="user_status_list" owner="ROLE_OWNER" slonyId="30" primaryKey="user_status_list_id">
...
      <rows columns="user_status_list_id, user_status, is_visible, can_login">
        <row>
          <col>5</col>
          <col>AcmeAudit</col>
          <col>false</col>
          <col>true</col>
        </row>
```

Some of these data differences are not relevant to static definition base we want, such as the contact, personal, and user_preference table rows found to be missing.
But the shift_status_list values are different and should be updated in the definition XML.


## 6. Task Syntax Examples


### TSE1
Composite more than one source xml set ontop of the first, to build full sql and slonik configuration files for the acme specific implementation:
> dbsteward --xml=someapp_v1.xml --xml=customers/acme/acme_data.xml

### TSE2
Overlay postgresql database_to_xml() output onto a dbsteward defintion, alongside xml compositing:
> psql someapp -c "select database_to_xml(true, false, 'http://dbsteward.org/pgdataxml');" > someapp_pg_data.xml

> dbsteward --xml=someapp.xml --xml=customers/rolly/rolly_data.xml --pgdataxml=someapp_pg_data.xml
```

### TSE3
Generate DBSteward-formatted postgresql database schema structure from a running postgresql database:
> dbsteward --dbschemadump --dbhost=db-someappdev.dev.facilt1 --dbname=someapp_40 --dbuser=nkiraly_dba --outputfile=out.xml

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


### TSE10
Use --slonyidin with --slonyidsetvalue to auto-number all slony replicatable things with a slonyId and slonySetId based on --requireslonyid and --requireslonysetid settings

To output an XML with slonyId specified, starting at 9001
```bash
[nicholas.kiraly@bludgeon ~/engineering/DBSteward/xml]
$ dbsteward --requireslonyid --slonyidstartvalue=9001 --slonyidin=slonyid_example_someapp_v2.xml
NOTICE   DBSteward Version 1.4.0
NOTICE   Using sqlformat=pgsql8
NOTICE   Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/slonyid_example_someapp_v2.xml..
NOTICE   Compositing XML File slonyid_example_someapp_v2.xml
NOTICE   Saving composite as ./slonyid_example_someapp_v2_composite.xml
NOTICE   Slony ID numbering any missing attributes
NOTICE   Saving Slony ID numbered XML as ./slonyid_example_someapp_v2_slonyid_numbered.xml

$ diff slonyid_example_someapp_v2_composite.xml slonyid_example_someapp_v2_slonyid_numbered.xml
121,122c121,122
<     <table name="group_list" owner="ROLE_OWNER" primaryKey="group_list_id">
<       <column name="group_list_id" type="bigserial"/>
---
>     <table name="group_list" owner="ROLE_OWNER" primaryKey="group_list_id" slonyId="9001">
>       <column name="group_list_id" type="bigserial" slonyId="9002"/>
```

To output an XML with slonyId and slonySetId specified, with slonyIds starting at 9001 and slonySetIds starting at 5001
```bash
$ dbsteward --requireslonysetid --requireslonyid --slonyidstartvalue=9001 --slonyidin=slonyid_example_someapp_v2.xml
NOTICE   DBSteward Version 1.4.0
NOTICE   Using sqlformat=pgsql8
NOTICE   Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/slonyid_example_someapp_v2.xml..
NOTICE   Compositing XML File slonyid_example_someapp_v2.xml
NOTICE   Saving composite as ./slonyid_example_someapp_v2_composite.xml
NOTICE   Slony ID numbering any missing attributes
NOTICE   Saving Slony ID numbered XML as ./slonyid_example_someapp_v2_slonyid_numbered.xml

$ diff slonyid_example_someapp_v2_composite.xml slonyid_example_someapp_v2_slonyid_numbered.xml
21,22c21,22
<   <schema name="public" owner="ROLE_OWNER">
<     <table name="sql_user" owner="ROLE_OWNER" primaryKey="user_id" slonyId="10">
---
>   <schema name="public" owner="ROLE_OWNER" slonySetId="1">
>     <table name="sql_user" owner="ROLE_OWNER" primaryKey="user_id" slonyId="10" slonySetId="1">
29c29
<       <column name="user_id" type="bigserial" slonyId="10"/>
---
>       <column name="user_id" type="bigserial" slonyId="10" slonySetId="1"/>
49,50c49,50
<     <trigger name="user_audit" sqlFormat="mysql5" table="sql_user" when="BEFORE" event="INSERT" function="EXECUTE xyz"/>
<     <table name="user_status_list" owner="ROLE_OWNER" slonyId="30" primaryKey="user_status_list_id">
---
>     <trigger name="user_audit" sqlFormat="mysql5" table="sql_user" when="BEFORE" event="INSERT" function="EXECUTE xyz" slonySetId="1"/>
>     <table name="user_status_list" owner="ROLE_OWNER" slonyId="30" primaryKey="user_status_list_id" slonySetId="1">
77c77
<     <table name="user_access_level" owner="ROLE_OWNER" slonyId="40" primaryKey="user_access_level_id">
---
>     <table name="user_access_level" owner="ROLE_OWNER" slonyId="40" primaryKey="user_access_level_id" slonySetId="1">
100c100
<     <table name="session_information" description="Information regarding a user's current session" primaryKey="session_id" owner="ROLE_OWNER" slonyId="20">
---
>     <table name="session_information" description="Information regarding a user's current session" primaryKey="session_id" owner="ROLE_OWNER" slonyId="20" slonySetId="1">
113c113
<     <function name="destroy_session" owner="ROLE_OWNER" returns="VOID" description="Deletes session data from the database">
---
>     <function name="destroy_session" owner="ROLE_OWNER" returns="VOID" description="Deletes session data from the database" slonySetId="1">
121,122c121,122
<     <table name="group_list" owner="ROLE_OWNER" primaryKey="group_list_id">
<       <column name="group_list_id" type="bigserial"/>
---
>     <table name="group_list" owner="ROLE_OWNER" primaryKey="group_list_id" slonySetId="1" slonyId="9001">
>       <column name="group_list_id" type="bigserial" slonySetId="1" slonyId="9002"/>
136c136
<     <trigger name="sql_user_part_trg" sqlFormat="pgsql8" event="INSERT" when="BEFORE" table="sql_user" forEach="ROW" function="_p_public_sql_user.insert_trigger()"/>
---
>     <trigger name="sql_user_part_trg" sqlFormat="pgsql8" event="INSERT" when="BEFORE" table="sql_user" forEach="ROW" function="_p_public_sql_user.insert_trigger()" slonySetId="1"/>
138,139c138,139
<   <schema name="search_results" owner="ROLE_OWNER">
<     <sequence name="result_tables_unique_id_seq" start="1" inc="1" max="99999" cycle="true" cache="1" owner="ROLE_OWNER" slonyId="346">
---
>   <schema name="search_results" owner="ROLE_OWNER" slonySetId="1">
>     <sequence name="result_tables_unique_id_seq" start="1" inc="1" max="99999" cycle="true" cache="1" owner="ROLE_OWNER" slonyId="346" slonySetId="1">
144c144
<   <schema name="_p_public_sql_user">
---
>   <schema name="_p_public_sql_user" slonySetId="1">
146c146
<     <table name="partition_0" owner="ROLE_OWNER" primaryKey="user_id" inheritsTable="sql_user" inheritsSchema="public" slonyId="347">
---
>     <table name="partition_0" owner="ROLE_OWNER" primaryKey="user_id" inheritsTable="sql_user" inheritsSchema="public" slonyId="347" slonySetId="1">
155c155
<     <table name="partition_1" owner="ROLE_OWNER" primaryKey="user_id" inheritsTable="sql_user" inheritsSchema="public" slonyId="348">
---
>     <table name="partition_1" owner="ROLE_OWNER" primaryKey="user_id" inheritsTable="sql_user" inheritsSchema="public" slonyId="348" slonySetId="1">
164c164
<     <table name="partition_2" owner="ROLE_OWNER" primaryKey="user_id" inheritsTable="sql_user" inheritsSchema="public" slonyId="349">
---
>     <table name="partition_2" owner="ROLE_OWNER" primaryKey="user_id" inheritsTable="sql_user" inheritsSchema="public" slonyId="349" slonySetId="1">
173c173
<     <table name="partition_3" owner="ROLE_OWNER" primaryKey="user_id" inheritsTable="sql_user" inheritsSchema="public" slonyId="350">
---
>     <table name="partition_3" owner="ROLE_OWNER" primaryKey="user_id" inheritsTable="sql_user" inheritsSchema="public" slonyId="350" slonySetId="1">
182c182
<     <function name="insert_trigger" returns="TRIGGER" owner="ROLE_OWNER" description="DBSteward auto-generated for table partition">
---
>     <function name="insert_trigger" returns="TRIGGER" owner="ROLE_OWNER" description="DBSteward auto-generated for table partition" slonySetId="1">
```
