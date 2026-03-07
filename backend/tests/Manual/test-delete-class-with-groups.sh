#!/bin/bash

# 测试删除班级功能 - 可选删除关联小组
# 验证 deleteGroups 参数是否正常工作

API_BASE="http://localhost:8084/api"
TOKEN=""

echo "=== 删除班级（可选删除小组）测试 ==="
echo ""

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. 登录获取 token
echo "步骤 1: 登录获取 token"
echo "-------------------"
LOGIN_RESPONSE=$(curl -s -X POST "$API_BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"guanfei","password":"123456"}')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)
USER_ID=$(echo $LOGIN_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$TOKEN" ]; then
    echo -e "${RED}✗ 登录失败${NC}"
    exit 1
fi

echo -e "${GREEN}✓ 登录成功 (用户ID: $USER_ID)${NC}"
echo ""

# ============================================================
# 测试场景 1: 删除班级，不删除小组
# ============================================================
echo "=========================================="
echo "测试场景 1: 删除班级，保留小组"
echo "=========================================="
echo ""

echo "步骤 2: 创建测试数据（场景1）"
echo "-------------------"

# 创建学校
SCHOOL_RESPONSE=$(curl -s -X POST "$API_BASE/schools" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"name\":\"测试学校-场景1\",\"principal_id\":$USER_ID}")

SCHOOL_ID_1=$(echo $SCHOOL_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
echo -e "${GREEN}✓ 创建学校 ID: $SCHOOL_ID_1${NC}"

# 创建班级（会自动创建同名小组）
CLASS_RESPONSE=$(curl -s -X POST "$API_BASE/classes" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"name\":\"测试班级-场景1\",\"school_id\":$SCHOOL_ID_1}")

CLASS_ID_1=$(echo $CLASS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
echo -e "${GREEN}✓ 创建班级 ID: $CLASS_ID_1${NC}"

# 获取小组 ID
GROUPS_RESPONSE=$(curl -s -X GET "$API_BASE/groups?page=1&pageSize=1" \
  -H "Authorization: Bearer $TOKEN")
GROUP_ID_1=$(echo $GROUPS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
echo -e "${GREEN}✓ 获取小组 ID: $GROUP_ID_1${NC}"
echo ""

echo "步骤 3: 删除班级（不删除小组）"
echo "-------------------"

DELETE_RESPONSE=$(curl -s -X DELETE "$API_BASE/classes/$CLASS_ID_1?deleteGroups=false" \
  -H "Authorization: Bearer $TOKEN")

if echo "$DELETE_RESPONSE" | grep -q '"code":200'; then
    echo -e "${GREEN}✓ 删除班级成功${NC}"
else
    echo -e "${RED}✗ 删除班级失败${NC}"
    echo "响应: $DELETE_RESPONSE"
fi

echo ""

echo "步骤 4: 验证结果（场景1）"
echo "-------------------"

sleep 1

# 验证班级已删除
CLASS_CHECK=$(curl -s -X GET "$API_BASE/classes/$CLASS_ID_1" \
  -H "Authorization: Bearer $TOKEN")

if echo "$CLASS_CHECK" | grep -q '"code":404'; then
    echo -e "${GREEN}✓ 班级已删除${NC}"
else
    echo -e "${RED}✗ 班级未删除${NC}"
fi

# 验证小组仍存在
GROUP_CHECK=$(curl -s -X GET "$API_BASE/groups/$GROUP_ID_1" \
  -H "Authorization: Bearer $TOKEN")

if echo "$GROUP_CHECK" | grep -q '"id"'; then
    echo -e "${GREEN}✓ 小组仍存在（符合预期）${NC}"
else
    echo -e "${RED}✗ 小组被删除了（不符合预期）${NC}"
fi

echo ""

# 清理场景1数据
curl -s -X DELETE "$API_BASE/groups/$GROUP_ID_1" -H "Authorization: Bearer $TOKEN" > /dev/null
curl -s -X DELETE "$API_BASE/schools/$SCHOOL_ID_1" -H "Authorization: Bearer $TOKEN" > /dev/null

# ============================================================
# 测试场景 2: 删除班级，同时删除小组
# ============================================================
echo "=========================================="
echo "测试场景 2: 删除班级，同时删除小组"
echo "=========================================="
echo ""

echo "步骤 5: 创建测试数据（场景2）"
echo "-------------------"

# 创建学校
SCHOOL_RESPONSE=$(curl -s -X POST "$API_BASE/schools" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"name\":\"测试学校-场景2\",\"principal_id\":$USER_ID}")

SCHOOL_ID_2=$(echo $SCHOOL_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
echo -e "${GREEN}✓ 创建学校 ID: $SCHOOL_ID_2${NC}"

# 创建班级
CLASS_RESPONSE=$(curl -s -X POST "$API_BASE/classes" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"name\":\"测试班级-场景2\",\"school_id\":$SCHOOL_ID_2}")

CLASS_ID_2=$(echo $CLASS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
echo -e "${GREEN}✓ 创建班级 ID: $CLASS_ID_2${NC}"

# 获取小组 ID
GROUPS_RESPONSE=$(curl -s -X GET "$API_BASE/groups?page=1&pageSize=1" \
  -H "Authorization: Bearer $TOKEN")
GROUP_ID_2=$(echo $GROUPS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
echo -e "${GREEN}✓ 获取小组 ID: $GROUP_ID_2${NC}"
echo ""

echo "步骤 6: 删除班级（同时删除小组）"
echo "-------------------"

DELETE_RESPONSE=$(curl -s -X DELETE "$API_BASE/classes/$CLASS_ID_2?deleteGroups=true" \
  -H "Authorization: Bearer $TOKEN")

if echo "$DELETE_RESPONSE" | grep -q '"code":200'; then
    echo -e "${GREEN}✓ 删除班级成功${NC}"
else
    echo -e "${RED}✗ 删除班级失败${NC}"
    echo "响应: $DELETE_RESPONSE"
fi

echo ""

echo "步骤 7: 验证结果（场景2）"
echo "-------------------"

sleep 1

# 验证班级已删除
CLASS_CHECK=$(curl -s -X GET "$API_BASE/classes/$CLASS_ID_2" \
  -H "Authorization: Bearer $TOKEN")

if echo "$CLASS_CHECK" | grep -q '"code":404'; then
    echo -e "${GREEN}✓ 班级已删除${NC}"
else
    echo -e "${RED}✗ 班级未删除${NC}"
fi

# 验证小组也被删除
GROUP_CHECK=$(curl -s -X GET "$API_BASE/groups/$GROUP_ID_2" \
  -H "Authorization: Bearer $TOKEN")

if echo "$GROUP_CHECK" | grep -q '"code":404'; then
    echo -e "${GREEN}✓ 小组已删除（符合预期）${NC}"
else
    echo -e "${RED}✗ 小组未删除（不符合预期）${NC}"
fi

echo ""

# 清理场景2数据
curl -s -X DELETE "$API_BASE/schools/$SCHOOL_ID_2" -H "Authorization: Bearer $TOKEN" > /dev/null

echo "=========================================="
echo "=== 测试完成 ==="
echo "=========================================="
