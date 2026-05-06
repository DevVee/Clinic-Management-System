<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Graphs] Unauthorized access: no session");
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
    error_log("[SSCMS Graphs] Error (Total Visits): " . $e->getMessage());
    $result_total_visits = [];
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
    error_log("[SSCMS Graphs] Error (Categories): " . $e->getMessage());
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
    error_log("[SSCMS Graphs] Error (Top Patients): " . $e->getMessage());
    $result_top_patients = [];
}

// Query for visit reasons (split comma-separated reasons)
try {
    $query_reasons = "
        SELECT TRIM(single_reason) AS visit_reason, COUNT(*) AS count
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
            SELECT v.id AS visit_id, TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(COALESCE(IF(v.reason = 'Other', v.other_reason, v.reason), 'Unknown'), ', ', n.n), ', ', -1)) AS single_reason
            FROM visits v
            CROSS JOIN (
                SELECT a.N + b.N * 10 + 1 AS n
                FROM 
                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                ORDER BY n
            ) n
            WHERE n.n <= LENGTH(COALESCE(IF(v.reason = 'Other', v.other_reason, v.reason), 'Unknown')) - LENGTH(REPLACE(COALESCE(IF(v.reason = 'Other', v.other_reason, v.reason), 'Unknown'), ',', '')) + 1
        ) AS reasons ON v.id = reasons.visit_id
        WHERE YEAR(v.visit_date) = ? AND single_reason != ''
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_reasons .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $query_reasons .= " GROUP BY single_reason ORDER BY count DESC LIMIT 5";
    $stmt_reasons = $conn->prepare($query_reasons);
    $stmt_reasons->execute($params);
    $result_reasons = $stmt_reasons->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch visit reasons: " . $e->getMessage();
    error_log("[SSCMS Graphs] Error (Visit Reasons): " . $e->getMessage());
    $result_reasons = [];
}

