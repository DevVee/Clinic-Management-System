<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    $medicine_name = isset($_GET['medicine_name']) ? trim($_GET['medicine_name']) : '';
    $query = "SELECT medicine_id, medicine_name, quantity_added, old_quantity, new_quantity, cost, user_id, created_at 
              FROM stock_audit_logs";
    $params = [];
    
    if ($medicine_name !== '') {
        $query .= " WHERE medicine_name LIKE ?";
        $params[] = "%$medicine_name%";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['data' => $logs]);
} catch (Exception $e) {
    error_log("[SSCMS Medicine Inventory] Fetch audit logs error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch audit logs']);
}
?>