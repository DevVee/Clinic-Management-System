<?php
// Initialize session and database connection
session_start();
require_once '../config/database.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Get today's date in Asia/Manila
$today = date('Y-m-d');

try {
    // Fetch approved appointments for today
    $stmt = $conn->prepare("
        SELECT appointment_time
        FROM appointments
        WHERE appointment_date = ? AND status = 'approved'
        AND HOUR(appointment_time) BETWEEN 7 AND 16
        ORDER BY appointment_time ASC
    ");
    $stmt->execute([$today]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Today's Appointments] Fetched " . count($appointments) . " approved appointments for $today");
} catch (Exception $e) {
    error_log("[SSCMS Today's Appointments] Query error: " . $e->getMessage());
    $appointments = [];
}

// Generate hourly time slots from 7:00 AM to 4:00 PM
$time_slots = [];
for ($hour = 7; $hour <= 16; $hour++) {
    $time_str = sprintf('%02d:00:00', $hour);
    $occupied = false;
    foreach ($appointments as $appt) {
        if (date('H:i:s', strtotime($appt['appointment_time'])) === $time_str) {
            $occupied = true;
            break;
        }
    }
    $time_slots[] = [
        'time' => $time_str,
        'occupied' => $occupied
    ];
}

// Calculate statistics
$total_slots = count($time_slots);
$occupied_slots = count(array_filter($time_slots, fn($slot) => $slot['occupied']));
$available_slots = $total_slots - $occupied_slots;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View today's approved appointments at Immaculate Conception College Clinic">
    <title>Today's Schedule — ICCBI Clinic</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <?php include '../includes/sscmslogo.php'; ?>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary:   'hsl(201 85% 39%)',
                        secondary: 'hsl(213 77% 54%)',
                        accent:    'hsl(144 100% 39%)',
                        warning:   'hsl(32 100% 50%)',
                        background:'hsl(210 40% 98%)',
                        foreground:'hsl(222 47% 17%)',
                        muted:     'hsl(210 40% 96%)',
                        border:    'hsl(214 32% 91%)',
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
        :root {
            --primary:   201 85% 39%;
            --secondary: 213 77% 54%;
            --accent:    144 100% 39%;
            --warning:   32 100% 50%;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: hsl(210 40% 98%);
            color: hsl(222 47% 17%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5 { font-family: 'Poppins', sans-serif; }

        /* ── Background pattern ── */
        .bg-medical-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%230f73ba' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        /* ── Hero gradient ── */
        .bg-gradient-hero {
            background: linear-gradient(135deg, hsl(201 85% 35%) 0%, hsl(213 77% 45%) 50%, hsl(201 85% 39%) 100%);
        }

        /* ── Glass card ── */
        .glass-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: 0 8px 32px rgba(15,115,186,0.10);
        }

        /* ── Slot cards ── */
        .slot-card {
            background: white;
            border: 2px solid hsl(214 32% 91%);
            border-radius: 1rem;
            transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s;
            cursor: default;
        }
        .slot-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(15,115,186,0.12); }

        .slot-available {
            border-color: hsl(144 100% 36%);
            background: linear-gradient(135deg, hsl(144 60% 97%), hsl(144 80% 93%));
        }
        .slot-available:hover { border-color: hsl(144 100% 30%); }

        .slot-occupied {
            border-color: hsl(32 100% 68%);
            background: linear-gradient(135deg, hsl(32 80% 97%), hsl(32 100% 92%));
        }

        /* ── Stat pill ── */
        .stat-pill {
            border-radius: 1.25rem;
            padding: 1.5rem 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            transition: transform 0.3s;
        }
        .stat-pill:hover { transform: translateY(-4px); }

        /* ── Fade-in stagger ── */
        .fade-up {
            opacity: 0;
            transform: translateY(24px);
            animation: fadeUp 0.6s ease forwards;
        }
        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }

        /* ── Pulse dot ── */
        .pulse-dot {
            animation: pulseDot 2s ease-in-out infinite;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.6; transform: scale(1.4); }
        }

        /* ── Wave separator ── */
        .wave-sep svg { display: block; }

        /* ── Badge ── */
        .badge-avail {
            background: hsl(144 100% 36%);
            color: white;
            border-radius: 999px;
            padding: 0.3rem 0.9rem;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .badge-booked {
            background: hsl(32 100% 50%);
            color: white;
            border-radius: 999px;
            padding: 0.3rem 0.9rem;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* ── Toast ── */
        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: hsl(222 47% 17%);
            color: white;
            padding: 0.9rem 1.4rem;
            border-radius: 1rem;
            font-size: 0.9rem;
            font-family: 'DM Sans', sans-serif;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.35s, transform 0.35s;
            z-index: 9999;
            max-width: 320px;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            pointer-events: none;
        }
        #toast.show { opacity: 1; transform: translateY(0); }

        /* ── Responsive grid ── */
        @media (max-width: 639px) {
            .slots-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body class="bg-medical-pattern">

<!-- ════════════════════════════════════════
     NAVBAR
════════════════════════════════════════ -->
<nav class="fixed top-0 left-0 w-full z-50 bg-white/95 backdrop-blur-md shadow-sm" style="height:68px;">
    <div class="max-w-6xl mx-auto px-4 h-full flex items-center justify-between">
        <!-- Brand -->
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-gradient-hero rounded-lg flex items-center justify-center shadow-md">
                <i data-lucide="stethoscope" class="w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="font-heading font-bold text-base leading-tight text-foreground">ICCBI Clinic</p>
                <p class="text-[11px] text-gray-400 leading-tight">Immaculate Conception College</p>
            </div>
        </div>

        <!-- Nav Links -->
        <div class="hidden md:flex items-center gap-7">
            <a href="#schedule" class="text-sm font-medium text-gray-500 hover:text-primary transition-colors">Schedule</a>
            <a href="#stats" class="text-sm font-medium text-gray-500 hover:text-primary transition-colors">Statistics</a>
        </div>

        <!-- CTA -->
        <a href="/appointments/web-new-appointment.php"
           class="inline-flex items-center gap-2 bg-gradient-hero text-white text-sm font-semibold px-5 py-2.5 rounded-full shadow-md hover:shadow-lg transition-all hover:scale-105">
            <i data-lucide="calendar-plus" class="w-4 h-4"></i>
            Book Appointment
        </a>
    </div>
</nav>

<!-- ════════════════════════════════════════
     HERO HEADER
════════════════════════════════════════ -->
<header class="bg-gradient-hero pt-[68px] relative overflow-hidden">
    <!-- Floating decoration circles -->
    <div class="absolute top-8 left-8 w-40 h-40 rounded-full bg-white/5" style="animation: float1 7s ease-in-out infinite;"></div>
    <div class="absolute bottom-4 right-12 w-24 h-24 rounded-full bg-white/8" style="animation: float1 5s ease-in-out infinite reverse;"></div>

    <!-- Floating icons -->
    <div class="absolute top-10 right-1/4 text-white/15" style="animation: floatIcon 6s ease-in-out infinite;">
        <i data-lucide="heart" class="w-10 h-10"></i>
    </div>
    <div class="absolute bottom-12 left-1/4 text-white/15" style="animation: floatIcon 8s ease-in-out infinite 1s;">
        <i data-lucide="activity" class="w-8 h-8"></i>
    </div>

    <style>
        @keyframes float1 { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-18px)} }
        @keyframes floatIcon { 0%,100%{transform:translateY(0) rotate(0)} 50%{transform:translateY(-12px) rotate(8deg)} }
    </style>

    <div class="max-w-6xl mx-auto px-4 py-14 text-center relative z-10">
        <!-- Live badge -->
        <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white text-xs font-semibold px-4 py-1.5 rounded-full mb-6 fade-up">
            <span class="w-2 h-2 rounded-full bg-green-400 pulse-dot"></span>
            Live Schedule — Updates in Real Time
        </div>

        <h1 class="font-heading text-4xl md:text-5xl font-bold text-white mb-3 fade-up delay-1" style="text-shadow:0 2px 12px rgba(0,0,0,0.2);">
            Today's Appointment Schedule
        </h1>
        <p class="text-white/80 text-lg mb-6 fade-up delay-2">
            <?php echo date('l, F j, Y'); ?>
        </p>

        <!-- Quick stats row -->
        <div id="stats" class="inline-flex flex-wrap justify-center gap-4 fade-up delay-3">
            <div class="stat-pill bg-white/15 backdrop-blur-sm text-white">
                <span class="font-heading font-bold text-3xl"><?= $total_slots ?></span>
                <span class="text-xs font-medium opacity-80 uppercase tracking-wider">Total Slots</span>
            </div>
            <div class="stat-pill bg-white/15 backdrop-blur-sm text-white">
                <span class="font-heading font-bold text-3xl text-yellow-300"><?= $occupied_slots ?></span>
                <span class="text-xs font-medium opacity-80 uppercase tracking-wider">Booked</span>
            </div>
            <div class="stat-pill bg-white/15 backdrop-blur-sm text-white">
                <span class="font-heading font-bold text-3xl text-green-300"><?= $available_slots ?></span>
                <span class="text-xs font-medium opacity-80 uppercase tracking-wider">Available</span>
            </div>
        </div>
    </div>

    <!-- Wave -->
    <div class="wave-sep -mb-1">
        <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="w-full h-14 fill-[hsl(210_40%_98%)]">
            <path d="M0,40 C360,80 1080,0 1440,40 L1440,60 L0,60 Z"/>
        </svg>
    </div>
