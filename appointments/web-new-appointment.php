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
    <meta name="description" content="Book Appointment — ICCBI Smart School Clinic Management System">
    <title>Book Appointment — SSCMS</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Flatpickr -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>

    <?php include '../includes/sscmslogo.php'; ?>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary:    'hsl(201 85% 39%)',
                        secondary:  'hsl(213 77% 54%)',
                        accent:     'hsl(144 100% 39%)',
                        foreground: 'hsl(222 47% 17%)',
                        muted:      'hsl(210 40% 96%)',
                        border:     'hsl(214 32% 91%)',
                    },
                    fontFamily: {
                        heading: ['Poppins', 'sans-serif'],
                        body:    ['DM Sans', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: hsl(210 40% 98%);
            color: hsl(222 47% 17%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        h1,h2,h3,h4,h5 { font-family: 'Poppins', sans-serif; }

        .bg-medical-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%230f73ba' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .bg-gradient-hero {
            background: linear-gradient(135deg, hsl(201 85% 35%) 0%, hsl(213 77% 45%) 50%, hsl(201 85% 39%) 100%);
        }

        .glass-card {
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: 0 8px 32px rgba(15,115,186,0.09);
        }

        .fade-up {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.55s ease forwards;
        }
        @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
        .d1{animation-delay:.05s} .d2{animation-delay:.1s} .d3{animation-delay:.15s}
        .d4{animation-delay:.2s}  .d5{animation-delay:.25s} .d6{animation-delay:.3s}

        .pulse-dot { animation: pulseDot 2s ease-in-out infinite; }
        @keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.4)} }

        .field-input {
            width: 100%;
            padding: 0.6rem 0.9rem;
            border: 1.5px solid hsl(214 32% 88%);
            border-radius: 0.65rem;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            background: white;
            color: hsl(222 47% 17%);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            height: 42px;
        }
        .field-input:focus {
            border-color: hsl(201 85% 45%);
            box-shadow: 0 0 0 3px hsl(201 85% 39% / 0.12);
        }
        .field-input.invalid {
            border-color: hsl(0 84% 60%);
            box-shadow: 0 0 0 3px hsl(0 84% 60% / 0.12);
        }
        textarea.field-input { height: auto; resize: vertical; }

        .field-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 500;
            color: hsl(215 16% 47%);
            margin-bottom: 0.35rem;
            font-family: 'Poppins', sans-serif;
        }
        .req { color: hsl(0 84% 60%); margin-left: 2px; }

        .btn-primary-solid {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: linear-gradient(135deg, hsl(201 85% 39%), hsl(213 77% 48%));
            color: white;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 0.88rem;
            padding: 0.65rem 1.4rem;
            border-radius: 9999px;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 14px hsl(201 85% 39% / 0.3);
        }
        .btn-primary-solid:hover { transform: translateY(-2px); box-shadow: 0 8px 20px hsl(201 85% 39% / 0.4); }
        .btn-primary-solid:disabled { opacity: 0.7; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: transparent;
            color: hsl(201 85% 39%);
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 0.88rem;
            padding: 0.65rem 1.4rem;
            border-radius: 9999px;
            border: 2px solid hsl(201 85% 39%);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-outline:hover { background: hsl(201 85% 39%); color: white; transform: translateY(-2px); }

        .flatpickr-input { cursor: pointer; }
        .flatpickr-calendar { font-family: 'DM Sans', sans-serif; border-radius: 1rem; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
        .flatpickr-day.selected { background: hsl(201 85% 39%) !important; border-color: hsl(201 85% 39%) !important; }

        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 0.9rem 1.3rem;
            border-radius: 1rem;
            font-size: 0.88rem;
            font-family: 'DM Sans', sans-serif;
            box-shadow: 0 10px 40px rgba(0,0,0,0.18);
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.3s, transform 0.3s;
            z-index: 9999;
            max-width: 320px;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            pointer-events: none;
        }
        #toast.show { opacity: 1; transform: translateY(0); }
        #toast.success { background: hsl(144 60% 15%); color: hsl(144 80% 85%); }
        #toast.error   { background: hsl(0 60% 20%); color: hsl(0 80% 85%); }
        #toast.info    { background: hsl(222 47% 17%); color: white; }

        #modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.55);
            backdrop-filter: blur(4px);
            z-index: 9998;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s;
        }
        #modal-overlay.show { opacity: 1; pointer-events: all; }
        #modal-box {
            background: white; border-radius: 1.5rem;
            padding: 2.5rem 2rem; text-align: center;
            max-width: 400px; width: 90%;
            transform: scale(0.92);
            transition: transform 0.3s;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        #modal-overlay.show #modal-box { transform: scale(1); }

        .step-indicator { display: flex; align-items: center; gap: 0; }
        .step { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Poppins', sans-serif; font-size: 0.78rem; font-weight: 700; }
        .step.active   { background: white; color: hsl(201 85% 39%); }
        .step.done     { background: hsl(144 100% 36%); color: white; }
        .step.inactive { background: rgba(255,255,255,0.2); color: rgba(255,255,255,0.6); }
        .step-line { width: 32px; height: 2px; background: rgba(255,255,255,0.3); }

        @media (max-width: 640px) {
            .fields-grid-3 { grid-template-columns: 1fr !important; }
            .fields-grid-2 { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body class="bg-medical-pattern">

<!-- NAVBAR -->
<nav class="fixed top-0 left-0 w-full z-50 bg-white/95 backdrop-blur-md shadow-sm" style="height:68px;">
    <div class="max-w-5xl mx-auto px-4 h-full flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-gradient-hero rounded-lg flex items-center justify-center shadow-md">
                <i data-lucide="stethoscope" class="w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="font-heading font-bold text-base leading-tight" style="color:hsl(222 47% 17%);">ICCBI Clinic</p>
                <p class="text-[11px] text-gray-400 leading-tight">Immaculate Conception College</p>
            </div>
        </div>
        <a href="/appointments/index.php"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-primary transition-colors px-4 py-2 rounded-full hover:bg-gray-100">
            <i data-lucide="calendar-days" class="w-4 h-4"></i>
            View Today's Slots
        </a>
    </div>
</nav>

<!-- HERO -->
<header class="bg-gradient-hero pt-[68px] relative overflow-hidden">
    <div class="absolute top-6 left-8 w-36 h-36 rounded-full bg-white/5" style="animation:float1 7s ease-in-out infinite;"></div>
    <div class="absolute bottom-2 right-16 w-20 h-20 rounded-full bg-white/8" style="animation:float1 5s ease-in-out infinite reverse;"></div>
    <div class="absolute top-12 right-1/3 text-white/15" style="animation:floatIcon 6s ease-in-out infinite;">
        <i data-lucide="calendar-check" class="w-10 h-10"></i>
    </div>
    <div class="absolute bottom-10 left-1/4 text-white/15" style="animation:floatIcon 8s ease-in-out infinite 1.2s;">
        <i data-lucide="heart" class="w-8 h-8"></i>
    </div>
    <style>
        @keyframes float1   { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-16px)} }
        @keyframes floatIcon{ 0%,100%{transform:translateY(0) rotate(0)} 50%{transform:translateY(-10px) rotate(6deg)} }
    </style>

    <div class="max-w-5xl mx-auto px-4 py-12 text-center relative z-10">
        <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white text-xs font-semibold px-4 py-1.5 rounded-full mb-5 fade-up">
            <span class="w-2 h-2 rounded-full bg-green-400 pulse-dot"></span>
            Appointments Open · Book Anytime
        </div>
        <h1 class="font-heading text-3xl md:text-4xl font-bold text-white mb-2 fade-up d1" style="text-shadow:0 2px 12px rgba(0,0,0,0.2);">
            Book a Clinic Appointment
        </h1>
        <p class="text-white/75 text-base mb-8 fade-up d2">
            Fill in the details below. You'll receive an SMS once your appointment is reviewed.
        </p>
        <div class="step-indicator justify-center fade-up d3">
            <div class="step active">1</div>
            <div class="step-line"></div>
            <div class="step inactive">2</div>
            <div class="step-line"></div>
            <div class="step inactive">3</div>
        </div>
        <div class="flex justify-center gap-8 mt-2 fade-up d3">
            <span class="text-white text-xs font-medium">Schedule</span>
            <span class="text-white/50 text-xs">Details</span>
            <span class="text-white/50 text-xs">Confirm</span>
        </div>
    </div>

    <div class="-mb-1">
        <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="w-full h-12 fill-[hsl(210_40%_98%)]">
            <path d="M0,40 C360,80 1080,0 1440,40 L1440,60 L0,60 Z"/>
        </svg>
    </div>
</header>

<!-- MAIN -->
<main class="max-w-5xl mx-auto px-4 py-10">
    <form id="appointmentForm" action="/appointments/submit_appointment.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="glass-card rounded-2xl p-6 mb-6 fade-up d2">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center" style="color:hsl(201 85% 39%);">
                    <i data-lucide="user" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="font-heading font-bold text-base" style="color:hsl(222 47% 17%);">Patient Information</h2>
                    <p class="text-xs text-gray-400">Enter your personal details</p>
                </div>
            </div>

            <div class="grid gap-4 fields-grid-2" style="grid-template-columns:repeat(2,1fr);">

                <!-- Name -->
                <div>
                    <label class="field-label">Full Name <span class="req">*</span></label>
                    <input type="text" name="patient_name" id="patient_name" class="field-input" placeholder="e.g. Juan Dela Cruz" required>
                </div>

                <!-- Category — all groups -->
                <div>
                    <label class="field-label">Category <span class="req">*</span></label>
                    <select name="category" id="category" class="field-input" required>
                        <option value="">Select Category</option>
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
                            <option value="Non-Teaching">Non-Teaching</option>
                            <option value="Non-Teaching Staff">Non-Teaching Staff</option>
                        </optgroup>
                        <optgroup label="🔧 Facilities &amp; Operations">
                            <option value="Maintenance Staff">Maintenance Staff</option>
                            <option value="Janitorial Staff">Janitorial Staff</option>
                            <option value="Security Staff">Security Staff</option>
                        </optgroup>
                        <optgroup label="👤 Other">
                            <option value="Staff">Staff</option>
                            <option value="Non-Student">Non-Student</option>
                            <option value="Visitor">Visitor</option>
                            <option value="Other">Other</option>
                        </optgroup>
                    </select>
                </div>

                <!-- Phone -->
                <div>
                    <label class="field-label">Phone Number <span class="req">*</span></label>
                    <div class="relative">
                        <input type="tel" name="phone" id="phone" class="field-input pl-10" placeholder="09XXXXXXXXX" pattern="[0-9]{10,11}" required>
                        <i data-lucide="phone" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" style="color:hsl(215 16% 60%);"></i>
                    </div>
                </div>

                <!-- Appointee -->
                <div>
                    <label class="field-label">Appoint With <span class="req">*</span></label>
                    <select name="appointee" id="appointee" class="field-input" required>
                        <option value="">Select Appointee</option>
                        <option value="Doctor">Doctor</option>
                        <option value="Nurse">Nurse</option>
                        <option value="Dentist">Dentist</option>
                    </select>
                </div>

                <!-- Date -->
                <div>
                    <label class="field-label">Appointment Date <span class="req">*</span></label>
                    <div class="relative">
                        <input type="text" name="appointment_date" id="appointment_date" class="field-input pr-9" placeholder="Select Date" required readonly>
                        <i data-lucide="calendar" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" style="color:hsl(215 16% 60%);"></i>
                    </div>
                </div>

                <!-- Time -->
                <div>
                    <label class="field-label">Appointment Time <span class="req">*</span></label>
                    <div class="relative">
                        <select name="appointment_time" id="appointment_time" class="field-input pr-9" required>
                            <option value="">Select Time</option>
                            <?php for ($hour = 7; $hour <= 16; $hour++): ?>
                                <?php $time = sprintf("%02d:00:00", $hour); ?>
                                <option value="<?= $time ?>"><?= date("h:i A", strtotime($time)) ?></option>
                            <?php endfor; ?>
                        </select>
                        <i data-lucide="clock" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" style="color:hsl(215 16% 60%);"></i>
                    </div>
                </div>

                <!-- Reason -->
                <div class="col-span-2">
                    <label class="field-label">Reason for Visit <span class="req">*</span></label>
                    <!-- Quick-fill suggestion chips -->
                    <div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-bottom:0.5rem;">
                        <?php
                        $chips = [
                            'General Check-up','Fever / High Temperature','Headache / Migraine',
                            'Stomach Pain','Cough and Colds','Wound / Injury Care',
                            'Toothache / Dental Pain','Eye Irritation','Allergic Reaction',
                            'Dizziness / Fainting','Blood Pressure Check','Medical Certificate Request',
                            'Follow-up Consultation','Menstrual Pain','Vaccination / Immunization',
                        ];
                        foreach ($chips as $chip): ?>
                        <button type="button" onclick="appendReason(this.dataset.val)" data-val="<?= htmlspecialchars($chip) ?>"
                            style="display:inline-flex;align-items:center;gap:.25rem;background:hsl(201 85% 94%);color:hsl(201 85% 32%);border:1px solid hsl(201 85% 80%);font-size:.72rem;font-weight:600;font-family:'Poppins',sans-serif;padding:.2rem .6rem;border-radius:9999px;cursor:pointer;transition:all .15s;white-space:nowrap;"
                            onmouseover="this.style.background='hsl(201 85% 39%)';this.style.color='white';"
                            onmouseout="this.style.background='hsl(201 85% 94%)';this.style.color='hsl(201 85% 32%)';">
                            <?= htmlspecialchars($chip) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <textarea name="reason" id="reason" class="field-input" rows="4"
                              placeholder="Please describe your reason for visiting, or tap a suggestion above…" required></textarea>
                </div>

            </div>
        </div>

        <!-- Submit bar -->
        <div class="glass-card rounded-2xl p-6 fade-up d5">
            <div class="flex flex-col sm:flex-row items-center gap-4 justify-between">
                <div>
                    <p class="font-heading font-semibold text-sm" style="color:hsl(222 47% 17%);">Ready to submit?</p>
                    <p class="text-xs text-gray-400 mt-0.5">Double-check all details before submitting.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/appointments/index.php" class="btn-outline">
                        <i data-lucide="calendar-days" class="w-4 h-4"></i> View Slots
                    </a>
                    <button type="submit" id="submitBtn" class="btn-primary-solid">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        Submit Appointment
                    </button>
                </div>
            </div>
        </div>

    </form>
</main>

<!-- FOOTER -->
<footer class="mt-16 bg-foreground text-white" style="background:hsl(222 47% 17%);">
    <div class="max-w-5xl mx-auto px-4 py-9 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-white/10 rounded-lg flex items-center justify-center">
                <i data-lucide="heart-pulse" class="w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="font-heading font-bold text-sm">ICCBI Clinic · SSCMS</p>
                <p class="text-white/40 text-xs">Smart School Clinic Management System</p>
            </div>
        </div>
        <p class="text-white/35 text-xs text-center">
            © 2025 Immaculate Conception College of Balayan, Inc.
        </p>
        <a href="/appointments/index.php" class="text-xs font-semibold underline underline-offset-2 hover:opacity-70 transition-opacity" style="color:hsl(201 85% 65%);">
            View Today's Schedule →
        </a>
    </div>
</footer>

<!-- SUCCESS MODAL -->
<div id="modal-overlay">
    <div id="modal-box">
        <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i data-lucide="check-circle" class="w-8 h-8 text-green-600"></i>
        </div>
        <h3 class="font-heading font-bold text-xl mb-2" style="color:hsl(222 47% 17%);">Appointment Submitted!</h3>
        <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Your appointment request has been received. You'll be notified via <strong>SMS</strong> once it's reviewed and approved.
        </p>
        <button id="modal-ok" class="btn-primary-solid mx-auto">
            <i data-lucide="arrow-right" class="w-4 h-4"></i> View Schedule
        </button>
    </div>
</div>

<!-- Toast -->
<div id="toast">
    <i data-lucide="info" class="w-4 h-4 shrink-0" id="toast-icon"></i>
    <span id="toast-msg"></span>
</div>

<script>
lucide.createIcons();

// Reason chip helper
function appendReason(val) {
    const ta = document.getElementById('reason');
    if (!ta.value.trim()) {
        ta.value = val;
    } else if (!ta.value.includes(val)) {
        ta.value = ta.value.trimEnd() + '; ' + val;
    }
    ta.classList.remove('invalid');
    ta.focus();
}

document.addEventListener('DOMContentLoaded', function () {

    // Flatpickr
    flatpickr("#appointment_date", {
        dateFormat: "Y-m-d",
        minDate: "today",
        maxDate: new Date(new Date().setDate(new Date().getDate() + 30)),
        disable: [d => d.getDay() === 0 || d.getDay() === 6]
    });

    // Toast
    let toastTimer;
    function showToast(msg, type = 'info') {
        clearTimeout(toastTimer);
        const t = document.getElementById('toast');
        document.getElementById('toast-msg').textContent = msg;
        const icon = document.getElementById('toast-icon');
        icon.setAttribute('data-lucide', type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info');
        lucide.createIcons();
        t.className = 'show ' + type;
        toastTimer = setTimeout(() => { t.className = type; }, 4500);
    }

    // Clear invalid on interaction
    document.querySelectorAll('.field-input').forEach(input => {
        input.addEventListener('focus',  () => input.classList.remove('invalid'));
        input.addEventListener('change', () => input.classList.remove('invalid'));
        input.addEventListener('input',  () => input.classList.remove('invalid'));
    });

    // Form submit
    const form = document.getElementById('appointmentForm');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        document.querySelectorAll('.field-input').forEach(input => input.classList.remove('invalid'));

        const nameEl      = document.getElementById('patient_name');
        const catEl       = document.getElementById('category');
        const phoneEl     = document.getElementById('phone');
        const appointeeEl = document.getElementById('appointee');
        const dateEl      = document.getElementById('appointment_date');
        const timeEl      = document.getElementById('appointment_time');
        const reasonEl    = document.getElementById('reason');

        if (!nameEl.value.trim())                       { nameEl.classList.add('invalid');      showToast('Please enter patient full name.', 'error');     nameEl.focus();      return; }
        if (!catEl.value)                               { catEl.classList.add('invalid');        showToast('Please select a category.', 'error');            catEl.focus();       return; }
        if (!phoneEl.value.trim())                      { phoneEl.classList.add('invalid');      showToast('Please enter phone number.', 'error');           phoneEl.focus();     return; }
        if (!/^\d{10,11}$/.test(phoneEl.value.trim())) { phoneEl.classList.add('invalid');      showToast('Phone number must be 10–11 digits.', 'error');   phoneEl.focus();     return; }
        if (!appointeeEl.value)                         { appointeeEl.classList.add('invalid');  showToast('Please select an appointee.', 'error');          appointeeEl.focus(); return; }
        if (!dateEl.value.trim())                       { dateEl.classList.add('invalid');       showToast('Please select an appointment date.', 'error');   dateEl.focus();      return; }
        if (!timeEl.value)                              { timeEl.classList.add('invalid');       showToast('Please select an appointment time.', 'error');   timeEl.focus();      return; }
        if (!reasonEl.value.trim())                     { reasonEl.classList.add('invalid');     showToast('Please enter reason for visit.', 'error');       reasonEl.focus();    return; }

        // AJAX submit
        const btn = document.getElementById('submitBtn');
        const orig = btn.innerHTML;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-opacity=".3"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg> Submitting…';
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
                    document.getElementById('modal-overlay').classList.add('show');
                    lucide.createIcons();
                    form.reset();
                    const fp = document.getElementById('appointment_date')._flatpickr;
                    if (fp) fp.clear();
                } else {
                    showToast(res?.message || 'Failed to submit. Please try again.', 'error');
                }
            },
            error: function (xhr, status, err) {
                console.error('[SSCMS] AJAX Error:', status, err, xhr.responseText);
                btn.innerHTML = orig;
                btn.disabled = false;
                lucide.createIcons();
                let msg = 'Server error. Please try again.';
                try {
                    const parsed = JSON.parse(xhr.responseText);
                    if (parsed?.message) msg = parsed.message;
                } catch (_) {
                    if (xhr.responseText) {
                        msg = 'Server error: ' + xhr.responseText.substring(0, 150).replace(/<[^>]+>/g, '').trim();
                    }
                }
                showToast(msg, 'error');
            }
        });
    });

    // Modal OK
    document.getElementById('modal-ok').addEventListener('click', function () {
        window.location.href = '/appointments/index.php';
    });
});
</script>
</body>
</html>