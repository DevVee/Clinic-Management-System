<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$date      = htmlspecialchars(trim($_GET['date']      ?? ''), ENT_QUOTES, 'UTF-8');
$appointee = htmlspecialchars(trim($_GET['appointee'] ?? ''), ENT_QUOTES, 'UTF-8');

try {
    // Fetch available dates for calendar
    if (isset($_GET['calendar'])) {
        $stmt = $conn->prepare("
            SELECT DISTINCT appointment_date
            FROM appointments
            WHERE status = 'approved' AND patient_name IS NULL AND appointment_date >= CURDATE()
            ORDER BY appointment_date
        ");
        $stmt->execute();
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'dates' => $dates]);
        exit;
    }

    // Fetch slots for selected date
    $query = "
        SELECT id, appointment_date, appointment_time, end_time, appointee, patient_name
        FROM appointments
        WHERE status = 'approved' AND appointment_date = ?
    ";
    $params = [$date];
    if ($appointee && in_array($appointee, ['Doctor', 'Nurse', 'Dentist'])) {
        $query .= " AND appointee = ?";
        $params[] = $appointee;
    }
    $query .= " ORDER BY CASE WHEN patient_name IS NULL THEN 0 ELSE 1 END, appointment_time ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Get Slots] Fetched " . count($slots) . " slots for $date");

    echo json_encode(['success' => true, 'slots' => $slots]);
} catch (Exception $e) {
    error_log("[SSCMS Get Slots] Query error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch slots']);
}
?>