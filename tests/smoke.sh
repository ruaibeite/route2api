#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FIXTURE="$ROOT/tests/fixtures/thinkphp"
OUT="$ROOT/tests/tmp"

rm -rf "$OUT"
mkdir -p "$OUT"

php "$ROOT/bin/route2api" scan --path="$FIXTURE" --framework=thinkphp --output="$OUT" --format=openapi,markdown

test -f "$OUT/openapi.json"
test -f "$OUT/api.md"
grep -q "用户登录" "$OUT/openapi.json"
grep -q "用户详情" "$OUT/api.md"

echo "Smoke test passed"
