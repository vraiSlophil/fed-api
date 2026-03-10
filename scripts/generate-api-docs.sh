#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
cd "$ROOT_DIR"

DOCS_OUTPUT_DIR="public/docs"
SCRIBE_DIR=".scribe"

mkdir -p "$DOCS_OUTPUT_DIR" "$SCRIBE_DIR"

: "${APP_ENV:=local}"
: "${APP_DEBUG:=false}"
: "${DOCS_API_BASE_URL:=https://api.fed.test}"
DB_CONNECTION=sqlite
DB_DATABASE=/tmp/fed-api-docs.sqlite
: "${GENERATING_API_DOCS:=true}"
: "${CACHE_STORE:=array}"
: "${SESSION_DRIVER:=array}"
: "${QUEUE_CONNECTION:=sync}"
: "${MAIL_MAILER:=log}"

export APP_ENV APP_DEBUG DOCS_API_BASE_URL DB_CONNECTION DB_DATABASE GENERATING_API_DOCS
export CACHE_STORE SESSION_DRIVER QUEUE_CONNECTION MAIL_MAILER

if [ "$DB_CONNECTION" = "sqlite" ] && [ "$DB_DATABASE" != ":memory:" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
fi

php artisan config:clear --ansi
php artisan route:clear --ansi
php artisan scribe:generate --scribe-dir="$SCRIBE_DIR"
npx --no-install widdershins "$DOCS_OUTPUT_DIR/openapi.yaml" -o "$DOCS_OUTPUT_DIR/reference.md"
