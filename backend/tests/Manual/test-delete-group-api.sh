#!/bin/bash

# 测试删除小组功能 - 通过 API 测试
# 验证删除小组时是否正确级联删除班级、教师和学生

API_BASE="http://localhost:8084/api"
TOKEN=""

echo "=== 删除小组级联删除 API 测试 ==="
echo ""

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# 1. 登录获取 token
echo "步骤 1: 登录获取 token"
echo "-------------------"
LOGIN_RESPONSE=$(curl -s -X POST "$API_BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"guanfei","password":"123456"}')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo -e "${RED}✗ 登录失败${NC}"
    echo "响应: $LOGIN_RESPONSE"
    exit 1
fi

# 获取当前用户 ID
USER_ID=$(echo $LOGIN_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

echo -e "${GREEN}✓ 登录成功 (用户ID: $USER_ID)${NC}"
echo ""

# 2. 创建学校
echo "步骤 2: 创建测试数据"
echo "-------------------"

SCHOOL_RESPONSE=$(curl -s -X POST "$API_BASE/schools" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"name\":\"测试学校-删除小组\",\"principal_id\":$USER_ID}")

SCHOOL_ID=$(echo $SCHOOL_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$SCHOOL_ID" ]; then
    echo -e "${RED}✗ 创建学校失败${NC}"
    echo "响应: $SCHOOL_RESPONSE"
    exit 1
fi

echo -e "${GREEN}✓ 创建学校 ID: $SCHOOL_ID${NC}"

# 3. 创建班级
CLASS_RESPONSE=$(curl -s -X POST "$API_BASE/classes" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"name\":\"测试班级-删除小组\",\"school_id\":$SCHOOL_ID}")

CLASS_ID=$(echo $CLASS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$CLASS_ID" ]; then
    echo -e "${RED}✗ 创建班级失败${NC}"
    echo "响应: $CLASS_RESPONSE"
    exit 1
fi

echo -e "${GREEN}✓ 创建班级 ID: $CLASS_ID${NC}"

# 4. 获取自动创建的小组 ID（创建班级时会自动创建同名小组）
CLASS_DETAIL=$(curl -s -X GET "$API_BASE/classes/$CLASS_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "班级详情: $CLASS_DETAIL"

# 从班级详情中提取小组信息（需要根据实际 API 响应调整）
# 这里我们先列出所有小组，找到最新创建的
GROUPS_RESPONSE=$(curl -s -X GET "$API_BASE/groups?page=1&pageSize=1" \
  -H "Authorization: Bearer $TOKEN")

GROUP_ID=$(echo $GROUPS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$GROUP_ID" ]; then
    echo -e "${RED}✗ 获取小组失败${NC}"
    echo "响应: $GROUPS_RESPONSE"
    exit 1
fi

echo -e "${GREEN}✓ 获取小组 ID: $GROUP_ID${NC}"
echo ""

# 5. 验证数据存在
echo "步骤 3: 验证数据存在"
echo "-------------------"

CLASS_CHECK=$(curl -s -X GET "$API_BASE/classes/$CLASS_ID" \
  -H "Authorization: Bearer $TOKEN")

if echo "$CLASS_CHECK" | grep -q '"id"'; then
    echo -e "${GREEN}✓ 班级存在${NC}"
else
    echo -e "${RED}✗ 班级不存在${NC}"
fi

GROUP_CHECK=$(curl -s -X GET "$API_BASE/groups/$GROUP_ID" \
  -H "Authorization: Bearer $TOKEN")

if echo "$GROUP_CHECK" | grep -q '"id"'; then
    echo -e "${GREEN}✓ 小组存在${NC}"
else
    echo -e "${RED}✗ 小组不存在${NC}"
fi

echo ""

# 6. 删除小组
echo "步骤 4: 删除小组"
echo "-------------------"

DELETE_RESPONSE=$(curl -s -X DELETE "$API_BASE/groups/$GROUP_ID" \
  -H "Authorization: Bearer $TOKEN")

if echo "$DELETE_RESPONSE" | grep -q '"code":200'; then
    echo -e "${GREEN}✓ 删除小组成功${NC}"
else
    echo -e "${RED}✗ 删除小组失败${NC}"
    echo "响应: $DELETE_RESPONSE"
fi

echo ""

# 7. 验证级联删除
echo "步骤 5: 验证级联删除"
echo "-------------------"

# 等待一下确保删除完成
sleep 1

CLASS_CHECK_AFTER=$(curl -s -X GET "$API_BASE/classes/$CLASS_ID" \
  -H "Authorization: Bearer $TOKEN")

if echo "$CLASS_CHECK_AFTER" | grep -q '"code":404'; then
    echo -e "${GREEN}✓ 班级已删除${NC}"
else
    echo -e "${RED}✗ 班级未删除${NC}"
    echo "响应: $CLASS_CHECK_AFTER"
fi

GROUP_CHECK_AFTER=$(curl -s -X GET "$API_BASE/groups/$GROUP_ID" \
  -H "Authorization: Bearer $TOKEN")

if echo "$GROUP_CHECK_AFTER" | grep -q '"code":404'; then
    echo -e "${GREEN}✓ 小组已删除${NC}"
else
    echo -e "${RED}✗ 小组未删除${NC}"
    echo "响应: $GROUP_CHECK_AFTER"
fi

echo ""

# 8. 清理测试数据
echo "步骤 6: 清理测试数据"
echo "-------------------"

curl -s -X DELETE "$API_BASE/schools/$SCHOOL_ID" \
  -H "Authorization: Bearer $TOKEN" > /dev/null

echo -e "${GREEN}✓ 清理完成${NC}"

echo ""
echo "=== 测试完成 ==="
