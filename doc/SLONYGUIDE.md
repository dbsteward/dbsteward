# Slony Installation and Configuration with DBSteward Output Files


## 1. Slony Replication Installation Abstract Process

You have generated DBSteward definition files with --generateslonik and slonyId sanity has been confirmed. How do you install slony replication for your database?

The abstract process for installing slony replication on a DBSteward-generated database with DBSteward generated slonik is as follows:

On the origin database server AND all replicas,

1. Apply _build.sql with psql CLI

On the origin database server ONLY,

1. Run preamble + paths.slonik through slonik CLI to set your slony node paths

2. Run preamble + create.slonik through slonik CLI to create the replication set(s) defined

4. Run preamble + subscribe_node_N.slonik through slonik CLI to subscribe a slony node to the replication set(s) defined

5. Do subscribe_node_N.slonik step for each subscriber node


## 2. Slony Replicated Database Upgrade Abstract Process

You have generated upgrade files, but how do you upgrade your slony-replicated database with these DBSteward upgrade files?

The abstract process for upgrading a slony-replicated database that was installed with DBSteward generated slonik originally, is as follows:

On the origin database server, for each replication set,

1. Run preamble + stage1.slonik with slonik CLI

2. Run preamble + EXECUTE SCRIPT stage1.sql slonik command with slonik CLI

3. Run stage2.sql on the database with psql CLI

4. Run preamble + stage3.slonik with slonik CLI

5. Run preamble + EXECUTE SCRIPT stage3.sql slonik command with slonik CLI

6. Run stage4.sql on the database with psql CLI


## 3. someapp Deployment with Replication

