#!/usr/bin/env bash
# Build an installable plugin zip: dist/fanoos.zip
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
rm -f dist/fanoos.zip
zip -rq dist/fanoos.zip fanoos -x '*.DS_Store'
echo "dist/fanoos.zip"
