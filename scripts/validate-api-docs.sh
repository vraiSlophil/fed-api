#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
cd "$ROOT_DIR"

sh ./scripts/generate-api-docs.sh
npx --no-install @redocly/cli lint public/docs/openapi.yaml