The XML examples that come with DBSteward have slony replication configuration in them. Here is the process for building and deploying them. In addition the [DBSteward maven plugin](https://github.com/nkiraly/dbsteward-maven-plugin) is being worked on to streamline deployment as a series maven lifecycle tasks.

Build someapp v1
```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward/xml $ dbsteward --xml=someapp_v1.xml --generateslonik
[DBSteward-1] DBSteward Version 1.3.12
[DBSteward-1] Using sqlformat=pgsql8
[DBSteward-1] Compositing XML files..
[DBSteward-1] Loading XML /home/nicholas.kiraly/engineering/DBSteward/xml/someapp_v1.xml..
[DBSteward-1] Compositing XML File someapp_v1.xml
[DBSteward-1] Locating xmllint executable...
[DBSteward-1] Found xmllint
[DBSteward-1] Validating XML (size = 6366) against /home/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
[DBSteward-1] XML Validates (size = 6366) against /home/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
[DBSteward-1] Locating xmllint executable...
[DBSteward-1] Found xmllint
[DBSteward-1] Validating XML (size = 6273) against /home/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
[DBSteward-1] XML Validates (size = 6273) against /home/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
[DBSteward-1] XML files someapp_v1.xml composited
[DBSteward-1] Saving as ./someapp_v1_composite.xml
[DBSteward-1] Building complete file ./someapp_v1_build.sql
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v1_build.sql
[DBSteward-1] Calculating table foreign key dependency order..
[DBSteward-2] Detected LANGUAGE SQL function public.destroy_session referring to table public.session_information in the database definition
[DBSteward-1] Defining structure
[DBSteward-1] Defining data inserts
[DBSteward-1] Primary key user_id does not exist as a column in child table partition_0, but may exist in parent table
[DBSteward-1] Primary key user_id does not exist as a column in child table partition_1, but may exist in parent table
[DBSteward-1] Primary key user_id does not exist as a column in child table partition_2, but may exist in parent table
[DBSteward-1] Primary key user_id does not exist as a column in child table partition_3, but may exist in parent table
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_preamble.slonik
[DBSteward-1] Building slony replication set create file ./someapp_v1_slony_replica_set_500_create.slonik
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_create.slonik
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_paths.slonik
[DBSteward-1] Building slony replication set 500 node 102 subscription file ./someapp_v1_slony_replica_set_500_subscribe_node_102.slonik
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_subscribe_node_102.slonik
[DBSteward-1] Building slony replication set 500 node 103 subscription file ./someapp_v1_slony_replica_set_500_subscribe_node_103.slonik
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_subscribe_node_103.slonik
[DBSteward-1] Building slony replication set 500 node 104 subscription file ./someapp_v1_slony_replica_set_500_subscribe_node_104.slonik
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v1_slony_replica_set_500_subscribe_node_104.slonik
[DBSteward-1] [slony] ID summary: 8 tables 3 sequences
[DBSteward-1] [slony] table ID segments: 3, 10, 20, 30, 347-350
[DBSteward-1] [slony] sequence ID segments for slonySetId 500: 3, 10, 346
```

That gives us the following output files:
```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward/xml $ ls -l someapp_v1*
-rwxr-xr-x  1 nicholas.kiraly  6035   6413 Feb 11 16:24 someapp_v1.xml
-rw-r--r--  1 nicholas.kiraly  6035  10620 Apr 28 12:22 someapp_v1_build.sql
-rw-r--r--  1 nicholas.kiraly  6035   9938 Apr 28 12:22 someapp_v1_composite.xml
-rw-r--r--  1 nicholas.kiraly  6035   2412 Apr 28 12:22 someapp_v1_slony_replica_set_500_create.slonik
-rw-r--r--  1 nicholas.kiraly  6035   1513 Apr 28 12:22 someapp_v1_slony_replica_set_500_paths.slonik
-rw-r--r--  1 nicholas.kiraly  6035    466 Apr 28 12:22 someapp_v1_slony_replica_set_500_preamble.slonik
-rw-r--r--  1 nicholas.kiraly  6035    818 Apr 28 12:22 someapp_v1_slony_replica_set_500_subscribe_node_102.slonik
-rw-r--r--  1 nicholas.kiraly  6035    818 Apr 28 12:22 someapp_v1_slony_replica_set_500_subscribe_node_103.slonik
-rw-r--r--  1 nicholas.kiraly  6035    818 Apr 28 12:22 someapp_v1_slony_replica_set_500_subscribe_node_104.slonik
```

1. *someapp_v1_build.sql* is the full database create script and should be run on master and all replica node database servers.
2. *someapp_v1_slony_replica_set_500_preamble.slonik* is the slonik preamble that needs to be used for all slonik scripts
3. *someapp_v1_slony_replica_set_500_paths.slonik* defines the paths between slony nodes as defined in the slonyReplicaSet element.
4. *someapp_v1_slony_replica_set_500_create.slonik* creates the slony replication set for the tables in the XML with slonyIds specified.
5. *someapp_v1_slony_replica_set_500_subscribe_node_102.slonik* and subsequent subscribes each cascading replica to the master node 101


## 4. someapp Upgrade with Replication

The XML examples that come with DBSteward have slony replication configuration in them. Here is the process for building and applying an upgrade of the replicated application.

Build someapp v1 to v2 upgrade
```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward/xml $ dbsteward --oldxml=someapp_v1.xml --newxml=someapp_v2.xml --generateslonik
[DBSteward-1] DBSteward Version 1.3.12
[DBSteward-1] Using sqlformat=pgsql8
[DBSteward-1] Compositing old XML files..
[DBSteward-1] Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/someapp_v1.xml..
[DBSteward-1] Compositing XML File someapp_v1.xml
[DBSteward-1] Locating xmllint executable...
[DBSteward-1] Found xmllint
[DBSteward-1] Validating XML (size = 6366) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
[DBSteward-1] XML Validates (size = 6366) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
[DBSteward-1] Locating xmllint executable...
[DBSteward-1] Found xmllint
[DBSteward-1] Validating XML (size = 6273) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
[DBSteward-1] XML Validates (size = 6273) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
[DBSteward-1] Old XML files someapp_v1.xml composited
[DBSteward-1] Compositing new XML files..
[DBSteward-1] Loading XML /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/xml/someapp_v2.xml..
[DBSteward-1] Compositing XML File someapp_v2.xml
[DBSteward-1] Locating xmllint executable...
[DBSteward-1] Found xmllint
[DBSteward-1] Validating XML (size = 8069) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
[DBSteward-1] XML Validates (size = 8069) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
[DBSteward-1] Locating xmllint executable...
[DBSteward-1] Found xmllint
[DBSteward-1] Validating XML (size = 7413) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd
[DBSteward-1] XML Validates (size = 7413) against /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/lib/DBSteward/dbsteward.dtd OK
[DBSteward-1] New XML files someapp_v2.xml composited
[DBSteward-1] Saving as ./someapp_v1_composite.xml
[DBSteward-1] Saving as ./someapp_v2_composite.xml
[DBSteward-1] Calculating old table foreign key dependency order..
[DBSteward-1] Calculating new table foreign key dependency order..
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v2_upgrade_slony_replica_set_500_preamble.slonik
[DBSteward-3] [File Segment] Opening output file segement ./someapp_v2_upgrade_slony_replica_set_500_stage1_schema1.sql
[DBSteward-3] [File Segment] Opening output file segement ./someapp_v2_upgrade_slony_replica_set_500_stage2_data1.sql
[DBSteward-1] Drop Old Schemas
[DBSteward-1] Create New Schemas
[DBSteward-1] Update Structure
[DBSteward-3] [File Segment] Opening output file segement ./someapp_v2_upgrade_slony_replica_set_500_stage3_schema1.sql
[DBSteward-1] Update Permissions
[DBSteward-1] Update Data
[DBSteward-3] [File Segment] Opening output file segement ./someapp_v2_upgrade_slony_replica_set_500_stage4_data1.sql
[DBSteward-1] Generating replica set 500 upgrade slonik
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v2_upgrade_slony_replica_set_500_stage1.slonik
[DBSteward-3] [File Segment] Fixed output file: ./someapp_v2_upgrade_slony_replica_set_500_stage3.slonik
```

That gives us the following output files:
```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward/xml $ ls -l someapp_v2_upgrade*
-rw-r--r--  1 nicholas.kiraly  6035   474 Apr 28 12:33 someapp_v2_upgrade_slony_replica_set_500_preamble.slonik
-rw-r--r--  1 nicholas.kiraly  6035   343 Apr 28 12:33 someapp_v2_upgrade_slony_replica_set_500_stage1.slonik
-rw-r--r--  1 nicholas.kiraly  6035  2976 Apr 28 12:33 someapp_v2_upgrade_slony_replica_set_500_stage1_schema1.sql
-rw-r--r--  1 nicholas.kiraly  6035   375 Apr 28 12:33 someapp_v2_upgrade_slony_replica_set_500_stage2_data1.sql
-rw-r--r--  1 nicholas.kiraly  6035  1944 Apr 28 12:33 someapp_v2_upgrade_slony_replica_set_500_stage3.slonik
-rw-r--r--  1 nicholas.kiraly  6035   866 Apr 28 12:33 someapp_v2_upgrade_slony_replica_set_500_stage3_schema1.sql
-rw-r--r--  1 nicholas.kiraly  6035   757 Apr 28 12:33 someapp_v2_upgrade_slony_replica_set_500_stage4_data1.sql
```

1. *someapp_v2_upgrade_slony_replica_set_500_preamble.slonik* is the slonik preamble that needs to be used for all slonik scripts for replica set 500
2. *someapp_v2_upgrade_slony_replica_set_500_stage1.slonik* is the stage 1 slonik to be run to remove any tables that are no longer to be replicated to the slony configuration
3. *someapp_v2_upgrade_slony_replica_set_500_stage1_schema1.sql* is the stage 1 schema add/modify changes to be applied via slonik EXECUTE SCRIPT for the DDL will be applied to all replicas.
4. *someapp_v2_upgrade_slony_replica_set_500_stage2_data1.sql* is the stage 2 data add/modify changes to be run directly on the db with psql. Slony triggers will bring the DML changes to the replicas.
5. *someapp_v2_upgrade_slony_replica_set_500_stage3.slonik* is the stage 3 slonik to be run to add any tables that are new or now replicated to the slony configuration
6. *someapp_v2_upgrade_slony_replica_set_500_stage3_schema1.sql* is the stage 3 schema drop/modify changes to be applied via slonik EXECUTE SCRIPT for the DDL will be applied to all replicas.
7. *someapp_v2_upgrade_slony_replica_set_500_stage4_data1.sql* is the stage 4 data delete/modify changes to be run directly on the db with psql. Slony triggers will bring the DML changes to the replicas.
