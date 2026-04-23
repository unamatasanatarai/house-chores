#!/usr/bin/env bash
# Test suite: users.php
# Expects FAMILY_ID to be set (exported by test_family.sh), or creates its own.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib.sh"

echo "=== User Tests ==="

# ── Setup: ensure a family exists ─────────────────────────────────────────────
if [ -z "${FAMILY_ID:-}" ]; then
  db_query "DELETE FROM chores; DELETE FROM users; DELETE FROM families;"
  body=$(curl -s -X POST "${BASE_URL}family.php" \
    -H "X-CHORES-TOKEN: $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"family_name": "Test Family"}')
  FAMILY_ID=$(echo "$body" | grep -o '"family_id":"[^"]*"' | cut -d'"' -f4)
  export FAMILY_ID
fi
db_query "DELETE FROM chores; DELETE FROM users WHERE family_id='$FAMILY_ID';"

# 1. Reject request with no token
response=$(curl -s -i "${BASE_URL}users.php?family_id=$FAMILY_ID")
status=$(get_status "$response")
if [ "$status" -eq 401 ]; then
  pass "rejects unauthenticated request"
else
  fail "expected 401, got $status"
fi

# 2. GET with missing family_id returns 400
response=$(curl -s -i -H "X-CHORES-TOKEN: $TOKEN" "${BASE_URL}users.php")
status=$(get_status "$response")
if [ "$status" -eq 400 ]; then
  pass "GET without family_id returns 400"
else
  fail "expected 400 for missing family_id, got $status"
fi

# 3. Add a user
response=$(curl -s -i -X POST "${BASE_URL}users.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"family_id\": \"$FAMILY_ID\", \"name\": \"Alice\"}")
status=$(get_status "$response")
body=$(get_body "$response")

if [ "$status" -eq 201 ] && echo "$body" | grep -q '"user_id"'; then
  pass "can add a user to the family"
  USER_ID=$(echo "$body" | grep -o '"user_id":"[^"]*"' | cut -d'"' -f4)
  export USER_ID
else
  fail "failed to add user (status $status, body: $body)"
fi

# DB verify
db_assert_count "Alice row exists" \
  "SELECT COUNT(*) FROM users WHERE name='Alice' AND family_id='$FAMILY_ID';" "1"

# 4. Duplicate name is rejected
response=$(curl -s -i -X POST "${BASE_URL}users.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"family_id\": \"$FAMILY_ID\", \"name\": \"Alice\"}")
status=$(get_status "$response")
if [ "$status" -eq 409 ]; then
  pass "rejects duplicate user name within the same family"
else
  fail "expected 409 for duplicate user, got $status"
fi

# DB verify — still only one Alice
db_assert_count "still only one Alice" \
  "SELECT COUNT(*) FROM users WHERE name='Alice';" "1"

# 5. Add a second user (needed for chore/action tests)
body=$(curl -s -X POST "${BASE_URL}users.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"family_id\": \"$FAMILY_ID\", \"name\": \"Bob\"}")
BOB_ID=$(echo "$body" | grep -o '"user_id":"[^"]*"' | cut -d'"' -f4)
export BOB_ID

db_assert_count "Bob row exists" \
  "SELECT COUNT(*) FROM users WHERE name='Bob' AND family_id='$FAMILY_ID';" "1"

# 6. List users
response=$(curl -s -i -H "X-CHORES-TOKEN: $TOKEN" \
  "${BASE_URL}users.php?family_id=$FAMILY_ID")
status=$(get_status "$response")
body=$(get_body "$response")
if [ "$status" -eq 200 ] && echo "$body" | grep -q '"users"'; then
  pass "can list users in a family"
else
  fail "failed to list users (status $status)"
fi

# 7. POST without name returns 400
response=$(curl -s -i -X POST "${BASE_URL}users.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"family_id\": \"$FAMILY_ID\"}")
status=$(get_status "$response")
if [ "$status" -eq 400 ]; then
  pass "POST without name returns 400"
else
  fail "expected 400 for missing name, got $status"
fi

# 8. Delete a user (Alice — not Bob; Bob is needed by downstream tests)
response=$(curl -s -i -X DELETE "${BASE_URL}users.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"user_id\": \"$USER_ID\"}")
status=$(get_status "$response")
if [ "$status" -eq 200 ]; then
  pass "can delete a user"
else
  fail "failed to delete user (status $status)"
fi

# DB verify — Alice is gone
db_assert_count "Alice row removed" \
  "SELECT COUNT(*) FROM users WHERE id='$USER_ID';" "0"

summarize
