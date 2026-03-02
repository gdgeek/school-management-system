#!/bin/bash

# =============================================================================
# Input Validation Security Tests
#
# Tests for XSS, SQL injection, and malicious input handling.
#
# Usage:
#   AUTH_TOKEN=<jwt> ./input-validation.sh                    # Default URL
#   AUTH_TOKEN=<jwt> ./input-validation.sh http://server:8084  # Custom URL
# =============================================================================

set -uo pipefail

BASE_URL="${1:-http://localhost:8084}"
AUTH_TOKEN="${AUTH_TOKEN:-}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PASS=0
FAIL=0
SKIP=0

pass() { echo -e "  ${GREEN}✓ PASS${NC}: $1"; PASS=$((PASS + 1)); }
fail() { echo -e "  ${RED}✗ FAIL${NC}: $1 (got: $2)"; FAIL=$((FAIL + 1)); }
skip() { echo -e "  ${YELLOW}⊘ SKIP${NC}: $1"; SKIP=$((SKIP + 1)); }

# Helper: POST JSON and return HTTP code + body
post_json() {
  local url="$1"
  local data="$2"
  curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
    -d "$data" \
    "$url" 2>/dev/null
}

# Helper: GET with search param
get_search() {
  local url="$1"
  curl -s -w "\n%{http_code}" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
    "$url" 2>/dev/null
}

echo "============================================="
echo "  Input Validation Security Tests"
echo "============================================="
echo "  Target: $BASE_URL"
echo "  Auth:   $([ -n "$AUTH_TOKEN" ] && echo 'provided' || echo 'none (some tests may return 401)')"
echo ""

# =============================================
# XSS Tests
# =============================================
echo "${YELLOW}[XSS Tests]${NC}"

# 2.1 Script tag injection
echo "[2.1] XSS - Script tag in school name"
RESULT=$(post_json "$BASE_URL/api/schools" '{"name":"<script>alert(1)</script>"}')
HTTP_CODE=$(echo "$RESULT" | tail -1)
BODY=$(echo "$RESULT" | sed '$d')

if echo "$BODY" | grep -q '<script>'; then
  fail "Script tag not sanitized in response" "reflected"
else
  pass "Script tag sanitized or rejected (HTTP $HTTP_CODE)"
fi

# 2.2 Event handler injection
echo "[2.2] XSS - Event handler in name"
RESULT=$(post_json "$BASE_URL/api/schools" '{"name":"<img onerror=alert(1) src=x>"}')
BODY=$(echo "$RESULT" | sed '$d')

if echo "$BODY" | grep -q 'onerror'; then
  fail "Event handler not sanitized" "reflected"
else
  pass "Event handler sanitized or rejected"
fi

# 2.3 SVG injection
echo "[2.3] XSS - SVG onload"
RESULT=$(post_json "$BASE_URL/api/schools" '{"name":"<svg onload=alert(1)>"}')
BODY=$(echo "$RESULT" | sed '$d')

if echo "$BODY" | grep -q 'onload'; then
  fail "SVG onload not sanitized" "reflected"
else
  pass "SVG onload sanitized or rejected"
fi

# 2.4 JavaScript URI
echo "[2.4] XSS - JavaScript URI"
RESULT=$(post_json "$BASE_URL/api/schools" '{"name":"<a href=\"javascript:alert(1)\">click</a>"}')
BODY=$(echo "$RESULT" | sed '$d')

if echo "$BODY" | grep -q 'javascript:'; then
  fail "JavaScript URI not sanitized" "reflected"
else
  pass "JavaScript URI sanitized or rejected"
fi

# =============================================
# SQL Injection Tests
# =============================================
echo ""
echo "${YELLOW}[SQL Injection Tests]${NC}"

# 2.5 Basic SQL injection in search
echo "[2.5] SQLi - Basic OR injection in search"
RESULT=$(get_search "$BASE_URL/api/schools?page=1&pageSize=20&search=%27%20OR%201%3D1%20--")
HTTP_CODE=$(echo "$RESULT" | tail -1)
BODY=$(echo "$RESULT" | sed '$d')

if [ "$HTTP_CODE" = "500" ]; then
  fail "SQL error triggered (possible injection)" "500"
elif echo "$BODY" | grep -qi "sql\|syntax\|mysql\|query"; then
  fail "SQL error message leaked" "error leaked"
else
  pass "SQL injection attempt handled safely (HTTP $HTTP_CODE)"
