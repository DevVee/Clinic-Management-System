<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Specialist Calendar] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX requests for calendar events
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'data' => []];

    try {
        if ($_POST['action'] === 'fetch_events') {
            $start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_STRING);
            $end = filter_input(INPUT_POST, 'end', FILTER_SANITIZE_STRING);
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING) ?: null;

            if (!$start || !$end || !strtotime($start) || !strtotime($end)) {
                throw new Exception('Invalid date range');
            }

            $query = "
                SELECT sv.id, sv.visit_date, sv.start_time, sv.end_time, u.name, u.admin_category
                FROM specialist_visits sv
                JOIN users u ON sv.user_id = u.id
                WHERE sv.visit_date BETWEEN ? AND ?
                AND u.admin_category IN ('Doctor', 'Dentist')
            ";
            $params = [$start, $end];

            if ($category) {
                $query .= " AND u.admin_category = ?";
                $params[] = $category;
            }

            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $events = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $color = $row['admin_category'] === 'Doctor' ? '#0284c7' : '#059669';
                $events[] = [
                    'id' => $row['id'],
                    'title' => "{$row['name']} ({$row['admin_category']})",
                    'start' => "{$row['visit_date']}T{$row['start_time']}",
                    'end' => "{$row['visit_date']}T{$row['end_time']}",
                    'color' => $color,
                    'extendedProps' => [
                        'name' => $row['name'],
                        'category' => $row['admin_category'],
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time']
                    ]
                ];
            }

            $response['success'] = true;
            $response['data'] = $events;
            error_log("[SSCMS Specialist Calendar] Fetched events: " . count($events));
        } elseif ($_POST['action'] === 'fetch_date_details') {
            $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING) ?: null;

            if (!$date || !strtotime($date)) {
                throw new Exception('Invalid date');
            }

            $query = "
                SELECT sv.id, sv.start_time, sv.end_time, u.name, u.admin_category
                FROM specialist_visits sv
                JOIN users u ON sv.user_id = u.id
                WHERE DATE(sv.visit_date) = ?
                AND u.admin_category IN ('Doctor', 'Dentist')
            ";
            $params = [$date];

            if ($category) {
                $query .= " AND u.admin_category = ?";
                $params[] = $category;
            }

            $query .= " ORDER BY sv.start_time ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = true;
            $response['data'] = $visits;
            error_log("[SSCMS Specialist Calendar] Fetched details for date: $date, visits: " . count($visits));
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("[SSCMS Specialist Calendar] Error: " . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Specialist Calendar">
    <meta name="author" content="ICCB">
    <title>Specialist Calendar - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --gradient-success: linear-gradient(135deg, #059669, #047857);
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
            border: none;
            color: white;
        }

        .fc .fc-button:hover {
            background: var(--primary-dark);
        }

        .fc .fc-daygrid-day {
            height: 80px;
        }

        .fc .fc-daygrid-day-number {
            font-weight: 500;
            font-size: 0.8rem;
            color: var(--text-primary);
        }

        .fc .fc-daygrid-day.fc-day-selected {
            border: 2px solid var(--primary);
            box-shadow: 0 0 10px rgba(2, 132, 199, 0.5);
            border-radius: 4px;
        }

        .fc .fc-event {
            border-radius: 4px;
            padding: 3px 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: var(--text-primary);
            background-clip: padding-box;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .fc .fc-event.fc-doctor {
            background: var(--gradient-primary);
        }

        .fc .fc-event.fc-dentist {
            background: var(--gradient-success);
        }

        .fc .fc-event:hover {
            opacity: 0.9;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
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
            color: var(--text-primary);
        }

        .details-table th {
            background: var(--primary-light);
            color: var(--text-primary);
            font-weight: 600;
        }

        .details-table tbody tr:hover {
            background: var(--primary-light);
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

        .toast.success {
            border-left: 3px solid var(--success);
        }

        .toast.error {
            border-left: 3px solid var(--danger);
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
            .fc .fc-event { font-size: 0.75rem; padding: 2px 4px; }
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
                            <i class="fas fa-calendar-alt me-2"></i>Specialist Visit Calendar
                        </h3>
                        <form class="filter-form">
                            <select id="categoryFilter" class="form-select">
                                <option value="">All Specialists</option>
                                <option value="Doctor">Doctors</option>
                                <option value="Dentist">Dentists</option>
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[SSCMS Specialist Calendar] Initialized');

            const calendarEl = document.getElementById('calendar');
            const categoryFilter = document.getElementById('categoryFilter');
            const visitDetailsCard = document.getElementById('visitDetailsCard');
            const visitDetailsTitle = document.getElementById('visitDetailsTitle');
            const visitDetailsContent = document.getElementById('visitDetailsContent');
            const toastEl = document.getElementById('calendarToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });

            let selectedDate = null;

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
                    $.ajax({
                        url: 'specialist_calendar.php',
                        type: 'POST',
                        data: {
                            action: 'fetch_events',
                            start: fetchInfo.startStr,
                            end: fetchInfo.endStr,
                            category: categoryFilter.value
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                response.data.forEach(event => {
                                    // Add class based on category
                                    event.classNames = [event.extendedProps.category.toLowerCase()];
                                });
                                successCallback(response.data);
                            } else {
                                showToast('Error fetching events: ' + response.message, false);
                                failureCallback();
                            }
                        },
                        error: function() {
                            showToast('Error fetching events', false);
                            failureCallback();
                        }
                    });
                },
                eventContent: function(arg) {
                    return {
                        html: `<div class="fc-event-title">${arg.event.title}</div>`
                    };
                },
                dateClick: function(info) {
                    if (selectedDate) {
                        const prevCell = document.querySelector(`.fc-daygrid-day[data-date="${selectedDate}"]`);
                        if (prevCell) prevCell.classList.remove('fc-day-selected');
                    }

                    info.dayEl.classList.add('fc-day-selected');
                    selectedDate = info.dateStr;

                    $.ajax({
                        url: 'specialist_calendar.php',
                        type: 'POST',
                        data: {
                            action: 'fetch_date_details',
                            date: info.dateStr,
                            category: categoryFilter.value
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                const visits = response.data;
                                visitDetailsTitle.textContent = `Specialist Visits on ${new Date(info.dateStr).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;
                                visitDetailsContent.innerHTML = visits.length === 0
                                    ? `
                                        <div class="empty-state">
                                            <i class="fas fa-user-clock empty-state-icon"></i>
                                            <p class="empty-state-text">No specialist visits on this date.</p>
                                        </div>
                                    `
                                    : `
                                        <table class="table table-striped details-table">
                                            <thead>
                                                <tr>
                                                    <th>Specialist</th>
                                                    <th>Time</th>
                                                    <th>Category</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${visits.map(visit => `
                                                    <tr>
                                                        <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                            ${visit.name}
                                                        </td>
                                                        <td>${visit.start_time ? new Date(`1970-01-01T${visit.start_time}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A'} - ${visit.end_time ? new Date(`1970-01-01T${visit.end_time}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A'}</td>
                                                        <td>${visit.admin_category}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    `;
                                visitDetailsCard.style.display = 'block';
                                visitDetailsCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            } else {
                                showToast('Error fetching visit details: ' + response.message, false);
                            }
                        },
                        error: function() {
                            showToast('Error fetching visit details', false);
                        }
                    });
                },
                eventDidMount: function(info) {
                    $(info.el).tooltip({
                        title: `${info.event.title} on ${info.event.start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} from ${info.event.extendedProps.start_time} to ${info.event.extendedProps.end_time}`,
                        placement: 'top',
                        trigger: 'hover'
                    });
                }
            });

            calendar.render();

            categoryFilter.addEventListener('change', function() {
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