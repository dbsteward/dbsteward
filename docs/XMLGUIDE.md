# XML Format Introduction for DBSteward Database Definitions

The definitive guide to the xml format is always the dbsteward.dtd, which is installed with the application.

However, some examples are always nice. Here we go.


## 1. Basic definition and explanation

```xml
<dbsteward>
  <database>
    <role>
      <application>db_app</application>
      <owner>root</owner>
      <replication>replication</replication>
      <readonly>readonly</readonly>
    </role>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="test_table" primaryKey="id" owner="ROLE_OWNER">
      <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
      <column name="id" type="int" null="false"/>
      <column name="description" type="varchar(255)" null="false"/>
      <column name="group_id" type="int" null="false"/>
      <index name="description_group_idx" using="btree">
        <indexDimension name="description_dim">description</indexDimension>
        <indexDimension name="group_dim">group_id</indexDimension>
      </index>
      <constraint name="description_length" type="check" definition="(length(description) > 5)"/>
    </table>
  </schema>
</dbsteward>
```

This is pretty self-explanatory, and basic in nature.  A few things to note:
* Definition of all roles is required, even if they're not used
* All database objects must be in a schema.  For MySQL, which has a mixed approach to schemas, put your tables in schema "public" and DBSteward will do the right thing, unless your are defining more than one MySQL database in your XML files, which would be delineated by schema in the XML.
* To allow a single definition to work on multiple RDBMS, some definition elements can specify a sqlFormat attribute (such as tableOption), which instructs DBSteward when they are used and when they aren't


## 2. Defining column default values

Column defaults are defined per column:

`<column name="examplecol" type="int" default="4"/>`

It's important to note that the default attribute takes an SQL expression, which means it's possible to use functions or other expressions if the target database has them available, for example:

`<column name="unique_id" type="UUID" default="gen_uuid()"/>`

However, this can be a bit confusing when dealing with text fields.  Since dbsteward does not attempt to analyze the underlying data type, it's up to the human to determine whether or not the value needs to be quoted.  For example:

`<column name="some_text" type="varchar" default="'default text'"/>`

This is required to allow the use of constants in default clauses, without them being ruined by automatic quoting:

`<column name="some_ts" type="timestamp" default="CURRENT_TIMESTAMP"/>`

If dbsteward quoted CURRENT_TIMESTAMP, the definition would not work as intended.  On the surface it seems like dbsteward should be able to determine whether or not to quote, but take the following example:

`<column name="some_text" type="varchar" default="CURRENT_TIMESTAMP"/>`

While the example seems illogical, it's completely valid, and if dbsteward attempted to automatically determine quoting, would be translated incorrectly.


## 3. Defining Enumerations

Enumerations are considered to be objects independent of other database objects.  This is because this paradigm can be scaled down simpler systems (such as MySQL) but the simpler paradigm can not be reliably scaled up to more complex systems.

Essentially, this means that an enumeration in dbsteward is created as a new datatype in dbsteward, then used in the type attribute for a column.  For systems that do not support this type of dereferencing and type definition, dbsteward handles the substitution.

An example:

    <type type="enum" name="directions">
      <enum name="north"/>
      <enum name="south"/>
      <enum name="east"/>
      <enum name="west"/>
    </type>

The type can then be used:

`<column name="which_way_did_he_go" type="directions" />`

On systems that allow the custom definition of types, the "directions" data type will be created and used, while system that do not support his will see the definition expanded inline.  For example:

    CREATE TABLE some_table (
      which_way_did_he_go ENUM('north', 'south', 'east', 'west')
    );

When extracting an existing database into XML that contains enumerations and does not support custom data types (such as MySQL) dbsteward has to generate unique names for the enumerations.  It does this by taking the string "enum_" and appending the MD5 of a concatenation of the possible enumeration values.  This allows dbsteward to collapse similar enumerations into a single type, while ensuring that each unique enumeration is defined uniquely.

After extraction, the enumeration names can (and probably should) be manually changed to have more rational values.  Be warned that if the resultant XML is used to produce a database on a system that does support custom types (such as PostgreSQL) the renaming of an enumeration is not transparent and may result in significant ALTERs to tables that are not strictly necessary.



## 4. Defining Foreign Keys

When foreign keys are indicated by the data structure, the attributes for the column tag are noticeably different than when not.  As always, the DTD is the definitive reference, but to summarize the attributes and their requirements:

