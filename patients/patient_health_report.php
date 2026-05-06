<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    $_SESSION['error_message'] = 'Invalid patient ID.';
    header('Location: manage-patients.php');
    exit;
}

// Fetch patient details
try {
    $stmt = $conn->prepare("
        SELECT first_name, last_name, middle_name, gender, category, grade_year, program_section,
               guardian_name, guardian_contact, guardian_facebook,
               emergency_contact_name, emergency_contact_number,
               pediatrician_name, pediatrician_contact,
               allergies, medical_conditions, notes, address
        FROM patients WHERE id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        $_SESSION['error_message'] = 'Patient not found.';
        header('Location: manage-patients.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("[SSCMS Health Report] Database error (patient fetch): " . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred.';
    header('Location: manage-patients.php');
    exit;
}
$full_name = trim($patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name']);

// Fetch health record
try {
    $stmt = $conn->prepare("SELECT * FROM health_records WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $health_record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$health_record) {
        $health_record = [
            'total_visits' => 0,
            'mild_visits' => 0,
            'moderate_visits' => 0,
            'severe_visits' => 0,
            'last_visit_date' => null,
            'last_visit_reason' => null
        ];
    }
} catch (PDOException $e) {
    error_log("[SSCMS Health Report] Database error (health record): " . $e->getMessage());
    $health_record = [
        'total_visits' => 0,
        'mild_visits' => 0,
        'moderate_visits' => 0,
        'severe_visits' => 0,
        'last_visit_date' => null,
        'last_visit_reason' => null
    ];
}

// Fetch all visit reasons with counts
try {
    $stmt = $conn->prepare("
        SELECT TRIM(single_reason) AS reason, COUNT(*) AS count
        FROM visits v
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
        WHERE v.patient_id = ? AND single_reason != ''
        GROUP BY single_reason
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmt->execute([$patient_id]);
    $visit_reasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $most_frequent_reason = $visit_reasons ? htmlspecialchars($visit_reasons[0]['reason']) : 'N/A';
} catch (PDOException $e) {
    error_log("[SSCMS Health Report] Database error (visit reasons): " . $e->getMessage());
    $visit_reasons = [];
    $most_frequent_reason = 'N/A';
}

// Prepare data for charts
$severity_data = [
    'Mild' => (int)$health_record['mild_visits'],
    'Moderate' => (int)$health_record['moderate_visits'],
    'Severe' => (int)$health_record['severe_visits']
];

// Monthly visit counts for the past 6 months
$monthly_visits = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_visits[$month] = 0;
}
try {
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(visit_date, '%Y-%m') AS month, COUNT(*) AS count
        FROM visits
        WHERE patient_id = ? AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
    ");
    $stmt->execute([$patient_id]);
    $visit_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($visit_counts as $vc) {
        $monthly_visits[$vc['month']] = (int)$vc['count'];
    }
} catch (PDOException $e) {
    error_log("[SSCMS Health Report] Database error (visit counts): " . $e->getMessage());
}
$monthly_labels = array_map(function($month) { return date('M Y', strtotime($month . '-01')); }, array_keys($monthly_visits));
$monthly_counts = array_values($monthly_visits);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Patient Health Report">
    <meta name="author" content="ICCB">
    <title>Health Report for <?= htmlspecialchars($full_name) ?> - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <?php include '../includes/sscmslogo.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        :root {
            --primary: #0f73ba;
            --primary-light: #e0f2fe;
            --primary-dark: #0d5a94;
            --secondary: #2c7be5;
            --black: #1a202c;
            --white: #ffffff;
            --gray: #6b7280;
            --light-gray: #e5e7eb;
            --accent: #0f73ba;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
            --radius: 10px;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --transition-speed: 0.2s;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8fafc;
            color: var(--black);
            line-height: 1.5;
            font-size: 0.9rem;
            margin: 0;
            padding: 0;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1.5rem 2.5rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container-fluid {
            max-width: 960px;
            padding: 0 1rem;
            margin: 0 auto;
        }

        /* ── Page Hero (screen only) ── */
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
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: .15rem;
        }

        .hero-content .hero-subtitle {
            color: rgba(255,255,255,.78);
            font-size: .82rem;
        }

        .hero-actions { display: flex; gap: .5rem; flex-wrap: wrap; }

        .btn-hero { padding: .4rem .9rem; border-radius: 6px; font-size: .82rem; font-weight: 600; display: inline-flex; align-items: center; gap: .35rem; cursor: pointer; height: 34px; transition: background .2s; border: none; text-decoration: none; }
        .btn-hero-back  { background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.4); color: #fff; }
        .btn-hero-back:hover  { background: rgba(255,255,255,.28); color: #fff; }
        .btn-hero-visits { background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.4); color: #fff; }
        .btn-hero-visits:hover { background: rgba(255,255,255,.28); color: #fff; }
        .btn-hero-pdf   { background: var(--success); color: #fff; }
        .btn-hero-pdf:hover   { background: #047857; color: #fff; }
        .btn-hero-print { background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.5); color: #fff; }
        .btn-hero-print:hover { background: rgba(255,255,255,.32); color: #fff; }

        .card {
            background-color: var(--white);
            border: 1px solid var(--light-gray);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
        }

        .report-card {
            width: 210mm;
            background: var(--white);
            padding: 10mm 12mm;
            border: 1px solid var(--light-gray);
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            font-size: 8.5pt;
            margin: 0 auto;
        }

        .report-header {
            border-bottom: 1px solid var(--black);
            padding-bottom: 3mm;
            margin-bottom: 4mm;
            text-align: center;
        }

        .banner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 2mm;
            padding: 1mm;
        }

        .banner-container img {
            max-width: 1000px;
            height: auto;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
        }

        .banner-container img:hover {
            transform: scale(1.02);
        }

        .report-header h1 {
            font-size: 14pt;
            font-weight: 700;
            color: var(--black);
            margin: 0 0 1mm 0;
            letter-spacing: 0.5px;
        }

        .patient-name {
            font-size: 12pt;
            font-weight: 700;
            color: var(--black);
            margin: 1mm 0;
            letter-spacing: 0.3px;
        }

        .report-header .subtitle, .report-header .date {
            font-size: 7pt;
            color: var(--gray);
            margin: 0.5mm 0;
        }

        .report-section {
            margin-bottom: 4mm;
        }

        .report-section h2 {
            font-size: 9pt;
            font-weight: 700;
            color: var(--primary-dark);
            background: var(--primary-light);
            border-left: 3px solid var(--primary);
            padding: 1.5mm 2mm;
            margin-bottom: 3mm;
            letter-spacing: 0.2px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2mm;
        }

        .info-grid h3 {
            font-size: 8pt;
            font-weight: 700;
            margin-bottom: 1mm;
            color: var(--black);
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 0.5mm;
        }

        .grades-table, .visit-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
            font-size: 7pt;
        }

        .grades-table th, .grades-table td,
        .visit-table th, .visit-table td {
            border: 1px solid var(--light-gray);
            padding: 1.5mm;
            text-align: left;
        }

        .grades-table th, .visit-table th {
            background: var(--light-gray);
            font-weight: 700;
            width: 30mm;
        }

        .grades-table td, .visit-table td {
            font-weight: 400;
        }

        .visit-table .highlight {
            background: #f0f7ff;
            font-weight: 600;
        }

        .comments {
            background: var(--white);
            padding: 2mm;
            border-left: 2px solid var(--accent);
            font-size: 7pt;
            color: var(--black);
            border-radius: 0;
            margin: 1mm 0;
        }

        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2mm;
            margin-bottom: 3mm;
            flex-grow: 1;
        }

        .chart-container {
            background: var(--white);
            border: 1px solid var(--light-gray);
            padding: 3mm;
            display: flex;
            flex-direction: column;
        }

        .chart-title {
            text-align: center;
            font-weight: 700;
            color: var(--black);
            font-size: 10pt;
            margin-bottom: 2mm;
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 0.5mm;
        }

        canvas {
            width: 100% !important;
            height: 35mm !important;
            max-height: 35mm !important;
        }

        .no-data {
            text-align: center;
            color: var(--gray);
            font-size: 7pt;
            padding: 3mm;
        }

        /* ── Visit stat boxes ── */
        .stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2mm; margin-bottom: 3mm; }
        .stat-box { text-align: center; padding: 2mm; border-radius: 3px; }
        .stat-box .s-val { font-size: 13pt; font-weight: 700; line-height: 1.2; }
        .stat-box .s-lbl { font-size: 7pt; color: var(--gray); }
        .stat-box.total   { background: #eff6ff; border: 1px solid #bfdbfe; }
        .stat-box.total   .s-val { color: var(--primary); }
        .stat-box.mild    { background: #d1fae5; border: 1px solid #6ee7b7; }
        .stat-box.mild    .s-val { color: #065f46; }
        .stat-box.moderate{ background: #fef3c7; border: 1px solid #fcd34d; }
        .stat-box.moderate .s-val { color: #92400e; }
        .stat-box.severe  { background: #fee2e2; border: 1px solid #fca5a5; }
        .stat-box.severe  .s-val { color: #991b1b; }

        /* ── Medical alert badges ── */
        .med-badge { display: inline-block; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; border-radius: 3px; padding: 0.5mm 1.5mm; font-size: 7pt; font-weight: 600; margin: 0.5mm; }
        .med-badge.allergy { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }

        /* ── Signature section ── */
        .signature-row { display: grid; grid-template-columns: 1fr 1fr; gap: 5mm; margin-top: 4mm; }
        .sig-block { border-top: 1px solid var(--black); padding-top: 1.5mm; font-size: 7pt; text-align: center; }
        .sig-block .sig-name { font-weight: 700; font-size: 8pt; }
        .sig-block .sig-role { color: var(--gray); }

        .text-muted {
            color: var(--gray);
            font-style: italic;
            font-size: 7pt;
        }

        /* ── Dark Mode ── */
        [data-theme="dark"] body              { background: hsl(222,47%,9%) !important; color: hsl(210,40%,96%) !important; }
        [data-theme="dark"] .card             { background: hsl(222,47%,13%) !important; border-color: hsl(222,30%,22%) !important; }
        [data-theme="dark"] .report-card      { background: hsl(222,47%,13%) !important; border-color: hsl(222,30%,25%) !important; color: hsl(210,40%,90%) !important; }
        [data-theme="dark"] .report-section h2 { background: hsl(201,85%,15%) !important; color: hsl(201,85%,75%) !important; border-color: hsl(201,85%,40%) !important; }
        [data-theme="dark"] .report-header    { border-color: hsl(222,30%,30%) !important; }
        [data-theme="dark"] .report-header h1,
        [data-theme="dark"] .patient-name     { color: hsl(210,40%,96%) !important; }
        [data-theme="dark"] .report-header .subtitle,
        [data-theme="dark"] .report-header .date { color: hsl(215,20%,65%) !important; }
        [data-theme="dark"] .grades-table th  { background: hsl(222,47%,20%) !important; color: hsl(210,40%,90%) !important; border-color: hsl(222,30%,28%) !important; }
        [data-theme="dark"] .grades-table td,
        [data-theme="dark"] .visit-table td   { border-color: hsl(222,30%,25%) !important; color: hsl(210,40%,85%) !important; }
        [data-theme="dark"] .visit-table th   { background: hsl(222,47%,20%) !important; color: hsl(210,40%,90%) !important; border-color: hsl(222,30%,28%) !important; }
        [data-theme="dark"] .visit-table .highlight { background: hsl(201,85%,15%) !important; }
        [data-theme="dark"] .chart-container  { background: hsl(222,47%,16%) !important; border-color: hsl(222,30%,25%) !important; }
        [data-theme="dark"] .chart-title      { color: hsl(210,40%,90%) !important; border-color: hsl(222,30%,28%) !important; }
        [data-theme="dark"] .comments         { background: hsl(222,47%,16%) !important; border-color: hsl(201,85%,40%) !important; color: hsl(210,40%,85%) !important; }
        [data-theme="dark"] .sig-block        { border-color: hsl(210,40%,70%) !important; color: hsl(210,40%,85%) !important; }
        [data-theme="dark"] .stat-box.total   { background: hsl(213,77%,15%) !important; border-color: hsl(213,77%,30%) !important; }
        [data-theme="dark"] .stat-box.mild    { background: hsl(152,74%,10%) !important; border-color: hsl(152,74%,25%) !important; }
        [data-theme="dark"] .stat-box.moderate{ background: hsl(38,92%,10%) !important;  border-color: hsl(38,92%,25%) !important; }
        [data-theme="dark"] .stat-box.severe  { background: hsl(0,84%,12%) !important;   border-color: hsl(0,84%,28%) !important; }
        [data-theme="dark"] .med-badge        { background: hsl(222,47%,20%) !important; border-color: hsl(222,30%,30%) !important; color: hsl(210,40%,85%) !important; }
        [data-theme="dark"] .med-badge.allergy{ background: hsl(0,84%,12%) !important;   border-color: hsl(0,84%,28%) !important; color: hsl(0,84%,80%) !important; }
        [data-theme="dark"] .info-grid h3     { color: hsl(210,40%,75%) !important; border-color: hsl(222,30%,28%) !important; }
        [data-theme="dark"] .page-hero        { box-shadow: 0 4px 20px rgba(0,0,0,.4); }

        @media (max-width: 992px) {
            .content { margin-left: var(--sidebar-collapsed-width); }
        }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
            .report-card { width: 100%; padding: 1rem; }
            .info-grid { grid-template-columns: 1fr; }
            .chart-row { grid-template-columns: 1fr; }
            .stat-row { grid-template-columns: repeat(2,1fr); }
            .signature-row { grid-template-columns: 1fr; }
            canvas { height: 80px !important; }
        }

        @media print {
            @page { margin: 8mm 10mm; size: A4 portrait; }
            body { background: #fff; }
            nav, .navbar, .main-navbar, #navbar, .top-header,
            .sidebar, .sidebar-overlay, .page-hero, .no-print { display: none !important; }
            .content { margin: 0 !important; padding: 0 !important; }
            .container-fluid { margin: 0 !important; padding: 0 !important; max-width: none; width: 100%; }
            .card { box-shadow: none !important; border: none !important; border-radius: 0 !important; }
            .report-card {
                width: 100%;
                padding: 0;
                border: none;
            }
            .report-section h2 {
                color: var(--primary-dark) !important;
                background: #f0f8ff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .stat-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            canvas { width: 100% !important; height: 35mm !important; max-height: 35mm !important; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">

                <!-- Page Hero (screen only, hidden on print) -->
                <div class="page-hero no-print">
                    <div class="hero-content">
                        <div class="hero-title"><i class="fas fa-file-medical me-2"></i>Health Report</div>
                        <div class="hero-subtitle"><?= htmlspecialchars($full_name) ?> &nbsp;·&nbsp; <?= htmlspecialchars($patient['category'] ?: '') ?></div>
                    </div>
                    <div class="hero-actions">
                        <a href="manage-patients.php" class="btn-hero btn-hero-back"><i class="fas fa-arrow-left me-1"></i> Back</a>
                        <a href="recent-visits.php?id=<?= $patient_id ?>" class="btn-hero btn-hero-visits"><i class="fas fa-list me-1"></i> Visits</a>
                        <button class="btn-hero btn-hero-pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf me-1"></i> PDF</button>
                        <button class="btn-hero btn-hero-print" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="report-card">

                            <!-- Report Header -->
                            <div class="report-header">
                                <div class="banner-container">
                                    <img src="../assets/img/ICC_BANNER.png" alt="Immaculate Conception College of Balayan, Inc.">
                                </div>
                                <h1>CLINICAL HEALTH REPORT</h1>
                                <div class="patient-name"><?= htmlspecialchars($full_name) ?></div>
                                <div class="subtitle"><?= htmlspecialchars($patient['category'] ?: '') ?><?= $patient['grade_year'] ? ' — ' . htmlspecialchars($patient['grade_year']) : '' ?><?= $patient['program_section'] ? ' · ' . htmlspecialchars($patient['program_section']) : '' ?></div>
                                <div class="date">Generated: <?= date('F j, Y') ?> &nbsp;·&nbsp; Immaculate Conception College Clinic</div>
                            </div>

                            <!-- Section 1: Patient Profile -->
                            <div class="report-section">
                                <h2>Patient Profile</h2>
                                <div class="info-grid">
                                    <div>
                                        <h3>Personal Information</h3>
                                        <table class="grades-table">
                                            <tr><th>Gender</th><td><?= htmlspecialchars($patient['gender'] ?: 'N/A') ?></td></tr>
                                            <tr><th>Category</th><td><?= htmlspecialchars($patient['category'] ?: 'N/A') ?></td></tr>
                                            <tr><th>Grade / Year</th><td><?= htmlspecialchars($patient['grade_year'] ?: 'N/A') ?></td></tr>
                                            <tr><th>Program / Section</th><td><?= htmlspecialchars($patient['program_section'] ?: 'N/A') ?></td></tr>
                                            <tr><th>Address</th><td><?= htmlspecialchars($patient['address'] ?: 'N/A') ?></td></tr>
                                        </table>
                                    </div>
                                    <div>
                                        <h3>Guardian &amp; Emergency Contacts</h3>
                                        <table class="grades-table">
                                            <tr><th>Guardian Name</th><td><?= htmlspecialchars($patient['guardian_name'] ?: 'N/A') ?></td></tr>
                                            <tr><th>Guardian Contact</th><td><?= htmlspecialchars($patient['guardian_contact'] ?: 'N/A') ?></td></tr>
                                            <tr><th>Emergency Contact</th><td><?= htmlspecialchars($patient['emergency_contact_name'] ?: 'N/A') ?></td></tr>
                                            <tr><th>Emergency Number</th><td><?= htmlspecialchars($patient['emergency_contact_number'] ?: 'N/A') ?></td></tr>
                                            <tr><th>Pediatrician</th><td><?= htmlspecialchars(trim(($patient['pediatrician_name'] ?: '') . ($patient['pediatrician_contact'] ? ' (' . $patient['pediatrician_contact'] . ')' : '')) ?: 'N/A') ?></td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Medical Background -->
                            <div class="report-section">
                                <h2>Medical Background</h2>
                                <div class="info-grid">
                                    <div>
                                        <h3>Known Allergies</h3>
                                        <?php if (!empty($patient['allergies'])): ?>
                                            <?php foreach (array_filter(array_map('trim', explode(',', $patient['allergies']))) as $allergy): ?>
                                                <span class="med-badge allergy"><?= htmlspecialchars($allergy) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No known allergies recorded</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3>Pre-existing Medical Conditions</h3>
                                        <?php if (!empty($patient['medical_conditions'])): ?>
                                            <?php foreach (array_filter(array_map('trim', explode(',', $patient['medical_conditions']))) as $cond): ?>
                                                <span class="med-badge"><?= htmlspecialchars($cond) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No pre-existing conditions recorded</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($patient['notes'])): ?>
                                <div style="margin-top:2mm;">
                                    <h3>Clinical Notes</h3>
                                    <div class="comments"><?= htmlspecialchars($patient['notes']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Section 3: Visit Summary Stats -->
                            <div class="report-section">
                                <h2>Visit Summary</h2>
                                <div class="stat-row">
                                    <div class="stat-box total">
                                        <div class="s-val"><?= $health_record['total_visits'] ?></div>
                                        <div class="s-lbl">Total Visits</div>
                                    </div>
                                    <div class="stat-box mild">
                                        <div class="s-val"><?= $health_record['mild_visits'] ?></div>
                                        <div class="s-lbl">Mild</div>
                                    </div>
                                    <div class="stat-box moderate">
                                        <div class="s-val"><?= $health_record['moderate_visits'] ?></div>
                                        <div class="s-lbl">Moderate</div>
                                    </div>
                                    <div class="stat-box severe">
                                        <div class="s-val"><?= $health_record['severe_visits'] ?></div>
                                        <div class="s-lbl">Severe</div>
                                    </div>
                                </div>
                                <div style="margin-bottom:2mm;">
                                    <strong style="font-size:7.5pt;">Last Visit:</strong>
                                    <span style="font-size:7.5pt;"><?= $health_record['last_visit_date'] ? date('F j, Y', strtotime($health_record['last_visit_date'])) : 'No visit recorded' ?></span>
                                    <?php if ($health_record['last_visit_reason']): ?>
                                        &nbsp;·&nbsp; <span style="font-size:7.5pt;color:var(--gray);"><?= htmlspecialchars($health_record['last_visit_reason']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Top Visit Reasons Table -->
                                <?php if (!empty($visit_reasons)): ?>
                                    <table class="visit-table">
                                        <thead>
                                            <tr><th style="width:70%;">Reason for Visit</th><th>No. of Visits</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($visit_reasons as $vr):
                                                $is_top = ($vr['reason'] === $most_frequent_reason); ?>
                                                <tr class="<?= $is_top ? 'highlight' : '' ?>">
                                                    <td><?= htmlspecialchars($vr['reason'] ?: 'N/A') ?></td>
                                                    <td><?= $vr['count'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="text-muted">No visit reasons recorded.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Section 4: Health Statistics Charts -->
                            <div class="report-section">
                                <h2>Health Statistics</h2>
                                <div class="chart-row">
                                    <div class="chart-container">
                                        <div class="chart-title">Condition Severity Distribution</div>
                                        <canvas id="severityChart"></canvas>
                                    </div>
                                    <div class="chart-container">
                                        <div class="chart-title">Monthly Visits — Last 6 Months</div>
                                        <canvas id="visitChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 5: Authorization / Signature -->
                            <div class="report-section" style="margin-top: 4mm;">
                                <h2>Authorization &amp; Certification</h2>
                                <div class="comments" style="margin-bottom:4mm;">
                                    I hereby certify that the information contained in this clinical health report is accurate and complete based on available records in the school clinic database as of <?= date('F j, Y') ?>.
                                </div>
                                <div class="signature-row">
                                    <div class="sig-block">
                                        <div style="height:10mm;"></div>
                                        <div class="sig-name">School Nurse / Physician</div>
                                        <div class="sig-role">Attending Clinic Staff</div>
                                    </div>
                                    <div class="sig-block">
                                        <div style="height:10mm;"></div>
                                        <div class="sig-name">Clinic Administrator</div>
                                        <div class="sig-role">Authorized Signatory</div>
                                    </div>
                                </div>
                            </div>

                        </div><!-- end .report-card -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded. Check CDN.');
                document.getElementById('severityChart').parentNode.innerHTML = '<p class="no-data">Chart failed to load</p>';
                document.getElementById('visitChart').parentNode.innerHTML = '<p class="no-data">Chart failed to load</p>';
                return;
            }

            const severityData = {
                mild: <?php echo $severity_data['Mild']; ?>,
                moderate: <?php echo $severity_data['Moderate']; ?>,
                severe: <?php echo $severity_data['Severe']; ?>
            };
            const monthlyData = {
                labels: [<?php echo "'" . implode("','", $monthly_labels) . "'"; ?>],
                counts: [<?php echo implode(',', $monthly_counts); ?>]
            };

            const severitySeries = [severityData.mild, severityData.moderate, severityData.severe];
            if (!severitySeries.some(value => value > 0)) {
                document.getElementById('severityChart').parentNode.innerHTML = '<p class="no-data">No severity data available</p>';
            } else {
                new Chart(document.getElementById('severityChart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Mild', 'Moderate', 'Severe'],
                        datasets: [{
                            label: 'Visits',
                            data: [severityData.mild, severityData.moderate, severityData.severe],
                            backgroundColor: ['#d1fae5', '#fef3c7', '#fee2e2'],
                            borderColor: ['#065f46', '#92400e', '#991b1b'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { 
                                    display: true, 
                                    text: 'Number of Visits', 
                                    font: { size: 12, family: 'Roboto', weight: '700' } 
                                },
                                ticks: { 
                                    font: { size: 10, family: 'Roboto' },
                                    color: '#000000'
                                }
                            },
                            x: {
                                title: { 
                                    display: true, 
                                    text: 'Severity', 
                                    font: { size: 12, family: 'Roboto', weight: '700' } 
                                },
                                ticks: { 
                                    font: { size: 10, family: 'Roboto' },
                                    color: '#000000'
                                }
                            }
                        },
                        plugins: {
                            legend: { 
                                display: false 
                            },
                            title: { 
                                display: false 
                            }
                        }
                    }
                });
            }

            if (!monthlyData.counts.some(value => value > 0)) {
                document.getElementById('visitChart').parentNode.innerHTML = '<p class="no-data">No visit data available</p>';
            } else {
                new Chart(document.getElementById('visitChart').getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: monthlyData.labels,
                        datasets: [{
                            label: 'Clinic Visits',
                            data: monthlyData.counts,
                            backgroundColor: 'rgba(15,115,186,.12)',
                            borderColor: '#0f73ba',
                            fill: true,
                            tension: 0.3,
                            pointBackgroundColor: '#0f73ba',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { 
                                    display: true, 
                                    text: 'Visits', 
                                    font: { size: 12, family: 'Roboto', weight: '700' } 
                                },
                                ticks: { 
                                    font: { size: 10, family: 'Roboto' },
                                    color: '#000000'
                                }
                            },
                            x: {
                                title: { 
                                    display: true, 
                                    text: 'Month', 
                                    font: { size: 12, family: 'Roboto', weight: '700' } 
                                },
                                ticks: { 
                                    font: { size: 10, family: 'Roboto' },
                                    color: '#000000'
                                }
                            }
                        },
                        plugins: {
                            legend: { 
                                display: false 
                            },
                            title: { 
                                display: false 
                            }
                        }
                    }
                });
            }
        });

        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const reportCard = document.querySelector('.report-card');

            setTimeout(() => {
                html2canvas(reportCard, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    logging: false
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

                    const pageW = pdf.internal.pageSize.getWidth();
                    const pageH = pdf.internal.pageSize.getHeight();
                    const margin = 10;
                    const usableW = pageW - margin * 2;
                    const usableH = pageH - margin * 2;

                    const canvasW = canvas.width;
                    const canvasH = canvas.height;
                    const ratio = canvasH / canvasW;
                    const imgH = usableW * ratio;

                    if (imgH <= usableH) {
                        // Fits on one page
                        pdf.addImage(imgData, 'PNG', margin, margin, usableW, imgH);
                    } else {
                        // Multi-page: slice canvas into page-height segments
                        let yOffset = 0;
                        const sliceH = Math.floor(canvasW * (usableH / usableW));
                        while (yOffset < canvasH) {
                            const sliceCanvas = document.createElement('canvas');
                            sliceCanvas.width = canvasW;
                            sliceCanvas.height = Math.min(sliceH, canvasH - yOffset);
                            const ctx = sliceCanvas.getContext('2d');
                            ctx.drawImage(canvas, 0, yOffset, canvasW, sliceCanvas.height, 0, 0, canvasW, sliceCanvas.height);
                            const sliceData = sliceCanvas.toDataURL('image/png');
                            const renderedH = (sliceCanvas.height / canvasW) * usableW;
                            pdf.addImage(sliceData, 'PNG', margin, margin, usableW, renderedH);
                            yOffset += sliceH;
                            if (yOffset < canvasH) pdf.addPage();
                        }
                    }

                    pdf.save('Health_Report_<?php echo str_replace(' ', '_', addslashes($full_name)); ?>.pdf');
                }).catch(error => {
                    console.error('Error generating PDF:', error);
                    alert('Failed to generate PDF. Please try again.');
                });
            }, 500);
        }
    </script>
</body>
</html>