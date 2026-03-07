#!/bin/bash

# Test script for schools.update route with AuthMiddleware
# Task 10.4: Verify schools.update route is registered with AuthMiddleware

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "Task 10.4: Schools Update Route Auth Test"
echo "=========================================="
echo ""

# Test 1: Attempt to update school without authentication
echo "Test 1: Update school without authentication (should fail with 401)"
echo "Request: PUT $BASE_URL/schools/1"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X PUT "$BASE_URL/schools/1" \
  -H "Content-Type: application/json" \
  -d '{"name":"Updated School"}')

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

echo "Response Code: $HTTP_CODE"
echo "Response Body: $BODY"
echo ""

if [ "$HTTP_CODE" = "401" ]; then
    echo "✓ Test 1 PASSED: Correctly rejected unauthenticated request"
else
    echo "✗ Test 1 FAILED: Expected 401, got $HTTP_CODE"
fi
echo ""

# Test 2: Login to get JWT token
echo "Test 2: Login to get JWT token"
echo "Request: POST $BASE_URL/auth/login"
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"guanfei","password":"123456"}')

echo "Response: $LOGIN_RESPONSE"
echo ""

TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo "✗ Test 2 FAILED: Could not extract token from login response"
    exit 1
else
    echo "✓ Test 2 PASSED: Successfully obtained JWT token"
    echo "Token: ${TOKEN:0:50}..."
fi
echo ""

# Test 3: Update school with valid authentication
echo "Test 3: Update school with valid authentication (should succeed)"
echo "Request: PUT $BASE_URL/schools/1"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X PUT "$BASE_URL/schools/1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"Updated School Name","address":"Updated Address"}')

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

echo "Response Code: $HTTP_CODE"
echo "Response Body: $BODY"
echo ""

if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ Test 3 PASSED: Successfully updated school with authentication"
else
    echo "✗ Test 3 FAILED: Expected 200, got $HTTP_CODE"
fi
echo ""

# Test 4: Verify the update was applied
echo "Test 4: Verify the update was applied"
echo "Request: GET $BASE_URL/schools/1"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "$BASE_URL/schools/1" \
  -H "Authorization: Bearer $TOKEN")

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

echo "Response Code: $HTTP_CODE"
echo "Response Body: $BODY"
echo ""

if [ "$HTTP_CODE" = "200" ]; then
    # Check if the updated name is in the response
    if echo "$BODY" | grep -q "Updated School Name"; then
        echo "✓ Test 4 PASSED: School data was successfully updated"
    else
        echo "⚠ Test 4 WARNING: School retrieved but updated name not found in response"
    fi
else
    echo "✗ Test 4 FAILED: Expected 200, got $HTTP_CODE"
fi
echo ""

# Test 5: Test with invalid token
echo "Test 5: Update school with invalid token (should fail with 401)"
echo "Request: PUT $BASE_URL/schools/1"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X PUT "$BASE_URL/schools/1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer invalid_token_here" \
  -d '{"name":"Should Not Update"}')

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

echo "Response Code: $HTTP_CODE"
echo "Response Body: $BODY"
echo ""

if [ "$HTTP_CODE" = "401" ]; then
    echo "✓ Test 5 PASSED: Correctly rejected invalid token"
else
    echo "✗ Test 5 FAILED: Expected 401, got $HTTP_CODE"
fi
echo ""

# Test 6: Test with non-existent school ID
echo "Test 6: Update non-existent school (should fail with 404)"
echo "Request: PUT $BASE_URL/schools/999999"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X PUT "$BASE_URL/schools/999999" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"Non-existent School"}')

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

echo "Response Code: $HTTP_CODE"
echo "Response Body: $BODY"
echo ""

if [ "$HTTP_CODE" = "404" ]; then
    echo "✓ Test 6 PASSED: Correctly returned 404 for non-existent school"
else
    echo "✗ Test 6 FAILED: Expected 404, got $HTTP_CODE"
fi
echo ""

# Summary
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo "Task 10.4 Verification:"
echo "- Route: PUT /api/schools/{id}"
echo "- Handler: SchoolController::update"
echo "- Middleware: AuthMiddleware"
echo ""
echo "All tests completed. Review results above."
echo "=========================================="
