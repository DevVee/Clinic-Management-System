<?php
include 'PHP/dashboard.php';
require_once __DIR__ . '/system/dashboard_config.php';
require_once __DIR__ . '/system/ui_config.php';

// Handle configure dashboard AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'configure_dashboard') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit;
    }
    $toggles = [
        'stat_cards' => [
            'patients'              => isset($_POST['stat_cards']['patients']),
            'visits_today'          => isset($_POST['stat_cards']['visits_today']),
            'visits_month'          => isset($_POST['stat_cards']['visits_month']),
            'medicines_used_today'  => isset($_POST['stat_cards']['medicines_used_today']),
            'medicine_items'        => isset($_POST['stat_cards']['medicine_items']),
            'low_stock'             => isset($_POST['stat_cards']['low_stock']),
            'specialist_visits'     => isset($_POST['stat_cards']['specialist_visits']),
            'expiring_medicines'    => isset($_POST['stat_cards']['expiring_medicines']),
        ],
        'quick_actions' => [
            'log_patient'       => isset($_POST['quick_actions']['log_patient']),
            'appointments'      => isset($_POST['quick_actions']['appointments']),
            'daily_reports'     => isset($_POST['quick_actions']['daily_reports']),
            'manage_inventory'  => isset($_POST['quick_actions']['manage_inventory']),
            'add_new_medicine'  => isset($_POST['quick_actions']['add_new_medicine']),
        ],
        'sections' => [
            'approved_appointments' => isset($_POST['sections']['approved_appointments']),
        ],
    ];
    $cfg  = "<?php\n// /system/ui_config.php\n\n\$ui_config = [\n    'dashboard' => [\n";
    $cfg .= "        'theme' => '" . get_ui_config('theme', 'default') . "',\n";
    $cfg .= "        'animation' => true,\n";
    $cfg .= "        'card_layout' => 'grid',\n";
    $cfg .= "        'toggles' => [\n            'stat_cards' => [\n";
    foreach ($toggles['stat_cards'] as $k => $v) $cfg .= "                '$k' => " . ($v ? 'true' : 'false') . ",\n";
    $cfg .= "            ],\n            'quick_actions' => [\n";
    foreach ($toggles['quick_actions'] as $k => $v) $cfg .= "                '$k' => " . ($v ? 'true' : 'false') . ",\n";
    $cfg .= "            ],\n            'sections' => [\n";
    foreach ($toggles['sections'] as $k => $v) $cfg .= "                '$k' => " . ($v ? 'true' : 'false') . ",\n";
    $cfg .= "            ]\n        ]\n    ]\n];\n\n";
    $cfg .= "function get_ui_config(\$key, \$default = null) {\n    global \$ui_config;\n    return isset(\$ui_config['dashboard'][\$key]) ? \$ui_config['dashboard'][\$key] : \$default;\n}\n\n";
    $cfg .= "function is_ui_toggle_enabled(\$category, \$toggle_name) {\n    global \$ui_config;\n    return isset(\$ui_config['dashboard']['toggles'][\$category][\$toggle_name]) && \$ui_config['dashboard']['toggles'][\$category][\$toggle_name] === true;\n}\n?>";
    if (file_put_contents(__DIR__ . '/system/ui_config.php', $cfg) !== false) {
        echo json_encode(['success' => true, 'message' => 'Dashboard settings saved!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not save settings. Check file permissions.']);
    }
    exit;
}

// Calculate low stock medicines (count medicines where quantity < 300)
$low_stock_query = "SELECT COUNT(*) AS low_count FROM medicines WHERE quantity < 300 AND is_active = 1";
$low_stock_result = $conn->query($low_stock_query);
$low_stock_row = $low_stock_result->fetch(PDO::FETCH_ASSOC);
$low_stock = $low_stock_row['low_count'] ?? 0;

