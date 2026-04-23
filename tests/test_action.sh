#!/usr/bin/env bash
# Test suite: action.php  (claim / complete / delete)
# Expects CHORE_ID, BOB_ID, FAMILY_ID to be exported by upstream tests, or seeds its own.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib.sh"

echo "=== Action Tests ==="

# ── Setup: ensure a claimable chore exists ────────────────────────────────────
if [ -z "${FAMILY_ID:-}" ] || [ -z "${CHORE_ID:-}" ] || [ -z "${BOB_ID:-}" ]; then
  db_query "DELETE FROM chores; DELETE FROM users; DELETE FROM families;"

  body=$(curl -s -X POST "${BASE_URL}family.php" \
    -H "X-CHORES-TOKEN: $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"family_name": "Test Family"}')
  FAMILY_ID=$(echo "$body" | grep -o '"family_id":"[^"]*"' | cut -d'"' -f4)
  export FAMILY_ID

  body=$(curl -s -X POST "${BASE_URL}users.php" \
    -H "X-CHORES-TOKEN: $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"family_id\": \"$FAMILY_ID\", \"name\": \"Bob\"}")
  BOB_ID=$(echo "$body" | grep -o '"user_id":"[^"]*"' | cut -d'"' -f4)
  export BOB_ID

  curl -s -X POST "${BASE_URL}chores.php" \
    -H "X-CHORES-TOKEN: $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"family_id\":\"$FAMILY_ID\",\"title\":\"Sweep floor\",\"due_date\":\"2026-12-31 12:00:00\"}" \
    > /dev/null
  CHORE_ID=$(db_query \
    "SELECT id FROM chores WHERE title='Sweep floor' AND family_id='$FAMILY_ID' LIMIT 1;")
  export CHORE_ID
fi

# Reset chore to a clean available state
db_query "UPDATE chores SET assigned_to=NULL, status='available', deleted_at=NULL WHERE id='$CHORE_ID';"

# Create a second chore for the complete + delete tests
curl -s -X POST "${BASE_URL}chores.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"family_id\":\"$FAMILY_ID\",\"title\":\"Take out trash\",\"due_date\":\"2026-12-31 12:00:00\"}" \
  > /dev/null
CHORE2_ID=$(db_query \
  "SELECT id FROM chores WHERE title='Take out trash' AND family_id='$FAMILY_ID' LIMIT 1;")

# 1. Reject request with no token
response=$(curl -s -i -X POST "${BASE_URL}action.php" \
  -H "Content-Type: application/json" \
  -d "{\"action\":\"claim\",\"chore_id\":\"$CHORE_ID\",\"user_id\":\"$BOB_ID\"}")
status=$(get_status "$response")
if [ "$status" -eq 401 ]; then
  pass "rejects unauthenticated action"
else
  fail "expected 401, got $status"
fi

# 2. Claim a chore (first claim succeeds)
response=$(curl -s -i -X POST "${BASE_URL}action.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"action\":\"claim\",\"chore_id\":\"$CHORE_ID\",\"user_id\":\"$BOB_ID\"}")
status=$(get_status "$response")
if [ "$status" -eq 200 ]; then
  pass "can claim an available chore"
else
  fail "failed to claim chore (status $status)"
fi

# DB verify
db_assert_count "chore is now claimed by Bob" \
  "SELECT COUNT(*) FROM chores WHERE id='$CHORE_ID' AND status='claimed' AND assigned_to='$BOB_ID';" "1"

# 3. Second claim on same chore returns 409
response=$(curl -s -i -X POST "${BASE_URL}action.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"action\":\"claim\",\"chore_id\":\"$CHORE_ID\",\"user_id\":\"$BOB_ID\"}")
status=$(get_status "$response")
body=$(get_body "$response")
if [ "$status" -eq 409 ] && echo "$body" | grep -q "Someone claimed this chore"; then
  pass "returns 409 conflict on duplicate claim"
else
  fail "expected 409 conflict, got $status"
fi

# DB verify — still claimed by Bob, not double-assigned
db_assert_count "chore still has exactly one claimer" \
  "SELECT COUNT(*) FROM chores WHERE id='$CHORE_ID' AND assigned_to='$BOB_ID';" "1"

# 4. Complete the claimed chore
response=$(curl -s -i -X POST "${BASE_URL}action.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"action\":\"complete\",\"chore_id\":\"$CHORE_ID\"}")
status=$(get_status "$response")
if [ "$status" -eq 200 ]; then
  pass "can mark a chore as completed"
else
  fail "failed to complete chore (status $status)"
fi

# DB verify
db_assert_count "chore status is completed" \
  "SELECT COUNT(*) FROM chores WHERE id='$CHORE_ID' AND status='completed';" "1"

# 5. Soft-delete the second chore
response=$(curl -s -i -X POST "${BASE_URL}action.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"action\":\"delete\",\"chore_id\":\"$CHORE2_ID\"}")
status=$(get_status "$response")
if [ "$status" -eq 200 ]; then
  pass "can soft-delete a chore"
else
  fail "failed to soft-delete chore (status $status)"
fi

# DB verify — row still exists but deleted_at is set
db_assert_count "soft-deleted chore has deleted_at set" \
  "SELECT COUNT(*) FROM chores WHERE id='$CHORE2_ID' AND deleted_at IS NOT NULL;" "1"

# DB verify — deleted chore not returned by GET /chores.php
response=$(curl -s -H "X-CHORES-TOKEN: $TOKEN" "${BASE_URL}chores.php")
if echo "$response" | grep -q "Take out trash"; then
  fail "soft-deleted chore still appears in active list"
else
  pass "soft-deleted chore does not appear in active list"
fi

summarize