</header>

<!-- ════════════════════════════════════════
     MAIN CONTENT
════════════════════════════════════════ -->
<main class="max-w-6xl mx-auto px-4 py-10">

    <!-- Progress bar -->
    <div class="glass-card rounded-2xl p-5 mb-8 fade-up delay-2">
        <div class="flex items-center justify-between mb-3">
            <span class="font-heading font-semibold text-sm text-gray-600">Slot Occupancy</span>
            <span class="text-sm font-semibold text-primary">
                <?= $total_slots > 0 ? round(($occupied_slots / $total_slots) * 100) : 0 ?>% Booked
            </span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
            <?php $pct = $total_slots > 0 ? round(($occupied_slots / $total_slots) * 100) : 0; ?>
            <div class="h-3 rounded-full transition-all duration-1000"
                 style="width:<?= $pct ?>%; background: linear-gradient(90deg, hsl(201 85% 39%), hsl(213 77% 54%));"
                 id="progress-bar"></div>
        </div>
        <div class="flex justify-between text-xs text-gray-400 mt-2">
            <span>0%</span>
            <span>50%</span>
            <span>100%</span>
        </div>
    </div>

    <!-- ── Schedule grid ── -->
    <section id="schedule">
        <div class="flex items-center gap-3 mb-6 fade-up delay-3">
            <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                <i data-lucide="calendar-days" class="w-5 h-5"></i>
            </div>
            <div>
                <h2 class="font-heading font-bold text-xl text-foreground">Time Slots</h2>
                <p class="text-sm text-gray-400">7:00 AM – 4:00 PM · Hourly appointments</p>
            </div>
        </div>

        <?php if (!empty($time_slots)): ?>
        <div class="slots-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 fade-up delay-4">
            <?php foreach ($time_slots as $i => $slot):
                $isOccupied = $slot['occupied'];
                $timeDisplay = date('g:i A', strtotime($slot['time']));
                $hour24 = (int)date('H', strtotime($slot['time']));
                $isPast = ($hour24 < (int)date('H'));
            ?>
            <div class="slot-card <?= $isOccupied ? 'slot-occupied' : 'slot-available' ?> p-5 flex items-center justify-between gap-4"
                 style="animation-delay: <?= 0.05 * $i ?>s;"
                 <?php if (!$isOccupied): ?>
                 onclick="onSlotClick('<?= $timeDisplay ?>')"
                 style="cursor:pointer; animation-delay: <?= 0.05 * $i ?>s;"
                 <?php endif; ?>>

                <!-- Left: time + hour period -->
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center <?= $isOccupied ? 'bg-orange-100 text-orange-500' : 'bg-green-100 text-green-600' ?>">
                        <i data-lucide="<?= $isOccupied ? 'user-check' : 'clock' ?>" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="font-heading font-bold text-base leading-tight text-foreground"><?= $timeDisplay ?></p>
                        <p class="text-xs text-gray-400 mt-0.5"><?= ($hour24 < 12) ? 'Morning' : (($hour24 < 17) ? 'Afternoon' : 'Evening') ?> slot</p>
                    </div>
                </div>

                <!-- Right: badge -->
                <?php if ($isOccupied): ?>
                <span class="badge-booked">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Booked
                </span>
                <?php else: ?>
                <span class="badge-avail">
                    <i data-lucide="circle" class="w-3 h-3"></i> Open
                </span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="glass-card rounded-2xl p-16 text-center fade-up delay-4">
            <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i data-lucide="calendar-x" class="w-8 h-8 text-gray-300"></i>
            </div>
            <h3 class="font-heading font-bold text-lg text-gray-500 mb-2">No Slots Available</h3>
            <p class="text-gray-400 text-sm">No appointment slots have been configured for today.</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- ── Legend ── -->
    <div class="mt-8 flex flex-wrap gap-4 justify-center fade-up delay-5">
        <div class="flex items-center gap-2 glass-card rounded-full px-4 py-2 text-sm font-medium text-gray-600">
            <span class="w-3 h-3 rounded-full bg-green-500"></span>
            Available — click to select
        </div>
        <div class="flex items-center gap-2 glass-card rounded-full px-4 py-2 text-sm font-medium text-gray-600">
            <span class="w-3 h-3 rounded-full bg-orange-400"></span>
            Booked — unavailable
        </div>
    </div>

    <!-- ── CTA Card ── -->
    <div class="mt-10 bg-gradient-hero rounded-3xl p-8 text-center relative overflow-hidden fade-up delay-5">
        <div class="absolute inset-0 bg-medical-pattern opacity-10"></div>
        <div class="relative z-10">
            <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i data-lucide="calendar-check" class="w-7 h-7 text-white"></i>
            </div>
            <h3 class="font-heading font-bold text-2xl text-white mb-2">Ready to Book?</h3>
            <p class="text-white/80 text-sm mb-6 max-w-sm mx-auto">
                <?= $available_slots ?> slot<?= $available_slots !== 1 ? 's' : '' ?> still open today.
                Secure yours before they fill up.
            </p>
            <a href="/appointments/web-new-appointment.php"
               class="inline-flex items-center gap-2 bg-white text-primary font-heading font-bold px-8 py-3 rounded-full shadow-lg hover:shadow-xl transition-all hover:scale-105 text-sm">
                <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                Book an Appointment Now
            </a>
        </div>
    </div>
