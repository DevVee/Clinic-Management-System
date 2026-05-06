<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Log Visit] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Create daily_logs table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS daily_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message TEXT NOT NULL,
            log_date DATETIME NOT NULL,
            user_id INT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
} catch (Exception $e) {
    error_log("[SSCMS Log Visit] Error creating daily_logs table: " . $e->getMessage());
}

try {
    $conn->exec("ALTER TABLE visits ADD COLUMN IF NOT EXISTS temperature DECIMAL(4,1) DEFAULT NULL");
} catch (Exception $e) {
    error_log("[SSCMS Log Visit] Error adding temperature column: " . $e->getMessage());
}

// Fetch settings
try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('clinic_contact_1', 'clinic_contact_2', 'guard_contact')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $clinic_contact_1 = $settings['clinic_contact_1'] ?? '0917 123 4567';
    $clinic_contact_2 = $settings['clinic_contact_2'] ?? '0928 765 4321';
    $guard_contact    = $settings['guard_contact']    ?? '09123456789';
} catch (Exception $e) {
    error_log("[SSCMS Log Visit] Error fetching settings: " . $e->getMessage());
    $clinic_contact_1 = '0917 123 4567';
    $clinic_contact_2 = '0928 765 4321';
    $guard_contact    = '09123456789';
}

