<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Update Appointment] Unauthorized access: no session");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("[SSCMS Update Appointment] Invalid CSRF token");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Send SMS function (unchanged from your version)
function sendSMS($number, $message) {
    $apiKey = '2840c5de6cdfbe118d100ad33fdc179b';
    $senderName = 'ICCBICLINIC';

    $url = 'https://api.semaphore.co/api/v4/messages';
    $data = [
        'apikey' => $apiKey,
        'number' => $number,
        'message' => $message,
        'sendername' => $senderName
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        error_log("[SSCMS SMS Error] cURL Error: $error");
        $result = "cURL Error: $error";
    } else {
        error_log("[SSCMS SMS Response] Response: $response");
        $result = "SMS Response: $response";
    }

    curl_close($ch);
    return $result;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS);

        if (!$id || !in_array($status, ['approved', 'rejected'])) {
            error_log("[SSCMS Update Appointment] Invalid input: id=$id, status=$status");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid appointment ID or status']);
            exit;
        }

        // Begin transaction
        $conn->beginTransaction();

        // Fetch appointment details
        $stmt = $conn->prepare("SELECT patient_name, phone, appointment_date, appointment_time FROM appointments WHERE id = ?");
        $stmt->execute([$id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            error_log("[SSCMS Update Appointment] Appointment not found: id=$id");
            $conn->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit;
        }

        // Update appointment status (no duplicate time slot check)
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $affected_rows = $stmt->rowCount();

        if ($affected_rows === 0) {
            error_log("[SSCMS Update Appointment] No rows updated: id=$id, status=$status");
            $conn->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update appointment']);
            exit;
        }

        // Prepare SMS
        $patient_name = $appointment['patient_name'];
        $phone = $appointment['phone'];
        $appointment_date = date('F j, Y', strtotime($appointment['appointment_date'])); // e.g., September 2, 2025
        $appointment_time = date('h:i A', strtotime($appointment['appointment_time'])); // e.g., 10:00 AM

        if (!empty($phone)) {
            if ($status === 'approved') {
                $sms_message = "ICCBICLINIC\nHello $patient_name,\nYour appointment has been approved.\nDate: $appointment_date\nTime: $appointment_time\nPlease arrive at least 10 minutes early.\nThank you and see you!";
            } else { // rejected
                $sms_message = "ICCBICLINIC\nHello $patient_name,\nWe’re sorry, your appointment request has been rejected.\nDate: $appointment_date\nTime: $appointment_time\nTo reschedule, please click this link:\n/appointments/new-appointment.php\nThank you for understanding.\nICCBICLINIC";
            }

            // Send SMS
            $sms_response = sendSMS($phone, $sms_message);
            error_log("[SSCMS Update Appointment] SMS sent to $phone: $sms_message");
            error_log("[SSCMS Update Appointment] SMS response: $sms_response");
        } else {
            error_log("[SSCMS Update Appointment] Warning: Phone number empty for appointment_id=$id");
        }

        // Commit transaction
        $conn->commit();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => "Appointment $status successfully"]);
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("[SSCMS Update Appointment] Error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    error_log("[SSCMS Update Appointment] Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>