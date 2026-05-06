<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Monthly Reports] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Initialize variables
$filter_month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?? '';
$filter_year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => date('Y')]]) ?? date('Y');
$month_name = empty($filter_month) ? 'All Months' : date('F', mktime(0, 0, 0, $filter_month, 1));
$error_message = '';
$success_message = '';

// Query for total visits per month
try {
    $query_total_visits = "
        SELECT DATE_FORMAT(v.visit_date, '%M') AS month_name, COUNT(*) AS total_visits
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_total_visits .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_total_visits .= " GROUP BY month_name ORDER BY STR_TO_DATE(month_name, '%M') ASC";
    $stmt_total_visits = $conn->prepare($query_total_visits);
    $stmt_total_visits->execute($params);
    $result_total_visits = $stmt_total_visits->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch total visits: " . $e->getMessage();
    error_log("[SSCMS Monthly Reports] Error (Total Visits): " . $e->getMessage());
    $result_total_visits = [];
}

// Query for top reasons (split symptoms individually)
try {
    $query_top_reasons = "
        SELECT TRIM(single_reason) AS reason, COUNT(*) AS count
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN visit_reasons vr ON v.id = vr.visit_id
        LEFT JOIN (
            SELECT visit_id, TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(reason, ', ', n.n), ', ', -1)) AS single_reason
            FROM visit_reasons
            CROSS JOIN (
                SELECT a.N + b.N * 10 + 1 AS n
                FROM 
                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                ORDER BY n
            ) n
            WHERE n.n <= LENGTH(reason) - LENGTH(REPLACE(reason, ',', '')) + 1
            UNION
            SELECT v.id AS visit_id, TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(COALESCE(v.reason, 'Unknown'), ', ', n.n), ', ', -1)) AS single_reason
            FROM visits v
            CROSS JOIN (
                SELECT a.N + b.N * 10 + 1 AS n
                FROM 
                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                ORDER BY n
            ) n
            WHERE n.n <= LENGTH(COALESCE(v.reason, 'Unknown')) - LENGTH(REPLACE(COALESCE(v.reason, 'Unknown'), ',', '')) + 1
        ) AS reasons ON v.id = reasons.visit_id
        WHERE YEAR(v.visit_date) = ? AND single_reason != ''
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_top_reasons .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_top_reasons .= " GROUP BY single_reason ORDER BY count DESC LIMIT 5";
    $stmt_top_reasons = $conn->prepare($query_top_reasons);
    $stmt_top_reasons->execute($params);
    $result_top_reasons = $stmt_top_reasons->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch top reasons: " . $e->getMessage();
    error_log("[SSCMS Monthly Reports] Error (Top Reasons): " . $e->getMessage());
    $result_top_reasons = [];
}

// Query for category-based analytics
try {
    $query_categories = "
        SELECT p.category, COUNT(*) AS count
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_categories .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_categories .= " GROUP BY p.category ORDER BY count DESC LIMIT 5";
    $stmt_categories = $conn->prepare($query_categories);
    $stmt_categories->execute($params);
    $result_categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch categories: " . $e->getMessage();
    error_log("[SSCMS Monthly Reports] Error (Categories): " . $e->getMessage());
    $result_categories = [];
}

// Query for top patients by visit frequency (Top 10)
try {
    $query_top_patients = "
        SELECT CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.category, COUNT(*) AS count
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_top_patients .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_top_patients .= " GROUP BY p.id, p.first_name, p.last_name, p.category ORDER BY count DESC LIMIT 10";
    $stmt_top_patients = $conn->prepare($query_top_patients);
    $stmt_top_patients->execute($params);
    $result_top_patients = $stmt_top_patients->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch top patients: " . $e->getMessage();
    error_log("[SSCMS Monthly Reports] Error (Top Patients): " . $e->getMessage());
    $result_top_patients = [];
}

