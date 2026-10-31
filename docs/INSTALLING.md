# Installing DBSteward

## Requirements
- PHP 8.3 or newer with the `dom`, `pcre`, `pgsql`, `simplexml` and `spl` extensions (`pdo_mysql` to extract MySQL schemas)
- `xmllint` (Debian/Ubuntu package `libxml2-utils`) on your PATH

DBSteward 1.4.3 and older run on PHP 5.5 – 7.x only. PHP 8.3+ support is on the master
branch and will ship in the next release; until that release is tagged, install `dev-master`.


## Installing via Composer
Installing DBSteward as a [Composer](https://getcomposer.org/) global [vendor binary](https://getcomposer.org/doc/articles/vendor-binaries.md) allows you to use the bin/dbsteward file anywhere, and track master branch if that better suits you.
```bash
composer global require dbsteward/dbsteward:dev-master
```

Or, install a particular version:
```bash
composer global require dbsteward/dbsteward:1.4.3
```

> Packagist also lists this repository under its old name, `nkiraly/dbsteward`. Prefer
> `dbsteward/dbsteward`, the name declared in composer.json.

You may need to add the Composer global bin directory to your PATH environment variable to streamline dbsteward usage:
```bash
export PATH="$PATH:$(composer global config bin-dir --absolute --quiet)"
```

With the composer global vendor binary installed, now you can run by just referring to the vendor binary as **dbsteward**. See [Using DBSteward](USING.md) for examples.

## Updating via Composer
Updating your composer global package is as easy as re-requiring the global dbsteward/dbsteward package and specifying the version you want to switch to using:
```bash
composer global require dbsteward/dbsteward:1.4.3
```

Or, if you have several global composer dependencies, do a global update:
```bash
composer global update
```

To see what version / tag of DBSteward you have installed globally, use show:
```bash
composer global show -i
```
