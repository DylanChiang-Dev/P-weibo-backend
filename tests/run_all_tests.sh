#!/bin/bash
# Run all tests for the P-Weibo Backend

set -e  # Exit on error

echo "================================"
echo "P-Weibo Backend Test Suite"
echo "================================"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

run_test() {
    local test_name=$1
    local test_file=$2
    
    echo -e "${BLUE}Running: $test_name${NC}"
    echo "----------------------------------------"
    
    if php "$test_file"; then
        echo -e "${GREEN}✓ $test_name passed${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}✗ $test_name failed${NC}"
        ((TESTS_FAILED++))
        return 1
    fi
    
    echo ""
}

# Run all tests
run_test "Exception Handling Tests" "tests/test_exceptions.php" || true
run_test "Enhanced QueryBuilder Tests" "tests/test_querybuilder_enhanced.php" || true
run_test "Middleware Tests" "tests/test_middleware.php" || true
run_test "QueryBuilder Tests" "tests/test_query_builder.php" || true
run_test "Service Integration Tests" "tests/test_services.php" || true
run_test "Token Tests" "tests/test_token.php" || true

# Summary
echo "================================"
echo "Test Summary"
echo "================================"
echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Failed: $TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed! ✓${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed! ✗${NC}"
    exit 1
fi
