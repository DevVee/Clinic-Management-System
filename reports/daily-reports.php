<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Get filter inputs
$filter_student = filter_input(INPUT_GET, 'student', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_from_date = filter_input(INPUT_GET, 'from_date', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_to_date = filter_input(INPUT_GET, 'to_date', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_time = filter_input(INPUT_GET, 'time', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_month = filter_input(INPUT_GET, 'month', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_severity = filter_input(INPUT_GET, 'severity', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_program_section = filter_input(INPUT_GET, 'program_section', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_grade_year = filter_input(INPUT_GET, 'grade_year', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

// Set default date to today (Asia/Manila)
$today = date('Y-m-d');
$filter_date = $filter_date ?: $today;

// Validate inputs
if ($filter_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $_SESSION['error_message'] = 'Invalid date format';
    $filter_date = $today;
}
if ($filter_from_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_from_date)) {
    $_SESSION['error_message'] = 'Invalid from date format';
    $filter_from_date = '';
}
if ($filter_to_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_to_date)) {
    $_SESSION['error_message'] = 'Invalid to date format';
    $filter_to_date = '';
}
if ($filter_time && !preg_match('/^\d{2}:00$/', $filter_time)) {
    $_SESSION['error_message'] = 'Invalid time format';
    $filter_time = '';
}
if ($filter_month && !preg_match('/^\d{1,2}$/', $filter_month)) {
    $_SESSION['error_message'] = 'Invalid month format';
    $filter_month = '';
}
if ($filter_severity && !in_array($filter_severity, ['Mild', 'Moderate', 'Severe'])) {
    $_SESSION['error_message'] = 'Invalid severity level';
    $filter_severity = '';
}
if ($filter_category && !in_array($filter_category, ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Faculty and Staff', 'Alumni', 'Visitor'])) {
    $_SESSION['error_message'] = 'Invalid category';
    $filter_category = '';
}
if ($filter_program_section && !preg_match('/^[A-Za-z0-9\s-]+$/', $filter_program_section)) {
    $_SESSION['error_message'] = 'Invalid program/section format';
    $filter_program_section = '';
}
if ($filter_grade_year && !preg_match('/^[A-Za-z0-9\s-]+$/', $filter_grade_year)) {
    $_SESSION['error_message'] = 'Invalid grade/year format';
    $filter_grade_year = '';
}

// Fetch distinct categories, program sections, and grade years
try {
    $category_query = "SELECT DISTINCT category FROM patients ORDER BY category";
    $category_stmt = $conn->prepare($category_query);
    $category_stmt->execute();
    $categories = $category_stmt->fetchAll(PDO::FETCH_COLUMN);

    $program_query = "SELECT DISTINCT ps.name, ps.category 
                     FROM program_sections ps
                     JOIN patients p ON ps.name = p.program_section 
                     ORDER BY ps.name";
    $program_stmt = $conn->prepare($program_query);
    $program_stmt->execute();
    $program_sections = $program_stmt->fetchAll(PDO::FETCH_ASSOC);

    $grade_query = "SELECT DISTINCT name FROM grade_years ORDER BY name";
    $grade_stmt = $conn->prepare($grade_query);
    $grade_stmt->execute();
    $grade_years = $grade_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("[SSCMS Daily Reports] Error fetching filter options: " . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to load filter options: ' . $e->getMessage();
    $categories = [];
    $program_sections = [];
    $grade_years = [];
}

// Build query for reports
try {
    $query = "
        SELECT 
            p.last_name,
            p.first_name,
            p.category,
            p.program_section,
            p.grade_year,
            v.visit_date,
            TIME_FORMAT(v.visit_time, '%h:%i %p') AS visit_time,
            v.severity,
            v.temperature,
            COALESCE(
                GROUP_CONCAT(
                    DISTINCT CASE 
                        WHEN vr.reason = 'Other' THEN COALESCE(vr.other_reason, 'Other')
                        ELSE vr.reason
                    END
                    SEPARATOR ', '
                ),
                CASE 
                    WHEN v.reason = 'Other' THEN COALESCE(v.other_reason, v.reason)
                    WHEN v.reason IS NOT NULL AND v.reason != '' THEN v.reason
                    ELSE 'Unknown'
                END
            ) AS reason,
            CASE 
                WHEN v.took_medicine = 'Yes' THEN
                    COALESCE(
                        GROUP_CONCAT(
                            DISTINCT CONCAT(m.name, '[', vm.quantity, ']')
                            SEPARATOR ', '
                        ),
                        GROUP_CONCAT(
                            DISTINCT CONCAT(m.name, '[', ml.quantity_used, ']')
                            SEPARATOR ', '
                        ),
                        CONCAT(COALESCE(m3.name, v.medicine_name, 'Unknown'), '[', COALESCE(v.medicine_quantity, 0), ']')
                    )
                ELSE 'No Medicine Taken'
            END AS medicine_name,
            COALESCE(
                GROUP_CONCAT(DISTINCT vm.quantity SEPARATOR ', '),
                GROUP_CONCAT(DISTINCT ml.quantity_used SEPARATOR ', '),
                v.medicine_quantity,
                '0'
            ) AS medicine_quantity,
            CASE 
                WHEN v.visit_handling = 'Default' THEN 'Observation'
                ELSE v.visit_handling
            END AS visit_handling
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN visit_reasons vr ON v.id = vr.visit_id
        LEFT JOIN visit_medicines vm ON v.id = vm.visit_id
        LEFT JOIN medicines m ON vm.medicine_id = m.id
        LEFT JOIN medicine_logs ml ON v.patient_id = p.id 
            AND DATE(ml.visit_date) = v.visit_date
        LEFT JOIN medicines m2 ON ml.medicine_id = m2.id
        LEFT JOIN medicines m3 ON v.medicine_id = m3.id
        WHERE 1=1
    ";
    $params = [];

    if ($filter_student) {
        $query .= " AND (p.last_name LIKE ? OR p.first_name LIKE ? OR p.middle_name LIKE ?)";
        $params[] = "%$filter_student%";
        $params[] = "%$filter_student%";
        $params[] = "%$filter_student%";
    }

    if ($filter_category) {
        $query .= " AND p.category = ?";
        $params[] = $filter_category;
    }

    if ($filter_severity) {
        $query .= " AND v.severity = ?";
        $params[] = $filter_severity;
    }

    if ($filter_time) {
        $query .= " AND HOUR(v.visit_time) = ?";
        $params[] = (int)substr($filter_time, 0, 2);
    }

    if ($filter_month) {
        $year = $filter_date ? date('Y', strtotime($filter_date)) : date('Y');
        $query .= " AND MONTH(v.visit_date) = ? AND YEAR(v.visit_date) = ?";
        $params[] = $filter_month;
        $params[] = $year;
    } elseif ($filter_from_date && $filter_to_date) {
        $query .= " AND v.visit_date BETWEEN ? AND ?";
        $params[] = $filter_from_date;
        $params[] = $filter_to_date;
    } elseif ($filter_date) {
        $query .= " AND v.visit_date = ?";
        $params[] = $filter_date;
    }

    if ($filter_program_section) {
        $query .= " AND p.program_section = ?";
        $params[] = $filter_program_section;
    }

    if ($filter_grade_year) {
        $query .= " AND p.grade_year = ?";
        $params[] = $filter_grade_year;
    }

    $query .= " GROUP BY v.id, p.last_name, p.first_name, p.category, p.program_section, p.grade_year, v.visit_date, v.visit_time, v.severity, v.temperature, v.took_medicine, v.visit_handling";
    $query .= " ORDER BY v.visit_date DESC, v.visit_time DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->errorInfo()[2]);
    }

    if ($params) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Daily Reports] Error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to load data: ' . $e->getMessage();
    $reports = [];
}

// Build applied filters display
$applied_filters = [];
if ($filter_student) {
    $applied_filters[] = "Student: " . htmlspecialchars($filter_student);
}
if ($filter_category) {
    $applied_filters[] = "Category: " . htmlspecialchars($filter_category);
}
if ($filter_severity) {
    $applied_filters[] = "Severity: " . htmlspecialchars($filter_severity);
}
if ($filter_time) {
    $applied_filters[] = "Time: " . htmlspecialchars(date("h:i A", strtotime("$filter_time:00")));
}
if ($filter_month) {
    $month_name = date('F', mktime(0, 0, 0, $filter_month, 1));
    $year = $filter_date ? date('Y', strtotime($filter_date)) : date('Y');
    $applied_filters[] = "Month: $month_name $year";
} elseif ($filter_from_date && $filter_to_date) {
    $from_date_obj = new DateTime($filter_from_date);
    $to_date_obj = new DateTime($filter_to_date);
    if ($filter_from_date === $filter_to_date) {
        $applied_filters[] = "Date: " . $from_date_obj->format('F j, Y');
    } else {
        $applied_filters[] = "Date Range: " . $from_date_obj->format('F j, Y') . " to " . $to_date_obj->format('F j, Y');
    }
} elseif ($filter_date) {
    $date_obj = new DateTime($filter_date);
    $applied_filters[] = "Date: " . $date_obj->format('F j, Y');
}
if ($filter_program_section) {
    $applied_filters[] = "Program/Section: " . htmlspecialchars($filter_program_section);
}
if ($filter_grade_year) {
    $applied_filters[] = "Grade/Year: " . htmlspecialchars($filter_grade_year);
}
$applied_filters_text = !empty($applied_filters) ? "Filtered by: " . implode(", ", $applied_filters) : "No filters applied";
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Daily Report">
    <meta name="author" content="ICCB">
    <?php include '../includes/sscmslogo.php'; ?>
    <title>Daily Report - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary: #0f73ba;
            --primary-dark: #0d5a94;
            --primary-light: #e0f2fe;
            --secondary: #2c7be5;
            --background: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #d1fae5;
            --danger: #dc2626;
            --danger-dark: #b91c1c;
            --warning: #d97706;
            --warning-dark: #b45309;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --transition-speed: 0.2s;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.06);
            --shadow-md: 0 4px 12px rgba(15,115,186,.08), 0 2px 4px rgba(0,0,0,.04);
            --shadow-lg: 0 10px 24px rgba(15,115,186,.1), 0 4px 8px rgba(0,0,0,.04);
            --radius: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.5;
            overflow-x: hidden;
            font-size: 0.875rem;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1.5rem 2.5rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container-fluid {
            max-width: 1440px;
            padding: 0 0.5rem;
        }

        /* ── Page Hero ── */
        .page-hero {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 60%, var(--secondary) 100%);
            border-radius: var(--radius);
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .page-hero::after {
            content: '';
            position: absolute;
            right: -40px;
            top: -40px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
            pointer-events: none;
        }

        .hero-text h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0 0 .25rem;
            font-family: 'Poppins', sans-serif;
        }

        .hero-text p {
            font-size: .85rem;
            opacity: .85;
            margin: 0;
        }

        .hero-actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .hero-actions .btn {
            font-size: .8rem;
            padding: .45rem .9rem;
            font-weight: 600;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            transition: all .15s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,.15);
        }

        .btn-hero-filter {
            background: rgba(255,255,255,.2);
            border: 1px solid rgba(255,255,255,.35);
            color: white;
        }
        .btn-hero-filter:hover { background: rgba(255,255,255,.3); color: white; }

        .btn-hero-excel {
            background: #10b981;
            border: 1px solid #059669;
            color: white;
        }
        .btn-hero-excel:hover { background: #059669; color: white; }

        .btn-hero-pdf {
            background: #ef4444;
            border: 1px solid #dc2626;
            color: white;
        }
        .btn-hero-pdf:hover { background: #dc2626; color: white; }

        .btn-hero-print {
            background: rgba(255,255,255,.9);
            border: 1px solid rgba(255,255,255,.6);
            color: var(--primary-dark);
        }
        .btn-hero-print:hover { background: white; color: var(--primary-dark); }

        /* ── Cards ── */
        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: .75rem 1.25rem;
            font-weight: 600;
            font-size: .875rem;
            border-radius: var(--radius) var(--radius) 0 0;
            border: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header i { opacity: .85; }

        /* ── Filter Badge ── */
        .filters-applied {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-size: .8rem;
            color: var(--text-secondary);
            background: var(--primary-light);
            border: 1px solid #bae6fd;
            border-radius: 20px;
            padding: .3rem .85rem;
            margin-bottom: 1rem;
        }

        .filters-applied i { color: var(--primary); font-size: .7rem; }

        /* ── Report Print Header ── */
        .report-header {
            text-align: center;
            margin-bottom: .75rem;
            padding: .5rem;
        }

        .report-header img {
            max-width: min(700px, 100%);
            height: auto;
        }

        .report-title {
            font-size: 13pt;
            font-weight: 700;
            color: var(--text-primary);
            margin: .5rem 0 .25rem;
            text-align: center;
        }

        /* ── Table ── */
        .table {
            color: var(--text-primary);
            font-size: .75rem;
        }

        .table th, .table td {
            padding: .45rem .6rem;
            border-color: var(--border);
            vertical-align: middle;
        }

        .table th {
            font-weight: 600;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: #f1f5f9;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .table tbody tr:hover { background: var(--primary-light); }

        /* Severity badges */
        .badge-mild    { background:#d1fae5; color:#065f46; border-radius:4px; padding:.2rem .5rem; font-size:.7rem; font-weight:600; }
        .badge-moderate{ background:#fef3c7; color:#92400e; border-radius:4px; padding:.2rem .5rem; font-size:.7rem; font-weight:600; }
        .badge-severe  { background:#fee2e2; color:#991b1b; border-radius:4px; padding:.2rem .5rem; font-size:.7rem; font-weight:600; }

        /* ── Misc ── */
        .btn {
            border-radius: 7px;
            padding: .4rem .85rem;
            font-weight: 500;
            font-size: .8rem;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }

        .btn-primary { background-color: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--primary-dark); border-color: var(--primary-dark); }

        .form-control, .form-select {
            border-radius: 7px;
            border: 1px solid var(--border);
            padding: .45rem .75rem;
            font-size: .875rem;
            height: 38px;
            background: var(--card-bg);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15,115,186,.12);
        }

        .form-label { font-size: .875rem; font-weight: 500; margin-bottom: .35rem; }
        .form-group { margin-bottom: 1.1rem; }
        .form-text   { font-size: .78rem; color: var(--text-muted); }

        .modal-content { border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,.18); border: none; }
        .modal-header  { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-bottom: none; padding: 1rem 1.5rem; border-radius: 12px 12px 0 0; }
        .modal-header .btn-close { filter: invert(1) opacity(.85); }
        .modal-body    { padding: 1.5rem; }
        .modal-footer  { border-top: 1px solid var(--border); padding: 1rem 1.5rem; }
        .modal-lg      { max-width: 820px; }

        .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 1080; }

        .clinic-footer {
            margin-top: 2rem;
            padding: 1rem 0;
            text-align: center;
            color: var(--text-muted);
            font-size: .8rem;
            border-top: 1px solid var(--border);
        }

        .flatpickr-day.selected { background-color: var(--primary); border-color: var(--primary); }

        /* ── Responsive ── */
        @media (max-width: 992px) {
            :root { --sidebar-width: var(--sidebar-collapsed-width); }
            .content { margin-left: var(--sidebar-width); }
        }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
            .modal-lg { max-width: 96%; }
            .hero-actions { width: 100%; }
            .hero-actions .btn { flex: 1; justify-content: center; }
        }

        /* ══════════════════════════════════
           DARK MODE — override page variables
           Everything using var(--*) adapts automatically.
           ══════════════════════════════════ */
        [data-theme="dark"] {
            --background:    hsl(222, 47%, 9%);
            --card-bg:       hsl(222, 47%, 13%);
            --text-primary:  hsl(210, 40%, 96%);
            --text-secondary:hsl(215, 20%, 65%);
            --text-muted:    hsl(215, 20%, 50%);
            --border:        hsl(222, 30%, 22%);
            --shadow-sm:     0 1px 3px rgba(0,0,0,.25);
            --shadow-md:     0 4px 12px rgba(0,0,0,.35), 0 2px 4px rgba(0,0,0,.2);
        }

        /* Components that use hardcoded colours need explicit overrides */
        [data-theme="dark"] .card-header {
            background: linear-gradient(135deg, hsl(201,85%,20%), hsl(201,85%,28%));
        }
        [data-theme="dark"] .table th {
            background: hsl(222,47%,18%);
            color: hsl(210,40%,96%);
        }
        [data-theme="dark"] .table,
        [data-theme="dark"] .table td { color: hsl(210,40%,88%); }
        [data-theme="dark"] .table-hover tbody tr:hover td { background: hsl(222,47%,16%); }
        [data-theme="dark"] .flatpickr-calendar {
            background: hsl(222,47%,13%);
            border-color: hsl(222,30%,25%);
            color: hsl(210,40%,90%);
            box-shadow: 0 8px 24px rgba(0,0,0,.4);
        }
        [data-theme="dark"] .flatpickr-day { color: hsl(210,40%,85%); }
        [data-theme="dark"] .flatpickr-day:hover { background: hsl(222,47%,20%); }
        [data-theme="dark"] .flatpickr-day.selected { background: var(--primary); color: #fff; }
        [data-theme="dark"] .flatpickr-months,
        [data-theme="dark"] .flatpickr-weekdays { background: hsl(222,47%,16%); color: hsl(210,40%,90%); }
        /* severity badges */
        [data-theme="dark"] .sev-badge.badge-mild     { background: hsl(152,74%,13%); color: hsl(152,74%,70%); }
        [data-theme="dark"] .sev-badge.badge-moderate { background: hsl(38,92%,13%);  color: hsl(38,92%,72%);  }
        [data-theme="dark"] .sev-badge.badge-severe   { background: hsl(0,84%,14%);   color: hsl(0,84%,72%);   }
        /* hero actions transparent bg on dark */
        [data-theme="dark"] .page-hero { box-shadow: 0 4px 20px rgba(0,0,0,.45); }

        /* ── Print-only elements (hidden on screen) ── */
        .print-doc-header, .print-doc-footer { display: none; }

        /* ── Print / PDF ── */
        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm 12mm 18mm;
            }

            /* ── Reset screen layout ── */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

            body {
                background: #fff !important;
                color: #000 !important;
                font-family: 'Times New Roman', Times, serif !important;
                font-size: 9pt !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .content { margin: 0 !important; padding: 0 !important; min-height: auto !important; }

            .container-fluid {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* ── Hide all UI ── */
            .page-hero, .no-print,
            nav, .navbar, .main-navbar, .top-header,
            .sidebar, .sidebar-overlay,
            .card-header,
            .modal, .modal-backdrop,
            .toast-container,
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate,
            #reportTable_wrapper .row:first-child,
            #reportTable_wrapper .row:last-child,
            [class*="navbar"], [id*="navbar"],
            [class*="sidebar"], [id*="sidebar"],
            [class*="sscms"], [id*="sscms"] {
                display: none !important;
            }

            /* ── Card becomes transparent wrapper ── */
            .card {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                background: transparent !important;
            }

            .card-body { padding: 0 !important; }

            /* ── Printable content area ── */
            #printable-content {
                width: 100%;
            }

            /* ── Show print-only document header ── */
            .print-doc-header {
                display: block !important;
                text-align: center;
                border-bottom: 2pt solid #000;
                padding-bottom: 4mm;
                margin-bottom: 5mm;
                page-break-after: avoid;
            }

            .print-doc-header .banner-wrap img {
                max-width: 480px;
                width: 70%;
                height: auto;
                display: block;
                margin: 0 auto 2mm;
            }

            .print-doc-header .school-name {
                font-size: 10pt;
                font-weight: 700;
                letter-spacing: 0.3pt;
                margin: 0 0 1mm;
            }

            .print-doc-header .school-sub {
                font-size: 8pt;
                color: #333;
                margin: 0 0 3mm;
            }

            .print-doc-header .doc-title {
                font-size: 12pt;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1pt;
                margin: 0 0 1mm;
                border: 0.5pt solid #000;
                display: inline-block;
                padding: 1mm 6mm;
            }

            .print-doc-header .doc-meta {
                font-size: 8pt;
                color: #444;
                margin: 2mm 0 0;
            }

            .print-doc-header .doc-meta span {
                margin: 0 4mm;
            }

            /* ── Hide old inline report-header (inside printable-content) ── */
            #printable-content > .report-header { display: none !important; }

            /* ── Report sub-title (already generated by PHP) ── */
            .report-title {
                font-size: 10pt !important;
                font-weight: 700 !important;
                text-align: center;
                margin: 0 0 4mm !important;
                padding: 0 !important;
                letter-spacing: 0.2pt;
            }

            /* ── Table ── */
            .table-responsive { overflow: visible !important; }

            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 7.5pt !important;
                font-family: 'Times New Roman', Times, serif !important;
                table-layout: fixed;
            }

            /* Column widths to fit portrait A4 (usable ≈ 186mm) */
            .table th:nth-child(1),
            .table td:nth-child(1)  { width: 16mm; } /* Last Name */
            .table th:nth-child(2),
            .table td:nth-child(2)  { width: 14mm; } /* First Name */
            .table th:nth-child(3),
            .table td:nth-child(3)  { width: 13mm; } /* Category */
            .table th:nth-child(4),
            .table td:nth-child(4)  { width: 20mm; } /* Program/Section */
            .table th:nth-child(5),
            .table td:nth-child(5)  { width: 11mm; } /* Grade/Year */
            .table th:nth-child(6),
            .table td:nth-child(6)  { width: 28mm; } /* Reason */
            .table th:nth-child(7),
            .table td:nth-child(7)  { width: 13mm; } /* Severity */
            .table th:nth-child(8),
            .table td:nth-child(8)  { width: 11mm; } /* Temp */
            .table th:nth-child(9),
            .table td:nth-child(9)  { width: 15mm; } /* Visit Date */
            .table th:nth-child(10),
            .table td:nth-child(10) { width: 11mm; } /* Visit Time */
            .table th:nth-child(11),
            .table td:nth-child(11) { width: 18mm; } /* Medicine */
            .table th:nth-child(12),
            .table td:nth-child(12) { width: 16mm; } /* Handling */

            .table th, .table td {
                border: 0.4pt solid #555 !important;
                padding: 1.2mm 1.5mm !important;
                background: #fff !important;
                color: #000 !important;
                white-space: normal !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
                vertical-align: top;
            }

            .table thead th {
                background: #e8e8e8 !important;
                font-weight: 700 !important;
                font-size: 7pt !important;
                text-transform: uppercase;
                letter-spacing: 0.2pt;
                text-align: center;
                vertical-align: middle;
            }

            .table tbody tr:nth-child(even) td { background: #f9f9f9 !important; }

            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }

            /* ── Severity: show text only, no badge ── */
            .sev-badge {
                background: none !important;
                border: none !important;
                padding: 0 !important;
                font-weight: 700;
                font-size: 7.5pt;
            }

            .sev-badge.badge-mild     { color: #065f46 !important; }
            .sev-badge.badge-moderate { color: #92400e !important; }
            .sev-badge.badge-severe   { color: #991b1b !important; }

            /* ── Print footer ── */
            .print-doc-footer {
                display: block !important;
                margin-top: 5mm;
                border-top: 0.5pt solid #aaa;
                padding-top: 2mm;
                display: flex !important;
                justify-content: space-between;
                font-size: 7pt;
                color: #555;
            }

            /* ── Signature block ── */
            .print-sig-row {
                display: grid !important;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 10mm;
                margin-top: 8mm;
            }

            .print-sig-block {
                text-align: center;
                font-size: 8pt;
            }

            .print-sig-block .sig-line {
                border-top: 0.5pt solid #000;
                margin-bottom: 1.5mm;
            }

            .print-sig-block .sig-name { font-weight: 700; }
            .print-sig-block .sig-role { color: #555; font-size: 7pt; }
        }

        /* ── PDF hidden content ── */
        .pdf-content {
            display: none;
            width: 190mm;
            font-size: 8pt;
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <div class="toast-container">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="toast align-items-center text-bg-danger border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                </div>

                <!-- Page Hero -->
                <div class="page-hero no-print">
                    <div class="hero-content">
                        <div class="hero-title">
                            <i class="fas fa-file-alt me-2"></i>Daily Report
                        </div>
                        <div class="hero-subtitle">Clinic visit records and patient data for the selected period</div>
                    </div>
                    <div class="hero-actions">
                        <button class="btn-hero-filter" data-bs-toggle="modal" data-bs-target="#filterModal">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <button class="btn-hero-excel" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-1"></i> Excel
                        </button>
                        <button class="btn-hero-pdf" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-1"></i> PDF
                        </button>
                        <button class="btn-hero-print" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-table me-2"></i>
                            <span id="card-report-title">Clinic Daily Report</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($applied_filters_text !== 'No filters applied'): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                                    <i class="fas fa-filter me-1"></i><?= htmlspecialchars($applied_filters_text) ?>
                                </span>
                            <?php endif; ?>
                            <a href="../dashboard.php" class="text-decoration-none">
                                <img src="../assets/img/ICCLOGO.png" style="height: 22px;" alt="ICCB Logo">
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="printable-content">

                            <!-- Print-only formal document header (hidden on screen) -->
                            <div class="print-doc-header">
                                <div class="banner-wrap">
                                    <img src="../assets/img/ICC_BANNER.png" alt="Immaculate Conception College of Balayan, Inc.">
                                </div>
                                <div class="school-name">Immaculate Conception College of Balayan, Inc.</div>
                                <div class="school-sub">School Clinic &nbsp;|&nbsp; Balayan, Batangas</div>
                                <div class="doc-title">Clinic Daily Report</div>
                                <div class="doc-meta">
                                    <span><strong>Generated:</strong> <?= date('F j, Y \a\t g:i A') ?></span>
                                    <span><strong>Prepared by:</strong> <?= htmlspecialchars($_SESSION['admin_category'] ?? 'Clinic Staff') ?></span>
                                    <?php if ($applied_filters_text !== 'No filters applied'): ?>
                                        <span><strong>Filter:</strong> <?= htmlspecialchars($applied_filters_text) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Screen: banner -->
                            <div class="report-header">
                                <img src="../assets/img/ICC_BANNER.png" alt="Immaculate Conception College of Balayan, Inc. Banner" style="max-width: 800px;">
                            </div>
                            <h2 id="report-title" class="report-title">
                                <?php
                                $title = 'Clinic Daily Report';
                                if ($filter_month) {
                                    $month_name = date('F', mktime(0, 0, 0, $filter_month, 1));
                                    $year = $filter_date ? date('Y', strtotime($filter_date)) : date('Y');
                                    $title = "Daily Report for $month_name $year";
                                } elseif ($filter_from_date && $filter_to_date) {
                                    $from_date_obj = new DateTime($filter_from_date);
                                    $to_date_obj = new DateTime($filter_to_date);
                                    if ($filter_from_date === $filter_to_date) {
                                        $title = 'Daily Report for ' . $from_date_obj->format('F j, Y');
                                    } else {
                                        $title = 'Daily Report from ' . $from_date_obj->format('F j, Y') . ' to ' . $to_date_obj->format('F j, Y');
                                    }
                                } elseif ($filter_date) {
                                    $date_obj = new DateTime($filter_date);
                                    $title = 'Daily Report for ' . $date_obj->format('F j, Y');
                                }
                                echo htmlspecialchars($title);
                                ?>
                            </h2>
                            <div class="table-responsive">
                                <table id="reportTable" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Last Name</th>
                                            <th>First Name</th>
                                            <th>Category</th>
                                            <th>Program/Section</th>
                                            <th>Grade/Year</th>
                                            <th>Reason</th>
                                            <th>Severity</th>
                                            <th>Temperature</th>
                                            <th>Visit Date</th>
                                            <th>Visit Time</th>
                                            <th>Medicine Taken</th>
                                            <th>Visit Handling</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($report['last_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($report['first_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($report['category'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($report['program_section'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($report['grade_year'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($report['reason'] ?? '-') ?></td>
                                                <td>
                                                    <?php
                                                    $sev = $report['severity'] ?? '-';
                                                    $sev_class = match(strtolower($sev)) {
                                                        'mild'     => 'badge-mild',
                                                        'moderate' => 'badge-moderate',
                                                        'severe'   => 'badge-severe',
                                                        default    => ''
                                                    };
                                                    echo $sev_class
                                                        ? '<span class="sev-badge ' . $sev_class . '">' . htmlspecialchars($sev) . '</span>'
                                                        : htmlspecialchars($sev);
                                                    ?>
                                                </td>
                                                <td><?= is_null($report['temperature']) ? '-' : htmlspecialchars(number_format($report['temperature'], 1) . '°C') ?></td>
                                                <td>
                                                    <?php
                                                    echo $report['visit_date']
                                                        ? (new DateTime($report['visit_date']))->format('F j, Y')
                                                        : '-';
                                                    ?>
                                                </td>
                                                <td><?= htmlspecialchars($report['visit_time'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($report['medicine_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($report['visit_handling'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Print-only: record count summary -->
                            <div class="print-doc-footer">
                                <span>Total Records: <strong><?= count($reports) ?></strong></span>
                                <span>Immaculate Conception College Clinic &mdash; Confidential Document</span>
                                <span>Generated: <?= date('F j, Y') ?></span>
                            </div>

                            <!-- Print-only: signature block -->
                            <div class="print-sig-row">
                                <div class="print-sig-block">
                                    <div style="height:12mm;"></div>
                                    <div class="sig-line"></div>
                                    <div class="sig-name">Prepared by</div>
                                    <div class="sig-role">Clinic Staff / Nurse</div>
                                </div>
                                <div class="print-sig-block">
                                    <div style="height:12mm;"></div>
                                    <div class="sig-line"></div>
                                    <div class="sig-name">Reviewed by</div>
                                    <div class="sig-role">School Physician / Head Nurse</div>
                                </div>
                                <div class="print-sig-block">
                                    <div style="height:12mm;"></div>
                                    <div class="sig-line"></div>
                                    <div class="sig-name">Noted by</div>
                                    <div class="sig-role">School Administrator</div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </main>

        <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="filterModalLabel">Filter Daily Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="filterForm" action="daily-reports.php" method="GET">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterStudent" class="form-label">Student Name</label>
                                        <input type="text" id="filterStudent" name="student" class="form-control" placeholder="Search by student name" value="<?= htmlspecialchars($filter_student) ?>">
                                        <div class="form-text">Enter first or last name</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterCategory" class="form-label">Category</label>
                                        <select id="filterCategory" name="category" class="form-select">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= htmlspecialchars($category) ?>" <?= $filter_category === $category ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Select a category to filter programs</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterProgramSection" class="form-label">Program/Section</label>
                                        <select id="filterProgramSection" name="program_section" class="form-select" disabled>
                                            <option value="">Select a category first</option>
                                        </select>
                                        <div class="form-text">Select a program or section (depends on category)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterGradeYear" class="form-label">Grade/Year</label>
                                        <select id="filterGradeYear" name="grade_year" class="form-select">
                                            <option value="">All Grades/Years</option>
                                            <?php foreach ($grade_years as $grade): ?>
                                                <option value="<?= htmlspecialchars($grade) ?>" <?= $filter_grade_year === $grade ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($grade) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Select grade or year</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterSeverity" class="form-label">Severity</label>
                                        <select id="filterSeverity" name="severity" class="form-select">
                                            <option value="">All Severities</option>
                                            <option value="Mild" <?= $filter_severity === 'Mild' ? 'selected' : '' ?>>Mild</option>
                                            <option value="Moderate" <?= $filter_severity === 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                                            <option value="Severe" <?= $filter_severity === 'Severe' ? 'selected' : '' ?>>Severe</option>
                                        </select>
                                        <div class="form-text">Select severity level</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterTime" class="form-label">Time (7:00 AM - 4:00 PM)</label>
                                        <select id="filterTime" name="time" class="form-select">
                                            <option value="">Select time</option>
                                            <?php
                                            for ($hour = 7; $hour <= 16; $hour++) {
                                                $time_24 = sprintf("%02d:00", $hour);
                                                $time_12 = date("h:i A", strtotime("$time_24:00"));
                                                $selected = ($filter_time === $time_24) ? 'selected' : '';
                                                echo "<option value=\"$time_24\" $selected>$time_12</option>";
                                            }
                                            ?>
                                        </select>
                                        <div class="form-text">Select visit time</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterDate" class="form-label">Date</label>
                                        <input type="text" id="filterDate" name="date" class="form-control flatpickr" placeholder="Select date" value="<?= htmlspecialchars($filter_date) ?>">
                                        <div class="form-text">Select a specific visit date</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterMonth" class="form-label">Month</label>
                                        <select id="filterMonth" name="month" class="form-select">
                                            <option value="">All Months</option>
                                            <?php
                                            $months = [
                                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                            ];
                                            foreach ($months as $num => $name) {
                                                $selected = ($filter_month === (string)$num) ? 'selected' : '';
                                                echo "<option value=\"$num\" $selected>$name</option>";
                                            }
                                            ?>
                                        </select>
                                        <div class="form-text">Select a month to filter</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterFromDate" class="form-label">From Date</label>
                                        <input type="text" id="filterFromDate" name="from_date" class="form-control flatpickr" placeholder="Select start date" value="<?= htmlspecialchars($filter_from_date) ?>">
                                        <div class="form-text">Select start date for range</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="filterToDate" class="form-label">To Date</label>
                                        <input type="text" id="filterToDate" name="to_date" class="form-control flatpickr" placeholder="Select end date" value="<?= htmlspecialchars($filter_to_date) ?>">
                                        <div class="form-text">Select end date for range</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-clear" onclick="clearFilters()">Clear</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div id="pdf-content" class="pdf-content">
            <div class="report-header">
                <img src="../assets/img/ICC_Banner.png" alt="Immaculate Conception College of Balayan, Inc. Banner" style="max-width: 800px;">
            </div>
            <h2 id="pdf-report-title" class="report-title"><?= htmlspecialchars($title) ?></h2>
            <div class="table-responsive">
                <table id="pdfReportTable" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Last Name</th>
                            <th>First Name</th>
                            <th>Category</th>
                            <th>Program/Section</th>
                            <th>Grade/Year</th>
                            <th>Reason</th>
                            <th>Severity</th>
                            <th>Temperature</th>
                            <th>Visit Date</th>
                            <th>Visit Time</th>
                            <th>Medicine Taken</th>
                            <th>Visit Handling</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?= htmlspecialchars($report['last_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($report['first_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($report['category'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($report['program_section'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($report['grade_year'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($report['reason'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($report['severity'] ?? '-') ?></td>
                                <td><?= is_null($report['temperature']) ? '-' : htmlspecialchars(number_format($report['temperature'], 1) . '°C') ?></td>
                                <td>
                                    <?php
                                    echo $report['visit_date']
                                        ? (new DateTime($report['visit_date']))->format('F j, Y')
                                        : '-';
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($report['visit_time'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($report['medicine_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($report['visit_handling'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Sync card header title with report title
            const reportTitleText = $('#report-title').text().trim();
            if (reportTitleText) $('#card-report-title').text(reportTitleText);

            // Initialize Flatpickr for date pickers
            flatpickr(".flatpickr", {
                dateFormat: "Y-m-d",
                maxDate: new Date(),
                theme: "light",
                prevArrow: '<i class="fas fa-arrow-left"></i>',
                nextArrow: '<i class="fas fa-arrow-right"></i>'
            });

            // Set default values
            $('#filterStudent').val('<?= htmlspecialchars($filter_student) ?>');
            $('#filterCategory').val('<?= htmlspecialchars($filter_category) ?>');
            $('#filterDate').val('<?= htmlspecialchars($filter_date) ?>');
            $('#filterFromDate').val('<?= htmlspecialchars($filter_from_date) ?>');
            $('#filterToDate').val('<?= htmlspecialchars($filter_to_date) ?>');
            $('#filterTime').val('<?= htmlspecialchars($filter_time) ?>');
            $('#filterMonth').val('<?= htmlspecialchars($filter_month) ?>');
            $('#filterSeverity').val('<?= htmlspecialchars($filter_severity) ?>');
            $('#filterProgramSection').val('<?= htmlspecialchars($filter_program_section) ?>');
            $('#filterGradeYear').val('<?= htmlspecialchars($filter_grade_year) ?>');

            // Initialize program sections
            const allPrograms = <?php echo json_encode($program_sections); ?>;
            const nonAcademicCategories = ['Faculty and Staff', 'Alumni', 'Visitor'];

            function updateProgramSection(category) {
                const $programSelect = $('#filterProgramSection');
                $programSelect.empty();
                $programSelect.append('<option value="">All Programs/Sections</option>');

                if (!category || nonAcademicCategories.includes(category)) {
                    $programSelect.prop('disabled', true);
                    if (category) {
                        $programSelect.append(`<option value="${category}">${category}</option>`);
                        $programSelect.val('<?= htmlspecialchars($filter_program_section) ?>');
                    }
                } else {
                    $programSelect.prop('disabled', false);
                    const filteredPrograms = allPrograms.filter(program => program.category === category);
                    filteredPrograms.forEach(program => {
                        const selected = program.name === '<?= htmlspecialchars($filter_program_section) ?>' ? 'selected' : '';
                        $programSelect.append(`<option value="${program.name}" ${selected}>${program.name}</option>`);
                    });
                }
            }

            // Update program section on category change
            $('#filterCategory').on('change', function() {
                const category = $(this).val();
                updateProgramSection(category);
            });

            // Initialize program section based on current category
            updateProgramSection('<?= htmlspecialchars($filter_category) ?>');

            // Clear conflicting date filters
            $('#filterDate').on('change', function() {
                if ($(this).val()) {
                    $('#filterFromDate').val('');
                    $('#filterToDate').val('');
                    $('#filterMonth').val('');
                }
            });

            $('#filterFromDate, #filterToDate').on('change', function() {
                if ($('#filterFromDate').val() || $('#filterToDate').val()) {
                    $('#filterDate').val('');
                    $('#filterMonth').val('');
                }
            });

            $('#filterMonth').on('change', function() {
                if ($(this).val()) {
                    $('#filterDate').val('');
                    $('#filterFromDate').val('');
                    $('#filterToDate').val('');
                }
            });

            // Initialize DataTable
            const reportTable = $('#reportTable').DataTable({
                pageLength: 25,
                lengthMenu: [25],
                language: { search: "", searchPlaceholder: "Search reports..." },
                columnDefs: [{ orderable: false, targets: [11] }],
                order: [[8, 'desc'], [9, 'desc']] // Sort by visit_date, visit_time
            });

            // Clear filters
            window.clearFilters = function() {
                $('#filterStudent').val('');
                $('#filterCategory').val('');
                $('#filterDate').val('');
                $('#filterFromDate').val('');
                $('#filterToDate').val('');
                $('#filterTime').val('');
                $('#filterMonth').val('');
                $('#filterSeverity').val('');
                $('#filterProgramSection').val('');
                $('#filterGradeYear').val('');
                $('#filterForm').submit();
            };

            // Export to PDF
            window.exportToPDF = function() {
                console.log('Exporting to PDF');
                const element = document.getElementById('pdf-content');
                element.style.display = 'block';

                const opt = {
                    margin: [0.1, 0.1, 0.1, 0.1],
                    filename: 'clinic_daily_report.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 3, useCORS: true, logging: false },
                    jsPDF: { unit: 'cm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };

                html2pdf().from(element).set(opt).save().then(() => {
                    element.style.display = 'none';
                    console.log('PDF exported successfully');
                }).catch(error => {
                    console.error('Error exporting PDF:', error);
                    element.style.display = 'none';
                    alert('Failed to generate PDF. Check console for details.');
                });
            };

            // Export to Excel
            window.exportToExcel = function() {
                const data = reportTable.rows().data().toArray();
                if (data.length === 0) {
                    alert('No data to export.');
                    return;
                }

                const reportTitle = $('#report-title').text();
                const currentDate = new Date().toISOString().split('T')[0];

                // Prepare data
                const excelData = [
                    { '': reportTitle, ' ': '', '  ': '', '   ': '', '    ': '', '     ': '', '      ': '', '       ': '', '        ': '', '         ': '', '          ': '', '           ': '' }, // Title row
                    { '': '', ' ': '', '  ': '', '   ': '', '    ': '', '     ': '', '      ': '', '       ': '', '        ': '', '         ': '', '          ': '', '           ': '' } // Empty row
                ];

                // Header row
                excelData.push({
                    '': 'Last Name',
                    ' ': 'First Name',
                    '  ': 'Category',
                    '   ': 'Program/Section',
                    '    ': 'Grade/Year',
                    '     ': 'Reason',
                    '      ': 'Severity',
                    '       ': 'Temperature',
                    '        ': 'Visit Date',
                    '         ': 'Visit Time',
                    '          ': 'Medicine Taken',
                    '           ': 'Visit Handling'
                });

                // Data rows
                data.forEach(row => {
                    excelData.push({
                        '': row[0] || '-',
                        ' ': row[1] || '-',
                        '  ': row[2] || '-',
                        '   ': row[3] || '-',
                        '    ': row[4] || '-',
                        '     ': row[5] || '-',
                        '      ': row[6] || '-',
                        '       ': isNaN(parseFloat(row[7])) ? '-' : number_format(parseFloat(row[7]), 1) + '°C',
                        '        ': row[8] || '-',
                        '         ': row[9] || '-',
                        '          ': row[10] || '-',
                        '           ': row[11] || '-'
                    });
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

                ws['!cols'] = [
                    { width: 20 },
                    { width: 20 },
                    { width: 15 },
                    { width: 20 },
                    { width: 15 },
                    { width: 25 },
                    { width: 10 },
                    { width: 10 },
                    { width: 15 },
                    { width: 15 },
                    { width: 20 },
                    { width: 15 }
                ];

                ws['!merges'] = [
                    { s: { r: 0, c: 0 }, e: { r: 0, c: 11 } }
                ];

                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Daily Report');
                XLSX.writeFile(wb, `Clinic_Daily_Report_${currentDate}.xlsx`);
            };

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });

        // Custom number formatting function for Excel
        function number_format(number, decimals) {
            return Number(number).toFixed(decimals);
        }
    </script>
</body>
</html>