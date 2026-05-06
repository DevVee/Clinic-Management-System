<?php
// Set session cookie parameters before starting session
$remember = isset($_POST['remember']) && $_POST['remember'] === 'on';
if ($remember) {
    session_set_cookie_params(30 * 24 * 60 * 60); // 30 days
} else {
    session_set_cookie_params(0); // Session cookie
}

session_start();
require_once 'config/database.php';

// Function to check if column exists (PostgreSQL-compatible)
function columnExists($conn, $table, $column) {
    try {
        $stmt = $conn->prepare(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = ? AND column_name = ?"
        );
        $stmt->execute([$table, $column]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Column check error: " . $e->getMessage());
        return false;
    }
}

// Function to validate session
function isValidSession($conn, $user_id, $session_id) {
    try {
        $has_remember_token = columnExists($conn, 'sessions', 'remember_token');
        $query = $has_remember_token
            ? "SELECT last_active, remember_token FROM sessions WHERE user_id = ? AND session_id = ?"
            : "SELECT last_active FROM sessions WHERE user_id = ? AND session_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($session && (time() - strtotime($session['last_active']) < 3600)) {
            return true; // 1-hour session timeout
        }
        if ($session) {
            error_log("[SSCMS Login] Session timed out for user_id: $user_id");
        }
        return false;
    } catch (Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

// Prevent logged-in users from accessing login page
if (isset($_SESSION['user_id']) && isValidSession($conn, $_SESSION['user_id'], session_id())) {
    header('Location: /dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    try {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
        $remember = isset($_POST['remember']) ? true : false;

        if (!$email || !$password) {
            throw new Exception('Email and password are required.');
        }

        $stmt = $conn->prepare("SELECT id, name, email, password, admin_category, profile_picture, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('Invalid email or password.');
        }

        // Clear any existing sessions for the user
        $stmt = $conn->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Start new session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['admin_category'] = $user['admin_category'];
        $_SESSION['profile_picture'] = $user['profile_picture'];
        $_SESSION['role'] = $user['role'];

        // Handle remember token
        $remember_token = $remember ? bin2hex(random_bytes(16)) : null;
        $has_remember_token = columnExists($conn, 'sessions', 'remember_token');
        $query = $has_remember_token
            ? "INSERT INTO sessions (user_id, session_id, remember_token, last_active) VALUES (?, ?, ?, NOW())"
            : "INSERT INTO sessions (user_id, session_id, last_active) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $params = $has_remember_token
            ? [$user['id'], session_id(), $remember_token]
            : [$user['id'], session_id()];
        $stmt->execute($params);
        error_log("[SSCMS Login] Session created for user_id: {$user['id']}, remember: " . ($remember ? 'yes' : 'no'));

        // Set remember token cookie
        if ($remember && $has_remember_token) {
            setcookie('remember_token', $remember_token, time() + 30 * 24 * 60 * 60, '/', '', false, true); // 30 days, HTTP-only
        }

        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'user_name' => $user['name'],
            'redirect' => '/dashboard.php'
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SSCMS | ICCBI Healthcare</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --primary: 201 85% 39%;
            --secondary: 213 77% 54%;
            --accent: 144 100% 39%;
            --background: 210 40% 98%;
            --foreground: 222 47% 17%;
            --card: 0 0% 100%;
            --border: 214 32% 91%;
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

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }

        .gradient-text {
            background: linear-gradient(135deg, hsl(201 85% 39%) 0%, hsl(213 77% 54%) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .bg-gradient-hero {
            background: linear-gradient(135deg, hsl(201 85% 35%) 0%, hsl(213 77% 45%) 50%, hsl(201 85% 39%) 100%);
        }

        .bg-medical-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%230f73ba' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .float-animation {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        .pulse-animation {
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        .slide-in-left {
            animation: slideInLeft 0.8s ease-out forwards;
        }

        @keyframes slideInLeft {
            from { transform: translateX(-50px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .slide-in-right {
            animation: slideInRight 0.8s ease-out forwards;
        }

        @keyframes slideInRight {
            from { transform: translateX(50px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 1s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .scale-in {
            animation: scaleIn 0.5s ease-out forwards;
        }

        @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            background: hsl(var(--primary));
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px -3px hsl(var(--primary) / 0.4);
        }

        .btn-primary:hover {
            background: hsl(var(--primary) / 0.9);
            box-shadow: 0 8px 25px -5px hsl(var(--primary) / 0.5);
            transform: translateY(-2px);
        }

        .input-field {
            background: white;
            border: 1px solid hsl(var(--border));
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }

        .input-field:focus {
            outline: none;
            border-color: hsl(var(--primary));
            box-shadow: 0 0 0 3px hsl(var(--primary) / 0.1);
        }

        .text-shadow {
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .home-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s;
        }

        .home-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transform: scale(0.9);
            transition: transform 0.3s ease-out;
        }

        .modal.active .modal-content {
            transform: scale(1);
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            padding: 1rem;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 500;
            z-index: 1001;
            transform: translateX(100%);
            transition: transform 0.3s ease-out;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background-color: hsl(144 100% 39%);
        }

        .notification.error {
            background-color: hsl(0 84% 60%);
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Home Button -->
    <div class="fixed top-4 left-4 z-50">
        <a href="../" class="home-btn flex items-center space-x-2 px-4 py-2 rounded-full font-medium">
            <i data-lucide="home" class="w-4 h-4"></i>
            <span class="hidden sm:inline">Home</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Hero Section -->
        <div class="w-full md:w-1/2 bg-gradient-hero relative overflow-hidden flex items-center justify-center py-8 md:py-0">
            <!-- Background Pattern -->
            <div class="absolute inset-0 bg-medical-pattern opacity-20"></div>
            
            <!-- Animated Background Elements -->
            <div class="absolute top-1/4 left-1/4 w-64 h-64 rounded-full bg-white/5 pulse-animation"></div>
            <div class="absolute bottom-1/4 right-1/4 w-48 h-48 rounded-full bg-white/10 pulse-animation" style="animation-delay: 1s;"></div>
            
            <!-- Floating Medical Icons -->
            <div class="absolute top-10 left-5 text-white/20 float-animation">
                <i data-lucide="heart" class="w-8 h-8"></i>
            </div>
            <div class="absolute top-20 right-10 text-white/20 float-animation" style="animation-delay: 0.5s;">
                <i data-lucide="stethoscope" class="w-6 h-6"></i>
            </div>
            <div class="absolute bottom-20 left-10 text-white/20 float-animation" style="animation-delay: 1s;">
                <i data-lucide="pill" class="w-5 h-5"></i>
            </div>
            
            <!-- Content -->
            <div class="relative z-10 text-center text-white px-6 max-w-lg">
                <!-- Logo -->
                <div class="flex items-center justify-center space-x-2 mb-6 slide-in-left">
                    <div class="w-10 h-10 flex items-center justify-center bg-white/20 rounded-full backdrop-blur-sm">
                        <i data-lucide="heart-pulse" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <h1 class="font-heading font-bold text-xl">SSCMS</h1>
                        <p class="text-white/80 text-xs">ICCBI Healthcare</p>
                    </div>
                </div>
                
                <!-- Headline -->
                <h2 class="text-2xl md:text-3xl font-bold mb-4 text-shadow slide-in-right" style="animation-delay: 0.2s;">
                    Smart School Clinic Management System
                </h2>
                
                <!-- Description -->
                <p class="text-white/90 mb-6 text-sm md:text-base max-w-md mx-auto fade-in" style="animation-delay: 0.4s;">
                    Secure healthcare portal for ICCBI community with AI-powered solutions.
                </p>
                
                <!-- Feature Cards -->
                <div class="grid grid-cols-2 gap-3 max-w-md mx-auto mb-6">
                    <div class="feature-card fade-in" style="animation-delay: 0.6s;">
                        <div class="flex items-center space-x-2">
                            <div class="bg-white/20 w-8 h-8 rounded-full flex items-center justify-center">
                                <i data-lucide="shield-check" class="w-4 h-4 text-green-400"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-sm">Secure</h3>
                                <p class="text-white/70 text-xs">Strong Security</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="feature-card fade-in" style="animation-delay: 0.8s;">
                        <div class="flex items-center space-x-2">
                            <div class="bg-white/20 w-8 h-8 rounded-full flex items-center justify-center">
                                <i data-lucide="bot" class="w-4 h-4 text-accent"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-sm">AI Assistant</h3>
                                <p class="text-white/70 text-xs">Nurse Angge</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="flex justify-center space-x-6 fade-in" style="animation-delay: 1.0s;">
                    <div class="text-center">
                        <div class="text-lg font-bold">1K+</div>
                        <div class="text-white/70 text-xs">Students</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold">24/7</div>
                        <div class="text-white/70 text-xs">Support</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold">99%</div>
                        <div class="text-white/70 text-xs">Satisfaction</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Login Section -->
        <div class="w-full md:w-1/2 flex items-center justify-center py-8 px-6 bg-background">
            <div class="w-full max-w-md">
                <!-- Login Card -->
                <div class="login-card rounded-2xl p-6 scale-in">
                    <!-- Header -->
                    <div class="text-center mb-6">
                        <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="log-in" class="w-6 h-6 text-primary"></i>
                        </div>
                        <h2 class="text-xl font-bold text-foreground mb-1">Welcome Back</h2>
                        <p class="text-muted-foreground text-sm">Sign in to your SSCMS account</p>
                    </div>
                    
                    <!-- Login Form -->
                    <form id="loginForm" class="space-y-4" method="POST" action="">
                        <!-- Email Field -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-foreground mb-1">
                                Email Address
                            </label>
                            <div class="relative">
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email"
                                    class="input-field w-full pl-9 text-sm"
                                    placeholder="Enter your email address"
                                    required
                                    autocomplete="email"
                                >
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground">
                                    <i data-lucide="mail" class="w-4 h-4"></i>
                                </div>
                            </div>
                            <div class="error-message text-red-500 text-xs mt-1 hidden" id="emailError"></div>
                        </div>
                        
                        <!-- Password Field -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label for="password" class="block text-sm font-medium text-foreground">
                                    Password
                                </label>
                                <a href="#" class="text-xs text-primary hover:underline" id="forgotPasswordBtn">
                                    Forgot password?
                                </a>
                            </div>
                            <div class="relative">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password"
                                    class="input-field w-full pl-9 pr-9 text-sm"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                >
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground">
                                    <i data-lucide="lock" class="w-4 h-4"></i>
                                </div>
                                <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" id="togglePassword">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <div class="error-message text-red-500 text-xs mt-1 hidden" id="passwordError"></div>
                        </div>
                        
                        <!-- Remember Me -->
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                id="remember" 
                                name="remember"
                                class="w-4 h-4 text-primary border-border rounded focus:ring-primary focus:ring-2"
                            >
                            <label for="remember" class="ml-2 text-sm text-foreground">
                                Remember me
                            </label>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" class="btn-primary w-full py-2.5 flex items-center justify-center space-x-2 text-sm" id="loginButton">
                            <i data-lucide="log-in" class="w-4 h-4"></i>
                            <span>Sign In</span>
                            <div class="spinner hidden" id="loginSpinner"></div>
                        </button>
                    </form>
                    
                    <!-- Security Notice -->
                    <div class="mt-6 text-center">
                        <div class="flex items-center justify-center space-x-2 text-xs text-muted-foreground">
                            <i data-lucide="shield" class="w-3 h-3"></i>
                            <span>Your data is securely encrypted and HIPAA compliant</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <!-- Forgot Password Modal -->
    <div class="modal" id="forgotPasswordModal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Reset Your Password</h3>
                <button type="button" class="text-muted-foreground hover:text-foreground" id="closeForgotPassword">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <p class="text-muted-foreground mb-4 text-sm">Enter your email address and we'll send you a link to reset your password.</p>
            <form id="forgotPasswordForm" class="space-y-4">
                <div>
                    <label for="resetEmail" class="block text-sm font-medium text-foreground mb-1">Email Address</label>
                    <input type="email" id="resetEmail" class="input-field w-full text-sm" placeholder="your.email@example.com" required>
                </div>
                <button type="submit" class="btn-primary w-full py-2.5 text-sm">Send Reset Link</button>
            </form>
        </div>
    </div>

    <!-- Welcome Modal -->
    <div class="modal" id="welcomeModal">
        <div class="modal-content text-center">
            <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                <i data-lucide="heart" class="w-8 h-8 text-primary"></i>
            </div>
            <h3 class="text-lg font-bold mb-2">Welcome!</h3>
            <p class="text-muted-foreground mb-4 text-sm" id="welcomeMessage">Hello, User! Welcome to SSCMS!</p>
            <button type="button" class="btn-primary w-full py-2.5 text-sm" id="continueButton">
                Continue to Dashboard
            </button>
        </div>
    </div>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();
        
        // Modal functionality
        const modals = {
            forgotPassword: document.getElementById('forgotPasswordModal'),
            welcome: document.getElementById('welcomeModal')
        };
        
        // Open modal functions
        document.getElementById('forgotPasswordBtn').addEventListener('click', () => openModal('forgotPassword'));
        
        // Close modal functions
        document.getElementById('closeForgotPassword').addEventListener('click', () => closeModal('forgotPassword'));
        document.getElementById('continueButton').addEventListener('click', () => {
            closeModal('welcome');
            window.location.href = '/dashboard.php';
        });
        
        // Close modals when clicking outside
        Object.keys(modals).forEach(key => {
            modals[key].addEventListener('click', (e) => {
                if (e.target === modals[key]) {
                    closeModal(key);
                }
            });
        });
        
        function openModal(modalName) {
            modals[modalName].classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalName) {
            modals[modalName].classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Toggle Password Visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            
            lucide.createIcons();
        });
        
        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i data-lucide="${type === 'success' ? 'check-circle' : 'alert-circle'}" class="w-4 h-4"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.getElementById('notificationContainer').appendChild(notification);
            
            // Animate in
            setTimeout(() => notification.classList.add('show'), 10);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
            
            lucide.createIcons();
        }
        
        // Form Submission
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const remember = document.getElementById('remember').checked;
            const loginButton = document.getElementById('loginButton');
            const spinner = document.getElementById('loginSpinner');
            
            // Basic validation
            if (!email || !password) {
                showNotification('Please fill in all required fields', 'error');
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showNotification('Please enter a valid email address', 'error');
                return;
            }
            
            // Show loading state
            loginButton.disabled = true;
            spinner.classList.remove('hidden');
            loginButton.classList.add('loading');
            
            try {
                // Create form data
                const formData = new FormData();
                formData.append('email', email);
                formData.append('password', password);
                if (remember) {
                    formData.append('remember', 'on');
                }
                
                // Submit via AJAX
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('welcomeMessage').textContent = `Hello, ${data.user_name || 'User'}! Welcome to SSCMS!`;
                    openModal('welcome');
                    
                    // Auto-redirect after 2 seconds if user doesn't click continue
                    setTimeout(() => {
                        if (modals.welcome.classList.contains('active')) {
                            window.location.href = data.redirect || '/dashboard.php';
                        }
                    }, 2000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Login error:', error);
                showNotification('An error occurred during login. Please try again.', 'error');
            } finally {
                loginButton.disabled = false;
                spinner.classList.add('hidden');
                loginButton.classList.remove('loading');
            }
        });
        
        // Forgot password form
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('resetEmail').value;
            
            if (!email) {
                showNotification('Please enter your email address', 'error');
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showNotification('Please enter a valid email address', 'error');
                return;
            }
            
            // Simulate sending reset link
            showNotification('Password reset link sent to your email', 'success');
            closeModal('forgotPassword');
            document.getElementById('resetEmail').value = '';
        });
        
        // Add some interactivity to form inputs
        document.querySelectorAll('.input-field').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('ring-2', 'ring-primary/20', 'rounded-lg');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('ring-2', 'ring-primary/20', 'rounded-lg');
            });
        });
        
        // Add animation to feature cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        // Observe all feature cards
        document.querySelectorAll('.feature-card').forEach(card => {
            observer.observe(card);
        });
        
        // Handle escape key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                Object.keys(modals).forEach(key => {
                    if (modals[key].classList.contains('active')) {
                        closeModal(key);
                    }
                });
            }
        });
        
        // Auto-focus on email field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField) {
                setTimeout(() => {
                    emailField.focus();
                }, 500);
            }
        });

        // Enter key submission
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.target.closest('.modal')) {
                const loginForm = document.getElementById('loginForm');
                if (loginForm && !loginForm.contains(document.activeElement)) {
                    return;
                }
                if (document.activeElement && document.activeElement.type !== 'textarea') {
                    loginForm.dispatchEvent(new Event('submit'));
                }
            }
        });
    </script>
</body>
</html>