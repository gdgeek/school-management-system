#!/bin/bash

# =============================================================================
# School Management System - Test Runner
# Runs both frontend and backend test suites
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

FRONTEND_EXIT=0
BACKEND_EXIT=0

echo "============================================="
echo "  School Management System - Test Runner"
echo "============================================="
echo ""

# --- Frontend Tests ---
echo -e "${YELLOW}[1/2] Running Frontend Tests (Vitest)...${NC}"
echo "---------------------------------------------"

if [ -d "$PROJECT_DIR/frontend/node_modules" ]; then
    cd "$PROJECT_DIR/frontend"
    npx vitest --run 2>&1 || FRONTEND_EXIT=$?
    cd "$PROJECT_DIR"
else
    echo -e "${YELLOW}  ⚠ node_modules not found. Run 'npm install' in frontend/ first.${NC}"
    FRONTEND_EXIT=1
fi

echo ""

# --- Backend Tests ---
echo -e "${YELLOW}[2/2] Running Backend Tests (PHPUnit)...${NC}"
echo "---------------------------------------------"

if [ -f "$PROJECT_DIR/backend/vendor/autoload.php" ]; then
    cd "$PROJECT_DIR/backend"
    ./vendor/bin/phpunit 2>&1 || BACKEND_EXIT=$?
    cd "$PROJECT_DIR"
else
    echo -e "${YELLOW}  ⚠ vendor/ not found. Run 'composer install' in backend/ first.${NC}"
    BACKEND_EXIT=1
fi

echo ""

# --- Summary ---
echo "============================================="
echo "  Test Results Summary"
echo "============================================="

if [ $FRONTEND_EXIT -eq 0 ]; then
    echo -e "  Frontend: ${GREEN}PASSED${NC}"
else
    echo -e "  Frontend: ${RED}FAILED${NC} (exit code: $FRONTEND_EXIT)"
fi

if [ $BACKEND_EXIT -eq 0 ]; then
    echo -e "  Backend:  ${GREEN}PASSED${NC}"
else
    echo -e "  Backend:  ${RED}FAILED${NC} (exit code: $BACKEND_EXIT)"
fi

echo "============================================="

# Exit with failure if any suite failed
if [ $FRONTEND_EXIT -ne 0 ] || [ $BACKEND_EXIT -ne 0 ]; then
    exit 1
fi

echo -e "${GREEN}All tests passed!${NC}"
exit 0
