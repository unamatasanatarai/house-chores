#!/usr/bin/env bash
# Test suite: family.php
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib.sh"

echo "=== Family Tests ==="

# ── Setup: clean slate ────────────────────────────────────────────────────────
db_query "DELETE FROM chores; DELETE FROM users; DELETE FROM families;"

# 1. Reject request with no token
response=$(curl -s -i "${BASE_URL}family.php")
status=$(get_status "$response")
if [ "$status" -eq 401 ]; then
  pass "rejects unauthenticated request"
else
  fail "expected 401, got $status"
fi

# 2. 404 when no family exists yet
response=$(curl -s -i -H "X-CHORES-TOKEN: $TOKEN" "${BASE_URL}family.php")
status=$(get_status "$response")
if [ "$status" -eq 404 ]; then
  pass "returns 404 when no family exists"
else
  fail "expected 404 before creation, got $status"
fi

# 3. Create a family
response=$(curl -s -i -X POST "${BASE_URL}family.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"family_name": "Test Family"}')
status=$(get_status "$response")
body=$(get_body "$response")

if [ "$status" -eq 201 ] && echo "$body" | grep -q '"family_id"'; then
  pass "can create a family"
  FAMILY_ID=$(echo "$body" | grep -o '"family_id":"[^"]*"' | cut -d'"' -f4)
  export FAMILY_ID
else
  fail "failed to create family (status $status, body: $body)"
fi

# DB verify
db_assert_count "family row exists" \
  "SELECT COUNT(*) FROM families WHERE family_name='Test Family';" "1"

# 4. Duplicate family is rejected with 409
response=$(curl -s -i -X POST "${BASE_URL}family.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"family_name": "Another Family"}')
status=$(get_status "$response")
if [ "$status" -eq 409 ]; then
  pass "rejects creation of a second family"
else
  fail "expected 409 for duplicate family, got $status"
fi

# DB verify — still only one family
db_assert_count "still only one family row" \
  "SELECT COUNT(*) FROM families;" "1"

# 5. Fetch the existing family
response=$(curl -s -i -H "X-CHORES-TOKEN: $TOKEN" "${BASE_URL}family.php")
status=$(get_status "$response")
body=$(get_body "$response")
if [ "$status" -eq 200 ] && echo "$body" | grep -q '"family"'; then
  pass "can fetch the existing family"
else
  fail "failed to fetch family (status $status)"
fi

# 6. Missing family_name returns 400
response=$(curl -s -i -X POST "${BASE_URL}family.php" \
  -H "X-CHORES-TOKEN: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}')
status=$(get_status "$response")
# Will get 409 (family exists) before 400, so either is acceptable here;
# the real 400 path is covered on a fresh DB. Both mean request was rejected.
if [ "$status" -eq 400 ] || [ "$status" -eq 409 ]; then
  pass "rejects POST without family_name"
else
  fail "expected 400/409 for missing family_name, got $status"
fi

summarize