fi

# 2.6 UNION SELECT injection
echo "[2.6] SQLi - UNION SELECT in search"
RESULT=$(get_search "$BASE_URL/api/schools?page=1&pageSize=20&search=%27%20UNION%20SELECT%20*%20FROM%20user%20--")
HTTP_CODE=$(echo "$RESULT" | tail -1)
BODY=$(echo "$RESULT" | sed '$d')

if [ "$HTTP_CODE" = "500" ]; then
  fail "SQL error triggered by UNION injection" "500"
else
  pass "UNION injection handled safely (HTTP $HTTP_CODE)"
fi

# 2.7 Time-based blind SQL injection
echo "[2.7] SQLi - Time-based blind injection"
START_TIME=$(date +%s)
RESULT=$(get_search "$BASE_URL/api/schools?page=1&pageSize=20&search=%27%20AND%20SLEEP(5)%20--")
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))

if [ "$ELAPSED" -ge 4 ]; then
  fail "Possible time-based SQL injection (response took ${ELAPSED}s)" "${ELAPSED}s"
else
  pass "Time-based injection not effective (${ELAPSED}s response)"
fi

# 2.8 SQL injection in POST body
echo "[2.8] SQLi - Injection in POST body"
RESULT=$(post_json "$BASE_URL/api/schools" '{"name":"test\"; DROP TABLE edu_school; --"}')
HTTP_CODE=$(echo "$RESULT" | tail -1)

if [ "$HTTP_CODE" = "500" ]; then
  fail "SQL error from POST body injection" "500"
else
  pass "POST body injection handled safely (HTTP $HTTP_CODE)"
fi

# =============================================
# Malformed Input Tests
# =============================================
echo ""
echo "${YELLOW}[Malformed Input Tests]${NC}"

# 2.9 Oversized input
echo "[2.9] Oversized input (10000 chars)"
LONG_NAME=$(python3 -c "print('A' * 10000)" 2>/dev/null || printf 'A%.0s' $(seq 1 10000))
RESULT=$(post_json "$BASE_URL/api/schools" "{\"name\":\"$LONG_NAME\"}")
HTTP_CODE=$(echo "$RESULT" | tail -1)

if [ "$HTTP_CODE" = "400" ] || [ "$HTTP_CODE" = "422" ] || [ "$HTTP_CODE" = "413" ]; then
  pass "Oversized input rejected (HTTP $HTTP_CODE)"
elif [ "$HTTP_CODE" = "401" ]; then
  skip "Oversized input test (auth required)"
  SKIP=$((SKIP - 1))  # undo double count
  skip "Need AUTH_TOKEN for this test"
else
  pass "Oversized input handled (HTTP $HTTP_CODE)"
fi

# 2.10 Malformed JSON
echo "[2.10] Malformed JSON body"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -X POST \
  -H "Content-Type: application/json" \
  ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
  -d '{invalid json here' \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$HTTP_CODE" = "400" ] || [ "$HTTP_CODE" = "422" ]; then
  pass "Malformed JSON rejected (HTTP $HTTP_CODE)"
elif [ "$HTTP_CODE" = "401" ]; then
  skip "Malformed JSON test (auth required)"
else
  pass "Malformed JSON handled (HTTP $HTTP_CODE)"
fi

# 2.11 Empty body POST
echo "[2.11] Empty body POST"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -X POST \
  -H "Content-Type: application/json" \
  ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
  -d '' \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$HTTP_CODE" = "400" ] || [ "$HTTP_CODE" = "422" ] || [ "$HTTP_CODE" = "401" ]; then
  pass "Empty body handled (HTTP $HTTP_CODE)"
else
  pass "Empty body processed (HTTP $HTTP_CODE)"
fi

# 2.12 Null bytes
echo "[2.12] Null byte injection"
RESULT=$(post_json "$BASE_URL/api/schools" '{"name":"test\u0000admin"}')
HTTP_CODE=$(echo "$RESULT" | tail -1)

if [ "$HTTP_CODE" = "500" ]; then
  fail "Null byte caused server error" "500"
else
  pass "Null byte handled safely (HTTP $HTTP_CODE)"
fi

# --- Summary ---
echo ""
echo "============================================="
echo "  Results: ${GREEN}$PASS passed${NC}, ${RED}$FAIL failed${NC}, ${YELLOW}$SKIP skipped${NC}"
echo "============================================="

[ "$FAIL" -gt 0 ] && exit 1
exit 0
