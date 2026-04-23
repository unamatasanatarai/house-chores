<?php
require_once 'config.php';

// GET /api/family.php
// Returns the existing family record (assumes a single-family deployment).
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT id, family_name, created_at FROM families LIMIT 1");
    $stmt->execute();
    $family = $stmt->fetch();

    if (!$family) {
        http_response_code(404);
        echo json_encode(["error" => "No family found. Please create one first."]);
        exit;
    }

    echo json_encode(["family" => $family]);
}

// POST /api/family.php
// Body: { "family_name": "The Smiths" }
// Creates the family. Returns 409 if one already exists.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $familyName = trim($data['family_name'] ?? '');

    $errors = [];
    if ($familyName === '') {
        $errors[] = "family_name is required.";
    } elseif (strlen($familyName) > 100) {
        $errors[] = "family_name must be 100 characters or fewer.";
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["error" => implode(" ", $errors)]);
        exit;
    }

    // Single-family model: only one family allowed.
    $check = $pdo->prepare("SELECT id FROM families LIMIT 1");
    $check->execute();
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "A family already exists."]);
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

    $stmt = $pdo->prepare("INSERT INTO families (id, family_name) VALUES (?, ?)");
    $stmt->execute([$uuid, $familyName]);

    http_response_code(201);
    echo json_encode(["status" => "success", "family_id" => $uuid]);
}