// Query for summary stats
try {
    $query_summary = "
        SELECT COUNT(*) AS total_visits,
               (SELECT COUNT(DISTINCT TRIM(single_reason))
                FROM (
                    SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(reason, ', ', n.n), ', ', -1)) AS single_reason
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
                    SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(COALESCE(IF(v.reason = 'Other', v.other_reason, v.reason), 'Unknown'), ', ', n.n), ', ', -1)) AS single_reason
                    FROM visits v
                    CROSS JOIN (
                        SELECT a.N + b.N * 10 + 1 AS n
                        FROM 
                        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                        ORDER BY n
                    ) n
                    WHERE n.n <= LENGTH(COALESCE(IF(v.reason = 'Other', v.other_reason, v.reason), 'Unknown')) - LENGTH(REPLACE(COALESCE(IF(v.reason = 'Other', v.other_reason, v.reason), 'Unknown'), ',', '')) + 1
                ) AS reasons
                WHERE single_reason != '') AS unique_reasons
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE YEAR(v.visit_date) = ?
    ";
    $params = [$filter_year];
    if (!empty($filter_month)) {
        $query_summary .= " AND MONTH(v.visit_date) = ?";
        $params[] = $filter_month;
    }
    $stmt_summary = $conn->prepare($query_summary);
    $stmt_summary->execute($params);
    $summary_stats = $stmt_summary->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to fetch summary stats: " . $e->getMessage();
    error_log("[SSCMS Graphs] Error (Summary Stats): " . $e->getMessage());
    $summary_stats = ['total_visits' => 0, 'unique_reasons' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Graphical Analytics">
    <meta name="author" content="ICCB">
    <title>Graphical Analytics - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.51.0/dist/apexcharts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.5;
            font-size: 0.9rem;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1.5rem 2.5rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container-fluid {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 0.75rem;
        }

        .report-header {
            background: var(--card-bg);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .report-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .report-header p {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .dashboard-header {
            background: var(--card-bg);
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            border: 1px solid var(--border);
        }

        .dashboard-title {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
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
            min-width: 120px;
        }

        .form-group label {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.8rem;
        }

        .form-select, .form-control {
            border: 1px solid var(--border);
            border-radius: 5px;
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
            padding: 0.3rem 0.75rem;
            border-radius: 5px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline-secondary {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .btn-outline-secondary:hover {
            background: var(--primary-light);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: var(--success-dark);
        }

        .custom-breadcrumb {
            background: var(--card-bg);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            font-size: 0.8rem;
            border: 1px solid var(--border);
        }

        .breadcrumb-item a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: var(--primary);
        }

        .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 500;
        }

        .card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            padding: 0.75rem;
            border: 1px solid var(--border);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 6px 6px 0 0;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .chart-container {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .chart-container h5 {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container h5 i {
            color: var(--primary);
            font-size: 0.8rem;
        }

        .chart-wrapper {
            border-radius: 6px;
            padding: 0.5rem;
        }

        .patient-chart-container {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .patient-list {
            background: var(--card-bg);
            border-radius: 6px;
            padding: 0.5rem;
            font-size: 0.8rem;
            border: 1px solid var(--border);
        }

        .patient-list h6 {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .patient-list ul {
            list-style: none;
            padding: 0;
            max-height: 300px;
            overflow-y: auto;
        }

        .patient-list li {
            background: var(--card-bg);
            padding: 0.5rem;
            border-radius: 4px;
            margin-bottom: 0.4rem;
            border-left: 2px solid var(--primary);
        }

        .patient-name {
            font-weight: 600;
            font-size: 0.8rem;
        }

        .patient-details {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .visit-count {
            background: var(--primary);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            display: inline-block;
            margin-top: 0.25rem;
        }

        .download-btn {
            margin-top: 0.5rem;
            width: 100%;
            justify-content: center;
        }

        .no-data {
            text-align: center;
            padding: 1rem;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 1.2rem;
            color: var(--border);
            margin-bottom: 0.5rem;
        }

        .no-data h6 {
            font-size: 0.85rem;
            font-weight: 500;
        }

        .no-data p {
            font-size: 0.75rem;
        }

        .clinic-footer {
            margin-top: 1rem;
            padding: 0.75rem 0;
            text-align: center;
            color: var(--text-secondary);
            border-top: 1px solid var(--border);
            font-size: 0.8rem;
        }

        .toast-container {
            position: fixed;
            top: 0.75rem;
            right: 0.75rem;
            z-index: 1055;
        }

        .toast {
            border-radius: 6px;
            box-shadow: var(--shadow);
        }

        .apexcharts-tooltip {
            background: var(--card-bg) !important;
            border: 1px solid var(--border) !important;
            border-radius: 6px !important;
            font-size: 0.75rem !important;
        }

        .apexcharts-tooltip-title {
            background: var(--primary-light) !important;
            color: var(--text-primary) !important;
            font-size: 0.75rem !important;
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

        .btn-hero-filter:hover { background: rgba(255,255,255,.32); }

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

        /* ── Chart card polish ── */
        .chart-container {
            border-radius: var(--radius) !important;
        }

        .chart-container h5 {
            border-left: 3px solid var(--primary);
            padding-left: .5rem;
        }

        /* ── Summary stat pills ── */
        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 6px;
            padding: .3rem .75rem;
            font-size: .82rem;
            font-weight: 600;
        }

        /* ── Dark Mode ── */
        [data-theme="dark"] body            { background: hsl(222,47%,9%) !important; color: hsl(210,40%,96%) !important; }
        [data-theme="dark"] .card           { background: hsl(222,47%,13%) !important; border-color: hsl(222,30%,22%) !important; }
        [data-theme="dark"] .card-header    { background: linear-gradient(135deg, hsl(201,85%,22%), hsl(201,85%,30%)) !important; }
        [data-theme="dark"] .chart-container{ background: hsl(222,47%,13%) !important; border-color: hsl(222,30%,22%) !important; }
        [data-theme="dark"] .chart-container h5 { color: hsl(210,40%,90%) !important; border-color: hsl(201,85%,40%) !important; }
        [data-theme="dark"] .chart-title    { color: hsl(210,40%,90%) !important; border-color: hsl(222,30%,28%) !important; }
        [data-theme="dark"] .no-data        { color: hsl(215,20%,55%) !important; }
        [data-theme="dark"] .page-hero      { box-shadow: 0 4px 20px rgba(0,0,0,.4); }
        [data-theme="dark"] .patient-list   { background: hsl(222,47%,15%) !important; border-color: hsl(222,30%,25%) !important; }
        [data-theme="dark"] .patient-list li { border-color: hsl(222,30%,22%) !important; }
        [data-theme="dark"] .patient-name, [data-theme="dark"] .visit-count { color: hsl(210,40%,90%) !important; }
        [data-theme="dark"] .patient-details { color: hsl(215,20%,60%) !important; }
        [data-theme="dark"] .report-header  { background: hsl(222,47%,13%) !important; border-color: hsl(222,30%,22%) !important; }
        [data-theme="dark"] .custom-breadcrumb { background: hsl(222,47%,13%) !important; border-color: hsl(222,30%,22%) !important; }
        [data-theme="dark"] .breadcrumb-item a { color: hsl(215,20%,60%) !important; }

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

            .container-fluid {
                padding: 0 0.5rem;
            }

            .report-header h2 {
                font-size: 1.1rem;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .form-group {
                width: 100%;
            }

            .form-select, .form-control {
                font-size: 0.8rem;
                padding: 0.3rem 0.5rem;
                height: 30px;
            }

            .btn {
                font-size: 0.8rem;
                padding: 0.3rem 0.5rem;
            }

            .chart-container h5 {
                font-size: 0.85rem;
            }

            .chart-wrapper {
                padding: 0.25rem;
            }

            .patient-list ul {
                max-height: 200px;
            }

            .patient-list li {
                padding: 0.4rem;
            }
        }

        @media (max-width: 576px) {
            .dashboard-title {
                font-size: 0.9rem;
            }

            .dashboard-title i {
                font-size: 0.8rem;
            }

            .chart-container {
                padding: 0.5rem;
            }

            .patient-list h6 {
                font-size: 0.8rem;
            }

            .patient-list li {
                padding: 0.3rem;
            }

            .patient-name, .visit-count {
                font-size: 0.75rem;
            }

            .patient-details {
                font-size: 0.7rem;
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
                <div class="page-hero">
                    <div class="hero-content">
                        <div class="hero-title"><i class="fas fa-chart-line me-2"></i>Graphical Analytics</div>
                        <div class="hero-subtitle">
                            Visual data for <?= htmlspecialchars($month_name) ?> <?= htmlspecialchars($filter_year) ?>
                            &nbsp;·&nbsp;
                            <span style="color:rgba(255,255,255,.9);font-weight:600;"><?= htmlspecialchars($summary_stats['total_visits']) ?></span> visits
                            &nbsp;·&nbsp;
                            <span style="color:rgba(255,255,255,.9);font-weight:600;"><?= htmlspecialchars($summary_stats['unique_reasons']) ?></span> unique reasons
                        </div>
                    </div>
                    <form method="GET" class="hero-filter-form">
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
                        <button type="submit" class="btn-hero-filter"><i class="fas fa-filter me-1"></i> Apply</button>
                        <a href="graphs.php" class="btn-hero-reset"><i class="fas fa-undo me-1"></i> Reset</a>
                    </form>
                </div>

                <!-- Toast Messages -->
                <div class="toast-container">
                    <?php if ($error_message): ?>
                        <div class="toast align-items-center text-bg-danger border-0 show">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($error_message) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Total Visits Chart -->
                <div class="chart-container">
                    <h5><i class="fas fa-chart-bar"></i> Monthly Visits</h5>
                    <?php if (empty($result_total_visits)): ?>
                        <div class="no-data">
                            <i class="fas fa-chart-bar"></i>
                            <h6>No Data</h6>
                            <p>No visits recorded.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-wrapper">
                            <div id="totalVisitsChart" style="height: 300px;"></div>
                        </div>
                        <button class="btn btn-success download-btn" onclick="downloadChart('totalVisitsChart', 'Monthly_Visits')">
                            <i class="fas fa-download me-1"></i> Download Image
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Category Distribution Chart -->
                <div class="chart-container">
                    <h5><i class="fas fa-chart-pie"></i> Category Distribution</h5>
                    <?php if (empty($result_categories)): ?>
                        <div class="no-data">
                            <i class="fas fa-chart-pie"></i>
                            <h6>No Data</h6>
                            <p>No categories found.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-wrapper">
                            <div id="categoryChart" style="height: 300px;"></div>
                        </div>
                        <button class="btn btn-success download-btn" onclick="downloadChart('categoryChart', 'Category_Distribution')">
                            <i class="fas fa-download me-1"></i> Download Image
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Visit Reasons Chart -->
                <div class="chart-container">
                    <h5><i class="fas fa-clipboard-list"></i> Visit Reasons</h5>
                    <?php if (empty($result_reasons)): ?>
                        <div class="no-data">
                            <i class="fas fa-clipboard-list"></i>
                            <h6>No Data</h6>
                            <p>No visit reasons recorded.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-wrapper">
                            <div id="reasonsChart" style="height: 300px;"></div>
                        </div>
                        <button class="btn btn-success download-btn" onclick="downloadChart('reasonsChart', 'Visit_Reasons')">
                            <i class="fas fa-download me-1"></i> Download Image
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Top Patients Chart -->
                <div class="chart-container">
                    <h5><i class="fas fa-users"></i> Top 10 Patients</h5>
                    <?php if (empty($result_top_patients)): ?>
                        <div class="no-data">
                            <i class="fas fa-users"></i>
                            <h6>No Data</h6>
                            <p>No patient visits found.</p>
                        </div>
                    <?php else: ?>
                        <div class="patient-chart-container">
                            <div class="chart-wrapper">
                                <div id="patientsChart" style="height: 300px;"></div>
                            </div>
                            <div class="patient-list">
                                <h6>Top Visitors</h6>
                                <ul>
                                    <?php foreach ($result_top_patients as $patient): ?>
                                        <li>
                                            <div class="patient-name"><?= htmlspecialchars($patient['patient_name']) ?></div>
                                            <div class="patient-details">Category: <?= htmlspecialchars($patient['category']) ?></div>
                                            <span class="visit-count"><?= htmlspecialchars($patient['count']) ?> visits</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <button class="btn btn-success download-btn" onclick="downloadChart('patientsChart', 'Top_Patients')">
                            <i class="fas fa-download me-1"></i> Download Image
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <footer class="clinic-footer">
            <p>Clinic Management System © 2025 ICCB</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const colors = {
            primary: ['#0f73ba', '#059669', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6', '#d946ef', '#f97316', '#6d28d9'],
            secondary: '#6b7280'
        };

        const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

        const ct = () => isDark()
            ? { mode:'dark',  fg:'#718096', grid:'#2d3748', totalLbl:'#e2e8f0' }
            : { mode:'light', fg:'#6b7280', grid:'#e5e7eb', totalLbl:'#1a202c' };

        const baseConfig = {
            chart: {
                fontFamily: 'Roboto, sans-serif',
                toolbar: { show: false },
                background: 'transparent',
                animations: { enabled: true, easing: 'easeinout', speed: 400 }
            },
            theme: { mode: ct().mode },
            dataLabels: {
                enabled: true,
                style: { fontSize: '10px', fontWeight: 600, colors: ['#ffffff'] }
            },
            tooltip: { theme: ct().mode, style: { fontSize: '10px' } },
            legend: { fontSize: '10px', fontWeight: 500, position: 'bottom' }
        };

        <?php if (!empty($result_total_visits)): ?>
        const visitsChart = new ApexCharts(document.querySelector('#totalVisitsChart'), {
            ...baseConfig,
            series: [{
                name: 'Visits',
                data: [<?php echo implode(',', array_column($result_total_visits, 'total_visits')); ?>]
            }],
            chart: {
                ...baseConfig.chart,
                type: 'bar',
                height: 300
            },
            colors: [colors.primary[0]],
            plotOptions: {
                bar: { borderRadius: 4, columnWidth: '45%' }
            },
            xaxis: {
                categories: [<?php echo "'" . implode("','", array_column($result_total_visits, 'month_name')) . "'"; ?>],
                labels: { style: { fontSize: '10px', fontWeight: 500, colors: Array(<?= count($result_total_visits) ?>).fill(ct().fg) } }
            },
            yaxis: {
                title: { text: 'Visits', style: { fontSize: '11px', fontWeight: 600 } },
                labels: { style: { fontSize: '10px', colors: [ct().fg] } }
            },
            grid: { borderColor: ct().grid, strokeDashArray: 2 },
        });
        visitsChart.render();
        <?php endif; ?>

        <?php if (!empty($result_categories)): ?>
        const categoryChart = new ApexCharts(document.querySelector('#categoryChart'), {
            ...baseConfig,
            series: [<?php echo implode(',', array_column($result_categories, 'count')); ?>],
            labels: [<?php echo "'" . implode("','", array_column($result_categories, 'category')) . "'"; ?>],
            chart: {
                ...baseConfig.chart,
                type: 'donut',
                height: 300
            },
            colors: colors.primary,
            plotOptions: {
                pie: {
                    donut: {
                        size: '55%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                fontSize: '11px',
                                fontWeight: 600
                            }
                        }
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) { return Math.round(val) + '%'; },
                style: { fontSize: '10px', fontWeight: 600 }
            }
        });
        categoryChart.render();
        <?php endif; ?>

        <?php if (!empty($result_reasons)): ?>
        const reasonsChart = new ApexCharts(document.querySelector('#reasonsChart'), {
            ...baseConfig,
            series: [<?php echo implode(',', array_column($result_reasons, 'count')); ?>],
            labels: [<?php echo "'" . implode("','", array_column($result_reasons, 'visit_reason')) . "'"; ?>],
            chart: {
                ...baseConfig.chart,
                type: 'donut',
                height: 300
            },
            colors: colors.primary,
            plotOptions: {
                pie: {
                    donut: {
                        size: '55%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                fontSize: '11px',
                                fontWeight: 600
                            }
                        }
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) { return Math.round(val) + '%'; },
                style: { fontSize: '10px', fontWeight: 600 }
            }
        });
        reasonsChart.render();
        <?php endif; ?>

        <?php if (!empty($result_top_patients)): ?>
        const patientsChart = new ApexCharts(document.querySelector('#patientsChart'), {
            ...baseConfig,
            series: [<?php echo implode(',', array_column($result_top_patients, 'count')); ?>],
            labels: [<?php echo "'" . implode("','", array_column($result_top_patients, 'patient_name')) . "'"; ?>],
            chart: {
                ...baseConfig.chart,
                type: 'pie',
                height: 300
            },
            colors: colors.primary,
            dataLabels: {
                enabled: true,
                style: { fontSize: '10px', fontWeight: 600 }
            }
        });
        patientsChart.render();
        <?php endif; ?>

        // ─── Re-apply chart theme on dark-mode toggle ─
        const graphPageCharts = [
            <?php if (!empty($result_total_visits)): ?>  typeof visitsChart   !== 'undefined' ? visitsChart   : null, <?php endif; ?>
            <?php if (!empty($result_categories)): ?>    typeof categoryChart !== 'undefined' ? categoryChart : null, <?php endif; ?>
            <?php if (!empty($result_reasons)): ?>       typeof reasonsChart  !== 'undefined' ? reasonsChart  : null, <?php endif; ?>
            <?php if (!empty($result_top_patients)): ?>  typeof patientsChart !== 'undefined' ? patientsChart : null, <?php endif; ?>
        ].filter(Boolean);

        document.addEventListener('sscms:themechange', ({ detail: { dark } }) => {
            const t = ct();
            graphPageCharts.forEach(chart => {
                chart.updateOptions({
                    theme:   { mode: t.mode },
                    tooltip: { theme: dark ? 'dark' : 'light' },
                    grid:    { borderColor: t.grid },
                    xaxis:   { labels: { style: { colors: Array(20).fill(t.fg) } } },
                    yaxis:   { labels: { style: { colors: [t.fg] } } },
                }, false, false);
            });
        });

        function downloadChart(chartId, fileName) {
            const chartElement = document.querySelector(`#${chartId}`);
            if (!chartElement) {
                alert('Chart not found.');
                return;
            }
            const bgColor = isDark() ? '#1a202c' : '#ffffff';
            html2canvas(chartElement, { scale: 2, backgroundColor: bgColor }).then(canvas => {
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = `${fileName}_${'<?php echo htmlspecialchars($month_name); ?>'}_${'<?php echo htmlspecialchars($filter_year); ?>'}.png`;
                link.click();
            }).catch(err => {
                console.error('Download error:', err);
                alert('Failed to download chart. Please try again.');
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.toast').forEach(toast => new bootstrap.Toast(toast).show());
        });
    </script>
</body>
</html>