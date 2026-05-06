<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Update session last_active
try {
    $stmt = $conn->prepare("UPDATE sessions SET last_active = NOW() WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$_SESSION['user_id'], session_id()]);
} catch (Exception $e) {
    error_log("[SSCMS Dashboard] Session update error: " . $e->getMessage());
}

// SMS function
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
        return ['success' => false, 'message' => "cURL Error: $error"];
    }

    error_log("[SSCMS SMS Response] Response: $response");
    curl_close($ch);
    return ['success' => true, 'message' => $response];
}

// Handle discharge request (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'discharge') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

        $visit_id = filter_input(INPUT_POST, 'visit_id', FILTER_VALIDATE_INT);
        if (!$visit_id) {
            throw new Exception('Invalid visit ID');
        }

        $conn->beginTransaction();

        // Get visit and patient details
        $stmt = $conn->prepare("
            SELECT v.patient_id, p.first_name, p.last_name, p.guardian_contact
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            WHERE v.id = ? AND v.discharge_time IS NULL
        ");
        $stmt->execute([$visit_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$visit) {
            throw new Exception('Visit not found or already discharged');
        }

        // Set discharge time in Philippine timezone
        $discharge_time = date('H:i:s');
        $discharge_time_display = date('h:i A');
        $discharge_date = date('F j, Y');

        // Update discharge_time
        $stmt = $conn->prepare("UPDATE visits SET discharge_time = ? WHERE id = ?");
        $stmt->execute([$discharge_time, $visit_id]);
        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to update discharge time');
        }

        // Send SMS to guardian
        $guardian_number = $visit['guardian_contact'];
        $full_name = $visit['first_name'] . ' ' . $visit['last_name'];
        $sms_message = "Good day! This is ICCBI CLINIC. We would like to inform you that your child, $full_name, was discharged from the school clinic today at $discharge_time_display on $discharge_date. Thank you.";

        if (empty($guardian_number)) {
            error_log("[SSCMS Dashboard] Warning: Guardian contact empty for patient_id={$visit['patient_id']}");
            $response['sms_message'] = 'Guardian contact number missing';
        } else {
            $sms_result = sendSMS($guardian_number, $sms_message);
            error_log("[SSCMS Dashboard] SMS sent to $guardian_number: $sms_message (Timezone: Asia/Manila)");
            $response['sms_message'] = $sms_result['message'];
            if (!$sms_result['success']) {
                throw new Exception('Failed to send SMS: ' . $sms_result['message']);
            }
        }

        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Patient $full_name discharged successfully";
        error_log("[SSCMS Dashboard] Discharge success: visit_id=$visit_id, patient_name=$full_name, discharge_time=$discharge_time, timezone=Asia/Manila");
    } catch (Exception $e) {
        $conn->rollBack();
        $response['message'] = $e->getMessage();
        error_log("[SSCMS Dashboard] Discharge error: " . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}

// Fetch stats with retry
try {
    $total_patients = $conn->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $visits_today = $conn->query("SELECT COUNT(*) FROM visits WHERE DATE(visit_date) = CURDATE()")->fetchColumn();
    $visits_month = $conn->query("SELECT COUNT(*) FROM visits WHERE YEAR(visit_date) = YEAR(CURDATE()) AND MONTH(visit_date) = MONTH(CURDATE())")->fetchColumn();
    $medicines_used_today = $conn->query("SELECT COALESCE(SUM(quantity_used), 0) FROM medicine_logs WHERE DATE(visit_date) = CURDATE()")->fetchColumn();
    $total_stock = $conn->query("SELECT COALESCE(SUM(quantity), 0) FROM medicines WHERE is_active = 1")->fetchColumn();
    $medicine_items = $conn->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1")->fetchColumn();
    $low_stock = $conn->query("SELECT COALESCE(SUM(quantity), 0) FROM medicines WHERE is_active = 1")->fetchColumn();
    
    // Fetch expiring medicines from medicine_batches
    $stmt = $conn->prepare("
        SELECT m.name AS medicine_name, MIN(mb.expiration_date) AS earliest_expiration,
               DATEDIFF(MIN(mb.expiration_date), CURDATE()) AS days_until_expiry
        FROM medicine_batches mb
        JOIN medicines m ON mb.medicine_id = m.id
        WHERE m.is_active = 1
        AND mb.expiration_date IS NOT NULL
        AND mb.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 MONTH)
        AND mb.quantity > 0
        GROUP BY mb.medicine_id, m.name
        ORDER BY earliest_expiration ASC
    ");
    $stmt->execute();
    $expiring_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch specialist visits for today and tomorrow
    $stmt = $conn->prepare("
        SELECT sv.id, sv.visit_date, sv.start_time, u.name, u.admin_category
        FROM specialist_visits sv
        JOIN users u ON sv.user_id = u.id
        WHERE DATE(sv.visit_date) IN (CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY))
        AND u.admin_category IN ('Doctor', 'Dentist')
        ORDER BY sv.visit_date, sv.start_time
    ");
    $stmt->execute();
    $specialist_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("[SSCMS Dashboard] Stats fetched: patients=$total_patients, visits_today=$visits_today, visits_month=$visits_month, medicines_used_today=$medicines_used_today, total_stock=$total_stock, medicine_items=$medicine_items, low_stock=$low_stock, expiring_medicines=" . count($expiring_medicines) . ", specialist_visits=" . count($specialist_visits));
} catch (Exception $e) {
    error_log("[SSCMS Dashboard] Stats query error: " . $e->getMessage());
    try {
        $total_patients = $conn->query("SELECT COUNT(*) FROM patients")->fetchColumn();
        $visits_today = $conn->query("SELECT COUNT(*) FROM visits WHERE DATE(visit_date) = CURDATE()")->fetchColumn();
        $visits_month = $conn->query("SELECT COUNT(*) FROM visits WHERE YEAR(visit_date) = YEAR(CURDATE()) AND MONTH(visit_date) = MONTH(CURDATE())")->fetchColumn();
        $medicines_used_today = $conn->query("SELECT COALESCE(SUM(quantity_used), 0) FROM medicine_logs WHERE DATE(visit_date) = CURDATE()")->fetchColumn();
        $total_stock = $conn->query("SELECT COALESCE(SUM(quantity), 0) FROM medicines WHERE is_active = 1")->fetchColumn();
        $medicine_items = $conn->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1")->fetchColumn();
        $low_stock = $conn->query("SELECT COALESCE(SUM(quantity), 0) FROM medicines WHERE is_active = 1")->fetchColumn();
        $expiring_medicines = [];
        $specialist_visits = [];
    } catch (Exception $e2) {
        error_log("[SSCMS Dashboard] Stats retry error: " . $e2->getMessage());
        $total_patients = $visits_today = $visits_month = $medicines_used_today = $total_stock = $medicine_items = $low_stock = 0;
        $expiring_medicines = [];
        $specialist_visits = [];
    }
}

// Determine low stock status
$low_stock_status = $low_stock <= 300 ? 'Low' : ($low_stock <= 500 ? 'Almost Low' : 'Good');
$low_stock_class = $low_stock <= 300 ? 'trend-down' : ($low_stock <= 500 ? 'trend-warning' : 'trend-up');

// Format expiration trend message
$expiry_trend = count($expiring_medicines) > 0
    ? $expiring_medicines[0]['medicine_name'] . ' in ' . $expiring_medicines[0]['days_until_expiry'] . ' days'
    : 'None within 2 months';

// Fetch currently in clinic
try {
    $stmt = $conn->prepare("
        SELECT v.id, v.patient_id, p.first_name, p.last_name, p.category
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE DATE(v.visit_date) = CURDATE()
        AND v.discharge_time IS NULL
        ORDER BY v.visit_time ASC
    ");
    $stmt->execute();
    $current_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Dashboard] Fetched " . count($current_patients) . " patients currently in clinic");
} catch (Exception $e) {
    error_log("[SSCMS Dashboard] Current patients query error: " . $e->getMessage());
    $current_patients = [];
}

// Fetch approved appointments (today only)
try {
    $stmt = $conn->prepare("
        SELECT id, patient_name, category, appointment_date, appointment_time, status
        FROM appointments
        WHERE DATE(appointment_date) = CURDATE() AND LOWER(status) = 'approved'
        ORDER BY appointment_time ASC
    ");
    $stmt->execute();
    $approved_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Dashboard] Fetched " . count($approved_appointments) . " approved appointments");
} catch (Exception $e) {
    error_log("[SSCMS Dashboard] Approved appointments query error: " . $e->getMessage());
    $approved_appointments = [];
}

?>