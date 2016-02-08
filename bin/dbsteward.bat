@if "%DEBUG%" == "" @echo off
@rem ##########################################################################
@rem  dbsteward startup script for windows
@rem ##########################################################################

@rem Set local scope for the variables with windows NT shell
if "%OS%"=="Windows_NT" setlocal

set DIRNAME=%~dp0
if "%DIRNAME%" == "" set DIRNAME=.
set APP_BASE_NAME=%~n0
set APP_HOME=%DIRNAME%..

goto init

:init
set CMD_LINE_ARGS=%*
goto execute

:execute
php %DIRNAME%\dbsteward %CMD_LINE_ARGS%

:end
if "%ERRORLEVEL%"=="0" goto mainEnd

:mainEnd
if "%OS%"=="Windows_NT" endlocal