// Query for most used medicines
try {
    $query_medicines = "
        SELECT m.name AS medicine_name, SUM(vm.quantity) AS total_quantity
        FROM visit_medicines vm
        JOIN medicines m ON vm.medicine_id = m.id
        JOIN visits v ON vm.visit_id = v.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_medicines .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_medicines .= " GROUP BY m.id, m.name ORDER BY total_quantity DESC LIMIT 5";
    $stmt_medicines = $conn->prepare($query_medicines);
    $stmt_medicines->execute($params);
    $result_medicines = $stmt_medicines->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch most used medicines: " . $e->getMessage();
    error_log("[SSCMS Monthly Reports] Error (Most Used Medicines): " . $e->getMessage());
    $result_medicines = [];
}

// Query for severity distribution
try {
    $query_severity = "
        SELECT v.severity, COUNT(*) AS count
        FROM visits v
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_severity .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_severity .= " GROUP BY v.severity ORDER BY FIELD(v.severity, 'Severe', 'Moderate', 'Mild')";
    $stmt_severity = $conn->prepare($query_severity);
    $stmt_severity->execute($params);
    $result_severity = $stmt_severity->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch severity distribution: " . $e->getMessage();
    error_log("[SSCMS Monthly Reports] Error (Severity Distribution): " . $e->getMessage());
    $result_severity = [];
}

