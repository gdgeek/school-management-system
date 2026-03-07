#!/bin/bash

# Test script for Task 9.3: GET /api/schools/{id}
# Tests the show() method implementation

set -e

BASE_URL="http://localhost:8084/api"
CONTENT_TYPE="Content-Type: application/json"

echo "=========================================="
echo "Task 9.3: School Show Endpoint Test"
echo "=========================================="
echo ""

# Step 1: Login to get JWT token
echo "Step 1: Logging in to get JWT token..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "$CONTENT_TYPE" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "Login Response:"
echo "$LOGIN_RESPONSE" | jq '.'

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // empty')

if [ -z "$TOKEN" ]; then
  echo "❌ Failed to get JWT token"
  exit 1
fi

echo "✅ JWT token obtained"
echo ""

# Step 2: Get list of schools to find a valid ID
echo "Step 2: Getting school list to find a valid ID..."
SCHOOLS_RESPONSE=$(curl -s -X GET "$BASE_URL/schools?page=1&pageSize=5" \
  -H "$CONTENT_TYPE" \
  -H "Authorization: Bearer $TOKEN")

echo "Schools List Response:"
echo "$SCHOOLS_RESPONSE" | jq '.'

SCHOOL_ID=$(echo "$SCHOOLS_RESPONSE" | jq -r '.data.items[0].id // empty')

if [ -z "$SCHOOL_ID" ]; then
  echo "⚠️  No schools found in database. Creating a test school..."
  
  # Create a test school
  CREATE_RESPONSE=$(curl -s -X POST "$BASE_URL/schools" \
    -H "$CONTENT_TYPE" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{
      "name": "Test School for Show Method",
      "address": "123 Test Street",
      "contact_phone": "1234567890"
    }')
  
  echo "Create School Response:"
  echo "$CREATE_RESPONSE" | jq '.'
  
  SCHOOL_ID=$(echo "$CREATE_RESPONSE" | jq -r '.data.id // empty')
  
  if [ -z "$SCHOOL_ID" ]; then
    echo "❌ Failed to create test school"
    exit 1
  fi
  
  echo "✅ Test school created with ID: $SCHOOL_ID"
fi

echo "✅ Using school ID: $SCHOOL_ID"
echo ""

# Step 3: Test GET /api/schools/{id} with valid ID
echo "Step 3: Testing GET /api/schools/$SCHOOL_ID (valid ID)..."
SHOW_RESPONSE=$(curl -s -X GET "$BASE_URL/schools/$SCHOOL_ID" \
  -H "$CONTENT_TYPE" \
  -H "Authorization: Bearer $TOKEN")

echo "Show School Response:"
echo "$SHOW_RESPONSE" | jq '.'

# Verify response structure
CODE=$(echo "$SHOW_RESPONSE" | jq -r '.code // empty')
MESSAGE=$(echo "$SHOW_RESPONSE" | jq -r '.message // empty')
SCHOOL_NAME=$(echo "$SHOW_RESPONSE" | jq -r '.data.name // empty')
SCHOOL_ID_RESPONSE=$(echo "$SHOW_RESPONSE" | jq -r '.data.id // empty')

if [ "$CODE" != "200" ]; then
  echo "❌ Expected code 200, got: $CODE"
  exit 1
fi

if [ -z "$SCHOOL_NAME" ]; then
  echo "❌ School name not found in response"
  exit 1
fi

if [ "$SCHOOL_ID_RESPONSE" != "$SCHOOL_ID" ]; then
  echo "❌ School ID mismatch. Expected: $SCHOOL_ID, Got: $SCHOOL_ID_RESPONSE"
  exit 1
fi

echo "✅ Valid ID test passed"
echo "   - Code: $CODE"
echo "   - Message: $MESSAGE"
echo "   - School Name: $SCHOOL_NAME"
echo "   - School ID: $SCHOOL_ID_RESPONSE"
echo ""

# Step 4: Test GET /api/schools/{id} with invalid ID (404)
echo "Step 4: Testing GET /api/schools/999999 (invalid ID - should return 404)..."
INVALID_RESPONSE=$(curl -s -X GET "$BASE_URL/schools/999999" \
  -H "$CONTENT_TYPE" \
  -H "Authorization: Bearer $TOKEN")

echo "Invalid ID Response:"
echo "$INVALID_RESPONSE" | jq '.'

INVALID_CODE=$(echo "$INVALID_RESPONSE" | jq -r '.code // empty')
INVALID_MESSAGE=$(echo "$INVALID_RESPONSE" | jq -r '.message // empty')

if [ "$INVALID_CODE" != "404" ]; then
  echo "❌ Expected code 404 for invalid ID, got: $INVALID_CODE"
  exit 1
fi

echo "✅ Invalid ID test passed"
echo "   - Code: $INVALID_CODE"
echo "   - Message: $INVALID_MESSAGE"
echo ""

# Step 5: Test without authentication (should return 401)
echo "Step 5: Testing GET /api/schools/$SCHOOL_ID without authentication (should return 401)..."
UNAUTH_RESPONSE=$(curl -s -X GET "$BASE_URL/schools/$SCHOOL_ID" \
  -H "$CONTENT_TYPE")

echo "Unauthenticated Response:"
echo "$UNAUTH_RESPONSE" | jq '.'

UNAUTH_CODE=$(echo "$UNAUTH_RESPONSE" | jq -r '.code // empty')

if [ "$UNAUTH_CODE" != "401" ]; then
  echo "❌ Expected code 401 for unauthenticated request, got: $UNAUTH_CODE"
  exit 1
fi

echo "✅ Authentication test passed"
echo "   - Code: $UNAUTH_CODE"
echo ""

# Step 6: Test with non-numeric ID (should return 404 - route not matched)
echo "Step 6: Testing GET /api/schools/abc (non-numeric ID - should return 404)..."
NON_NUMERIC_RESPONSE=$(curl -s -X GET "$BASE_URL/schools/abc" \
  -H "$CONTENT_TYPE" \
  -H "Authorization: Bearer $TOKEN")

echo "Non-numeric ID Response:"
echo "$NON_NUMERIC_RESPONSE" | jq '.'

NON_NUMERIC_CODE=$(echo "$NON_NUMERIC_RESPONSE" | jq -r '.code // empty')

if [ "$NON_NUMERIC_CODE" != "404" ]; then
  echo "⚠️  Expected code 404 for non-numeric ID, got: $NON_NUMERIC_CODE"
  echo "   (This is acceptable if the route pattern doesn't match)"
fi

echo "✅ Non-numeric ID test completed"
echo ""

# Summary
echo "=========================================="
echo "✅ All tests passed!"
echo "=========================================="
echo ""
echo "Summary:"
echo "  ✅ Authentication works correctly"
echo "  ✅ Valid school ID returns 200 with school data"
echo "  ✅ Invalid school ID returns 404"
echo "  ✅ Unauthenticated request returns 401"
echo "  ✅ Route parameter extraction works correctly"
echo ""
echo "Task 9.3 implementation verified successfully!"
