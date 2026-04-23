#!/usr/bin/env bash
# Run all test suites in order, sharing context via exported variables.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

export BASE_URL="${BASE_URL:-http://localhost:8080/api/}"
export TOKEN="${TOKEN:-YOUR_HARDCODED_TOKEN_HERE}"
export DB_SERVICE="${DB_SERVICE:-db}"
export DOCKER_COMPOSE="${DOCKER_COMPOSE:-docker-compose}"

TOTAL_PASS=0
TOTAL_FAIL=0

run_suite() {
  local script="$1"
  # Run each suite in a subshell; capture its last two output lines for totals.
  output=$(bash "$SCRIPT_DIR/$script")
  echo "$output"
  # Parse "Results: N passed, M failed"
  local p f
  p=$(echo "$output" | grep -o '[0-9]* passed' | grep -o '[0-9]*' || echo 0)
  f=$(echo "$output" | grep -o '[0-9]* failed' | grep -o '[0-9]*' || echo 0)
  TOTAL_PASS=$((TOTAL_PASS + p))
  TOTAL_FAIL=$((TOTAL_FAIL + f))
}

echo "╔══════════════════════════════════════╗"
echo "║        Chores API Test Runner        ║"
echo "╚══════════════════════════════════════╝"
echo ""

# Suites run in dependency order: family → users → chores → actions.
# Each suite self-seeds if FAMILY_ID / BOB_ID / CHORE_ID are not yet set.
run_suite test_family.sh
echo ""
run_suite test_users.sh
echo ""
run_suite test_chores.sh
echo ""
run_suite test_action.sh

echo ""
echo "╔══════════════════════════════════════╗"
printf  "║  Total: %-3s passed  %-3s failed       ║\n" "$TOTAL_PASS" "$TOTAL_FAIL"
echo "╚══════════════════════════════════════╝"

if [ "$TOTAL_FAIL" -gt 0 ]; then
  exit 1
fi

echo "🎉 All tests passed!"