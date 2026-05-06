<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Calendar] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Debug: Check database connection
if (!$conn) {
    error_log("[SSCMS Calendar] Database connection failed");
    die("Database connection failed");
}

// Handle AJAX requests for calendar events
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'data' => [], 'message' => ''];

    try {
        if ($_POST['action'] === 'fetch_events') {
            $start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_STRING);
            $end = filter_input(INPUT_POST, 'end', FILTER_SANITIZE_STRING);
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING) ?: null;

            // Validate dates
            if (!$start || !$end || !strtotime($start) || !strtotime($end)) {
                throw new Exception('Invalid date range');
            }

            // Debug: Log received parameters
            error_log("[SSCMS Calendar] Fetch events - Start: $start, End: $end, Category: " . ($category ?: 'none'));

            // Fetch visit counts per day
            $query = "
                SELECT DATE(v.visit_date) as visit_date, COUNT(*) as visit_count
                FROM visits v
                JOIN patients p ON v.patient_id = p.id
                WHERE v.visit_date BETWEEN ? AND ?
            ";
            $params = [$start, $end];

            if ($category) {
                $query .= " AND p.category = ?";
                $params[] = $category;
            }

            $query .= " GROUP BY visit_date";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Query preparation failed: ' . $conn->error);
            }
            $stmt->execute($params);
            $events = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $visit_count = $row['visit_count'];
                $color = $visit_count > 5 ? '#dc2626' : '#0284c7'; // Red for >5, blue for <=5
                $events[] = [
                    'title' => "$visit_count Visit" . ($visit_count > 1 ? 's' : ''),
                    'start' => $row['visit_date'],
                    'color' => $color,
                    'visit_count' => $visit_count
                ];
            }

            $response['success'] = true;
            $response['data'] = $events;
            error_log("[SSCMS Calendar] Fetched events: " . count($events) . " for dates $start to $end");
        } elseif ($_POST['action'] === 'fetch_date_details') {
            $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING) ?: null;

            if (!$date || !strtotime($date)) {
                throw new Exception('Invalid date');
            }

            // Debug: Log date and category
            error_log("[SSCMS Calendar] Fetch details - Date: $date, Category: " . ($category ?: 'none'));

            $query = "
                SELECT v.id, v.visit_time, p.first_name, p.last_name, p.category, v.reason
                FROM visits v
                JOIN patients p ON v.patient_id = p.id
                WHERE DATE(v.visit_date) = ?
            ";
            $params = [$date];

            if ($category) {
                $query .= " AND p.category = ?";
                $params[] = $category;
            }

            $query .= " ORDER BY v.visit_time ASC";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Query preparation failed: ' . $conn->error);
            }
            $stmt->execute($params);
            $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = true;
            $response['data'] = $visits;
            error_log("[SSCMS Calendar] Fetched details for date: $date, visits: " . count($visits));
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("[SSCMS Calendar] Error: " . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}

// Fetch patient categories for filter
try {
    $stmt = $conn->query("SELECT DISTINCT category FROM patients WHERE category IS NOT NULL ORDER BY category");
    if (!$stmt) {
        throw new Exception('Categories query failed: ' . $conn->error);
    }
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("[SSCMS Calendar] Fetched categories: " . count($categories));
} catch (Exception $e) {
    error_log("[SSCMS Calendar] Categories query error: " . $e->getMessage());
    $categories = [];
}

