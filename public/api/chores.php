<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT id, title, description, status, assigned_to, due_date FROM chores WHERE deleted_at IS NULL ORDER BY due_date ASC");
    $stmt->execute();
    $chores = $stmt->fetchAll();

    echo json_encode(["chores" => $chores]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $errors = [];

    if (empty($data['family_id'])) {
        $errors[] = "family_id is required.";
    }

    if (empty($data['title'])) {
        $errors[] = "title is required.";
    }

    if (empty($data['due_date'])) {
        $errors[] = "due_date is required.";
    } elseif (!strtotime($data['due_date']) || DateTime::createFromFormat('Y-m-d H:i:s', $data['due_date']) === false) {
        $errors[] = "due_date must be a valid datetime in format YYYY-MM-DD HH:MM:SS.";
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["error" => implode(" ", $errors)]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO chores (id, family_id, title, description, status, due_date) VALUES (UUID(), ?, ?, ?, 'available', ?)");
    $stmt->execute([$data['family_id'], $data['title'], $data['description'] ?? '', $data['due_date']]);

    echo json_encode(["status" => "success"]);
}