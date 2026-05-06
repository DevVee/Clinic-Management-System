<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    $response['message'] = 'Unauthorized access';
    error_log("[SSCMS Appointment] Unauthorized access: no session");
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $response['message'] = 'Invalid CSRF token';
    error_log("[SSCMS Appointment] Invalid CSRF token");
    echo json_encode($response);
    exit;
}

$action = htmlspecialchars(trim($_POST['action'] ?? ''), ENT_QUOTES, 'UTF-8');
$id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$id || !in_array($action, ['edit', 'delete', 'approve', 'reject', 'close'])) {
    $response['message'] = 'Invalid request';
    echo json_encode($response);
    exit;
}

try {
    // Check current appointment status
    $stmt = $conn->prepare("SELECT status FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $response['message'] = 'Appointment not found';
        echo json_encode($response);
        exit;
    }

    $current_status = $appointment['status'];

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            error_log("[SSCMS Appointment] Deleted appointment ID $id");
        } else {
            $response['message'] = 'Appointment not found';
        }
        echo json_encode($response);
        exit;
    }

    if ($action === 'approve') {
        if ($current_status !== 'pending') {
            $response['message'] = 'Only pending appointments can be approved';
            echo json_encode($response);
            exit;
        }
        $stmt = $conn->prepare("UPDATE appointments SET status = 'approved' WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            error_log("[SSCMS Appointment] Approved appointment ID $id");
        } else {
            $response['message'] = 'No changes made';
        }
        echo json_encode($response);
        exit;
    }

    if ($action === 'reject') {
        if ($current_status !== 'pending') {
            $response['message'] = 'Only pending appointments can be rejected';
            echo json_encode($response);
            exit;
        }
        $stmt = $conn->prepare("UPDATE appointments SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            error_log("[SSCMS Appointment] Rejected appointment ID $id");
        } else {
            $response['message'] = 'No changes made';
        }
        echo json_encode($response);
        exit;
    }

    if ($action === 'close') {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'closed' WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            error_log("[SSCMS Appointment] Closed appointment ID $id");
        } else {
            $response['message'] = 'No changes made';
        }
        echo json_encode($response);
        exit;
    }

    if ($action === 'edit') {
        if ($current_status === 'rejected' || $current_status === 'closed') {
            $response['message'] = 'Cannot edit rejected or closed appointments';
            echo json_encode($response);
            exit;
        }

        $required = ['patient_name', 'category', 'appointee', 'appointment_date', 'appointment_time', 'end_time', 'reason', 'status'];
        foreach ($required as $field) {
            if (!isset($_POST[$field])) {
                $response['message'] = 'Missing required fields';
                echo json_encode($response);
                exit;
            }
        }

        $patient_name = filter_var($_POST['patient_name'], FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        $category = filter_var($_POST['category'], FILTER_SANITIZE_SPECIAL_CHARS);
        $phone = filter_var($_POST['phone'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        $appointee = filter_var($_POST['appointee'], FILTER_SANITIZE_SPECIAL_CHARS);
        $appointment_date = filter_var($_POST['appointment_date'], FILTER_SANITIZE_SPECIAL_CHARS);
        $appointment_time = filter_var($_POST['appointment_time'], FILTER_SANITIZE_SPECIAL_CHARS);
        $end_time = filter_var($_POST['end_time'], FILTER_SANITIZE_SPECIAL_CHARS);
        $reason = filter_var($_POST['reason'], FILTER_SANITIZE_SPECIAL_CHARS);
        $status = filter_var($_POST['status'], FILTER_SANITIZE_SPECIAL_CHARS);

        // Validate inputs
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
            $response['message'] = 'Invalid date format';
            echo json_encode($response);
            exit;
        }

        $valid_times = [];
        for ($h = 7; $h <= 16; $h++) {
            $valid_times[] = sprintf("%02d:00:00", $h);
            $valid_times[] = sprintf("%02d:30:00", $h);
        }
        if (!in_array($appointment_time, $valid_times) || !in_array($end_time, $valid_times)) {
            $response['message'] = 'Invalid time';
            echo json_encode($response);
            exit;
        }

        if (strtotime($end_time) <= strtotime($appointment_time)) {
            $response['message'] = 'End time must be after start time';
            echo json_encode($response);
            exit;
        }

        $valid_appointees = ['Doctor', 'Nurse', 'Dentist'];
        if (!in_array($appointee, $valid_appointees)) {
            $response['message'] = 'Invalid appointee';
            echo json_encode($response);
            exit;
        }

        $valid_categories = [
            'Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Graduate School', 'Alumni',
            'Faculty', 'Faculty Staff', 'Teacher',
            'Administrative Staff', 'Clinic Staff', 'Support Staff', 'Non-Teaching', 'Non-Teaching Staff',
            'Maintenance Staff', 'Janitorial Staff', 'Security Staff',
            'Non-Student', 'Staff', 'Visitor', 'Other',
        ];
        if ($category && !in_array($category, $valid_categories)) {
            $response['message'] = 'Invalid category';
            echo json_encode($response);
            exit;
        }

        if ($phone && !preg_match('/^\d{10,11}$/', $phone)) {
            $response['message'] = 'Phone number must be 10 or 11 digits';
            echo json_encode($response);
            exit;
        }

        $valid_statuses = ['pending', 'approved'];
        if (!in_array($status, $valid_statuses)) {
            $response['message'] = 'Invalid status for edit';
            echo json_encode($response);
            exit;
        }

        // Check for overlapping slots (excluding current appointment)
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM appointments
            WHERE appointment_date = ? AND appointee = ? AND id != ?
            AND (
                (appointment_time <= ? AND end_time >= ?) OR
                (appointment_time <= ? AND end_time >= ?)
            )
        ");
        $stmt->execute([$appointment_date, $appointee, $id, $appointment_time, $appointment_time, $end_time, $end_time]);
        if ($stmt->fetchColumn() > 0) {
            $response['message'] = 'Time slot overlaps with existing appointment';
            echo json_encode($response);
            exit;
        }

        // Update appointment
        $stmt = $conn->prepare("
            UPDATE appointments
            SET patient_name = ?, category = ?, phone = ?, appointment_date = ?, appointment_time = ?, end_time = ?, reason = ?, appointee = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$patient_name, $category, $phone, $appointment_date, $appointment_time, $end_time, $reason, $appointee, $status, $id]);

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            error_log("[SSCMS Appointment] Updated appointment ID $id");
        } else {
            $response['message'] = 'No changes made or appointment not found';
        }
        echo json_encode($response);
        exit;
    }
} catch (Exception $e) {
    $response['message'] = 'Database error';
    error_log("[SSCMS Appointment] Error: " . $e->getMessage());
    echo json_encode($response);
    exit;
}
?>