// Debug: Check visits and patients data
try {
    $debug_visits = $conn->query("SELECT COUNT(*) as count FROM visits")->fetch(PDO::FETCH_ASSOC);
    $debug_patients = $conn->query("SELECT COUNT(*) as count FROM patients")->fetch(PDO::FETCH_ASSOC);
    error_log("[SSCMS Calendar] Debug - Visits count: " . $debug_visits['count'] . ", Patients count: " . $debug_patients['count']);
} catch (Exception $e) {
    error_log("[SSCMS Calendar] Debug query error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Calendar">
    <meta name="author" content="ICCB">
    <title>Clinic Visits Calendar - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css" rel="stylesheet">
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

        .calendar-card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            padding: 1rem;
            border: 1px solid var(--border);
            margin-bottom: 1rem;
        }

        .details-card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            padding: 1rem;
            border: 1px solid var(--border);
            margin-bottom: 1rem;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .calendar-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            padding-left: 0.5rem;
            border-left: 4px solid var(--primary);
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-form select {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            max-width: 200px;
        }

        .fc {
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
        }

        .fc .fc-toolbar {
            margin-bottom: 0.5rem;
        }

        .fc .fc-toolbar-title {
            font-size: 1rem;
            font-weight: 600;
        }

        .fc .fc-button {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            background: var(--primary);
            border-color: var(--primary);
        }

        .fc .fc-button:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .fc .fc-daygrid-day {
            height: 80px; /* Compact height */
        }

        .fc .fc-daygrid-day-number {
            font-weight: 500;
            font-size: 0.8rem;
        }

        .fc .fc-daygrid-day.fc-day-has-visits {
            background: var(--primary-light);
            transition: background 0.3s ease;
        }

        .fc .fc-daygrid-day.fc-day-has-visits-high {
            background: var(--danger-light);
        }

        .fc .fc-daygrid-day.fc-day-selected {
            border: 2px solid var(--primary);
            box-shadow: 0 0 10px rgba(2, 132, 199, 0.5);
            border-radius: 4px;
        }

        .fc .fc-event {
            border-radius: 4px;
            padding: 1px 4px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            background: var(--gradient-primary);
            border: none;
            color: white;
        }

        .fc .fc-event.fc-event-high {
            background: var(--gradient-danger);
        }

        .fc .fc-event .fc-event-title {
            display: flex;
            align-items: center;
        }

        .fc .fc-event .fc-event-title::before {
            content: attr(data-count);
            display: inline-flex;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-primary);
            font-size: 0.7rem;
            font-weight: 600;
            align-items: center;
            justify-content: center;
            margin-right: 4px;
        }

        .details-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            padding-left: 0.5rem;
            border-left: 4px solid var(--accent);
            margin-bottom: 0.75rem;
        }

        .details-table {
            font-size: 0.85rem;
        }

        .details-table th,
        .details-table td {
            padding: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 1rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }

        .empty-state-text {
            font-size: 0.85rem;
        }

        .toast-container {
            z-index: 9999;
        }

        .toast {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            border: none;
            min-width: 320px;
        }

        .toast.success { border-left: 3px solid var(--success); }
        .toast.error { border-left: 3px solid var(--danger); }

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
            .calendar-header { flex-wrap: wrap; gap: 0.5rem; }
            .filter-form { width: 100%; }
        }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 0.75rem; }
            .calendar-card, .details-card { padding: 0.75rem; }
            .calendar-title, .details-title { font-size: 1rem; }
            .filter-form select { width: 100%; }
            .fc .fc-toolbar { flex-direction: column; gap: 0.5rem; }
            .fc .fc-toolbar-chunk { width: 100%; text-align: center; }
            .fc .fc-daygrid-day { height: 60px; }
            .fc .fc-event { font-size: 0.75rem; }
            .fc .fc-event .fc-event-title::before { width: 16px; height: 16px; font-size: 0.65rem; }
            .details-table th, .details-table td { padding: 0.4rem; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Calendar Card -->
                <div class="calendar-card slide-up">
                    <div class="calendar-header">
                        <h3 class="calendar-title">
                            <i class="fas fa-calendar-alt me-2"></i>Clinic Visits Calendar
                        </h3>
                        <form class="filter-form">
                            <select id="categoryFilter" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div id="calendar"></div>
                </div>

                <!-- Visit Details Card -->
                <div class="details-card slide-up" id="visitDetailsCard" style="display: none;">
                    <h3 class="details-title" id="visitDetailsTitle"></h3>
                    <div id="visitDetailsContent"></div>
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

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="calendarToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[SSCMS Calendar] Initialized');

            const calendarEl = document.getElementById('calendar');
            const categoryFilter = document.getElementById('categoryFilter');
            const visitDetailsCard = document.getElementById('visitDetailsCard');
            const visitDetailsTitle = document.getElementById('visitDetailsTitle');
            const visitDetailsContent = document.getElementById('visitDetailsContent');
            const toastEl = document.getElementById('calendarToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });

            let selectedDate = null;

            // Initialize FullCalendar
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                timeZone: 'Asia/Manila',
                height: 'auto',
                events: function(fetchInfo, successCallback, failureCallback) {
                    console.log('[SSCMS Calendar] Fetching events:', {
                        start: fetchInfo.startStr,
                        end: fetchInfo.endStr,
                        category: categoryFilter.value
                    });
                    $.ajax({
                        url: 'calendar.php',
                        type: 'POST',
                        data: {
                            action: 'fetch_events',
                            start: fetchInfo.startStr,
                            end: fetchInfo.endStr,
                            category: categoryFilter.value
                        },
                        dataType: 'json',
                        success: function(response) {
                            console.log('[SSCMS Calendar] Fetch events response:', response);
                            if (response.success) {
                                if (response.data.length === 0) {
                                    showToast('No visits found for the selected period.', false);
                                }
                                successCallback(response.data.map(event => ({
                                    ...event,
                                    classNames: event.visit_count > 5 ? 'fc-event-high fc-day-has-visits-high' : 'fc-day-has-visits'
                                })));
                            } else {
                                showToast('Error fetching events: ' + (response.message || 'Unknown error'), false);
                                console.error('[SSCMS Calendar] Fetch events failed:', response.message);
                                failureCallback();
                            }
                        },
                        error: function(xhr, status, error) {
                            showToast('Error fetching events: ' + error, false);
                            console.error('[SSCMS Calendar] AJAX error:', xhr.responseText);
                            failureCallback();
                        }
                    });
                },
                eventContent: function(arg) {
                    return {
                        html: `<div class="fc-event-title" data-count="${arg.event.extendedProps.visit_count}">${arg.event.title}</div>`
                    };
                },
                dateClick: function(info) {
                    console.log('[SSCMS Calendar] Date clicked:', info.dateStr);
                    // Remove previous selection
                    if (selectedDate) {
                        const prevCell = document.querySelector(`.fc-daygrid-day[data-date="${selectedDate}"]`);
                        if (prevCell) prevCell.classList.remove('fc-day-selected');
                    }

                    // Highlight selected date
                    info.dayEl.classList.add('fc-day-selected');
                    selectedDate = info.dateStr;

                    // Fetch visit details
                    $.ajax({
                        url: 'calendar.php',
                        type: 'POST',
                        data: {
                            action: 'fetch_date_details',
                            date: info.dateStr,
                            category: categoryFilter.value
                        },
                        dataType: 'json',
                        success: function(response) {
                            console.log('[SSCMS Calendar] Fetch details response:', response);
                            if (response.success) {
                                const visits = response.data;
                                visitDetailsTitle.textContent = `Visits on ${new Date(info.dateStr).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;
                                visitDetailsContent.innerHTML = visits.length === 0
                                    ? `
                                        <div class="empty-state">
                                            <i class="fas fa-user-clock empty-state-icon"></i>
                                            <p class="empty-state-text">No visits on this date.</p>
                                        </div>
                                    `
                                    : `
                                        <table class="table table-striped details-table">
                                            <thead>
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Time</th>
                                                    <th>Category</th>
                                                    <th>Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${visits.map(visit => `
                                                    <tr>
                                                        <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                            ${visit.first_name} ${visit.last_name}
                                                        </td>
                                                        <td>${visit.visit_time ? new Date(`1970-01-01T${visit.visit_time}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A'}</td>
                                                        <td>${visit.category || 'N/A'}</td>
                                                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                            ${visit.reason || 'N/A'}
                                                        </td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    `;
                                visitDetailsCard.style.display = 'block';
                                visitDetailsCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            } else {
                                showToast('Error fetching visit details: ' + (response.message || 'Unknown error'), false);
                                console.error('[SSCMS Calendar] Fetch details failed:', response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            showToast('Error fetching visit details: ' + error, false);
                            console.error('[SSCMS Calendar] AJAX error:', xhr.responseText);
                        }
                    });
                },
                eventDidMount: function(info) {
                    $(info.el).tooltip({
                        title: `${info.event.extendedProps.visit_count} visit${info.event.extendedProps.visit_count > 1 ? 's' : ''} on ${info.event.start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`,
                        placement: 'top',
                        trigger: 'hover'
                    });
                }
            });

            calendar.render();

            // Handle category filter change
            categoryFilter.addEventListener('change', function() {
                console.log('[SSCMS Calendar] Category filter changed:', categoryFilter.value);
                calendar.refetchEvents();
                visitDetailsCard.style.display = 'none';
                visitDetailsTitle.textContent = '';
                visitDetailsContent.innerHTML = '';
                if (selectedDate) {
                    const prevCell = document.querySelector(`.fc-daygrid-day[data-date="${selectedDate}"]`);
                    if (prevCell) prevCell.classList.remove('fc-day-selected');
                    selectedDate = null;
                }
            });

            // Toast function
            function showToast(message, isSuccess) {
                toastBody.textContent = message;
                toastEl.classList.remove('success', 'error');
                toastEl.classList.add(isSuccess ? 'success' : 'error');
                toast.show();
            }
        });
    </script>
</body>
</html>