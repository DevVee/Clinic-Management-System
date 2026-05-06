<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Medicine Inventory] Unauthorized access");
    http_response_code(403);
    echo json_encode(['data' => [], 'error' => 'Unauthorized']);
    exit;
}

try {
    $medicine_id = isset($_GET['medicine_id']) && is_numeric($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : null;
    $year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : null;
    $month = isset($_GET['month']) && is_numeric($_GET['month']) ? (int)$_GET['month'] : null;
    $date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : null;

    // Fetch logs with expiration_date
    $query = "SELECT sal.medicine_id, sal.medicine_name, sal.quantity_added, sal.old_quantity, 
                     sal.new_quantity, sal.cost, sal.expiration_date, sal.user_id, sal.created_at, 
                     COALESCE(u.name, '-') AS user_name
              FROM stock_audit_logs sal
              LEFT JOIN users u ON sal.user_id = u.id";
    $params = [];
    $conditions = [];

    if ($medicine_id !== null) {
        $conditions[] = "sal.medicine_id = ?";
        $params[] = $medicine_id;
    }

    if ($year !== null) {
        $conditions[] = "YEAR(sal.created_at) = ?";
        $params[] = $year;
    }

    if ($month !== null) {
        $conditions[] = "MONTH(sal.created_at) = ?";
        $params[] = $month;
    }

    if ($date !== null) {
        $conditions[] = "DATE(sal.created_at) = ?";
        $params[] = $date;
    }

    if ($conditions) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY sal.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total cost
    $total_cost = 0;
    foreach ($logs as $log) {
        $total_cost += $log['cost'];
    }

    // Fetch medicines for dropdown
    $medicine_query = "SELECT id, name FROM medicines ORDER BY name ASC";
    $medicine_stmt = $conn->prepare($medicine_query);
    $medicine_stmt->execute();
    $medicines = $medicine_stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("[SSCMS Medicine Inventory] Query: $query, Params: " . json_encode($params) . ", Results: " . count($logs));
    echo json_encode([
        'data' => $logs,
        'total_cost' => $total_cost,
        'medicines' => $medicines
    ]);
} catch (PDOException $e) {
    error_log("[SSCMS Medicine Inventory] PDO error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['data' => [], 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("[SSCMS Medicine Inventory] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['data' => [], 'error' => 'Server error: ' . $e->getMessage()]);
}
?>