<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS New Appointment] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System">
    <meta name="author" content="ICCB">
    <title>New Appointment - SSCMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php include '../includes/sscmslogo.php'; ?>

    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --header-height: 70px;
            --transition-speed: 0.3s;
            --primary:        hsl(201 85% 39%);
            --primary-dark:   hsl(201 85% 32%);
            --primary-light:  hsl(201 85% 94%);
            --secondary:      hsl(213 77% 54%);
            --foreground:     hsl(222 47% 17%);
            --border:         hsl(214 32% 91%);
            --text-secondary: hsl(215 16% 47%);
            --background:     hsl(210 40% 98%);
            --danger:         hsl(0 84% 60%);
            --card-bg:        #ffffff;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--background);
            color: var(--foreground);
            margin: 0; padding: 0;
            overflow-x: hidden;
        }
        h1,h2,h3,h4,h5 { font-family: 'Poppins', sans-serif; }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1.5rem 2rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }
        .container-fluid { max-width: 1100px; padding: 0; }

        /* ── Hero ── */
        .page-hero {
            background: linear-gradient(135deg, hsl(201 85% 35%) 0%, hsl(213 77% 45%) 50%, hsl(201 85% 39%) 100%);
            border-radius: 1.25rem;
            padding: 1.75rem 2rem;
            margin-bottom: 1.25rem;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before { content:''; position:absolute; top:-30px; right:-30px; width:120px; height:120px; border-radius:50%; background:rgba(255,255,255,0.07); }
        .page-hero::after  { content:''; position:absolute; bottom:-20px; left:40px; width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.05); }
        .hero-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; }
        .hero-title { color:white; font-size:1.35rem; font-weight:700; margin:0 0 .2rem; display:flex; align-items:center; gap:.55rem; }
        .hero-sub   { color:rgba(255,255,255,.7); font-size:.8rem; font-family:'DM Sans',sans-serif; }
        .live-badge { display:inline-flex; align-items:center; gap:.4rem; background:rgba(255,255,255,.15); backdrop-filter:blur(8px); color:white; font-size:.74rem; font-weight:600; font-family:'Poppins',sans-serif; padding:.35rem .85rem; border-radius:9999px; }
        .pulse-dot  { width:8px; height:8px; background:hsl(144 100% 50%); border-radius:50%; animation:pulseDot 2s ease-in-out infinite; }
        @keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.5)} }

        /* ── Breadcrumb ── */
        .breadcrumb-bar { background:white; border:1px solid var(--border); border-radius:.75rem; padding:.5rem 1rem; font-size:.78rem; margin-bottom:1.25rem; box-shadow:0 1px 4px rgba(0,0,0,.04); }
        .breadcrumb-bar a { color:var(--primary); text-decoration:none; font-weight:500; }
        .breadcrumb-bar a:hover { text-decoration:underline; }
        .breadcrumb-bar .sep { color:var(--text-secondary); margin:0 .4rem; }
        .breadcrumb-bar .cur { color:var(--text-secondary); }

        /* ── Card ── */
        .glass-card { background:rgba(255,255,255,.94); border:1px solid rgba(255,255,255,.4); box-shadow:0 4px 24px rgba(15,115,186,.07); border-radius:1.25rem; margin-bottom:1.25rem; }
        .card-sec-hdr { padding:1.2rem 1.5rem 0; display:flex; align-items:center; gap:.75rem; }
        .card-sec-icon { width:38px; height:38px; background:var(--primary-light); color:var(--primary); border-radius:.75rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .card-sec-title { font-size:.95rem; font-weight:700; color:var(--foreground); margin:0; }
        .card-sec-sub   { font-size:.74rem; color:var(--text-secondary); }
        .card-body-inner { padding:1.2rem 1.5rem 1.5rem; }

        /* ── Fields ── */
        .field-label { display:block; font-size:.8rem; font-weight:500; color:var(--text-secondary); margin-bottom:.3rem; font-family:'Poppins',sans-serif; }
        .req { color:var(--danger); margin-left:2px; }

        .field-input {
            width:100%; padding:.6rem .9rem;
            border:1.5px solid var(--border); border-radius:.65rem;
            font-family:'DM Sans',sans-serif; font-size:.88rem;
            background:white; color:var(--foreground);
            height:42px; outline:none;
            transition:border-color .2s, box-shadow .2s;
            appearance:none; -webkit-appearance:none;
        }
        .field-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px hsl(201 85% 39% / .12); }
        .field-input.invalid { border-color:var(--danger); box-shadow:0 0 0 3px hsl(0 84% 60% / .1); }
        textarea.field-input { height:auto; resize:vertical; }

        .iw { position:relative; }
        .iw .field-input  { padding-left:2.4rem; }
        .iw .ii           { position:absolute; left:.72rem; top:50%; transform:translateY(-50%); color:var(--text-secondary); pointer-events:none; width:15px; height:15px; }
        .iwr .field-input { padding-right:2.4rem; }
        .iwr .iir         { position:absolute; right:.72rem; top:50%; transform:translateY(-50%); color:var(--text-secondary); pointer-events:none; width:15px; height:15px; }

        .fields-grid { display:grid; gap:1rem; grid-template-columns:repeat(2,1fr); }
        .full-w { grid-column:1/-1; }

        /* ── Category groups ── */
        optgroup { font-style:normal; font-weight:600; font-size:.8rem; }

        /* ── Reason suggestions ── */
        .reason-chips { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.55rem; }
        .r-chip {
            display:inline-flex; align-items:center; gap:.3rem;
            background:var(--primary-light); color:var(--primary);
            border:1px solid hsl(201 85% 82%);
            font-size:.73rem; font-weight:500; font-family:'Poppins',sans-serif;
            padding:.22rem .65rem; border-radius:9999px;
            cursor:pointer; transition:all .18s; white-space:nowrap;
            user-select:none;
        }
        .r-chip:hover { background:var(--primary); color:white; border-color:var(--primary); }

        /* ── Submit bar ── */
        .submit-bar { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1.2rem 1.5rem; border-top:1px solid var(--border); flex-wrap:wrap; }
        .sb-info p  { font-family:'Poppins',sans-serif; font-weight:600; font-size:.88rem; color:var(--foreground); margin:0 0 .15rem; }
        .sb-info span { font-size:.74rem; color:var(--text-secondary); }
        .sb-actions { display:flex; gap:.65rem; align-items:center; }

        .btn-solid {
            display:inline-flex; align-items:center; gap:.4rem;
            background:linear-gradient(135deg, hsl(201 85% 39%), hsl(213 77% 48%));
            color:white; font-family:'Poppins',sans-serif; font-weight:600; font-size:.83rem;
            padding:.58rem 1.35rem; border-radius:9999px; border:none; cursor:pointer;
            transition:transform .2s, box-shadow .2s;
            box-shadow:0 4px 14px hsl(201 85% 39% / .3);
            text-decoration:none;
        }
        .btn-solid:hover   { transform:translateY(-2px); box-shadow:0 8px 20px hsl(201 85% 39% / .4); color:white; }
        .btn-solid:disabled{ opacity:.7; cursor:not-allowed; transform:none; }

        .btn-ol {
            display:inline-flex; align-items:center; gap:.4rem;
            background:transparent; color:var(--primary);
            font-family:'Poppins',sans-serif; font-weight:600; font-size:.83rem;
            padding:.55rem 1.15rem; border-radius:9999px;
            border:2px solid var(--primary); cursor:pointer;
            transition:all .2s; text-decoration:none;
        }
        .btn-ol:hover { background:var(--primary); color:white; transform:translateY(-2px); }

        /* ── Toast ── */
        #toast {
            position:fixed; bottom:2rem; right:2rem;
            padding:.85rem 1.2rem; border-radius:1rem;
            font-size:.86rem; font-family:'DM Sans',sans-serif;
            box-shadow:0 10px 40px rgba(0,0,0,.18);
            opacity:0; transform:translateY(12px);
            transition:opacity .3s, transform .3s;
            z-index:9999; max-width:340px;
            display:flex; align-items:center; gap:.55rem;
            pointer-events:none;
        }
        #toast.show { opacity:1; transform:translateY(0); pointer-events:all; }
        #toast.success { background:hsl(144 60% 13%); color:hsl(144 80% 82%); border-left:3px solid hsl(144 100% 39%); }
        #toast.error   { background:hsl(0 60% 18%);   color:hsl(0 80% 82%);   border-left:3px solid var(--danger); }

        /* ── Modal ── */
        #modal-ov {
            position:fixed; inset:0; background:rgba(0,0,0,.55);
            backdrop-filter:blur(4px); z-index:9998;
            display:flex; align-items:center; justify-content:center;
            opacity:0; pointer-events:none; transition:opacity .3s;
        }
        #modal-ov.show { opacity:1; pointer-events:all; }
        #modal-box {
            background:white; border-radius:1.5rem;
            padding:2.4rem 2rem; text-align:center;
            max-width:400px; width:90%;
            transform:scale(.92); transition:transform .3s;
            box-shadow:0 20px 60px rgba(0,0,0,.2);
        }
        #modal-ov.show #modal-box { transform:scale(1); }

        footer { background:white; border-top:1px solid var(--border); padding:1rem 1.5rem; font-size:.77rem; color:var(--text-secondary); text-align:center; margin-top:2rem; }

        .flatpickr-calendar { font-family:'DM Sans',sans-serif; border-radius:1rem; box-shadow:0 10px 40px rgba(0,0,0,.14); }
        .flatpickr-day.selected { background:var(--primary)!important; border-color:var(--primary)!important; }

        @keyframes slideUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
        .su  { animation:slideUp .45s ease forwards; }
        .d1  { animation-delay:.06s; opacity:0; }
        .d2  { animation-delay:.13s; opacity:0; }
        .d3  { animation-delay:.20s; opacity:0; }

        @keyframes spin { to { transform:rotate(360deg); } }

        @media (max-width:992px) { :root { --sidebar-width:var(--sidebar-collapsed-width); } }
        @media (max-width:768px)  {
            .content { margin-left:0; padding:1rem; }
            .fields-grid { grid-template-columns:1fr; }
            .hero-inner  { flex-direction:column; align-items:flex-start; }
            .submit-bar  { flex-direction:column; align-items:stretch; }
            .sb-actions  { justify-content:flex-end; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">

                <!-- Hero -->
                <div class="page-hero su">
                    <div class="hero-inner">
                        <div>
                            <h1 class="hero-title">
                                <i data-lucide="calendar-plus" style="width:21px;height:21px;"></i>
                                New Appointment
                            </h1>
                            <p class="hero-sub">Book a clinic appointment for a patient</p>
                        </div>
                        <div class="live-badge">
                            <span class="pulse-dot"></span>
                            Appointments Open
                        </div>
                    </div>
                </div>

                <!-- Breadcrumb -->
                <div class="breadcrumb-bar su d1">
                    <a href="/dashboard.php"><i class="fas fa-home" style="font-size:.72rem;"></i> Dashboard</a>
                    <span class="sep">/</span>
                    <a href="/appointments/appointment-list.php">Appointments</a>
                    <span class="sep">/</span>
                    <span class="cur">New Appointment</span>
                </div>

                <!-- Form Card -->
                <div class="glass-card su d2">
                    <div class="card-sec-hdr">
                        <div class="card-sec-icon">
                            <i data-lucide="user-plus" style="width:17px;height:17px;"></i>
                        </div>
                        <div>
                            <p class="card-sec-title">Book a New Appointment</p>
                            <p class="card-sec-sub">Fill in all required fields below</p>
                        </div>
                        <a href="/appointments/appointment-list.php" class="btn-ol ms-auto" style="font-size:.77rem;padding:.4rem .95rem;">
                            <i data-lucide="list" style="width:13px;height:13px;"></i>
                            View Appointments
                        </a>
                    </div>

                    <div class="card-body-inner">
                        <form id="appointmentForm" action="/appointments/submit_appointment.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <div class="fields-grid">

                                <!-- Name -->
                                <div>
                                    <label for="patient_name" class="field-label">Full Name <span class="req">*</span></label>
                                    <input type="text" name="patient_name" id="patient_name" class="field-input" placeholder="Enter Full Name" required>
                                </div>

                                <!-- Category -->
                                <div>
                                    <label for="category" class="field-label">Category <span class="req">*</span></label>
                                    <select name="category" id="category" class="field-input" required>
                                        <option value="">— Select Category —</option>
                                        <optgroup label="🎒 Students">
                                            <option value="Pre School">Pre School</option>
                                            <option value="Elementary">Elementary</option>
                                            <option value="JHS">Junior High School (JHS)</option>
                                            <option value="SHS">Senior High School (SHS)</option>
                                            <option value="College">College</option>
                                            <option value="Graduate School">Graduate School</option>
                                            <option value="Alumni">Alumni</option>
                                        </optgroup>
                                        <optgroup label="👩‍🏫 Teaching Staff">
                                            <option value="Teacher">Teacher</option>
                                            <option value="Faculty">Faculty</option>
                                            <option value="Faculty Staff">Faculty Staff</option>
                                        </optgroup>
                                        <optgroup label="🏢 Administrative Staff">
                                            <option value="Administrative Staff">Administrative Staff</option>
                                            <option value="Clinic Staff">Clinic Staff</option>
                                            <option value="Support Staff">Support Staff</option>
                                            <option value="Non-Teaching Staff">Non-Teaching Staff</option>
                                        </optgroup>
                                        <optgroup label="🔧 Facilities & Operations">
                                            <option value="Maintenance Staff">Maintenance Staff</option>
                                            <option value="Janitorial Staff">Janitorial Staff</option>
                                            <option value="Security Staff">Security Staff</option>
                                        </optgroup>
                                        <optgroup label="👤 Other">
                                            <option value="Non-Student">Non-Student</option>
                                            <option value="Visitor">Visitor</option>
                                            <option value="Other">Other</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label for="phone" class="field-label">Phone Number <span class="req">*</span></label>
                                    <div class="iw">
                                        <input type="tel" name="phone" id="phone" class="field-input" placeholder="09XXXXXXXXX" pattern="[0-9]{10,11}" required>
                                        <i data-lucide="phone" class="ii"></i>
                                    </div>
                                </div>

                                <!-- Appointee -->
                                <div>
                                    <label for="appointee" class="field-label">Appoint With <span class="req">*</span></label>
                                    <select name="appointee" id="appointee" class="field-input" required>
                                        <option value="">— Select Appointee —</option>
                                        <option value="Doctor">🩺 Doctor</option>
                                        <option value="Nurse">💊 Nurse</option>
                                        <option value="Dentist">🦷 Dentist</option>
                                    </select>
                                </div>

                                <!-- Date -->
                                <div>
                                    <label for="appointment_date" class="field-label">Appointment Date <span class="req">*</span></label>
                                    <div class="iwr" style="position:relative;">
                                        <input type="text" name="appointment_date" id="appointment_date" class="field-input" placeholder="Select Date" required readonly>
                                        <i data-lucide="calendar" class="iir"></i>
                                    </div>
                                </div>

                                <!-- Time -->
                                <div>
                                    <label for="appointment_time" class="field-label">Appointment Time <span class="req">*</span></label>
                                    <div class="iwr" style="position:relative;">
                                        <select name="appointment_time" id="appointment_time" class="field-input" style="padding-right:2.4rem;" required>
                                            <option value="">— Select Time —</option>
                                            <?php for ($hour = 7; $hour <= 16; $hour++): ?>
                                                <?php $t = sprintf("%02d:00:00", $hour); ?>
                                                <option value="<?= $t ?>"><?= date("h:i A", strtotime($t)) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <i data-lucide="clock" class="iir"></i>
                                    </div>
                                </div>

                                <!-- Reason -->
                                <div class="full-w">
                                    <label for="reason" class="field-label">Reason for Visit <span class="req">*</span></label>

                                    <!-- Quick suggestion chips -->
                                    <div class="reason-chips" id="reasonChips">
                                        <span class="r-chip" data-val="General Check-up"><i data-lucide="stethoscope" style="width:11px;height:11px;"></i> General Check-up</span>
                                        <span class="r-chip" data-val="Fever / High Temperature"><i data-lucide="thermometer" style="width:11px;height:11px;"></i> Fever</span>
                                        <span class="r-chip" data-val="Headache / Migraine"><i data-lucide="brain" style="width:11px;height:11px;"></i> Headache</span>
                                        <span class="r-chip" data-val="Stomach Pain / Abdominal Pain"><i data-lucide="activity" style="width:11px;height:11px;"></i> Stomach Pain</span>
                                        <span class="r-chip" data-val="Cough and Colds"><i data-lucide="wind" style="width:11px;height:11px;"></i> Cough &amp; Colds</span>
                                        <span class="r-chip" data-val="Wound / Injury Care"><i data-lucide="bandage" style="width:11px;height:11px;"></i> Wound / Injury</span>
                                        <span class="r-chip" data-val="Toothache / Dental Pain"><i data-lucide="smile" style="width:11px;height:11px;"></i> Toothache</span>
                                        <span class="r-chip" data-val="Eye Irritation / Redness"><i data-lucide="eye" style="width:11px;height:11px;"></i> Eye Irritation</span>
                                        <span class="r-chip" data-val="Allergic Reaction"><i data-lucide="alert-circle" style="width:11px;height:11px;"></i> Allergy</span>
                                        <span class="r-chip" data-val="Dizziness / Fainting"><i data-lucide="zap-off" style="width:11px;height:11px;"></i> Dizziness</span>
                                        <span class="r-chip" data-val="Blood Pressure Check"><i data-lucide="heart-pulse" style="width:11px;height:11px;"></i> BP Check</span>
                                        <span class="r-chip" data-val="Medical Certificate Request"><i data-lucide="file-text" style="width:11px;height:11px;"></i> Medical Certificate</span>
                                        <span class="r-chip" data-val="Follow-up Consultation"><i data-lucide="refresh-cw" style="width:11px;height:11px;"></i> Follow-up</span>
                                        <span class="r-chip" data-val="Menstrual Pain / Dysmenorrhea"><i data-lucide="circle-dot" style="width:11px;height:11px;"></i> Dysmenorrhea</span>
                                        <span class="r-chip" data-val="Vaccination / Immunization"><i data-lucide="syringe" style="width:11px;height:11px;"></i> Vaccination</span>
                                    </div>

                                    <textarea name="reason" id="reason" class="field-input" rows="4"
                                              placeholder="Describe reason for visit, or tap a suggestion above…" required></textarea>
                                </div>

                            </div><!-- /.fields-grid -->
                        </form>
                    </div>

                    <!-- Submit bar -->
                    <div class="submit-bar">
                        <div class="sb-info">
                            <p>Ready to submit?</p>
                            <span>Double-check all details before submitting.</span>
                        </div>
                        <div class="sb-actions">
                            <a href="/appointments/appointment-list.php" class="btn-ol">
                                <i data-lucide="list" style="width:13px;height:13px;"></i> View List
                            </a>
                            <button type="submit" form="appointmentForm" id="submitBtn" class="btn-solid">
                                <i data-lucide="send" style="width:13px;height:13px;"></i> Submit Appointment
                            </button>
                        </div>
                    </div>
                </div><!-- /.glass-card -->

            </div>
        </main>

        <footer>
            <i data-lucide="hospital" style="width:12px;height:12px;display:inline-block;vertical-align:middle;margin-right:4px;"></i>
            IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. © SSCMS 2025
        </footer>
    </div>

    <!-- Success Modal -->
    <div id="modal-ov">
        <div id="modal-box">
            <div style="width:62px;height:62px;background:hsl(144 60% 92%);border-radius:1rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                <i data-lucide="check-circle" style="width:30px;height:30px;color:hsl(144 60% 38%);"></i>
            </div>
            <h3 style="font-family:'Poppins',sans-serif;font-weight:700;font-size:1.15rem;margin-bottom:.5rem;color:hsl(222 47% 17%);">Appointment Booked!</h3>
            <p style="font-size:.83rem;color:#6b7280;line-height:1.65;margin-bottom:1.4rem;">
                The appointment has been saved and is now <strong>pending</strong> review.
            </p>
            <button id="modal-ok" class="btn-solid" style="margin:0 auto;">
                <i data-lucide="list" style="width:13px;height:13px;"></i> View Appointments
            </button>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast">
        <i id="toastIcon" data-lucide="info" style="width:15px;height:15px;flex-shrink:0;"></i>
        <span id="toastMsg"></span>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
    lucide.createIcons();

    document.addEventListener('DOMContentLoaded', function () {

        // ── Flatpickr ──
        flatpickr("#appointment_date", {
            dateFormat: "Y-m-d",
            minDate: "today",
            maxDate: new Date(new Date().setDate(new Date().getDate() + 30)),
            disable: [d => d.getDay() === 0 || d.getDay() === 6]
        });

        // ── Reason chips ──
        document.querySelectorAll('.r-chip').forEach(chip => {
            chip.addEventListener('click', function () {
                const ta = document.getElementById('reason');
                const val = this.dataset.val;
                // If empty, just set. If already has text, append with separator.
                if (!ta.value.trim()) {
                    ta.value = val;
                } else if (!ta.value.includes(val)) {
                    ta.value = ta.value.trimEnd() + '; ' + val;
                }
                ta.classList.remove('invalid');
                ta.focus();
            });
        });

        // ── Toast helper ──
        let toastTimer;
        function showToast(msg, type = 'error') {
            clearTimeout(toastTimer);
            const t  = document.getElementById('toast');
            const ic = document.getElementById('toastIcon');
            document.getElementById('toastMsg').textContent = msg;
            ic.setAttribute('data-lucide', type === 'success' ? 'check-circle' : 'alert-circle');
            lucide.createIcons();
            t.className = 'show ' + type;
            toastTimer = setTimeout(() => { t.className = type; }, 4800);
        }

        // ── Clear invalid on input ──
        document.querySelectorAll('.field-input').forEach(el => {
            el.addEventListener('input',  () => el.classList.remove('invalid'));
            el.addEventListener('change', () => el.classList.remove('invalid'));
        });

        // ── Form submit ──
        const form = document.getElementById('appointmentForm');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            document.querySelectorAll('.field-input').forEach(el => el.classList.remove('invalid'));

            const nameEl      = document.getElementById('patient_name');
            const catEl       = document.getElementById('category');
            const phoneEl     = document.getElementById('phone');
            const appointeeEl = document.getElementById('appointee');
            const dateEl      = document.getElementById('appointment_date');
            const timeEl      = document.getElementById('appointment_time');
            const reasonEl    = document.getElementById('reason');

            if (!nameEl.value.trim())                        { nameEl.classList.add('invalid');      showToast("Please enter the patient's full name.", 'error'); nameEl.focus(); return; }
            if (!catEl.value)                                { catEl.classList.add('invalid');        showToast('Please select a category.', 'error'); catEl.focus(); return; }
            if (!phoneEl.value.trim())                       { phoneEl.classList.add('invalid');      showToast('Please enter a phone number.', 'error'); phoneEl.focus(); return; }
            if (!/^\d{10,11}$/.test(phoneEl.value.trim()))  { phoneEl.classList.add('invalid');      showToast('Phone number must be 10 or 11 digits.', 'error'); phoneEl.focus(); return; }
            if (!appointeeEl.value)                          { appointeeEl.classList.add('invalid');  showToast('Please select an appointee.', 'error'); appointeeEl.focus(); return; }
            if (!dateEl.value.trim())                        { dateEl.classList.add('invalid');       showToast('Please select an appointment date.', 'error'); dateEl.focus(); return; }
            if (!timeEl.value)                               { timeEl.classList.add('invalid');       showToast('Please select an appointment time.', 'error'); timeEl.focus(); return; }
            if (!reasonEl.value.trim())                      { reasonEl.classList.add('invalid');     showToast('Please enter a reason for the visit.', 'error'); reasonEl.focus(); return; }

            // ── AJAX ──
            const btn = document.getElementById('submitBtn');
            const orig = btn.innerHTML;
            btn.innerHTML = '<svg style="width:13px;height:13px;animation:spin .7s linear infinite;display:inline-block;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg> Submitting…';
            btn.disabled = true;

            $.ajax({
                url: form.action,
                method: 'POST',
                data: $(form).serialize(),
                dataType: 'json',
                success: function (res) {
                    btn.innerHTML = orig;
                    btn.disabled = false;
                    lucide.createIcons();
                    if (res && res.success) {
                        document.getElementById('modal-ov').classList.add('show');
                        lucide.createIcons();
                        form.reset();
                        const fp = document.getElementById('appointment_date')._flatpickr;
                        if (fp) fp.clear();
                    } else {
                        showToast(res?.message || 'Failed to book appointment. Please try again.', 'error');
                    }
                },
                error: function (xhr, status, err) {
                    console.error('[SSCMS] AJAX Error:', status, err);
                    console.error('[SSCMS] Response:', xhr.responseText);
                    btn.innerHTML = orig;
                    btn.disabled = false;
                    lucide.createIcons();
                    let msg = 'Server error. Please try again.';
                    try {
                        const parsed = JSON.parse(xhr.responseText);
                        if (parsed?.message) msg = parsed.message;
                    } catch (_) {
                        // response wasn't JSON — likely a PHP fatal error
                        if (xhr.responseText) {
                            msg = 'Server error: ' + xhr.responseText.substring(0, 120).replace(/<[^>]+>/g, '').trim();
                        }
                    }
                    showToast(msg, 'error');
                }
            });
        });

        // ── Modal OK ──
        document.getElementById('modal-ok').addEventListener('click', function () {
            window.location.href = '/appointments/appointment-list.php';
        });
    });
    </script>
</body>
</html>