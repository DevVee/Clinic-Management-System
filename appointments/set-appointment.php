<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Set Appointment] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response['message'] = 'Invalid CSRF token';
        error_log("[SSCMS Set Appointment] Invalid CSRF token");
        echo json_encode($response);
        exit;
    }

    $required = ['appointment_date', 'start_time', 'appointee'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $response['message'] = 'Required fields: Date, Start Time, Appointee';
            echo json_encode($response);
            exit;
        }
    }

    $patient_name = filter_var($_POST['patient_name'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
    $category     = filter_var($_POST['category']     ?? '', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
    $phone        = filter_var($_POST['phone']        ?? '', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
    $appointee    = filter_var($_POST['appointee'],       FILTER_SANITIZE_SPECIAL_CHARS);
    $date         = filter_var($_POST['appointment_date'], FILTER_SANITIZE_SPECIAL_CHARS);
    $start_time   = filter_var($_POST['start_time'],      FILTER_SANITIZE_SPECIAL_CHARS);
    $reason       = filter_var($_POST['reason']      ?? '', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;

    $end_time = date('H:i:s', strtotime($start_time . ' +30 minutes'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $response['message'] = 'Invalid date format';
        echo json_encode($response);
        exit;
    }

    $valid_times = [];
    for ($h = 7; $h <= 16; $h++) {
        $valid_times[] = sprintf("%02d:00:00", $h);
    }
    if (!in_array($start_time, $valid_times)) {
        $response['message'] = 'Invalid time';
        echo json_encode($response);
        exit;
    }

    $valid_appointees = ['Doctor', 'Nurse', 'Dentist'];
    if (!in_array($appointee, $valid_appointees)) {
        $response['message'] = 'Invalid appointee';
        echo json_encode($response);
        exit;
    }

    if ($category && !in_array($category, ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni', 'Non-Student',
        'Teacher', 'Faculty', 'Faculty Staff', 'Administrative Staff', 'Clinic Staff', 'Support Staff',
        'Non-Teaching', 'Non-Teaching Staff', 'Maintenance Staff', 'Janitorial Staff', 'Security Staff',
        'Staff', 'Visitor', 'Other'])) {
        $response['message'] = 'Invalid category';
        echo json_encode($response);
        exit;
    }

    if ($phone && !preg_match('/^\d{10,11}$/', $phone)) {
        $response['message'] = 'Phone number must be 10 or 11 digits';
        echo json_encode($response);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM appointments
            WHERE appointment_date = ? AND appointee = ?
            AND (
                (appointment_time <= ? AND end_time >= ?) OR
                (appointment_time <= ? AND end_time >= ?)
            )
        ");
        $stmt->execute([$date, $appointee, $start_time, $start_time, $end_time, $end_time]);
        if ($stmt->fetchColumn() > 0) {
            $response['message'] = 'Time slot overlaps with existing appointment';
            echo json_encode($response);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO appointments (patient_name, category, phone, appointment_date, appointment_time, end_time, reason, appointee, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')
        ");
        $stmt->execute([$patient_name, $category, $phone, $date, $start_time, $end_time, $reason, $appointee]);
        error_log("[SSCMS Set Appointment] Slot created for $appointee on $date $start_time-$end_time" . ($patient_name ? " by $patient_name" : ""));

        $response['success'] = true;
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        $response['message'] = 'Database error';
        error_log("[SSCMS Set Appointment] Error: " . $e->getMessage());
        echo json_encode($response);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Set Appointment Slots">
    <meta name="author" content="ICCBI">
    <title>Set Appointment Slots - SSCMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php include '../includes/sscmslogo.php'; ?>

    <style>
        :root {
            --primary:        hsl(201 85% 39%);
            --primary-dark:   hsl(201 85% 31%);
            --primary-light:  hsl(201 85% 94%);
            --secondary:      hsl(213 77% 54%);
            --success:        hsl(168 72% 42%);
            --success-light:  hsl(168 72% 93%);
            --warning:        hsl(38 92% 50%);
            --danger:         hsl(0 84% 60%);
            --danger-light:   hsl(0 84% 93%);
            --foreground:     hsl(222 47% 17%);
            --text-secondary: hsl(215 16% 47%);
            --text-muted:     hsl(215 16% 65%);
            --border:         hsl(214 32% 91%);
            --background:     hsl(210 40% 98%);
            --card-bg:        #ffffff;
            --sidebar-width:           280px;
            --sidebar-collapsed-width: 70px;
            --header-height:  70px;
            --transition-speed: 0.3s;
            --grad-primary: linear-gradient(135deg, hsl(201 85% 39%), hsl(213 77% 48%));
            --grad-success:  linear-gradient(135deg, hsl(168 72% 38%), hsl(168 72% 52%));
            --grad-danger:   linear-gradient(135deg, hsl(0 84% 55%), hsl(0 84% 68%));
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--background);
            color: var(--foreground);
            margin: 0; padding: 0;
            font-size: 0.88rem;
            overflow-x: hidden;
        }
        h1,h2,h3,h4,h5,h6 { font-family: 'Poppins', sans-serif; }

        /* ── Layout ── */
        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1.25rem 2rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
            background:
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><g fill="%230f73ba" fill-opacity="0.025"><path d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/></g></svg>');
        }
        .container-fluid { max-width: 1100px; padding: 0; }

        /* ── Page hero ── */
        .page-hero {
            background: var(--grad-primary);
            border-radius: 1.1rem;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.1rem;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before { content:''; position:absolute; top:-28px; right:-28px; width:110px; height:110px; border-radius:50%; background:rgba(255,255,255,.07); }
        .page-hero::after  { content:''; position:absolute; bottom:-18px; left:38px; width:72px; height:72px; border-radius:50%; background:rgba(255,255,255,.05); }
        .hero-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.65rem; }
        .hero-title { color:white; font-size:1.25rem; font-weight:700; margin:0 0 .2rem; display:flex; align-items:center; gap:.5rem; }
        .hero-sub   { color:rgba(255,255,255,.7); font-size:.78rem; }

        /* ── Breadcrumb ── */
        .breadcrumb-bar {
            background: white;
            border: 1px solid var(--border);
            border-radius: .7rem;
            padding: .45rem 1rem;
            font-size: .77rem;
            margin-bottom: 1.1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
        }
        .breadcrumb-bar a { color:var(--primary); text-decoration:none; font-weight:500; }
        .breadcrumb-bar a:hover { text-decoration:underline; }
        .breadcrumb-bar .sep { color:var(--text-muted); margin:0 .35rem; }
        .breadcrumb-bar .cur { color:var(--text-secondary); }

        /* ── Main card ── */
        .main-card {
            background: rgba(255,255,255,.94);
            border: 1px solid var(--border);
            border-radius: 1.1rem;
            box-shadow: 0 4px 24px rgba(15,115,186,.06);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .main-card-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: .9rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .main-card-title {
            font-family: 'Poppins', sans-serif;
            font-size: .92rem;
            font-weight: 700;
            color: var(--foreground);
            display: flex; align-items: center; gap: .45rem;
            margin: 0;
        }
        .main-card-body { padding: 1.5rem; }

        /* ── Form ── */
        .form-label {
            font-family: 'Poppins', sans-serif;
            font-size: .8rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: .3rem;
            display: block;
        }
        .req { color: var(--danger); margin-left: 2px; }

        .field-wrap { position: relative; }

        .form-control, .form-select {
            font-family: 'DM Sans', sans-serif;
            font-size: .87rem;
            border-radius: .65rem;
            border: 1.5px solid var(--border);
            background: white;
            color: var(--foreground);
            height: 42px;
            transition: border-color .2s, box-shadow .2s;
            padding-left: .9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px hsl(201 85% 39% / .12);
            outline: none;
        }
        textarea.form-control { height: auto; resize: vertical; }

        /* input with leading icon */
        .has-icon .form-control,
        .has-icon .form-select { padding-left: 2.4rem; }
        .field-icon {
            position: absolute;
            left: .75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            width: 15px; height: 15px;
        }

        /* ── Section divider inside form ── */
        .form-section-label {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: var(--primary-light);
            color: var(--primary);
            font-family: 'Poppins', sans-serif;
            font-size: .7rem;
            font-weight: 700;
            padding: .25rem .75rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .75rem;
        }

        /* ── Buttons ── */
        .btn {
            font-family: 'Poppins', sans-serif;
            font-size: .82rem;
            font-weight: 600;
            border-radius: 9999px;
            padding: .55rem 1.25rem;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            transition: transform .18s, box-shadow .18s;
            border: none;
            cursor: pointer;
            line-height: 1.5;
            height: auto;
            min-height: unset;
        }
        .btn:hover:not(:disabled) { transform: translateY(-1px); }

        .btn-primary-grad {
            background: var(--grad-primary);
            color: white;
            box-shadow: 0 3px 12px hsl(201 85% 39% / .3);
        }
        .btn-primary-grad:hover { box-shadow: 0 6px 18px hsl(201 85% 39% / .4); color: white; }

        .btn-ol-primary {
            background: transparent;
            color: var(--primary);
            border: 1.5px solid var(--primary);
        }
        .btn-ol-primary:hover { background: var(--primary-light); color: var(--primary); }

        .btn-submit {
            width: 100%;
            justify-content: center;
            padding: .7rem 1.5rem;
            font-size: .88rem;
            background: var(--grad-primary);
            color: white;
            box-shadow: 0 4px 14px hsl(201 85% 39% / .3);
            border-radius: .75rem;
        }
        .btn-submit:hover { box-shadow: 0 8px 22px hsl(201 85% 39% / .42); color: white; }
        .btn-submit:disabled { opacity: .7; cursor: not-allowed; transform: none !important; }

        /* ── Info box ── */
        .info-box {
            background: var(--primary-light);
            border: 1px solid hsl(201 85% 82%);
            border-radius: .75rem;
            padding: .85rem 1rem;
            font-size: .8rem;
            color: hsl(201 85% 30%);
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            margin-bottom: 1.25rem;
        }
        .info-box i { flex-shrink: 0; margin-top: .05rem; }

        /* ── Toast ── */
        .toast {
            border-radius: .75rem;
            box-shadow: 0 10px 40px rgba(0,0,0,.15);
            border: none;
            min-width: 300px;
            overflow: hidden;
        }
        .toast.success { border-left: 3px solid var(--success); }
        .toast.error   { border-left: 3px solid var(--danger); }
        .toast-header {
            background: white;
            color: var(--foreground);
            border-bottom: 1px solid var(--border);
            padding: .5rem 1rem;
            font-family: 'Poppins', sans-serif;
            font-size: .8rem;
        }
        .toast-body { padding: .75rem 1rem; font-size: .82rem; font-weight: 500; font-family: 'DM Sans', sans-serif; }

        /* ── Footer ── */
        footer {
            background: white;
            border-top: 1px solid var(--border);
            padding: .9rem 1.25rem;
            font-size: .76rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 1.5rem;
        }

        /* ── Flatpickr ── */
        .flatpickr-calendar { font-family: 'DM Sans', sans-serif; border-radius: .85rem; box-shadow: 0 10px 40px rgba(0,0,0,.14); }
        .flatpickr-day.selected { background: var(--primary) !important; border-color: var(--primary) !important; }

        /* ── Animations ── */
        @keyframes slideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        .su { animation: slideUp .45s ease forwards; }
        .d1 { animation-delay:.06s; opacity:0; }
        .d2 { animation-delay:.13s; opacity:0; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Responsive ── */
        @media (max-width:992px) { :root { --sidebar-width: var(--sidebar-collapsed-width); } }
        @media (max-width:768px) {
            .content { margin-left: 0; padding: 1rem; }
            .hero-title { font-size: 1.1rem; }
            .main-card-body { padding: 1rem; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">

                <!-- ── Page Hero ── -->
                <div class="page-hero su">
                    <div class="hero-inner">
                        <div>
                            <h1 class="hero-title">
                                <i data-lucide="calendar-cog" style="width:20px;height:20px;"></i>
                                Set Appointment Slots
                            </h1>
                            <p class="hero-sub">Create and manage available clinic appointment slots</p>
                        </div>
                        <a href="/appointments/appointment-list.php" class="btn btn-ol-primary" style="background:rgba(255,255,255,.15);color:white;border-color:rgba(255,255,255,.4);">
                            <i data-lucide="list" style="width:13px;height:13px;"></i> View Appointments
                        </a>
                    </div>
                </div>

                <!-- ── Breadcrumb ── -->
                <div class="breadcrumb-bar su d1">
                    <a href="/dashboard.php"><i class="fas fa-home" style="font-size:.7rem;"></i> Dashboard</a>
                    <span class="sep">/</span>
                    <a href="/appointments/appointment-list.php">Appointments</a>
                    <span class="sep">/</span>
                    <span class="cur">Set Slots</span>
                </div>

                <!-- ── Main Card ── -->
                <div class="main-card su d2">
                    <div class="main-card-header">
                        <p class="main-card-title">
                            <i data-lucide="calendar-plus" style="width:16px;height:16px;color:var(--primary);"></i>
                            Create Appointment Slot
                        </p>
                        <a href="/appointments/appointment-list.php" class="btn btn-ol-primary" style="font-size:.77rem;padding:.38rem .9rem;">
                            <i data-lucide="arrow-left" style="width:12px;height:12px;"></i> Back to List
                        </a>
                    </div>

                    <div class="main-card-body">

                        <!-- Info notice -->
                        <div class="info-box">
                            <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0;margin-top:.1rem;"></i>
                            <span>Slots created here are automatically set to <strong>Approved</strong> status. Patient name, category, phone, and reason are optional — you can create a blank slot to reserve time.</span>
                        </div>

                        <form id="slotForm" action="/appointments/set-appointment.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <div class="row g-3">

                                <!-- ── Section: Schedule ── -->
                                <div class="col-12">
                                    <div class="form-section-label">
                                        <i data-lucide="calendar" style="width:11px;height:11px;"></i>
                                        Schedule
                                    </div>
                                </div>

                                <!-- Appointee -->
                                <div class="col-md-6">
                                    <label for="appointee" class="form-label">
                                        Appointee <span class="req">*</span>
                                    </label>
                                    <div class="field-wrap has-icon">
                                        <select name="appointee" id="appointee" class="form-select" required>
                                            <option value="">— Select Appointee —</option>
                                            <option value="Doctor">🩺 Doctor</option>
                                            <option value="Nurse">💊 Nurse</option>
                                            <option value="Dentist">🦷 Dentist</option>
                                        </select>
                                        <i data-lucide="stethoscope" class="field-icon"></i>
                                    </div>
                                </div>

                                <!-- Date -->
                                <div class="col-md-6">
                                    <label for="appointment_date" class="form-label">
                                        Appointment Date <span class="req">*</span>
                                    </label>
                                    <div class="field-wrap has-icon" style="position:relative;">
                                        <input type="text" name="appointment_date" id="appointment_date"
                                               class="form-control flatpickr" placeholder="Select Date" required readonly>
                                        <i data-lucide="calendar" class="field-icon"></i>
                                    </div>
                                </div>

                                <!-- Start Time -->
                                <div class="col-md-6">
                                    <label for="start_time" class="form-label">
                                        Start Time <span class="req">*</span>
                                    </label>
                                    <div class="field-wrap has-icon" style="position:relative;">
                                        <select name="start_time" id="start_time" class="form-select" required>
                                            <option value="">— Select Start Time —</option>
                                            <?php for ($h = 7; $h <= 16; $h++): ?>
                                                <?php $time = sprintf("%02d:00:00", $h); ?>
                                                <option value="<?= $time ?>"><?= date("h:i A", strtotime($time)) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <i data-lucide="clock" class="field-icon"></i>
                                    </div>
                                </div>

                                <!-- End time info -->
                                <div class="col-md-6 d-flex align-items-end">
                                    <div style="background:hsl(210 40% 96%);border:1px solid var(--border);border-radius:.65rem;padding:.6rem .9rem;width:100%;font-size:.8rem;color:var(--text-secondary);display:flex;align-items:center;gap:.5rem;">
                                        <i data-lucide="timer" style="width:14px;height:14px;color:var(--primary);flex-shrink:0;"></i>
                                        End time is automatically set <strong style="color:var(--foreground);margin-left:.25rem;">30 minutes</strong>&nbsp;after start
                                    </div>
                                </div>

                                <!-- ── Section: Patient (Optional) ── -->
                                <div class="col-12" style="margin-top:.25rem;">
                                    <div class="form-section-label">
                                        <i data-lucide="user" style="width:11px;height:11px;"></i>
                                        Patient Details <span style="font-weight:400;opacity:.7;margin-left:.3rem;">(optional)</span>
                                    </div>
                                </div>

                                <!-- Name -->
                                <div class="col-md-6">
                                    <label for="patient_name" class="form-label">Full Name</label>
                                    <div class="field-wrap has-icon">
                                        <input type="text" name="patient_name" id="patient_name"
                                               class="form-control" placeholder="Enter Full Name">
                                        <i data-lucide="user" class="field-icon"></i>
                                    </div>
                                </div>

                                <!-- Category -->
                                <div class="col-md-6">
                                    <label for="category" class="form-label">Category</label>
                                    <select name="category" id="category" class="form-select">
                                        <option value="">— Select Category —</option>
                                        <optgroup label="🎒 Students">
                                            <option value="Pre School">Pre School</option>
                                            <option value="Elementary">Elementary</option>
                                            <option value="JHS">Junior High School (JHS)</option>
                                            <option value="SHS">Senior High School (SHS)</option>
                                            <option value="College">College</option>
                                            <option value="Alumni">Alumni</option>
                                        </optgroup>
                                        <optgroup label="👩‍🏫 Teaching Staff">
                                            <option value="Teacher">Teacher</option>
                                            <option value="Faculty">Faculty</option>
                                            <option value="Faculty Staff">Faculty Staff</option>
                                        </optgroup>
                                        <optgroup label="🏢 Administrative Staff">
                                            <option value="Administrative Staff">Administrative Staff</option>
                                            <option value="Clinic Staff">Clinic Staff</option>
                                            <option value="Support Staff">Support Staff</option>
                                            <option value="Non-Teaching">Non-Teaching</option>
                                            <option value="Non-Teaching Staff">Non-Teaching Staff</option>
                                        </optgroup>
                                        <optgroup label="🔧 Facilities &amp; Operations">
                                            <option value="Maintenance Staff">Maintenance Staff</option>
                                            <option value="Janitorial Staff">Janitorial Staff</option>
                                            <option value="Security Staff">Security Staff</option>
                                        </optgroup>
                                        <optgroup label="👤 Other">
                                            <option value="Non-Student">Non-Student</option>
                                            <option value="Staff">Staff</option>
                                            <option value="Visitor">Visitor</option>
                                            <option value="Other">Other</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <!-- Phone -->
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <div class="field-wrap has-icon">
                                        <input type="tel" name="phone" id="phone"
                                               class="form-control" placeholder="09XXXXXXXXX">
                                        <i data-lucide="phone" class="field-icon"></i>
                                    </div>
                                </div>

                                <!-- Reason -->
                                <div class="col-12">
                                    <label for="reason" class="form-label">Reason / Notes</label>
                                    <textarea name="reason" id="reason" class="form-control"
                                              placeholder="Enter reason for slot or any notes…" rows="4"></textarea>
                                </div>

                                <!-- Submit -->
                                <div class="col-12" style="margin-top:.25rem;">
                                    <button type="submit" id="submitBtn" class="btn btn-submit">
                                        <i data-lucide="calendar-check" style="width:15px;height:15px;"></i>
                                        Create Slot
                                    </button>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </main>

        <footer>
            <i data-lucide="hospital" style="width:12px;height:12px;display:inline-block;vertical-align:middle;margin-right:4px;"></i>
            IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. © SSCMS 2025
        </footer>
    </div>

    <!-- Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="formToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        lucide.createIcons();

        document.addEventListener('DOMContentLoaded', function() {
            console.log('[SSCMS Set Appointment] Initialized');

            // Flatpickr
            flatpickr("#appointment_date", {
                dateFormat: "Y-m-d",
                minDate: "today",
                maxDate: new Date().setDate(new Date().getDate() + 30),
                disable: [d => d.getDay() === 0 || d.getDay() === 6]
            });

            // Toast helper
            const toastEl   = document.getElementById('formToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast     = new bootstrap.Toast(toastEl, { delay: 5000 });

            function showToast(msg, type = 'error') {
                toastEl.classList.remove('success', 'error');
                toastEl.classList.add(type);
                toastBody.textContent = msg;
                toast.show();
            }

            // Form submission — identical logic to original
            const form = document.getElementById('slotForm');

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    showToast('Please fill in all required fields correctly.', 'error');
                    return;
                }

                const phone = document.getElementById('phone').value;
                if (phone && !/^\d{10,11}$/.test(phone)) {
                    showToast('Phone number must be 10 or 11 digits.', 'error');
                    return;
                }

                const btn  = document.getElementById('submitBtn');
                const orig = btn.innerHTML;
                btn.innerHTML = '<svg style="width:14px;height:14px;animation:spin .7s linear infinite;display:inline-block;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg> Creating…';
                btn.disabled = true;

                $.ajax({
                    url: form.action,
                    method: form.method,
                    data: $(form).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        console.log('[SSCMS Set Appointment] AJAX Success:', response);
                        btn.innerHTML = orig;
                        btn.disabled  = false;
                        lucide.createIcons();
                        if (response.success) {
                            showToast('Appointment slot created successfully!', 'success');
                            form.reset();
                            form.classList.remove('was-validated');
                            const fp = document.getElementById('appointment_date')._flatpickr;
                            if (fp) fp.clear();
                            setTimeout(() => {
                                window.location.href = '/appointments/appointment-list.php';
                            }, 1500);
                        } else {
                            showToast(response.message || 'Failed to create slot.', 'error');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('[SSCMS Set Appointment] AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                        btn.innerHTML = orig;
                        btn.disabled  = false;
                        lucide.createIcons();
                        let msg = 'Server error. Please try again.';
                        try {
                            const parsed = JSON.parse(jqXHR.responseText);
                            if (parsed?.message) msg = parsed.message;
                        } catch (_) {
                            if (jqXHR.responseText) msg = jqXHR.responseText.substring(0, 120).replace(/<[^>]+>/g,'').trim();
                        }
                        showToast(msg, 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>