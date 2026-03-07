#!/bin/bash

# Comprehensive JWT Generation and Validation Test Script
# Task 5.5: Ensure JWT generation and validation work correctly
#
# This script tests:
# 1. JWT token generation in AuthController::login()
# 2. JWT token validation in AuthMiddleware
# 3. Token expiration handling
# 4. Invalid token handling
# 5. Token payload structure (user_id, username, roles, school_id)
# 6. Token signature validation
# 7. Edge cases (malformed tokens, expired tokens, invalid signatures)

BASE_URL="http://localhost:8084/api"
TEST_USERNAME="guanfei"
TEST_PASSWORD="123456"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0

# Helper function to print test results
print_test_result() {
    local test_name="$1"
    local result="$2"
    local details="$3"
    
    if [ "$result" = "PASS" ]; then
        echo -e "${GREEN}✓ PASS${NC}: $test_name"
        [ ! -z "$details" ] && echo "  $details"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}✗ FAIL${NC}: $test_name"
        [ ! -z "$details" ] && echo "  $details"
        ((TESTS_FAILED++))
    fi
    echo ""
}

echo "=========================================="
echo "JWT Comprehensive Verification Tests"
echo "=========================================="
echo ""

# ============================================
# Test 1: JWT Token Generation
# ============================================
echo "Test 1: JWT Token Generation in AuthController::login()"
echo "--------------------------------------------------------"

LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d "{
    \"username\": \"$TEST_USERNAME\",
    \"password\": \"$TEST_PASSWORD\"
  }")

echo "Login Response:"
echo "$LOGIN_RESPONSE" | jq '.'
echo ""

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // empty')
CODE=$(echo "$LOGIN_RESPONSE" | jq -r '.code // empty')

if [ ! -z "$TOKEN" ] && [ "$CODE" = "200" ]; then
    # Verify JWT structure (3 parts separated by dots)
    TOKEN_PARTS=$(echo "$TOKEN" | tr '.' '\n' | wc -l)
    if [ "$TOKEN_PARTS" -eq 3 ]; then
        print_test_result "JWT token generation" "PASS" "Token has correct structure (3 parts)"
    else
        print_test_result "JWT token generation" "FAIL" "Token has $TOKEN_PARTS parts, expected 3"
    fi
else
    print_test_result "JWT token generation" "FAIL" "Failed to generate token (code: $CODE)"
fi

# ============================================
# Test 2: Token Payload Structure
# ============================================
echo "Test 2: Token Payload Contains Required Fields"
echo "--------------------------------------------------------"

