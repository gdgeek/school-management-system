#!/bin/bash

# Test script for DELETE /api/schools/{id}
# Tests the school deletion endpoint through PSR-15 middleware stack

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "School Delete Endpoint Test"
echo "=========================================="
echo ""

# Step 1: Login to get JWT token
echo "Step 1: Logging in..."
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
  echo "❌ Failed to get authentication token"
  exit 1
fi

echo "✅ Authentication successful"
echo "Token: ${TOKEN:0:20}..."
echo ""

# Step 2: Create a test school
echo "Step 2: Creating a test school..."
CREATE_RESPONSE=$(curl -s -X POST "$BASE_URL/schools" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Test School for Deletion",
    "info": "This school will be deleted",
    "principal_id": null
  }')

echo "Create Response:"
echo "$CREATE_RESPONSE" | jq '.'
echo ""

# Extract school ID
SCHOOL_ID=$(echo "$CREATE_RESPONSE" | jq -r '.data.id // empty')

if [ -z "$SCHOOL_ID" ]; then
  echo "❌ Failed to create test school"
  exit 1
fi

echo "✅ Test school created with ID: $SCHOOL_ID"
echo ""

# Step 3: Verify school exists
echo "Step 3: Verifying school exists..."
GET_RESPONSE=$(curl -s -X GET "$BASE_URL/schools/$SCHOOL_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "Get Response:"
echo "$GET_RESPONSE" | jq '.'
echo ""

GET_CODE=$(echo "$GET_RESPONSE" | jq -r '.code // 0')
if [ "$GET_CODE" != "200" ]; then
  echo "❌ School not found before deletion"
  exit 1
fi

echo "✅ School exists before deletion"
echo ""

# Step 4: Delete the school
echo "Step 4: Deleting the school..."
DELETE_RESPONSE=$(curl -s -X DELETE "$BASE_URL/schools/$SCHOOL_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "Delete Response:"
echo "$DELETE_RESPONSE" | jq '.'
echo ""

# Verify response
DELETE_CODE=$(echo "$DELETE_RESPONSE" | jq -r '.code // 0')
DELETE_MESSAGE=$(echo "$DELETE_RESPONSE" | jq -r '.message // ""')

if [ "$DELETE_CODE" != "200" ]; then
  echo "❌ Delete failed with code: $DELETE_CODE"
  echo "Message: $DELETE_MESSAGE"
  exit 1
fi

echo "✅ School deleted successfully"
echo "Message: $DELETE_MESSAGE"
echo ""

# Step 5: Verify school no longer exists
echo "Step 5: Verifying school is deleted..."
VERIFY_RESPONSE=$(curl -s -X GET "$BASE_URL/schools/$SCHOOL_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "Verify Response:"
echo "$VERIFY_RESPONSE" | jq '.'
echo ""

VERIFY_CODE=$(echo "$VERIFY_RESPONSE" | jq -r '.code // 0')
if [ "$VERIFY_CODE" != "404" ]; then
  echo "❌ School still exists after deletion (expected 404, got $VERIFY_CODE)"
  exit 1
fi

echo "✅ School successfully deleted (404 returned)"
echo ""

# Step 6: Test deleting non-existent school
echo "Step 6: Testing deletion of non-existent school..."
NOTFOUND_RESPONSE=$(curl -s -X DELETE "$BASE_URL/schools/999999" \
  -H "Authorization: Bearer $TOKEN")

echo "Not Found Response:"
echo "$NOTFOUND_RESPONSE" | jq '.'
echo ""

NOTFOUND_CODE=$(echo "$NOTFOUND_RESPONSE" | jq -r '.code // 0')
if [ "$NOTFOUND_CODE" != "404" ]; then
  echo "❌ Expected 404 for non-existent school, got $NOTFOUND_CODE"
  exit 1
fi

echo "✅ Correctly returns 404 for non-existent school"
echo ""

# Step 7: Test without authentication
echo "Step 7: Testing without authentication..."
UNAUTH_RESPONSE=$(curl -s -X DELETE "$BASE_URL/schools/1")

echo "Unauthorized Response:"
echo "$UNAUTH_RESPONSE" | jq '.'
echo ""

UNAUTH_CODE=$(echo "$UNAUTH_RESPONSE" | jq -r '.code // 0')
if [ "$UNAUTH_CODE" != "401" ]; then
  echo "❌ Expected 401 for unauthenticated request, got $UNAUTH_CODE"
  exit 1
fi

echo "✅ Correctly returns 401 for unauthenticated request"
echo ""

# Summary
echo "=========================================="
echo "✅ All DELETE /api/schools/{id} tests passed!"
echo "=========================================="
echo ""
echo "Test Results:"
echo "  ✅ Authentication works"
echo "  ✅ School creation works"
echo "  ✅ School deletion works"
echo "  ✅ Deleted school returns 404"
echo "  ✅ Non-existent school returns 404"
echo "  ✅ Unauthenticated request returns 401"
echo ""
