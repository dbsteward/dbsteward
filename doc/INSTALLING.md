# Installing DBSteward


## Installing via Composer
Installing DBSteward as a [Composer] (https://getcomposer.org/) global [vendor binary] (https://getcomposer.org/doc/articles/vendor-binaries.md) allows you to use the bin/dbsteward file anywhere, and track master branch if that better suits you.
```bash
composer global require nkiraly/dbsteward:dev-master
```

Or, install a particular version:
```bash
composer global require nkiraly/dbsteward:1.4.2
```

You may need to add the composer bin path to your PATH environment variable to streamline dbsteward usage:
```bash
export PATH=$PATH:~/.composer/vendor/bin
```

With the composer global vendor binary installed, now you can run by just referring to the vendor binary as **dbsteward**. See [Using DBSteward](
https://github.com/nkiraly/DBSteward/blob/master/doc/USING.md) for examples.

## Updating via Composer
Updating your composer global package is as easy as re-requiring the global nkiraly/dbsteward package and specifying the version you want to switch to using:
```bash
composer global require nkiraly/dbsteward:1.4.2
```

Or, if you have several global composer dependencies, do a global update:
```bash
composer global update
```

To see what version / tag of DBSteward you have installed globally, use show:
```bash
composer global show -i
```
