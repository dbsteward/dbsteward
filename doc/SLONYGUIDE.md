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

