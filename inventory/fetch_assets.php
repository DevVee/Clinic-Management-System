<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    $stmt = $conn->prepare("SELECT id, name, quantity, picture, description, condition, created_at FROM assets");
    $stmt->execute();
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => $assets]);
} catch (Exception $e) {
    error_log("[SSCMS Asset Inventory] Fetch error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch assets']);
}
?>