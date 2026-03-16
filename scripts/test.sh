#!/usr/bin/env sh
set -eu

php artisan config:clear --ansi
exec php artisan test "$@"
