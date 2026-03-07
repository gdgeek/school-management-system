#!/bin/bash

# 测试小组详情 API 的认证和响应格式
# 验证任务 5.3：GET /api/groups/{id} 返回 creator 和 members 字段，并包含认证检查

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试小组详情 API (任务 5.3)"
echo "=========================================="
echo ""

# 步骤 1: 登录获取 token
echo "步骤 1: 登录获取 JWT token..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "登录响应: $LOGIN_RESPONSE"
TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
  echo "❌ 错误: 无法获取 token"
  exit 1
fi

echo "✓ 成功获取 token: ${TOKEN:0:20}..."
echo ""

# 步骤 2: 测试未认证访问（应返回 401）
echo "步骤 2: 测试未认证访问小组详情..."
UNAUTH_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "$BASE_URL/groups/1")
HTTP_CODE=$(echo "$UNAUTH_RESPONSE" | grep "HTTP_CODE:" | cut -d':' -f2)
BODY=$(echo "$UNAUTH_RESPONSE" | sed '/HTTP_CODE:/d')

echo "HTTP 状态码: $HTTP_CODE"
echo "响应体: $BODY"

if [ "$HTTP_CODE" = "401" ]; then
  echo "✓ 正确返回 401 Unauthorized"
else
  echo "❌ 错误: 应返回 401，实际返回 $HTTP_CODE"
fi
echo ""

# 步骤 3: 获取小组列表找到一个有效的小组 ID
echo "步骤 3: 获取小组列表..."
GROUPS_RESPONSE=$(curl -s -X GET "$BASE_URL/groups?page=1&pageSize=10" \
  -H "Authorization: Bearer $TOKEN")

echo "小组列表响应: $GROUPS_RESPONSE"

# 提取第一个小组的 ID
GROUP_ID=$(echo $GROUPS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$GROUP_ID" ]; then
  echo "⚠ 警告: 没有找到小组，尝试使用 ID 1"
  GROUP_ID=1
else
  echo "✓ 找到小组 ID: $GROUP_ID"
fi
echo ""

# 步骤 4: 测试已认证访问小组详情
echo "步骤 4: 测试已认证访问小组详情 (ID: $GROUP_ID)..."
DETAIL_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "$BASE_URL/groups/$GROUP_ID" \
  -H "Authorization: Bearer $TOKEN")

HTTP_CODE=$(echo "$DETAIL_RESPONSE" | grep "HTTP_CODE:" | cut -d':' -f2)
BODY=$(echo "$DETAIL_RESPONSE" | sed '/HTTP_CODE:/d')

echo "HTTP 状态码: $HTTP_CODE"
echo "响应体: $BODY"
echo ""

# 步骤 5: 验证响应格式
echo "步骤 5: 验证响应格式..."

if [ "$HTTP_CODE" = "200" ]; then
  echo "✓ HTTP 状态码正确 (200)"
  
  # 检查是否包含 creator 字段
  if echo "$BODY" | grep -q '"creator"'; then
    echo "✓ 响应包含 creator 字段"
    
    # 检查 creator 的子字段
    if echo "$BODY" | grep -q '"creator":{[^}]*"id"' && \
       echo "$BODY" | grep -q '"creator":{[^}]*"username"' && \
       echo "$BODY" | grep -q '"creator":{[^}]*"nickname"'; then
      echo "✓ creator 包含必需字段 (id, username, nickname)"
    else
      echo "❌ creator 缺少必需字段"
    fi
  else
    echo "❌ 响应缺少 creator 字段"
  fi
  
  # 检查是否包含 members 字段
  if echo "$BODY" | grep -q '"members"'; then
    echo "✓ 响应包含 members 字段"
  else
    echo "❌ 响应缺少 members 字段"
  fi
  
elif [ "$HTTP_CODE" = "404" ]; then
  echo "⚠ 小组不存在 (404)，这是正常的错误处理"
else
  echo "❌ 错误: 期望 200 或 404，实际返回 $HTTP_CODE"
fi
echo ""

# 步骤 6: 测试不存在的小组（应返回 404）
echo "步骤 6: 测试访问不存在的小组..."
NOT_FOUND_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "$BASE_URL/groups/999999" \
  -H "Authorization: Bearer $TOKEN")

HTTP_CODE=$(echo "$NOT_FOUND_RESPONSE" | grep "HTTP_CODE:" | cut -d':' -f2)
BODY=$(echo "$NOT_FOUND_RESPONSE" | sed '/HTTP_CODE:/d')

echo "HTTP 状态码: $HTTP_CODE"
echo "响应体: $BODY"

if [ "$HTTP_CODE" = "404" ]; then
  echo "✓ 正确返回 404 Not Found"
else
  echo "❌ 错误: 应返回 404，实际返回 $HTTP_CODE"
fi
echo ""

echo "=========================================="
echo "测试完成"
echo "=========================================="
