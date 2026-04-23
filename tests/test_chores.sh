#!/usr/bin/env bash
# Test suite: chores.php
# Expects FAMILY_ID and BOB_ID to be set (exported by upstream tests), or seeds its own.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib.sh"

echo "=== Chores Tests ==="

# ── Setup: ensure family + user exist ─────────────────────────────────────────
if [ -z "${FAMILY_ID:-}" ]; then
  db_query "DELETE FROM chores; DELETE FROM users; DELETE FROM families;"
  body=$(curl -s -X POST "${BASE_URL}family.php" \
    -H "X-CHORES-TOKEN: $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"family_name": "Test Family"}')
  FAMILY_ID=$(echo "$body" | grep -o '"family_id":"[^"]*"' | cut -d'"' -f4)
  export FAMILY_ID
fi

if [ -z "${BOB_ID:-}" ]; then
  db_query "DELETE FROM chores WHERE family_id='$FAMILY_ID';"
  body=$(curl -s -X POST "${BASE_URL}users.php" \
    -H "X-CHORES-TOKEN: $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"family_id\": \"$FAMILY_ID\", \"name\": \"Bob\"}")
  BOB_ID=$(echo "$body" | grep -o '"user_id":"[^"]*"' | cut -d'"' -f4)
  export BOB_ID
fi

db_query "DELETE FROM chores WHERE family_id='$FAMILY_ID';"

# 1. Reject request with no token
response=$(curl -s -i "${BASE_URL}chores.php")
status=$(get_status "$response")
if [ "$status" -eq 401 ]; then
  pass "rejects unauthenticated request"
else
  fail "expected 401, got $status"
fi

# 2. Fetch empty chores list
response=$(curl -s -i -H "X-CHORES-TOKEN: $TOKEN" "${BASE_URL}chores.php")
status=$(get_status "$response")
body=$(get_body "$response")
if [ "$status" -eq 200 ] && echo "$body" | grep -q '"chores"'; then
  pass "can fetch (empty) chores list"
else
  fail "failed to fetch chores list (status $status)"
fi

# 3. Create a chore
response=$(curl -s -i -X POST "${BASE_URL}chores.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"family_id\": \"$FAMILY_ID\",
    \"title\": \"Wash dishes\",
    \"description\": \"All of them\",
    \"due_date\": \"2026-12-31 12:00:00\"
  }")
status=$(get_status "$response")
body=$(get_body "$response")
if [ "$status" -eq 200 ]; then
  pass "can create a chore"
else
  fail "failed to create chore (status $status, body: $body)"
fi

# DB verify
db_assert_count "chore row exists" \
  "SELECT COUNT(*) FROM chores WHERE title='Wash dishes' AND family_id='$FAMILY_ID';" "1"

# 4. Chore appears in list
response=$(curl -s -i -H "X-CHORES-TOKEN: $TOKEN" "${BASE_URL}chores.php")
body=$(get_body "$response")
if echo "$body" | grep -q "Wash dishes"; then
  pass "created chore appears in list"
else
  fail "created chore not found in list"
fi

# Export CHORE_ID for action tests
CHORE_ID=$(db_query \
  "SELECT id FROM chores WHERE title='Wash dishes' AND family_id='$FAMILY_ID' LIMIT 1;")
export CHORE_ID

# 5. Chore with missing title returns 400 (validated before DB)
response=$(curl -s -i -X POST "${BASE_URL}chores.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"family_id\": \"$FAMILY_ID\", \"due_date\": \"2026-12-31 12:00:00\"}")
status=$(get_status "$response")
if [ "$status" -eq 400 ]; then
  pass "returns 400 when title is missing"
else
  fail "expected 400 for missing title, got $status"
fi

# 6. Chore with a bad due_date format returns 400
response=$(curl -s -i -X POST "${BASE_URL}chores.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"family_id\": \"$FAMILY_ID\", \"title\": \"Bad date chore\", \"due_date\": \"not-a-date\"}")
status=$(get_status "$response")
if [ "$status" -eq 400 ]; then
  pass "returns 400 for invalid due_date format"
else
  fail "expected 400 for bad due_date, got $status"
fi

summarize
