#!/usr/bin/env bash
# Create the users and database the test suite expects (see phpunit.xml.dist).
# Usage: prepare-test-db.sh pgsql8|mysql5
# Connection: PGSQL8_DBHOST / MYSQL5_DBHOST (default 127.0.0.1); the PostgreSQL
# superuser password is read from PGPASSWORD (default "postgres"), MySQL root has
# no password.
set -euo pipefail

case "${1:-}" in
  pgsql8)
    PGPASSWORD="${PGPASSWORD:-postgres}" psql -h "${PGSQL8_DBHOST:-127.0.0.1}" -U postgres -v ON_ERROR_STOP=1 <<'SQL'
CREATE USER deployment WITH SUPERUSER CREATEDB CREATEROLE PASSWORD 'password1';
CREATE ROLE dbsteward_phpunit_app;
CREATE DATABASE dbsteward_phpunit OWNER deployment;
SQL
    ;;
  mysql5)
    mysql -h "${MYSQL5_DBHOST:-127.0.0.1}" -uroot <<'SQL'
CREATE USER 'deployment'@'%' IDENTIFIED BY 'password1';
CREATE USER 'dbsteward_pu_app'@'%' IDENTIFIED BY 'password1';
GRANT ALL ON *.* TO 'deployment'@'%', 'dbsteward_pu_app'@'%' WITH GRANT OPTION;
CREATE DATABASE dbsteward_phpunit;
SET GLOBAL log_bin_trust_function_creators = 1;
SQL
    ;;
  *)
    echo "usage: $0 pgsql8|mysql5" >&2
    exit 2
    ;;
esac
