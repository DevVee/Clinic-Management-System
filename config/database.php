<?php
$host     = getenv('DB_HOST')     ?: 'localhost';
$dbname   = getenv('DB_NAME')     ?: 'postgres';
$username = getenv('DB_USER')     ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';
$port     = getenv('DB_PORT')     ?: '5432';

try {
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log("DB connection failed: " . $e->getMessage());
    // Pages that need DB will handle $conn being null
    $conn = null;

    // Only output JSON error for API/AJAX calls
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
        strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(503);
        die(json_encode(['error' => 'Database connection failed.']));
    }
}
