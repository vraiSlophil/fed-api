#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
cd "$ROOT_DIR"

sh ./scripts/generate-api-docs.sh

if [ ! -f public/docs/openapi.yaml ]; then
    printf '%s\n' "Expected public/docs/openapi.yaml to be generated." >&2
    exit 1
fi
