#DBSteward Installation


##Installing via Composer
Installing DBSteward as a [Composer] (https://getcomposer.org/) global [vendor binary] (https://getcomposer.org/doc/articles/vendor-binaries.md) allows you to use the bin/dbsteward file anywhere, and track master branch if that better suits you.
```bash
composer global require nkiraly/dbsteward:dev-master
```

Or, install a particular version:
```bash
composer global require nkiraly/dbsteward:1.3.9
```

You may need to add the composer bin path to your PATH environment variable to streamline dbsteward usage:
```bash
export PATH=$PATH:~/.composer/vendor/bin
```

With the composer global vendor binary installed, now you can run by just referring to the vendor binary as **dbsteward**. See [Using DBSteward](https://github.com/nkiraly/DBSteward/wiki/Crash-course#using-dbsteward).

##Updating via Composer
Updating your composer global package is as easy as re-requiring the global nkiraly/dbsteward package and specifying the version you want to switch to using:
```bash
composer global require nkiraly/dbsteward:1.3.11
```

Or, if you have several global composer dependencies, do a global update:
```bash
composer global update
```

To see what version / tag of DBSteward you have installed globally, use show:
```bash
composer global show -i
```

##Installing via PEAR
To install DBSteward and get rolling via [PHP PEAR](http://pear.php.net),

The PEAR channel URL is http://pear.dbsteward.org/

Discover the channel and install DBSteward with the following commands:

```bash
pear channel-discover pear.dbsteward.org
pear install dbsteward/DBSteward
```

With the PEAR package installed, now you can refer to the dbsteward binary with just **dbsteward**. See [Using DBSteward](https://github.com/nkiraly/DBSteward/wiki/Crash-course#using-dbsteward).

##Upgrading via PEAR
If your workstation already has PEAR and a version of DBSteward installed, these pear commands will ensure that the DBSteward PEAR channel is up to date and you are running the latest version.

```bash
sudo pear clear-cache
sudo pear channel-update pear.dbsteward.org
sudo pear remote-list -c dbsteward
sudo pear upgrade dbsteward/DBSteward
```

Example in action on a workstation:
```bash
[nicholas.kiraly@bludgeon ~]$ sudo pear clear-cache
reading directory /tmp/pear/cache
4 cache entries cleared
 
[nicholas.kiraly@bludgeon ~]$ sudo pear channel-update pear.dbsteward.org
Updating channel "pear.dbsteward.org"
Channel "pear.dbsteward.org" is up to date
 
[nicholas.kiraly@bludgeon ~]$ sudo pear remote-list -c pear.dbsteward.org
Channel pear.dbsteward.org Available packages:
==============================================
Package   Version
DBSteward 1.3.11
 
[nicholas.kiraly@bludgeon ~]$ sudo pear upgrade dbsteward/DBSteward
Nothing to upgrade
```
