#!/bin/bash

# Test script for PSR-15 Auth Login endpoint
# Tests POST /api/auth/login through PSR-15 middleware stack

BASE_URL="http://localhost:8084"

echo "=========================================="
echo "Testing PSR-15 Auth Login Endpoint"
echo "=========================================="
echo ""

# Test 1: Successful login with valid credentials
echo "Test 1: Valid login (username: guanfei, password: 123456)"
echo "---"
RESPONSE=$(curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "Response:"
echo "$RESPONSE" | jq '.'
echo ""

# Extract token for later use
TOKEN=$(echo "$RESPONSE" | jq -r '.data.token // empty')

if [ -n "$TOKEN" ]; then
  echo "✓ Login successful, token received"
  echo "Token: ${TOKEN:0:50}..."
else
  echo "✗ Login failed, no token received"
fi
echo ""

# Test 2: Login with invalid password
echo "Test 2: Invalid password"
echo "---"
RESPONSE=$(curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "wrongpassword"
  }')

echo "Response:"
echo "$RESPONSE" | jq '.'
CODE=$(echo "$RESPONSE" | jq -r '.code')

if [ "$CODE" = "401" ]; then
  echo "✓ Correctly returned 401 for invalid password"
else
  echo "✗ Expected 401, got $CODE"
fi
echo ""

# Test 3: Login with non-existent user
echo "Test 3: Non-existent user"
echo "---"
RESPONSE=$(curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "nonexistentuser",
    "password": "123456"
  }')

echo "Response:"
echo "$RESPONSE" | jq '.'
CODE=$(echo "$RESPONSE" | jq -r '.code')

if [ "$CODE" = "401" ]; then
  echo "✓ Correctly returned 401 for non-existent user"
else
  echo "✗ Expected 401, got $CODE"
fi
echo ""

# Test 4: Missing username
echo "Test 4: Missing username"
echo "---"
RESPONSE=$(curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "password": "123456"
  }')

echo "Response:"
echo "$RESPONSE" | jq '.'
CODE=$(echo "$RESPONSE" | jq -r '.code')

if [ "$CODE" = "400" ]; then
  echo "✓ Correctly returned 400 for missing username"
else
  echo "✗ Expected 400, got $CODE"
fi
echo ""

# Test 5: Missing password
echo "Test 5: Missing password"
echo "---"
RESPONSE=$(curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei"
  }')

echo "Response:"
echo "$RESPONSE" | jq '.'
CODE=$(echo "$RESPONSE" | jq -r '.code')

if [ "$CODE" = "400" ]; then
  echo "✓ Correctly returned 400 for missing password"
else
  echo "✗ Expected 400, got $CODE"
fi
echo ""

# Test 6: Empty request body
echo "Test 6: Empty request body"
echo "---"
RESPONSE=$(curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{}')

echo "Response:"
echo "$RESPONSE" | jq '.'
CODE=$(echo "$RESPONSE" | jq -r '.code')

if [ "$CODE" = "400" ]; then
  echo "✓ Correctly returned 400 for empty body"
else
  echo "✗ Expected 400, got $CODE"
fi
echo ""

# Test 7: Verify response format
echo "Test 7: Verify response format"
echo "---"
RESPONSE=$(curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "Checking response structure..."
HAS_CODE=$(echo "$RESPONSE" | jq 'has("code")')
HAS_MESSAGE=$(echo "$RESPONSE" | jq 'has("message")')
HAS_DATA=$(echo "$RESPONSE" | jq 'has("data")')
HAS_TIMESTAMP=$(echo "$RESPONSE" | jq 'has("timestamp")')
HAS_TOKEN=$(echo "$RESPONSE" | jq '.data | has("token")')
HAS_USER=$(echo "$RESPONSE" | jq '.data | has("user")')

if [ "$HAS_CODE" = "true" ] && [ "$HAS_MESSAGE" = "true" ] && [ "$HAS_DATA" = "true" ] && [ "$HAS_TIMESTAMP" = "true" ]; then
  echo "✓ Response has correct structure (code, message, data, timestamp)"
else
  echo "✗ Response structure is incorrect"
fi

if [ "$HAS_TOKEN" = "true" ] && [ "$HAS_USER" = "true" ]; then
  echo "✓ Data contains token and user"
else
  echo "✗ Data missing token or user"
fi
echo ""

echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo "All tests completed. Review results above."
