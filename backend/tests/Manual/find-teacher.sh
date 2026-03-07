#!/bin/bash

BASE_URL="http://localhost:8084/api"

# Try to find a teacher account by checking common usernames
for username in "teacher" "admin" "teacher01" "admin01"; do
  echo "Trying username: $username"
  LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d "{
      \"username\": \"$username\",
      \"password\": \"123456\"
    }")
  
  if echo "$LOGIN_RESPONSE" | grep -q '"token"'; then
    echo "✓ Found working account: $username"
    echo "$LOGIN_RESPONSE" | jq '.data.user.roles'
    break
  fi
done
