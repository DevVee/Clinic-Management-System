<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    // Prepare WHERE clause for filters
    $whereClauses = [];
    $params = [];

    if (!empty($_GET['medicine_id'])) {
        $whereClauses[] = 'ml.medicine_id = :medicine_id';
        $params[':medicine_id'] = $_GET['medicine_id'];
    }
    if (!empty($_GET['year'])) {
        $whereClauses[] = 'YEAR(ml.visit_date) = :year';
        $params[':year'] = $_GET['year'];
    }
    if (!empty($_GET['month'])) {
        $whereClauses[] = 'MONTH(ml.visit_date) = :month';
        $params[':month'] = $_GET['month'];
    }
    if (!empty($_GET['date'])) {
        $whereClauses[] = 'DATE(ml.visit_date) = :date';
        $params[':date'] = $_GET['date'];
    }

    $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Query for medicine logs
    $query = "
        SELECT 
            ml.id,
            m.name AS medicine_name,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            ml.quantity_used,
            ml.visit_date,
            ml.reason
        FROM medicine_logs ml
        JOIN medicines m ON ml.medicine_id = m.id
        JOIN patients p ON ml.patient_id = p.id
        $whereSql
        ORDER BY ml.visit_date DESC
    ";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query for medicines list
    $medicineQuery = "SELECT id, name FROM medicines ORDER BY name";
    $medicineStmt = $conn->prepare($medicineQuery);
    $medicineStmt->execute();
    $medicines = $medicineStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'data' => $logs,
        'medicines' => $medicines
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
} catch (Exception $e) {
    error_log("[SSCMS Fetch Medicine Logs] Error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to fetch medicine logs']);
}
?>