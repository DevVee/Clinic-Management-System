<?php
session_start();

// Try DB connection — if it fails, still show the landing page
$conn = null;
try {
    $host     = getenv('DB_HOST')     ?: 'localhost';
    $dbname   = getenv('DB_NAME')     ?: 'postgres';
    $username = getenv('DB_USER')     ?: 'postgres';
    $password = getenv('DB_PASSWORD') ?: '';
    $port     = getenv('DB_PORT')     ?: '5432';
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    error_log("index.php DB error: " . $e->getMessage());
}

// Only redirect logged-in users if DB is available
if ($conn && isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT last_active FROM sessions WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$_SESSION['user_id'], session_id()]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($session && (time() - strtotime($session['last_active']) < 3600)) {
            header('Location: /dashboard');
            exit;
        }
    } catch (Exception $e) {
        // Session check failed — just show the landing page
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSCMS - Smart School Clinic Management System | ICCBI Healthcare</title>
    <meta name="description"
        content="Smart School Clinic Management System for Immaculate Conception College of Balayan, Inc. - Modern healthcare management powered by AI">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'hsl(201 85% 39%)',
                        secondary: 'hsl(213 77% 54%)',
                        accent: 'hsl(144 100% 39%)',
                        warning: 'hsl(32 100% 50%)',
                        'trust-green': 'hsl(144 100% 39%)',
                        background: 'hsl(210 40% 98%)',
                        foreground: 'hsl(222 47% 17%)',
                        card: 'hsl(0 0% 100%)',
                        muted: 'hsl(210 40% 96%)',
                        border: 'hsl(214 32% 91%)',
                        destructive: 'hsl(0 84% 60%)',
                    },
                    fontFamily: {
                        'heading': ['Poppins', 'sans-serif'],
                        'body': ['Inter', 'sans-serif'],
                    },
                    borderRadius: {
                        'lg': '0.75rem',
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --primary: 201 85% 39%;
            --primary-foreground: 0 0% 100%;
            --secondary: 213 77% 54%;
            --secondary-foreground: 0 0% 100%;
            --background: 210 40% 98%;
            --foreground: 222 47% 17%;
            --card: 0 0% 100%;
            --card-foreground: 222 47% 17%;
            --popover: 0 0% 100%;
            --popover-foreground: 222 47% 17%;
            --muted: 210 40% 96%;
            --muted-foreground: 215 16% 47%;
            --accent: 144 100% 39%;
            --accent-foreground: 0 0% 100%;
            --warning: 32 100% 50%;
            --warning-foreground: 0 0% 100%;
            --destructive: 0 84% 60%;
            --destructive-foreground: 0 0% 100%;
            --border: 214 32% 91%;
            --input: 214 32% 91%;
            --ring: 201 85% 39%;
            --radius: 0.75rem;
            --medical-blue: 201 85% 39%;
            --medical-light: 201 85% 95%;
            --trust-green: 144 100% 39%;
            --soft-gray: 210 40% 98%;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: hsl(var(--background));
            color: hsl(var(--foreground));
            overflow-x: hidden;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Poppins', sans-serif;
        }

        .gradient-text {
            background: linear-gradient(135deg, hsl(201 85% 39%) 0%, hsl(213 77% 54%) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .feature-card {
            background: hsl(var(--card));
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            border: 1px solid hsl(var(--border) / 0.5);
        }

        .feature-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            transform: translateY(-4px);
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-hero-primary {
            background: white;
            color: hsl(var(--primary));
            font-weight: 600;
            padding: 1rem 2rem;
            border-radius: 9999px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .btn-hero-primary:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            transform: scale(1.05);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            color: white;
            font-weight: 600;
            padding: 1rem 2rem;
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s;
        }

        .btn-hero-secondary:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            transform: scale(1.05);
        }

        .section-padding {
            padding: 5rem 1rem;
        }

        @media (min-width: 768px) {
            .section-padding {
                padding: 7rem 2rem;
            }
        }

        .container-custom {
            max-width: 1280px;
            margin: 0 auto;
        }

        .text-shadow {
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .bg-medical-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%230f73ba' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        /* ===================================================
           HERO BACKGROUND IMAGE STYLES
           Replace the src in the <img id="hero-bg"> tag below
           with your school's image path, e.g. ./assets/img/school.jpg
        =================================================== */
        #hero-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            z-index: 0;
        }

        /* Dark overlay on top of the background image so text stays readable */
        #hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(8, 80, 120, 0.82) 0%, rgba(22, 80, 160, 0.78) 50%, rgba(8, 80, 120, 0.82) 100%);
            z-index: 1;
        }

        /* ===================================================
           END HERO BACKGROUND IMAGE STYLES
        =================================================== */

        .bg-gradient-hero {
            background: linear-gradient(135deg, hsl(201 85% 35%) 0%, hsl(213 77% 45%) 50%, hsl(201 85% 39%) 100%);
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, hsl(201 85% 39%) 0%, hsl(213 77% 54%) 100%);
        }

        .shadow-medical {
            box-shadow: 0 4px 20px -2px hsl(201 85% 39% / 0.15);
        }

        .shadow-medical-lg {
            box-shadow: 0 10px 40px -4px hsl(201 85% 39% / 0.2);
        }

        .shadow-glow {
            box-shadow: 0 0 40px hsl(201 85% 39% / 0.3);
        }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .animate-in {
            opacity: 1;
            transform: translateY(0);
        }

        .float-animation {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        .pulse-animation {
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .rotate-animation {
            animation: rotate 50s linear infinite;
        }

        @keyframes rotate {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .rotate-animation-slow {
            animation: rotate 60s linear infinite;
        }

        nav {
            transition: all 0.3s ease;
            transform: translateY(-100%);
            height: 80px;
            padding: 0 0.75rem;
        }

        nav.loaded {
            transform: translateY(0);
        }

        nav.scrolled {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
            opacity: 0;
        }

        .mobile-menu.open {
            max-height: 500px;
            opacity: 1;
        }

        .lightbox {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
        }

        .lightbox.open {
            opacity: 1;
            visibility: visible;
        }

        .lightbox-content {
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .lightbox.open .lightbox-content {
            transform: scale(1);
        }

        .testimonial-item {
            opacity: 0;
            transition: opacity 0.5s ease;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
        }

        .testimonial-item.active {
            opacity: 1;
            position: relative;
        }

        .carousel-dot {
            transition: all 0.3s ease;
        }

        .carousel-dot.active {
            width: 2rem;
            background-color: hsl(var(--primary));
        }

        .scroll-to-top {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }

        .scroll-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 w-full z-50 py-4 transition-all duration-300 bg-white/95 backdrop-blur-md shadow-md">
        <div class="container-custom px-4 md:px-6">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 flex items-center justify-center">
                        <img src="./assets/img/SSCMS.png" class="w-full h-full object-contain" alt="SSCMS Logo">
                    </div>
                    <div>
                        <h1 class="font-heading font-bold text-xl text-foreground">SSCMS</h1>
                        <p class="text-xs text-muted-foreground">ICCBI Healthcare</p>
                    </div>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#home" class="text-foreground hover:text-primary transition-colors font-medium">Home</a>
                    <a href="#features"
                        class="text-foreground hover:text-primary transition-colors font-medium">Features</a>
                    <a href="#about" class="text-foreground hover:text-primary transition-colors font-medium">About</a>
                    <a href="#booking"
                        class="text-foreground hover:text-primary transition-colors font-medium">Booking</a>
                    <a href="#gallery"
                        class="text-foreground hover:text-primary transition-colors font-medium">Gallery</a>
                    <a href="#nurse-angge"
                        class="text-foreground hover:text-primary transition-colors font-medium">Nurse Angge</a>
                </div>

                <!-- Login Button -->
                <a href="/login"
                    class="bg-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-primary/90 transition-colors shadow-medical">
                    Login
                </a>

                <!-- Mobile Menu Button -->
                <button class="md:hidden mobile-menu-btn">
                    <i data-lucide="menu" class="w-6 h-6 text-foreground"></i>
                </button>
            </div>

            <!-- Mobile Menu -->
            <div class="mobile-menu md:hidden mt-4 bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="flex flex-col space-y-4 p-4">
                    <a href="#home"
                        class="text-foreground hover:text-primary transition-colors font-medium py-2">Home</a>
                    <a href="#features"
                        class="text-foreground hover:text-primary transition-colors font-medium py-2">Features</a>
                    <a href="#about"
                        class="text-foreground hover:text-primary transition-colors font-medium py-2">About</a>
                    <a href="#booking"
                        class="text-foreground hover:text-primary transition-colors font-medium py-2">Booking</a>
                    <a href="#gallery"
                        class="text-foreground hover:text-primary transition-colors font-medium py-2">Gallery</a>
                    <a href="#nurse-angge"
                        class="text-foreground hover:text-primary transition-colors font-medium py-2">Nurse Angge</a>
                    <a href="/login"
                        class="bg-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-primary/90 transition-colors text-center">
                        Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ============================================================
         HERO SECTION
         HOW TO ADD YOUR SCHOOL BACKGROUND IMAGE:
         1. Place your school photo in ./assets/img/ folder
         2. Change the src below from "./assets/img/school-bg.jpg"
            to your actual filename, e.g. "./assets/img/iccbi-campus.jpg"
         3. Adjust the overlay opacity in #hero-overlay (CSS above)
            if the text is hard to read — higher value = darker overlay
    ============================================================ -->
    <section id="home" class="min-h-screen relative overflow-hidden flex items-center justify-center pt-16">

        <!-- BACKGROUND IMAGE — replace src with your school photo -->
        <img id="hero-bg" src="/assets/img/icc-background.jpg" alt="ICCBI School Campus"
            onerror="this.style.display='none'; document.getElementById('hero-fallback').style.display='block';">

        <!-- Fallback gradient shown only if image fails to load -->
        <div id="hero-fallback" class="absolute inset-0 bg-gradient-hero" style="display:none; z-index:0;"></div>

        <!-- Dark color overlay on top of background image for text readability -->
        <div id="hero-overlay"></div>

        <!-- Background Pattern (subtle, on top of overlay) -->
        <div class="absolute inset-0 bg-medical-pattern opacity-20" style="z-index: 2;"></div>

        <!-- Animated Circles -->
        <div class="absolute top-1/4 left-1/4 w-64 h-64 rounded-full bg-white/5 pulse-animation" style="z-index: 2;">
        </div>
        <div class="absolute bottom-1/4 right-1/4 w-48 h-48 rounded-full bg-white/10 pulse-animation"
            style="animation-delay: 1s; z-index: 2;"></div>

        <!-- Floating Icons -->
        <div class="absolute top-20 left-10 text-white/20 float-animation" style="z-index: 2;">
            <i data-lucide="heart" class="w-12 h-12"></i>
        </div>
        <div class="absolute top-40 right-20 text-white/20 float-animation" style="animation-delay: 0.5s; z-index: 2;">
            <i data-lucide="stethoscope" class="w-10 h-10"></i>
        </div>
        <div class="absolute bottom-40 left-20 text-white/20 float-animation" style="animation-delay: 1s; z-index: 2;">
            <i data-lucide="pill" class="w-8 h-8"></i>
        </div>
        <div class="absolute top-1/3 right-1/4 text-white/20 float-animation"
            style="animation-delay: 1.5s; z-index: 2;">
            <i data-lucide="activity" class="w-14 h-14"></i>
        </div>
        <div class="absolute bottom-1/3 left-1/3 text-white/20 float-animation"
            style="animation-delay: 2s; z-index: 2;">
            <i data-lucide="shield" class="w-9 h-9"></i>
        </div>
        <div class="absolute top-1/2 right-10 text-white/20 float-animation" style="animation-delay: 2.5s; z-index: 2;">
            <i data-lucide="users" class="w-11 h-11"></i>
        </div>

        <!-- Content -->
        <div class="container-custom px-4 md:px-6 pt-8 md:pt-10 relative" style="z-index: 3;">
            <div class="max-w-4xl mx-auto text-center text-white">
                <!-- Badge -->
                <div
                    class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-sm rounded-full px-4 py-2 mb-8 animate-on-scroll">
                    <div class="w-2 h-2 rounded-full bg-green-400 pulse-animation"></div>
                    <span class="text-sm font-medium">Trusted by ICCBI Community</span>
                </div>

                <!-- Main Headline -->
                <h1 class="text-4xl md:text-6xl font-bold mb-6 text-shadow animate-on-scroll"
                    style="transition-delay: 0.1s;">
                    Smart School Clinic Management System
                </h1>

                <!-- Subheading -->
                <p class="text-xl md:text-2xl mb-8 text-white/90 max-w-3xl mx-auto animate-on-scroll"
                    style="transition-delay: 0.2s;">
                    Revolutionizing healthcare management at Immaculate Conception College of Balayan, Inc. with
                    cutting-edge technology and AI-powered solutions.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-6 mb-12 animate-on-scroll"
                    style="transition-delay: 0.3s;">
                    <a href="/appointments" class="btn-hero-secondary">
                        Book Appointments
                    </a>
                </div>

                <!-- Trust Indicators -->
                <div class="flex flex-wrap justify-center items-center gap-6 text-white/80 animate-on-scroll"
                    style="transition-delay: 0.4s;">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="shield-check" class="w-5 h-5 text-green-400"></i>
                        <span>Secured Information</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i data-lucide="activity" class="w-5 h-5 text-blue-400"></i>
                        <span>Real-time Monitoring</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i data-lucide="users" class="w-5 h-5 text-yellow-400"></i>
                        <span>1000+ Students Served</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wave Separator -->
        <div class="absolute bottom-0 left-0 w-full" style="z-index: 3;">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none" class="w-full h-16 fill-white"></svg>
        </div>
    </section>
    <!-- ============================================================ END HERO SECTION -->

    <!-- Features Section -->
    <section id="features" class="section-padding bg-white bg-medical-pattern">
        <div class="container-custom">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div
                    class="inline-flex items-center bg-primary/10 text-primary rounded-full px-4 py-1 text-sm font-medium mb-4 animate-on-scroll">
                    Powerful Features
                </div>
                <h2 class="text-3xl md:text-4xl font-bold mb-4 animate-on-scroll" style="transition-delay: 0.1s;">
                    Everything You Need for <span class="gradient-text">Modern Healthcare</span>
                </h2>
                <p class="text-muted-foreground text-lg animate-on-scroll" style="transition-delay: 0.2s;">
                    Our comprehensive platform provides all the tools needed to manage school clinic operations
                    efficiently and effectively.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div class="feature-card animate-on-scroll">
                    <div class="bg-primary/10 text-primary w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Patient Management</h3>
                    <p class="text-muted-foreground">Comprehensive student health records and medical history
                        management.</p>
                </div>

                <div class="feature-card animate-on-scroll" style="transition-delay: 0.1s;">
                    <div class="bg-accent/10 text-accent w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                        <i data-lucide="bot" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">AI Assistant (Nurse Angge)</h3>
                    <p class="text-muted-foreground">24/7 AI-powered health consultation and symptom assessment.</p>
                </div>

                <div class="feature-card animate-on-scroll" style="transition-delay: 0.2s;">
                    <div
                        class="bg-secondary/10 text-secondary w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                        <i data-lucide="calendar" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Appointment Scheduling</h3>
                    <p class="text-muted-foreground">Easy online booking and management of clinic appointments.</p>
                </div>

                <div class="feature-card animate-on-scroll" style="transition-delay: 0.3s;">
                    <div class="bg-warning/10 text-warning w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                        <i data-lucide="pill" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Medicine Inventory</h3>
                    <p class="text-muted-foreground">Track and manage medicine stock with automatic low-stock alerts.
                    </p>
                </div>

                <div class="feature-card animate-on-scroll" style="transition-delay: 0.4s;">
                    <div class="bg-primary/10 text-primary w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                        <i data-lucide="bar-chart-3" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Analytics & Reports</h3>
                    <p class="text-muted-foreground">Comprehensive health analytics and reporting for data-driven
                        decisions.</p>
                </div>

                <div class="feature-card animate-on-scroll" style="transition-delay: 0.5s;">
                    <div class="bg-accent/10 text-accent w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                        <i data-lucide="message-square" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">SMS Notifications</h3>
                    <p class="text-muted-foreground">Automated SMS alerts for appointments, reminders, and health
                        updates.</p>
                </div>

                <div class="feature-card animate-on-scroll" style="transition-delay: 0.6s;">
                    <div
                        class="bg-secondary/10 text-secondary w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                        <i data-lucide="user-check" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Specialist Booking</h3>
                    <p class="text-muted-foreground">Book appointments with specialists and external healthcare
                        providers.</p>
                </div>

                <div class="feature-card animate-on-scroll" style="transition-delay: 0.7s;">
                    <div
                        class="bg-trust-green/10 text-trust-green w-12 h-12 rounded-lg flex items-center justify-center mb-4">
                        <i data-lucide="database" class="w-6 h-6"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Secure Data Storage</h3>
                    <p class="text-muted-foreground">HIPAA-compliant secure storage for all sensitive health
                        information.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section-padding bg-gradient-to-b from-background to-muted/50 relative overflow-hidden">
        <div
            class="absolute top-0 left-0 w-96 h-96 bg-primary/5 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2">
        </div>
        <div
            class="absolute bottom-0 right-0 w-96 h-96 bg-secondary/5 rounded-full blur-3xl translate-x-1/2 translate-y-1/2">
        </div>

        <div class="container-custom relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div
                    class="inline-flex items-center bg-primary/10 text-primary rounded-full px-4 py-1 text-sm font-medium mb-4 animate-on-scroll">
                    How It Works
                </div>
                <h2 class="text-3xl md:text-4xl font-bold mb-4 animate-on-scroll" style="transition-delay: 0.1s;">
                    Streamlined Healthcare <span class="gradient-text">Management System</span>
                </h2>
                <p class="text-muted-foreground text-lg animate-on-scroll" style="transition-delay: 0.2s;">
                    Our system simplifies healthcare management through an integrated platform that connects students,
                    healthcare providers, and administrators.
                </p>
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl p-6 shadow-lg animate-on-scroll">
                            <div class="flex items-center space-x-4 mb-4">
                                <div
                                    class="bg-primary/10 text-primary w-12 h-12 rounded-lg flex items-center justify-center">
                                    <i data-lucide="user-check" class="w-6 h-6"></i>
                                </div>
                                <h3 class="font-bold text-lg">Student Portal</h3>
                            </div>
                            <p class="text-muted-foreground">
                                Students can easily book appointments, access their health records, and consult with
                                Nurse Angge AI for initial health assessments.
                            </p>
                        </div>

                        <div class="bg-white rounded-xl p-6 shadow-lg animate-on-scroll"
                            style="transition-delay: 0.1s;">
                            <div class="flex items-center space-x-4 mb-4">
                                <div
                                    class="bg-secondary/10 text-secondary w-12 h-12 rounded-lg flex items-center justify-center">
                                    <i data-lucide="stethoscope" class="w-6 h-6"></i>
                                </div>
                                <h3 class="font-bold text-lg">Healthcare Provider Dashboard</h3>
                            </div>
                            <p class="text-muted-foreground">
                                Medical staff have access to patient records, appointment schedules, and inventory
                                management tools in one centralized dashboard.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-white rounded-xl p-6 shadow-lg animate-on-scroll"
                            style="transition-delay: 0.2s;">
                            <div class="flex items-center space-x-4 mb-4">
                                <div
                                    class="bg-accent/10 text-accent w-12 h-12 rounded-lg flex items-center justify-center">
                                    <i data-lucide="shield" class="w-6 h-6"></i>
                                </div>
                                <h3 class="font-bold text-lg">Administrative Control</h3>
                            </div>
                            <p class="text-muted-foreground">
                                School administrators can monitor clinic performance, generate reports, and manage user
                                access with comprehensive administrative tools.
                            </p>
                        </div>

                        <div class="bg-white rounded-xl p-6 shadow-lg animate-on-scroll"
                            style="transition-delay: 0.3s;">
                            <div class="flex items-center space-x-4 mb-4">
                                <div
                                    class="bg-warning/10 text-warning w-12 h-12 rounded-lg flex items-center justify-center">
                                    <i data-lucide="bar-chart-3" class="w-6 h-6"></i>
                                </div>
                                <h3 class="font-bold text-lg">Analytics & Reporting</h3>
                            </div>
                            <p class="text-muted-foreground">
                                Generate comprehensive reports on student health trends, clinic utilization, and
                                resource allocation for data-driven decision making.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-2xl p-8 max-w-4xl mx-auto mt-16 animate-on-scroll">
                <h3 class="text-2xl font-bold mb-4 text-center">About SSCMS</h3>
                <p class="text-muted-foreground text-lg text-center">
                    The Smart School Clinic Management System (SSCMS) is a comprehensive healthcare platform designed
                    specifically for educational institutions.
                    Developed for Immaculate Conception College of Balayan, Inc., our system streamlines clinic
                    operations, enhances patient care,
                    and provides valuable health insights through data analytics and AI-powered tools.
                </p>
            </div>
        </div>
    </section>

    <!-- Booking Section -->
    <section id="booking" class="section-padding bg-gradient-to-b from-muted/30 to-background relative overflow-hidden">
        <div
            class="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2">
        </div>
        <div
            class="absolute bottom-0 left-0 w-96 h-96 bg-secondary/5 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2">
        </div>

        <div class="container-custom relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div
                    class="inline-flex items-center bg-primary/10 text-primary rounded-full px-4 py-1 text-sm font-medium mb-4 animate-on-scroll">
                    Book Appointments
                </div>
                <h2 class="text-3xl md:text-4xl font-bold mb-4 animate-on-scroll" style="transition-delay: 0.1s;">
                    Schedule Your <span class="gradient-text">Clinic Visit</span>
                </h2>
                <p class="text-muted-foreground text-lg animate-on-scroll" style="transition-delay: 0.2s;">
                    Easily book appointments with our healthcare professionals through our convenient online system.
                </p>
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="space-y-6 animate-on-scroll">
                        <div class="bg-white rounded-2xl p-6 shadow-lg">
                            <div class="flex items-center space-x-3 mb-4">
                                <div
                                    class="bg-primary/10 text-primary w-10 h-10 rounded-lg flex items-center justify-center">
                                    <i data-lucide="clock" class="w-5 h-5"></i>
                                </div>
                                <h3 class="font-bold text-lg">Clinic Hours</h3>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Monday - Friday</span>
                                    <span class="font-medium">8:00 AM - 5:00 PM</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Saturday</span>
                                    <span class="font-medium">9:00 AM - 12:00 PM</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Sunday</span>
                                    <span class="font-medium">Closed</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl p-6 shadow-lg">
                            <div class="flex items-center space-x-3 mb-4">
                                <div
                                    class="bg-accent/10 text-accent w-10 h-10 rounded-lg flex items-center justify-center">
                                    <i data-lucide="heart" class="w-5 h-5"></i>
                                </div>
                                <h3 class="font-bold text-lg">Available Services</h3>
                            </div>
                            <ul class="space-y-2">
                                <li class="flex items-center space-x-2">
                                    <i data-lucide="check" class="w-4 h-4 text-accent"></i>
                                    <span class="text-muted-foreground">General Check-ups</span>
                                </li>
                                <li class="flex items-center space-x-2">
                                    <i data-lucide="check" class="w-4 h-4 text-accent"></i>
                                    <span class="text-muted-foreground">First Aid & Emergency Care</span>
                                </li>
                                <li class="flex items-center space-x-2">
                                    <i data-lucide="check" class="w-4 h-4 text-accent"></i>
                                    <span class="text-muted-foreground">Health Counseling</span>
                                </li>
                                <li class="flex items-center space-x-2">
                                    <i data-lucide="check" class="w-4 h-4 text-accent"></i>
                                    <span class="text-muted-foreground">Vaccinations</span>
                                </li>
                                <li class="flex items-center space-x-2">
                                    <i data-lucide="check" class="w-4 h-4 text-accent"></i>
                                    <span class="text-muted-foreground">Medication Dispensing</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-8 shadow-lg flex flex-col items-center justify-center text-center animate-on-scroll"
                        style="transition-delay: 0.1s;">
                        <div
                            class="bg-primary/10 text-primary w-16 h-16 rounded-full flex items-center justify-center mb-6">
                            <i data-lucide="calendar" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-2xl font-bold mb-4">Ready to Book an Appointment?</h3>
                        <p class="text-muted-foreground mb-6">
                            Click the button below to schedule your appointment with our healthcare professionals.
                        </p>
                        <a href="#"
                            class="bg-primary text-white font-bold py-3 px-8 rounded-lg hover:bg-primary/90 transition-colors shadow-medical inline-flex items-center space-x-2">
                            <i data-lucide="calendar" class="w-5 h-5"></i>
                            <span>Book Appointment Now</span>
                        </a>
                        <div class="mt-6 p-4 bg-muted rounded-lg">
                            <p class="text-sm text-muted-foreground">
                                Need help? Contact us at
                                <a href="mailto:support@sscms.com"
                                    class="text-primary font-medium">support@sscms.com</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section id="gallery" class="section-padding bg-muted/30">
        <div class="container-custom">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div
                    class="inline-flex items-center bg-primary/10 text-primary rounded-full px-4 py-1 text-sm font-medium mb-4 animate-on-scroll">
                    Gallery
                </div>
                <h2 class="text-3xl md:text-4xl font-bold mb-4 animate-on-scroll" style="transition-delay: 0.1s;">
                    A Glimpse of <span class="gradient-text">Our System</span>
                </h2>
                <p class="text-muted-foreground text-lg animate-on-scroll" style="transition-delay: 0.2s;">
                    Explore the key features and interfaces of our Smart School Clinic Management System.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl overflow-hidden shadow-lg group cursor-pointer animate-on-scroll"
                    onclick="openLightbox(1)">
                    <div class="relative overflow-hidden">
                        <img src="./assets/img/dashboard.png" alt="Dashboard"
                            class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300">
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-4">
                            <div class="text-white">
                                <span
                                    class="bg-primary text-white text-xs px-2 py-1 rounded-md mb-2 inline-block">Dashboard</span>
                                <h3 class="font-bold text-lg">Admin Dashboard</h3>
                                <p class="text-white/80 text-sm">Comprehensive overview of clinic operations</p>
                            </div>
                            <div
                                class="absolute top-4 right-4 bg-white/20 backdrop-blur-sm rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <i data-lucide="zoom-in" class="w-5 h-5 text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl overflow-hidden shadow-lg group cursor-pointer animate-on-scroll"
                    style="transition-delay: 0.1s;" onclick="openLightbox(2)">
                    <div class="relative overflow-hidden">
                        <img src="./assets/img/patient.png" alt="Patient Management"
                            class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300">
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-4">
                            <div class="text-white">
                                <span
                                    class="bg-secondary text-white text-xs px-2 py-1 rounded-md mb-2 inline-block">Management</span>
                                <h3 class="font-bold text-lg">Patient Records</h3>
                                <p class="text-white/80 text-sm">Comprehensive student health records</p>
                            </div>
                            <div
                                class="absolute top-4 right-4 bg-white/20 backdrop-blur-sm rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <i data-lucide="zoom-in" class="w-5 h-5 text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl overflow-hidden shadow-lg group cursor-pointer animate-on-scroll"
                    style="transition-delay: 0.2s;" onclick="openLightbox(3)">
                    <div class="relative overflow-hidden">
                        <img src="./assets/img/appointments.png" alt="Appointments"
                            class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300">
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-4">
                            <div class="text-white">
                                <span
                                    class="bg-accent text-white text-xs px-2 py-1 rounded-md mb-2 inline-block">Scheduling</span>
                                <h3 class="font-bold text-lg">Scheduling System</h3>
                                <p class="text-white/80 text-sm">Easy appointment booking and management</p>
                            </div>
                            <div
                                class="absolute top-4 right-4 bg-white/20 backdrop-blur-sm rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <i data-lucide="zoom-in" class="w-5 h-5 text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightbox Modal -->
        <div class="lightbox fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4">
            <div class="lightbox-content bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                <div class="relative">
                    <button
                        class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm rounded-full p-2 z-10 hover:bg-white transition-colors"
                        onclick="closeLightbox()">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                    <img id="lightbox-image" src="" alt="" class="w-full h-64 md:h-96 object-cover">
                    <div class="p-6">
                        <span id="lightbox-category"
                            class="bg-primary text-white text-xs px-2 py-1 rounded-md mb-2 inline-block"></span>
                        <h3 id="lightbox-title" class="font-bold text-2xl mb-2"></h3>
                        <p id="lightbox-description" class="text-muted-foreground"></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tech Stack Section -->
    <section class="section-padding bg-white bg-medical-pattern">
        <div class="container-custom">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div
                    class="inline-flex items-center bg-primary/10 text-primary rounded-full px-4 py-1 text-sm font-medium mb-4 animate-on-scroll">
                    Technology Stack
                </div>
                <h2 class="text-3xl md:text-4xl font-bold mb-4 animate-on-scroll" style="transition-delay: 0.1s;">
                    Built with <span class="gradient-text">Modern Technologies</span>
                </h2>
                <p class="text-muted-foreground text-lg animate-on-scroll" style="transition-delay: 0.2s;">
                    Our platform leverages cutting-edge technologies to deliver a secure, scalable, and user-friendly
                    experience.
                </p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                <div
                    class="bg-white rounded-xl p-6 text-center border border-indigo-100 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 animate-on-scroll">
                    <div
                        class="bg-indigo-100 text-indigo-600 w-16 h-16 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="code-2" class="w-8 h-8"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">PHP</h3>
                    <p class="text-muted-foreground text-sm">Server-side scripting</p>
                </div>

                <div class="bg-white rounded-xl p-6 text-center border border-blue-100 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 animate-on-scroll"
                    style="transition-delay: 0.1s;">
                    <div
                        class="bg-blue-100 text-blue-600 w-16 h-16 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="database" class="w-8 h-8"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">MySQL</h3>
                    <p class="text-muted-foreground text-sm">Database management</p>
                </div>

                <div class="bg-white rounded-xl p-6 text-center border border-purple-100 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 animate-on-scroll"
                    style="transition-delay: 0.2s;">
                    <div
                        class="bg-purple-100 text-purple-600 w-16 h-16 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="zap" class="w-8 h-8"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Bootstrap</h3>
                    <p class="text-muted-foreground text-sm">Frontend framework</p>
                </div>

                <div class="bg-white rounded-xl p-6 text-center border border-pink-100 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 animate-on-scroll"
                    style="transition-delay: 0.3s;">
                    <div
                        class="bg-pink-100 text-pink-600 w-16 h-16 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="bar-chart-3" class="w-8 h-8"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Chart.js</h3>
                    <p class="text-muted-foreground text-sm">Data visualization</p>
                </div>

                <div class="bg-white rounded-xl p-6 text-center border border-green-100 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 animate-on-scroll"
                    style="transition-delay: 0.4s;">
                    <div
                        class="bg-green-100 text-green-600 w-16 h-16 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="bot" class="w-8 h-8"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">AI Integration</h3>
                    <p class="text-muted-foreground text-sm">Machine learning</p>
                </div>

                <div class="bg-white rounded-xl p-6 text-center border border-amber-100 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 animate-on-scroll"
                    style="transition-delay: 0.5s;">
                    <div
                        class="bg-amber-100 text-amber-600 w-16 h-16 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="shield" class="w-8 h-8"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Security</h3>
                    <p class="text-muted-foreground text-sm">Data protection</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section-padding bg-gradient-to-b from-muted/30 to-background relative overflow-hidden">
        <div
            class="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2">
        </div>
        <div
            class="absolute bottom-0 left-0 w-96 h-96 bg-secondary/5 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2">
        </div>

        <div class="container-custom relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div
                    class="inline-flex items-center bg-primary/10 text-primary rounded-full px-4 py-1 text-sm font-medium mb-4 animate-on-scroll">
                    Testimonials
                </div>
                <h2 class="text-3xl md:text-4xl font-bold mb-4 animate-on-scroll" style="transition-delay: 0.1s;">
                    What Our Community <span class="gradient-text">Says About Us</span>
                </h2>
                <p class="text-muted-foreground text-lg animate-on-scroll" style="transition-delay: 0.2s;">
                    Hear from students, faculty, and staff who have experienced our healthcare services.
                </p>
            </div>

            <div class="max-w-3xl mx-auto">
                <div class="glass-card rounded-2xl p-8 relative">
                    <div
                        class="absolute -top-4 left-8 bg-primary text-white w-12 h-12 rounded-full flex items-center justify-center shadow-medical">
                        <i data-lucide="quote" class="w-6 h-6"></i>
                    </div>

                    <div class="relative">
                        <div class="testimonial-item active">
                            <div class="flex justify-center mb-6">
                                <div class="flex space-x-1">
                                    <i data-lucide="star" class="w-5 h-5 fill-yellow-400 text-yellow-400"></i>
                                    <i data-lucide="star" class="w-5 h-5 fill-yellow-400 text-yellow-400"></i>
                                    <i data-lucide="star" class="w-5 h-5 fill-yellow-400 text-yellow-400"></i>
                                    <i data-lucide="star" class="w-5 h-5 fill-yellow-400 text-yellow-400"></i>
                                    <i data-lucide="star" class="w-5 h-5 fill-yellow-400 text-yellow-400"></i>
                                </div>
                            </div>

                            <p class="text-lg text-center italic text-muted-foreground mb-8">
                                "The SSCMS has made accessing healthcare so much easier. I can book appointments online
                                and get reminders, which saves me time and ensures I never miss my check-ups. The AI
                                assistant is also incredibly helpful for quick health questions."
                            </p>

                            <div class="flex items-center justify-center space-x-4">
                                <div
                                    class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                    S
                                </div>
                                <div>
                                    <h4 class="font-bold">Anonymous Student</h4>
                                    <p class="text-muted-foreground text-sm">Grade 12</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Nurse Angge Section -->
    <section id="nurse-angge" class="section-padding bg-gradient-primary relative overflow-hidden">
        <div class="absolute inset-0 bg-medical-pattern opacity-10"></div>
        <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-white/5 rounded-full blur-2xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-white/5 rounded-full blur-2xl"></div>

        <div class="container-custom relative z-10">
            <div class="flex flex-col lg:flex-row items-center">
                <div class="lg:w-1/2 mb-12 lg:mb-0 relative">
                    <div class="relative mx-auto w-64 h-64 lg:w-80 lg:h-80">
                        <div class="absolute inset-0 bg-white/20 rounded-full pulse-animation"></div>
                        <div
                            class="absolute inset-4 bg-gradient-primary rounded-full flex items-center justify-center shadow-glow float-animation">
                            <i data-lucide="bot" class="w-32 h-32 text-white"></i>
                        </div>
                        <div class="absolute -top-4 -right-4 text-white rotate-animation">
                            <i data-lucide="sparkles" class="w-8 h-8"></i>
                        </div>
                        <div class="absolute -bottom-4 -left-4 text-white rotate-animation"
                            style="animation-duration: 30s;">
                            <i data-lucide="sparkles" class="w-6 h-6"></i>
                        </div>
                        <div class="absolute top-1/2 -right-8 text-white rotate-animation"
                            style="animation-duration: 40s;">
                            <i data-lucide="sparkles" class="w-5 h-5"></i>
                        </div>
                        <div class="absolute -bottom-8 left-1/4 text-white rotate-animation"
                            style="animation-duration: 25s;">
                            <i data-lucide="sparkles" class="w-7 h-7"></i>
                        </div>
                    </div>
                </div>

                <div class="lg:w-1/2 lg:pl-12">
                    <div
                        class="inline-flex items-center bg-white/20 text-white rounded-full px-4 py-1 text-sm font-medium mb-4 animate-on-scroll">
                        AI Health Assistant
                    </div>

                    <h2 class="text-3xl md:text-4xl font-bold mb-6 text-white text-shadow animate-on-scroll"
                        style="transition-delay: 0.1s;">
                        Meet <span class="">Nurse Angge</span>
                    </h2>

                    <p class="text-white/90 text-lg mb-8 animate-on-scroll" style="transition-delay: 0.2s;">
                        Our AI-powered health assistant provides 24/7 medical guidance, symptom assessment, and
                        personalized health recommendations for the ICCBI community.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                        <div class="flex items-center space-x-3 text-white animate-on-scroll"
                            style="transition-delay: 0.3s;">
                            <div class="bg-white/20 w-10 h-10 rounded-full flex items-center justify-center">
                                <i data-lucide="message-circle" class="w-5 h-5"></i>
                            </div>
                            <span>24/7 Health Consultation</span>
                        </div>

                        <div class="flex items-center space-x-3 text-white animate-on-scroll"
                            style="transition-delay: 0.4s;">
                            <div class="bg-white/20 w-10 h-10 rounded-full flex items-center justify-center">
                                <i data-lucide="brain" class="w-5 h-5"></i>
                            </div>
                            <span>AI-Powered Diagnosis</span>
                        </div>

                        <div class="flex items-center space-x-3 text-white animate-on-scroll"
                            style="transition-delay: 0.5s;">
                            <div class="bg-white/20 w-10 h-10 rounded-full flex items-center justify-center">
                                <i data-lucide="heart" class="w-5 h-5"></i>
                            </div>
                            <span>Personalized Care Tips</span>
                        </div>

                        <div class="flex items-center space-x-3 text-white animate-on-scroll"
                            style="transition-delay: 0.6s;">
                            <div class="bg-white/20 w-10 h-10 rounded-full flex items-center justify-center">
                                <i data-lucide="stethoscope" class="w-5 h-5"></i>
                            </div>
                            <span>Symptom Assessment</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-foreground text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-medical-pattern opacity-5"></div>

        <div class="container-custom px-4 md:px-6 py-12 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="lg:col-span-1">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center">
                            <img src="./assets/img/SSCMS.png" class="w-full h-full object-contain" alt="SSCMS Logo">
                        </div>
                        <div>
                            <h3 class="font-heading font-bold text-xl">SSCMS</h3>
                            <p class="text-white/70 text-xs">ICCBI Healthcare</p>
                        </div>
                    </div>
                    <p class="text-white/70 mb-6">
                        Smart School Clinic Management System for Immaculate Conception College of Balayan, Inc.
                    </p>
                </div>

                <div>
                    <h4 class="font-bold text-lg mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#home" class="text-white/70 hover:text-white transition-colors">Home</a></li>
                        <li><a href="#features" class="text-white/70 hover:text-white transition-colors">Features</a>
                        </li>
                        <li><a href="#about" class="text-white/70 hover:text-white transition-colors">About</a></li>
                        <li><a href="#booking" class="text-white/70 hover:text-white transition-colors">Booking</a></li>
                        <li><a href="#gallery" class="text-white/70 hover:text-white transition-colors">Gallery</a></li>
                        <li><a href="#nurse-angge" class="text-white/70 hover:text-white transition-colors">Nurse
                                Angge</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-lg mb-4">Group 6 BSCS Members</h4>
                    <ul class="space-y-2">
                        <li class="text-white/70">1. Prince Arvee Avena</li>
                        <li class="text-white/70">2. Jean Caraig</li>
                        <li class="text-white/70">3. Andrei Espinar</li>
                        <li class="text-white/70">4. Angelyn Panganiban</li>
                        <li class="text-white/70">5. Kleoford Ordoyo</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-lg mb-4">Contact Info</h4>
                    <div class="space-y-3">
                        <div class="flex items-start space-x-3">
                            <i data-lucide="map-pin" class="w-5 h-5 text-primary mt-0.5"></i>
                            <p class="text-white/70">
                                Immaculate Conception College of Balayan, Inc.,<br>
                                Balayan, Batangas, Philippines
                            </p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <i data-lucide="mail" class="w-5 h-5 text-primary"></i>
                            <p class="text-white/70">support@sscms.com</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/10 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-white/70 text-sm mb-4 md:mb-0">
                    © 2025 SSCMS. All rights reserved.
                </p>
                <p class="text-white/70 text-sm">
                    A Capstone Project by Group 6 BSCS Students
                </p>
            </div>
        </div>

        <button
            class="scroll-to-top fixed bottom-8 right-8 bg-primary text-white w-12 h-12 rounded-full shadow-glow hover:shadow-medical transition-all duration-300 flex items-center justify-center z-40"
            onclick="scrollToTop()">
            <i data-lucide="arrow-up" class="w-5 h-5"></i>
        </button>
    </footer>

    <script>
        lucide.createIcons();

        document.addEventListener('DOMContentLoaded', function () {
            initNavbar();
            initScrollAnimations();
            initCountUpAnimations();
            initScrollToTop();

            setTimeout(() => {
                document.querySelector('nav').classList.add('loaded');
            }, 100);
        });

        function initNavbar() {
            const navbar = document.querySelector('nav');
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const mobileMenu = document.querySelector('.mobile-menu');

            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }

                const scrollToTopBtn = document.querySelector('.scroll-to-top');
                if (window.scrollY > 300) {
                    scrollToTopBtn.classList.add('visible');
                } else {
                    scrollToTopBtn.classList.remove('visible');
                }
            });

            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('open');
                const icon = mobileMenuBtn.querySelector('i');
                if (mobileMenu.classList.contains('open')) {
                    icon.setAttribute('data-lucide', 'x');
                } else {
                    icon.setAttribute('data-lucide', 'menu');
                }
                lucide.createIcons();
            });

            document.querySelectorAll('.mobile-menu a').forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.remove('open');
                    const icon = mobileMenuBtn.querySelector('i');
                    icon.setAttribute('data-lucide', 'menu');
                    lucide.createIcons();
                });
            });
        }

        function scrollToSection(selector) {
            const element = document.querySelector(selector);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth' });
            }
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function initScrollToTop() {
            const scrollToTopBtn = document.querySelector('.scroll-to-top');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 300) {
                    scrollToTopBtn.classList.add('visible');
                } else {
                    scrollToTopBtn.classList.remove('visible');
                }
            });
        }

        function initScrollAnimations() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -100px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.animate-on-scroll').forEach(el => {
                observer.observe(el);
            });
        }

        function initCountUpAnimations() {
            const countUpElements = document.querySelectorAll('.count-up');

            const countUpObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const target = parseInt(element.getAttribute('data-count'));
                        const suffix = element.textContent.replace(/[0-9]/g, '');
                        countUp(element, target, 2000, suffix);
                        countUpObserver.unobserve(element);
                    }
                });
            }, { threshold: 0.5 });

            countUpElements.forEach(el => {
                countUpObserver.observe(el);
            });
        }

        function countUp(element, end, duration = 2000, suffix = '') {
            let start = 0;
            const startTime = performance.now();

            function animate(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const current = Math.floor(easeOutQuart * end);
                element.textContent = current.toLocaleString() + suffix;
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            }

            requestAnimationFrame(animate);
        }

        const galleryData = {
            1: {
                image: './assets/img/dashboard.png',
                category: 'Dashboard',
                title: 'Admin Dashboard',
                description: 'Comprehensive overview of clinic operations with real-time analytics and key performance indicators.'
            },
            2: {
                image: './assets/img/patient.png',
                category: 'Management',
                title: 'Patient Records',
                description: 'Comprehensive student health records with medical history, allergies, and treatment plans.'
            },
            3: {
                image: './assets/img/appointments.png',
                category: 'Scheduling',
                title: 'Scheduling System',
                description: 'Easy appointment booking and management with automated reminders and calendar integration.'
            }
        };

        function openLightbox(id) {
            const lightbox = document.querySelector('.lightbox');
            const imageData = galleryData[id];
            if (imageData) {
                document.getElementById('lightbox-image').src = imageData.image;
                document.getElementById('lightbox-category').textContent = imageData.category;
                document.getElementById('lightbox-title').textContent = imageData.title;
                document.getElementById('lightbox-description').textContent = imageData.description;
                lightbox.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeLightbox() {
            const lightbox = document.querySelector('.lightbox');
            lightbox.classList.remove('open');
            document.body.style.overflow = 'auto';
        }

        document.querySelector('.lightbox').addEventListener('click', function (e) {
            if (e.target === this) {
                closeLightbox();
            }
        });

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>

</html>