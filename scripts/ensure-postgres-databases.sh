#!/usr/bin/env sh
set -eu

database_host="${DB_HOST:-postgres}"
database_port="${DB_PORT:-5432}"
database_user="${DB_USERNAME:-postgres}"
database_password="${DB_PASSWORD:-}"
primary_database="${DB_DATABASE:-postgres}"
test_database="${DB_TEST_DATABASE:-}"

export PGPASSWORD="${database_password}"

testing_database_from_file() {
    if [ ! -f ".env.testing" ]; then
        return
    fi

    awk -F= '
        $1 == "DB_DATABASE" {
            print substr($0, index($0, $2))
            exit
        }
    ' .env.testing
}

database_exists() {
    psql \
        -h "${database_host}" \
        -p "${database_port}" \
        -U "${database_user}" \
        -d postgres \
        -tAc "SELECT 1 FROM pg_database WHERE datname = '$1'" \
        | grep -q '^1$'
}

ensure_database() {
    database_name="$1"

    if [ -z "${database_name}" ]; then
        return
    fi

    if database_exists "${database_name}"; then
        printf 'Database %s already exists.\n' "${database_name}"

        return
    fi

    createdb \
        -h "${database_host}" \
        -p "${database_port}" \
        -U "${database_user}" \
        "${database_name}"

    printf 'Created database %s.\n' "${database_name}"
}

ensure_database "${primary_database}"

if [ -z "${test_database}" ]; then
    test_database="$(testing_database_from_file)"
fi

if [ -n "${test_database}" ] && [ "${test_database}" != "${primary_database}" ]; then
    ensure_database "${test_database}"
fi
