@echo off

REM DBSteward
REM Database SQL compiler and differencing via XML definition
REM
REM @package DBSteward
REM @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
REM @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>

php %~dp0\dbsteward %*
