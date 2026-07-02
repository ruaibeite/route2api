#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FIXTURE="$ROOT/tests/fixtures/thinkphp"
OUT="$ROOT/tests/tmp"

rm -rf "$OUT"
mkdir -p "$OUT"

php "$ROOT/bin/route2api" scan --path="$FIXTURE" --framework=thinkphp --output="$OUT" --format=openapi,yaml,postman,markdown,html

test -f "$OUT/openapi.json"
test -f "$OUT/openapi.yaml"
test -f "$OUT/postman_collection.json"
test -f "$OUT/api.md"
test -f "$OUT/index.html"
grep -q "用户登录" "$OUT/openapi.json"
grep -q '"requestBody"' "$OUT/openapi.json"
grep -q '"username"' "$OUT/openapi.json"
grep -q '"remember"' "$OUT/openapi.json"
grep -q '"refresh"' "$OUT/openapi.json"
grep -q '"page_size"' "$OUT/openapi.json"
grep -q "用户登录" "$OUT/postman_collection.json"
grep -q '"body"' "$OUT/postman_collection.json"
grep -q "用户详情" "$OUT/api.md"
grep -q "用户详情" "$OUT/index.html"

CONFIG_OUT="$FIXTURE/docs/api"
rm -rf "$CONFIG_OUT"
php "$ROOT/bin/route2api" scan --path="$FIXTURE"
test -f "$CONFIG_OUT/openapi.json"
test -f "$CONFIG_OUT/openapi.yaml"
test -f "$CONFIG_OUT/postman_collection.json"
test -f "$CONFIG_OUT/api.md"
test -f "$CONFIG_OUT/index.html"
grep -q "Fixture API" "$CONFIG_OUT/openapi.json"
grep -q "https://api.example.test" "$CONFIG_OUT/openapi.json"
rm -rf "$CONFIG_OUT"

echo "Smoke test passed"
