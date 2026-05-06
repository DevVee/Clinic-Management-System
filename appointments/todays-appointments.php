<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Today's Appointments] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Default to today's date
$selected_date = isset($_GET['selected_date']) ? filter_var($_GET['selected_date'], FILTER_SANITIZE_STRING) : '2025-05-25';

try {
    $stmt = $conn->prepare("
        SELECT id, patient_name, category, phone, appointment_date, appointment_time, reason, appointee
        FROM appointments
        WHERE status = 'approved' AND appointment_date = ?
        ORDER BY appointment_time ASC
    ");
    $stmt->execute([$selected_date]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Today's Appointments] Fetched " . count($appointments) . " approved appointments for $selected_date");
} catch (Exception $e) {
    error_log("[SSCMS Today's Appointments] Query error: " . $e->getMessage());
    $appointments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View today's approved appointments at Immaculate Conception College Clinic">
    <meta name="author" content="ICCB">
    <title>Today's Approved Appointments - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
              <?php include '../includes/sscmslogo.php'; ?>

    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --primary-dark: #4338ca;
            --success: #2ec4b6;
            --success-light: #e6f7f5;
            --info: #0dcaf0;
            --info-light: #cff4fc;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --accent: #8b5cf6;
            --accent-light: #ede9fe;
            --secondary: #6b7280;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
            --light-bg: #f9fafb;
            --white: #ffffff;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --border-radius: 0.5rem;
            --border-radius-sm: 0.375rem;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --header-height: 70px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 0.95rem;
            margin: 0;
            overflow-x: hidden;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1rem;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }

        .container-fluid {
            max-width: 1280px;
            padding: 0 1rem;
        }

        .dashboard-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding-left: 0.75rem;
            border-left: 4px solid var(--primary);
        }

        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-body {
            padding: 1.25rem;
        }

        .calendar-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1rem;
            border: 1px solid var(--border);
        }

        #calendar {
            max-width: 100%;
            margin: 0 auto;
        }

        .fc {
            font-family: 'Inter', sans-serif;
        }

        .fc-header-toolbar {
            margin-bottom: 1rem;
        }

        .fc-button {
            background: var(--primary);
            border: 1px solid var(--primary);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        .fc-button:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: scale(1.05);
        }

        .fc-button:focus {
            box-shadow: 0 0 0 0.2rem var(--primary-light);
        }

        .fc-daygrid-day {
            cursor: pointer;
            transition: background-color 0.2s ease;
            min-height: 44px;
        }

        .fc-daygrid-day:hover {
            background-color: var(--primary-light);
        }

        .fc-daygrid-day.available {
            background: var(--success-light);
            position: relative;
        }

        .fc-daygrid-day.available::after {
            content: '●';
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            color: var(--success);
            font-size: 0.8rem;
        }

        .fc-daygrid-day.selected {
            background: var(--primary-light);
            border: 2px solid var(--primary);
        }

        .filters-section {
            background: var(--light-bg);
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--border);
        }

        .form-control {
            border: 1px solid var(--border);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            padding: 0.5rem 0.75rem;
            background: var(--white);
            height: 36px;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem var(--primary-light);
        }

        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
            min-height: 44px;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            color: var(--secondary);
            border-color: var(--secondary);
        }

        .btn-outline-secondary:hover {
            background: var(--secondary);
            border-color: var(--secondary);
            transform: translateY(-1px);
        }

        .table {
            font-size: 0.9rem;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--border);
            border-radius: var(--border-radius-sm);
            overflow: hidden;
        }

        .table th {
            background: var(--light-bg);
            border-top: none;
            border-bottom: 2px solid var(--border);
            color: var(--text-secondary);
            font-weight: 600;
            padding: 0.75rem;
        }

        .table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-hover tbody tr:hover {
            background: var(--primary-light);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--white);
            background: var(--success);
        }

        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 2.5rem;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
        }

        .footer {
            background: var(--white);
            border-top: 1px solid var(--border);
            padding: 1.5rem 0;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 2rem;
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: 1px solid var(--border);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            border-bottom: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 0.75rem 1rem;
        }

        .modal-body {
            padding: 1rem;
            font-size: 0.9rem;
        }

        .modal-body p {
            margin-bottom: 0.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 0.75rem 1rem;
        }

        .toast {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-lg);
            min-width: 300px;
        }

        .toast-header {
            background: var(--light-bg);
            border-bottom: 1px solid var(--border);
        }

        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid var(--border);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        @media (max-width: 992px) {
            :root { --sidebar-width: var(--sidebar-collapsed-width); }
            .content { margin-left: var(--sidebar-width); }
        }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
            .fc-header-toolbar {
                flex-direction: column;
                gap: 0.5rem;
            }
            .fc-toolbar-chunk {
                display: flex;
                justify-content: center;
            }
            .table {
                font-size: 0.8rem;
            }
            .table th:nth-child(4), .table td:nth-child(4) { /* Category */
                display: none;
            }
            .form-control {
                font-size: 0.85rem;
                height: 34px;
            }
            .btn {
                font-size: 0.85rem;
                padding: 0.4rem 0.8rem;
            }
            .status-badge {
                font-size: 0.75rem;
                padding: 0.2rem 0.6rem;
            }
        }

        @media (max-width: 576px) {
            body {
                font-size: 0.85rem;
            }
            .dashboard-title {
                font-size: 1.1rem;
            }
            .form-control {
                font-size: 0.8rem;
                height: 32px;
            }
            .btn {
                font-size: 0.8rem;
                padding: 0.3rem 0.6rem;
            }
            .modal-body {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <h1 class="dashboard-title fade-in">
                    <i class="fas fa-calendar-check me-2"></i>
                    Today's Approved Appointments
                </h1>

                <div class="card fade-in">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Appointment Calendar
                    </div>
                    <div class="card-body">
                        <div class="calendar-container mb-4">
                            <div id="calendar"></div>
                        </div>

                        <div class="filters-section">
                            <div class="row align-items-center">
                                <div class="col-md-6 mb-2 mb-md-0">
                                    <label class="form-label small text-secondary mb-1">Selected Date:</label>
                                    <div class="form-control bg-light" id="selectedDate">
                                        <?= date('l, F j, Y', strtotime($selected_date)) ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-secondary mb-1">&nbsp;</label>
                                    <div>
                                        <button class="btn btn-outline-secondary btn-sm w-100" onclick="resetDate()">
                                            <i class="fas fa-sync-alt me-1"></i>
                                            Reset to Today
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="appointmentTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Category</th>
                                        <th>Appointee</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $row): ?>
                                        <?php
                                        $hour = (int)substr($row['appointment_time'], 0, 2);
                                        if ($hour < 7 || $hour > 16) continue;
                                        $time_12 = date("h:i A", strtotime($row['appointment_time']));
                                        ?>
                                        <tr class="fade-in">
                                            <td><strong><?= htmlspecialchars($row['appointment_date']) ?></strong></td>
                                            <td><?= htmlspecialchars($time_12) ?></td>
                                            <td><?= htmlspecialchars($row['patient_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($row['category'] ?? 'N/A') ?></td>
                                            <td>
                                                <i class="fas fa-<?= $row['appointee'] === 'Doctor' ? 'user-md' : ($row['appointee'] === 'Nurse' ? 'user-nurse' : 'tooth') ?> me-1"></i>
                                                <?= htmlspecialchars($row['appointee']) ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary view-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#detailsModal"
                                                        data-id="<?= $row['id'] ?>"
                                                        data-patient="<?= htmlspecialchars($row['patient_name'] ?? '') ?>"
                                                        data-phone="<?= htmlspecialchars($row['phone'] ?? '') ?>"
                                                        data-category="<?= htmlspecialchars($row['category'] ?? '') ?>"
                                                        data-appointee="<?= htmlspecialchars($row['appointee']) ?>"
                                                        data-date="<?= htmlspecialchars($row['appointment_date']) ?>"
                                                        data-time="<?= htmlspecialchars($time_12) ?>"
                                                        data-reason="<?= htmlspecialchars($row['reason'] ?? '') ?>">
                                                    <i class="fas fa-eye me-1"></i>
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($appointments)): ?>
                                        <tr>
                                            <td colspan="6">
                                                <div class="empty-state">
                                                    <i class="fas fa-calendar-times"></i>
                                                    <h6>No Approved Appointments</h6>
                                                    <p>No approved appointments for this date.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <footer class="footer">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6 text-md-start text-center">
                        <i class="fas fa-hospital me-1"></i>
                        © 2025 Immaculate Conception College of Balayan - Health Services
                    </div>
                    <div class="col-md-6 text-md-end text-center mt-2 mt-md-0">
                        <span class="text-muted">Your health, our priority</span>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Appointment Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Patient:</strong> <span id="modalPatient"></span></p>
                    <p><strong>Category:</strong> <span id="modalCategory"></span></p>
                    <p><strong>Phone:</strong> <span id="modalPhone"></span></p>
                    <p><strong>Appointee:</strong> <span id="modalAppointee"></span></p>
                    <p><strong>Date:</strong> <span id="modalDate"></span></p>
                    <p><strong>Time:</strong> <span id="modalTime"></span></p>
                    <p><strong>Reason:</strong> <span id="modalReason"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="actionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-bell text-primary me-2"></i>
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[SSCMS Today\'s Appointments] Initialized');

            const calendarEl = document.getElementById('calendar');
            const tableBody = document.querySelector('#appointmentTable tbody');
            const selectedDateEl = document.getElementById('selectedDate');
            const toastEl = document.getElementById('actionToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 4000 });

            let currentSelectedDate = '<?= htmlspecialchars($selected_date) ?>';

            // Initialize FullCalendar
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: window.innerWidth <= 768 ? 'listWeek' : 'dayGridMonth',
                initialDate: currentSelectedDate,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: window.innerWidth <= 768 ? 'listWeek' : 'dayGridMonth,timeGridWeek,listWeek'
                },
                height: 'auto',
                aspectRatio: 1.8,
                validRange: {
                    start: '2025-01-01',
                    end: new Date().setFullYear(new Date().getFullYear() + 1)
                },
                dayMaxEvents: false,
                moreLinkClick: 'popover',
                datesSet: function() {
                    loadAvailableDates();
                },
                dateClick: function(info) {
                    document.querySelectorAll('.fc-daygrid-day.selected').forEach(el => {
                        el.classList.remove('selected');
                    });
                    info.dayEl.classList.add('selected');
                    currentSelectedDate = info.dateStr;
                    selectedDateEl.textContent = new Date(info.dateStr).toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    loadAppointments(info.dateStr);
                    updateUrl(info.dateStr);
                }
            });
            calendar.render();

            // Load dates with approved appointments
            function loadAvailableDates() {
                $.ajax({
                    url: '/appointments/get_approved_dates.php?calendar=true',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            calendar.getEvents().forEach(event => event.remove());
                            response.dates.forEach(date => {
                                const dayEl = document.querySelector(`[data-date="${date}"]`);
                                if (dayEl) {
                                    dayEl.classList.add('available');
                                }
                            });
                            showToast('Calendar updated with dates containing approved appointments', 'success');
                        } else {
                            showToast(response.message || 'Failed to load available dates', 'error');
                        }
                    },
                    error: function(jqXHR, textStatus) {
                        showToast('Error loading calendar: ' + textStatus, 'error');
                    }
                });
            }

            // Load approved appointments for selected date
            function loadAppointments(date) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="loading-spinner"></div>
                            Loading approved appointments...
                        </td>
                    </tr>
                `;

                $.ajax({
                    url: '/appointments/get_approved_dates.php',
                    method: 'GET',
                    data: { date: date },
                    dataType: 'json',
                    success: function(response) {
                        tableBody.innerHTML = '';
                        if (response.success && response.appointments.length > 0) {
                            response.appointments.forEach((appt, index) => {
                                const hour = parseInt(appt.appointment_time.split(':')[0]);
                                if (hour < 7 || hour > 16) return; // Filter 7 AM–4 PM
                                const row = document.createElement('tr');
                                row.className = 'fade-in';
                                row.style.animationDelay = `${index * 0.1}s`;

                                const formattedTime = new Date('1970-01-01 ' + appt.appointment_time).toLocaleTimeString([], {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                    hour12: true
                                });
                                const formattedDate = new Date(appt.appointment_date).toLocaleDateString('en-US', {
                                    month: 'short',
                                    day: 'numeric'
                                });

                                row.innerHTML = `
                                    <td><strong>${formattedDate}</strong></td>
                                    <td>${formattedTime}</td>
                                    <td>${appt.patient_name || 'N/A'}</td>
                                    <td>${appt.category || 'N/A'}</td>
                                    <td>
                                        <i class="fas fa-${appt.appointee === 'Doctor' ? 'user-md' : appt.appointee === 'Nurse' ? 'user-nurse' : 'tooth'} me-1"></i>
                                        ${appt.appointee}
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#detailsModal"
                                                data-id="${appt.id}"
                                                data-patient="${appt.patient_name || ''}"
                                                data-phone="${appt.phone || ''}"
                                                data-category="${appt.category || ''}"
                                                data-appointee="${appt.appointee}"
                                                data-date="${appt.appointment_date}"
                                                data-time="${formattedTime}"
                                                data-reason="${appt.reason || ''}">
                                            <i class="fas fa-eye me-1"></i>
                                            View
                                        </button>
                                    </td>
                                `;
                                tableBody.appendChild(row);
                            });
                            showToast(`Found ${response.appointments.length} approved appointments for ${selectedDateEl.textContent}`, 'info');
                        } else {
                            tableBody.innerHTML = `
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fas fa-calendar-times"></i>
                                            <h6>No Approved Appointments</h6>
                                            <p>No approved appointments for this date.</p>
                                        </div>
                                    </td>
                                </tr>
                            `;
                            showToast('No approved appointments found for this date', 'info');
                        }
                    },
                    error: function(jqXHR, textStatus) {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-exclamation-triangle text-danger"></i>
                                        <h6>Error Loading Appointments</h6>
                                        <p>Unable to load appointments. Please try again.</p>
                                    </div>
                                </td>
                            </tr>
                        `;
                        showToast('Error loading appointments', 'error');
                    }
                });
            }

            // Update URL with selected date
            function updateUrl(date) {
                const url = new URL(window.location);
                url.searchParams.set('selected_date', date);
                window.history.pushState({}, '', url);
            }

            // Reset to today
            window.resetDate = function() {
                currentSelectedDate = '2025-05-25';
                selectedDateEl.textContent = new Date(currentSelectedDate).toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                document.querySelectorAll('.fc-daygrid-day.selected').forEach(el => {
                    el.classList.remove('selected');
                });
                const dayEl = document.querySelector(`[data-date="${currentSelectedDate}"]`);
                if (dayEl) {
                    dayEl.classList.add('selected');
                }
                calendar.gotoDate(currentSelectedDate);
                loadAppointments(currentSelectedDate);
                updateUrl(currentSelectedDate);
                showToast('Reset to today\'s date', 'info');
            };

            // Modal population
            const detailsModal = document.getElementById('detailsModal');
            detailsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const patient = button.getAttribute('data-patient');
                const phone = button.getAttribute('data-phone');
                const category = button.getAttribute('data-category');
                const appointee = button.getAttribute('data-appointee');
                const date = button.getAttribute('data-date');
                const time = button.getAttribute('data-time');
                const reason = button.getAttribute('data-reason');

                document.getElementById('modalPatient').textContent = patient || 'N/A';
                document.getElementById('modalCategory').textContent = category || 'N/A';
                document.getElementById('modalPhone').textContent = phone || 'N/A';
                document.getElementById('modalAppointee').textContent = appointee;
                document.getElementById('modalDate').textContent = date;
                document.getElementById('modalTime').textContent = time;
                document.getElementById('modalReason').textContent = reason || 'N/A';
            });

            // Show toast
            function showToast(message, type = 'info') {
                const toastHeader = toastEl.querySelector('.toast-header');
                const iconClass = type === 'success' ? 'fa-check-circle text-success' :
                                 type === 'error' ? 'fa-exclamation-circle text-danger' :
                                 'fa-info-circle text-primary';
                toastHeader.querySelector('i').className = `fas ${iconClass} me-2`;
                toastBody.textContent = message;
                toast.show();
            }

            // Initialize on load
            setTimeout(() => {
                loadAvailableDates();
                const dayEl = document.querySelector(`[data-date="${currentSelectedDate}"]`);
                if (dayEl) {
                    dayEl.classList.add('selected');
                }
            }, 500);
        });
    </script>
</body>
</html>