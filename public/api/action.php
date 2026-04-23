<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $action  = $data['action']  ?? null;
    $choreId = $data['chore_id'] ?? null;
    $userId  = $data['user_id']  ?? null;

    $allowedActions = ['claim', 'complete', 'delete'];

    $errors = [];
    if (empty($action)) {
        $errors[] = "action is required.";
    } elseif (!in_array($action, $allowedActions, true)) {
        $errors[] = "action must be one of: " . implode(', ', $allowedActions) . ".";
    }

    if (empty($choreId)) {
        $errors[] = "chore_id is required.";
    }

    if ($action === 'claim' && empty($userId)) {
        $errors[] = "user_id is required for the claim action.";
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["error" => implode(" ", $errors)]);
        exit;
    }

    if ($action === 'claim') {
        $check = $pdo->prepare("SELECT assigned_to FROM chores WHERE id = ?");
        $check->execute([$choreId]);
        $current = $check->fetch();

        if (!$current) {
            http_response_code(404);
            echo json_encode(["error" => "Chore not found."]);
            exit;
        }

        if ($current['assigned_to'] !== null) {
            http_response_code(409);
            echo json_encode(["error" => "Oops! Someone claimed this chore just before you."]);
            exit;
        }

        $update = $pdo->prepare("UPDATE chores SET assigned_to = ?, status = 'claimed' WHERE id = ?");
        $update->execute([$userId, $choreId]);
    }

    if ($action === 'complete') {
        $update = $pdo->prepare("UPDATE chores SET status = 'completed' WHERE id = ?");
        $update->execute([$choreId]);
    }

    if ($action === 'delete') {
        $update = $pdo->prepare("UPDATE chores SET deleted_at = NOW() WHERE id = ?");
        $update->execute([$choreId]);
    }

    echo json_encode(["status" => "success"]);
}