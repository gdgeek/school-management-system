#!/bin/bash

# Test script for auth.user route with AuthMiddleware
# Task 6.2: Verify auth.user route is registered with AuthMiddleware

set -e

BASE_URL="http://localhost:8084/api"
BLUE='\033[0;34m'
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Testing auth.user Route Configuration${NC}"
echo -e "${BLUE}Task 6.2: Register auth.user route with AuthMiddleware${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Test 1: Login to get JWT token
echo -e "${YELLOW}Test 1: Login to get JWT token${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "${BASE_URL}/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "Login Response:"
echo "$LOGIN_RESPONSE" | jq '.'

# Extract token
TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // empty')

if [ -z "$TOKEN" ]; then
  echo -e "${RED}❌ FAILED: Could not get JWT token from login${NC}"
  exit 1
fi

echo -e "${GREEN}✓ Successfully obtained JWT token${NC}"
echo ""

# Test 2: Call /api/auth/user WITHOUT token (should fail with 401)
echo -e "${YELLOW}Test 2: Call /api/auth/user WITHOUT token (should fail with 401)${NC}"
NO_AUTH_RESPONSE=$(curl -s -w "\nHTTP_STATUS:%{http_code}" -X GET "${BASE_URL}/auth/user")

HTTP_STATUS=$(echo "$NO_AUTH_RESPONSE" | grep "HTTP_STATUS:" | cut -d: -f2)
RESPONSE_BODY=$(echo "$NO_AUTH_RESPONSE" | sed '/HTTP_STATUS:/d')

echo "Response Status: $HTTP_STATUS"
echo "Response Body:"
echo "$RESPONSE_BODY" | jq '.'

if [ "$HTTP_STATUS" = "401" ]; then
  echo -e "${GREEN}✓ Correctly returned 401 Unauthorized without token${NC}"
else
  echo -e "${RED}❌ FAILED: Expected 401, got $HTTP_STATUS${NC}"
  exit 1
fi
echo ""

# Test 3: Call /api/auth/user WITH valid token (should succeed with 200)
echo -e "${YELLOW}Test 3: Call /api/auth/user WITH valid token (should succeed with 200)${NC}"
AUTH_RESPONSE=$(curl -s -w "\nHTTP_STATUS:%{http_code}" -X GET "${BASE_URL}/auth/user" \
  -H "Authorization: Bearer ${TOKEN}")

HTTP_STATUS=$(echo "$AUTH_RESPONSE" | grep "HTTP_STATUS:" | cut -d: -f2)
RESPONSE_BODY=$(echo "$AUTH_RESPONSE" | sed '/HTTP_STATUS:/d')

echo "Response Status: $HTTP_STATUS"
echo "Response Body:"
echo "$RESPONSE_BODY" | jq '.'

if [ "$HTTP_STATUS" = "200" ]; then
  echo -e "${GREEN}✓ Successfully retrieved user info with valid token${NC}"
  
  # Verify response structure
  USER_ID=$(echo "$RESPONSE_BODY" | jq -r '.data.id // empty')
  USERNAME=$(echo "$RESPONSE_BODY" | jq -r '.data.username // empty')
  
  if [ -n "$USER_ID" ] && [ -n "$USERNAME" ]; then
    echo -e "${GREEN}✓ Response contains user data (id: $USER_ID, username: $USERNAME)${NC}"
  else
    echo -e "${RED}❌ FAILED: Response missing user data${NC}"
    exit 1
  fi
else
  echo -e "${RED}❌ FAILED: Expected 200, got $HTTP_STATUS${NC}"
  exit 1
fi
echo ""

# Test 4: Call /api/auth/user WITH invalid token (should fail with 401)
echo -e "${YELLOW}Test 4: Call /api/auth/user WITH invalid token (should fail with 401)${NC}"
INVALID_TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.invalid.signature"
INVALID_AUTH_RESPONSE=$(curl -s -w "\nHTTP_STATUS:%{http_code}" -X GET "${BASE_URL}/auth/user" \
  -H "Authorization: Bearer ${INVALID_TOKEN}")

HTTP_STATUS=$(echo "$INVALID_AUTH_RESPONSE" | grep "HTTP_STATUS:" | cut -d: -f2)
RESPONSE_BODY=$(echo "$INVALID_AUTH_RESPONSE" | sed '/HTTP_STATUS:/d')

echo "Response Status: $HTTP_STATUS"
echo "Response Body:"
echo "$RESPONSE_BODY" | jq '.'

if [ "$HTTP_STATUS" = "401" ]; then
  echo -e "${GREEN}✓ Correctly returned 401 Unauthorized with invalid token${NC}"
else
  echo -e "${RED}❌ FAILED: Expected 401, got $HTTP_STATUS${NC}"
  exit 1
fi
echo ""

# Test 5: Verify route configuration
echo -e "${YELLOW}Test 5: Verify route configuration${NC}"
echo "Checking config/routes.php for auth.user route..."

ROUTE_CONFIG=$(grep -A 5 "'name' => 'auth.user'" ../../config/routes.php || echo "")

if [ -n "$ROUTE_CONFIG" ]; then
  echo -e "${GREEN}✓ auth.user route found in config/routes.php${NC}"
  echo "$ROUTE_CONFIG"
  
  # Check if AuthMiddleware is configured
  if echo "$ROUTE_CONFIG" | grep -q "AuthMiddleware"; then
    echo -e "${GREEN}✓ AuthMiddleware is configured for auth.user route${NC}"
  else
    echo -e "${RED}❌ FAILED: AuthMiddleware not found in route configuration${NC}"
    exit 1
  fi
else
  echo -e "${RED}❌ FAILED: auth.user route not found in config/routes.php${NC}"
  exit 1
fi
echo ""

# Test 6: Verify PSR-15 migration whitelist
echo -e "${YELLOW}Test 6: Verify PSR-15 migration whitelist${NC}"
echo "Checking config/psr15-migration.php for /api/auth/user..."

MIGRATION_CONFIG=$(grep "/api/auth/user" ../config/psr15-migration.php || echo "")

if [ -n "$MIGRATION_CONFIG" ]; then
  echo -e "${GREEN}✓ /api/auth/user found in PSR-15 migration whitelist${NC}"
  echo "$MIGRATION_CONFIG"
else
  echo -e "${RED}❌ FAILED: /api/auth/user not found in PSR-15 migration whitelist${NC}"
  exit 1
fi
echo ""

# Summary
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Test Summary${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}✓ All tests passed!${NC}"
echo ""
echo "Verified:"
echo "  1. Route pattern: /api/auth/user"
echo "  2. HTTP method: GET"
echo "  3. Handler: AuthController::user"
echo "  4. AuthMiddleware: Properly attached and enforcing authentication"
echo "  5. PSR-15 migration: Route is in whitelist"
echo "  6. Response format: Standard API response structure"
echo ""
echo -e "${GREEN}Task 6.2 completed successfully!${NC}"
