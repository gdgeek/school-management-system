#!/bin/bash

# =============================================================================
# API Security Tests
#
# Tests for rate limiting, CORS, error information leakage, and HTTP methods.
#
# Usage:
#   ./api-security.sh                              # Default: localhost:8084
#   ./api-security.sh http://myserver:8084          # Custom URL
#   AUTH_TOKEN=<jwt> ./api-security.sh              # With authentication
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
echo "  API Security Tests"
echo "============================================="
echo "  Target: $BASE_URL"
echo ""

# =============================================
# HTTP Security Headers
# =============================================
echo "${YELLOW}[HTTP Security Headers]${NC}"

HEADERS=$(curl -s -D - -o /dev/null "$BASE_URL/health" 2>/dev/null)

# 6.1 X-Frame-Options
echo "[6.1] X-Frame-Options header"
if echo "$HEADERS" | grep -qi "X-Frame-Options.*SAMEORIGIN"; then
  pass "X-Frame-Options: SAMEORIGIN"
else
  fail "X-Frame-Options missing or incorrect" "$(echo "$HEADERS" | grep -i 'X-Frame-Options' || echo 'missing')"
fi

# 6.2 X-Content-Type-Options
echo "[6.2] X-Content-Type-Options header"
if echo "$HEADERS" | grep -qi "X-Content-Type-Options.*nosniff"; then
  pass "X-Content-Type-Options: nosniff"
else
  fail "X-Content-Type-Options missing or incorrect" "$(echo "$HEADERS" | grep -i 'X-Content-Type-Options' || echo 'missing')"
fi

# 6.3 X-XSS-Protection
echo "[6.3] X-XSS-Protection header"
if echo "$HEADERS" | grep -qi "X-XSS-Protection"; then
  pass "X-XSS-Protection present"
else
  fail "X-XSS-Protection missing" "missing"
fi

# 6.4 Referrer-Policy
echo "[6.4] Referrer-Policy header"
if echo "$HEADERS" | grep -qi "Referrer-Policy"; then
  pass "Referrer-Policy present"
else
  fail "Referrer-Policy missing" "missing"
fi

# 6.5 Permissions-Policy
echo "[6.5] Permissions-Policy header"
if echo "$HEADERS" | grep -qi "Permissions-Policy"; then
  pass "Permissions-Policy present"
else
  fail "Permissions-Policy missing" "missing"
fi

# 6.6 Strict-Transport-Security
echo "[6.6] Strict-Transport-Security header"
if echo "$HEADERS" | grep -qi "Strict-Transport-Security"; then
  pass "Strict-Transport-Security present"
else
  fail "Strict-Transport-Security missing" "missing"
fi

# 6.7 Content-Security-Policy
echo "[6.7] Content-Security-Policy header"
if echo "$HEADERS" | grep -qi "Content-Security-Policy"; then
  pass "Content-Security-Policy present"
else
  fail "Content-Security-Policy missing" "missing"
fi

# =============================================
# CORS Tests
# =============================================
echo ""
echo "${YELLOW}[CORS Tests]${NC}"

# 3.3 CORS preflight request
echo "[3.3] CORS - Preflight OPTIONS request"
PREFLIGHT=$(curl -s -D - -o /dev/null \
  -X OPTIONS \
  -H "Origin: http://localhost:3002" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Authorization, Content-Type" \
  "$BASE_URL/api/schools" 2>/dev/null)

PREFLIGHT_CODE=$(echo "$PREFLIGHT" | head -1 | grep -oP '\d{3}' | head -1)

if [ "$PREFLIGHT_CODE" = "204" ] || [ "$PREFLIGHT_CODE" = "200" ]; then
  pass "Preflight returns $PREFLIGHT_CODE"
else
  fail "Preflight unexpected status" "$PREFLIGHT_CODE"
fi

# 3.3b CORS headers present in preflight
echo "[3.3b] CORS - Access-Control headers in preflight"
if echo "$PREFLIGHT" | grep -qi "Access-Control-Allow-Origin"; then
  pass "Access-Control-Allow-Origin present in preflight"
else
  fail "Access-Control-Allow-Origin missing in preflight" "missing"
fi

if echo "$PREFLIGHT" | grep -qi "Access-Control-Allow-Methods"; then
  pass "Access-Control-Allow-Methods present in preflight"
else
  fail "Access-Control-Allow-Methods missing in preflight" "missing"
fi

# 3.4 CORS - disallowed origin
echo "[3.4] CORS - Disallowed origin"
DISALLOWED=$(curl -s -D - -o /dev/null \
  -H "Origin: http://evil-site.com" \
  "$BASE_URL/health" 2>/dev/null)

if echo "$DISALLOWED" | grep -qi "Access-Control-Allow-Origin.*evil-site"; then
  fail "Disallowed origin reflected in CORS header" "reflected"
else
  pass "Disallowed origin not reflected"
fi

# =============================================
# Rate Limiting Tests
# =============================================
echo ""
echo "${YELLOW}[Rate Limiting Tests]${NC}"

# 3.2 Rate limit headers present
echo "[3.2] Rate limit headers in response"
RL_HEADERS=$(curl -s -D - -o /dev/null \
  ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
  "$BASE_URL/health" 2>/dev/null)

if echo "$RL_HEADERS" | grep -qi "X-RateLimit-Limit"; then
  pass "X-RateLimit-Limit header present"
else
  skip "X-RateLimit-Limit not present (may not apply to /health)"
fi

if echo "$RL_HEADERS" | grep -qi "X-RateLimit-Remaining"; then
  pass "X-RateLimit-Remaining header present"
else
  skip "X-RateLimit-Remaining not present (may not apply to /health)"
fi

# 3.1 Rate limit trigger (send many requests quickly)
echo "[3.1] Rate limit trigger (sending 110 rapid requests)"
RATE_LIMITED=false
for i in $(seq 1 110); do
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    "$BASE_URL/api/schools" 2>/dev/null)
  if [ "$HTTP_CODE" = "429" ]; then
    RATE_LIMITED=true
    pass "Rate limit triggered at request #$i (429)"
    break
  fi
