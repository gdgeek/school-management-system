#!/bin/bash

# =============================================================================
# School Management System - Simple Load Test (curl-based)
#
# A lightweight load test script that uses curl for environments
# where k6 is not available. Tests key API endpoints.
#
# Usage:
#   ./load-test.sh                          # Default: localhost:8084, 5 concurrent
#   ./load-test.sh http://myserver:8084 10  # Custom URL, 10 concurrent
#   AUTH_TOKEN=<jwt> ./load-test.sh         # With authentication
# =============================================================================

set -euo pipefail

BASE_URL="${1:-http://localhost:8084}"
CONCURRENCY="${2:-5}"
REQUESTS_PER_ENDPOINT="${3:-20}"
AUTH_TOKEN="${AUTH_TOKEN:-}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

RESULTS_DIR="$(dirname "$0")/results"
mkdir -p "$RESULTS_DIR"
REPORT_FILE="$RESULTS_DIR/load-test-$(date +%Y%m%d-%H%M%S).txt"

AUTH_HEADER=""
if [ -n "$AUTH_TOKEN" ]; then
  AUTH_HEADER="-H \"Authorization: Bearer $AUTH_TOKEN\""
fi

echo "============================================="
echo "  School Management System - Load Test"
echo "============================================="
echo "  Base URL:     $BASE_URL"
echo "  Concurrency:  $CONCURRENCY"
echo "  Requests/EP:  $REQUESTS_PER_ENDPOINT"
echo "  Auth Token:   $([ -n "$AUTH_TOKEN" ] && echo 'provided' || echo 'none')"
echo "  Report:       $REPORT_FILE"
echo "============================================="
echo ""

# Write report header
{
  echo "Load Test Report - $(date)"
  echo "Base URL: $BASE_URL"
  echo "Concurrency: $CONCURRENCY"
  echo "Requests per endpoint: $REQUESTS_PER_ENDPOINT"
  echo "============================================="
  echo ""
} > "$REPORT_FILE"

# Function to test a single endpoint with curl
test_endpoint() {
  local method="$1"
  local url="$2"
  local label="$3"
  local data="${4:-}"

  echo -e "${CYAN}Testing: $label ($method $url)${NC}"

  local total_time=0
  local min_time=999999
  local max_time=0
  local success=0
  local fail=0

  for i in $(seq 1 "$REQUESTS_PER_ENDPOINT"); do
    local start_ms
    start_ms=$(date +%s%N 2>/dev/null || python3 -c 'import time; print(int(time.time()*1000000000))')

    local http_code
    if [ "$method" = "GET" ]; then
      http_code=$(curl -s -o /dev/null -w "%{http_code}" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
        --max-time 10 \
        "$url" 2>/dev/null || echo "000")
    elif [ "$method" = "POST" ]; then
      http_code=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
        -d "$data" \
        --max-time 10 \
        "$url" 2>/dev/null || echo "000")
    fi

    local end_ms
    end_ms=$(date +%s%N 2>/dev/null || python3 -c 'import time; print(int(time.time()*1000000000))')

    local duration_ms=$(( (end_ms - start_ms) / 1000000 ))

    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 400 ] 2>/dev/null; then
      success=$((success + 1))
    else
      fail=$((fail + 1))
    fi

    total_time=$((total_time + duration_ms))
    [ "$duration_ms" -lt "$min_time" ] && min_time=$duration_ms
    [ "$duration_ms" -gt "$max_time" ] && max_time=$duration_ms
  done

  local avg_time=$((total_time / REQUESTS_PER_ENDPOINT))
  local success_rate=$((success * 100 / REQUESTS_PER_ENDPOINT))

  # Color based on avg response time
  local color="$GREEN"
  [ "$avg_time" -gt 1000 ] && color="$YELLOW"
  [ "$avg_time" -gt 2000 ] && color="$RED"

  echo -e "  Avg: ${color}${avg_time}ms${NC} | Min: ${min_time}ms | Max: ${max_time}ms | Success: ${success_rate}%"

  # Write to report
  printf "%-40s avg=%5dms  min=%5dms  max=%5dms  success=%3d%%\n" \
    "$label" "$avg_time" "$min_time" "$max_time" "$success_rate" >> "$REPORT_FILE"
}

