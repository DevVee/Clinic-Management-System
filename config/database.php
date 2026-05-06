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
    die(json_encode(['error' => 'Database connection failed.']));
}
