<?php

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/middleware.php';

require_once __DIR__ . '/handlers.php';

// Get the requested URI and method
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Remove /api from the beginning of the URI
$uri = preg_replace('/^\/api/', '', $uri);

// Router
if ($uri === '/users/add' && $method === 'POST') {
    handleUserAdd();
} elseif ($uri === '/users' && $method === 'GET') {
    handleUserList();
} elseif (preg_match('/^\/chores/', $uri)) {
    $userId = Middleware::authenticate();
    if ($uri === '/chores' && $method === 'GET') {
        handleChoreList($userId);
    } elseif ($uri === '/chores/add' && $method === 'POST') {
        handleChoreAdd($userId);
    } else {
        handleChoreAction($uri, $method, $userId);
    }
} elseif (preg_match('/^\/logs/', $uri)) {
    $userId = Middleware::authenticate();
    handleLogList($userId);
} else {
    Response::error('404_NOT_FOUND', 'Endpoint not found', [], 404);
}