# --- Health Check ---
echo -e "\n${YELLOW}[1/5] Health Check${NC}"
echo "" >> "$REPORT_FILE"
echo "[Health Check]" >> "$REPORT_FILE"
test_endpoint "GET" "$BASE_URL/health" "Health Check"

# --- List Endpoints ---
echo -e "\n${YELLOW}[2/5] List Endpoints (Pagination)${NC}"
echo "" >> "$REPORT_FILE"
echo "[List Endpoints]" >> "$REPORT_FILE"
test_endpoint "GET" "$BASE_URL/api/schools?page=1&pageSize=20" "Schools List (page=1, size=20)"
test_endpoint "GET" "$BASE_URL/api/classes?page=1&pageSize=20" "Classes List (page=1, size=20)"
test_endpoint "GET" "$BASE_URL/api/teachers?page=1&pageSize=20" "Teachers List (page=1, size=20)"
test_endpoint "GET" "$BASE_URL/api/students?page=1&pageSize=20" "Students List (page=1, size=20)"
test_endpoint "GET" "$BASE_URL/api/groups?page=1&pageSize=20" "Groups List (page=1, size=20)"

# --- Pagination Sizes ---
echo -e "\n${YELLOW}[3/5] Pagination Performance${NC}"
echo "" >> "$REPORT_FILE"
echo "[Pagination Performance]" >> "$REPORT_FILE"
test_endpoint "GET" "$BASE_URL/api/schools?page=1&pageSize=10" "Schools (pageSize=10)"
test_endpoint "GET" "$BASE_URL/api/schools?page=1&pageSize=50" "Schools (pageSize=50)"
test_endpoint "GET" "$BASE_URL/api/schools?page=1&pageSize=100" "Schools (pageSize=100)"

# --- Search ---
echo -e "\n${YELLOW}[4/5] Search Performance${NC}"
echo "" >> "$REPORT_FILE"
echo "[Search Performance]" >> "$REPORT_FILE"
test_endpoint "GET" "$BASE_URL/api/schools?page=1&pageSize=20&search=test" "Schools Search (term=test)"
test_endpoint "GET" "$BASE_URL/api/classes?page=1&pageSize=20&search=class" "Classes Search (term=class)"

# --- Concurrent Requests ---
echo -e "\n${YELLOW}[5/5] Concurrent Request Test${NC}"
echo "" >> "$REPORT_FILE"
echo "[Concurrent Requests - $CONCURRENCY parallel]" >> "$REPORT_FILE"

echo -e "${CYAN}Sending $CONCURRENCY concurrent requests to /api/schools...${NC}"
concurrent_start=$(date +%s%N 2>/dev/null || python3 -c 'import time; print(int(time.time()*1000000000))')

pids=()
for i in $(seq 1 "$CONCURRENCY"); do
  curl -s -o /dev/null -w "%{http_code} %{time_total}\n" \
    -H "Content-Type: application/json" \
    ${AUTH_TOKEN:+-H "Authorization: Bearer $AUTH_TOKEN"} \
    "$BASE_URL/api/schools?page=1&pageSize=20" >> "$RESULTS_DIR/concurrent_tmp.txt" 2>/dev/null &
  pids+=($!)
done

for pid in "${pids[@]}"; do
  wait "$pid" 2>/dev/null || true
done

concurrent_end=$(date +%s%N 2>/dev/null || python3 -c 'import time; print(int(time.time()*1000000000))')
concurrent_total=$(( (concurrent_end - concurrent_start) / 1000000 ))

echo -e "  Total time for $CONCURRENCY concurrent requests: ${GREEN}${concurrent_total}ms${NC}"
echo "  $CONCURRENCY concurrent requests completed in ${concurrent_total}ms" >> "$REPORT_FILE"

rm -f "$RESULTS_DIR/concurrent_tmp.txt"

# --- Summary ---
echo ""
echo "============================================="
echo -e "  ${GREEN}Load test complete!${NC}"
echo "  Report saved to: $REPORT_FILE"
echo "============================================="
