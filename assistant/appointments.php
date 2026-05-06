<?php
// Initialize session and dependencies
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Smart School Clinic Management System - Book Appointment">
    <meta name="author" content="Group 6, ICCB BSCS">
    <title>Book Appointment - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <?php include '../includes/sscmslogo.php'; ?>
    <style>
        :root {
            --mobile-primary: #0084ff;
            --mobile-primary-hover: #0066cc;
            --mobile-secondary: #f0f2f5;
            --mobile-text-primary: #1c1e21;
            --mobile-text-secondary: #65676b;
            --mobile-border: #e4e6ea;
            --mobile-bg-light: #ffffff;
            --mobile-bg-dark: #f8f9fa;
            --mobile-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
            --mobile-radius: 18px;
            --mobile-radius-small: 12px;
            --success: #059669;
            --danger: #dc2626;
            --transition-speed: 0.3s;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--mobile-bg-dark);
            height: 100vh;
            margin: 0;
            padding: 0;
            font-size: 13px;
            color: var(--mobile-text-primary);
            line-height: 1.5;
        }

        .container {
            max-width: 100%;
            height: 100vh;
            background: var(--mobile-bg-light);
            box-shadow: var(--mobile-shadow);
            display: flex;
            flex-direction: column;
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--mobile-bg-light);
            border-bottom: 1px solid var(--mobile-border);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 100;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .header-info h1 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
            color: var(--mobile-text-primary);
        }

        .header-info .status {
            font-size: 11px;
            color: var(--mobile-text-secondary);
        }

        .header-actions {
            margin-left: auto;
            display: flex;
            gap: 8px;
        }

        .header-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--mobile-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .header-btn:hover {
            background: var(--mobile-border);
            transform: scale(1.05);
        }

        .form-container {
            padding: 70px 10px 80px;
            height: calc(100vh - 150px);
            background: var(--mobile-bg-light);
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .card {
            background: var(--mobile-bg-light);
            border: 1px solid var(--mobile-border);
            border-radius: var(--mobile-radius);
            box-shadow: var(--mobile-shadow);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: var(--mobile-bg-light);
            border-bottom: 1px solid var(--mobile-border);
            padding: 1rem;
            font-weight: 600;
            color: var(--mobile-text-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 1.25rem;
        }

        .form-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--mobile-text-secondary);
        }

        .form-control, .form-select {
            border: 1px solid var(--mobile-border);
            border-radius: var(--mobile-radius-small);
            font-size: 0.9rem;
            padding: 0.5rem 0.75rem;
            background: var(--mobile-bg-light);
            height: 36px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--mobile-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 132, 255, 0.1);
        }

        .form-control.flatpickr {
            background: var(--mobile-bg-light);
        }

        .btn {
            border-radius: var(--mobile-radius-small);
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
            min-height: 44px;
        }

        .btn-primary {
            background: var(--mobile-primary);
            border-color: var(--mobile-primary);
        }

        .btn-primary:hover {
            background: var(--mobile-primary-hover);
            border-color: var(--mobile-primary-hover);
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            color: var(--mobile-primary);
            border-color: var(--mobile-primary);
        }

        .btn-outline-primary:hover {
            background: var(--mobile-primary);
            border-color: var(--mobile-primary);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger);
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: #dc3545;
            border-color: #dc3545;
            transform: translateY(-1px);
        }

        .modal-content {
            border-radius: var(--mobile-radius);
            border: 1px solid var(--mobile-border);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--mobile-primary), var(--mobile-primary-hover));
            color: #fff;
            border-bottom: none;
            border-radius: var(--mobile-radius) var(--mobile-radius) 0 0;
            padding: 0.75rem 1rem;
        }

        .modal-body {
            padding: 1rem;
            font-size: 0.9rem;
        }

        .modal-footer {
            border-top: 1px solid var(--mobile-border);
            padding: 0.75rem 1rem;
        }

        .toast {
            border-radius: var(--mobile-radius);
            border: none;
            box-shadow: var(--mobile-shadow);
            min-width: 300px;
        }

        .toast-header {
            background: var(--mobile-bg-light);
            border-bottom: 1px solid var(--mobile-border);
        }

        .toast.success .toast-header {
            background: rgba(5, 150, 105, 0.1);
        }

        .toast.error .toast-header {
            background: rgba(220, 38, 38, 0.1);
        }

        .appointment-row {
            position: relative;
            padding-top: 2rem;
            background: var(--mobile-secondary);
            border-radius: var(--mobile-radius);
        }

        .remove-row {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }

        .notification {
            position: fixed;
            top: 15px;
            right: 15px;
            padding: 10px 20px;
            border-radius: 10px;
            color: #fff;
            z-index: 1000;
            animation: slideIn 0.3s ease, slideOut 0.3s ease 2s forwards;
        }

        .notification.success {
            background: var(--success);
        }

        .notification.error {
            background: var(--danger);
        }

        /* Navbar Styles */
        .navbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--mobile-bg-light);
            border-top: 1px solid var(--mobile-border);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0;
            z-index: 101;
            box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.1);
            height: 60px;
        }

        .navbar-item {
            flex: 1;
            text-align: center;
            color: var(--mobile-text-secondary);
            font-size: 12px;
            font-weight: 500;
            padding: 8px 0;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .navbar-item.active {
            color: var(--mobile-primary);
            background: var(--mobile-secondary);
            border-radius: 8px;
            margin: 0 5px;
        }

        .navbar-item:hover {
            background: var(--mobile-secondary);
            border-radius: 8px;
        }

        .navbar-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .navbar-item span {
            display: block;
            font-size: 11px;
        }

        /* Adjustments for Desktop */
        @media (min-width: 768px) {
            .navbar {
                bottom: 0;
                left: 0;
                right: 0;
                height: 60px;
                padding: 8px 20px;
                justify-content: center;
                gap: 20px;
            }

            .navbar-item {
                flex: 0 1 120px;
                padding: 8px;
            }

            .navbar-item i {
                font-size: 22px;
            }

            .navbar-item span {
                font-size: 12px;
            }

            .container {
                margin: 0;
                width: 100%;
            }

            .form-container {
                height: calc(100vh - 130px);
            }
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        @keyframes slideOut {
            from { transform: translateX(0); }
            to { transform: translateX(100%); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        .form-container::-webkit-scrollbar {
            width: 4px;
        }

        .form-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .form-container::-webkit-scrollbar-thumb {
            background: var(--mobile-border);
            border-radius: 2px;
        }

        .form-container::-webkit-scrollbar-thumb:hover {
            background: var(--mobile-text-secondary);
        }

        @media (max-width: 576px) {
            .form-container {
                padding: 70px 10px 80px;
            }

            .card {
                font-size: 12px;
            }

            .header h1 {
                font-size: 16px;
            }

            .header .status {
                font-size: 11px;
            }

            .form-label {
                font-size: 0.85rem;
            }

            .form-control, .form-select {
                font-size: 0.85rem;
                height: 34px;
            }

            .btn {
                font-size: 0.85rem;
                padding: 0.4rem 0.8rem;
            }

            .card-header {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }

            .navbar {
                height: 50px;
            }

            .navbar-item i {
                font-size: 18px;
            }

            .navbar-item span {
                font-size: 10px;
            }
        }

        @media print {
            body {
                background: #fff;
                padding: 0 !important;
            }
            .navbar, .header, .notification, .toast-container, .modal {
                display: none !important;
            }
            .container {
                width: 210mm;
                border: none;
                box-shadow: none;
                margin-left: 0;
            }
            .form-container {
                height: auto;
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="avatar">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <div class="header-info">
                <h1>Book Appointment</h1>
                <div class="status">Schedule your clinic visit</div>
            </div>
            <div class="header-actions">
             
            </div>
        </div>
        <div class="form-container" role="main" aria-live="polite">
            <div class="card fade-in">
                <div class="card-header">
                    <div>
                        <i class="fas fa-calendar-check me-2"></i>
                        Request New Appointments
                    </div>
                </div>
                <div class="card-body">
                    <form id="appointmentForm" action="/appointments/submit_appointment.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <!-- Shared Appointment Fields -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="appointee" class="form-label">Appointee <span class="text-danger">*</span></label>
                                <select name="appointee" id="appointee" class="form-select" required>
                                    <option value="">Select Appointee</option>
                                    <option value="Doctor">Doctor</option>
                                    <option value="Nurse">Nurse</option>
                                    <option value="Dentist">Dentist</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="appointment_date" class="form-label">Appointment Date <span class="text-danger">*</span></label>
                                <input type="text" name="appointment_date" id="appointment_date" class="form-control flatpickr" placeholder="Select Date" required>
                            </div>
                            <div class="col-md-4">
                                <label for="appointment_time" class="form-label">Appointment Time <span class="text-danger">*</span></label>
                                <select name="appointment_time" id="appointment_time" class="form-select" required>
                                    <option value="">Select Time</option>
                                    <?php for ($hour = 7; $hour <= 16; $hour++): ?>
                                        <?php $time = sprintf("%02d:00:00", $hour); ?>
                                        <option value="<?= $time ?>"><?= date("h:i A", strtotime($time)) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div id="appointmentsContainer">
                            <!-- Initial Appointment Row -->
                            <div class="appointment-row mb-3 border p-3 rounded">
                                <button type="button" class="btn btn-sm btn-danger remove-row d-none"><i class="fas fa-trash"></i></button>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="patient_name_0" class="form-label">Name <span class="text-danger">*</span></label>
                                        <input type="text" name="appointments[0][patient_name]" id="patient_name_0" class="form-control" placeholder="Enter Full Name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="category_0" class="form-label">Category <span class="text-danger">*</span></label>
                                        <select name="appointments[0][category]" id="category_0" class="form-select" required>
                                            <option value="">Select Category</option>
                                            <option value="Pre School">Pre School</option>
                                            <option value="Elementary">Elementary</option>
                                            <option value="JHS">JHS</option>
                                            <option value="SHS">SHS</option>
                                            <option value="College">College</option>
                                            <option value="Alumni">Alumni</option>
                                            <option value="Non-Student">Non-Student</option>
                                            <option value="Teacher">Teacher</option>
                                            <option value="Non-Teaching">Non-Teaching</option>
                                            <option value="Staff">Staff</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone_0" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                        <input type="tel" name="appointments[0][phone]" id="phone_0" class="form-control" placeholder="Enter Phone Number" pattern="[0-9]{10,11}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="reason_select_0" class="form-label">Reason for Visit <span class="text-danger">*</span></label>
                                        <select id="reason_select_0" class="form-select" required>
                                            <option value="">Select Reason</option>
                                            <option value="General Checkup">General Checkup</option>
                                            <option value="Dental Consultation">Dental Consultation</option>
                                            <option value="Follow-Up Visit">Follow-Up Visit</option>
                                            <option value="Vaccination">Vaccination</option>
                                            <option value="Medical Certificate">Medical Certificate</option>
                                            <option value="Illness/Injury">Illness/Injury</option>
                                            <option value="Mental Health Consultation">Mental Health Consultation</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <input type="hidden" name="appointments[0][reason]" id="reason_hidden_0">
                                    </div>
                                    <div class="col-12 d-none" id="customReasonContainer_0">
                                        <label for="custom_reason_0" class="form-label">Specify Reason <span class="text-danger">*</span></label>
                                        <textarea id="custom_reason_0" class="form-control" placeholder="Please specify the reason for your visit" rows="4"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <button type="button" id="addAppointment" class="btn btn-outline-primary w-100"><i class="fas fa-plus me-2"></i>Add Another Patient</button>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Submit Appointments</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <nav class="navbar">
        <a href="app_nurse_angge.php" class="navbar-item" aria-label="Chat with Nurse Angge">
            <i class="fas fa-comment"></i>
            <span>Chat</span>
        </a>
        <a href="appointments.php" class="navbar-item active" aria-label="Book Appointments">
            <i class="fas fa-calendar-alt"></i>
            <span>Appointments</span>
        </a>
    </nav>

    <!-- Thank You Modal -->
    <div class="modal fade" id="thankYouModal" tabindex="-1" aria-labelledby="thankYouModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content fade-in">
                <div class="modal-header">
                    <h5 class="modal-title" id="thankYouModalLabel">Thank You!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center">
                        <i class="fas fa-check-circle text-success fa-2x mb-2"></i><br>
                        Your appointment requests have been submitted successfully. We will notify you via SMS regarding your appointment statuses (approved or rejected).
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Okay</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="formToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
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
        $(document).ready(function() {
            console.log('[SSCMS Web New Appointment] Initialized');

            let appointmentCount = 0;

            // Initialize Flatpickr for shared date field
            flatpickr("#appointment_date", {
                dateFormat: "Y-m-d",
                minDate: "today",
                maxDate: new Date().setDate(new Date().getDate() + 30),
                disable: [
                    function(date) {
                        return date.getDay() === 0 || date.getDay() === 6;
                    }
                ],
                prevArrow: '<i class="fas fa-arrow-left"></i>',
                nextArrow: '<i class="fas fa-arrow-right"></i>'
            });

            // Initialize reason handler for the first appointment
            initializeReasonHandler(0);

            // Add new appointment row
            $('#addAppointment').on('click', function() {
                appointmentCount++;
                const container = $('#appointmentsContainer');
                const newRow = $('<div>').addClass('appointment-row mb-3 border p-3 rounded').css('background', 'var(--mobile-secondary)');
                newRow.html(`
                    <button type="button" class="btn btn-sm btn-danger remove-row"><i class="fas fa-trash"></i></button>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="patient_name_${appointmentCount}" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="appointments[${appointmentCount}][patient_name]" id="patient_name_${appointmentCount}" class="form-control" placeholder="Enter Full Name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category_${appointmentCount}" class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="appointments[${appointmentCount}][category]" id="category_${appointmentCount}" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="Pre School">Pre School</option>
                                <option value="Elementary">Elementary</option>
                                <option value="JHS">JHS</option>
                                <option value="SHS">SHS</option>
                                <option value="College">College</option>
                                <option value="Alumni">Alumni</option>
                                <option value="Non-Student">Non-Student</option>
                                <option value="Teacher">Teacher</option>
                                <option value="Non-Teaching">Non-Teaching</option>
                                <option value="Staff">Staff</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="phone_${appointmentCount}" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="appointments[${appointmentCount}][phone]" id="phone_${appointmentCount}" class="form-control" placeholder="Enter Phone Number" pattern="[0-9]{10,11}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="reason_select_${appointmentCount}" class="form-label">Reason for Visit <span class="text-danger">*</span></label>
                            <select id="reason_select_${appointmentCount}" class="form-select" required>
                                <option value="">Select Reason</option>
                                <option value="General Checkup">General Checkup</option>
                                <option value="Dental Consultation">Dental Consultation</option>
                                <option value="Follow-Up Visit">Follow-Up Visit</option>
                                <option value="Vaccination">Vaccination</option>
                                <option value="Medical Certificate">Medical Certificate</option>
                                <option value="Illness/Injury">Illness/Injury</option>
                                <option value="Mental Health Consultation">Mental Health Consultation</option>
                                <option value="Other">Other</option>
                            </select>
                            <input type="hidden" name="appointments[${appointmentCount}][reason]" id="reason_hidden_${appointmentCount}">
                        </div>
                        <div class="col-12 d-none" id="customReasonContainer_${appointmentCount}">
                            <label for="custom_reason_${appointmentCount}" class="form-label">Specify Reason <span class="text-danger">*</span></label>
                            <textarea id="custom_reason_${appointmentCount}" class="form-control" placeholder="Please specify the reason for your visit" rows="4"></textarea>
                        </div>
                    </div>
                `);
                container.append(newRow);
                initializeReasonHandler(appointmentCount);
                updateRemoveButtons();
            });

            // Remove appointment row
            $('#appointmentsContainer').on('click', '.remove-row', function() {
                $(this).closest('.appointment-row').remove();
                updateRemoveButtons();
            });

            // Initialize reason handler for a specific appointment row
            function initializeReasonHandler(index) {
                const reasonSelect = $(`#reason_select_${index}`);
                const reasonHidden = $(`#reason_hidden_${index}`);
                const customReasonContainer = $(`#customReasonContainer_${index}`);
                const customReasonTextarea = $(`#custom_reason_${index}`);

                reasonSelect.on('change', function() {
                    if (this.value === 'Other') {
                        customReasonContainer.removeClass('d-none');
                        customReasonTextarea.attr('required', 'required');
                        customReasonTextarea.val('');
                        reasonHidden.val('');
                    } else {
                        customReasonContainer.addClass('d-none');
                        customReasonTextarea.removeAttr('required');
                        customReasonTextarea.val('');
                        reasonHidden.val(this.value);
                    }
                });

                customReasonTextarea.on('input', function() {
                    reasonHidden.val(this.value.trim());
                    reasonSelect.val('Other');
                });
            }

            // Update visibility of remove buttons
            function updateRemoveButtons() {
                const rows = $('.appointment-row');
                rows.each(function() {
                    const removeBtn = $(this).find('.remove-row');
                    removeBtn.toggleClass('d-none', rows.length === 1);
                });
            }

            // Form submission
            const form = $('#appointmentForm');
            const toastEl = $('#formToast');
            const toastBody = toastEl.find('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
            const thankYouModal = new bootstrap.Modal($('#thankYouModal'));

            form.on('submit', function(e) {
                e.preventDefault();
                if (!form[0].checkValidity()) {
                    form.addClass('was-validated');
                    toastEl.removeClass('success error');
                    toastEl.addClass('error');
                    toastBody.text('Please fill in all required fields correctly.');
                    toast.show();
                    return;
                }

                // Validate phone numbers
                const phoneInputs = form.find('input[type="tel"]');
                let phoneValid = true;
                phoneInputs.each(function() {
                    if (!/^\d{10,11}$/.test(this.value)) {
                        $(this).addClass('is-invalid');
                        phoneValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });

                if (!phoneValid) {
                    toastEl.removeClass('success error');
                    toastEl.addClass('error');
                    toastBody.text('Phone numbers must be 10 or 11 digits.');
                    toast.show();
                    return;
                }

                // Validate custom reasons
                const reasonSelects = form.find('select[id^="reason_select_"]');
                let reasonValid = true;
                reasonSelects.each(function() {
                    const index = this.id.split('_').pop();
                    const customReasonTextarea = $(`#custom_reason_${index}`);
                    if (this.value === 'Other' && !customReasonTextarea.val().trim()) {
                        customReasonTextarea.addClass('is-invalid');
                        reasonValid = false;
                    } else {
                        customReasonTextarea.removeClass('is-invalid');
                    }
                });

                if (!reasonValid) {
                    toastEl.removeClass('success error');
                    toastEl.addClass('error');
                    toastBody.text('Please specify a reason for visits marked as "Other".');
                    toast.show();
                    return;
                }

                $.ajax({
                    url: form.attr('action'),
                    method: form.attr('method'),
                    data: form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        console.log('[SSCMS Web New Appointment] AJAX Success:', response);
                        if (response.success) {
                            thankYouModal.show();
                            form[0].reset();
                            form.removeClass('was-validated');
                            $('[id^="customReasonContainer_"]').addClass('d-none').find('textarea').removeAttr('required');
                            // Reset to one row
                            const container = $('#appointmentsContainer');
                            container.children().slice(1).remove();
                            appointmentCount = 0;
                            updateRemoveButtons();
                        } else {
                            toastEl.removeClass('success');
                            toastEl.addClass('error');
                            toastBody.text(response.message || 'Failed to submit appointments.');
                            toast.show();
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('[SSCMS Web New Appointment] AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                        toastEl.removeClass('success');
                        toastEl.addClass('error');
                        toastBody.text('Error: ' + (jqXHR.responseText || textStatus));
                        toast.show();
                    }
                });
            });

            // Redirect on modal hide
            $('#thankYouModal').on('hidden.bs.modal', function() {
                window.location.href = '/appointments/index.php';
            });

            // Notification function
            function showNotification(message, type) {
                const notification = $('<div>').addClass(`notification ${type}`).text(message);
                $('body').append(notification);
                setTimeout(() => notification.remove(), 2500);
            }
        });
    </script>
</body>
</html>