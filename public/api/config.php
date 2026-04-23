<?php
// Configuration & Security
define('DB_HOST', 'db');
define('DB_NAME', 'family_chores');
define('DB_USER', 'root');
define('DB_PASS', 'root_password');
define('API_TOKEN', 'YOUR_HARDCODED_TOKEN_HERE');

if (php_sapi_name() !== 'cli') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: X-CHORES-TOKEN, Content-Type");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }

    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $providedToken = $headers['X-CHORES-TOKEN'] ?? '';

    if ($providedToken !== API_TOKEN) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    if (php_sapi_name() === 'cli') {
        die("Database Connection Failed: " . $e->getMessage() . "\n");
    }
    http_response_code(500);
    echo json_encode([
                "error" => "Database Connection Failed",
                        "details" => $e->getMessage() 
                            ]);
    exit;
}