| Attribute        | When foreign keyed | When not foreign keyed |
| ---------------- | ------------------ | ---------------------- |
| name             | required           | required               |
| type             | required           | *forbidden*            |
| foreignSchema    | *forbidden*        | required               |
| foreignTable     | *forbidden*        | required               |
| foreignColumn    | *forbidden*        | required               |
| foreignKeyName   | *forbidden*        | required               |
| foreignIndexName | *forbidden*        | _optional_             |
| foreignOnDelete  | *forbidden*        | _optional_             |
| foreignOnUpdate  | *forbidden*        | _optional_             |

From a high level, dbsteward manages datatypes between foreign key constraints automatically, searching for the table and column referenced to determine what datatype to use.  Take the following definitions:

```XML
<table name="parent">
  <column name="parent_col" type="int"/>
</table>
<table name="child">
  <column name="ref_col" foreignSchema="public" foreignTable="parent" foreignColumn="parent_col"/>
</table>
```

The data type of *ref_col* will automatically be determined to be *int*to match *parent_col*.  Furthermore, if an upgrade cycle changes *parent_col* to a *bigint*, dbsteward will automatically generate ALTER statements to change *ref_col* to match.

When extracting an existing database into XML, DBSteward does *not* verify that foreign column data types match their parents, thus a poorly maintained schema may not be accurately extracted into XML and unexpected things may result.  At this time, it is the responsibility of humans to ensure that XML extraction accurately represents foreign key relationships.


## 5. Notating table or column renaming

During the development life of an application, table or columns need to be re-named, not re-created. The mechanism for notating this is the oldObjectName construct such as oldTableName or oldColumnName.

Previous database XML definition:
```XML
<table name="tableA" primaryKey="tableA_id">
  <column name="tableA_id" type="int"/>
  <column name="tableA_name" type="varchar(100)"/>
</table>

<table name="tableB" primaryKey="tableB_id">
  <column name="tableB_id" type="int"/>
  <column name="tableB_value" type="numeric"/>
</table>
```

New database XML definition with renamed table and column:

Previous database XML definition:
```XML
<table name="refinedTableA" oldSchemaName="public" oldTableName="tableA" primaryKey="tableA_id">
  <column name="tableA_id" type="int"/>
  <column name="tableA_name" type="varchar(100)"/>
</table>

<table name="tableB" primaryKey="tableB_id">
  <column name="tableB_id" type="int"/>
  <column name="tableB_business_value" oldColumnName="tableB_value" type="numeric"/>
  <column name="referenceTableB_value" oldColumnName="tableB_value" type="numeric"/>
</table>
```

In the above new definition, tableA is noted to be renamed to refinedTableA, and tableB_value is noted to be renamed to referenceTableB_value with the new column tableB_business_value being added.

When moving to the next development version adfter the new definition in this context, the oldObjectName attributes can be stripped as they are no longer needed to specify the object renaming. Some developers prefer to leave the oldObjectName references. This mostly depends on your rate of change and need to refer to legacy column names.


## 6. Column rename and data transforms

You have to rename a column as part of a data migration transform to support a feature change, inherently, you need to preserve the data in that column. What do?

Take this scenario: queue_file_data is type text to store base64 encoded data, but you need it to be bytea for binary file storage for large object streaming. This is how you get there:

Existing table - Version A
```XML
<table name="queue" primaryKey="queue_id" slonyId="20" owner="ROLE_OWNER">
  <column name="queue_id" type="serial" slonyId="20"/>
  <column name="queue_entry_time" type="timestamp with time zone"/>
  <column name="queue_file_name" type="character varying(300)"/>
  <column name="queue_file_data" type="text"/>
  <column name="queue_file_data_checksum" type="text"/>
  <column name="queue_lock_time" type="timestamp with time zone"/>
  <column name="queue_fetch_time" type="timestamp with time zone"/>
  <grant operation="SELECT,INSERT,UPDATE" role="ROLE_APPLICATION"/>
</table>
```

Transitional table - Version B
```XML
<table name="queue" primaryKey="queue_id" slonyId="20" owner="ROLE_OWNER">
  <column name="queue_id" type="serial" slonyId="20"/>
  <column name="queue_entry_time" type="timestamp with time zone"/>
  <column name="queue_file_name" type="character varying(300)"/>
  <column name="queue_file_data_oldbase64" type="text" oldColumnName="queue_file_data" description="pre version V, this column for base64 text storage of file contents"/>
  <column name="queue_file_data_newbytea" type="bytea" description="version >= C column for bytea binary storage of file contents"/>
  <column name="queue_file_data_checksum" type="text"/>
  <column name="queue_lock_time" type="timestamp with time zone"/>
  <column name="queue_fetch_time" type="timestamp with time zone"/>
  <grant operation="SELECT,INSERT,UPDATE" role="ROLE_APPLICATION"/>
</table>
```

