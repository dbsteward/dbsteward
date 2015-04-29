# Release Packaging Checklist


## 1. Check source code version labels

```bash
nicholas.kiraly@bludgeon  (master)
~/engineering/DBSteward $

phing clean

grep -ir 'version' build.xml lib/* .config.properties | grep '\d.\d'

```


## 2. Branch release by version number

```bash
git checkout -b 1.3.12
git config branch."1.3.12".remote origin
git config branch."1.3.12".merge refs/heads/1.3.12
git push
git pull
```


## 3. Build PEAR package

```bash
nicholas.kiraly@bludgeon  (1.3.12)
~/engineering/DBSteward $ phing

...

DBSteward > make:

     [echo] Creating PEAR archive
   [delete] Could not find file /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/DBSteward-1.3.12.tgz to delete.
   [delete] Directory /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/package does not exist or is not a directory.
    [mkdir] Created dir: /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/package/DBSteward-1.3.12
     [copy] Created 8 empty directories in /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/package/DBSteward-1.3.12
     [copy] Copying 139 files to /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/package/DBSteward-1.3.12
     [move] Moving 1 files to /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/package
      [tar] Building tar: /usr/home/nfs/nicholas.kiraly/engineering/DBSteward/DBSteward-1.3.12.tgz

BUILD FINISHED

Total time: 7.8931 seconds

```


## 4. Add PEAR package to pear channel with Pirum

```bash
nicholas.kiraly@bludgeon  (1.3.12)
~/engineering/DBSteward $ cd ~/engineering/pear.dbsteward.org/

nicholas.kiraly@bludgeon  (gh-pages)
~/engineering/pear.dbsteward.org $ pirum add . ../DBSteward/DBSteward-1.3.12.tgz
Pirum 1.1.5 by Fabien Potencier
Available commands:
  pirum build target_dir
  pirum add target_dir Pirum-1.0.0.tgz
  pirum remove target_dir Pirum-1.0.0.tgz

Running the add command:
   INFO   Parsing package DBSteward 1.3.12, DBSteward 1.3.11, DBSteward 1.3.10, DBSteward 1.3.9, DBSteward 1.3.8, DBSteward 1.3.7, DBSteward 1.3.6, DBSteward 1.3.5, DBSteward 1.3.4, DBSteward 1.3.3, DBSteward 1.3.2, DBSteward 1.3.1, DBSteward 1.3.0, DBSteward 1.2.4, DBSteward 1.2.3, DBSteward 1.2.2, DBSteward 1.2.1, DBSteward 1.2.0
   INFO   Building channel.
   INFO   Building maintainers.
   INFO   Building categories.
   INFO   Building packages.
   INFO   Building package DBSteward.
   INFO   Building composer repository.
   INFO   Building releases.
   INFO   Building releases for DBSteward.
   INFO   Building release 1.3.12, 1.3.11, 1.3.10, 1.3.9, 1.3.8, 1.3.7, 1.3.6, 1.3.5, 1.3.4, 1.3.3, 1.3.2, 1.3.1, 1.3.0, 1.2.4, 1.2.3, 1.2.2, 1.2.1, 1.2.0
   INFO   Building index.
   INFO   Building feed.
   INFO   Updating PEAR server files.
   INFO   Command add run successfully.

nicholas.kiraly@bludgeon  (gh-pages)
~/engineering/pear.dbsteward.org $ git add * get/* rest/*

nicholas.kiraly@bludgeon  (gh-pages)
~/engineering/pear.dbsteward.org $ git commit -a


DBSteward 1.3.12 Release Package

Changes
* allow infinite includeFile depth of definition fragment XML files
* update outdated sample XML
* View definition dependency support with new view element dependsOnViews attribute github/nkiraly/DBSteward PR #75
* fix SQL output file transactionality when generating stage files for execution by slonik github/nkiraly/DBSteward PR #76
* mysql5 column default removal fix github/nkiraly/DBSteward PR #77
* Contextualize pgsql8 VIEW Slony Replica Set ID DROP / CREATE
* Drop and recreate pgsql8 functions referring to types modified in the definition github/nkiraly/DBSteward PR #78
* Optional tabrow delimiter specificity github/nkiraly/DBSteward PR #79

nicholas.kiraly@bludgeon  (gh-pages)
~/engineering/pear.dbsteward.org $ git push

```



# Appendix A - packaging server configuration

```bash
export FTP_PASSIVE_MODE=1

pkg_add -r bash
pkg_add -r subversion
pkg_add -r git-subversion
pkg_add -r pear
pkg_add -r php5-dom
pkg_add -r php5-pgsql
pkg_add -r php5-mssql
pkg_add -r php-xdebug
pkg_add -r php5-simplexml
pkg_add -r php5-json
pkg_add -r php5-tokenizer
pkg_add -r php5-pcntl
pkg_add -r php5-zlib

cp /usr/local/etc/freetds.conf.dist /usr/local/etc/freetds.conf
vi  /usr/local/etc/freetds.conf
tds version = 8.0

vi /usr/local/etc/pear.conf

pear upgrade

pear channel-discover pear.symfony-project.com
pear channel-discover pear.symfony.com
pear channel-discover pear.pdepend.org
pear channel-discover pear.phpmd.org
pear channel-discover pear.phpunit.de
pear channel-discover pear.phpdoc.org
pear channel-discover pear.phing.info
pear install --alldeps phing/phing
pear install channel://pear.php.net/VersionControl_SVN-0.5.1
pear install channel://pear.php.net/VersionControl_Git-0.4.4

pear upgrade PhpDocumentor
pear install --alldeps phpunit/PHPUnit

pear channel-discover pear.domain51.com
pear install domain51/Phing_d51PearPkg2Task
pear install --alldeps channel://pear.domain51.com/Phing_d51PearPkg2Task-0.6.3
pear install channel://pear.php.net/Console_ProgressBar-0.5.2beta
pear install channel://pear.php.net/XML_Serializer-0.20.2

pear channel-discover pear.pirum-project.org
pear install pirum/Pirum

```


