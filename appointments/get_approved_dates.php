<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    $response['message'] = 'Unauthorized access';
    error_log("[SSCMS Approved Dates] Unauthorized access: no session");
    echo json_encode($response);
    exit;
}

try {
    if (isset($_GET['calendar']) && $_GET['calendar'] === 'true') {
        // Fetch distinct dates with approved appointments
        $stmt = $conn->prepare("SELECT DISTINCT appointment_date FROM appointments WHERE status = 'approved' AND appointment_date >= CURDATE()");
        $stmt->execute();
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $response['success'] = true;
        $response['dates'] = $dates;
        error_log("[SSCMS Approved Dates] Fetched " . count($dates) . " dates with approved appointments");
    } elseif (isset($_GET['date'])) {
        // Fetch approved appointments for a specific date
        $date = htmlspecialchars(trim($_GET['date']), ENT_QUOTES, 'UTF-8');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $response['message'] = 'Invalid date format';
            echo json_encode($response);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT id, patient_name, category, phone, appointment_date, appointment_time, reason, appointee
            FROM appointments
            WHERE status = 'approved' AND appointment_date = ?
            ORDER BY appointment_time ASC
        ");
        $stmt->execute([$date]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['success'] = true;
        $response['appointments'] = $appointments;
        error_log("[SSCMS Approved Dates] Fetched " . count($appointments) . " approved appointments for $date");
    } else {
        $response['message'] = 'Invalid request';
    }
} catch (Exception $e) {
    $response['message'] = 'Database error';
    error_log("[SSCMS Approved Dates] Error: " . $e->getMessage());
}

echo json_encode($response);
?>