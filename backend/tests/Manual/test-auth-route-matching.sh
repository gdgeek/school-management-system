#!/bin/bash

# Task 6.4: Test Route Matching for Auth Endpoints
# Verifies that auth routes are correctly matched by RouterMiddleware

BASE_URL="http://localhost:8084/api"
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

echo "=========================================="
echo "Auth Route Matching Tests"
echo "=========================================="
echo ""

# Test 1: POST /api/auth/login (valid method)
echo "Test 1: POST /api/auth/login (valid method)"
RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"guanfei","password":"123456"}')
CODE=$(echo "$RESPONSE" | jq -r '.code // empty')

if [ "$CODE" = "200" ]; then
    echo -e "${GREEN}✓ PASS${NC}: Route matched correctly"
else
    echo -e "${RED}✗ FAIL${NC}: Expected 200, got $CODE"
fi
echo ""

# Test 2: GET /api/auth/login (invalid method)
echo "Test 2: GET /api/auth/login (invalid method - should be 405)"
RESPONSE=$(curl -s -X GET "$BASE_URL/auth/login")
CODE=$(echo "$RESPONSE" | jq -r '.code // empty')

if [ "$CODE" = "405" ] || [ "$CODE" = "404" ]; then
    echo -e "${GREEN}✓ PASS${NC}: Invalid method rejected (code: $CODE)"
else
    echo -e "${RED}✗ FAIL${NC}: Expected 405 or 404, got $CODE"
fi
echo ""

# Test 3: GET /api/auth/user with valid token
echo "Test 3: GET /api/auth/user (valid method with auth)"
TOKEN=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"guanfei","password":"123456"}' | jq -r '.data.token')

RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
  -H "Authorization: Bearer $TOKEN")
CODE=$(echo "$RESPONSE" | jq -r '.code // empty')

if [ "$CODE" = "200" ]; then
    echo -e "${GREEN}✓ PASS${NC}: Route matched and AuthMiddleware executed"
else
    echo -e "${RED}✗ FAIL${NC}: Expected 200, got $CODE"
fi
echo ""

# Test 4: POST /api/auth/user (invalid method)
echo "Test 4: POST /api/auth/user (invalid method - should be 405)"
RESPONSE=$(curl -s -X POST "$BASE_URL/auth/user" \
  -H "Authorization: Bearer $TOKEN")
CODE=$(echo "$RESPONSE" | jq -r '.code // empty')

if [ "$CODE" = "405" ] || [ "$CODE" = "404" ]; then
    echo -e "${GREEN}✓ PASS${NC}: Invalid method rejected (code: $CODE)"
else
    echo -e "${RED}✗ FAIL${NC}: Expected 405 or 404, got $CODE"
fi
echo ""

# Test 5: GET /api/auth/nonexistent (404)
echo "Test 5: GET /api/auth/nonexistent (should be 404)"
RESPONSE=$(curl -s -X GET "$BASE_URL/auth/nonexistent")
CODE=$(echo "$RESPONSE" | jq -r '.code // empty')

if [ "$CODE" = "404" ]; then
    echo -e "${GREEN}✓ PASS${NC}: Unmatched route returns 404"
else
    echo -e "${RED}✗ FAIL${NC}: Expected 404, got $CODE"
fi
echo ""

echo "=========================================="
echo "Route Matching Tests Complete"
echo "=========================================="