if ($low_stock > 0) {
    $low_stock_class = 'trend-down';
    $low_stock_status = 'Low Stock Items';
    $arrow = 'down';
} else {
    $low_stock_class = 'trend-up';
    $low_stock_status = 'All Good';
    $arrow = 'up';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Dashboard">
    <meta name="author" content="ICCB">
    <title>Dashboard - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'includes/sscmslogo.php'; ?>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.51.0/dist/apexcharts.min.js"></script>
    <style>
        /* ══════════════════════════════════════════
           SSCMS DASHBOARD — Premium Medical Theme
           ══════════════════════════════════════════ */
        :root {
            --primary:       #0369a1;
            --primary-dark:  #075985;
            --primary-light: #e0f2fe;
            --accent:        #0ea5e9;
            --accent-glow:   rgba(14, 165, 233, 0.25);
            --emerald:       #10b981;
            --amber:         #f59e0b;
            --rose:          #f43f5e;
            --violet:        #8b5cf6;
            --surface:       #ffffff;
            --surface-2:     #f8fafc;
            --border:        #e2e8f0;
            --text-primary:  #0f172a;
            --text-secondary:#64748b;
            --text-muted:    #94a3b8;
            --sidebar-w:     260px;
            --header-h:      64px;
            --radius:        14px;
            --radius-sm:     8px;
            --shadow-sm:     0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md:     0 4px 16px rgba(3,105,161,.08), 0 2px 6px rgba(0,0,0,.04);
            --shadow-lg:     0 12px 40px rgba(3,105,161,.12), 0 4px 12px rgba(0,0,0,.06);
            --shadow-glow:   0 0 0 3px var(--accent-glow);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--surface-2);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        /* ─── LAYOUT ─── */
        .content {
            margin-left: var(--sidebar-width);
            padding: 24px 28px 40px;
            min-height: 100vh;
            transition: margin-left .3s;
        }

        @media (max-width: 992px) { .content { margin-left: var(--sidebar-collapsed-width); } }
        @media (max-width: 768px) { .content { margin-left: 0; padding: 16px 14px 32px; } }

        /* ─── PAGE HEADER ─── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .page-title span {
            display: block;
            font-size: .875rem;
            font-weight: 400;
            color: var(--text-secondary);
            font-family: 'Space Grotesk', sans-serif;
            margin-top: 2px;
        }

        /* ─── WELCOME BANNER ─── */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--accent) 100%);
            border-radius: var(--radius);
            padding: 28px 32px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            right: -60px;
            top: -60px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
        }

        .welcome-inner {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .welcome-text h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }

        .welcome-text p {
            color: rgba(255,255,255,.8);
            font-size: .95rem;
        }

        .welcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 50px;
            padding: 8px 18px;
            color: #fff;
            font-size: .85rem;
            font-weight: 500;
        }

        .welcome-badge .dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #4ade80;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .7; transform: scale(1.3); }
        }

        .date-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,.12);
            border-radius: 8px;
            padding: 6px 14px;
            color: rgba(255,255,255,.9);
            font-size: .82rem;
            margin-top: 10px;
        }

        /* ─── SECTION LABEL ─── */
        .section-label {
            font-family: 'Poppins', sans-serif;
            font-size: .8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-muted);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ─── STAT CARDS ─── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 22px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,.07), 0 1px 3px rgba(0,0,0,.05);
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all .25s cubic-bezier(.4,0,.2,1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: var(--radius) var(--radius) 0 0;
            background: var(--card-accent, var(--primary));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(3,105,161,.15), 0 4px 12px rgba(0,0,0,.08);
            border-color: transparent;
            color: inherit;
        }

        .stat-card:hover::before { transform: scaleX(1); }

        .stat-card.c-blue  { --card-accent: var(--primary); }
        .stat-card.c-teal  { --card-accent: var(--emerald); }
        .stat-card.c-amber { --card-accent: var(--amber); }
        .stat-card.c-rose  { --card-accent: var(--rose); }
        .stat-card.c-violet{ --card-accent: var(--violet); }
        .stat-card.c-sky   { --card-accent: var(--accent); }
        .stat-card.c-green { --card-accent: #22c55e; }
        .stat-card.c-orange{ --card-accent: #f97316; }

        .stat-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .stat-icon-wrap {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: var(--icon-bg, var(--primary-light));
            color: var(--icon-color, var(--primary));
            flex-shrink: 0;
        }

        .stat-card.c-blue  .stat-icon-wrap { --icon-bg: #dbeafe; --icon-color: #2563eb; }
        .stat-card.c-teal  .stat-icon-wrap { --icon-bg: #d1fae5; --icon-color: #059669; }
        .stat-card.c-amber .stat-icon-wrap { --icon-bg: #fef3c7; --icon-color: #d97706; }
        .stat-card.c-rose  .stat-icon-wrap { --icon-bg: #ffe4e6; --icon-color: #e11d48; }
        .stat-card.c-violet.stat-icon-wrap { --icon-bg: #ede9fe; --icon-color: #7c3aed; }
        .stat-card.c-violet .stat-icon-wrap{ --icon-bg: #ede9fe; --icon-color: #7c3aed; }
        .stat-card.c-sky   .stat-icon-wrap { --icon-bg: #e0f2fe; --icon-color: #0284c7; }
        .stat-card.c-green .stat-icon-wrap { --icon-bg: #dcfce7; --icon-color: #16a34a; }
        .stat-card.c-orange.stat-icon-wrap { --icon-bg: #ffedd5; --icon-color: #ea580c; }
        .stat-card.c-orange .stat-icon-wrap{ --icon-bg: #ffedd5; --icon-color: #ea580c; }

        .stat-value {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: .875rem;
            color: #334155;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: .78rem;
            font-weight: 600;
        }

        .stat-badge.up      { background: #dcfce7; color: #15803d; }
        .stat-badge.down    { background: #ffe4e6; color: #be123c; }
        .stat-badge.neutral { background: #e2e8f0; color: #334155; }

        /* ─── CHART CARDS ─── */
        .chart-row {
            display: grid;
            gap: 20px;
            margin-bottom: 24px;
        }

        .chart-row.two-col { grid-template-columns: 1fr 1fr; }
        .chart-row.three-col { grid-template-columns: 2fr 1fr; }

        @media (max-width: 1100px) {
            .chart-row.two-col,
            .chart-row.three-col { grid-template-columns: 1fr; }
        }

        .chart-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,.07), 0 1px 3px rgba(0,0,0,.05);
            overflow: hidden;
            transition: box-shadow .25s;
        }

        .chart-card:hover { box-shadow: 0 6px 20px rgba(3,105,161,.1), 0 2px 8px rgba(0,0,0,.06); }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px 0;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chart-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-title .icon-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--accent);
        }

        .chart-subtitle {
            font-size: .78rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .chart-actions {
            display: flex;
            gap: 6px;
        }

        .chart-pill {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: .75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            transition: all .2s;
        }

        .chart-pill.active, .chart-pill:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .chart-body { padding: 12px 8px 8px; }

        /* ─── QUICK ACTIONS ─── */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .action-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 16px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 10px;
            transition: all .25s cubic-bezier(.4,0,.2,1);
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
            color: var(--primary);
        }

        .action-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: var(--primary-light);
            color: var(--primary);
            transition: background .25s;
        }

        .action-card:hover .action-icon {
            background: var(--primary);
            color: #fff;
        }

        .action-label {
            font-size: .875rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .action-card:hover .action-label { color: var(--primary); }

        .action-sub {
            font-size: .73rem;
            color: var(--text-muted);
        }

        /* ─── PATIENT TABLE ─── */
        .table-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-title {
            font-family: 'Poppins', sans-serif;
            font-size: .95rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .count-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: .75rem;
            font-weight: 600;
            background: var(--primary-light);
            color: var(--primary);
        }

        .live-time {
            font-size: .8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .live-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--emerald);
            animation: pulse-dot 2s infinite;
        }

        .view-all-link {
            font-size: .8rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .view-all-link:hover { color: var(--primary-dark); }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead th {
            padding: 10px 20px;
            font-size: .73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text-muted);
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .data-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }

        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table tbody tr:hover { background: var(--surface-2); }

        .data-table td {
            padding: 12px 20px;
            font-size: .85rem;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .patient-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .75rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .patient-name-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .patient-fullname {
            font-weight: 600;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .category-pill {
            display: inline-flex;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 600;
            background: var(--primary-light);
            color: var(--primary);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 600;
        }

        .status-pill.active { background: #dcfce7; color: #15803d; }
        .status-pill.approved { background: #dbeafe; color: #1d4ed8; }

        .btn-discharge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 7px;
            font-size: .78rem;
            font-weight: 600;
            border: 1px solid #fecdd3;
            background: #fff1f2;
            color: #be123c;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-discharge:hover {
            background: #be123c;
            color: #fff;
            border-color: #be123c;
        }

        .btn-discharge:disabled { opacity: .5; cursor: not-allowed; }

        /* ─── EMPTY STATE ─── */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-icon {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: var(--surface-2);
            border: 2px dashed var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: var(--text-muted);
            margin: 0 auto 12px;
        }

        .empty-state p {
            color: var(--text-muted);
            font-size: .85rem;
        }

        /* ─── CONFIGURE BTN ─── */
        .btn-configure {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: .83rem;
            font-weight: 600;
            background: var(--primary);
            color: #fff;
            text-decoration: none;
            border: none;
            transition: background .2s;
        }

        .btn-configure:hover { background: var(--primary-dark); color: #fff; }

        /* ─── FOOTER ─── */
        .dash-footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: .78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* ─── TOAST ─── */
        .toast-container { z-index: 1080; }

        /* ─── ANIMATIONS ─── */
        .fade-up {
            opacity: 0;
            transform: translateY(16px);
            animation: fadeUp .5s forwards;
        }

        @keyframes fadeUp {
            to { opacity: 1; transform: none; }
        }

        .fade-up:nth-child(1)  { animation-delay: .05s; }
        .fade-up:nth-child(2)  { animation-delay: .10s; }
        .fade-up:nth-child(3)  { animation-delay: .15s; }
        .fade-up:nth-child(4)  { animation-delay: .20s; }
        .fade-up:nth-child(5)  { animation-delay: .25s; }
        .fade-up:nth-child(6)  { animation-delay: .30s; }
        .fade-up:nth-child(7)  { animation-delay: .35s; }
        .fade-up:nth-child(8)  { animation-delay: .40s; }

        /* ══════════════════════════════════════
           DARK MODE — CSS variable overrides
           Everything that uses var(--surface),
           var(--border) etc. adapts automatically.
           ══════════════════════════════════════ */
        [data-theme="dark"] {
            --surface:         hsl(222, 47%, 13%);
            --surface-2:       hsl(222, 47%, 10%);
            --border:          hsl(222, 30%, 22%);
            --text-primary:    hsl(210, 40%, 96%);
            --text-secondary:  hsl(215, 20%, 65%);
            --text-muted:      hsl(215, 20%, 50%);
            --primary-light:   hsl(201, 85%, 14%);
            /* chart helpers */
            --chart-fg:        hsl(215, 20%, 55%);
            --chart-grid:      hsl(222, 30%, 22%);
            --chart-track:     hsl(222, 30%, 20%);
            --chart-tooltip-bg:hsl(222, 47%, 16%);
            --chart-total-lbl: hsl(210, 40%, 88%);
        }

        /* body / layout */
        [data-theme="dark"] body         { background: var(--surface-2); color: var(--text-primary); }
        [data-theme="dark"] .content     { background: var(--surface-2); }

        /* stat badges need explicit dark overrides (hardcoded light bg) */
        [data-theme="dark"] .stat-badge.up      { background: hsl(142,72%,14%); color: #4ade80; }
        [data-theme="dark"] .stat-badge.down    { background: hsl(350,84%,14%); color: #fb7185; }
        [data-theme="dark"] .stat-badge.neutral { background: hsl(222,30%,22%); color: hsl(210,40%,75%); }

        /* stat icon backgrounds */
        [data-theme="dark"] .stat-card.c-blue  .stat-icon-wrap { --icon-bg: hsl(213,93%,14%); --icon-color: #60a5fa; }
        [data-theme="dark"] .stat-card.c-teal  .stat-icon-wrap { --icon-bg: hsl(152,74%,10%); --icon-color: #34d399; }
        [data-theme="dark"] .stat-card.c-amber .stat-icon-wrap { --icon-bg: hsl(38,92%,12%);  --icon-color: #fbbf24; }
        [data-theme="dark"] .stat-card.c-rose  .stat-icon-wrap { --icon-bg: hsl(350,84%,13%); --icon-color: #fb7185; }
        [data-theme="dark"] .stat-card.c-violet .stat-icon-wrap{ --icon-bg: hsl(262,80%,14%); --icon-color: #a78bfa; }
        [data-theme="dark"] .stat-card.c-sky   .stat-icon-wrap { --icon-bg: hsl(201,85%,12%); --icon-color: #38bdf8; }
        [data-theme="dark"] .stat-card.c-green .stat-icon-wrap { --icon-bg: hsl(142,72%,10%); --icon-color: #4ade80; }
        [data-theme="dark"] .stat-card.c-orange .stat-icon-wrap{ --icon-bg: hsl(24,95%,12%);  --icon-color: #fb923c; }

        /* action cards */
        [data-theme="dark"] .action-card:hover { border-color: var(--primary); color: var(--primary); }
        [data-theme="dark"] .action-icon       { background: hsl(201,85%,14%); }
        [data-theme="dark"] .action-card:hover .action-icon { background: var(--primary); }
        [data-theme="dark"] .action-label      { color: var(--text-primary); }

        /* status pills */
        [data-theme="dark"] .status-pill.active   { background: hsl(142,72%,12%); color: #4ade80; }
        [data-theme="dark"] .status-pill.approved { background: hsl(213,93%,14%); color: #60a5fa; }
        [data-theme="dark"] .category-pill        { background: hsl(201,85%,14%); color: #38bdf8; }
        [data-theme="dark"] .count-badge          { background: hsl(201,85%,14%); color: #38bdf8; }

        /* discharge button */
        [data-theme="dark"] .btn-discharge { background: hsl(350,84%,13%); border-color: hsl(350,84%,25%); color: #fb7185; }
        [data-theme="dark"] .btn-discharge:hover { background: #be123c; color: #fff; }

        /* data table row hover */
        [data-theme="dark"] .data-table tbody tr:hover { background: var(--surface); }
        [data-theme="dark"] .data-table thead th { background: var(--surface-2); }

        /* empty state */
        [data-theme="dark"] .empty-icon { background: var(--surface); border-color: var(--border); }

        /* footer */
        [data-theme="dark"] .dash-footer { border-color: var(--border); }

        /* modal (configure) */
        [data-theme="dark"] .configure-toggle-item { background: var(--surface); border-color: var(--border) !important; }
        [data-theme="dark"] .configure-toggle-item .toggle-label { color: var(--text-primary); }
        [data-theme="dark"] .configure-toggle-item .toggle-sub   { color: var(--text-secondary); }
    </style>
</head>
<body>
    <?php include 'includes/navigations.php'; ?>

    <div class="content">
        <main>

            <!-- ══ PAGE HEADER ══ -->
            <div class="page-header fade-up">
                <div class="page-title">
                    Dashboard
                    <span>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?> 👋</span>
                </div>
            </div>

            <!-- ══ WELCOME BANNER ══ -->
            <div class="welcome-banner fade-up">
                <div class="welcome-inner">
                    <div class="welcome-text">
                        <h2>Clinic at a Glance</h2>
                        <p>Here's what's happening in your clinic today.</p>
                        <div class="date-chip">
                            <i class="far fa-calendar-alt"></i>
                            <?= date('l, F j, Y') ?>
                        </div>
                    </div>
                    <div class="welcome-badge">
                        <span class="dot"></span>
                        System Online
                    </div>
                </div>
            </div>

            <!-- ══ STAT CARDS ══ -->
            <?php if (
                (is_stat_card_enabled('patients') && is_ui_toggle_enabled('stat_cards', 'patients')) ||
                (is_stat_card_enabled('visits_today') && is_ui_toggle_enabled('stat_cards', 'visits_today')) ||
                (is_stat_card_enabled('visits_month') && is_ui_toggle_enabled('stat_cards', 'visits_month')) ||
                (is_stat_card_enabled('medicines_used_today') && is_ui_toggle_enabled('stat_cards', 'medicines_used_today')) ||
                (is_stat_card_enabled('medicine_items') && is_ui_toggle_enabled('stat_cards', 'medicine_items')) ||
                (is_stat_card_enabled('low_stock') && is_ui_toggle_enabled('stat_cards', 'low_stock')) ||
                (is_stat_card_enabled('specialist_visits') && is_ui_toggle_enabled('stat_cards', 'specialist_visits')) ||
                (is_stat_card_enabled('expiring_medicines') && is_ui_toggle_enabled('stat_cards', 'expiring_medicines'))
            ): ?>
            <p class="section-label">Clinic Overview</p>
            <div class="stats-grid">

                <?php if (is_stat_card_enabled('patients') && is_ui_toggle_enabled('stat_cards', 'patients')): ?>
                <a href="/patients/manage-patients.php" class="stat-card c-blue fade-up">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?= number_format($total_patients) ?></div>
                            <div class="stat-label">Total Population</div>
                        </div>
                        <div class="stat-icon-wrap"><i class="fas fa-users"></i></div>
                    </div>
                    <span class="stat-badge neutral"><i class="fas fa-database fa-xs"></i> In Database</span>
                </a>
                <?php endif; ?>

                <?php if (is_stat_card_enabled('visits_today') && is_ui_toggle_enabled('stat_cards', 'visits_today')): ?>
                <a href="/reports/monthly-reports.php" class="stat-card c-sky fade-up">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?= number_format($visits_today) ?></div>
                            <div class="stat-label">Visits Today</div>
                        </div>
                        <div class="stat-icon-wrap"><i class="fas fa-hospital-user"></i></div>
                    </div>
                    <span class="stat-badge up"><i class="fas fa-arrow-up fa-xs"></i> Today</span>
                </a>
                <?php endif; ?>

                <?php if (is_stat_card_enabled('visits_month') && is_ui_toggle_enabled('stat_cards', 'visits_month')): ?>
                <a href="/reports/monthly-reports.php" class="stat-card c-teal fade-up">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?= number_format($visits_month) ?></div>
                            <div class="stat-label">Visits This Month</div>
                        </div>
                        <div class="stat-icon-wrap"><i class="fas fa-chart-line"></i></div>
                    </div>
                    <span class="stat-badge up"><i class="fas fa-arrow-up fa-xs"></i> This Month</span>
                </a>
                <?php endif; ?>

                <?php if (is_stat_card_enabled('medicines_used_today') && is_ui_toggle_enabled('stat_cards', 'medicines_used_today')): ?>
                <a href="/inventory/medicine_logs.php" class="stat-card c-violet fade-up">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?= number_format($medicines_used_today) ?></div>
                            <div class="stat-label">Medicines Used Today</div>
                        </div>
                        <div class="stat-icon-wrap"><i class="fas fa-prescription-bottle"></i></div>
                    </div>
                    <span class="stat-badge neutral"><i class="fas fa-layer-group fa-xs"></i> of <?= number_format($total_stock) ?> total</span>
                </a>
                <?php endif; ?>

                <?php if (is_stat_card_enabled('medicine_items') && is_ui_toggle_enabled('stat_cards', 'medicine_items')): ?>
                <a href="/inventory/edit_medicine.php" class="stat-card c-amber fade-up">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?= number_format($medicine_items) ?></div>
                            <div class="stat-label">Medicine Items</div>
                        </div>
                        <div class="stat-icon-wrap"><i class="fas fa-pills"></i></div>
                    </div>
                    <span class="stat-badge neutral"><i class="fas fa-box fa-xs"></i> In Inventory</span>
                </a>
                <?php endif; ?>

                <?php if (is_stat_card_enabled('low_stock') && is_ui_toggle_enabled('stat_cards', 'low_stock')): ?>
                <a href="/inventory/edit_medicine.php" class="stat-card c-rose fade-up">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?= number_format($low_stock) ?></div>
                            <div class="stat-label">Low Stock Medicines</div>
                        </div>
                        <div class="stat-icon-wrap"><i class="fas fa-exclamation-circle"></i></div>
                    </div>
                    <span class="stat-badge <?= $low_stock > 0 ? 'down' : 'up' ?>">
                        <i class="fas fa-arrow-<?= $arrow ?> fa-xs"></i>
                        <?= htmlspecialchars($low_stock_status) ?>
                    </span>
                </a>
                <?php endif; ?>

                <?php if (is_stat_card_enabled('specialist_visits') && is_ui_toggle_enabled('stat_cards', 'specialist_visits')): ?>
                <a href="/calendar/specialist_calendar.php" class="stat-card c-green fade-up">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?= count($specialist_visits) ?></div>
                            <div class="stat-label">Specialist Visits</div>
                        </div>
                        <div class="stat-icon-wrap"><i class="fas fa-user-md"></i></div>
                    </div>
                    <span class="stat-badge neutral"><i class="fas fa-calendar fa-xs"></i>
                        <?php if (empty($specialist_visits)): ?>No Visits Today
                        <?php else: ?>
                            <?php
                            $today_sv = array_filter($specialist_visits, fn($v) => date('Y-m-d', strtotime($v['visit_date'])) === date('Y-m-d'));
                            echo !empty($today_sv) ? count($today_sv).' today' : count($specialist_visits).' tomorrow';
                            ?>
                        <?php endif; ?>
                    </span>
                </a>
                <?php endif; ?>

                <?php if (is_stat_card_enabled('expiring_medicines') && is_ui_toggle_enabled('stat_cards', 'expiring_medicines')): ?>
                <a href="/inventory/medicine_expiration.php" class="stat-card c-orange fade-up">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?= count($expiring_medicines) ?></div>
                            <div class="stat-label">Expiring Medicines</div>
                        </div>
                        <div class="stat-icon-wrap"><i class="fas fa-hourglass-end"></i></div>
                    </div>
                    <span class="stat-badge <?= count($expiring_medicines) > 0 ? 'down' : 'up' ?>">
                        <i class="fas fa-arrow-<?= count($expiring_medicines) > 0 ? 'down' : 'up' ?> fa-xs"></i>
                        <?= htmlspecialchars($expiry_trend) ?>
                    </span>
                </a>
                <?php endif; ?>

            </div>
            <?php endif; ?>

            <!-- ══ CHARTS ROW 1 ══ -->
            <p class="section-label">Analytics</p>
            <div class="chart-row three-col" style="margin-bottom:20px;">

                <!-- Visits Trend -->
                <div class="chart-card fade-up">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">
                                <span class="icon-dot"></span>
                                Patient Visits Trend
                            </div>
                            <div class="chart-subtitle">Monthly visit frequency this year</div>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-pill active" id="pill-month">Monthly</button>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div id="visitsAreaChart" style="min-height:240px;"></div>
                    </div>
                </div>

                <!-- Category Donut -->
                <div class="chart-card fade-up">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">
                                <span class="icon-dot" style="background:#f59e0b;"></span>
                                Patient Categories
                            </div>
                            <div class="chart-subtitle">Distribution by category</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div id="categoryDonutChart" style="min-height:240px;"></div>
                    </div>
                </div>

            </div>

            <!-- Charts Row 2 -->
            <div class="chart-row two-col" style="margin-bottom:28px;">

                <!-- Medicine Stock Radial -->
                <div class="chart-card fade-up">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">
                                <span class="icon-dot" style="background:#8b5cf6;"></span>
                                Inventory Health
                            </div>
                            <div class="chart-subtitle">Stock status overview</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div id="stockRadialChart" style="min-height:220px;"></div>
                    </div>
                </div>

                <!-- Weekly Activity Heatmap-style bar -->
                <div class="chart-card fade-up">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">
                                <span class="icon-dot" style="background:#10b981;"></span>
                                Medicines Dispensed
                            </div>
                            <div class="chart-subtitle">Top medicines used today</div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div id="medicineBarChart" style="min-height:220px;"></div>
                    </div>
                </div>

            </div>

            <!-- ══ QUICK ACTIONS ══ -->
            <?php if (
                is_ui_toggle_enabled('quick_actions', 'log_patient') ||
                is_ui_toggle_enabled('quick_actions', 'appointments') ||
                is_ui_toggle_enabled('quick_actions', 'daily_reports') ||
                is_ui_toggle_enabled('quick_actions', 'manage_inventory') ||
                is_ui_toggle_enabled('quick_actions', 'add_new_medicine')
            ): ?>
            <p class="section-label">Quick Actions</p>
            <div class="actions-grid" style="margin-bottom:28px;">

                <?php if (is_ui_toggle_enabled('quick_actions', 'log_patient')): ?>
                <a href="/patients/log-new-patient.php" class="action-card fade-up">
                    <div class="action-icon"><i class="fas fa-calendar-plus"></i></div>
                    <div>
                        <div class="action-label">Log Patient</div>
                        <div class="action-sub">New visit record</div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (is_ui_toggle_enabled('quick_actions', 'appointments')): ?>
                <a href="/appointments/appointment-list.php" class="action-card fade-up">
                    <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <div class="action-label">Appointments</div>
                        <div class="action-sub">Manage schedules</div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (is_ui_toggle_enabled('quick_actions', 'daily_reports')): ?>
                <a href="/reports/daily-reports.php" class="action-card fade-up">
                    <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="action-label">Daily Reports</div>
                        <div class="action-sub">Clinic statistics</div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (is_ui_toggle_enabled('quick_actions', 'manage_inventory')): ?>
                <a href="/inventory/edit_medicine.php" class="action-card fade-up">
                    <div class="action-icon"><i class="fas fa-boxes"></i></div>
                    <div>
                        <div class="action-label">Inventory</div>
                        <div class="action-sub">Track medicine stock</div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (is_ui_toggle_enabled('quick_actions', 'add_new_medicine')): ?>
                <a href="/inventory/add_new_medicine.php" class="action-card fade-up">
                    <div class="action-icon"><i class="fas fa-capsules"></i></div>
                    <div>
                        <div class="action-label">Add Medicine</div>
                        <div class="action-sub">New inventory item</div>
                    </div>
                </a>
                <?php endif; ?>

            </div>
            <?php endif; ?>

            <!-- ══ CURRENTLY IN CLINIC TABLE ══ -->
            <p class="section-label">Live Clinic</p>
            <div class="table-card fade-up" style="margin-bottom:24px;">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-stethoscope" style="color:var(--emerald);"></i>
                        Currently in Clinic
                        <span class="count-badge"><?= count($current_patients) ?> Patients</span>
                    </div>
                    <div class="live-time">
                        <span class="live-dot"></span>
                        <span id="currentTime">—</span>
                    </div>
                </div>

                <?php if (empty($current_patients)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-user-clock"></i></div>
                        <p>No patients currently in clinic.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Category</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_patients as $p): ?>
                            <tr>
                                <td>
                                    <div class="patient-name-cell">
                                        <div class="patient-avatar">
                                            <?= strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)) ?>
                                        </div>
                                        <span class="patient-fullname"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></span>
                                    </div>
                                </td>
                                <td><span class="category-pill"><?= htmlspecialchars($p['category']) ?></span></td>
                                <td>
                                    <button class="btn-discharge discharge-btn" data-visit-id="<?= $p['id'] ?>">
                                        <i class="fas fa-sign-out-alt"></i> Discharge
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ══ APPROVED APPOINTMENTS TABLE ══ -->
            <?php if (is_ui_toggle_enabled('sections', 'approved_appointments')): ?>
            <div class="table-card fade-up">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-calendar-check" style="color:var(--primary);"></i>
                        Approved Appointments Today
                        <span class="count-badge"><?= count($approved_appointments) ?> Approved</span>
                    </div>
                    <a href="/appointments/appointment-list.php" class="view-all-link">
                        View All <i class="fas fa-arrow-right fa-xs"></i>
                    </a>
                </div>

                <?php if (empty($approved_appointments)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-calendar-check"></i></div>
                        <p>No approved appointments for today.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Category</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approved_appointments as $appt): ?>
                            <tr data-appointment-id="<?= $appt['id'] ?>">
                                <td>
                                    <div class="patient-name-cell">
                                        <div class="patient-avatar" style="background:linear-gradient(135deg,var(--emerald),#34d399);">
                                            <?= strtoupper(substr($appt['patient_name'], 0, 2)) ?>
                                        </div>
                                        <span class="patient-fullname"><?= htmlspecialchars($appt['patient_name']) ?></span>
                                    </div>
                                </td>
                                <td><span class="category-pill"><?= htmlspecialchars($appt['category']) ?></span></td>
                                <td style="font-weight:600; color:var(--text-secondary);">
                                    <?= $appt['appointment_time'] ? date('h:i A', strtotime($appt['appointment_time'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <span class="status-pill approved">
                                        <i class="fas fa-check-circle fa-xs"></i>
                                        <?= htmlspecialchars($appt['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </main>

        <footer class="dash-footer">
            <i class="fas fa-hospital-alt"></i>
            IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. &copy; SSCMS 2025
        </footer>
    </div>

    <!-- Configure Dashboard Modal -->
    <div class="modal fade" id="configureModal" tabindex="-1" aria-labelledby="configureModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
                <div class="modal-header" style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));border-radius:16px 16px 0 0;border:none;padding:1.25rem 1.5rem;">
                    <h5 class="modal-title" id="configureModalLabel" style="color:#fff;font-family:'Poppins',sans-serif;font-weight:700;display:flex;align-items:center;gap:.6rem;">
                        <i class="fas fa-sliders-h"></i> Configure Dashboard
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding:1.5rem;">
                    <div id="configureAlert" class="alert d-none mb-3" role="alert"></div>
                    <form id="configureForm">
                        <input type="hidden" name="action" value="configure_dashboard">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <!-- Stat Cards -->
                        <div class="mb-4">
                            <h6 style="font-family:'Poppins',sans-serif;font-weight:700;color:var(--text-primary);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;">
                                <i class="fas fa-chart-pie" style="color:var(--primary);"></i> Stat Cards
                            </h6>
                            <div class="row g-2">
                                <?php
                                $stat_items = [
                                    'patients'             => ['fa-users',              'Total Population'],
                                    'visits_today'         => ['fa-hospital-user',      'Visits Today'],
                                    'visits_month'         => ['fa-chart-line',         'Visits This Month'],
                                    'medicines_used_today' => ['fa-prescription-bottle','Medicines Used Today'],
                                    'medicine_items'       => ['fa-pills',              'Medicine Items'],
                                    'low_stock'            => ['fa-exclamation-circle', 'Low Stock'],
                                    'specialist_visits'    => ['fa-user-md',            'Specialist Visits'],
                                    'expiring_medicines'   => ['fa-hourglass-end',      'Expiring Medicines'],
                                ];
                                foreach ($stat_items as $key => [$icon, $label]): ?>
                                <div class="col-6 col-md-4">
                                    <label class="configure-toggle-item d-flex align-items-center gap-2 p-2 rounded" style="cursor:pointer;background:var(--surface-2);border:1px solid var(--border);transition:all .2s;">
                                        <input type="checkbox" class="form-check-input m-0 flex-shrink-0"
                                               name="stat_cards[<?= $key ?>]"
                                               <?= is_ui_toggle_enabled('stat_cards', $key) ? 'checked' : '' ?>>
                                        <i class="fas <?= $icon ?>" style="color:var(--primary);width:16px;text-align:center;flex-shrink:0;"></i>
                                        <span style="font-size:.82rem;font-weight:500;color:var(--text-primary);"><?= $label ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="mb-4">
                            <h6 style="font-family:'Poppins',sans-serif;font-weight:700;color:var(--text-primary);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;">
                                <i class="fas fa-bolt" style="color:var(--amber);"></i> Quick Actions
                            </h6>
                            <div class="row g-2">
                                <?php
                                $qa_items = [
                                    'log_patient'      => ['fa-calendar-plus', 'Log Patient'],
                                    'appointments'     => ['fa-calendar-alt',  'Appointments'],
                                    'daily_reports'    => ['fa-chart-bar',     'Daily Reports'],
                                    'manage_inventory' => ['fa-boxes',         'Manage Inventory'],
                                    'add_new_medicine' => ['fa-capsules',      'Add Medicine'],
                                ];
                                foreach ($qa_items as $key => [$icon, $label]): ?>
                                <div class="col-6 col-md-4">
                                    <label class="configure-toggle-item d-flex align-items-center gap-2 p-2 rounded" style="cursor:pointer;background:var(--surface-2);border:1px solid var(--border);transition:all .2s;">
                                        <input type="checkbox" class="form-check-input m-0 flex-shrink-0"
                                               name="quick_actions[<?= $key ?>]"
                                               <?= is_ui_toggle_enabled('quick_actions', $key) ? 'checked' : '' ?>>
                                        <i class="fas <?= $icon ?>" style="color:var(--emerald);width:16px;text-align:center;flex-shrink:0;"></i>
                                        <span style="font-size:.82rem;font-weight:500;color:var(--text-primary);"><?= $label ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Sections -->
                        <div class="mb-2">
                            <h6 style="font-family:'Poppins',sans-serif;font-weight:700;color:var(--text-primary);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;">
                                <i class="fas fa-th-large" style="color:var(--violet);"></i> Sections
                            </h6>
                            <div class="row g-2">
                                <div class="col-6 col-md-4">
                                    <label class="configure-toggle-item d-flex align-items-center gap-2 p-2 rounded" style="cursor:pointer;background:var(--surface-2);border:1px solid var(--border);transition:all .2s;">
                                        <input type="checkbox" class="form-check-input m-0 flex-shrink-0"
                                               name="sections[approved_appointments]"
                                               <?= is_ui_toggle_enabled('sections', 'approved_appointments') ? 'checked' : '' ?>>
                                        <i class="fas fa-calendar-check" style="color:var(--violet);width:16px;text-align:center;flex-shrink:0;"></i>
                                        <span style="font-size:.82rem;font-weight:500;color:var(--text-primary);">Approved Appointments</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border);padding:1rem 1.5rem;gap:.75rem;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="font-weight:600;border-radius:8px;">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn" id="saveConfigureBtn"
                            style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;font-family:'Poppins',sans-serif;min-width:120px;">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="dashboardToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    /* ══════════════════════════════════════════
       SSCMS DASHBOARD — Scripts
       ══════════════════════════════════════════ */

    // ─── Live Clock ─────────────────────────────
    function updateClock() {
        const el = document.getElementById('currentTime');
        if (el) el.textContent = new Date().toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit', hour12:true });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ─── ApexCharts — Theme-Aware Setup ──────────
    const palette = ['#0369a1','#0ea5e9','#10b981','#f59e0b','#f43f5e','#8b5cf6','#f97316','#22c55e'];

    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

    // Returns theme-dependent chart colours (called on init and on theme toggle)
    function chartTheme() {
        const dark = isDark();
        return {
            mode:      dark ? 'dark'   : 'light',
            fg:        dark ? '#718096' : '#94a3b8',  // axis label colour
            grid:      dark ? '#2d3748' : '#f1f5f9',  // grid / track background
            tooltipBg: dark ? '#1a202c' : '#ffffff',
            totalLbl:  dark ? '#e2e8f0' : '#0f172a',
            chartBg:   'transparent',
        };
    }

    const baseChart = () => ({
        fontFamily: "'Space Grotesk', sans-serif",
        toolbar: { show: false },
        animations: { enabled: true, easing: 'easeinout', speed: 600 },
        background: 'transparent',
    });

    const baseTooltip = () => ({
        theme: isDark() ? 'dark' : 'light',
        style: { fontSize: '12px' },
    });

    const baseLegend = { fontSize: '12px', fontWeight: 500 };

    // ─── 1. Visits Area Chart ────────────────────
    const visitMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const visitData   = [38,52,45,68,74,61,89,76,93,82,101,<?= $visits_month ?>];

    const visitsChart = new ApexCharts(document.querySelector('#visitsAreaChart'), {
        series: [{ name:'Visits', data: visitData }],
        chart: { ...baseChart(), type:'area', height:240 },
        theme: { mode: chartTheme().mode },
        tooltip: baseTooltip(),
        legend: baseLegend,
        colors: ['#0369a1'],
        fill: { type:'gradient', gradient:{ shadeIntensity:1, opacityFrom:.4, opacityTo:.02, stops:[0,95] } },
        stroke: { curve:'smooth', width:2.5 },
        dataLabels: { enabled:false },
        xaxis: { categories: visitMonths, labels:{ style:{ fontSize:'11px', colors: Array(12).fill(chartTheme().fg) } }, axisBorder:{ show:false }, axisTicks:{ show:false } },
        yaxis: { labels:{ style:{ fontSize:'11px', colors: [chartTheme().fg] } } },
        grid: { borderColor: chartTheme().grid, strokeDashArray:4 },
        markers: { size:0, hover:{ size:5 } }
    });
    visitsChart.render();

    // ─── 2. Category Donut ───────────────────────
    const catLabels = ['Students','Faculty','Staff','Others'];
    const catData   = [62, 18, 12, 8];

    const donutChart = new ApexCharts(document.querySelector('#categoryDonutChart'), {
        series: catData,
        labels: catLabels,
        chart: { ...baseChart(), type:'donut', height:240 },
        theme: { mode: chartTheme().mode },
        tooltip: baseTooltip(),
        legend: { ...baseLegend, position:'bottom', offsetY:4 },
        colors: palette,
        plotOptions: { pie:{ donut:{ size:'60%', labels:{ show:true, total:{ show:true, label:'Total', fontSize:'13px', fontWeight:600, color: chartTheme().totalLbl } } } } },
        dataLabels: { enabled:false },
    });
    donutChart.render();

    // ─── 3. Stock Radial ─────────────────────────
    const totalStock = <?= $total_stock ?: 1 ?>;
    const usedToday  = <?= $medicines_used_today ?>;
    const lowStock   = <?= $low_stock ?>;
    const usedPct    = Math.round((usedToday / totalStock) * 100);
    const lowPct     = Math.round((lowStock / Math.max(<?= $medicine_items ?>, 1)) * 100);
    const goodPct    = 100 - lowPct;

    const radialChart = new ApexCharts(document.querySelector('#stockRadialChart'), {
        series: [goodPct, lowPct > 0 ? lowPct : 0, usedPct],
        labels: ['Good Stock','Low Stock','Used Today'],
        chart: { ...baseChart(), type:'radialBar', height:220 },
        theme: { mode: chartTheme().mode },
        tooltip: baseTooltip(),
        legend: { ...baseLegend, show:true, position:'bottom' },
        colors: ['#10b981','#f43f5e','#8b5cf6'],
        plotOptions: {
            radialBar: {
                offsetY: 0,
                startAngle: -90, endAngle: 270,
                hollow: { margin:5, size:'30%', background:'transparent' },
                track: { background: chartTheme().grid, strokeWidth:'97%', margin:5 },
                dataLabels: {
                    name:  { fontSize:'11px' },
                    value: { fontSize:'14px', fontWeight:700 },
                    total: { show:true, label:'Health', color: chartTheme().totalLbl, fontSize:'12px', fontWeight:600, formatter: () => goodPct+'%' }
                }
            }
        },
    });
    radialChart.render();

    // ─── 4. Medicine Bar Chart ───────────────────
    const medNames = ['Paracetamol','Ibuprofen','Amoxicillin','Cetirizine','Mefenamic'];
    const medVals  = [34,22,18,15,11];

    const barChart = new ApexCharts(document.querySelector('#medicineBarChart'), {
        series: [{ name:'Units Used', data: medVals }],
        chart: { ...baseChart(), type:'bar', height:220 },
        theme: { mode: chartTheme().mode },
        tooltip: baseTooltip(),
        legend: { show:false },
        colors: palette,
        plotOptions: { bar:{ borderRadius:6, horizontal:true, barHeight:'60%', distributed:true } },
        dataLabels: { enabled:true, style:{ fontSize:'11px', fontWeight:600 } },
        xaxis: { categories: medNames, labels:{ style:{ fontSize:'11px', colors: Array(5).fill(chartTheme().fg) } } },
        yaxis: { labels:{ style:{ fontSize:'11px', colors: [chartTheme().fg] } } },
        grid: { borderColor: chartTheme().grid, strokeDashArray:4 },
    });
    barChart.render();

    // ─── Re-apply chart theme on dark-mode toggle ─
    const allDashCharts = [visitsChart, donutChart, radialChart, barChart];

    document.addEventListener('sscms:themechange', ({ detail: { dark } }) => {
        const ct = chartTheme();
        allDashCharts.forEach(chart => {
            chart.updateOptions({
                theme:   { mode: ct.mode },
                tooltip: { theme: dark ? 'dark' : 'light' },
                grid:    { borderColor: ct.grid },
                xaxis:   { labels: { style: { colors: Array(12).fill(ct.fg) } } },
                yaxis:   { labels: { style: { colors: [ct.fg] } } },
            }, false, false);
        });
        // Radial track + donut total label need extra update
        radialChart.updateOptions({
            plotOptions: { radialBar: { track: { background: ct.grid }, dataLabels: { total: { color: ct.totalLbl } } } }
        }, false, false);
        donutChart.updateOptions({
            plotOptions: { pie: { donut: { labels: { total: { color: ct.totalLbl } } } } }
        }, false, false);
    });

    // ─── Discharge Button ────────────────────────
    $(document).on('click', '.discharge-btn', function() {
        const visitId = $(this).data('visit-id');
        const $btn = $(this).prop('disabled', true);

        $.ajax({
            url: 'dashboard.php', type: 'POST',
            data: { action:'discharge', visit_id: visitId, csrf_token: '<?= $_SESSION['csrf_token'] ?>' },
            dataType: 'json',
            success(res) {
                if (res.success) {
                    $btn.closest('tr').fadeOut(300, function(){ $(this).remove(); });
                    const cnt = $('.data-table tbody tr').length - 1;
                    $('.count-badge').first().text(cnt + ' Patients');
                }
                const t = new bootstrap.Toast(document.getElementById('dashboardToast'));
                $('#dashboardToast .toast-body').text(res.message);
                t.show();
            },
            error() { $btn.prop('disabled', false); },
            complete() { $btn.prop('disabled', false); }
        });
    });

    // ─── Auto-refresh current patients (60s) ─────
    setInterval(() => {
        $.get('dashboard.php', data => {
            const doc = new DOMParser().parseFromString(data, 'text/html');
            const fresh = doc.querySelector('.table-card');
            if (fresh) document.querySelector('.table-card').outerHTML = fresh.outerHTML;
        });
    }, 60000);

    // ─── Bootstrap tooltips ──────────────────────
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach(el => new bootstrap.Tooltip(el));

    // ─── Configure Dashboard Modal ───────────────
    document.querySelectorAll('.configure-toggle-item').forEach(item => {
        item.addEventListener('mouseenter', () => item.style.borderColor = 'var(--primary)');
        item.addEventListener('mouseleave', () => item.style.borderColor = 'var(--border)');
    });

    document.getElementById('saveConfigureBtn').addEventListener('click', function() {
        const btn  = this;
        const form = document.getElementById('configureForm');
        const alert = document.getElementById('configureAlert');
        const data = new FormData(form);

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';
        alert.className = 'alert d-none mb-3';

        fetch('/dashboard.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert.className = 'alert alert-success mb-3';
                    alert.textContent = res.message;
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('configureModal')).hide();
                        location.reload();
                    }, 800);
                } else {
                    alert.className = 'alert alert-danger mb-3';
                    alert.textContent = res.message;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save me-1"></i> Save Changes';
                }
            })
            .catch(() => {
                alert.className = 'alert alert-danger mb-3';
                alert.textContent = 'Network error. Please try again.';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Save Changes';
            });
    });
    </script>
</body>
</html>