// Query for specialist visits
try {
    $query_specialist = "
        SELECT u.admin_category, COUNT(*) AS count
        FROM specialist_visits sv
        JOIN users u ON sv.user_id = u.id
        WHERE YEAR(sv.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_specialist .= " AND MONTH(sv.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_specialist .= " GROUP BY u.admin_category ORDER BY count DESC";
    $stmt_specialist = $conn->prepare($query_specialist);
    $stmt_specialist->execute($params);
    $result_specialist = $stmt_specialist->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch specialist visits: " . $e->getMessage();
    error_log("[SSCMS Monthly Reports] Error (Specialist Visits): " . $e->getMessage());
    $result_specialist = [];
}

// Query for medicine stock alerts
try {
    $query_stock_alerts = "
        SELECT name AS medicine_name, quantity
        FROM medicines
        WHERE quantity < 10
        ORDER BY quantity ASC
        LIMIT 5";
    $stmt_stock_alerts = $conn->prepare($query_stock_alerts);
    $stmt_stock_alerts->execute();
    $result_stock_alerts = $stmt_stock_alerts->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch stock alerts: " . $e->getMessage();
    error_log("[SSCMS Monthly Reports] Error (Stock Alerts): " . $e->getMessage());
    $result_stock_alerts = [];
}

// Query for appointment status summary
try {
    $query_appointments = "
        SELECT status, COUNT(*) AS count
        FROM appointments
        WHERE YEAR(appointment_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_appointments .= " AND MONTH(appointment_date) = ?";
        $params[] = $filter_month;
    }
    $query_appointments .= " GROUP BY status ORDER BY FIELD(status, 'pending', 'approved', 'rejected')";
    $stmt_appointments = $conn->prepare($query_appointments);
    $stmt_appointments->execute($params);
    $result_appointments = $stmt_appointments->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch appointment status: " . $e->getMessage();
    error_log("[SSCMS Monthly Reports] Error (Appointment Status): " . $e->getMessage());
    $result_appointments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Monthly Reports">
    <meta name="author" content="ICCB">
    <title>Monthly Reports - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <?php include '../includes/sscmslogo.php'; ?>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary: #0f73ba;
            --primary-light: #e0f2fe;
            --primary-dark: #0d5a94;
            --secondary: #2c7be5;
            --text-primary: #1a202c;
            --text-secondary: #4a5568;
            --border: #d1d5db;
            --background: #f8fafc;
            --card-bg: #ffffff;
            --success: #059669;
            --success-dark: #047857;
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
            --radius: 10px;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --transition-speed: 0.3s;
        }

        [data-theme="dark"] {
            --primary: #60a5fa;
            --primary-light: #1e3a8a;
            --primary-dark: #3b82f6;
            --secondary: #9ca3af;
            --text-primary: #f3f4f6;
            --text-secondary: #d1d5db;
            --border: #4b5563;
            --background: #111827;
            --card-bg: #1f2937;
            --success: #10b981;
            --success-dark: #0a8f6c;
        }

        html, body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.5;
            font-size: 0.9rem;
            margin: 0;
            box-sizing: border-box;
        }

        *, *:before, *:after {
            box-sizing: inherit;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1.5rem 2.5rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container-fluid {
            max-width: 1200px;
            padding: 0 0.75rem;
            margin: 0 auto;
        }

        .report-header {
            text-align: center;
            padding: 0.75rem;
            background: var(--card-bg);
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .report-header img {
            max-width: 100%;
            width: 500px;
            height: auto;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }

        .report-header h2 {
            color: var(--primary);
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0.5rem 0 0.25rem;
        }

        .report-header p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin: 0;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.75rem;
            background: var(--card-bg);
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .dashboard-title {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dashboard-title i {
            color: var(--primary);
            font-size: 0.9rem;
        }

        .filter-form {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-select, .form-control {
            border-radius: 5px;
            border: 1px solid var(--border);
            padding: 0.3rem 0.75rem;
            font-size: 0.85rem;
            height: 32px;
            background: var(--card-bg);
            transition: border-color 0.2s ease;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }

        .btn {
            border-radius: 5px;
            padding: 0.3rem 0.75rem;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: background-color 0.2s ease;
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            border: none;
            color: white;
        }

        .btn-success:hover {
            background: var(--success-dark);
        }

        .btn-outline-secondary {
            border-color: var(--border);
            color: var(--text-secondary);
        }

        .btn-outline-secondary:hover {
            background: var(--primary-light);
        }

        .download-btn {
            margin-bottom: 0.5rem;
        }

        .custom-breadcrumb {
            padding: 0.5rem 0.75rem;
            background: var(--card-bg);
            border-radius: 6px;
            font-size: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .custom-breadcrumb .breadcrumb-item a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .custom-breadcrumb .breadcrumb-item a:hover {
            color: var(--primary);
        }

        .custom-breadcrumb .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 500;
        }

        .summary-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .summary-card h4 {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .summary-card .stat {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .summary-card .label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .summary-card i {
            font-size: 0.9rem;
            color: var(--primary);
        }

        .card {
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border);
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }

        .card-header {
            background: var(--primary);
            color: white;
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            font-size: 0.9rem;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 0.75rem;
        }

        .table-section {
            margin-bottom: 1rem;
        }

        .table {
            color: var(--text-primary);
            font-size: 0.8rem;
            margin-bottom: 0;
            border-collapse: collapse;
            width: 100%;
        }

        .table th, .table td {
            padding: 0.5rem;
            border: 1px solid var(--border);
            vertical-align: middle;
            text-align: left;
        }

        .table th {
            font-weight: 500;
            background: var(--primary-light);
            color: var(--text-primary);
        }

        .table tbody tr:hover {
            background: var(--primary-light);
        }

        .table-responsive {
            border-radius: 6px;
            overflow-x: auto;
            border: 1px solid var(--border);
            max-height: 300px;
            overflow-y: auto;
        }

        .toast-container {
            position: fixed;
            top: 0.75rem;
            right: 0.75rem;
            z-index: 1055;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 0.75rem;
            font-size: 0.85rem;
            color: var(--primary);
        }

        .loading-spinner i {
            font-size: 1rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }

        .clinic-footer {
            margin-top: 1rem;
            padding: 0.75rem 0;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
            border-top: 1px solid var(--border);
        }

        /* ── Page Hero ── */
        .page-hero {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 60%, var(--secondary) 100%);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 4px 15px rgba(15,115,186,.25);
        }

        .hero-content .hero-title {
            color: #fff;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: .15rem;
        }

        .hero-content .hero-subtitle {
            color: rgba(255,255,255,.78);
            font-size: .82rem;
        }

        .hero-filter-form {
            display: flex;
            align-items: flex-end;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .hero-filter-form label {
            color: rgba(255,255,255,.85);
            font-size: .75rem;
            font-weight: 500;
            margin-bottom: .2rem;
        }

        .hero-filter-form .form-select,
        .hero-filter-form .form-control {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.35);
            color: #fff;
            font-size: .82rem;
            height: 34px;
            border-radius: 6px;
        }

        .hero-filter-form .form-select option { color: #1a202c; background: #fff; }

        .hero-filter-form .form-select:focus,
        .hero-filter-form .form-control:focus {
            background: rgba(255,255,255,.25);
            border-color: rgba(255,255,255,.7);
            box-shadow: none;
            color: #fff;
        }

        .btn-hero-filter {
            background: rgba(255,255,255,.2);
            border: 1px solid rgba(255,255,255,.5);
            color: #fff;
            font-size: .82rem;
            font-weight: 600;
            padding: .4rem .9rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            cursor: pointer;
            transition: background .2s;
            height: 34px;
        }

        .btn-hero-filter:hover { background: rgba(255,255,255,.32); color: #fff; }

        .btn-hero-reset {
            background: transparent;
            border: 1px solid rgba(255,255,255,.35);
            color: rgba(255,255,255,.8);
            font-size: .82rem;
            padding: .4rem .9rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            text-decoration: none;
            height: 34px;
            transition: background .2s;
        }

        .btn-hero-reset:hover { background: rgba(255,255,255,.15); color: #fff; }

        .btn-hero-print {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.4);
            color: #fff;
            font-size: .82rem;
            font-weight: 600;
            padding: .4rem .9rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            cursor: pointer;
            transition: background .2s;
            height: 34px;
        }

        .btn-hero-print:hover { background: rgba(255,255,255,.28); color: #fff; }

        /* ── Summary stat cards ── */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: .9rem 1rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .stat-card .stat-icon {
            width: 38px; height: 38px;
            border-radius: 8px;
            background: var(--primary-light);
            display: flex; align-items: center; justify-content: center;
            color: var(--primary);
            font-size: .95rem;
            flex-shrink: 0;
        }
        .stat-card .stat-value { font-size: 1.15rem; font-weight: 700; color: var(--primary); line-height: 1.2; }
        .stat-card .stat-label { font-size: .75rem; color: var(--text-secondary); }

        /* ── Section headings ── */
        .section-heading {
            font-size: .85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: .5rem;
            padding-bottom: .35rem;
            border-bottom: 2px solid var(--primary-light);
        }

        /* ── Print ── */
        @media print {
            @page { margin: 10mm 12mm; size: A4 portrait; }
            .page-hero, .no-print, .toast-container { display: none !important; }
            .content { margin-left: 0 !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #ccc !important; }
            .table th, .table td { white-space: normal; word-break: break-word; }
        }

        /* ── Dark Mode ── */
        [data-theme="dark"] body              { background: hsl(222,47%,9%) !important; color: hsl(210,40%,96%) !important; }
        [data-theme="dark"] .card             { background: hsl(222,47%,13%) !important; border-color: hsl(222,30%,22%) !important; }
        [data-theme="dark"] .card-header      { background: linear-gradient(135deg, hsl(201,85%,22%), hsl(201,85%,30%)) !important; }
        [data-theme="dark"] .card-body        { background: hsl(222,47%,13%) !important; }
        [data-theme="dark"] .stat-card        { background: hsl(222,47%,15%) !important; border-color: hsl(222,30%,25%) !important; }
        [data-theme="dark"] .stat-card .stat-icon { background: hsl(222,47%,20%) !important; }
        [data-theme="dark"] .table           { color: hsl(210,40%,90%) !important; }
        [data-theme="dark"] .table th        { background: hsl(222,47%,18%) !important; color: hsl(210,40%,96%) !important; border-color: hsl(222,30%,28%) !important; }
        [data-theme="dark"] .table td        { border-color: hsl(222,30%,25%) !important; }
        [data-theme="dark"] .table tbody tr:hover td { background: hsl(222,47%,16%) !important; }
        [data-theme="dark"] .section-heading { border-color: hsl(222,30%,28%) !important; color: hsl(210,40%,96%) !important; }
        [data-theme="dark"] .page-hero        { box-shadow: 0 4px 20px rgba(0,0,0,.4); }
        [data-theme="dark"] .hero-filter-form .form-select,
        [data-theme="dark"] .hero-filter-form .form-control { background: rgba(255,255,255,.08) !important; }

        @media (max-width: 992px) {
            .content {
                margin-left: var(--sidebar-collapsed-width);
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
            }
            .report-header img {
                width: 300px;
            }
            .report-header h2 {
                font-size: 1.1rem;
            }
            .dashboard-title {
                font-size: 1rem;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .form-group {
                width: 100%;
            }
            .summary-card .stat {
                font-size: 1rem;
            }
            .table {
                font-size: 0.75rem;
            }
            .btn, .form-select, .form-control {
                font-size: 0.8rem;
                padding: 0.3rem 0.5rem;
                height: 30px;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .download-btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .report-header img {
                width: 200px;
            }
            .dashboard-title {
                font-size: 0.9rem;
            }
            .summary-card .stat {
                font-size: 0.9rem;
            }
            .download-btn {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Page Hero -->
                <div class="page-hero no-print">
                    <div class="hero-content">
                        <div class="hero-title"><i class="fas fa-chart-bar me-2"></i>Monthly Report</div>
                        <div class="hero-subtitle">
                            Analytics summary for <?= htmlspecialchars($month_name) ?> <?= htmlspecialchars($filter_year) ?>
                        </div>
                    </div>
                    <div class="d-flex align-items-end gap-2 flex-wrap">
                        <form method="GET" class="hero-filter-form" aria-label="Filter report data">
                            <div class="form-group">
                                <label for="monthFilter">Month</label>
                                <select name="month" id="monthFilter" class="form-select">
                                    <option value="">All Months</option>
                                    <?php
                                    for ($m = 1; $m <= 12; $m++) {
                                        $selected = ($filter_month == $m) ? 'selected' : '';
                                        echo "<option value=\"$m\" $selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="yearFilter">Year</label>
                                <input type="number" name="year" id="yearFilter" class="form-control" value="<?= htmlspecialchars($filter_year) ?>" min="2000" max="<?= date('Y') ?>" style="width:90px;">
                            </div>
                            <button type="submit" class="btn-hero-filter"><i class="fas fa-filter me-1"></i> Filter</button>
                            <a href="monthly-reports.php" class="btn-hero-reset"><i class="fas fa-undo me-1"></i> Reset</a>
                        </form>
                        <button class="btn-hero-print" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
                    </div>
                </div>

                <!-- Toast Container -->
                <div class="toast-container">
                    <?php if ($error_message): ?>
                        <div class="toast align-items-center text-bg-danger border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($error_message) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($success_message): ?>
                        <div class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($success_message) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Summary Stats -->
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div>
                            <div class="stat-value"><?= array_sum(array_column($result_total_visits, 'total_visits')) ?></div>
                            <div class="stat-label">Total Visits</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="stat-value"><?= count($result_top_patients) ? $result_top_patients[0]['count'] : 0 ?></div>
                            <div class="stat-label">Highest Patient Visits</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
                        <div>
                            <div class="stat-value" style="font-size:.85rem;"><?= count($result_top_reasons) ? htmlspecialchars($result_top_reasons[0]['reason']) : 'N/A' ?></div>
                            <div class="stat-label">Top Visit Reason</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-capsules"></i></div>
                        <div>
                            <div class="stat-value" style="font-size:.85rem;"><?= count($result_medicines) ? htmlspecialchars($result_medicines[0]['medicine_name']) : 'N/A' ?></div>
                            <div class="stat-label">Most Used Medicine</div>
                        </div>
                    </div>
                </div>

                <!-- Main Report Card -->
                <div class="card" aria-labelledby="analyticsTitle">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color:#fff; border-radius: var(--radius) var(--radius) 0 0;">
                        <span id="analyticsTitle"><i class="fas fa-table me-2"></i> Detailed Analytics</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-white text-primary" style="font-size:.75rem;"><?= htmlspecialchars($month_name) ?> <?= htmlspecialchars($filter_year) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Loading Spinner -->
                        <div class="loading-spinner" id="loadingSpinner">
                            <i class="fas fa-spinner"></i> Loading data...
                        </div>

                        <!-- Tables Section -->
                        <div class="table-section">
                            <h5 class="section-heading"><i class="fas fa-list-ul me-1 text-primary"></i> Top Reasons for Visits</h5>
                            <button type="button" class="btn btn-success btn-sm download-btn" onclick="exportToExcel('top_reasons')" aria-label="Download Top Reasons for Visits as Excel"><i class="fas fa-file-excel me-1"></i> Download Excel</button>
                            <?php if (empty($result_top_reasons)): ?>
                                <p class="text-muted">No data available.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table" aria-label="Top reasons for visits">
                                        <thead>
                                            <tr>
                                                <th>Reason</th>
                                                <th>Visits</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result_top_reasons as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['reason']) ?></td>
                                                    <td><?= htmlspecialchars($row['count']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="table-section">
                            <h5 class="section-heading"><i class="fas fa-users me-1 text-primary"></i> Visits by Category</h5>
                            <button type="button" class="btn btn-success btn-sm download-btn" onclick="exportToExcel('categories')" aria-label="Download Visits by Category as Excel"><i class="fas fa-file-excel me-1"></i> Download Excel</button>
                            <?php if (empty($result_categories)): ?>
                                <p class="text-muted">No data available.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table" aria-label="Visits by category">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Visits</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result_categories as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['category']) ?></td>
                                                    <td><?= htmlspecialchars($row['count']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="table-section">
                            <h5 class="section-heading"><i class="fas fa-user-clock me-1 text-primary"></i> Top 10 Patients by Visits</h5>
                            <button type="button" class="btn btn-success btn-sm download-btn" onclick="exportToExcel('top_patients')" aria-label="Download Top Patients by Visits as Excel"><i class="fas fa-file-excel me-1"></i> Download Excel</button>
                            <?php if (empty($result_top_patients)): ?>
                                <p class="text-muted">No data available.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table" aria-label="Top patients by visits">
                                        <thead>
                                            <tr>
                                                <th>Patient Name</th>
                                                <th>Category</th>
                                                <th>Visits</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result_top_patients as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['patient_name']) ?></td>
                                                    <td><?= htmlspecialchars($row['category']) ?></td>
                                                    <td><?= htmlspecialchars($row['count']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="table-section">
                            <h5 class="section-heading"><i class="fas fa-capsules me-1 text-primary"></i> Most Used Medicines</h5>
                            <button type="button" class="btn btn-success btn-sm download-btn" onclick="exportToExcel('most_used_medicines')" aria-label="Download Most Used Medicines as Excel"><i class="fas fa-file-excel me-1"></i> Download Excel</button>
                            <?php if (empty($result_medicines)): ?>
                                <p class="text-muted">No data available.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table" aria-label="Most used medicines">
                                        <thead>
                                            <tr>
                                                <th>Medicine Name</th>
                                                <th>Total Quantity Used</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result_medicines as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                                                    <td><?= htmlspecialchars($row['total_quantity']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="table-section">
                            <h5 class="section-heading"><i class="fas fa-exclamation-triangle me-1 text-primary"></i> Severity Distribution</h5>
                            <button type="button" class="btn btn-success btn-sm download-btn" onclick="exportToExcel('severity_distribution')" aria-label="Download Severity Distribution as Excel"><i class="fas fa-file-excel me-1"></i> Download Excel</button>
                            <?php if (empty($result_severity)): ?>
                                <p class="text-muted">No data available.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table" aria-label="Severity distribution">
                                        <thead>
                                            <tr>
                                                <th>Severity</th>
                                                <th>Visits</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result_severity as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['severity']) ?></td>
                                                    <td><?= htmlspecialchars($row['count']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="table-section">
                            <h5 class="section-heading"><i class="fas fa-user-md me-1 text-primary"></i> Specialist Visits</h5>
                            <button type="button" class="btn btn-success btn-sm download-btn" onclick="exportToExcel('specialist_visits')" aria-label="Download Specialist Visits as Excel"><i class="fas fa-file-excel me-1"></i> Download Excel</button>
                            <?php if (empty($result_specialist)): ?>
                                <p class="text-muted">No data available.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table" aria-label="Specialist visits">
                                        <thead>
                                            <tr>
                                                <th>Specialist Role</th>
                                                <th>Visits</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result_specialist as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['admin_category']) ?></td>
                                                    <td><?= htmlspecialchars($row['count']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="table-section">
                            <h5 class="section-heading"><i class="fas fa-boxes me-1 text-warning"></i> Medicine Stock Alerts <span class="badge bg-warning text-dark ms-1" style="font-size:.7rem;">Low Stock</span></h5>
                            <button type="button" class="btn btn-success btn-sm download-btn" onclick="exportToExcel('stock_alerts')" aria-label="Download Medicine Stock Alerts as Excel"><i class="fas fa-file-excel me-1"></i> Download Excel</button>
                            <?php if (empty($result_stock_alerts)): ?>
                                <p class="text-muted">No low stock medicines.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table" aria-label="Medicine stock alerts">
                                        <thead>
                                            <tr>
                                                <th>Medicine Name</th>
                                                <th>Current Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result_stock_alerts as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                                                    <td><?= htmlspecialchars($row['quantity']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="table-section">
                            <h5 class="section-heading"><i class="fas fa-calendar-alt me-1 text-primary"></i> Appointment Status Summary</h5>
                            <button type="button" class="btn btn-success btn-sm download-btn" onclick="exportToExcel('appointment_status')" aria-label="Download Appointment Status Summary as Excel"><i class="fas fa-file-excel me-1"></i> Download Excel</button>
                            <?php if (empty($result_appointments)): ?>
                                <p class="text-muted">No data available.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table" aria-label="Appointment status summary">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result_appointments as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(ucfirst($row['status'])) ?></td>
                                                    <td><?= htmlspecialchars($row['count']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="clinic-footer">
            <div class="container-fluid">
                <p class="mb-0">Clinic Management System © 2025</p>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToExcel(category) {
            let data, filename, headers;
            const reportTitle = 'Monthly Report - ' + '<?php echo htmlspecialchars($month_name); ?>' + ' ' + '<?php echo htmlspecialchars($filter_year); ?>';
            const currentDate = new Date().toISOString().split('T')[0];

            switch (category) {
                case 'top_reasons':
                    data = <?php echo json_encode($result_top_reasons); ?>;
                    filename = `Monthly_Report_Top_Reasons_${currentDate}.xlsx`;
                    headers = ['Reason', 'Visits'];
                    break;
                case 'categories':
                    data = <?php echo json_encode($result_categories); ?>;
                    filename = `Monthly_Report_Categories_${currentDate}.xlsx`;
                    headers = ['Category', 'Visits'];
                    break;
                case 'top_patients':
                    data = <?php echo json_encode($result_top_patients); ?>;
                    filename = `Monthly_Report_Top_Patients_${currentDate}.xlsx`;
                    headers = ['Patient Name', 'Category', 'Visits'];
                    break;
                case 'most_used_medicines':
                    data = <?php echo json_encode($result_medicines); ?>;
                    filename = `Monthly_Report_Most_Used_Medicines_${currentDate}.xlsx`;
                    headers = ['Medicine Name', 'Total Quantity Used'];
                    break;
                case 'severity_distribution':
                    data = <?php echo json_encode($result_severity); ?>;
                    filename = `Monthly_Report_Severity_Distribution_${currentDate}.xlsx`;
                    headers = ['Severity', 'Visits'];
                    break;
                case 'specialist_visits':
                    data = <?php echo json_encode($result_specialist); ?>;
                    filename = `Monthly_Report_Specialist_Visits_${currentDate}.xlsx`;
                    headers = ['Specialist Role', 'Visits'];
                    break;
                case 'stock_alerts':
                    data = <?php echo json_encode($result_stock_alerts); ?>;
                    filename = `Monthly_Report_Stock_Alerts_${currentDate}.xlsx`;
                    headers = ['Medicine Name', 'Current Quantity'];
                    break;
                case 'appointment_status':
                    data = <?php echo json_encode($result_appointments); ?>;
                    filename = `Monthly_Report_Appointment_Status_${currentDate}.xlsx`;
                    headers = ['Status', 'Count'];
                    break;
                default:
                    alert('Invalid category for export.');
                    return;
            }

            if (!data || data.length === 0) {
                alert('No data to export for ' + category.replace('_', ' ') + '.');
                return;
            }

            // Prepare Excel data
            const excelData = [
                { '': reportTitle }, // Title row
                { '': '' } // Empty row
            ];

            // Add headers
            const headerRow = {};
            headers.forEach((header, index) => {
                headerRow[String.fromCharCode(65 + index)] = header;
            });
            excelData.push(headerRow);

            // Add data rows
            data.forEach(row => {
                const dataRow = {};
                if (category === 'top_reasons') {
                    dataRow['A'] = row.reason || '-';
                    dataRow['B'] = row.count || '0';
                } else if (category === 'categories') {
                    dataRow['A'] = row.category || '-';
                    dataRow['B'] = row.count || '0';
                } else if (category === 'top_patients') {
                    dataRow['A'] = row.patient_name || '-';
                    dataRow['B'] = row.category || '-';
                    dataRow['C'] = row.count || '0';
                } else if (category === 'most_used_medicines') {
                    dataRow['A'] = row.medicine_name || '-';
                    dataRow['B'] = row.total_quantity || '0';
                } else if (category === 'severity_distribution') {
                    dataRow['A'] = row.severity || '-';
                    dataRow['B'] = row.count || '0';
                } else if (category === 'specialist_visits') {
                    dataRow['A'] = row.admin_category || '-';
                    dataRow['B'] = row.count || '0';
                } else if (category === 'stock_alerts') {
                    dataRow['A'] = row.medicine_name || '-';
                    dataRow['B'] = row.quantity || '0';
                } else if (category === 'appointment_status') {
                    dataRow['A'] = row.status ? ucfirst(row.status) : '-';
                    dataRow['B'] = row.count || '0';
                }
                excelData.push(dataRow);
            });

            // Create worksheet
            const ws = XLSX.utils.json_to_sheet(excelData, { skipHeader: true });

            // Apply styling
            const range = XLSX.utils.decode_range(ws['!ref']);
            for (let R = 0; R <= range.e.r; ++R) {
                for (let C = 0; C <= range.e.c; ++C) {
                    const cellAddress = XLSX.utils.encode_cell({ r: R, c: C });
                    if (!ws[cellAddress]) ws[cellAddress] = { t: 's', v: '' };

                    ws[cellAddress].s = {
                        border: {
                            top: { style: 'thin', color: { rgb: '000000' } },
                            bottom: { style: 'thin', color: { rgb: '000000' } },
                            left: { style: 'thin', color: { rgb: '000000' } },
                            right: { style: 'thin', color: { rgb: '000000' } }
                        }
                    };

                    if (R === 0) {
                        ws[cellAddress].s.font = { bold: true, sz: 14 };
                        ws[cellAddress].s.fill = { fgColor: { rgb: 'E6F3FA' } };
                    }

                    if (R === 2) {
                        ws[cellAddress].s.font = { bold: true };
                        ws[cellAddress].s.fill = { fgColor: { rgb: 'D3E3FD' } };
                    }
                }
            }

            // Set column widths
            ws['!cols'] = headers.map(() => ({ width: 20 }));

            // Merge title row
            ws['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: headers.length - 1 } }];

            // Create workbook and save
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, category.replace('_', ' ').toUpperCase());
            XLSX.writeFile(wb, filename);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
            document.querySelectorAll('.toast').forEach(toast => new bootstrap.Toast(toast).show());
        });

        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    </script>
</body>
</html>