@echo off

REM DBSteward
REM Database SQL compiler and differencing via XML definition
REM
REM @package DBSteward
REM @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
REM @author Nicholas J Kiraly <kiraly.nicholas@gmail.com>
REM
REM This batch file is to streamline running dbsteward from a repository checkout on windows.
REM Note that #126 describes how composer does this for package installations automatically
REM https://getcomposer.org/doc/articles/vendor-binaries.md#what-about-windows-and-bat-files-

php %~dp0\dbsteward %*