done

if [ "$RATE_LIMITED" = false ]; then
  skip "Rate limit not triggered after 110 requests (may need higher volume or different config)"
fi

# =============================================
# Error Information Leakage
# =============================================
echo ""
echo "${YELLOW}[Error Information Leakage]${NC}"

# 3.6 Server error doesn't expose internals
echo "[3.6] Error response doesn't leak stack traces"
ERROR_RESP=$(curl -s \
  -H "Content-Type: application/json" \
  ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
  "$BASE_URL/api/schools/99999999" 2>/dev/null)

LEAKED=false
for PATTERN in "stack trace" "vendor/" "src/" "\.php:" "PDOException" "SQLSTATE" "Fatal error"; do
  if echo "$ERROR_RESP" | grep -qi "$PATTERN"; then
    fail "Error response leaks internal info" "$PATTERN found"
    LEAKED=true
    break
  fi
done

if [ "$LEAKED" = false ]; then
  pass "Error response does not leak internal details"
fi

# 3.6b 404 response doesn't expose file paths
echo "[3.6b] 404 response doesn't expose file paths"
NOT_FOUND=$(curl -s "$BASE_URL/api/nonexistent-endpoint" 2>/dev/null)

if echo "$NOT_FOUND" | grep -qiE "/var/|/home/|/app/src|\.php"; then
  fail "404 response leaks file paths" "path leaked"
else
  pass "404 response clean of file paths"
fi

# =============================================
# HTTP Method Tests
# =============================================
echo ""
echo "${YELLOW}[HTTP Method Tests]${NC}"

# 3.7 TRACE method
echo "[3.7] TRACE method rejected"
TRACE_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  -X TRACE \
  "$BASE_URL/api/schools" 2>/dev/null)

if [ "$TRACE_CODE" = "405" ] || [ "$TRACE_CODE" = "404" ] || [ "$TRACE_CODE" = "401" ]; then
  pass "TRACE method not allowed (HTTP $TRACE_CODE)"
else
  fail "TRACE method may be allowed" "$TRACE_CODE"
fi

# =============================================
# Path Traversal
# =============================================
echo ""
echo "${YELLOW}[Path Traversal Tests]${NC}"

# 3.8 Path traversal attempt
echo "[3.8] Path traversal - /../"
TRAVERSAL_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  "$BASE_URL/api/../config" 2>/dev/null)

if [ "$TRAVERSAL_CODE" = "404" ] || [ "$TRAVERSAL_CODE" = "400" ] || [ "$TRAVERSAL_CODE" = "401" ]; then
  pass "Path traversal blocked (HTTP $TRAVERSAL_CODE)"
else
  fail "Path traversal may be possible" "$TRAVERSAL_CODE"
fi

echo "[3.8b] Path traversal - encoded"
TRAVERSAL_CODE2=$(curl -s -o /dev/null -w "%{http_code}" \
  "$BASE_URL/api/%2e%2e/config" 2>/dev/null)

if [ "$TRAVERSAL_CODE2" = "404" ] || [ "$TRAVERSAL_CODE2" = "400" ] || [ "$TRAVERSAL_CODE2" = "401" ]; then
  pass "Encoded path traversal blocked (HTTP $TRAVERSAL_CODE2)"
else
  fail "Encoded path traversal may be possible" "$TRAVERSAL_CODE2"
fi

# =============================================
# Content-Type Enforcement
# =============================================
echo ""
echo "${YELLOW}[Content-Type Tests]${NC}"

echo "[CT.1] API response Content-Type is application/json"
CT_HEADERS=$(curl -s -D - -o /dev/null \
  ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
  "$BASE_URL/health" 2>/dev/null)

if echo "$CT_HEADERS" | grep -qi "Content-Type.*application/json"; then
  pass "Response Content-Type is application/json"
else
  fail "Response Content-Type not application/json" "$(echo "$CT_HEADERS" | grep -i 'Content-Type' || echo 'missing')"
fi

# --- Summary ---
echo ""
echo "============================================="
echo "  Results: ${GREEN}$PASS passed${NC}, ${RED}$FAIL failed${NC}, ${YELLOW}$SKIP skipped${NC}"
echo "============================================="

[ "$FAIL" -gt 0 ] && exit 1
exit 0
