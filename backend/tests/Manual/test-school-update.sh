#!/bin/bash

# Test script for PUT /api/schools/{id}
# Tests the update() method in SchoolController

BASE_URL="http://localhost:8084/api"

echo "=== Testing School Update Endpoint ==="
echo ""

# Step 1: Login to get JWT token
echo "Step 1: Logging in..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "Login Response: $LOGIN_RESPONSE"
TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
  echo "❌ Failed to get token"
  exit 1
fi

echo "✅ Token obtained: ${TOKEN:0:20}..."
echo ""

# Step 2: Create a test school
echo "Step 2: Creating a test school..."
CREATE_RESPONSE=$(curl -s -X POST "$BASE_URL/schools" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Test School for Update",
    "info": "Original description"
  }')

echo "Create Response: $CREATE_RESPONSE"
SCHOOL_ID=$(echo $CREATE_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$SCHOOL_ID" ]; then
  echo "❌ Failed to create school"
  exit 1
fi

echo "✅ School created with ID: $SCHOOL_ID"
echo ""

# Step 3: Update the school
echo "Step 3: Updating school..."
UPDATE_RESPONSE=$(curl -s -X PUT "$BASE_URL/schools/$SCHOOL_ID" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Updated School Name",
    "info": "Updated description"
  }')

echo "Update Response: $UPDATE_RESPONSE"
echo ""

# Step 4: Verify the update
echo "Step 4: Verifying update..."
GET_RESPONSE=$(curl -s -X GET "$BASE_URL/schools/$SCHOOL_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "Get Response: $GET_RESPONSE"
echo ""

# Check if name was updated
if echo "$GET_RESPONSE" | grep -q "Updated School Name"; then
  echo "✅ School name updated successfully"
else
  echo "❌ School name not updated"
fi

if echo "$GET_RESPONSE" | grep -q "Updated description"; then
  echo "✅ School info updated successfully"
else
  echo "❌ School info not updated"
fi

echo ""

# Step 5: Test partial update (only name)
echo "Step 5: Testing partial update (only name)..."
PARTIAL_UPDATE_RESPONSE=$(curl -s -X PUT "$BASE_URL/schools/$SCHOOL_ID" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Partially Updated Name"
  }')

echo "Partial Update Response: $PARTIAL_UPDATE_RESPONSE"
echo ""

# Step 6: Test update non-existent school
echo "Step 6: Testing update non-existent school..."
NOT_FOUND_RESPONSE=$(curl -s -X PUT "$BASE_URL/schools/999999" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Should Not Work"
  }')

echo "Not Found Response: $NOT_FOUND_RESPONSE"

if echo "$NOT_FOUND_RESPONSE" | grep -q "404\|not found"; then
  echo "✅ Correctly returns 404 for non-existent school"
else
  echo "❌ Did not return 404 for non-existent school"
fi

echo ""

# Step 7: Test update without authentication
echo "Step 7: Testing update without authentication..."
UNAUTH_RESPONSE=$(curl -s -X PUT "$BASE_URL/schools/$SCHOOL_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Should Not Work"
  }')

echo "Unauthorized Response: $UNAUTH_RESPONSE"

if echo "$UNAUTH_RESPONSE" | grep -q "401\|Unauthorized\|authentication"; then
  echo "✅ Correctly requires authentication"
else
  echo "❌ Did not require authentication"
fi

echo ""

# Cleanup: Delete the test school
echo "Cleanup: Deleting test school..."
DELETE_RESPONSE=$(curl -s -X DELETE "$BASE_URL/schools/$SCHOOL_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "Delete Response: $DELETE_RESPONSE"
echo ""

echo "=== Test Complete ==="
