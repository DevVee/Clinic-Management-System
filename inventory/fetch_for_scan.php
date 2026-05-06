<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Fetch For Scan] Unauthorized access");
    http_response_code(403);
    echo json_encode(['data' => null, 'error' => 'Unauthorized']);
    exit;
}

try {
    $barcode = trim(filter_input(INPUT_GET, 'barcode', FILTER_SANITIZE_STRING));
    if (empty($barcode)) {
        error_log("[SSCMS Fetch For Scan] No barcode provided");
        http_response_code(400);
        echo json_encode(['data' => null, 'error' => 'No barcode provided']);
        exit;
    }

    $query = "SELECT id, name, generic_name, quantity, purchase_price FROM medicines WHERE barcode = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$barcode]);
    $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("[SSCMS Fetch For Scan] Query: $query, Barcode: $barcode, Found: " . ($medicine ? 'Yes' : 'No'));
    echo json_encode(['data' => $medicine ?: null]);
} catch (PDOException $e) {
    error_log("[SSCMS Fetch For Scan] PDO error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['data' => null, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("[SSCMS Fetch For Scan] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['data' => null, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>