</main>

<!-- ════════════════════════════════════════
     FOOTER
════════════════════════════════════════ -->
<footer class="mt-16 bg-foreground text-white">
    <div class="max-w-6xl mx-auto px-4 py-10 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-white/10 rounded-lg flex items-center justify-center">
                <i data-lucide="heart-pulse" class="w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="font-heading font-bold text-sm">ICCBI Clinic · SSCMS</p>
                <p class="text-white/50 text-xs">Smart School Clinic Management System</p>
            </div>
        </div>
        <p class="text-white/40 text-xs text-center">
            © 2025 Immaculate Conception College of Balayan, Inc. · Health Services<br>
            <span class="text-white/25">Your health, our priority</span>
        </p>
        <a href="/appointments/web-new-appointment.php"
           class="text-xs font-semibold text-primary underline underline-offset-2 hover:text-secondary transition-colors">
            Book Appointment →
        </a>
    </div>
</footer>

<!-- ── Toast ── -->
<div id="toast">
    <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
    <span id="toast-msg"></span>
</div>

<!-- Scripts -->
<script>
    lucide.createIcons();

    /* ── Toast helper ── */
    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toast-msg').textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 4000);
    }

    /* ── Slot click ── */
    function onSlotClick(time) {
        showToast(`${time} selected — click "Book Appointment" to proceed.`);
    }

    /* ── Animate progress bar on load ── */
    window.addEventListener('load', () => {
        const bar = document.getElementById('progress-bar');
        if (bar) {
            const target = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => { bar.style.width = target; }, 300);
        }

        /* Welcome toast */
        setTimeout(() => {
            showToast('<?= $available_slots ?> slot<?= $available_slots !== 1 ? 's' : '' ?> available today!');
        }, 900);

        /* Re-init icons after PHP-rendered content */
        lucide.createIcons();
    });
</script>
</body>
</html>