<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Specialist Schedule] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission to schedule a visit
$errors = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_visit'])) {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
        error_log("[SSCMS Specialist Schedule] CSRF token validation failed");
    } else {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $visit_date = filter_input(INPUT_POST, 'visit_date', FILTER_SANITIZE_STRING);
        $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
        $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);

        // Validate inputs
        if (!$user_id) {
            $errors[] = 'Please select a specialist.';
        }
        if (!$visit_date || !strtotime($visit_date)) {
            $errors[] = 'Please select a valid date.';
        }
        if (!$start_time || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start_time)) {
            $errors[] = 'Please enter a valid start time (HH:MM, 24-hour format).';
        }
        if (!$end_time || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $end_time)) {
            $errors[] = 'Please enter a valid end time (HH:MM, 24-hour format).';
        }
        if ($start_time && $end_time && strtotime($start_time) >= strtotime($end_time)) {
            $errors[] = 'End time must be after start time.';
        }

        // Check if specialist exists and is Doctor/Dentist
        if ($user_id) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND admin_category IN ('Doctor', 'Dentist')");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                $errors[] = 'Invalid specialist selected.';
            }
        }

        if (empty($errors)) {
            try {
                // Insert visit schedule
                $stmt = $conn->prepare("
                    INSERT INTO specialist_visits (user_id, visit_date, start_time, end_time)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $visit_date, $start_time, $end_time]);
                $success = true;
                error_log("[SSCMS Specialist Schedule] Visit scheduled for user_id: $user_id on $visit_date");
            } catch (Exception $e) {
                $errors[] = 'Error scheduling visit: ' . $e->getMessage();
                error_log("[SSCMS Specialist Schedule] Database error: " . $e->getMessage());
            }
        }
    }
}

// Fetch Doctors and Dentists for the form
try {
    $stmt = $conn->prepare("SELECT id, name, admin_category FROM users WHERE admin_category IN ('Doctor', 'Dentist') ORDER BY name");
    $stmt->execute();
    $specialists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = 'Error fetching specialists: ' . $e->getMessage();
    error_log("[SSCMS Specialist Schedule] Specialists query error: " . $e->getMessage());
    $specialists = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Schedule Specialist Visit">
    <meta name="author" content="ICCB">
    <title>Schedule Specialist Visits - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>

    <style>
        :root {
            --primary: #0284c7;
            --primary-light: #e0f2fe;
            --primary-dark: #0369a1;
            --success: #059669;
            --success-light: #d1fae5;
            --warning: #d97706;
            --warning-light: #fef3c7;
            --danger: #dc2626;
            --danger-light: #fee2e2;
            --accent: #7c3aed;
            --accent-light: #ede9fe;
            --secondary: #6b7280;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
            --background: #f3f4f6;
            --card-bg: #ffffff;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --header-height: 70px;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition-speed: 0.3s;
            --gradient-primary: linear-gradient(135deg, #0284c7, #0369a1);
            --gradient-danger: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            font-size: 0.9rem;
            font-weight: 400;
            overflow-x: hidden;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1rem 1rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="%23f3f4f6"/><path d="M0 0L100 100M100 0L0 100" stroke="%23e5e7eb" stroke-width="0.1"/></svg>');
            background-size: 20px 20px;
        }

        .container-fluid {
            max-width: 1400px;
            padding: 0 1rem;
        }

        .schedule-card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            padding: 1rem;
            border: 1px solid var(--border);
            margin-bottom: 1rem;
        }

        .schedule-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            padding-left: 0.5rem;
            border-left: 4px solid var(--success);
            margin-bottom: 1rem;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control, .form-select {
            font-size: 0.85rem;
            padding: 0.5rem;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .alert {
            font-size: 0.85rem;
            padding: 0.75rem;
        }

        footer {
            background: var(--card-bg);
            border-top: 1px solid var(--border);
            padding: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 1rem;
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-up { animation: slideInUp 0.5s ease forwards; }

        @media (max-width: 992px) {
            :root { --sidebar-width: var(--sidebar-collapsed-width); }
            .content { margin-left: var(--sidebar-width); }
        }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 0.75rem; }
            .schedule-card { padding: 0.75rem; }
            .schedule-title { font-size: 1rem; }
            .form-control, .form-select { font-size: 0.8rem; }
            .btn-primary { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Schedule Form Card -->
                <div class="schedule-card slide-up">
                    <h3 class="schedule-title">
                        <i class="fas fa-user-md me-2"></i>Schedule Specialist Visit
                    </h3>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success">
                            Visit scheduled successfully! <a href="specialist_calendar.php" class="alert-link">View Calendar</a>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="user_id" class="form-label">Select Specialist</label>
                                <select id="user_id" name="user_id" class="form-select" required>
                                    <option value="">-- Select Specialist --</option>
                                    <?php foreach ($specialists as $specialist): ?>
                                        <option value="<?php echo $specialist['id']; ?>">
                                            <?php echo htmlspecialchars($specialist['name'] . ' (' . $specialist['admin_category'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="visit_date" class="form-label">Visit Date</label>
                                <input type="date" id="visit_date" name="visit_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" id="start_time" name="start_time" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" id="end_time" name="end_time" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" name="schedule_visit" class="btn btn-primary">
                            <i class="fas fa-calendar-check me-1"></i>Schedule Visit
                        </button>
                    </form>
                </div>
            </div>
        </main>

        <footer class="slide-up">
            <div class="container-fluid">
                <div>
                    <i class="fas fa-hospital me-1"></i>
                    IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. © SSCMS 2025
                </div>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>