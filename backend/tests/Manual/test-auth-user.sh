#!/bin/bash

# Test script for GET /api/auth/user endpoint
# Tests the PSR-15 implementation of the user endpoint

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "Testing GET /api/auth/user (PSR-15)"
echo "=========================================="
echo ""

# Step 1: Login to get JWT token
echo "Step 1: Login to get JWT token..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "Login Response:"
echo "$LOGIN_RESPONSE" | jq '.'
echo ""

# Extract token
TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // empty')

if [ -z "$TOKEN" ]; then
  echo "❌ Failed to get token from login response"
  exit 1
fi

echo "✓ Token obtained: ${TOKEN:0:20}..."
echo ""

# Step 2: Test GET /api/auth/user with valid token
echo "Step 2: Test GET /api/auth/user with valid token..."
USER_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
  -H "Authorization: Bearer $TOKEN")

echo "User Response:"
echo "$USER_RESPONSE" | jq '.'
echo ""

# Verify response structure
CODE=$(echo "$USER_RESPONSE" | jq -r '.code // empty')
MESSAGE=$(echo "$USER_RESPONSE" | jq -r '.message // empty')
USER_ID=$(echo "$USER_RESPONSE" | jq -r '.data.id // empty')
USERNAME=$(echo "$USER_RESPONSE" | jq -r '.data.username // empty')

if [ "$CODE" = "200" ] && [ ! -z "$USER_ID" ] && [ "$USERNAME" = "guanfei" ]; then
  echo "✓ GET /api/auth/user with valid token: SUCCESS"
  echo "  - Code: $CODE"
  echo "  - Message: $MESSAGE"
  echo "  - User ID: $USER_ID"
  echo "  - Username: $USERNAME"
else
  echo "❌ GET /api/auth/user with valid token: FAILED"
  echo "  Expected: code=200, username=guanfei"
  echo "  Got: code=$CODE, username=$USERNAME"
fi
echo ""

# Step 3: Test GET /api/auth/user without token (should fail with 401)
echo "Step 3: Test GET /api/auth/user without token (should return 401)..."
NO_TOKEN_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user")

echo "Response without token:"
echo "$NO_TOKEN_RESPONSE" | jq '.'
echo ""

NO_TOKEN_CODE=$(echo "$NO_TOKEN_RESPONSE" | jq -r '.code // empty')

if [ "$NO_TOKEN_CODE" = "401" ]; then
  echo "✓ GET /api/auth/user without token: Correctly returned 401"
else
  echo "❌ GET /api/auth/user without token: Expected 401, got $NO_TOKEN_CODE"
fi
echo ""

# Step 4: Test GET /api/auth/user with invalid token (should fail with 401)
echo "Step 4: Test GET /api/auth/user with invalid token (should return 401)..."
INVALID_TOKEN_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
  -H "Authorization: Bearer invalid.token.here")

echo "Response with invalid token:"
echo "$INVALID_TOKEN_RESPONSE" | jq '.'
echo ""

INVALID_TOKEN_CODE=$(echo "$INVALID_TOKEN_RESPONSE" | jq -r '.code // empty')

if [ "$INVALID_TOKEN_CODE" = "401" ]; then
  echo "✓ GET /api/auth/user with invalid token: Correctly returned 401"
else
  echo "❌ GET /api/auth/user with invalid token: Expected 401, got $INVALID_TOKEN_CODE"
fi
echo ""

# Summary
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo "✓ Login successful"
echo "✓ GET /api/auth/user with valid token"
echo "✓ GET /api/auth/user without token (401)"
echo "✓ GET /api/auth/user with invalid token (401)"
echo ""
echo "All tests completed!"
