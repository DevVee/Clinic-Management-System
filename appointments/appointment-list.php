<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Appointment List] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$selected_date   = isset($_GET['selected_date']) ? filter_var($_GET['selected_date'], FILTER_SANITIZE_STRING) : '';
$status_filter   = isset($_GET['status'])        ? filter_var($_GET['status'],        FILTER_SANITIZE_STRING) : '';
$category_filter = isset($_GET['category'])      ? filter_var($_GET['category'],      FILTER_SANITIZE_STRING) : '';

$valid_categories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni', 'Non-Student', 'Teacher', 'Non-Teaching', 'Staff', 'Other'];

$query = "
    SELECT id, patient_name, category, phone, appointment_date, appointment_time, reason, status, appointee
    FROM appointments
    WHERE 1=1
";
$params = [];
if ($selected_date) {
    $query .= " AND DATE(appointment_date) = ?";
    $params[] = $selected_date;
}
if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}
if ($category_filter && in_array($category_filter, $valid_categories)) {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}
$query .= " ORDER BY CASE WHEN appointment_date = '2025-09-02' THEN 0 ELSE 1 END, appointment_date DESC, appointment_time ASC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Appointment List] Fetched " . count($appointments) . " appointments");
} catch (Exception $e) {
    error_log("[SSCMS Appointment List] Query error: " . $e->getMessage());
    $appointments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System">
    <meta name="author" content="ICCB">
    <title>Manage Appointments - SSCMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include '../includes/sscmslogo.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* ═══════════════════════════════════════════
           DESIGN TOKENS — teal/blue matching SSCMS
        ═══════════════════════════════════════════ */
        :root {
            --primary:        hsl(201 85% 39%);
            --primary-dark:   hsl(201 85% 31%);
            --primary-light:  hsl(201 85% 94%);
            --primary-mid:    hsl(201 85% 50%);
            --secondary:      hsl(213 77% 54%);
            --success:        hsl(168 72% 42%);
            --success-light:  hsl(168 72% 93%);
            --warning:        hsl(38 92% 50%);
            --warning-light:  hsl(38 92% 93%);
            --danger:         hsl(0 84% 60%);
            --danger-light:   hsl(0 84% 93%);
            --accent:         hsl(258 80% 60%);
            --accent-light:   hsl(258 80% 94%);
            --foreground:     hsl(222 47% 17%);
            --text-secondary: hsl(215 16% 47%);
            --text-muted:     hsl(215 16% 65%);
            --border:         hsl(214 32% 91%);
            --background:     hsl(210 40% 98%);
            --card-bg:        #ffffff;
            --sidebar-width:          280px;
            --sidebar-collapsed-width: 70px;
            --header-height:  70px;
            --transition-speed: 0.3s;

            --grad-primary: linear-gradient(135deg, hsl(201 85% 39%), hsl(213 77% 48%));
            --grad-success:  linear-gradient(135deg, hsl(168 72% 38%), hsl(168 72% 52%));
            --grad-warning:  linear-gradient(135deg, hsl(38 92% 46%), hsl(38 92% 58%));
            --grad-danger:   linear-gradient(135deg, hsl(0 84% 55%), hsl(0 84% 68%));
            --grad-accent:   linear-gradient(135deg, hsl(258 80% 54%), hsl(258 80% 68%));
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--background);
            color: var(--foreground);
            margin: 0; padding: 0;
            font-size: 0.88rem;
            font-weight: 400;
            overflow-x: hidden;
        }
        h1,h2,h3,h4,h5,h6,
        .font-heading { font-family: 'Poppins', sans-serif; }

        /* ── Layout ── */
        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1.25rem 2rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
            background:
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><g fill="%230f73ba" fill-opacity="0.025"><path d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/></g></svg>');
        }
        .container-fluid { max-width: 1280px; padding: 0; }

        /* ── Page hero strip ── */
        .page-hero {
            background: var(--grad-primary);
            border-radius: 1.1rem;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.1rem;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before { content:''; position:absolute; top:-28px; right:-28px; width:110px; height:110px; border-radius:50%; background:rgba(255,255,255,0.07); }
        .page-hero::after  { content:''; position:absolute; bottom:-18px; left:38px; width:72px; height:72px; border-radius:50%; background:rgba(255,255,255,0.05); }
        .hero-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.65rem; }
        .hero-title { color:white; font-size:1.25rem; font-weight:700; margin:0 0 .2rem; font-family:'Poppins',sans-serif; display:flex; align-items:center; gap:.5rem; }
        .hero-sub   { color:rgba(255,255,255,.7); font-size:.78rem; }
        .hero-stat  {
            display:flex; align-items:center; gap:1rem;
        }
        .stat-pill {
            background:rgba(255,255,255,.15);
            backdrop-filter:blur(8px);
            color:white;
            font-family:'Poppins',sans-serif;
            font-size:.72rem; font-weight:600;
            padding:.3rem .8rem; border-radius:9999px;
            display:inline-flex; align-items:center; gap:.35rem;
        }
        .pulse-dot { width:7px; height:7px; background:hsl(144 100% 55%); border-radius:50%; animation:pulseDot 2s ease-in-out infinite; }
        @keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.5)} }

        /* ── Breadcrumb ── */
        .breadcrumb-bar {
            background: white;
            border: 1px solid var(--border);
            border-radius: .7rem;
            padding: .45rem 1rem;
            font-size: .77rem;
            margin-bottom: 1.1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
            font-family: 'DM Sans', sans-serif;
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
            margin-bottom: 1.5rem;
            overflow: hidden;
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
        .main-card-body { padding: 1rem 1.25rem 1.25rem; }

        /* ── Toolbar ── */
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-bottom: 1rem;
            align-items: center;
        }

        /* ── Buttons ── */
        .btn {
            font-family: 'Poppins', sans-serif;
            font-size: .78rem;
            font-weight: 600;
            border-radius: 9999px;
            padding: .38rem .9rem;
            height: auto;
            line-height: 1.5;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            transition: transform .18s, box-shadow .18s;
            border: none;
            cursor: pointer;
        }
        .btn:hover:not(:disabled) { transform: translateY(-1px); }

        .btn-primary-grad {
            background: var(--grad-primary);
            color: white;
            box-shadow: 0 3px 10px hsl(201 85% 39% / .28);
        }
        .btn-primary-grad:hover { box-shadow: 0 6px 16px hsl(201 85% 39% / .38); color:white; }

        .btn-ol-primary {
            background: transparent;
            color: var(--primary);
            border: 1.5px solid var(--primary);
        }
        .btn-ol-primary:hover { background: var(--primary-light); color: var(--primary); }

        .btn-success-grad {
            background: var(--grad-success);
            color: white;
            box-shadow: 0 3px 10px hsl(168 72% 42% / .25);
        }
        .btn-success-grad:hover { box-shadow: 0 6px 16px hsl(168 72% 42% / .38); color:white; }

        .btn-danger-grad {
            background: var(--grad-danger);
            color: white;
            box-shadow: 0 3px 10px hsl(0 84% 60% / .22);
        }
        .btn-danger-grad:hover { box-shadow: 0 6px 16px hsl(0 84% 60% / .35); color:white; }

        .btn-accent-grad {
            background: var(--grad-accent);
            color: white;
            box-shadow: 0 3px 10px hsl(258 80% 60% / .22);
        }
        .btn-accent-grad:hover { box-shadow: 0 6px 16px hsl(258 80% 60% / .35); color:white; }

        .btn-secondary-soft {
            background: hsl(215 16% 92%);
            color: var(--text-secondary);
        }
        .btn-secondary-soft:hover { background: hsl(215 16% 85%); color: var(--foreground); }

        /* ── Form controls ── */
        .form-control, .form-select {
            font-family: 'DM Sans', sans-serif;
            font-size: .82rem;
            border-radius: .6rem;
            border: 1.5px solid var(--border);
            background: white;
            color: var(--foreground);
            height: 34px;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px hsl(201 85% 39% / .12);
            outline: none;
        }
        .form-label {
            font-family: 'Poppins', sans-serif;
            font-size: .78rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: .28rem;
        }

        /* ── Table ── */
        .appt-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: .8rem;
        }
        .appt-table thead tr {
            background: hsl(210 40% 97%);
        }
        .appt-table th {
            font-family: 'Poppins', sans-serif;
            font-size: .74rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: .65rem .85rem;
            border-bottom: 1.5px solid var(--border);
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }
        .appt-table th:hover { color: var(--primary); }
        .appt-table td {
            padding: .6rem .85rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--foreground);
        }
        .appt-table tbody tr:last-child td { border-bottom: none; }
        .appt-table tbody tr {
            transition: background .15s;
        }
        .appt-table tbody tr:hover td { background: var(--primary-light); }

        /* Today row */
        .today-row td { background: hsl(168 72% 95%); font-weight: 500; }
        .today-row:hover td { background: hsl(168 72% 90%) !important; }

        /* ── Status badges ── */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: .28rem;
            padding: .22rem .65rem;
            border-radius: 9999px;
            font-family: 'Poppins', sans-serif;
            font-size: .7rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-pending  { background: var(--warning-light); color: hsl(38 92% 32%); }
        .badge-approved { background: var(--success-light); color: hsl(168 72% 28%); }
        .badge-rejected { background: var(--danger-light);  color: hsl(0 84% 40%); }
        .badge-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
        .badge-pending  .badge-dot { background: hsl(38 92% 50%); }
        .badge-approved .badge-dot { background: hsl(168 72% 42%); }
        .badge-rejected .badge-dot { background: hsl(0 84% 60%); }

        /* ── Appointee chip ── */
        .appt-chip {
            display: inline-flex; align-items: center; gap: .25rem;
            padding: .2rem .55rem;
            border-radius: 9999px;
            font-size: .7rem; font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }
        .appt-doctor  { background: hsl(213 77% 92%); color: hsl(213 77% 30%); }
        .appt-nurse   { background: hsl(168 72% 90%); color: hsl(168 72% 28%); }
        .appt-dentist { background: hsl(258 80% 92%); color: hsl(258 80% 35%); }

        /* ── View button in table ── */
        .btn-view {
            font-family: 'Poppins', sans-serif;
            font-size: .72rem; font-weight: 600;
            padding: .28rem .7rem;
            border-radius: 9999px;
            border: 1.5px solid var(--primary);
            background: transparent;
            color: var(--primary);
            cursor: pointer;
            transition: all .18s;
            white-space: nowrap;
        }
        .btn-view:hover { background: var(--primary); color: white; transform: translateY(-1px); }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: .75rem; opacity: .35; }

        /* ── Modal ── */
        .modal-content {
            border: 1px solid var(--border);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,.15);
        }
        .modal-header {
            background: var(--grad-primary);
            color: white;
            border-bottom: none;
            padding: .9rem 1.25rem;
        }
        .modal-title { font-family: 'Poppins', sans-serif; font-size: .95rem; font-weight: 700; }
        .modal-body { padding: 1.25rem; font-size: .84rem; font-family: 'DM Sans', sans-serif; }
        .modal-footer { border-top: 1px solid var(--border); padding: .75rem 1.25rem; gap: .5rem; }

        /* View mode detail rows */
        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            padding: .55rem 0;
            border-bottom: 1px solid hsl(214 32% 95%);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            font-family: 'Poppins', sans-serif;
            font-size: .74rem;
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 80px;
            flex-shrink: 0;
        }
        .detail-value {
            font-size: .84rem;
            color: var(--foreground);
        }

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
            font-family: 'DM Sans', sans-serif;
        }

        /* ── Flatpickr ── */
        .flatpickr-calendar { font-family: 'DM Sans', sans-serif; border-radius: .85rem; box-shadow: 0 10px 40px rgba(0,0,0,.14); }
        .flatpickr-day.selected { background: var(--primary) !important; border-color: var(--primary) !important; }

        /* ── Animations ── */
        @keyframes slideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        .su  { animation: slideUp .45s ease forwards; }
        .d1  { animation-delay:.06s; opacity:0; }
        .d2  { animation-delay:.12s; opacity:0; }
        .d3  { animation-delay:.18s; opacity:0; }

        /* ── Responsive ── */
        @media (max-width:992px) {
            :root { --sidebar-width: var(--sidebar-collapsed-width); }
        }
        @media (max-width:768px) {
            .content { margin-left: 0; padding: 1rem; }
            .appt-table th:nth-child(3),
            .appt-table td:nth-child(3),
            .appt-table th:nth-child(4),
            .appt-table td:nth-child(4),
            .appt-table th:nth-child(6),
            .appt-table td:nth-child(6) { display: none; }
            .hero-title { font-size: 1.1rem; }
        }

        @media print {
            .btn, .main-card-header, .modal, .toast-container,
            .breadcrumb-bar, .toolbar, .page-hero { display: none !important; }
            .content { margin-left: 0 !important; }
            .appt-table { font-size: .72rem; border: 1px solid #000; }
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
                                <i data-lucide="calendar-check" style="width:20px;height:20px;"></i>
                                Appointments List
                            </h1>
                            <p class="hero-sub">Manage, approve, and track all clinic appointments</p>
                        </div>
                        <div class="hero-stat">
                            <div class="stat-pill">
                                <span class="pulse-dot"></span>
                                <?= count($appointments) ?> Record<?= count($appointments) !== 1 ? 's' : '' ?>
                            </div>
                            <?php
                                $pending_count = count(array_filter($appointments, fn($a) => $a['status'] === 'pending'));
                            ?>
                            <?php if ($pending_count > 0): ?>
                            <div class="stat-pill" style="background:hsl(38 92% 50% / .25);">
                                <i data-lucide="clock" style="width:11px;height:11px;"></i>
                                <?= $pending_count ?> Pending
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ── Breadcrumb ── -->
                <div class="breadcrumb-bar su d1">
                    <a href="/dashboard.php"><i class="fas fa-home" style="font-size:.7rem;"></i> Dashboard</a>
                    <span class="sep">/</span>
                    <span class="cur">Appointments</span>
                </div>

                <!-- ── Main Card ── -->
                <div class="main-card su d2">
                    <div class="main-card-header">
                        <p class="main-card-title">
                            <i data-lucide="table-2" style="width:16px;height:16px;color:var(--primary);"></i>
                            All Appointments
                        </p>
                        <a href="/appointments/book_appointment.php" class="btn btn-primary-grad">
                            <i data-lucide="calendar-plus" style="width:13px;height:13px;"></i>
                            New Appointment
                        </a>
                    </div>

                    <div class="main-card-body">
                        <!-- ── Toolbar ── -->
                        <div class="toolbar">
                            <button class="btn btn-ol-primary" onclick="sortTable('date')">
                                <i data-lucide="calendar" style="width:12px;height:12px;"></i> Sort by Date
                            </button>
                            <button class="btn btn-ol-primary" onclick="sortTable('time')">
                                <i data-lucide="clock" style="width:12px;height:12px;"></i> Sort by Time
                            </button>
                            <button class="btn btn-ol-primary" onclick="sortTable('status')">
                                <i data-lucide="bar-chart-2" style="width:12px;height:12px;"></i> Sort by Status
                            </button>
                            <button class="btn btn-ol-primary" onclick="showAll()">
                                <i data-lucide="list" style="width:12px;height:12px;"></i> Show All
                            </button>
                            <button class="btn btn-ol-primary" data-bs-toggle="modal" data-bs-target="#dateFilterModal">
                                <i data-lucide="filter" style="width:12px;height:12px;"></i> Filter by Date
                            </button>
                            <select id="statusFilter" class="form-select" style="width:auto;height:34px;font-size:.78rem;" onchange="applyStatusFilter()">
                                <option value="">All Statuses</option>
                                <option value="pending"  <?= $status_filter === 'pending'  ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                            <select id="categoryFilter" class="form-select" style="width:auto;height:34px;font-size:.78rem;" onchange="applyCategoryFilter()">
                                <option value="">All Categories</option>
                                <?php foreach ($valid_categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- ── Table ── -->
                        <div class="table-responsive" style="border-radius:.75rem;border:1px solid var(--border);overflow:hidden;">
                            <table class="appt-table" id="appointmentTable">
                                <thead>
                                    <tr>
                                        <th onclick="sortTable('date')">
                                            <span style="display:flex;align-items:center;gap:.3rem;">
                                                <i data-lucide="calendar" style="width:12px;height:12px;"></i> Date
                                            </span>
                                        </th>
                                        <th onclick="sortTable('time')">
                                            <span style="display:flex;align-items:center;gap:.3rem;">
                                                <i data-lucide="clock" style="width:12px;height:12px;"></i> Time
                                            </span>
                                        </th>
                                        <th><span style="display:flex;align-items:center;gap:.3rem;"><i data-lucide="phone" style="width:12px;height:12px;"></i> Phone</span></th>
                                        <th><span style="display:flex;align-items:center;gap:.3rem;"><i data-lucide="tag" style="width:12px;height:12px;"></i> Category</span></th>
                                        <th><span style="display:flex;align-items:center;gap:.3rem;"><i data-lucide="user" style="width:12px;height:12px;"></i> Patient</span></th>
                                        <th>Appointee</th>
                                        <th>Reason</th>
                                        <th onclick="sortTable('status')">
                                            <span style="display:flex;align-items:center;gap:.3rem;">
                                                <i data-lucide="activity" style="width:12px;height:12px;"></i> Status
                                            </span>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($appointments)): ?>
                                    <tr>
                                        <td colspan="9">
                                            <div class="empty-state">
                                                <div><i class="fas fa-calendar-xmark"></i></div>
                                                <p style="font-family:'Poppins',sans-serif;font-weight:600;font-size:.88rem;color:var(--text-secondary);margin:.5rem 0 .25rem;">No appointments found</p>
                                                <p style="font-size:.78rem;">Try adjusting your filters or add a new appointment.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($appointments as $row): ?>
                                        <?php
                                        $time_12  = date("h:i A", strtotime($row['appointment_time']));
                                        $hour     = (int)substr($row['appointment_time'], 0, 2);
                                        if ($hour < 7 || $hour > 16) continue;
                                        $is_today = $row['appointment_date'] === '2025-09-02';
                                        $apptee_class = 'appt-' . strtolower($row['appointee']);
                                        $status_lc = strtolower($row['status']);
                                        ?>
                                        <tr class="appointment-row <?= $is_today ? 'today-row' : '' ?>"
                                            data-date="<?= htmlspecialchars($row['appointment_date']) ?>"
                                            data-time="<?= htmlspecialchars($row['appointment_time']) ?>"
                                            data-status="<?= htmlspecialchars($row['status']) ?>"
                                            data-id="<?= $row['id'] ?>">
                                            <td>
                                                <?php if ($is_today): ?>
                                                    <span style="display:inline-flex;align-items:center;gap:.3rem;">
                                                        <span style="width:6px;height:6px;background:hsl(168 72% 42%);border-radius:50%;flex-shrink:0;"></span>
                                                        <?= htmlspecialchars($row['appointment_date']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($row['appointment_date']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($time_12) ?></td>
                                            <td><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($row['category'] ?? 'N/A') ?></td>
                                            <td style="font-weight:500;"><?= htmlspecialchars($row['patient_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="appt-chip <?= $apptee_class ?>">
                                                    <?= htmlspecialchars($row['appointee']) ?>
                                                </span>
                                            </td>
                                            <td style="color:var(--text-secondary);"><?= htmlspecialchars($row['reason'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="badge-status badge-<?= $status_lc ?>">
                                                    <span class="badge-dot"></span>
                                                    <?= ucfirst($status_lc) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn-view view-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#actionModal"
                                                        data-id="<?= $row['id'] ?>"
                                                        data-patient="<?= htmlspecialchars($row['patient_name'] ?? '') ?>"
                                                        data-phone="<?= htmlspecialchars($row['phone'] ?? '') ?>"
                                                        data-category="<?= htmlspecialchars($row['category'] ?? '') ?>"
                                                        data-appointee="<?= htmlspecialchars($row['appointee']) ?>"
                                                        data-date="<?= htmlspecialchars($row['appointment_date']) ?>"
                                                        data-time="<?= htmlspecialchars($row['appointment_time']) ?>"
                                                        data-reason="<?= htmlspecialchars($row['reason'] ?? '') ?>"
                                                        data-status="<?= htmlspecialchars($row['status']) ?>">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <footer>
            <i data-lucide="hospital" style="width:12px;height:12px;display:inline-block;vertical-align:middle;margin-right:4px;"></i>
            IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. © SSCMS 2025
        </footer>
    </div>

    <!-- ══════════════ ACTION MODAL (same structure, new look) ══════════════ -->
    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalLabel">
                        <i data-lucide="calendar-days" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-right:.4rem;"></i>
                        Appointment Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <!-- View Mode -->
                    <div id="viewMode">
                        <div class="detail-row"><span class="detail-label">Patient</span>   <span class="detail-value" id="modalPatient"></span></div>
                        <div class="detail-row"><span class="detail-label">Category</span>  <span class="detail-value" id="modalCategory"></span></div>
                        <div class="detail-row"><span class="detail-label">Phone</span>     <span class="detail-value" id="modalPhone"></span></div>
                        <div class="detail-row"><span class="detail-label">Appointee</span> <span class="detail-value" id="modalAppointee"></span></div>
                        <div class="detail-row"><span class="detail-label">Date</span>      <span class="detail-value" id="modalDate"></span></div>
                        <div class="detail-row"><span class="detail-label">Time</span>      <span class="detail-value" id="modalTime"></span></div>
                        <div class="detail-row"><span class="detail-label">Reason</span>    <span class="detail-value" id="modalReason"></span></div>
                        <div class="detail-row"><span class="detail-label">Status</span>    <span class="detail-value" id="modalStatus"></span></div>
                    </div>
                    <!-- Edit Mode -->
                    <form id="editForm" style="display:none;">
                        <input type="hidden" name="id" id="editId">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-2">
                            <label for="editPatient" class="form-label">Patient</label>
                            <input type="text" class="form-control" id="editPatient" name="patient_name" required>
                        </div>
                        <div class="mb-2">
                            <label for="editCategory" class="form-label">Category</label>
                            <select class="form-select" id="editCategory" name="category">
                                <option value="">Select Category</option>
                                <?php foreach ($valid_categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="editPhone" class="form-label">Phone (Optional)</label>
                            <input type="tel" class="form-control" id="editPhone" name="phone">
                        </div>
                        <div class="mb-2">
                            <label for="editAppointee" class="form-label">Appointee</label>
                            <select class="form-select" id="editAppointee" name="appointee" required>
                                <option value="">Select Appointee</option>
                                <option value="Doctor">Doctor</option>
                                <option value="Nurse">Nurse</option>
                                <option value="Dentist">Dentist</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="editDate" class="form-label">Date</label>
                            <input type="text" class="form-control flatpickr" id="editDate" name="appointment_date" required>
                        </div>
                        <div class="mb-2">
                            <label for="editTime" class="form-label">Time</label>
                            <select class="form-select" id="editTime" name="appointment_time" required>
                                <option value="">Select Time</option>
                                <?php for ($h = 7; $h <= 16; $h++): ?>
                                    <?php
                                    $t   = sprintf("%02d:00:00", $h);
                                    $t30 = sprintf("%02d:30:00", $h);
                                    ?>
                                    <option value="<?= $t ?>"><?= date("h:i A", strtotime($t)) ?></option>
                                    <option value="<?= $t30 ?>"><?= date("h:i A", strtotime($t30)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="editReason" class="form-label">Reason</label>
                            <textarea class="form-control" id="editReason" name="reason" rows="3" style="height:auto;"></textarea>
                        </div>
                        <div class="mb-2">
                            <label for="editStatus" class="form-label">Status</label>
                            <select class="form-select" id="editStatus" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-accent-grad"    id="editBtn"    style="display:none;">
                        <i data-lucide="pencil" style="width:12px;height:12px;"></i> Edit
                    </button>
                    <button type="button" class="btn btn-primary-grad"   id="saveBtn"    style="display:none;">
                        <i data-lucide="save" style="width:12px;height:12px;"></i> Save
                    </button>
                    <button type="button" class="btn btn-secondary-soft" id="cancelBtn"  style="display:none;">Cancel</button>
                    <button type="button" class="btn btn-danger-grad"    id="deleteBtn">
                        <i data-lucide="trash-2" style="width:12px;height:12px;"></i> Delete
                    </button>
                    <button type="button" class="btn btn-success-grad approve-btn">
                        <i data-lucide="check" style="width:12px;height:12px;"></i> Approve
                    </button>
                    <button type="button" class="btn btn-danger-grad reject-btn">
                        <i data-lucide="x" style="width:12px;height:12px;"></i> Reject
                    </button>
                    <button type="button" class="btn btn-secondary-soft" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════ DATE FILTER MODAL ══════════════ -->
    <div class="modal fade" id="dateFilterModal" tabindex="-1" aria-labelledby="dateFilterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dateFilterModalLabel">
                        <i data-lucide="filter" style="width:15px;height:15px;display:inline-block;vertical-align:middle;margin-right:.35rem;"></i>
                        Filter by Date
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Select Date</label>
                    <input type="text" class="form-control flatpickr" id="filterDate" placeholder="YYYY-MM-DD">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary-soft" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary-grad" id="applyDateFilter">
                        <i data-lucide="check" style="width:12px;height:12px;"></i> Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════ TOAST ══════════════ -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="actionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
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
            console.log('[SSCMS Appointment List] Initialized');

            flatpickr(".flatpickr", {
                dateFormat: "Y-m-d",
                minDate: "2025-01-01",
                maxDate: new Date().setFullYear(new Date().getFullYear() + 1),
            });

            let sortState = { date: 'desc', time: 'asc', status: 'asc' };

            window.sortTable = function(column) {
                const tbody = document.querySelector("#appointmentTable tbody");
                const rows  = Array.from(document.querySelectorAll(".appointment-row"));
                if (column === 'date') {
                    sortState.date = sortState.date === 'asc' ? 'desc' : 'asc';
                    rows.sort((a, b) => {
                        const da = new Date(a.dataset.date), db = new Date(b.dataset.date);
                        return sortState.date === 'asc' ? da - db : db - da;
                    });
                } else if (column === 'time') {
                    sortState.time = sortState.time === 'asc' ? 'desc' : 'asc';
                    rows.sort((a, b) => sortState.time === 'asc'
                        ? a.dataset.time.localeCompare(b.dataset.time)
                        : b.dataset.time.localeCompare(a.dataset.time));
                } else if (column === 'status') {
                    sortState.status = sortState.status === 'asc' ? 'desc' : 'asc';
                    rows.sort((a, b) => sortState.status === 'asc'
                        ? a.dataset.status.localeCompare(b.dataset.status)
                        : b.dataset.status.localeCompare(a.dataset.status));
                }
                rows.forEach(r => tbody.appendChild(r));
            };

            window.showAll = function() {
                const url = new URL(window.location);
                url.searchParams.delete('selected_date');
                url.searchParams.delete('status');
                url.searchParams.delete('category');
                window.location.href = url;
            };

            window.applyStatusFilter = function() {
                const val = document.getElementById('statusFilter').value;
                const url = new URL(window.location);
                val ? url.searchParams.set('status', val) : url.searchParams.delete('status');
                window.location.href = url;
            };

            window.applyCategoryFilter = function() {
                const val = document.getElementById('categoryFilter').value;
                const url = new URL(window.location);
                val ? url.searchParams.set('category', val) : url.searchParams.delete('category');
                window.location.href = url;
            };

            document.getElementById('applyDateFilter')?.addEventListener('click', function() {
                const d = document.getElementById('filterDate').value;
                if (d) {
                    const url = new URL(window.location);
                    url.searchParams.set('selected_date', d);
                    window.location.href = url;
                } else {
                    showToast('Please select a date.', 'error');
                }
            });

            // ── Toast helper ──
            const toastEl   = document.getElementById('actionToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast     = new bootstrap.Toast(toastEl, { delay: 5000 });

            function showToast(msg, type = 'success') {
                toastEl.classList.remove('success', 'error');
                toastEl.classList.add(type);
                toastBody.textContent = msg;
                toast.show();
            }

            // ── Action modal ──
            const actionModal = document.getElementById('actionModal');
            const viewMode    = document.getElementById('viewMode');
            const editForm    = document.getElementById('editForm');
            const editBtn     = document.getElementById('editBtn');
            const saveBtn     = document.getElementById('saveBtn');
            const cancelBtn   = document.getElementById('cancelBtn');
            const deleteBtn   = document.getElementById('deleteBtn');

            actionModal.addEventListener('show.bs.modal', function(event) {
                const b = event.relatedTarget;
                const id        = b.getAttribute('data-id');
                const patient   = b.getAttribute('data-patient');
                const phone     = b.getAttribute('data-phone');
                const category  = b.getAttribute('data-category');
                const appointee = b.getAttribute('data-appointee');
                const date      = b.getAttribute('data-date');
                const time      = b.getAttribute('data-time');
                const reason    = b.getAttribute('data-reason');
                const status    = b.getAttribute('data-status');

                document.getElementById('modalPatient').textContent   = patient   || 'N/A';
                document.getElementById('modalCategory').textContent  = category  || 'N/A';
                document.getElementById('modalPhone').textContent     = phone     || 'N/A';
                document.getElementById('modalAppointee').textContent = appointee;
                document.getElementById('modalDate').textContent      = date;
                document.getElementById('modalTime').textContent      = time;
                document.getElementById('modalReason').textContent    = reason    || 'N/A';
                document.getElementById('modalStatus').textContent    = status;

                document.getElementById('editId').value         = id;
                document.getElementById('editPatient').value    = patient   || '';
                document.getElementById('editCategory').value   = category  || '';
                document.getElementById('editPhone').value      = phone     || '';
                document.getElementById('editAppointee').value  = appointee;
                document.getElementById('editDate').value       = date;
                document.getElementById('editTime').value       = time;
                document.getElementById('editReason').value     = reason    || '';
                document.getElementById('editStatus').value     = status;

                editBtn.style.display = status.toLowerCase() === 'approved' ? 'inline-flex' : 'none';

                viewMode.style.display = 'block';
                editForm.style.display = 'none';
                saveBtn.style.display  = 'none';
                cancelBtn.style.display= 'none';

                lucide.createIcons();
            });

            editBtn.addEventListener('click', function() {
                viewMode.style.display  = 'none';
                editForm.style.display  = 'block';
                editBtn.style.display   = 'none';
                deleteBtn.style.display = 'none';
                document.querySelector('.approve-btn').style.display = 'none';
                document.querySelector('.reject-btn').style.display  = 'none';
                saveBtn.style.display   = 'inline-flex';
                cancelBtn.style.display = 'inline-flex';
                flatpickr(document.getElementById('editDate'), {
                    dateFormat: 'Y-m-d',
                    minDate: 'today',
                    maxDate: new Date().setDate(new Date().getDate() + 30),
                });
            });

            cancelBtn.addEventListener('click', function() {
                viewMode.style.display  = 'block';
                editForm.style.display  = 'none';
                editBtn.style.display   = document.getElementById('modalStatus').textContent.toLowerCase() === 'approved' ? 'inline-flex' : 'none';
                deleteBtn.style.display = 'inline-flex';
                document.querySelector('.approve-btn').style.display = 'inline-flex';
                document.querySelector('.reject-btn').style.display  = 'inline-flex';
                saveBtn.style.display   = 'none';
                cancelBtn.style.display = 'none';
            });

            saveBtn.addEventListener('click', function() {
                const formData = new FormData(editForm);
                formData.append('action', 'edit');
                if (!editForm.checkValidity()) {
                    editForm.classList.add('was-validated');
                    showToast('Please fill in all required fields correctly.', 'error');
                    return;
                }
                $.ajax({
                    url: '/appointments/manage_appointment.php',
                    method: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast('Appointment updated successfully!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast(response.message || 'Failed to update appointment.', 'error');
                        }
                    },
                    error: function(jqXHR, textStatus) {
                        showToast('Error: ' + (jqXHR.responseText || textStatus), 'error');
                    }
                });
            });

            deleteBtn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this appointment?')) return;
                $.ajax({
                    url: '/appointments/manage_appointment.php',
                    method: 'POST',
                    data: {
                        id: document.getElementById('editId').value,
                        action: 'delete',
                        csrf_token: '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast('Appointment deleted successfully!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast(response.message || 'Failed to delete appointment.', 'error');
                        }
                    },
                    error: function(jqXHR, textStatus) {
                        showToast('Error: ' + (jqXHR.responseText || textStatus), 'error');
                    }
                });
            });

            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('approve-btn') || event.target.closest('.approve-btn') ||
                    event.target.classList.contains('reject-btn')  || event.target.closest('.reject-btn')) {
                    const btn    = event.target.closest('.approve-btn') || event.target.closest('.reject-btn') || event.target;
                    const id     = document.getElementById('editId').value;
                    const action = btn.classList.contains('approve-btn') ? 'approved' : 'rejected';
                    $.ajax({
                        url: '/appointments/update_appointment.php',
                        method: 'POST',
                        data: {
                            id: id,
                            status: action,
                            csrf_token: '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showToast(`Appointment ${action} successfully!`, 'success');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showToast(response.message || `Failed to ${action} appointment.`, 'error');
                            }
                        },
                        error: function(jqXHR, textStatus) {
                            showToast('Error: ' + (jqXHR.responseText || textStatus), 'error');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>