<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// ─── AJAX HANDLER ──────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    $searchTerm    = filter_input(INPUT_GET, 'search',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $reasonFilter  = filter_input(INPUT_GET, 'reason',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $programFilter = filter_input(INPUT_GET, 'program', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $yearFilter    = filter_input(INPUT_GET, 'year',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

    $query  = "SELECT DISTINCT p.id, p.first_name, p.middle_name, p.last_name,
                      p.gender, p.grade_year, p.program_section, p.guardian_contact, p.category
               FROM patients p";
    $params = [];

    if ($reasonFilter) {
        $query .= " INNER JOIN visits v ON p.id = v.patient_id
                    INNER JOIN visit_reasons vr ON v.id = vr.visit_id
                    WHERE vr.reason LIKE ?";
        $params[] = "%$reasonFilter%";
    } else {
        $query .= " WHERE 1=1";
    }

    if ($searchTerm) {
        $query  .= " AND (p.last_name LIKE ? OR p.first_name LIKE ? OR p.middle_name LIKE ?)";
        $params  = array_merge($params, ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
    }
    if ($programFilter) { $query .= " AND p.program_section = ?"; $params[] = $programFilter; }
    if ($yearFilter)    { $query .= " AND p.grade_year = ?";       $params[] = $yearFilter; }

    $query .= " ORDER BY p.last_name, p.first_name";

    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $patients, 'count' => count($patients)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ─── NORMAL PAGE LOAD ──────────────────────────────────────
$searchTerm    = filter_input(INPUT_GET, 'search',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$reasonFilter  = filter_input(INPUT_GET, 'reason',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$programFilter = filter_input(INPUT_GET, 'program', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$yearFilter    = filter_input(INPUT_GET, 'year',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Search Patients">
    <meta name="author" content="ICCB">
    <title>Search Patients - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>
    <style>
        /* ══════════════════════════════════════
           SSCMS — Search Patients
           ══════════════════════════════════════ */
        :root {
            --primary:       #0f73ba;
            --primary-dark:  #0d5a94;
            --primary-light: #e0f2fe;
            --accent:        #2c7be5;
            --emerald:       #059669;
            --amber:         #d97706;
            --rose:          #dc2626;
            --surface:       #ffffff;
            --surface-2:     #f8fafc;
            --surface-3:     #f0f7ff;
            --border:        #e2e8f0;
            --border-focus:  #0f73ba;
            --text-1:        #1e293b;
            --text-2:        #475569;
            --text-3:        #94a3b8;
            --sidebar-w:     260px;
            --radius:        12px;
            --radius-sm:     8px;
            --shadow-sm:     0 1px 3px rgba(0,0,0,.06);
            --shadow-md:     0 4px 16px rgba(15,115,186,.10);
            --shadow-lg:     0 12px 40px rgba(15,115,186,.14);
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
            padding: 20px 24px 40px;
            min-height: 100vh;
            transition: margin-left .3s;
        }
        @media (max-width:992px) { .content { margin-left: var(--sidebar-collapsed-width); } }
        @media (max-width:768px) { .content { margin-left: 0; padding: 12px 16px 32px; } }

        /* ─── Page Header ─── */
        .page-header {
            margin-bottom: 24px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            animation: fadeUp .45s both;
        }

        .page-heading {
            font-family: 'Poppins', sans-serif;
            font-size: 1.45rem;
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
        .breadcrumb-trail .sep { opacity: .5; }

        /* ─── Search Card ─── */
        .search-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 2px solid #b8c8d8;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            overflow: hidden;
            animation: fadeUp .5s .08s both;
        }

        .search-card-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 60%, var(--accent) 100%);
            padding: 20px 26px;
            position: relative;
            overflow: hidden;
        }

        .search-card-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .search-card-header::after {
            content: '';
            position: absolute;
            right: -40px; top: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
        }

        .header-inner {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon {
            width: 44px; height: 44px;
            background: rgba(255,255,255,.18);
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #fff;
            flex-shrink: 0;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,.2);
        }

        .header-text h5 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.05rem;
            font-weight: 600;
            color: #fff;
            margin: 0 0 2px;
        }

        .header-text p {
            font-size: .78rem;
            color: rgba(255,255,255,.75);
            margin: 0;
        }

        .search-card-body { padding: 24px 26px; }

        /* ─── Form Controls ─── */
        .filter-label {
            font-size: .75rem;
            font-weight: 600;
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 7px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-label i { color: var(--primary); opacity: .7; font-size: .7rem; }

        .form-control, .form-select {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            font-family: 'DM Sans', sans-serif;
            font-size: .875rem;
            padding: 0 14px;
            height: 40px;
            line-height: 40px;
            color: var(--text-1);
            background: var(--surface);
            transition: border-color .2s, box-shadow .2s;
            box-sizing: border-box;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(14,165,233,.15);
            outline: none;
        }

        .form-control::placeholder { color: var(--text-3); }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: .8rem;
            pointer-events: none;
            transition: color .2s;
            z-index: 2;
        }

        .input-wrap .form-control {
            padding-left: 36px;
            padding-right: 14px;
        }

        .input-wrap:focus-within .input-icon { color: var(--primary); }

        /* ─── Buttons — same height as inputs (40px total = 38px + 2px border) ─── */
        .btn-search,
        .btn-clear {
            height: 40px;          /* matches input: 9px top + 9px bottom + line-height ~20px + 2px border */
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: .875rem;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            white-space: nowrap;
            transition: background .22s, box-shadow .22s, transform .15s, border-color .22s, color .22s;
            line-height: 1;
        }

        /* Icon inside buttons — constrain so FA icons don't override height */
        .btn-search i,
        .btn-clear i {
            font-size: .8rem;
            line-height: 1;
            width: 14px;
            text-align: center;
            flex-shrink: 0;
        }

        .btn-search {
            min-width: 110px;
            gap: 7px;
            background: var(--primary);
            color: #fff;
            border: 2px solid var(--primary-dark);
            overflow: hidden;
        }

        .btn-search:hover:not(:disabled) { background: var(--primary-dark); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-search:active  { transform: none; }
        .btn-search:disabled { opacity: .7; cursor: not-allowed; transform: none; }

        .btn-clear {
            width: 40px;
            min-width: 40px;
            background: var(--surface);
            color: var(--text-2);
            border: 2px solid #c8d3df;
        }

        .btn-clear:hover { border-color: var(--rose); color: var(--rose); background: #fff1f2; }

        /* ─── Results Section ─── */
        .results-section {
            animation: fadeUp .5s .15s both;
        }

        .results-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .results-title {
            font-family: 'Poppins', sans-serif;
            font-size: .85rem;
            font-weight: 600;
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: .06em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .results-title::after {
            content: '';
            width: 80px;
            height: 1px;
            background: var(--border);
        }

        .result-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: .75rem;
            font-weight: 600;
            background: var(--primary-light);
            color: var(--primary);
            opacity: 0;
            transform: scale(.9);
            transition: all .3s;
        }

        .result-count-badge.show {
            opacity: 1;
            transform: scale(1);
        }

        /* ─── Table Card ─── */
        .table-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 2px solid #b8c8d8;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: box-shadow .25s;
        }

        .table-card:hover { box-shadow: var(--shadow-md); }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead th {
            padding: 11px 18px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--text-3);
            background: var(--surface-3);
            border-bottom: 1.5px solid var(--border);
            white-space: nowrap;
        }

        .data-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s, transform .15s;
        }

        .data-table tbody tr:last-child { border-bottom: none; }

        .data-table tbody tr:hover { background: #f8fbff; }

        .data-table td {
            padding: 12px 18px;
            font-size: .845rem;
            color: var(--text-1);
            vertical-align: middle;
        }

        /* ─── Patient Cell ─── */
        .patient-cell {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .p-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .72rem;
            font-weight: 700;
            flex-shrink: 0;
            letter-spacing: .02em;
        }

        .p-name {
            font-weight: 600;
            font-size: .855rem;
            color: var(--text-1);
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ─── Gender pill ─── */
        .gender-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 600;
        }

        .gender-pill.male   { background: #dbeafe; color: #1d4ed8; }
        .gender-pill.female { background: #fce7f3; color: #be185d; }
        .gender-pill.other  { background: var(--surface-3); color: var(--text-2); }

        /* ─── Category/Program pill ─── */
        .tag-pill {
            display: inline-flex;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 600;
            background: var(--primary-light);
            color: var(--primary);
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ─── Log Visit Button ─── */
        .btn-log {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 7px;
            font-size: .78rem;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            background: var(--primary);
            color: #fff;
            text-decoration: none;
            border: none;
            transition: all .2s;
            white-space: nowrap;
        }

        .btn-log:hover {
            background: var(--primary-dark);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(3,105,161,.3);
        }

        /* ─── Empty / Loading States ─── */
        .state-box {
            padding: 52px 20px;
            text-align: center;
        }

        .state-icon-wrap {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: var(--surface-3);
            border: 2px dashed var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            color: var(--text-3);
            margin: 0 auto 16px;
        }

        .state-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-2);
            margin-bottom: 6px;
        }

        .state-sub {
            font-size: .82rem;
            color: var(--text-3);
        }

        /* ─── Skeleton Loader ─── */
        .skeleton-row td { padding: 14px 18px; }

        .skeleton-block {
            height: 14px;
            border-radius: 6px;
            background: linear-gradient(90deg, #f1f5f9 25%, #e8edf3 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
        }

        .skeleton-circle {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(90deg, #f1f5f9 25%, #e8edf3 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            flex-shrink: 0;
        }

        .skeleton-cell { display: flex; align-items: center; gap: 11px; }

        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* ─── Search Progress Bar ─── */
        .search-progress {
            height: 3px;
            background: var(--border);
            border-radius: 0 0 0 0;
            overflow: hidden;
            opacity: 0;
            transition: opacity .2s;
        }

        .search-progress.active { opacity: 1; }

        .search-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 3px;
            transition: width .1s linear;
        }

        /* ─── Toast ─── */
        .toast-wrap {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1090;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .s-toast {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            font-size: .85rem;
            min-width: 260px;
            animation: slideInRight .3s both;
        }

        .s-toast.success { border-left: 4px solid var(--emerald); }
        .s-toast.error   { border-left: 4px solid var(--rose); }
        .s-toast.warning { border-left: 4px solid var(--amber); }

        .toast-ico { font-size: 1rem; flex-shrink: 0; }
        .s-toast.success .toast-ico { color: var(--emerald); }
        .s-toast.error   .toast-ico { color: var(--rose); }
        .s-toast.warning .toast-ico { color: var(--amber); }

        /* ─── Modal ─── */
        .modal-content { border: none; border-radius: var(--radius); overflow: hidden; }
        .modal-header.success-h { background: linear-gradient(135deg, var(--emerald), #34d399); color: #fff; border: none; padding: 18px 22px; }
        .modal-header.success-h .btn-close { filter: invert(1); }
        .modal-body { padding: 24px; }
        .modal-footer { border-top: 1px solid var(--border); padding: 14px 22px; }

        /* ─── Animations ─── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: none; }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: none; }
        }

        .fade-in { animation: fadeUp .35s both; }

        /* ─── Pagination ─── */
        .pagination-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            background: var(--surface-2);
            flex-wrap: wrap;
            gap: 10px;
        }

        .pagination-info { font-size: .78rem; color: var(--text-3); }
        .pagination-info strong { color: var(--text-2); }

        .page-btns { display: flex; gap: 4px; }

        .page-btn {
            width: 30px; height: 30px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: var(--surface);
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-2);
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-btn:hover, .page-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .page-btn:disabled { opacity: .4; cursor: not-allowed; }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>

            <!-- ══ Toast Area ══ -->
            <div class="toast-wrap" id="toastWrap">
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="s-toast error">
                        <i class="fas fa-circle-xmark toast-ico"></i>
                        <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['sms_status']) && strpos($_SESSION['sms_status'], 'Failed') === 0): ?>
                    <div class="s-toast warning">
                        <i class="fas fa-triangle-exclamation toast-ico"></i>
                        <span><?= htmlspecialchars($_SESSION['sms_status']) ?></span>
                    </div>
                    <?php unset($_SESSION['sms_status']); ?>
                <?php endif; ?>
            </div>

            <!-- ══ Success Modal ══ -->
            <?php if (isset($_SESSION['success_message'])):
                $sms_guardian = $_SESSION['sms_status']          ?? null;
                $sms_adviser  = $_SESSION['sms_status_adviser']  ?? null;
                $sms_guard    = $_SESSION['sms_status_guard']     ?? null;
                $success_msg  = $_SESSION['success_message'];
                unset($_SESSION['success_message'], $_SESSION['sms_status'], $_SESSION['sms_status_adviser'], $_SESSION['sms_status_guard']);
            ?>
            <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
                    <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.18);">

                        <!-- Green gradient header -->
                        <div style="background:linear-gradient(135deg,#059669 0%,#10b981 60%,#34d399 100%);padding:28px 24px 20px;text-align:center;position:relative;overflow:hidden;">
                            <div style="position:absolute;top:-30px;right:-30px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.08);"></div>
                            <div style="position:absolute;bottom:-20px;left:-20px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.06);"></div>
                            <div style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:1.6rem;color:#fff;position:relative;z-index:1;">
                                <i class="fas fa-check"></i>
                            </div>
                            <h5 style="font-family:'Poppins',sans-serif;font-size:1.1rem;font-weight:700;color:#fff;margin:0 0 4px;position:relative;z-index:1;">Visit Logged Successfully!</h5>
                            <p style="font-size:.8rem;color:rgba(255,255,255,.8);margin:0;position:relative;z-index:1;"><?= htmlspecialchars($success_msg) ?></p>
                        </div>

                        <!-- SMS Status body -->
                        <div style="padding:20px 24px;">
                            <p style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-3);margin-bottom:12px;">SMS Notifications</p>

                            <?php
                            $sms_rows = [
                                ['Guardian / Patient', $sms_guardian, 'fa-user'],
                                ['Class Adviser',      $sms_adviser,  'fa-chalkboard-user'],
                                ['Security Guard',     $sms_guard,    'fa-shield-halved'],
                            ];
                            foreach ($sms_rows as [$label, $status, $icon]):
                                if (!$status) continue;
                                $ok      = stripos($status, 'successful') !== false;
                                $bgColor = $ok ? '#f0fdf4' : '#fff7ed';
                                $bdColor = $ok ? '#bbf7d0' : '#fed7aa';
                                $icColor = $ok ? '#16a34a' : '#ea580c';
                                $icName  = $ok ? 'fa-circle-check' : 'fa-circle-exclamation';
                            ?>
                            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;border:1.5px solid <?= $bdColor ?>;background:<?= $bgColor ?>;margin-bottom:8px;">
                                <div style="width:30px;height:30px;border-radius:7px;background:<?= $ok ? '#dcfce7' : '#ffedd5' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas <?= $icon ?>" style="font-size:.75rem;color:<?= $icColor ?>;"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:.75rem;font-weight:600;color:var(--text-2);"><?= $label ?></div>
                                    <div style="font-size:.72rem;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($status) ?></div>
                                </div>
                                <i class="fas <?= $icName ?>" style="color:<?= $icColor ?>;font-size:.85rem;flex-shrink:0;"></i>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Footer -->
                        <div style="padding:0 24px 20px;display:flex;gap:8px;justify-content:flex-end;">
                            <a href="/dashboard.php"
                               style="height:38px;padding:0 16px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);color:var(--text-2);font-size:.835rem;font-weight:600;display:inline-flex;align-items:center;text-decoration:none;transition:all .2s;"
                               onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
                               onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-2)'">
                                Dashboard
                            </a>
                            <button type="button" class="btn-search" data-bs-dismiss="modal"
                                    style="height:38px;padding:0 20px;width:auto;min-width:0;">
                                Log Another
                            </button>
                        </div>

                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ══ Page Header ══ -->
            <div class="page-header">
                <div>
                    <h1 class="page-heading">
                        Search Patients
                        <span>Find and log a patient visit</span>
                    </h1>
                </div>
                <div class="breadcrumb-trail">
                    <a href="/dashboard.php"><i class="fas fa-house-chimney"></i> Dashboard</a>
                    <span class="sep">/</span>
                    <span>Search Patients</span>
                </div>
            </div>

            <!-- ══ Search Card ══ -->
            <div class="search-card">
                <div class="search-card-header">
                    <div class="header-inner">
                        <div class="header-icon"><i class="fas fa-magnifying-glass"></i></div>
                        <div class="header-text">
                            <h5>Find a Patient</h5>
                            <p>Search by name, filter by program or year level</p>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="search-progress" id="searchProgress">
                    <div class="search-progress-bar" id="searchProgressBar"></div>
                </div>

                <div class="search-card-body">
                    <div class="row g-3 align-items-end">

                        <!-- Name Search -->
                        <div class="col-md-4 col-sm-12">
                            <label class="filter-label"><i class="fas fa-user"></i> Patient Name</label>
                            <div class="input-wrap">
                                <i class="fas fa-search input-icon"></i>
                                <input type="text" id="searchPatient" class="form-control"
                                    placeholder="Search by last, first, or middle name…"
                                    value="<?= htmlspecialchars($searchTerm) ?>"
                                    autocomplete="off">
                            </div>
                        </div>

                        <!-- Program Filter -->
                        <div class="col-md-3 col-sm-6">
                            <label class="filter-label"><i class="fas fa-building-columns"></i> Program / Section</label>
                            <div class="input-wrap">
                                <i class="fas fa-chevron-down input-icon" style="right:12px;left:auto;"></i>
                                <select id="programFilter" class="form-select" style="padding-right:32px;">
                                    <option value="">All Programs</option>
                                    <?php
                                    $programs = $conn->query("SELECT DISTINCT program_section FROM patients WHERE program_section IS NOT NULL ORDER BY program_section");
                                    while ($row = $programs->fetch(PDO::FETCH_ASSOC)) {
                                        $sel = ($row['program_section'] === $programFilter) ? 'selected' : '';
                                        echo '<option value="'.htmlspecialchars($row['program_section']).'" '.$sel.'>'.htmlspecialchars($row['program_section']).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Year Filter -->
                        <div class="col-md-2 col-sm-6">
                            <label class="filter-label"><i class="fas fa-layer-group"></i> Grade / Year</label>
                            <div class="input-wrap">
                                <i class="fas fa-chevron-down input-icon" style="right:12px;left:auto;"></i>
                                <select id="yearFilter" class="form-select" style="padding-right:32px;">
                                    <option value="">All Years</option>
                                    <?php
                                    $years = $conn->query("SELECT DISTINCT grade_year FROM patients WHERE grade_year IS NOT NULL ORDER BY grade_year");
                                    while ($row = $years->fetch(PDO::FETCH_ASSOC)) {
                                        $sel = ($row['grade_year'] === $yearFilter) ? 'selected' : '';
                                        echo '<option value="'.htmlspecialchars($row['grade_year']).'" '.$sel.'>'.htmlspecialchars($row['grade_year']).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="col-md-3 col-sm-12">
                            <label class="filter-label" style="opacity:0;">Actions</label>
                            <div class="d-flex gap-2">
                                <button type="button" id="searchBtn" class="btn-search" style="flex:1;">
                                    <span id="searchBtnText">Search</span>
                                </button>
                                <button type="button" id="clearBtn" class="btn-clear">
                                    Clear
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Keyboard hint -->
                    <p style="margin-top:12px;font-size:.73rem;color:var(--text-3);">
                        <i class="fas fa-keyboard" style="margin-right:4px;"></i>
                        Tip: Press <kbd style="background:var(--surface-3);border:1px solid var(--border);border-radius:4px;padding:1px 6px;font-size:.7rem;">Enter</kbd> to search quickly
                    </p>
                </div>
            </div>

            <!-- ══ Results ══ -->
            <div class="results-section" id="resultsSection">
                <div class="results-header">
                    <div class="results-title">Results</div>
                    <span class="result-count-badge" id="resultCountBadge">
                        <i class="fas fa-users fa-xs"></i>
                        <span id="resultCountText">0 patients found</span>
                    </span>
                </div>

                <div class="table-card" id="tableCard">
                    <!-- Initial empty state -->
                    <div class="state-box" id="initialState">
                        <div class="state-icon-wrap">
                            <i class="fas fa-magnifying-glass-plus"></i>
                        </div>
                        <div class="state-title">Search to find patients</div>
                        <div class="state-sub">Use the filters above to search the patient database</div>
                    </div>

                    <!-- Table (hidden until results) -->
                    <div id="tableWrapper" style="display:none;">
                        <table class="data-table" id="patientsTable">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Gender</th>
                                    <th>Grade / Year</th>
                                    <th>Program / Section</th>
                                    <th>Guardian Contact</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody"></tbody>
                        </table>
                        <div class="pagination-wrap">
                            <div class="pagination-info" id="paginationInfo">Showing — results</div>
                            <div class="page-btns" id="pageBtns"></div>
                        </div>
                    </div>

                    <!-- No results -->
                    <div class="state-box" id="noResultsState" style="display:none;">
                        <div class="state-icon-wrap">
                            <i class="fas fa-users-slash"></i>
                        </div>
                        <div class="state-title">No patients found</div>
                        <div class="state-sub">Try adjusting your search or filters</div>
                    </div>

                    <!-- Error state -->
                    <div class="state-box" id="errorState" style="display:none;">
                        <div class="state-icon-wrap" style="border-color:var(--rose);">
                            <i class="fas fa-circle-xmark" style="color:var(--rose);"></i>
                        </div>
                        <div class="state-title">Search failed</div>
                        <div class="state-sub" id="errorMsg">Something went wrong. Please try again.</div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    /* ══════════════════════════════════════
       SSCMS Search Patients — AJAX Engine
       ══════════════════════════════════════ */

    const ROWS_PER_PAGE = 10;
    let allPatients     = [];
    let currentPage     = 1;
    let searchTimeout   = null;
    let progressTimer   = null;

    // ─── Helper: initials from name ─────────
    function initials(name) {
        const parts = name.trim().split(' ').filter(Boolean);
        if (parts.length >= 2) return (parts[0][0] + parts[parts.length-1][0]).toUpperCase();
        return name.slice(0,2).toUpperCase();
    }

    // ─── Helper: gender pill ────────────────
    function genderPill(g) {
        const lower = (g||'').toLowerCase();
        const cls   = lower === 'male' ? 'male' : lower === 'female' ? 'female' : 'other';
        const icon  = lower === 'male' ? 'mars' : lower === 'female' ? 'venus' : 'genderless';
        return `<span class="gender-pill ${cls}"><i class="fas fa-${icon}"></i> ${g||'—'}</span>`;
    }

    // ─── Progress Bar ────────────────────────
    function startProgress() {
        const bar  = document.getElementById('searchProgressBar');
        const wrap = document.getElementById('searchProgress');
        wrap.classList.add('active');
        bar.style.width = '0%';

        let pct = 0;
        clearInterval(progressTimer);
        progressTimer = setInterval(() => {
            // Fast to 70%, then slow until done
            const speed = pct < 70 ? 3 : .4;
            pct = Math.min(pct + speed, 90);
            bar.style.width = pct + '%';
        }, 50);
    }

    function finishProgress() {
        clearInterval(progressTimer);
        const bar  = document.getElementById('searchProgressBar');
        const wrap = document.getElementById('searchProgress');
        bar.style.transition = 'width .25s ease';
        bar.style.width = '100%';
        setTimeout(() => {
            wrap.classList.remove('active');
            bar.style.width = '0%';
            bar.style.transition = '';
        }, 400);
    }

    // ─── Skeleton Rows ───────────────────────
    function skeletonRows() {
        let html = '';
        for (let i = 0; i < 5; i++) {
            const w1 = [120,140,160][i%3], w2 = [60,80][i%2], w3 = [50,70][i%2];
            html += `
            <tr class="skeleton-row">
                <td><div class="skeleton-cell">
                    <div class="skeleton-circle"></div>
                    <div class="skeleton-block" style="width:${w1}px;"></div>
                </div></td>
                <td><div class="skeleton-block" style="width:${w2}px;"></div></td>
                <td><div class="skeleton-block" style="width:${w3}px;"></div></td>
                <td><div class="skeleton-block" style="width:100px;"></div></td>
                <td><div class="skeleton-block" style="width:90px;"></div></td>
                <td><div class="skeleton-block" style="width:70px;height:28px;border-radius:7px;"></div></td>
            </tr>`;
        }
        return html;
    }

    // ─── Render Table Page ───────────────────
    function renderPage(page) {
        currentPage = page;
        const start  = (page - 1) * ROWS_PER_PAGE;
        const slice  = allPatients.slice(start, start + ROWS_PER_PAGE);
        const search = encodeURIComponent($('#searchPatient').val());
        const prog   = encodeURIComponent($('#programFilter').val());
        const yr     = encodeURIComponent($('#yearFilter').val());

        let rows = '';
        slice.forEach((p, idx) => {
            const fullName = `${p.last_name}, ${p.first_name}${p.middle_name ? ' '+p.middle_name : ''}`;
            const av = initials(`${p.first_name} ${p.last_name}`);
            const gPill = genderPill(p.gender);
            const logUrl = `log-visit.php?patient_id=${p.id}&search=${search}&program=${prog}&year=${yr}`;

            rows += `
            <tr class="fade-in" style="animation-delay:${idx * 0.04}s">
                <td>
                    <div class="patient-cell">
                        <div class="p-avatar">${av}</div>
                        <span class="p-name" title="${fullName}">${fullName}</span>
                    </div>
                </td>
                <td>${gPill}</td>
                <td>${p.grade_year ? '<span class="tag-pill">'+escHtml(p.grade_year)+'</span>' : '<span style="color:var(--text-3)">—</span>'}</td>
                <td>${p.program_section ? '<span class="tag-pill" title="'+escHtml(p.program_section)+'">'+escHtml(p.program_section)+'</span>' : '<span style="color:var(--text-3)">—</span>'}</td>
                <td style="font-size:.8rem;color:var(--text-2);">${p.guardian_contact ? '<i class="fas fa-phone fa-xs" style="color:var(--text-3);margin-right:4px;"></i>'+escHtml(p.guardian_contact) : '—'}</td>
                <td>
                    <a href="${logUrl}" class="btn-log">
                        <i class="fas fa-clipboard-list"></i> Log Visit
                    </a>
                </td>
            </tr>`;
        });

        $('#tableBody').html(rows);
        renderPagination();

        const end = Math.min(start + ROWS_PER_PAGE, allPatients.length);
        $('#paginationInfo').html(`Showing <strong>${start+1}–${end}</strong> of <strong>${allPatients.length}</strong> patients`);
    }

    function renderPagination() {
        const total = Math.ceil(allPatients.length / ROWS_PER_PAGE);
        if (total <= 1) { $('#pageBtns').html(''); return; }

        let html = `<button class="page-btn" onclick="renderPage(${currentPage-1})" ${currentPage===1?'disabled':''}>
                        <i class="fas fa-chevron-left" style="font-size:.65rem;"></i>
                    </button>`;

        for (let i = 1; i <= total; i++) {
            if (total > 7 && i > 2 && i < total - 1 && Math.abs(i - currentPage) > 1) {
                if (i === 3 || i === total-2) html += `<button class="page-btn" disabled style="border:none;background:transparent;">…</button>`;
                continue;
            }
            html += `<button class="page-btn ${i===currentPage?'active':''}" onclick="renderPage(${i})">${i}</button>`;
        }

        html += `<button class="page-btn" onclick="renderPage(${currentPage+1})" ${currentPage===total?'disabled':''}>
                    <i class="fas fa-chevron-right" style="font-size:.65rem;"></i>
                 </button>`;
        $('#pageBtns').html(html);
    }

    // ─── HTML Escape ─────────────────────────
    function escHtml(str) {
        return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─── Show/Hide States ────────────────────
    function showState(state) {
        // state: 'initial' | 'loading' | 'results' | 'empty' | 'error'
        $('#initialState').hide();
        $('#tableWrapper').hide();
        $('#noResultsState').hide();
        $('#errorState').hide();

        if (state === 'initial')  { $('#initialState').show(); }
        if (state === 'loading')  { $('#tableWrapper').show(); $('#tableBody').html(skeletonRows()); $('#pageBtns').html(''); $('#paginationInfo').text('Searching…'); }
        if (state === 'results')  { $('#tableWrapper').show(); }
        if (state === 'empty')    { $('#noResultsState').show(); }
        if (state === 'error')    { $('#errorState').show(); }
    }

    // ─── Main Search Function ────────────────
    function doSearch() {
        const q    = $('#searchPatient').val().trim();
        const prog = $('#programFilter').val();
        const yr   = $('#yearFilter').val();

        // Only disable button while searching — no icon swap
        $('#searchBtn').prop('disabled', true);
        $('#searchBtnText').text('Searching…');

        startProgress();
        showState('loading');

        // Animate count badge out
        $('#resultCountBadge').removeClass('show');

        const params = new URLSearchParams({ ajax: '1', search: q, program: prog, year: yr });

        // ✦ 2-second simulated delay ✦
        setTimeout(() => {
            fetch(`log-new-patient.php?${params}`)
                .then(r => r.json())
                .then(res => {
                    finishProgress();
                    $('#searchBtnText').text('Search');
                    $('#searchBtn').prop('disabled', false);

                    if (!res.success) {
                        $('#errorMsg').text(res.message || 'An error occurred.');
                        showState('error');
                        return;
                    }

                    allPatients = res.data;
                    currentPage = 1;

                    // Update badge
                    const cnt = res.count;
                    $('#resultCountText').text(cnt + ' patient' + (cnt !== 1 ? 's' : '') + ' found');
                    setTimeout(() => $('#resultCountBadge').addClass('show'), 50);

                    if (cnt === 0) {
                        showState('empty');
                    } else {
                        showState('results');
                        renderPage(1);
                    }
                })
                .catch(() => {
                    finishProgress();
                    $('#searchBtnText').text('Search');
                    $('#searchBtn').prop('disabled', false);
                    $('#errorMsg').text('Network error. Please try again.');
                    showState('error');
                });
        }, 2000); // ← 2-second delay
    }

    // ─── Event Listeners ────────────────────
    $(document).ready(function() {

        // Search button
        $('#searchBtn').on('click', doSearch);

        // Enter key in search input
        $('#searchPatient').on('keydown', function(e) {
            if (e.key === 'Enter') doSearch();
        });

        // Debounced auto-search on filter change
        $('#programFilter, #yearFilter').on('change', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(doSearch, 300);
        });

        // Clear button
        $('#clearBtn').on('click', function() {
            $('#searchPatient').val('');
            $('#programFilter').val('');
            $('#yearFilter').val('');
            allPatients = [];
            currentPage = 1;
            showState('initial');
            $('#resultCountBadge').removeClass('show');
        });

        // Auto-dismiss static toasts
        setTimeout(() => {
            document.querySelectorAll('.s-toast').forEach(t => {
                t.style.transition = 'opacity .4s';
                t.style.opacity    = '0';
                setTimeout(() => t.remove(), 400);
            });
        }, 4000);

        // Show success modal if visit was just logged
        <?php if (isset($success_msg)): ?>
        $(document).ready(function() {
            const sm = new bootstrap.Modal(document.getElementById('successModal'), { backdrop: 'static' });
            sm.show();
        });
        <?php endif; ?>

        console.log('[SSCMS] Search Patients initialized');
    });
    </script>
</body>
</html>