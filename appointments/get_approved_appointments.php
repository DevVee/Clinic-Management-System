<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, appointment_date, appointment_time, status, patient_name
        FROM appointments
        WHERE DATE(appointment_date) = CURDATE() AND LOWER(status) = 'approved'
        ORDER BY appointment_time ASC
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Get Approved] Fetched " . count($appointments) . " approved appointments for today");
    echo json_encode(['success' => true, 'appointments' => $appointments]);
} catch (Exception $e) {
    error_log("[SSCMS Get Approved] Query error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch approved appointments']);
}
?>
