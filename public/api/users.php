<?php
require_once 'config.php';

// GET /api/users.php?family_id=<uuid>
// Returns all users belonging to the given family.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $familyId = trim($_GET['family_id'] ?? '');

    if ($familyId === '') {
        http_response_code(400);
        echo json_encode(["error" => "family_id query parameter is required."]);
        exit;
    }

    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $familyId)) {
        http_response_code(400);
        echo json_encode(["error" => "family_id must be a valid UUID."]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE family_id = ? ORDER BY name ASC");
    $stmt->execute([$familyId]);
    $users = $stmt->fetchAll();

    echo json_encode(["users" => $users]);
}

// POST /api/users.php
// Body: { "family_id": "<uuid>", "name": "Alice" }
// Adds a new member to the family.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $familyId = trim($data['family_id'] ?? '');
    $name     = trim($data['name'] ?? '');

    $errors = [];
    if ($familyId === '') {
        $errors[] = "family_id is required.";
    } elseif (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $familyId)) {
        $errors[] = "family_id must be a valid UUID.";
    }

    if ($name === '') {
        $errors[] = "name is required.";
    } elseif (strlen($name) > 50) {
        $errors[] = "name must be 50 characters or fewer.";
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["error" => implode(" ", $errors)]);
        exit;
    }

    // Verify the family exists.
    $familyCheck = $pdo->prepare("SELECT id FROM families WHERE id = ?");
    $familyCheck->execute([$familyId]);
    if (!$familyCheck->fetch()) {
        http_response_code(404);
        echo json_encode(["error" => "Family not found."]);
        exit;
    }

    // Prevent duplicate names within the same family.
    $check = $pdo->prepare("SELECT id FROM users WHERE family_id = ? AND name = ?");
    $check->execute([$familyId, $name]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "A user with that name already exists in this family."]);
        exit;
    }

    $id = bin2hex(random_bytes(16));
    $uuid = sprintf(
        '%s-%s-%s-%s-%s',
        substr($id, 0, 8),
        substr($id, 8, 4),
        substr($id, 12, 4),
        substr($id, 16, 4),
        substr($id, 20)
    );

    $stmt = $pdo->prepare("INSERT INTO users (id, family_id, name) VALUES (?, ?, ?)");
    $stmt->execute([$uuid, $familyId, $name]);

    http_response_code(201);
    echo json_encode(["status" => "success", "user_id" => $uuid]);
}

// DELETE /api/users.php
// Body: { "user_id": "<uuid>" }
// Removes a user. Blocked if the user has claimed (in-progress) chores.
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);

    $userId = trim($data['user_id'] ?? '');

    if ($userId === '') {
        http_response_code(400);
        echo json_encode(["error" => "user_id is required."]);
        exit;
    }

    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $userId)) {
        http_response_code(400);
        echo json_encode(["error" => "user_id must be a valid UUID."]);
        exit;
    }

    // Verify the user exists.
    $exists = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $exists->execute([$userId]);
    if (!$exists->fetch()) {
        http_response_code(404);
        echo json_encode(["error" => "User not found."]);
        exit;
    }

    // Safety: block deletion if user has active (claimed) chores.
    $check = $pdo->prepare(
        "SELECT id FROM chores WHERE assigned_to = ? AND status = 'claimed' AND deleted_at IS NULL"
    );
    $check->execute([$userId]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "Cannot remove a user who still has claimed chores."]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    echo json_encode(["status" => "success"]);
}
