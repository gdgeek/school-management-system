#!/bin/bash

# =============================================================================
# Authentication & Authorization Security Tests
#
# Tests for authentication bypass, token manipulation, and role-based access.
#
# Usage:
#   ./auth-security.sh                              # Default: localhost:8084
#   ./auth-security.sh http://myserver:8084          # Custom URL
#   AUTH_TOKEN=<jwt> ./auth-security.sh              # With valid token
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

echo "============================================="
echo "  Auth & Authorization Security Tests"
echo "============================================="
echo "  Target: $BASE_URL"
echo ""

# --- 1.1 No Token Access ---
echo "[1.1] No Token - accessing protected endpoint"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Content-Type: application/json" \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$HTTP_CODE" = "401" ]; then
  pass "Protected endpoint returns 401 without token"
else
  fail "Expected 401 without token" "$HTTP_CODE"
fi

# --- 1.2 Invalid Token ---
echo "[1.2] Invalid Token - garbage string"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer this-is-not-a-valid-jwt-token" \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$HTTP_CODE" = "401" ]; then
  pass "Invalid token returns 401"
else
  fail "Expected 401 for invalid token" "$HTTP_CODE"
fi

# --- 1.3 Malformed JWT (wrong structure) ---
echo "[1.3] Malformed JWT - two parts only"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer header.payload" \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$HTTP_CODE" = "401" ]; then
  pass "Malformed JWT (2 parts) returns 401"
else
  fail "Expected 401 for malformed JWT" "$HTTP_CODE"
fi

# --- 1.4 Empty Bearer Token ---
echo "[1.4] Empty Bearer value"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer " \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$HTTP_CODE" = "401" ]; then
  pass "Empty Bearer token returns 401"
else
  fail "Expected 401 for empty Bearer" "$HTTP_CODE"
fi

# --- 1.5 JWT with alg:none ---
echo "[1.5] JWT algorithm confusion (alg: none)"
# Craft a JWT with alg:none - header: {"alg":"none","typ":"JWT"}, payload: {"user_id":1,"roles":["admin"]}
NONE_HEADER=$(echo -n '{"alg":"none","typ":"JWT"}' | base64 -w0 2>/dev/null || echo -n '{"alg":"none","typ":"JWT"}' | base64)
NONE_PAYLOAD=$(echo -n '{"user_id":1,"roles":["admin"],"iat":9999999999,"exp":9999999999}' | base64 -w0 2>/dev/null || echo -n '{"user_id":1,"roles":["admin"],"iat":9999999999,"exp":9999999999}' | base64)
NONE_TOKEN="${NONE_HEADER}.${NONE_PAYLOAD}."

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $NONE_TOKEN" \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$HTTP_CODE" = "401" ]; then
  pass "JWT with alg:none rejected"
else
  fail "Expected 401 for alg:none JWT" "$HTTP_CODE"
fi

# --- 1.6 Tampered JWT Payload ---
echo "[1.6] Tampered JWT payload"
# Take a valid-looking JWT structure but with tampered payload
FAKE_HEADER=$(echo -n '{"alg":"HS256","typ":"JWT"}' | base64 -w0 2>/dev/null || echo -n '{"alg":"HS256","typ":"JWT"}' | base64)
FAKE_PAYLOAD=$(echo -n '{"user_id":1,"roles":["admin"],"exp":9999999999}' | base64 -w0 2>/dev/null || echo -n '{"user_id":1,"roles":["admin"],"exp":9999999999}' | base64)
FAKE_TOKEN="${FAKE_HEADER}.${FAKE_PAYLOAD}.fake-signature"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $FAKE_TOKEN" \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$HTTP_CODE" = "401" ]; then
  pass "Tampered JWT payload rejected"
else
  fail "Expected 401 for tampered JWT" "$HTTP_CODE"
fi

# --- 1.7 Authorization header without Bearer prefix ---
echo "[1.7] Authorization without Bearer prefix"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Content-Type: application/json" \
  -H "Authorization: some-token-value" \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$HTTP_CODE" = "401" ]; then
  pass "Non-Bearer authorization rejected"
else
  fail "Expected 401 for non-Bearer auth" "$HTTP_CODE"
fi

# --- 1.8 Token via query parameter (cross-system jump) ---
echo "[1.8] Token via query parameter - invalid token"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Content-Type: application/json" \
  "$BASE_URL/api/schools?token=invalid-token-here" 2>/dev/null)

if [ "$HTTP_CODE" = "401" ]; then
  pass "Invalid query param token rejected"
else
  fail "Expected 401 for invalid query param token" "$HTTP_CODE"
fi

# --- Tests requiring a valid AUTH_TOKEN ---
if [ -n "$AUTH_TOKEN" ]; then
  echo ""
  echo "[Authenticated Tests]"

  # 1.9 Valid token access
  echo "[1.9] Valid token - accessing protected endpoint"
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $AUTH_TOKEN" \
    "$BASE_URL/api/schools" 2>/dev/null)

  if [ "$HTTP_CODE" = "200" ]; then
    pass "Valid token grants access (200)"
  else
    fail "Expected 200 with valid token" "$HTTP_CODE"
  fi

  # 1.10 Check response doesn't leak sensitive data
  echo "[1.10] Response does not contain sensitive fields"
  RESPONSE=$(curl -s \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $AUTH_TOKEN" \
    "$BASE_URL/api/auth/user" 2>/dev/null)

  if echo "$RESPONSE" | grep -qi "password"; then
    fail "Response contains 'password' field" "leaked"
  else
    pass "No password field in user response"
  fi
else
  echo ""
  skip "Authenticated tests (set AUTH_TOKEN env var to enable)"
fi

# --- Summary ---
echo ""
echo "============================================="
echo "  Results: ${GREEN}$PASS passed${NC}, ${RED}$FAIL failed${NC}, ${YELLOW}$SKIP skipped${NC}"
echo "============================================="

[ "$FAIL" -gt 0 ] && exit 1
exit 0