Final desired table form - Version C
```XML
<table name="queue" primaryKey="queue_id" slonyId="20" owner="ROLE_OWNER">
  <column name="queue_id" type="serial" slonyId="20"/>
  <column name="queue_entry_time" type="timestamp with time zone"/>
  <column name="queue_file_name" type="character varying(300)"/>
  <column name="queue_file_data" type="bytea" oldColumnName="queue_file_data_newbytea"/>
  <column name="queue_file_data_checksum" type="text"/>
  <column name="queue_lock_time" type="timestamp with time zone"/>
  <column name="queue_fetch_time" type="timestamp with time zone"/>
  <grant operation="SELECT,INSERT,UPDATE" role="ROLE_APPLICATION"/>
</table>
```

This is the upgrade process that will be managed as these 3 versions A, B, and C

1. Create an old and new version of the column in Version B, for data transformation
2. Upgrade the application to version B
3. queue_file_data will be renamed queue_file_data_oldbase64 instead of dropped
4. Transform the data from queue_file_data_oldbase64 to the new column queue_file_data_newbytea as bytea data
5. Use the application / wait for next maintenance window
6. Upgrade the application to version C
7. queue_file_data_newbytea will be renamed queue_file_data instead of dropped - application code version C knows the column as queue_file_data


## 7. Column data transforms and defaults for columns being added to satisfy new constraints

Adding columns to existing tables that that need immediate data transforms applied to meet constraints that are about to be applied.

Four column add attributes may be defined to have DBSteward include SQL statements adjacent to table structure changes:

- beforeAddStage1
- afterAddStage1
- beforeAddStage3
- afterAddStage3

Consider this scenario: a column added to table that will become part of the primary key.

DBSteward table definition in previous version:

```XML
<table name="registration_steps_completed" owner="ROLE_OWNER" primaryKey="user_id, registration_step_list_id" slonyId="385">
  <column name="user_id" null="false" foreignSchema="public" foreignTable="entity" foreignColumn="user_id"/>
  <column name="registration_step_list_id" null="false" foreignSchema="public" foreignTable="registration_step_list" foreignColumn="registration_step_list_id"/>
  <column name="status_message" type="text"/>
  <column name="app_mode"  null="false" foreignSchema="public" foreignTable="app_mode" foreignColumn="app_mode"/>
  <column name="visited" type="boolean" default="false"/>
  <column name="needs_attention" type="boolean" default="false"/>
  <column name="completed" type="boolean" default="false"/>
  <grant role="ROLE_APPLICATION" operation="SELECT, INSERT, UPDATE"/>
</table>
```

DBSteward table definition in current version:

```XML
<table name="registration_steps_completed" owner="ROLE_OWNER" primaryKey="user_id, registration_step_list_id, step_rank" slonyId="385">
  <column name="user_id" null="false" foreignSchema="public" foreignTable="entity" foreignColumn="user_id"/>
  <column name="registration_step_list_id" null="false" foreignSchema="public" foreignTable="registration_step_list" foreignColumn="registration_step_list_id"/>
  <column name="step_rank" type="integer" null="false" afterAddStage1="UPDATE registration_steps_completed SET step_rank = 1 WHERE step_rank IS NULL;"/>
  <column name="status_message" type="text"/>
  <column name="app_mode"  null="false" foreignSchema="public" foreignTable="app_mode" foreignColumn="app_mode"/>
  <column name="visited" type="boolean" default="false"/>
  <column name="needs_attention" type="boolean" default="false"/>
  <column name="completed" type="boolean" default="false"/>
  <grant role="ROLE_APPLICATION" operation="SELECT, INSERT, UPDATE"/>
</table>
```
 

DBSteward then outputs this SQL to achieve adding the column and making it part of the new primary key:

```SQL
--- someapp_v2_upgrade_stage1_schema1.sql
ALTER TABLE public.registration_steps_completed
DROP CONSTRAINT registration_steps_completed_pkey;
ALTER TABLE public.registration_steps_completed
DROP COLUMN step_completed ,
ADD COLUMN step_rank integer ;
UPDATE registration_steps_completed SET step_rank = 1 WHERE step_rank IS NULL; -- from public.registration_steps_completed.step_rank afterAddStage1 definition
ALTER TABLE public.registration_steps_completed
ADD CONSTRAINT registration_steps_completed_pkey PRIMARY KEY (user_id, registration_step_list_id, step_rank);
--- someapp_v2_upgrade_schema_stage2.sql
ALTER TABLE public.registration_steps_completed
ALTER COLUMN step_rank SET NOT NULL;
```