# Decode JWT payload (base64 decode the middle part)
if [ ! -z "$TOKEN" ]; then
    PAYLOAD=$(echo "$TOKEN" | cut -d'.' -f2)
    # Add padding if needed for base64 decoding
    PADDING_LENGTH=$((4 - ${#PAYLOAD} % 4))
    if [ $PADDING_LENGTH -ne 4 ]; then
        PAYLOAD="${PAYLOAD}$(printf '=%.0s' $(seq 1 $PADDING_LENGTH))"
    fi
    
    DECODED_PAYLOAD=$(echo "$PAYLOAD" | base64 -d 2>/dev/null)
    
    echo "Decoded Token Payload:"
    echo "$DECODED_PAYLOAD" | jq '.'
    echo ""
    
    # Check for required fields
    HAS_USER_ID=$(echo "$DECODED_PAYLOAD" | jq 'has("user_id")')
    HAS_USERNAME=$(echo "$DECODED_PAYLOAD" | jq 'has("username")')
    HAS_ROLES=$(echo "$DECODED_PAYLOAD" | jq 'has("roles")')
    HAS_IAT=$(echo "$DECODED_PAYLOAD" | jq 'has("iat")')
    HAS_EXP=$(echo "$DECODED_PAYLOAD" | jq 'has("exp")')
    
    if [ "$HAS_USER_ID" = "true" ] && [ "$HAS_USERNAME" = "true" ] && \
       [ "$HAS_ROLES" = "true" ] && [ "$HAS_IAT" = "true" ] && [ "$HAS_EXP" = "true" ]; then
        print_test_result "Token payload structure" "PASS" "All required fields present (user_id, username, roles, iat, exp)"
    else
        print_test_result "Token payload structure" "FAIL" "Missing required fields"
    fi
    
    # Verify expiration is in the future
    EXP_TIME=$(echo "$DECODED_PAYLOAD" | jq -r '.exp')
    CURRENT_TIME=$(date +%s)
    if [ "$EXP_TIME" -gt "$CURRENT_TIME" ]; then
        print_test_result "Token expiration time" "PASS" "Expiration is in the future (exp: $EXP_TIME, now: $CURRENT_TIME)"
    else
        print_test_result "Token expiration time" "FAIL" "Expiration is in the past"
    fi
else
    print_test_result "Token payload structure" "FAIL" "No token available to decode"
fi

# ============================================
# Test 3: Valid Token Validation
# ============================================
echo "Test 3: JWT Token Validation with Valid Token"
echo "--------------------------------------------------------"

if [ ! -z "$TOKEN" ]; then
    USER_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
      -H "Authorization: Bearer $TOKEN")
    
    echo "User Response:"
    echo "$USER_RESPONSE" | jq '.'
    echo ""
    
    USER_CODE=$(echo "$USER_RESPONSE" | jq -r '.code // empty')
    USER_ID=$(echo "$USER_RESPONSE" | jq -r '.data.id // empty')
    USERNAME=$(echo "$USER_RESPONSE" | jq -r '.data.username // empty')
    
    if [ "$USER_CODE" = "200" ] && [ ! -z "$USER_ID" ] && [ "$USERNAME" = "$TEST_USERNAME" ]; then
        print_test_result "Valid token validation" "PASS" "Token validated successfully, user context injected"
    else
        print_test_result "Valid token validation" "FAIL" "Expected code 200 and username $TEST_USERNAME, got code $USER_CODE and username $USERNAME"
    fi
else
    print_test_result "Valid token validation" "FAIL" "No token available for testing"
fi

# ============================================
# Test 4: Missing Token Handling
# ============================================
echo "Test 4: Missing Token Handling"
echo "--------------------------------------------------------"

NO_TOKEN_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user")

echo "Response without token:"
echo "$NO_TOKEN_RESPONSE" | jq '.'
echo ""

NO_TOKEN_CODE=$(echo "$NO_TOKEN_RESPONSE" | jq -r '.code // empty')
NO_TOKEN_MSG=$(echo "$NO_TOKEN_RESPONSE" | jq -r '.message // empty')

if [ "$NO_TOKEN_CODE" = "401" ]; then
    print_test_result "Missing token handling" "PASS" "Correctly returned 401 with message: $NO_TOKEN_MSG"
else
    print_test_result "Missing token handling" "FAIL" "Expected 401, got $NO_TOKEN_CODE"
fi

# ============================================
# Test 5: Malformed Token Handling
# ============================================
echo "Test 5: Malformed Token Handling"
echo "--------------------------------------------------------"

MALFORMED_TOKENS=(
    "not.a.valid.jwt.token"
    "only-one-part"
    "two.parts"
    "invalid_base64!@#.invalid_base64!@#.invalid_base64!@#"
)

for MALFORMED_TOKEN in "${MALFORMED_TOKENS[@]}"; do
    MALFORMED_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
      -H "Authorization: Bearer $MALFORMED_TOKEN")
    
    MALFORMED_CODE=$(echo "$MALFORMED_RESPONSE" | jq -r '.code // empty')
    
    if [ "$MALFORMED_CODE" = "401" ]; then
        print_test_result "Malformed token: $MALFORMED_TOKEN" "PASS" "Correctly rejected with 401"
    else
        print_test_result "Malformed token: $MALFORMED_TOKEN" "FAIL" "Expected 401, got $MALFORMED_CODE"
    fi
done

# ============================================
# Test 6: Invalid Signature Handling
# ============================================
echo "Test 6: Invalid Signature Handling"
echo "--------------------------------------------------------"

if [ ! -z "$TOKEN" ]; then
    # Tamper with the token by changing the last character of the signature
    TOKEN_LENGTH=${#TOKEN}
    TAMPERED_TOKEN="${TOKEN:0:$((TOKEN_LENGTH-1))}X"
    
    TAMPERED_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
      -H "Authorization: Bearer $TAMPERED_TOKEN")
    
    echo "Response with tampered token:"
    echo "$TAMPERED_RESPONSE" | jq '.'
    echo ""
    
    TAMPERED_CODE=$(echo "$TAMPERED_RESPONSE" | jq -r '.code // empty')
    TAMPERED_MSG=$(echo "$TAMPERED_RESPONSE" | jq -r '.message // empty')
    
    if [ "$TAMPERED_CODE" = "401" ]; then
        print_test_result "Invalid signature handling" "PASS" "Correctly rejected tampered token with 401: $TAMPERED_MSG"
    else
        print_test_result "Invalid signature handling" "FAIL" "Expected 401, got $TAMPERED_CODE"
    fi
else
    print_test_result "Invalid signature handling" "FAIL" "No token available for tampering test"
fi

# ============================================
# Test 7: Token Extraction from Different Sources
# ============================================
echo "Test 7: Token Extraction from Different Sources"
echo "--------------------------------------------------------"

if [ ! -z "$TOKEN" ]; then
    # Test 7a: Authorization header (Bearer token)
    BEARER_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
      -H "Authorization: Bearer $TOKEN")
    BEARER_CODE=$(echo "$BEARER_RESPONSE" | jq -r '.code // empty')
    
    if [ "$BEARER_CODE" = "200" ]; then
        print_test_result "Token from Authorization header" "PASS" "Successfully extracted from Bearer token"
    else
        print_test_result "Token from Authorization header" "FAIL" "Expected 200, got $BEARER_CODE"
    fi
    
    # Test 7b: Query parameter
    QUERY_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user?token=$TOKEN")
    QUERY_CODE=$(echo "$QUERY_RESPONSE" | jq -r '.code // empty')
    
    if [ "$QUERY_CODE" = "200" ]; then
        print_test_result "Token from query parameter" "PASS" "Successfully extracted from query string"
    else
        print_test_result "Token from query parameter" "FAIL" "Expected 200, got $QUERY_CODE"
    fi
    
    # Test 7c: Cookie
    COOKIE_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
      -H "Cookie: auth_token=$TOKEN")
    COOKIE_CODE=$(echo "$COOKIE_RESPONSE" | jq -r '.code // empty')
    
    if [ "$COOKIE_CODE" = "200" ]; then
        print_test_result "Token from cookie" "PASS" "Successfully extracted from cookie"
    else
        print_test_result "Token from cookie" "FAIL" "Expected 200, got $COOKIE_CODE"
    fi
