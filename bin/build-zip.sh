#!/usr/bin/env bash
# Build an installable plugin zip: dist/ostorlab.zip
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
rm -f dist/ostorlab.zip
zip -rq dist/ostorlab.zip ostorlab -x '*.DS_Store'
echo "dist/ostorlab.zip"
