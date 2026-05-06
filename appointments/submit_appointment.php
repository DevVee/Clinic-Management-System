<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

// ── Helper: send JSON and exit ──────────────────────────────────────────────
function respond(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// ── Only accept POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

// ── Resolve DB connection (supports $pdo, $conn, $db, $connection) ──────────
$resolvedPDO = null;
if     (isset($pdo)        && $pdo        instanceof PDO) { $resolvedPDO = $pdo;        }
elseif (isset($conn)       && $conn       instanceof PDO) { $resolvedPDO = $conn;       }
elseif (isset($db)         && $db         instanceof PDO) { $resolvedPDO = $db;         }
elseif (isset($connection) && $connection instanceof PDO) { $resolvedPDO = $connection; }
elseif (isset($conn) && $conn instanceof mysqli) {
    error_log('[SSCMS submit_appointment] MySQLi detected — PDO required.');
    respond(false, 'Database configuration error: PDO required. Please contact the administrator.');
} else {
    error_log('[SSCMS submit_appointment] No PDO connection found. Variables present: ' . implode(', ', array_keys(get_defined_vars())));
    respond(false, 'Database connection error. Please contact the administrator.');
}
$pdo = $resolvedPDO;

// ── CSRF validation ─────────────────────────────────────────────────────────
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    error_log('[SSCMS submit_appointment] CSRF mismatch.');
    respond(false, 'Security token mismatch. Please refresh the page and try again.');
}

// ── Collect & sanitize inputs ───────────────────────────────────────────────
$patient_name     = trim($_POST['patient_name']     ?? '');
$category         = trim($_POST['category']         ?? '');
$phone            = trim($_POST['phone']             ?? '');
$appointee        = trim($_POST['appointee']         ?? '');
$appointment_date = trim($_POST['appointment_date']  ?? '');
$appointment_time = trim($_POST['appointment_time']  ?? '');
$reason           = trim($_POST['reason']            ?? '');

// ── Required fields ─────────────────────────────────────────────────────────
$missing = [];
if (!$patient_name)     $missing[] = 'Patient Name';
if (!$category)         $missing[] = 'Category';
if (!$phone)            $missing[] = 'Phone Number';
if (!$appointee)        $missing[] = 'Appointee';
if (!$appointment_date) $missing[] = 'Appointment Date';
if (!$appointment_time) $missing[] = 'Appointment Time';
if (!$reason)           $missing[] = 'Reason';

if (!empty($missing)) {
    respond(false, 'Missing required fields: ' . implode(', ', $missing) . '.');
}

// ── Phone format ────────────────────────────────────────────────────────────
if (!preg_match('/^\d{10,11}$/', $phone)) {
    respond(false, 'Phone number must be 10 or 11 digits (numbers only).');
}

// ── Date format ─────────────────────────────────────────────────────────────
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    respond(false, 'Invalid appointment date format. Expected YYYY-MM-DD.');
}

$apptDate = DateTime::createFromFormat('Y-m-d', $appointment_date);
$today    = new DateTime('today');
if (!$apptDate) {
    respond(false, 'Could not parse appointment date.');
}
if ($apptDate < $today) {
    respond(false, 'Appointment date cannot be in the past.');
}

// ── Weekday only ────────────────────────────────────────────────────────────
$dow = (int) $apptDate->format('N'); // 1=Mon … 7=Sun
if ($dow >= 6) {
    respond(false, 'Appointments are only available Monday – Friday.');
}

// ── Appointee whitelist ─────────────────────────────────────────────────────
$validAppointees = ['Doctor', 'Nurse', 'Dentist'];
if (!in_array($appointee, $validAppointees, true)) {
    respond(false, 'Invalid appointee. Choose Doctor, Nurse, or Dentist.');
}

// ── Category whitelist (all possible categories) ────────────────────────────
$validCategories = [
    // Students
    'Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Graduate School', 'Alumni',
    // Teaching Staff
    'Faculty', 'Faculty Staff',
    // Non-Teaching / Administrative
    'Administrative Staff', 'Support Staff', 'Clinic Staff',
    // Maintenance / Facilities
    'Maintenance Staff', 'Janitorial Staff', 'Security Staff',
    // Other
    'Non-Teaching Staff', 'Non-Student', 'Visitor', 'Other',
];
if (!in_array($category, $validCategories, true)) {
    respond(false, 'Invalid category selected: ' . htmlspecialchars($category));
}

// ── Time format & range ─────────────────────────────────────────────────────
if (!preg_match('/^\d{2}:00:00$/', $appointment_time)) {
    respond(false, 'Invalid time format. Expected HH:00:00.');
}
$hour = (int) substr($appointment_time, 0, 2);
if ($hour < 7 || $hour > 16) {
    respond(false, 'Appointment time must be between 07:00 AM and 04:00 PM.');
}

// ── Duplicate check ─────────────────────────────────────────────────────────
try {
    $chk = $pdo->prepare(
        "SELECT id FROM appointments
         WHERE patient_name      = :pname
           AND appointment_date  = :adate
           AND appointment_time  = :atime
           AND appointee         = :appointee
         LIMIT 1"
    );
    $chk->execute([
        ':pname'     => $patient_name,
        ':adate'     => $appointment_date,
        ':atime'     => $appointment_time,
        ':appointee' => $appointee,
    ]);
    if ($chk->fetch()) {
        respond(false, 'A duplicate appointment already exists for this patient at the same date, time, and appointee.');
    }
} catch (PDOException $e) {
    error_log('[SSCMS submit_appointment] Duplicate check error: ' . $e->getMessage());
    respond(false, 'Database error during duplicate check. Please try again.');
}

// ── Insert ──────────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "INSERT INTO appointments
            (patient_name, category, phone, appointee, appointment_date, appointment_time, reason, status, created_at, updated_at)
         VALUES
            (:pname, :cat, :phone, :appointee, :adate, :atime, :reason, 'pending', NOW(), NOW())"
    );
    $stmt->execute([
        ':pname'     => $patient_name,
        ':cat'       => $category,
        ':phone'     => $phone,
        ':appointee' => $appointee,
        ':adate'     => $appointment_date,
        ':atime'     => $appointment_time,
        ':reason'    => $reason,
    ]);

    $newId = $pdo->lastInsertId();

    // Regenerate CSRF to prevent reuse
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    error_log("[SSCMS submit_appointment] Appointment #{$newId} created — '{$patient_name}' | {$appointment_date} {$appointment_time} | {$appointee} | {$category}");

    respond(true, 'Appointment booked successfully!', ['appointment_id' => (int) $newId]);

} catch (PDOException $e) {
    error_log('[SSCMS submit_appointment] Insert error: ' . $e->getMessage());
    respond(false, 'Failed to save appointment. DB error: ' . $e->getMessage());
}