// SMS function
function sendSMS($number, $message) {
    $apiKey     = '2840c5de6cdfbe118d100ad33fdc179b';
    $senderName = 'ICCBICLINIC';
    $url        = 'https://api.semaphore.co/api/v4/messages';
    $data       = ['apikey' => $apiKey, 'number' => $number, 'message' => $message, 'sendername' => $senderName];
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_POST => 1, CURLOPT_POSTFIELDS => http_build_query($data), CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) { $e = curl_error($ch); curl_close($ch); return "Failed to send SMS: $e"; }
    curl_close($ch);
    $d = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($d)) {
        return isset($d[0]['status']) && $d[0]['status'] === 'Pending' ? "SMS send successful" : "Failed to send SMS: Invalid API response";
    }
    return "Failed to send SMS: HTTP $httpCode";
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        $patient_id          = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $reasons             = $_POST['reason'] ?? [];
        $custom_reason       = filter_input(INPUT_POST, 'custom_reason', FILTER_SANITIZE_SPECIAL_CHARS);
        $took_medicine       = filter_input(INPUT_POST, 'took_medicine', FILTER_SANITIZE_SPECIAL_CHARS);
        $batch_ids           = $_POST['batch_id'] ?? [];
        $medicine_quantities = $_POST['medicine_quantity'] ?? [];
        $visit_date          = filter_input(INPUT_POST, 'visit_date', FILTER_SANITIZE_SPECIAL_CHARS);
        $visit_time          = filter_input(INPUT_POST, 'visit_time', FILTER_SANITIZE_SPECIAL_CHARS);
        $severity            = filter_input(INPUT_POST, 'severity', FILTER_SANITIZE_SPECIAL_CHARS);
        $visit_handling      = filter_input(INPUT_POST, 'visit_handling', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Default';
        $temperature         = filter_input(INPUT_POST, 'temperature', FILTER_VALIDATE_FLOAT);

        if (!$patient_id || (empty($reasons) && empty($custom_reason)) || !$visit_date || !$visit_time || !in_array($severity, ['Mild','Moderate','Severe'])) {
            throw new Exception('Missing or invalid required fields. Please provide at least one reason or a custom reason.');
        }
        if (!preg_match('/^(0?[1-9]|1[0-2]):[0-5][0-9] (AM|PM)$/i', $visit_time)) throw new Exception('Invalid visit time format.');
        $formatted_visit_time = date('H:i:s', strtotime($visit_time));
        if (!$formatted_visit_time) throw new Exception('Invalid visit time format.');
        if (!in_array($visit_handling, ['Default','Send Home','Give Medicine','Return to Class'])) throw new Exception('Invalid visit handling option.');
        if ($temperature !== false && ($temperature < 35.0 || $temperature > 42.0)) throw new Exception('Temperature must be between 35.0°C and 42.0°C.');

        $discharge_time         = ($visit_handling !== 'Default') ? date('H:i:s') : null;
        $discharge_time_display = ($visit_handling !== 'Default') ? date('h:i A') : null;
        $discharge_date         = ($visit_handling !== 'Default') ? date('F j, Y') : null;

        $stmt = $conn->prepare("INSERT INTO visits (patient_id, took_medicine, visit_time, visit_date, severity, discharge_time, visit_handling, temperature) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$patient_id, $took_medicine, $formatted_visit_time, $visit_date, $severity, $discharge_time, $visit_handling, $temperature]);
        $visit_id = $conn->lastInsertId();

        $reason_stmt = $conn->prepare("INSERT INTO visit_reasons (visit_id, reason) VALUES (?, ?)");
        $reason_str  = [];
        foreach ($reasons as $reason) { $reason_stmt->execute([$visit_id, $reason]); $reason_str[] = $reason; }
        if ($custom_reason) { $reason_stmt->execute([$visit_id, $custom_reason]); $reason_str[] = $custom_reason; }
        $reason_str = implode(', ', $reason_str);

        if ($took_medicine === 'Yes' && $visit_handling !== 'Send Home') {
            if (count($batch_ids) !== count($medicine_quantities)) throw new Exception('Mismatch between batches and quantities.');
            $medicine_stmt = $conn->prepare("INSERT INTO visit_medicines (visit_id, medicine_id, quantity, batch_id) VALUES (?, ?, ?, ?)");
            foreach ($batch_ids as $index => $batch_id) {
                $quantity = filter_var($medicine_quantities[$index], FILTER_VALIDATE_INT);
                if (!$batch_id || $quantity <= 0) throw new Exception('Batch and valid quantity are required.');
                $stmt = $conn->prepare("SELECT mb.id, mb.medicine_id, mb.batch_number, mb.quantity, m.name FROM medicine_batches mb JOIN medicines m ON mb.medicine_id = m.id WHERE mb.id = ? AND mb.quantity > 0");
                $stmt->execute([$batch_id]);
                $batch = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$batch) throw new Exception("Batch ID '$batch_id' not found or out of stock.");
                if ($quantity > $batch['quantity']) throw new Exception("Quantity for '{$batch['name']}' (Batch: {$batch['batch_number']}) exceeds available stock.");
                $medicine_stmt->execute([$visit_id, $batch['medicine_id'], $quantity, $batch_id]);
                $conn->prepare("UPDATE medicine_batches SET quantity = quantity - ? WHERE id = ?")->execute([$quantity, $batch_id]);
                $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?")->execute([$quantity, $batch['medicine_id']]);
                $conn->prepare("INSERT INTO medicine_logs (medicine_id, patient_id, quantity_used, visit_date, reason, batch_id, batch_number) VALUES (?, ?, ?, ?, ?, ?, ?)")
                     ->execute([$batch['medicine_id'], $patient_id, $quantity, "$visit_date $formatted_visit_time", $reason_str, $batch_id, $batch['batch_number']]);
            }
        } else { $took_medicine = 'No'; }

        $stmt = $conn->prepare("SELECT first_name, last_name, guardian_contact, program_section, grade_year, category FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient          = $stmt->fetch(PDO::FETCH_ASSOC);
        $full_name        = $patient['first_name'] . ' ' . $patient['last_name'];
        $recipient_number = $patient['guardian_contact'];

        // Guardian SMS
        if ($recipient_number) {
            if (preg_match('/^(\+63|0)9\d{9}$/', $recipient_number)) {
                $intro = $patient['category'] === 'Faculty and Staff' ? "Dear {$full_name}," : "Dear Parent/Guardian,";
                $subject = $patient['category'] === 'Faculty and Staff' ? "You visited" : "Your child, {$full_name}, visited";
                $sms_message = "{$intro}\nThis is ICCBI Clinic. {$subject} our clinic on {$visit_date} at {$visit_time} due to {$reason_str} (Severity: {$severity}, Temp: {$temperature}°C).";
                if ($took_medicine === 'Yes' && $visit_handling !== 'Send Home') {
                    $sms_message .= " Medicines administered: ";
                    foreach ($batch_ids as $index => $batch_id) {
                        $s = $conn->prepare("SELECT m.name FROM medicine_batches mb JOIN medicines m ON mb.medicine_id = m.id WHERE mb.id = ?");
                        $s->execute([$batch_id]); $b = $s->fetch(PDO::FETCH_ASSOC);
                        $sms_message .= "{$b['name']} ({$medicine_quantities[$index]}), ";
                    }
                    $sms_message = rtrim($sms_message, ', ');
                }
                $pronoun = $patient['category'] === 'Faculty and Staff' ? 'You are' : 'They are';
                $pronoun2 = $patient['category'] === 'Faculty and Staff' ? 'You were' : 'They were';
                if ($visit_handling === 'Default')           $sms_message .= " {$pronoun} under observation. Contact us at {$clinic_contact_1} or {$clinic_contact_2}.";
                elseif ($visit_handling === 'Send Home')     $sms_message .= " {$pronoun2} discharged at {$discharge_time_display} on {$discharge_date} and sent home. Contact us at {$clinic_contact_1}.";
                elseif ($visit_handling === 'Give Medicine') $sms_message .= " {$pronoun2} discharged at {$discharge_time_display} on {$discharge_date} after medication. Contact us at {$clinic_contact_1}.";
                elseif ($visit_handling === 'Return to Class') $sms_message .= " {$pronoun2} cleared to return to class on {$discharge_date}. Contact us at {$clinic_contact_1}.";
                $sms_response = sendSMS($recipient_number, $sms_message);
                $conn->prepare("INSERT INTO daily_logs (message, log_date, user_id) VALUES (?, NOW(), ?)")->execute(["Guardian SMS for patient_id=$patient_id: $sms_response", $_SESSION['user_id']]);
                $_SESSION['sms_status'] = $sms_response;
            } else { $_SESSION['sms_status'] = "Failed to send SMS to guardian: Invalid contact number."; }
        } else { $_SESSION['sms_status'] = "Failed to send SMS to guardian: Contact number is empty."; }

        // Adviser SMS
        if ($patient['program_section'] && $patient['grade_year'] && $patient['category'] !== 'Faculty and Staff') {
            $stmt = $conn->prepare("SELECT a.contact_number FROM advisers a JOIN program_sections ps ON a.program_section_id = ps.id JOIN grade_years gy ON a.grade_year_id = gy.id WHERE ps.name = ? AND gy.name = ?");
            $stmt->execute([$patient['program_section'], $patient['grade_year']]);
            $adviser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($adviser && $adviser['contact_number'] && preg_match('/^(\+63|0)9\d{9}$/', $adviser['contact_number'])) {
                $adv_msg = "Dear Adviser,\nThis is ICCBI Clinic. Your student, {$full_name} ({$patient['program_section']} {$patient['grade_year']}), visited our clinic on {$visit_date} at {$visit_time} due to {$reason_str} (Severity: {$severity}, Temp: {$temperature}°C).";
                if ($visit_handling === 'Default')             $adv_msg .= " The student is under observation. Contact the clinic at {$clinic_contact_1}.";
                elseif ($visit_handling === 'Send Home')       $adv_msg .= " Discharged at {$discharge_time_display} on {$discharge_date} and sent home. Contact the clinic at {$clinic_contact_1}.";
                elseif ($visit_handling === 'Give Medicine')   $adv_msg .= " Discharged at {$discharge_time_display} on {$discharge_date} after medication. Contact the clinic at {$clinic_contact_1}.";
                elseif ($visit_handling === 'Return to Class') $adv_msg .= " Cleared to return to class on {$discharge_date}. Contact the clinic at {$clinic_contact_1}.";
                $_SESSION['sms_status_adviser'] = sendSMS($adviser['contact_number'], $adv_msg);
            } else { $_SESSION['sms_status_adviser'] = "Failed to send SMS to adviser: No adviser found."; }
        } else { $_SESSION['sms_status_adviser'] = "Adviser SMS not sent."; }

        // Guard SMS
        if ($visit_handling === 'Send Home' && preg_match('/^(\+63|0)9\d{9}$/', $guard_contact)) {
            $guard_msg = "Dear Guard,\nSSCMS: {$full_name} ({$patient['program_section']} {$patient['grade_year']}) was discharged on {$discharge_date} at {$discharge_time_display} due to {$reason_str} and is permitted to leave. Contact clinic at {$clinic_contact_1}.";
            $_SESSION['sms_status_guard'] = sendSMS($guard_contact, $guard_msg);
        } else { $_SESSION['sms_status_guard'] = "Guard SMS not sent."; }

        $conn->commit();
        $_SESSION['success_message'] = 'Visit logged successfully!';
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
    $search_params = '';
    if (isset($_GET['search']) || isset($_GET['reason']) || isset($_GET['program']) || isset($_GET['year'])) {
        $search_params = '?' . http_build_query(array_filter(['search' => $_GET['search'] ?? '', 'reason' => $_GET['reason'] ?? '', 'program' => $_GET['program'] ?? '', 'year' => $_GET['year'] ?? '']));
    }
    header('Location: log-new-patient.php' . $search_params);
    exit;
}

// Fetch patient
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
if (!$patient_id) { $_SESSION['error_message'] = 'No patient selected.'; header('Location: log-new-patient.php'); exit; }
$stmt = $conn->prepare("SELECT first_name, last_name, middle_name, gender, grade_year, program_section, guardian_contact, category FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) { $_SESSION['error_message'] = 'Patient not found.'; header('Location: log-new-patient.php'); exit; }

// Pre-fetch batches for reuse in JS template
$batches_result = $conn->query("SELECT mb.id, mb.batch_number, mb.quantity, m.name FROM medicine_batches mb JOIN medicines m ON mb.medicine_id = m.id WHERE mb.quantity > 0 ORDER BY m.name, mb.batch_number");
$batches_data   = $batches_result->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Visit — <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?> · SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>
    <style>
        /* ══════════════════════════════════════
           SSCMS — Log Visit
           ══════════════════════════════════════ */
        :root {
            --primary:       #0369a1;
            --primary-dark:  #075985;
            --primary-light: #e0f2fe;
            --accent:        #0ea5e9;
            --emerald:       #10b981;
            --amber:         #f59e0b;
            --rose:          #f43f5e;
            --violet:        #8b5cf6;
            --surface:       #ffffff;
            --surface-2:     #f8fafc;
            --surface-3:     #f1f5f9;
            --border:        #c8d3df;
            --border-strong: #b0bfce;
            --border-focus:  #0ea5e9;
            --text-1:        #0f172a;
            --text-2:        #475569;
            --text-3:        #94a3b8;
            --sidebar-w:     260px;
            --radius:        14px;
            --radius-sm:     8px;
            --radius-xs:     6px;
            --h-input:       40px;
            --shadow-sm:     0 1px 3px rgba(0,0,0,.06);
            --shadow-md:     0 4px 16px rgba(3,105,161,.10);
            --shadow-lg:     0 12px 40px rgba(3,105,161,.14);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface-2);
            color: var(--text-1);
            font-size: .875rem;
        }

        /* ─── Layout ─── */
        .content {
            margin-left: var(--sidebar-w);
            padding: 28px 28px 56px;
            min-height: 100vh;
            transition: margin-left .3s;
        }
        @media (max-width:992px) { .content { margin-left: var(--sidebar-collapsed-width); } }
        @media (max-width:768px) { .content { margin-left: 0; padding: 16px 16px 40px; } }

        /* ─── Page Header ─── */
        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            animation: fadeUp .45s both;
        }

        .page-heading {
            font-family: 'Poppins', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-1);
            line-height: 1.2;
        }

        .page-heading span {
            display: block;
            font-size: .82rem;
            font-weight: 400;
            color: var(--text-3);
            font-family: 'DM Sans', sans-serif;
            margin-top: 3px;
        }

        .breadcrumb-trail {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .78rem;
            color: var(--text-3);
        }

        .breadcrumb-trail a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .breadcrumb-trail a:hover { text-decoration: underline; }

        /* ─── Patient Banner ─── */
        .patient-banner {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 55%, var(--accent) 100%);
            border-radius: var(--radius);
            padding: 20px 26px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            animation: fadeUp .5s .06s both;
        }

        .patient-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .patient-banner::after {
            content: '';
            position: absolute;
            right: -50px; top: -50px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
        }

        .banner-inner {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .patient-avatar-lg {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: rgba(255,255,255,.2);
            border: 2px solid rgba(255,255,255,.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            backdrop-filter: blur(6px);
        }

        .patient-meta { flex: 1; min-width: 0; }

        .patient-meta h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .meta-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 500;
            background: rgba(255,255,255,.15);
            color: rgba(255,255,255,.92);
            border: 1px solid rgba(255,255,255,.2);
            backdrop-filter: blur(4px);
        }

        .meta-chip i { font-size: .65rem; opacity: .8; }

        /* ─── Form Card ─── */
        .form-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 2px solid var(--border-strong);
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
            overflow: hidden;
            transition: box-shadow .25s;
            animation: fadeUp .5s both;
        }

        .form-card:hover { box-shadow: var(--shadow-md); }
        .form-card:nth-child(1) { animation-delay: .08s; }
        .form-card:nth-child(2) { animation-delay: .12s; }
        .form-card:nth-child(3) { animation-delay: .16s; }
        .form-card:nth-child(4) { animation-delay: .20s; }
        .form-card:nth-child(5) { animation-delay: .24s; }

        .form-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 22px;
            border-bottom: 2px solid var(--border);
            background: var(--surface-3);
        }

        .card-header-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .78rem;
            flex-shrink: 0;
        }

        .card-header-title {
            font-family: 'Poppins', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            color: var(--text-1);
        }

        .card-header-sub {
            font-size: .75rem;
            color: var(--text-3);
            margin-top: 1px;
        }

        .form-card-body { padding: 22px; }

        /* ─── Form Elements ─── */
        .field-label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 7px;
        }

        .field-label .req { color: var(--rose); margin-left: 2px; }
        .field-hint { font-size: .72rem; color: var(--text-3); margin-top: 5px; }

        .form-control, .form-select {
            height: var(--h-input);
            padding: 0 12px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            font-family: 'DM Sans', sans-serif;
            font-size: .875rem;
            color: var(--text-1);
            background: var(--surface);
            transition: border-color .2s, box-shadow .2s;
            width: 100%;
            box-sizing: border-box;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14,165,233,.15);
            outline: none;
        }

        .form-control[readonly] {
            background: var(--surface-3);
            color: var(--text-2);
            cursor: default;
        }

        .form-control::placeholder { color: var(--text-3); }

        .form-control.is-invalid, .form-select.is-invalid {
            border-color: var(--rose);
            box-shadow: 0 0 0 3px rgba(244,63,94,.1);
        }

        /* ─── Reason Checkboxes ─── */
        .reason-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 8px;
            padding: 14px;
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            max-height: 220px;
            overflow-y: auto;
        }

        .reason-grid::-webkit-scrollbar { width: 5px; }
        .reason-grid::-webkit-scrollbar-track { background: var(--surface-3); border-radius: 10px; }
        .reason-grid::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

        .reason-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: var(--radius-xs);
            border: 1.5px solid var(--border);
            cursor: pointer;
            transition: all .18s;
            background: var(--surface);
            user-select: none;
        }

        .reason-item:hover { border-color: var(--primary); background: var(--primary-light); }
        .reason-item.selected { border-color: var(--primary); background: var(--primary-light); }

        .reason-item input[type="checkbox"] {
            width: 15px; height: 15px;
            accent-color: var(--primary);
            cursor: pointer;
            flex-shrink: 0;
        }

        .reason-item label {
            font-size: .78rem;
            color: var(--text-2);
            cursor: pointer;
            line-height: 1.3;
        }

        .reason-item.selected label { color: var(--primary); font-weight: 500; }

        /* ─── Radio Group ─── */
        .radio-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .radio-card {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            cursor: pointer;
            transition: all .18s;
            background: var(--surface);
            user-select: none;
            height: var(--h-input);
        }

        .radio-card:hover { border-color: var(--primary); background: var(--primary-light); }
        .radio-card.selected { border-color: var(--primary); background: var(--primary-light); }

        .radio-card input[type="radio"] {
            width: 15px; height: 15px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .radio-card label {
            font-size: .875rem;
            font-weight: 500;
            color: var(--text-2);
            cursor: pointer;
        }

        .radio-card.selected label { color: var(--primary); }

        /* ─── Severity Selector ─── */
        .severity-group { display: flex; gap: 10px; flex-wrap: wrap; }

        .severity-btn {
            flex: 1;
            min-width: 90px;
            height: var(--h-input);
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--surface);
            font-family: 'DM Sans', sans-serif;
            font-size: .835rem;
            font-weight: 600;
            color: var(--text-2);
            cursor: pointer;
            transition: all .18s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .severity-btn:hover { transform: translateY(-1px); }
        .severity-btn[data-val="Mild"]:hover,
        .severity-btn[data-val="Mild"].active   { border-color: var(--emerald); background: #d1fae5; color: #065f46; }
        .severity-btn[data-val="Moderate"]:hover,
        .severity-btn[data-val="Moderate"].active { border-color: var(--amber); background: #fef3c7; color: #92400e; }
        .severity-btn[data-val="Severe"]:hover,
        .severity-btn[data-val="Severe"].active   { border-color: var(--rose); background: #ffe4e6; color: #9f1239; }

        /* ─── Medicine Row ─── */
        .medicine-row {
            background: var(--surface-3);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-bottom: 12px;
            position: relative;
        }

        .medicine-row-num {
            position: absolute;
            top: -10px; left: 14px;
            background: var(--primary);
            color: #fff;
            font-size: .67rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 50px;
            letter-spacing: .05em;
        }

        .btn-remove-med {
            position: absolute;
            top: 10px; right: 12px;
            width: 28px; height: 28px;
            border-radius: 6px;
            background: #fff1f2;
            border: 1.5px solid #fecdd3;
            color: var(--rose);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            cursor: pointer;
            transition: all .18s;
        }

        .btn-remove-med:hover { background: var(--rose); color: #fff; border-color: var(--rose); }

        .qty-warning {
            font-size: .72rem;
            color: var(--rose);
            margin-top: 4px;
            display: none;
        }

        /* ─── Handling Cards ─── */
        .handling-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }

        .handling-card {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 10px;
            cursor: pointer;
            transition: all .18s;
            background: var(--surface);
            text-align: center;
            user-select: none;
        }

        .handling-card:hover { border-color: var(--primary); transform: translateY(-1px); }

        .handling-card.selected.default  { border-color: var(--primary); background: var(--primary-light); }
        .handling-card.selected.send-home { border-color: var(--amber); background: #fef3c7; }
        .handling-card.selected.give-med  { border-color: var(--violet); background: #ede9fe; }
        .handling-card.selected.return-c  { border-color: var(--emerald); background: #d1fae5; }

        .handling-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            margin: 0 auto 8px;
            transition: all .18s;
        }

        .handling-card.default  .handling-icon { background: var(--primary-light); color: var(--primary); }
        .handling-card.send-home .handling-icon { background: #fef3c7; color: #d97706; }
        .handling-card.give-med  .handling-icon { background: #ede9fe; color: #7c3aed; }
        .handling-card.return-c  .handling-icon { background: #d1fae5; color: #059669; }

        .handling-card.selected .handling-icon { transform: scale(1.1); }

        .handling-label {
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-2);
            line-height: 1.3;
        }

        .handling-card.selected.default   .handling-label { color: var(--primary); }
        .handling-card.selected.send-home .handling-label { color: #d97706; }
        .handling-card.selected.give-med  .handling-label { color: #7c3aed; }
        .handling-card.selected.return-c  .handling-label { color: #059669; }

        /* ─── Add Medicine Button ─── */
        .btn-add-med {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: var(--h-input);
            padding: 0 16px;
            border-radius: var(--radius-sm);
            border: 1.5px dashed var(--border);
            background: var(--surface);
            color: var(--text-2);
            font-size: .835rem;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: all .2s;
            width: 100%;
            justify-content: center;
            margin-top: 4px;
        }

        .btn-add-med:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

        /* ─── Action Buttons ─── */
        .actions-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            padding: 20px 0 0;
            animation: fadeUp .5s .28s both;
        }

        .btn-cancel, .btn-submit {
            height: var(--h-input);
            padding: 0 24px;
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            transition: all .2s;
            border: 2px solid transparent;
        }

        .btn-cancel {
            background: var(--surface);
            color: var(--text-2);
            border-color: var(--border-strong);
        }

        .btn-cancel:hover { border-color: var(--rose); color: var(--rose); background: #fff1f2; }

        .btn-submit {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary-dark);
        }

        .btn-submit:hover:not(:disabled) { background: var(--primary-dark); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-submit:disabled { opacity: .65; cursor: not-allowed; transform: none; }

        /* ─── Toast ─── */
        .toast-wrap {
            position: fixed;
            top: 20px; right: 20px;
            z-index: 1090;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .s-toast {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            background: var(--surface);
            border: 1.5px solid var(--border);
            box-shadow: var(--shadow-lg);
            font-size: .85rem;
            min-width: 260px;
            max-width: 340px;
            animation: slideInRight .3s both;
        }

        .s-toast.success { border-left: 4px solid var(--emerald); }
        .s-toast.error   { border-left: 4px solid var(--rose); }
        .s-toast.warning { border-left: 4px solid var(--amber); }

        .toast-icon { font-size: .95rem; flex-shrink: 0; padding-top: 1px; }
        .s-toast.success .toast-icon { color: var(--emerald); }
        .s-toast.error   .toast-icon { color: var(--rose); }
        .s-toast.warning .toast-icon { color: var(--amber); }

        /* ─── Time Display ─── */
        .time-display {
            display: flex;
            align-items: center;
            gap: 10px;
            height: var(--h-input);
            padding: 0 14px;
            background: var(--surface-3);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-weight: 600;
            color: var(--text-2);
            font-size: .875rem;
        }

        .time-display i { color: var(--primary); font-size: .8rem; }

        /* ─── Animations ─── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: none; }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: none; }
        }

        /* ─── Responsive ─── */
        @media (max-width: 768px) {
            .reason-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            .handling-grid { grid-template-columns: repeat(2, 1fr); }
            .severity-group { flex-direction: column; }
            .severity-btn { min-width: unset; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <!-- Hidden select with all batch options for JS cloning -->
    <select id="batchOptionsSource" style="display:none;">
        <option value="">— Select Batch —</option>
        <?php foreach ($batches_data as $b): ?>
            <option value="<?= $b['id'] ?>" data-quantity="<?= $b['quantity'] ?>">
                <?= htmlspecialchars($b['name']) ?> (Batch: <?= htmlspecialchars($b['batch_number']) ?>, Qty: <?= $b['quantity'] ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <div class="content">
        <main>

            <!-- ══ Toasts ══ -->
            <div class="toast-wrap" id="toastWrap">
                <?php
                $toasts = [
                    ['error_message',       'error',   'fa-circle-xmark'],
                    ['sms_status',          'warning', 'fa-triangle-exclamation'],
                    ['sms_status_adviser',  'warning', 'fa-triangle-exclamation'],
                    ['sms_status_guard',    'warning', 'fa-triangle-exclamation'],
                    ['success_message',     'success', 'fa-circle-check'],
                ];
                foreach ($toasts as [$key, $type, $icon]) {
                    if (isset($_SESSION[$key]) && (
                        $type !== 'warning' || strpos($_SESSION[$key], 'Failed') === 0
                    )) {
                        echo '<div class="s-toast '.$type.'"><i class="fas '.$icon.' toast-icon"></i><span>'.htmlspecialchars($_SESSION[$key]).'</span></div>';
                        unset($_SESSION[$key]);
                    }
                }
                ?>
            </div>

            <!-- ══ Page Header ══ -->
            <div class="page-header">
                <div>
                    <h1 class="page-heading">
                        Log Patient Visit
                        <span>Recording visit for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></span>
                    </h1>
                </div>
                <div class="breadcrumb-trail">
                    <a href="/dashboard.php">Dashboard</a>
                    <span>/</span>
                    <a href="log-new-patient.php">Search Patients</a>
                    <span>/</span>
                    <span>Log Visit</span>
                </div>
            </div>

            <!-- ══ Patient Banner ══ -->
            <?php
            $initials = strtoupper(substr($patient['first_name'],0,1) . substr($patient['last_name'],0,1));
            $fullname = htmlspecialchars($patient['last_name'].', '.$patient['first_name'].($patient['middle_name'] ? ' '.$patient['middle_name'] : ''));
            ?>
            <div class="patient-banner">
                <div class="banner-inner">
                    <div class="patient-avatar-lg"><?= $initials ?></div>
                    <div class="patient-meta">
                        <h3><?= $fullname ?></h3>
                        <div class="meta-chips">
                            <?php if ($patient['category']): ?>
                            <span class="meta-chip"><i class="fas fa-tag"></i> <?= htmlspecialchars($patient['category']) ?></span>
                            <?php endif; ?>
                            <?php if ($patient['grade_year']): ?>
                            <span class="meta-chip"><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($patient['grade_year']) ?></span>
                            <?php endif; ?>
                            <?php if ($patient['program_section']): ?>
                            <span class="meta-chip"><i class="fas fa-building-columns"></i> <?= htmlspecialchars($patient['program_section']) ?></span>
                            <?php endif; ?>
                            <?php if ($patient['guardian_contact']): ?>
                            <span class="meta-chip"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['guardian_contact']) ?></span>
                            <?php endif; ?>
                            <span class="meta-chip"><i class="fas fa-<?= strtolower($patient['gender'] ?? '') === 'female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ FORM ══ -->
            <form id="logVisitForm"
                  action="log-visit.php?patient_id=<?= $patient_id ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&reason=<?= urlencode($_GET['reason'] ?? '') ?>&program=<?= urlencode($_GET['program'] ?? '') ?>&year=<?= urlencode($_GET['year'] ?? '') ?>"
                  method="POST">
                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                <input type="hidden" name="severity" id="severityHidden" value="">
                <input type="hidden" name="visit_handling" id="visitHandlingHidden" value="Default">

                <!-- ── Section 1: Visit Reasons ── -->
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-header-icon"><i class="fas fa-notes-medical"></i></div>
                        <div>
                            <div class="card-header-title">Visit Reasons</div>
                            <div class="card-header-sub">Select one or more reasons for this visit</div>
                        </div>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-4">
                            <div class="col-lg-7">
                                <label class="field-label">Reason(s) for Visit <span class="req">*</span></label>
                                <div class="reason-grid" id="reasonGrid">
                                    <?php
                                    $reasonOptions = ['Headache','Stomach Ache','Fever','Cold/Flu','Cough','Nausea','Vomiting','Diarrhea','Allergy','Skin Rash','Injury (Wound/Bruise)','Sprain/Fracture','High Blood Pressure','Low Blood Pressure','Menstrual Pain','Eye Irritation','Ear Pain','Dental Pain'];
                                    foreach ($reasonOptions as $r):
                                        $id = 'reason_'.str_replace([' ','/','-','(',')',','], '_', strtolower($r));
                                    ?>
                                    <div class="reason-item" onclick="toggleReason(this)">
                                        <input class="reason-checkbox" type="checkbox" name="reason[]" value="<?= htmlspecialchars($r) ?>" id="<?= $id ?>">
                                        <label for="<?= $id ?>"><?= htmlspecialchars($r) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="field-hint">Click to select — scroll for more options</p>
                            </div>

                            <div class="col-lg-5">
                                <div class="mb-4">
                                    <label class="field-label">Severity <span class="req">*</span></label>
                                    <div class="severity-group">
                                        <button type="button" class="severity-btn" data-val="Mild"     onclick="setSeverity('Mild')">    Mild</button>
                                        <button type="button" class="severity-btn" data-val="Moderate" onclick="setSeverity('Moderate')">Moderate</button>
                                        <button type="button" class="severity-btn" data-val="Severe"   onclick="setSeverity('Severe')">  Severe</button>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="field-label" for="temperature">Temperature (°C) <span class="req">*</span></label>
                                    <input type="number" step="0.1" min="35.0" max="42.0"
                                           class="form-control" name="temperature" id="temperature"
                                           placeholder="e.g. 36.6" required>
                                    <p class="field-hint">Normal range: 35.0°C – 42.0°C</p>
                                </div>

                                <div>
                                    <label class="field-label" for="custom_reason">Custom / Other Reason</label>
                                    <input type="text" class="form-control" name="custom_reason" id="custom_reason"
                                           placeholder="Type any additional reason here…">
                                    <p class="field-hint">Use if the reason isn't listed above</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Section 2: Medicine ── -->
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-header-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-pills"></i></div>
                        <div>
                            <div class="card-header-title">Medicine Information</div>
                            <div class="card-header-sub">Record any medicines given to the patient</div>
                        </div>
                    </div>
                    <div class="form-card-body">
                        <div class="mb-4">
                            <label class="field-label">Did the patient take medicine? <span class="req">*</span></label>
                            <div class="radio-group">
                                <div class="radio-card selected" id="radio-no" onclick="setMedicine('No')">
                                    <input type="radio" name="took_medicine" value="No" id="took_medicine_no" checked>
                                    <label for="took_medicine_no">No</label>
                                </div>
                                <div class="radio-card" id="radio-yes" onclick="setMedicine('Yes')">
                                    <input type="radio" name="took_medicine" value="Yes" id="took_medicine_yes">
                                    <label for="took_medicine_yes">Yes</label>
                                </div>
                            </div>
                        </div>

                        <div id="medicineSection" style="display:none;">
                            <div id="medicineRows"></div>
                            <button type="button" class="btn-add-med" id="addMedicineBtn">
                                + Add Another Medicine Batch
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Section 3: Visit Handling ── -->
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-header-icon" style="background:#d1fae5;color:#059669;"><i class="fas fa-procedures"></i></div>
                        <div>
                            <div class="card-header-title">Visit Handling</div>
                            <div class="card-header-sub">How was this visit resolved?</div>
                        </div>
                    </div>
                    <div class="form-card-body">
                        <div class="handling-grid">
                            <div class="handling-card default selected" data-val="Default" onclick="setHandling(this)">
                                <div class="handling-icon"><i class="fas fa-eye"></i></div>
                                <div class="handling-label">Under Observation</div>
                            </div>
                            <div class="handling-card send-home" data-val="Send Home" onclick="setHandling(this)">
                                <div class="handling-icon"><i class="fas fa-house-medical"></i></div>
                                <div class="handling-label">Send Home</div>
                            </div>
                            <div class="handling-card give-med" data-val="Give Medicine" onclick="setHandling(this)">
                                <div class="handling-icon"><i class="fas fa-capsules"></i></div>
                                <div class="handling-label">Give Medicine</div>
                            </div>
                            <div class="handling-card return-c" data-val="Return to Class" onclick="setHandling(this)">
                                <div class="handling-icon"><i class="fas fa-person-walking-arrow-right"></i></div>
                                <div class="handling-label">Return to Class</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Section 4: Date & Time ── -->
                <div class="form-card">
                    <div class="form-card-header">
                        <div class="card-header-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-clock"></i></div>
                        <div>
                            <div class="card-header-title">Visit Date & Time</div>
                            <div class="card-header-sub">Auto-set to current Asia/Manila time</div>
                        </div>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="field-label">Visit Time</label>
                                <div class="time-display">
                                    <i class="fas fa-clock"></i>
                                    <span id="liveTime"><?= date('h:i A') ?></span>
                                </div>
                                <input type="hidden" name="visit_time" id="visitTimeHidden" value="<?= date('h:i A') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="field-label">Visit Date</label>
                                <div class="time-display">
                                    <i class="fas fa-calendar-day"></i>
                                    <span><?= date('l, F j, Y') ?></span>
                                </div>
                                <input type="hidden" name="visit_date" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Actions ── -->
                <div class="actions-row">
                    <a href="log-new-patient.php?search=<?= urlencode($_GET['search'] ?? '') ?>&reason=<?= urlencode($_GET['reason'] ?? '') ?>&program=<?= urlencode($_GET['program'] ?? '') ?>&year=<?= urlencode($_GET['year'] ?? '') ?>"
                       class="btn-cancel">
                        Cancel
                    </a>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span id="submitText">Log Visit</span>
                        <span id="submitSpinner" style="display:none;">
                            <i class="fas fa-spinner fa-spin" style="font-size:.8rem;"></i>
                        </span>
                    </button>
                </div>

            </form>

        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    /* ══════════════════════════════════════
       SSCMS Log Visit — JS
       ══════════════════════════════════════ */

    let medRowCount = 0;

    // ─── Reason toggle ───────────────────
    function toggleReason(item) {
        const cb = item.querySelector('input[type="checkbox"]');
        cb.checked = !cb.checked;
        item.classList.toggle('selected', cb.checked);
    }

    // ─── Severity ────────────────────────
    function setSeverity(val) {
        document.querySelectorAll('.severity-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`.severity-btn[data-val="${val}"]`).classList.add('active');
        document.getElementById('severityHidden').value = val;
    }

    // ─── Visit Handling ──────────────────
    function setHandling(card) {
        document.querySelectorAll('.handling-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        const val = card.dataset.val;
        document.getElementById('visitHandlingHidden').value = val;

        // Hide medicine section if Send Home
        if (val === 'Send Home') {
            setMedicine('No');
            document.getElementById('radio-yes').style.opacity = '.4';
            document.getElementById('radio-yes').style.pointerEvents = 'none';
        } else {
            document.getElementById('radio-yes').style.opacity = '';
            document.getElementById('radio-yes').style.pointerEvents = '';
        }
    }

    // ─── Medicine toggle ─────────────────
    function setMedicine(val) {
        const isYes = val === 'Yes';
        document.getElementById('took_medicine_yes').checked = isYes;
        document.getElementById('took_medicine_no').checked  = !isYes;
        document.getElementById('radio-yes').classList.toggle('selected', isYes);
        document.getElementById('radio-no').classList.toggle('selected', !isYes);
        document.getElementById('medicineSection').style.display = isYes ? 'block' : 'none';
        if (isYes && medRowCount === 0) addMedicineRow();
        if (!isYes) { document.getElementById('medicineRows').innerHTML = ''; medRowCount = 0; }
    }

    // ─── Build batch options HTML ─────────
    function getBatchOptions() {
        const src = document.getElementById('batchOptionsSource');
        return src.innerHTML;
    }

    // ─── Add medicine row ────────────────
    function addMedicineRow() {
        const idx = medRowCount++;
        const row = document.createElement('div');
        row.className = 'medicine-row';
        row.id        = `medRow_${idx}`;
        row.innerHTML = `
            <span class="medicine-row-num">BATCH ${idx + 1}</span>
            ${idx > 0 ? `<button type="button" class="btn-remove-med" onclick="removeMedRow(${idx})" title="Remove"><i class="fas fa-times"></i></button>` : ''}
            <div class="row g-3" style="margin-top:10px;">
                <div class="col-md-6">
                    <label class="field-label">Select Batch <span class="req">*</span></label>
                    <select name="batch_id[]" id="batch_${idx}" class="form-select batch-select" onchange="onBatchChange(this)">
                        ${getBatchOptions()}
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="field-label">Quantity Taken <span class="req">*</span></label>
                    <input type="number" class="form-control med-qty" name="medicine_quantity[]"
                           id="med_qty_${idx}" min="1" placeholder="Enter quantity"
                           onchange="validateQty(this)">
                    <div class="qty-warning" id="qty_warn_${idx}"></div>
                </div>
            </div>`;
        document.getElementById('medicineRows').appendChild(row);
    }

    function removeMedRow(idx) {
        document.getElementById(`medRow_${idx}`)?.remove();
        // Renumber labels
        document.querySelectorAll('.medicine-row-num').forEach((el, i) => el.textContent = `BATCH ${i + 1}`);
    }

    function onBatchChange(select) {
        const row = select.closest('.medicine-row');
        const qtyInput = row.querySelector('.med-qty');
        const avail = parseInt(select.options[select.selectedIndex]?.dataset?.quantity) || 0;
        if (select.value) { qtyInput.setAttribute('max', avail); qtyInput.value = avail > 0 ? 1 : ''; }
        else { qtyInput.value = ''; }
    }

    function validateQty(input) {
        const row   = input.closest('.medicine-row');
        const sel   = row.querySelector('.batch-select');
        const avail = parseInt(sel.options[sel.selectedIndex]?.dataset?.quantity) || 0;
        const warn  = row.querySelector('.qty-warning');
        const val   = parseInt(input.value) || 0;
        if (val > avail) {
            warn.textContent = `Exceeds available stock (${avail} units).`;
            warn.style.display = 'block';
            input.value = avail;
        } else { warn.style.display = 'none'; }
    }

    // ─── Live clock ──────────────────────
    function updateClock() {
        const now = new Date();
        const t = now.toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit', hour12: true });
        document.getElementById('liveTime').textContent = t;
        document.getElementById('visitTimeHidden').value = t;
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ─── Form validation & submit ─────────
    document.getElementById('logVisitForm').addEventListener('submit', function(e) {
        const reasons  = [...document.querySelectorAll('.reason-checkbox:checked')].map(c => c.value);
        const custom   = document.getElementById('custom_reason').value.trim();
        const severity = document.getElementById('severityHidden').value;
        const temp     = parseFloat(document.getElementById('temperature').value);
        const handling = document.getElementById('visitHandlingHidden').value;

        if (!reasons.length && !custom) {
            e.preventDefault();
            alert('Please select at least one visit reason, or enter a custom reason.');
            document.getElementById('reasonGrid').scrollIntoView({ behavior: 'smooth' });
            return;
        }

        if (!severity) {
            e.preventDefault();
            alert('Please select a severity level.');
            return;
        }

        if (isNaN(temp) || temp < 35.0 || temp > 42.0) {
            e.preventDefault();
            document.getElementById('temperature').classList.add('is-invalid');
            alert('Please enter a valid temperature between 35.0°C and 42.0°C.');
            document.getElementById('temperature').focus();
            return;
        }

        document.getElementById('temperature').classList.remove('is-invalid');

        const tookMed = document.getElementById('took_medicine_yes').checked;
        if (tookMed && handling !== 'Send Home') {
            const batches = [...document.querySelectorAll('.batch-select')];
            const qtys    = [...document.querySelectorAll('.med-qty')];
            for (let i = 0; i < batches.length; i++) {
                if (!batches[i].value) {
                    e.preventDefault();
                    alert('Please select a batch for all medicine entries.');
                    batches[i].focus();
                    return;
                }
                const qty   = parseInt(qtys[i]?.value) || 0;
                const avail = parseInt(batches[i].options[batches[i].selectedIndex]?.dataset?.quantity) || 0;
                if (qty <= 0 || qty > avail) {
                    e.preventDefault();
                    alert(`Invalid quantity for batch ${i+1}. Must be between 1 and ${avail}.`);
                    qtys[i]?.focus();
                    return;
                }
            }
        }

        // Show spinner
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('submitText').textContent = 'Logging…';
        document.getElementById('submitSpinner').style.display = 'inline';
    });

    // ─── Event bindings ──────────────────
    document.getElementById('addMedicineBtn').addEventListener('click', addMedicineRow);

    // ─── Auto-dismiss toasts ─────────────
    setTimeout(() => {
        document.querySelectorAll('.s-toast').forEach(t => {
            t.style.transition = 'opacity .4s';
            t.style.opacity = '0';
            setTimeout(() => t.remove(), 400);
        });
    }, 5000);

    console.log('[SSCMS] Log Visit initialized');
    </script>
</body>
</html>