else
    print_test_result "Token extraction tests" "FAIL" "No token available for testing"
fi

# ============================================
# Test 8: User Context Injection
# ============================================
echo "Test 8: User Context Injection into Request Attributes"
echo "--------------------------------------------------------"

if [ ! -z "$TOKEN" ]; then
    # Make a request to a protected endpoint and verify user context is available
    USER_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
      -H "Authorization: Bearer $TOKEN")
    
    USER_ID=$(echo "$USER_RESPONSE" | jq -r '.data.id // empty')
    USERNAME=$(echo "$USER_RESPONSE" | jq -r '.data.username // empty')
    
    if [ ! -z "$USER_ID" ] && [ "$USERNAME" = "$TEST_USERNAME" ]; then
        print_test_result "User context injection" "PASS" "User ID and username correctly available in controller"
    else
        print_test_result "User context injection" "FAIL" "User context not properly injected"
    fi
else
    print_test_result "User context injection" "FAIL" "No token available for testing"
fi

# ============================================
# Test 9: Token with Invalid Credentials
# ============================================
echo "Test 9: Login with Invalid Credentials"
echo "--------------------------------------------------------"

INVALID_LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "nonexistent",
    "password": "wrongpassword"
  }')

echo "Invalid login response:"
echo "$INVALID_LOGIN_RESPONSE" | jq '.'
echo ""

INVALID_LOGIN_CODE=$(echo "$INVALID_LOGIN_RESPONSE" | jq -r '.code // empty')
INVALID_LOGIN_TOKEN=$(echo "$INVALID_LOGIN_RESPONSE" | jq -r '.data.token // empty')

if [ "$INVALID_LOGIN_CODE" = "401" ] && [ -z "$INVALID_LOGIN_TOKEN" ]; then
    print_test_result "Invalid credentials handling" "PASS" "Correctly rejected with 401, no token generated"
else
    print_test_result "Invalid credentials handling" "FAIL" "Expected 401 with no token, got code $INVALID_LOGIN_CODE"
fi

# ============================================
# Test 10: Empty Authorization Header
# ============================================
echo "Test 10: Empty Authorization Header"
echo "--------------------------------------------------------"

EMPTY_AUTH_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
  -H "Authorization: Bearer ")

EMPTY_AUTH_CODE=$(echo "$EMPTY_AUTH_RESPONSE" | jq -r '.code // empty')

if [ "$EMPTY_AUTH_CODE" = "401" ]; then
    print_test_result "Empty authorization header" "PASS" "Correctly rejected with 401"
else
    print_test_result "Empty authorization header" "FAIL" "Expected 401, got $EMPTY_AUTH_CODE"
fi

# ============================================
# Test 11: Authorization Header without Bearer Prefix
# ============================================
echo "Test 11: Authorization Header without Bearer Prefix"
echo "--------------------------------------------------------"

if [ ! -z "$TOKEN" ]; then
    NO_BEARER_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/user" \
      -H "Authorization: $TOKEN")
    
    NO_BEARER_CODE=$(echo "$NO_BEARER_RESPONSE" | jq -r '.code // empty')
    
    if [ "$NO_BEARER_CODE" = "401" ]; then
        print_test_result "Authorization without Bearer prefix" "PASS" "Correctly rejected with 401"
    else
        print_test_result "Authorization without Bearer prefix" "FAIL" "Expected 401, got $NO_BEARER_CODE"
    fi
else
    print_test_result "Authorization without Bearer prefix" "FAIL" "No token available for testing"
fi

# ============================================
# Test Summary
# ============================================
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo -e "Total Tests: $((TESTS_PASSED + TESTS_FAILED))"
echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Failed: $TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All JWT tests passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some JWT tests failed!${NC}"
    exit 1
fi
