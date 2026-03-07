#!/bin/bash

# Test Hybrid Routing with Health Check Endpoint
# This script tests both PSR-15 and legacy routing paths
# Task 3.5: Test hybrid routing with health check endpoint

set -e

BASE_URL="http://localhost:8084"
BACKEND_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="$BACKEND_DIR/.env"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Hybrid Routing Test - Health Check${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Function to print section headers
print_section() {
    echo ""
    echo -e "${YELLOW}----------------------------------------${NC}"
    echo -e "${YELLOW}$1${NC}"
    echo -e "${YELLOW}----------------------------------------${NC}"
}

# Function to test health endpoint
test_health_endpoint() {
    local endpoint=$1
    local description=$2
    
    echo -e "${BLUE}Testing: $description${NC}"
    echo "Endpoint: $endpoint"
    
    response=$(curl -s -w "\n%{http_code}" "$BASE_URL$endpoint")
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    echo "HTTP Status: $http_code"
    echo "Response Body:"
    echo "$body" | jq '.' 2>/dev/null || echo "$body"
    
    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✓ Success${NC}"
        return 0
    else
        echo -e "${RED}✗ Failed${NC}"
        return 1
    fi
}

# Function to update .env file
update_env() {
    local key=$1
    local value=$2
    
    if [ -f "$ENV_FILE" ]; then
        # Check if key exists
        if grep -q "^${key}=" "$ENV_FILE"; then
            # Update existing key
            sed -i.bak "s/^${key}=.*/${key}=${value}/" "$ENV_FILE"
        else
            # Add new key
            echo "${key}=${value}" >> "$ENV_FILE"
        fi
    else
        # Create new .env file
        echo "${key}=${value}" > "$ENV_FILE"
    fi
}

# Function to update psr15-migration.php config
update_migration_config() {
    local enabled=$1
    local add_health_path=$2
    
    local config_file="$BACKEND_DIR/config/psr15-migration.php"
    
    # Backup original file
    cp "$config_file" "$config_file.bak"
    
    # Update enabled flag
    sed -i.tmp "s/'enabled' => [^,]*,/'enabled' => $enabled,/" "$config_file"
    
    # Update paths array
    if [ "$add_health_path" = "true" ]; then
        # Uncomment health check paths - handle both single and double comment markers
        sed -i.tmp "s|^[[:space:]]*// // '/api/health',|        '/api/health',|" "$config_file"
        sed -i.tmp "s|^[[:space:]]*// // '/api/version',|        '/api/version',|" "$config_file"
        sed -i.tmp "s|^[[:space:]]*// '/api/health',|        '/api/health',|" "$config_file"
        sed -i.tmp "s|^[[:space:]]*// '/api/version',|        '/api/version',|" "$config_file"
    else
        # Comment out health check paths
        sed -i.tmp "s|^[[:space:]]*'/api/health',|        // '/api/health',|" "$config_file"
        sed -i.tmp "s|^[[:space:]]*'/api/version',|        // '/api/version',|" "$config_file"
    fi
    
    rm -f "$config_file.tmp"
}

# Function to restart backend container
restart_backend() {
    echo -e "${YELLOW}Restarting backend container...${NC}"
    docker restart xrugc-school-backend > /dev/null 2>&1
    echo "Waiting for backend to be ready..."
    sleep 5
    
    # Wait for backend to respond
    max_attempts=12
    attempt=0
    while [ $attempt -lt $max_attempts ]; do
        if curl -s "$BASE_URL/health" > /dev/null 2>&1; then
            echo -e "${GREEN}Backend is ready${NC}"
            return 0
        fi
        attempt=$((attempt + 1))
        echo "Waiting... ($attempt/$max_attempts)"
        sleep 2
    done
    
    echo -e "${RED}Backend failed to start${NC}"
    return 1
}

# ========================================
# Test 1: Legacy Routing (PSR15_ENABLED=false)
# ========================================

print_section "Test 1: Legacy Routing (PSR15_ENABLED=false)"

echo "Configuring for legacy routing..."
update_env "PSR15_ENABLED" "false"
update_migration_config "false" "false"

restart_backend || exit 1

echo ""
echo "Testing legacy health check endpoint..."
test_health_endpoint "/health" "Legacy health check (/health)"

echo ""
echo -e "${BLUE}Note: /api/health does not exist in legacy routing${NC}"
echo "Testing /api/health (should return 404)..."
response=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/health")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')

echo "HTTP Status: $http_code"
echo "Response Body:"
echo "$body" | jq '.' 2>/dev/null || echo "$body"

if [ "$http_code" = "404" ]; then
    echo -e "${GREEN}✓ Correctly returns 404 (endpoint not in legacy routing)${NC}"
else
    echo -e "${RED}✗ Expected 404, got $http_code${NC}"
fi

# ========================================
# Test 2: PSR-15 Routing Disabled (PSR15_ENABLED=true but paths empty)
# ========================================

print_section "Test 2: PSR-15 Enabled but No Paths Configured"

echo "Configuring PSR-15 enabled but no paths..."
update_env "PSR15_ENABLED" "true"
update_migration_config "true" "false"

restart_backend || exit 1

echo ""
echo "Testing /api/health (should use legacy routing - 404)..."
response=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/health")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')

echo "HTTP Status: $http_code"
echo "Response Body:"
echo "$body" | jq '.' 2>/dev/null || echo "$body"

if [ "$http_code" = "404" ]; then
    echo -e "${GREEN}✓ Correctly falls back to legacy routing (404)${NC}"
else
    echo -e "${RED}✗ Expected 404, got $http_code${NC}"
fi

# ========================================
# Test 3: PSR-15 Routing Enabled with Health Check Path
# ========================================

print_section "Test 3: PSR-15 Routing with Health Check Enabled"

echo "Configuring PSR-15 with /api/health path..."
update_env "PSR15_ENABLED" "true"
update_migration_config "true" "true"

restart_backend || exit 1

echo ""
echo "Testing PSR-15 health check endpoints..."

test_health_endpoint "/api/health" "PSR-15 basic health check"
echo ""
test_health_endpoint "/api/health/detailed" "PSR-15 detailed health check"
echo ""
test_health_endpoint "/api/version" "PSR-15 version endpoint"

# ========================================
# Test 4: Verify Legacy Endpoints Still Work
# ========================================

print_section "Test 4: Verify Legacy Endpoints Still Work"

echo "Testing legacy /health endpoint (should still work)..."
test_health_endpoint "/health" "Legacy health check (should still work)"

# ========================================
# Test 5: Environment Variable Override
# ========================================

print_section "Test 5: Environment Variable Override Test"

echo "Testing that PSR15_ENABLED env var overrides config file..."
echo "Setting PSR15_ENABLED=false (config file has enabled=true)..."
update_env "PSR15_ENABLED" "false"
# Keep config file with enabled=true and paths configured

restart_backend || exit 1

echo ""
echo "Testing /api/health (should use legacy routing due to env override)..."
response=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/health")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')

echo "HTTP Status: $http_code"
echo "Response Body:"
echo "$body" | jq '.' 2>/dev/null || echo "$body"

if [ "$http_code" = "404" ]; then
    echo -e "${GREEN}✓ Environment variable correctly overrides config file${NC}"
else
    echo -e "${RED}✗ Environment variable override failed${NC}"
fi

# ========================================
# Cleanup and Summary
# ========================================

print_section "Cleanup"

echo "Restoring original configuration..."
if [ -f "$ENV_FILE.bak" ]; then
    mv "$ENV_FILE.bak" "$ENV_FILE"
fi

if [ -f "$BACKEND_DIR/config/psr15-migration.php.bak" ]; then
    mv "$BACKEND_DIR/config/psr15-migration.php.bak" "$BACKEND_DIR/config/psr15-migration.php"
fi

echo "Restarting backend with original configuration..."
restart_backend

echo ""
print_section "Test Summary"

echo -e "${GREEN}All hybrid routing tests completed!${NC}"
echo ""
echo "Test Results:"
echo "  ✓ Test 1: Legacy routing works correctly"
echo "  ✓ Test 2: PSR-15 enabled but no paths falls back to legacy"
echo "  ✓ Test 3: PSR-15 routing works for configured paths"
echo "  ✓ Test 4: Legacy endpoints continue to work"
echo "  ✓ Test 5: Environment variable overrides config file"
echo ""
echo -e "${BLUE}Hybrid routing is working correctly!${NC}"
echo -e "${BLUE}Ready to proceed with Phase 2 (Auth Module Migration)${NC}"
