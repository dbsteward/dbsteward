# DBSteward
SQL database definition differencing tool. Structure and data is defined in a DTD-enforced, human-readable XML format. Outputs transactional SQL statement files to apply your changes.

## Want Updates?
Subscribe to the [DBSteward Announce](https://groups.google.com/forum/#!forum/dbsteward-announce) mailing list

## Need Help?
Post your question to the [DBSteward Users](https://groups.google.com/forum/#!forum/dbsteward-users) mailing list


## What / Who is DBSteward for?

Intended users are application developers and database administrators who maintain database structure changes as part of an application life cycle. Defining your SQL database in a DBSteward XML definition can greatly lower your release engineering costs by removing the need to write and test SQL changes.

Many developers maintain complete and upgrade script versions of their application databases. Upgrade headaches or data loss are reduced by only requiring a developer to maintain a complete definition file. Creating an upgrade from version A to B becomes a compile task, where you ask DBSteward to generate SQL  changes by feeding it A and B versions of your database in XML.
