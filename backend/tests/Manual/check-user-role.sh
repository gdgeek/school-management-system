#!/bin/bash

BASE_URL="http://localhost:8084/api"

# Login
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "Login Response:"
echo "$LOGIN_RESPONSE" | jq '.'

# Extract token
TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token // empty')

if [ -z "$TOKEN" ]; then
  echo "Failed to get token"
  exit 1
fi

# Get user info
echo -e "\nUser Info:"
curl -s -X GET "$BASE_URL/auth/user" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
