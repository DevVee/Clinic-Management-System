<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    // Sanitize and validate inputs
    $student = filter_input(INPUT_GET, 'student', FILTER_SANITIZE_STRING) ?? '';
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?? '';
    $from_date = filter_input(INPUT_GET, 'from_date', FILTER_SANITIZE_STRING) ?? '';
    $to_date = filter_input(INPUT_GET, 'to_date', FILTER_SANITIZE_STRING) ?? '';
    $time = filter_input(INPUT_GET, 'time', FILTER_SANITIZE_STRING) ?? '';
    $month = filter_input(INPUT_GET, 'month', FILTER_SANITIZE_STRING) ?? '';
    $severity = filter_input(INPUT_GET, 'severity', FILTER_SANITIZE_STRING) ?? '';

    // Validate inputs
    if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Invalid date format');
    }
    if ($from_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
        throw new Exception('Invalid from date format');
    }
    if ($to_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
        throw new Exception('Invalid to date format');
    }
    if ($time && !preg_match('/^\d{2}:00$/', $time)) {
        throw new Exception('Invalid time format');
    }
    if ($month && !preg_match('/^\d{1,2}$/', $month)) {
        throw new Exception('Invalid month format');
    }
    if ($severity && !in_array($severity, ['Mild', 'Moderate', 'Severe'])) {
        throw new Exception("Invalid severity level: $severity");
    }

    // Build query
    $query = "
        SELECT 
            p.last_name,
            p.first_name,
            p.category,
            COALESCE(v.other_reason, v.reason) AS reason,
            v.severity,
            v.visit_date,
            TIME_FORMAT(v.visit_time, '%h:%i %p') AS visit_time,
            COALESCE(m.name, v.medicine_name) AS medicine_name,
            v.medicine_quantity
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN medicines m ON v.medicine_id = m.id
        WHERE 1=1
    ";
    $params = [];

    if ($student) {
        $query .= " AND (p.last_name LIKE ? OR p.first_name LIKE ? OR p.middle_name LIKE ?)";
        $params[] = "%$student%";
        $params[] = "%$student%";
        $params[] = "%$student%";
    }

    if ($severity) {
        $query .= " AND v.severity = ?";
        $params[] = $severity;
    }

    if ($time) {
        $query .= " AND HOUR(v.visit_time) = ?";
        $params[] = (int)substr($time, 0, 2);
    }

    if ($month) {
        $year = $date ? date('Y', strtotime($date)) : date('Y');
        $query .= " AND MONTH(v.visit_date) = ? AND YEAR(v.visit_date) = ?";
        $params[] = $month;
        $params[] = $year;
    } elseif ($from_date && $to_date) {
        $query .= " AND v.visit_date BETWEEN ? AND ?";
        $params[] = $from_date;
        $params[] = $to_date;
    } elseif ($date) {
        $query .= " AND v.visit_date = ?";
        $params[] = $date;
    }

    $query .= " ORDER BY v.visit_date DESC, v.visit_time DESC";

    // Prepare and execute query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }

    if ($params) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log query and results for debugging
    error_log("Fetch reports query: $query");
    error_log("Parameters: " . json_encode($params));
    error_log("Results count: " . count($reports));
    error_log("Sample data: " . json_encode(array_slice($reports, 0, 3)));

    echo json_encode(['data' => $reports]);
} catch (Exception $e) {
    error_log("Fetch reports error: " . $e->getMessage());
    echo json_encode(['data' => [], 'error' => 'Database error: ' . $e->getMessage()]);
}
?>