#!/usr/bin/env bash
# Shared test helpers — source this file, do not run directly.

BASE_URL="${BASE_URL:-http://localhost:8080/api/}"
TOKEN="${TOKEN:-YOUR_HARDCODED_TOKEN_HERE}"
DB_SERVICE="${DB_SERVICE:-db}"
DOCKER_COMPOSE="${DOCKER_COMPOSE:-docker-compose}"

PASS=0
FAIL=0

pass() {
  echo "  ✅ $1"
  PASS=$((PASS + 1))
}

fail() {
  echo "  ❌ $1"
  FAIL=$((FAIL + 1))
}

# Extract HTTP status code from a curl -i response.
get_status() {
  echo "$1" | grep -m1 "^HTTP" | awk '{print $2}'
}

# Extract the body from a curl -i response (everything after the blank line).
get_body() {
  echo "$1" | sed '1,/^\r\{0,1\}$/d'
}

# db_query <SQL>
# Run a SQL query inside the DB container and print the result.
db_query() {
  $DOCKER_COMPOSE exec -T "$DB_SERVICE" \
    mysql -u root -proot_password family_chores -se "$1" 2>/dev/null
}

# db_assert_count <label> <SQL that returns a single integer> <expected>
db_assert_count() {
  local label="$1"
  local sql="$2"
  local expected="$3"
  local actual
  actual=$(db_query "$sql")
  if [ "$actual" = "$expected" ]; then
    pass "[DB] $label (expected $expected)"
  else
    fail "[DB] $label (expected $expected, got '$actual')"
  fi
}

# Print a summary line; return 1 if any failures occurred.
summarize() {
  echo ""
  echo "  Results: $PASS passed, $FAIL failed"
  [ "$FAIL" -eq 0 ]
}
