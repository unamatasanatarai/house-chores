<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Response.php';

class Middleware {
    public static function authenticate() {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            Response::error('401_UNAUTHORIZED', 'Missing or invalid Authorization header', [], 401);
        }

        $token = $matches[1];

        // Validate UUID format
        if (!preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){3}-[a-f\d]{12}$/i', $token)) {
            Response::error('401_UNAUTHORIZED', 'Invalid token format', [], 401);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('401_UNAUTHORIZED', 'User not found', [], 401);
        }

        // Return the authenticated user ID
        return $user['id'];
    }
}
