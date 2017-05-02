# Slony Installation and Configuration with DBSteward Output Files


## 1. Slony Replication Installation Abstract Process

You have generated DBSteward definition files with --generateslonik and slonyId sanity has been confirmed. How do you install slony replication for your database?

The abstract process for installing slony replication on a DBSteward-generated database with DBSteward generated slonik is as follows:

On the origin database server AND all replicas,

1. Apply _build.sql with psql CLI

2. Start all slons for all slony database nodes

On the origin database server ONLY,

3. Run create_nodes.slonik with slonik CLI to store your nodes and connection paths

4. Run subscribe.slonik with slonik CLI to subscribe your nodes to your defined replica sets


## 2. Slony Replicated Database Upgrade Abstract Process

You have generated upgrade files, but how do you upgrade your slony-replicated database with these DBSteward upgrade files?

The abstract process for upgrading a slony-replicated database that was installed with DBSteward generated slonik originally, is as follows:

On the origin database server, for each replication set,

1. Run stage1.slonik with slonik CLI

2. Run stage2.sql on the database with psql CLI

3. Run stage3.slonik slonik CLI

4. Run stage4.sql on the database with psql CLI


## 3. someapp Deployment with Replication

The XML examples that come with DBSteward have slony replication configuration in them. Here is the process for building and deploying them. In addition the [DBSteward maven plugin](https://github.com/dbsteward/dbsteward-maven-plugin) is being worked on to streamline deployment as a series maven lifecycle tasks.

Build someapp v1
```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward/xml $ dbsteward --xml=someapp_v1.xml --generateslonik -v
NOTICE   DBSteward Version 1.4.0
NOTICE   Using sqlformat=pgsql8
INFO     Compositing XML files..
NOTICE   Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/someapp_v1.xml..
NOTICE   Compositing XML File someapp_v1.xml
INFO     Validating XML (size = 6366) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
INFO     XML Validates (size = 6366) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
INFO     Validating XML (size = 6273) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
INFO     XML Validates (size = 6273) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
INFO     XML files someapp_v1.xml composited
NOTICE   Saving composite as ./someapp_v1_composite.xml
INFO     Building complete file ./someapp_v1_build.sql
NOTICE   [File Segment] Fixed output file: ./someapp_v1_build.sql
INFO     Calculating table foreign key dependency order..
INFO     Detected LANGUAGE SQL function public.destroy_session referring to table public.session_information in the database definition
INFO     Defining structure
INFO     Defining data inserts
INFO     Primary key user_id does not exist as a column in child table partition_0, but may exist in parent table
INFO     Primary key user_id does not exist as a column in child table partition_1, but may exist in parent table
INFO     Primary key user_id does not exist as a column in child table partition_2, but may exist in parent table
INFO     Primary key user_id does not exist as a column in child table partition_3, but may exist in parent table
NOTICE   Building slonik pramble for replica set ID 500 output file ./someapp_v1_slony_replica_set_500_create_nodes.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_create_nodes.slonik
NOTICE   Building slonik STORE NODEs for replica set ID 500 output file ./someapp_v1_slony_replica_set_500_create_nodes.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_create_nodes.slonik
NOTICE   Building slonik STORE PATHs for replica set ID 500 output file ./someapp_v1_slony_replica_set_500_create_nodes.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_create_nodes.slonik
NOTICE   Building slonik pramble for replica set ID 500 output file ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   Building slonik CREATE SET for replica set ID 500 output file ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   Building slonik replica set ID 500 node ID 102 subscription output file ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   Building slonik replica set ID 500 node ID 103 subscription output file ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   Building slonik replica set ID 500 node ID 104 subscription output file ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_subscribe.slonik
NOTICE   [slony] ID summary: 8 tables 3 sequences
NOTICE   [slony] table ID segments: 3, 10, 20, 30, 347-350
NOTICE   [slony] sequence ID segments for slonySetId 500: 3, 10, 346
```

That gives us the following output files:
```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward/xml $ ls -l someapp_v1*
-rwxr-xr-x  1 nicholas.kiraly  6035   6413 Jun 29 22:12 someapp_v1.xml
-rw-r--r--  1 nicholas.kiraly  6035  10620 Jul  7 01:28 someapp_v1_build.sql
-rw-r--r--  1 nicholas.kiraly  6035   9938 Jul  7 01:28 someapp_v1_composite.xml
-rw-r--r--  1 nicholas.kiraly  6035   2514 Jul  7 01:28 someapp_v1_slony_replica_set_500_create_nodes.slonik
-rw-r--r--  1 nicholas.kiraly  6035   5211 Jul  7 01:28 someapp_v1_slony_replica_set_500_subscribe.slonik
```

1. *someapp_v1_build.sql* is the full database create script and should be run on master and all replica node database servers.
2. *someapp_v1_slony_replica_set_500_create_nodes.slonik* stores cluster node information and the connection paths between them. This initializes the slony nodes and slon processes will then start to operate on them.
3. *someapp_v1_slony_replica_set_500_subscribe.slonik* subscribes each node to the specified tables in replica set 500.


## 4. someapp Upgrade with Replication

The XML examples that come with DBSteward have slony replication configuration in them. Here is the process for building and applying an upgrade of the replicated application.

Build someapp v1 to v2 upgrade
```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward/xml $ dbsteward --oldxml=someapp_v1.xml --newxml=someapp_v2.xml --generateslonik -v
NOTICE   DBSteward Version 1.4.0
NOTICE   Using sqlformat=pgsql8
INFO     Compositing old XML files..
NOTICE   Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/someapp_v1.xml..
NOTICE   Compositing XML File someapp_v1.xml
INFO     Validating XML (size = 6366) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
INFO     XML Validates (size = 6366) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
INFO     Validating XML (size = 6273) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
INFO     XML Validates (size = 6273) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
INFO     Old XML files someapp_v1.xml composited
INFO     Compositing new XML files..
NOTICE   Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/someapp_v2.xml..
NOTICE   Compositing XML File someapp_v2.xml
INFO     Validating XML (size = 8069) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
INFO     XML Validates (size = 8069) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
INFO     Validating XML (size = 7413) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
INFO     XML Validates (size = 7413) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
INFO     New XML files someapp_v2.xml composited
NOTICE   Saving oldxml composite as ./someapp_v1_composite.xml
NOTICE   Saving newxml composite as ./someapp_v2_composite.xml
INFO     Calculating old table foreign key dependency order..
INFO     Calculating new table foreign key dependency order..
NOTICE   [File Segment] Opening output file segement ./someapp_v2_upgrade_slony_replica_set_500_stage1_schema1.sql
NOTICE   [File Segment] Opening output file segement ./someapp_v2_upgrade_slony_replica_set_500_stage2_data1.sql
INFO     Drop Old Schemas
INFO     Create New Schemas
INFO     Update Structure
NOTICE   [File Segment] Opening output file segement ./someapp_v2_upgrade_slony_replica_set_500_stage3_schema1.sql
INFO     Update Permissions
INFO     Update Data
NOTICE   [File Segment] Opening output file segement ./someapp_v2_upgrade_slony_replica_set_500_stage4_data1.sql
INFO     Generating replica set 500 upgrade slonik
NOTICE   Building slonik upgrade replica set ID 500
NOTICE   Building slonik pramble for replica set ID 500 output file ./someapp_v2_upgrade_slony_replica_set_500_stage1.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v2_upgrade_slony_replica_set_500_stage1.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v2_upgrade_slony_replica_set_500_stage1.slonik
NOTICE   Building slonik pramble for replica set ID 500 output file ./someapp_v2_upgrade_slony_replica_set_500_stage3.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v2_upgrade_slony_replica_set_500_stage3.slonik
NOTICE   [File Segment] Fixed output file: ./someapp_v2_upgrade_slony_replica_set_500_stage3.slonik
```

That gives us the following output files:
```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward/xml $ ls -l someapp_v2_upgrade*
-rw-r--r--  1 nicholas.kiraly  6035   978 Jul  7 01:33 someapp_v2_upgrade_slony_replica_set_500_stage1.slonik
-rw-r--r--  1 nicholas.kiraly  6035  2981 Jul  7 01:33 someapp_v2_upgrade_slony_replica_set_500_stage1_schema1.sql
-rw-r--r--  1 nicholas.kiraly  6035   375 Jul  7 01:33 someapp_v2_upgrade_slony_replica_set_500_stage2_data1.sql
-rw-r--r--  1 nicholas.kiraly  6035  2579 Jul  7 01:33 someapp_v2_upgrade_slony_replica_set_500_stage3.slonik
-rw-r--r--  1 nicholas.kiraly  6035   871 Jul  7 01:33 someapp_v2_upgrade_slony_replica_set_500_stage3_schema1.sql
-rw-r--r--  1 nicholas.kiraly  6035   757 Jul  7 01:33 someapp_v2_upgrade_slony_replica_set_500_stage4_data1.sql
```

1. *someapp_v2_upgrade_slony_replica_set_500_stage1.slonik* is the stage 1 slonik to be run to remove any tables that are no longer to be replicated to the slony configuration, and run add/modify DDL/DCL on all nodes with slonik EXECUTE SCRIPT *someapp_v2_upgrade_slony_replica_set_500_stage1_schema1.sql*
2. *someapp_v2_upgrade_slony_replica_set_500_stage2_data1.sql* is the stage 2 data add/modify changes to be run directly on the db with psql. Slony triggers will bring the DML changes to the replicas.
3. *someapp_v2_upgrade_slony_replica_set_500_stage3.slonik* is the stage 3 slonik to be run to add any tables that are new or now replicated to the slony configuration, and modify/remove DDL/DCL on all nodes with slonik EXECUTE SCRIPT *someapp_v2_upgrade_slony_replica_set_500_stage3_schema1.sql*
4. *someapp_v2_upgrade_slony_replica_set_500_stage4_data1.sql* is the stage 4 data modify/delete changes to be run directly on the db with psql. Slony triggers will bring the DML changes to the replicas.
