<?php
ob_start(); // Start output buffering
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Fetch Medicines] Unauthorized access");
    http_response_code(403);
    echo json_encode(['data' => [], 'error' => 'Unauthorized']);
    ob_end_flush();
    exit;
}

try {
    $query = "SELECT id, name, generic_name, quantity, purchase_price, supplier, expiration_date, barcode, created_at 
              FROM medicines 
              WHERE is_active = 1 
              ORDER BY name ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("[SSCMS Fetch Medicines] Query: $query, Results: " . count($medicines));
    ob_clean(); // Clear buffer before JSON output
    echo json_encode(['data' => $medicines]);
} catch (PDOException $e) {
    error_log("[SSCMS Fetch Medicines] PDO error: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['data' => [], 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("[SSCMS Fetch Medicines] Error: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['data' => [], 'error' => 'Server error: ' . $e->getMessage()]);
}
ob_end_flush();
?>