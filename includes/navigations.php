<?php
// Start output buffering to prevent premature output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate user session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("Unauthorized access attempt to sidebar.php: " . (isset($_SESSION['user_id']) ? "user_id: {$_SESSION['user_id']}" : "no session"));
    header('Location: /login.php');
    exit;
}

// Fallback user data
$user_name = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin';
$admin_category = isset($_SESSION['admin_category']) ? htmlspecialchars($_SESSION['admin_category']) : 'Admin';
$profile_picture = isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] ? htmlspecialchars($_SESSION['profile_picture']) : null;

// Check for SuperAdmin role (case-insensitive)
$is_superadmin = isset($_SESSION['role']) && strcasecmp($_SESSION['role'], 'SuperAdmin') === 0;

// Sanitize current file for active link
$current_file = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';

// Get current time for greeting
$current_hour = date('H');
$greeting = '';
if ($current_hour < 12) {
    $greeting = 'Good Morning';
} elseif ($current_hour < 18) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}
?>

<!-- Apply theme before first paint to avoid flash -->
<script>
(function(){
    var t = localStorage.getItem('sscms_theme');
    if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
})();
</script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap');

    :root {
        /* Medical Theme Colors - From Landing Page */
        --primary: hsl(201, 85%, 39%);
        --primary-dark: hsl(201, 85%, 30%);
        --secondary: hsl(213, 77%, 54%);
        --accent: hsl(144, 100%, 39%);
        --warning: hsl(32, 100%, 50%);
        --background: hsl(210, 40%, 98%);
        --foreground: hsl(222, 47%, 17%);
        --card: hsl(0, 0%, 100%);
        --muted: hsl(210, 40%, 96%);
        --muted-foreground: hsl(215, 16%, 47%);
        --border: hsl(214, 32%, 91%);
        --destructive: hsl(0, 84%, 60%);
        
        /* Layout Variables */
        --top-bar-height: 40px;
        --header-height: 70px;
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 70px;
        --ai-chat-width: 420px;
        
        /* Shadows */
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --shadow-glow: 0 0 20px hsla(201, 85%, 39%, 0.15);
        
        /* Transitions */
        --transition-fast: 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        --transition-normal: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: var(--background);
        color: var(--foreground);
        overflow-x: hidden;
        padding-top: calc(var(--top-bar-height) + var(--header-height));
    }

    h1, h2, h3, h4, h5, h6 {
        font-family: 'Poppins', sans-serif;
    }

    /* Top Status Bar */
    .top-header {
        height: var(--top-bar-height);
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        font-size: 0.75rem;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1040;
        display: flex;
        align-items: center;
        padding: 0 1.5rem;
        box-shadow: var(--shadow-md);
    }

    .clinic-status {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--accent);
        animation: pulse-indicator 2s ease-in-out infinite;
        box-shadow: 0 0 0 0 rgba(0, 200, 81, 0.7);
    }

    @keyframes pulse-indicator {
        0%, 100% { 
            opacity: 1; 
            box-shadow: 0 0 0 0 rgba(0, 200, 81, 0.7);
        }
        50% { 
            opacity: 0.7; 
            box-shadow: 0 0 0 4px rgba(0, 200, 81, 0);
        }
    }

    .status-text {
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .separator {
        opacity: 0.3;
        margin: 0 0.5rem;
    }

    .current-time {
        margin-left: auto;
        font-weight: 500;
        font-family: 'Poppins', sans-serif;
        letter-spacing: 0.05em;
    }

    /* Main Navbar */
    .main-navbar {
        background: var(--card);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--border);
        box-shadow: var(--shadow-md);
        padding: 0.75rem 1.5rem;
        position: fixed;
        top: var(--top-bar-height);
        left: 0;
        right: 0;
        z-index: 1030;
        height: var(--header-height);
        transition: all var(--transition-normal);
    }

    .navbar-brand-container {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 0 0 auto;
    }

    .brand-logo {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        object-fit: contain;
        transition: transform var(--transition-fast);
    }

    .brand-logo:hover {
        transform: scale(1.05);
    }

    .brand-info {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .brand-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary);
        margin: 0;
        line-height: 1.2;
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .brand-subtitle {
        font-size: 0.7rem;
        color: var(--muted-foreground);
        font-weight: 500;
        letter-spacing: 0.02em;
    }

    .sidebar-toggle-btn {
        background: var(--muted);
        border: 1px solid var(--border);
        color: var(--foreground);
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-fast);
        margin-left: 1rem;
    }

    .sidebar-toggle-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: translateX(-2px);
    }

    .flex-grow-1 {
        flex-grow: 1;
    }

    /* Ask AI Button - Minimal Clean Design */
    .ask-ai-btn {
        background: white;
        border: 2px solid var(--primary);
        color: var(--primary);
        padding: 0.625rem 1.25rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9375rem;
        font-family: 'Poppins', sans-serif;
        display: flex;
        align-items: center;
        gap: 0.625rem;
        cursor: pointer;
        transition: all var(--transition-fast);
        box-shadow: 0 2px 8px rgba(15, 115, 186, 0.15);
        position: relative;
        overflow: hidden;
        margin-right: 1rem;
    }

    .ask-ai-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(15, 115, 186, 0.1), transparent);
        transition: left 0.6s;
    }

    .ask-ai-btn:hover {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border-color: var(--primary);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(15, 115, 186, 0.3);
    }

    .ask-ai-btn:hover::before {
        left: 100%;
    }

    .ask-ai-btn:hover .ask-ai-icon {
        color: white;
    }

    .ask-ai-btn:active {
        transform: translateY(0);
    }

    .ask-ai-icon {
        font-size: 1.125rem;
        color: var(--primary);
        transition: color var(--transition-fast);
    }

    .ask-ai-text {
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    /* Profile Section */
    .profile-section {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .profile-greeting {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.125rem;
    }

    .greeting-text {
        font-size: 0.7rem;
        color: var(--muted-foreground);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .user-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--foreground);
        font-family: 'Poppins', sans-serif;
    }

    .profile-dropdown {
        position: relative;
    }

    .profile-trigger {
        background: var(--muted);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0.75rem;
        border-radius: 12px;
        cursor: pointer;
        transition: all var(--transition-fast);
    }

    .profile-trigger:hover {
        background: var(--card);
        border-color: var(--primary);
        box-shadow: var(--shadow-glow);
        transform: translateY(-1px);
    }

    .profile-avatar {
        position: relative;
        width: 40px;
        height: 40px;
    }

    .profile-image {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary);
    }

    .avatar-placeholder {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.1rem;
        border: 2px solid var(--primary);
    }

    .online-indicator {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 12px;
        height: 12px;
        background: var(--accent);
        border: 2px solid var(--card);
        border-radius: 50%;
        animation: pulse-indicator 2s ease-in-out infinite;
    }

    .dropdown-arrow {
        color: var(--muted-foreground);
        font-size: 0.75rem;
        transition: all var(--transition-fast);
    }

    .profile-trigger:hover .dropdown-arrow {
        color: var(--primary);
        transform: translateY(2px);
    }

    /* Profile Dropdown Menu */
    .profile-menu {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: var(--shadow-lg);
        padding: 0.5rem;
        min-width: 240px;
        margin-top: 0.75rem;
        animation: dropdown-appear 0.2s ease forwards;
        transform-origin: top right;
    }

    .profile-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 0.5rem;
    }

    .profile-info strong {
        display: block;
        color: var(--foreground);
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        font-family: 'Poppins', sans-serif;
    }

    .profile-info small {
        color: var(--muted-foreground);
        font-size: 0.75rem;
        font-weight: 500;
    }

    .dropdown-item {
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--foreground);
        text-decoration: none;
        transition: all var(--transition-fast);
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 10px;
        margin-bottom: 0.25rem;
    }

    .dropdown-item:hover {
        background: linear-gradient(135deg, hsla(201, 85%, 95%, 0.8), hsla(213, 77%, 95%, 0.8));
        color: var(--primary);
        transform: translateX(4px);
    }

    .dropdown-item i {
        width: 18px;
        text-align: center;
        color: var(--muted-foreground);
        transition: color var(--transition-fast);
    }

    .dropdown-item:hover i {
        color: var(--primary);
    }

    .logout-item {
        color: var(--destructive);
        border-top: 1px solid var(--border);
        margin-top: 0.5rem;
        padding-top: 1rem;
    }

    .logout-item:hover {
        background: hsla(0, 84%, 60%, 0.1);
        color: var(--destructive);
    }

    .logout-item:hover i {
        color: var(--destructive);
    }

    /* Sidebar */
    .sidebar {
        position: fixed;
        top: calc(var(--top-bar-height) + var(--header-height));
        left: 0;
        height: calc(100vh - var(--top-bar-height) - var(--header-height));
        width: var(--sidebar-width);
        overflow-y: auto;
        overflow-x: hidden;
        background: var(--card);
        border-right: 1px solid var(--border);
        box-shadow: var(--shadow-lg);
        z-index: 1020;
        transition: all var(--transition-normal);
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: var(--muted-foreground);
    }

    /* Sidebar Header */
    .sidebar-header {
        padding: 1.5rem 1rem;
        text-align: center;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(to bottom, var(--muted), var(--card));
        transition: all var(--transition-normal);
    }

    .profile-image-container {
        position: relative;
        width: 60px;
        height: 60px;
        margin: 0 auto 0.75rem;
        transition: all var(--transition-normal);
    }

    .profile-image-container .profile-image {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--primary);
        box-shadow: var(--shadow-glow);
    }

    .profile-image-container .avatar-placeholder {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        border: 3px solid var(--primary);
        box-shadow: var(--shadow-glow);
    }

    .status-dot {
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 14px;
        height: 14px;
        background: var(--accent);
        border: 3px solid var(--card);
        border-radius: 50%;
        animation: pulse-indicator 2s ease-in-out infinite;
    }

    .sidebar-header p {
        color: var(--foreground);
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        font-family: 'Poppins', sans-serif;
        transition: all var(--transition-normal);
    }

    .sidebar-header small {
        color: var(--muted-foreground);
        font-size: 0.75rem;
        font-weight: 500;
        transition: all var(--transition-normal);
    }

    /* Navigation Sections */
    .nav-section {
        padding: 1rem 1.25rem 0.5rem;
        color: var(--muted-foreground);
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-family: 'Poppins', sans-serif;
        transition: all var(--transition-normal);
    }

    /* Navigation Links */
    .sidebar nav {
        padding: 0.5rem 0 1.5rem;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1.25rem;
        color: var(--foreground);
        border-radius: 12px;
        margin: 0.25rem 0.75rem;
        font-weight: 500;
        font-size: 0.875rem;
        min-height: 44px;
        transition: all var(--transition-fast);
        text-decoration: none;
        position: relative;
        overflow: hidden;
    }

    .nav-link::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 0;
        background: linear-gradient(to bottom, var(--primary), var(--secondary));
        border-radius: 0 3px 3px 0;
        transition: height var(--transition-fast);
    }

    .nav-link:hover::before {
        height: 70%;
    }

    .nav-link:hover {
        background: linear-gradient(135deg, hsla(201, 85%, 95%, 0.5), hsla(213, 77%, 95%, 0.5));
        color: var(--primary);
        transform: translateX(4px);
    }

    .nav-link.active {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        box-shadow: var(--shadow-glow);
    }

    .nav-link.active::before {
        height: 0;
    }

    .nav-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        margin-right: 0.75rem;
        font-size: 1rem;
        transition: all var(--transition-fast);
        flex-shrink: 0;
    }

    .nav-link span {
        white-space: nowrap;
        transition: all var(--transition-normal);
    }

    /* Icon Background Colors - Medical Theme */
    .nav-link:nth-of-type(1) .nav-icon { background: hsla(201, 85%, 95%, 0.8); color: var(--primary); }
    .nav-link:nth-of-type(2) .nav-icon { background: hsla(144, 100%, 95%, 0.8); color: var(--accent); }
    .nav-link:nth-of-type(3) .nav-icon,
    .nav-link:nth-of-type(4) .nav-icon,
    .nav-link:nth-of-type(5) .nav-icon { background: hsla(213, 77%, 95%, 0.8); color: var(--secondary); }
    .nav-link:nth-of-type(6) .nav-icon,
    .nav-link:nth-of-type(7) .nav-icon { background: hsla(32, 100%, 95%, 0.8); color: var(--warning); }
    
    .nav-link.active .nav-icon {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    /* Accordion Styles */
    .accordion-toggle {
        cursor: pointer;
    }

    .accordion-icon {
        margin-left: auto;
        transition: transform var(--transition-fast);
        font-size: 0.75rem;
    }

    .accordion-toggle[aria-expanded="true"] .accordion-icon {
        transform: rotate(180deg);
    }

    .collapse {
        transition: all var(--transition-normal);
        overflow: hidden;
    }

    .nav-sublink {
        padding-left: 3.5rem;
        font-size: 0.8rem;
        margin: 0.125rem 0.75rem;
        min-height: 38px;
    }

    .nav-sublink .nav-icon {
        width: 28px;
        height: 28px;
        font-size: 0.875rem;
    }

    /* Sidebar Overlay */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 1015;
        opacity: 0;
        visibility: hidden;
        transition: all var(--transition-normal);
    }

    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /* ============================================
       AI CHAT MODULE STYLES
       ============================================ */

    /* AI Chat Overlay */
    .ai-chat-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,.35);
        backdrop-filter: blur(2px);
        z-index: 1034;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .ai-chat-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /* AI Chat Container */
    .ai-chat-container {
        position: fixed;
        top: calc(var(--top-bar-height) + var(--header-height));
        right: 0;
        width: var(--ai-chat-width);
        height: calc(100vh - var(--top-bar-height) - var(--header-height));
        background: var(--card);
        border-left: 1px solid var(--border);
        box-shadow: -8px 0 32px rgba(0,0,0,.12);
        z-index: 1035;
        transform: translateX(100%);
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        border-radius: 16px 0 0 0;
    }

    .ai-chat-container.show {
        transform: translateX(0);
    }

    @media (max-width: 576px) {
        .ai-chat-container {
            top: 60px;
            height: calc(100vh - 60px);
            width: 100%;
            border-radius: 0;
        }
    }

    /* AI Chat Header */
    .ai-chat-header {
        padding: 1.1rem 1.25rem;
        border-bottom: 1px solid rgba(255,255,255,.12);
        background: linear-gradient(135deg, hsl(201,85%,28%) 0%, hsl(213,77%,45%) 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
        position: relative;
        overflow: hidden;
        border-radius: 16px 0 0 0;
    }

    .ai-chat-header::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M20 20v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zM4 4V0H2v4H0v2h2v4h2V6h4V4H4z'/%3E%3C/g%3E%3C/svg%3E");
        pointer-events: none;
    }

    .ai-chat-title-section {
        display: flex;
        align-items: center;
        gap: 1rem;
        z-index: 1;
    }

    .ai-chat-icon {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(10px);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .ai-chat-title-text h3 {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 0.25rem 0;
        font-family: 'Poppins', sans-serif;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .ai-chat-title-text p {
        font-size: 0.8rem;
        margin: 0;
        opacity: 0.95;
        font-weight: 400;
    }

    .ai-chat-close-btn {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-fast);
        flex-shrink: 0;
        z-index: 1;
    }

    .ai-chat-close-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .ai-chat-close-btn i {
        font-size: 1.125rem;
    }

    /* End Chat Button */
    .ai-chat-end-btn {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: all var(--transition-fast);
        flex-shrink: 0;
        z-index: 1;
        font-size: 0.8125rem;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
    }

    .ai-chat-end-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
    }

    .ai-chat-end-btn i {
        font-size: 0.875rem;
    }

    .ai-chat-actions {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        z-index: 1;
    }

    /* AI Chat Messages Container */
    .ai-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem 1.25rem;
        background: var(--background);
    }

    .ai-chat-messages::-webkit-scrollbar {
        width: 6px;
    }

    .ai-chat-messages::-webkit-scrollbar-track {
        background: transparent;
    }

    .ai-chat-messages::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 3px;
    }

    .ai-chat-messages::-webkit-scrollbar-thumb:hover {
        background: var(--muted-foreground);
    }

    /* Welcome Message */
    .ai-welcome-message {
        text-align: center;
        padding: 3rem 1.5rem;
        color: var(--muted-foreground);
        animation: fadeInUp 0.6s ease;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .ai-welcome-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.75rem;
        font-size: 3rem;
        color: white;
        box-shadow: 0 8px 24px rgba(15, 115, 186, 0.3);
        position: relative;
        overflow: hidden;
    }

    .ai-welcome-icon::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        animation: shimmer 3s infinite;
    }

    @keyframes shimmer {
        0% {
            transform: translateX(-100%) translateY(-100%) rotate(45deg);
        }
        100% {
            transform: translateX(100%) translateY(100%) rotate(45deg);
        }
    }

    .ai-welcome-message h4 {
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--foreground);
        margin-bottom: 0.75rem;
        font-family: 'Poppins', sans-serif;
    }

    .ai-welcome-message p {
        font-size: 0.9375rem;
        line-height: 1.7;
        margin-bottom: 0;
        color: var(--muted-foreground);
    }

    /* Chat Message */
    .chat-message {
        display: flex;
        gap: 0.875rem;
        margin-bottom: 1.75rem;
        animation: message-appear 0.4s ease forwards;
    }

    @keyframes message-appear {
        from {
            opacity: 0;
            transform: translateY(15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.125rem;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .chat-message.user .message-avatar {
        background: linear-gradient(135deg, hsl(201,85%,35%), hsl(213,77%,50%));
        color: white;
        font-size: 0.85rem;
        font-weight: 700;
        font-family: 'Poppins', sans-serif;
        border-radius: 10px;
    }

    .chat-message.ai .message-avatar {
        background: white;
        border: 1.5px solid #dbeafe;
        color: var(--primary);
        border-radius: 10px;
    }

    .message-content {
        flex: 1;
    }

    .message-bubble {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 0 14px 14px 14px;
        padding: 0.75rem 1rem;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
        margin-bottom: 0.25rem;
        transition: box-shadow var(--transition-fast);
    }

    .chat-message.user .message-bubble {
        background: linear-gradient(135deg, hsl(201,85%,35%), hsl(213,77%,50%));
        color: white;
        border: none;
        border-radius: 14px 0 14px 14px;
        box-shadow: 0 3px 10px rgba(15,115,186,.22);
    }

    .chat-message.ai .message-bubble {
        background: #f0f7ff;
        border: 1px solid #dbeafe;
    }

    .message-text {
        font-size: 0.9375rem;
        line-height: 1.65;
        margin: 0;
        word-wrap: break-word;
    }

    .chat-message.user .message-text {
        color: white;
    }

    .message-text ol,
    .message-text ul {
        margin: 0.625rem 0;
        padding-left: 1.5rem;
    }

    .message-text li {
        margin-bottom: 0.375rem;
    }

    .message-text br {
        display: block;
        margin: 0.25rem 0;
        content: "";
    }

    .message-time {
        font-size: 0.7rem;
        color: var(--muted-foreground);
        padding: 0 0.375rem;
        font-weight: 500;
    }

    .chat-message.user .message-time {
        color: rgba(255, 255, 255, 0.8);
    }

    /* Typing Indicator */
    .typing-indicator {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .typing-indicator .message-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--muted);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .typing-dots {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        display: flex;
        gap: 0.375rem;
    }

    .typing-dot {
        width: 8px;
        height: 8px;
        background: var(--muted-foreground);
        border-radius: 50%;
        animation: typing-bounce 1.4s ease-in-out infinite;
    }

    .typing-dot:nth-child(1) {
        animation-delay: 0s;
    }

    .typing-dot:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-dot:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing-bounce {
        0%, 60%, 100% {
            transform: translateY(0);
        }
        30% {
            transform: translateY(-8px);
        }
    }

    /* AI Chat Input */
    .ai-chat-input-container {
        padding: 1.5rem;
        border-top: 1px solid var(--border);
        background: linear-gradient(to top, var(--card), var(--background));
        flex-shrink: 0;
    }

    .ai-chat-input-wrapper {
        display: flex;
        gap: 0.875rem;
        align-items: flex-end;
        background: white;
        border: 2px solid var(--border);
        border-radius: 16px;
        padding: 0.625rem;
        transition: all var(--transition-fast);
    }

    .ai-chat-input-wrapper:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(15, 115, 186, 0.1);
    }

    .ai-chat-input {
        flex: 1;
        background: transparent;
        border: none;
        padding: 0.75rem 0.875rem;
        font-size: 0.9375rem;
        font-family: 'Inter', sans-serif;
        color: var(--foreground);
        resize: none;
        min-height: 48px;
        max-height: 120px;
        line-height: 1.5;
        transition: all var(--transition-fast);
    }

    .ai-chat-input:focus {
        outline: none;
    }

    .ai-chat-input::placeholder {
        color: var(--muted-foreground);
    }

    .ai-chat-send-btn {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border: none;
        color: white;
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-fast);
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(15, 115, 186, 0.3);
    }

    .ai-chat-send-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(15, 115, 186, 0.4);
    }

    .ai-chat-send-btn:active:not(:disabled) {
        transform: translateY(0);
    }

    .ai-chat-send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .ai-chat-send-btn i {
        font-size: 1.25rem;
    }

    /* Character Counter */
    .ai-chat-input-container {
        position: relative;
    }

    .char-counter {
        position: absolute;
        bottom: 2rem;
        right: 5.75rem;
        font-size: 0.7rem;
        color: var(--muted-foreground);
        font-weight: 500;
        background: rgba(255, 255, 255, 0.95);
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        pointer-events: none;
        z-index: 10;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .char-counter.warning {
        color: var(--warning);
        background: rgba(255, 165, 0, 0.1);
    }

    .char-counter.danger {
        color: var(--destructive);
        background: rgba(220, 38, 38, 0.1);
    }

    /* Animations */
    @keyframes dropdown-appear {
        from {
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }

    /* Responsive Design */
    @media (max-width: 992px) {
        .sidebar {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header p,
        .sidebar-header small,
        .nav-section,
        .nav-link span,
        .accordion-icon {
            opacity: 0;
            height: 0;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 1rem 0.5rem;
        }

        .profile-image-container {
            width: 40px;
            height: 40px;
            margin-bottom: 0;
        }

        .nav-link {
            padding: 0.75rem;
            margin: 0.25rem 0.5rem;
            justify-content: center;
        }

        .nav-icon {
            margin-right: 0;
        }

        .collapse,
        .nav-sublink {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            left: calc(-1 * var(--sidebar-width));
            width: var(--sidebar-width);
            box-shadow: none;
            transition: left var(--transition-normal), box-shadow var(--transition-normal);
        }

        .sidebar.show {
            left: 0;
            box-shadow: 4px 0 20px rgba(0,0,0,.15);
        }

        .sidebar-header p,
        .sidebar-header small,
        .nav-section,
        .nav-link span,
        .accordion-icon {
            opacity: 1;
            height: auto;
            overflow: visible;
        }

        .sidebar-header {
            padding: 1.25rem 1rem;
        }

        .profile-image-container {
            width: 52px;
            height: 52px;
            margin-bottom: 0.5rem;
        }

        .nav-link {
            padding: 0.75rem 1.25rem;
            margin: 0.2rem 0.75rem;
            justify-content: flex-start;
            min-height: 46px;
        }

        .nav-icon {
            margin-right: 0.75rem;
        }

        .collapse,
        .nav-sublink {
            display: block !important;
        }

        .profile-greeting {
            display: none;
        }

        .brand-info {
            display: none;
        }

        /* AI Chat Full Width on Mobile */
        .ai-chat-container {
            width: 100%;
            max-width: 100%;
        }
    }

    @media (max-width: 576px) {
        /* Hide top status bar on small screens to maximize space */
        .top-header {
            display: none;
        }

        /* Reduce body offset to just the navbar height */
        body {
            padding-top: var(--header-height);
        }

        /* Tighten navbar on mobile */
        .main-navbar {
            top: 0;
            height: 60px;
            padding: 0.5rem 1rem;
        }

        /* Sidebar starts right below the navbar */
        .sidebar {
            top: 60px;
            height: calc(100vh - 60px);
        }

        .user-name {
            display: none;
        }

        .brand-logo {
            width: 34px;
            height: 34px;
        }

        .ask-ai-text {
            display: none;
        }

        .ask-ai-btn {
            padding: 0.5rem;
            width: 40px;
            height: 40px;
            justify-content: center;
            margin-right: 0.5rem;
        }

        .ask-ai-icon {
            margin: 0;
        }

        .ai-chat-end-btn span {
            display: none;
        }

        .ai-chat-end-btn {
            padding: 0.5rem;
            width: 36px;
        }

        /* Profile trigger tighter on mobile */
        .profile-trigger {
            padding: 0.375rem 0.5rem;
        }
    }

    /* Show/hide hamburger icon animation */
    .sidebar-toggle-btn.active i::before {
        content: "\f00d"; /* fa-times */
    }

    /* Accessibility */
    .nav-link:focus,
    .profile-trigger:focus,
    .accordion-toggle:focus,
    .sidebar-toggle-btn:focus,
    .ask-ai-btn:focus,
    .ai-chat-close-btn:focus,
    .ai-chat-send-btn:focus,
    .ai-chat-input:focus {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }

    /* ── Dark Mode Variables ── */
    [data-theme="dark"] {
        --background:       hsl(222, 47%, 9%);
        --foreground:       hsl(210, 40%, 96%);
        --card:             hsl(222, 47%, 13%);
        --muted:            hsl(222, 40%, 18%);
        --muted-foreground: hsl(215, 20%, 65%);
        --border:           hsl(222, 30%, 22%);
        --primary:          hsl(201, 85%, 52%);
        --primary-dark:     hsl(201, 85%, 40%);
        --secondary:        hsl(213, 77%, 65%);
        --shadow-glow:      0 0 20px hsla(201, 85%, 52%, 0.2);
    }

    /* Dark mode dark theme application */
    [data-theme="dark"] body             { background: var(--background); color: var(--foreground); }
    [data-theme="dark"] .main-navbar     { background: var(--card); border-color: var(--border); }
    [data-theme="dark"] .sidebar         { background: var(--card); border-color: var(--border); }
    [data-theme="dark"] .sidebar-header  { background: var(--muted); border-color: var(--border); }
    [data-theme="dark"] .nav-item,
    [data-theme="dark"] .nav-link        { color: var(--muted-foreground); }
    [data-theme="dark"] .nav-link:hover,
    [data-theme="dark"] .nav-link.active { background: rgba(201,85%,52%,.12); color: var(--primary); }
    [data-theme="dark"] .profile-trigger { background: var(--muted); border-color: var(--border); }
    [data-theme="dark"] .dropdown-menu,
    [data-theme="dark"] .profile-menu    { background: var(--card); border-color: var(--border); }
    [data-theme="dark"] .dropdown-item   { color: var(--foreground); }
    [data-theme="dark"] .dropdown-item:hover { background: var(--muted); }
    [data-theme="dark"] .sidebar-toggle-btn { background: var(--muted); border-color: var(--border); color: var(--foreground); }
    [data-theme="dark"] .ask-ai-btn      { background: var(--muted); border-color: var(--primary); color: var(--primary); }
    [data-theme="dark"] .ai-chat-panel   { background: var(--card); border-color: var(--border); }
    [data-theme="dark"] .ai-message      { background: var(--muted); }

    /* ── Dark Mode: generic page-level overrides (applies to all pages) ── */
    [data-theme="dark"] body {
        background: hsl(222,47%,9%) !important;
        color: hsl(210,40%,92%) !important;
    }

    /* Cards */
    [data-theme="dark"] .card,
    [data-theme="dark"] .card-body,
    [data-theme="dark"] .summary-card,
    [data-theme="dark"] .chart-card {
        background: hsl(222,47%,13%) !important;
        border-color: hsl(222,30%,22%) !important;
        color: hsl(210,40%,92%) !important;
    }

    [data-theme="dark"] .card-header {
        background: hsl(222,47%,18%) !important;
        border-color: hsl(222,30%,22%) !important;
        color: hsl(210,40%,96%) !important;
    }

    /* Tables */
    [data-theme="dark"] .table,
    [data-theme="dark"] .table td {
        color: hsl(210,40%,88%) !important;
        border-color: hsl(222,30%,25%) !important;
    }

    [data-theme="dark"] .table th,
    [data-theme="dark"] .table thead th {
        background: hsl(222,47%,18%) !important;
        color: hsl(210,40%,96%) !important;
        border-color: hsl(222,30%,28%) !important;
    }

    [data-theme="dark"] .table-striped tbody tr:nth-of-type(odd) td,
    [data-theme="dark"] .table-hover tbody tr:hover td {
        background: hsl(222,47%,16%) !important;
    }

    /* Forms */
    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select,
    [data-theme="dark"] input[type="text"],
    [data-theme="dark"] input[type="number"],
    [data-theme="dark"] input[type="email"],
    [data-theme="dark"] input[type="date"],
    [data-theme="dark"] select,
    [data-theme="dark"] textarea {
        background: hsl(222,47%,18%) !important;
        border-color: hsl(222,30%,30%) !important;
        color: hsl(210,40%,92%) !important;
    }

    [data-theme="dark"] .form-control:focus,
    [data-theme="dark"] .form-select:focus {
        background: hsl(222,47%,20%) !important;
        border-color: hsl(201,85%,50%) !important;
        box-shadow: 0 0 0 0.2rem hsla(201,85%,50%,.2) !important;
    }

    [data-theme="dark"] .form-label,
    [data-theme="dark"] label {
        color: hsl(210,40%,80%) !important;
    }

    [data-theme="dark"] .form-text,
    [data-theme="dark"] .text-muted {
        color: hsl(215,20%,55%) !important;
    }

    /* Modals */
    [data-theme="dark"] .modal-content {
        background: hsl(222,47%,12%) !important;
        border-color: hsl(222,30%,22%) !important;
        color: hsl(210,40%,92%) !important;
    }

    [data-theme="dark"] .modal-header,
    [data-theme="dark"] .modal-footer {
        background: hsl(222,47%,14%) !important;
        border-color: hsl(222,30%,22%) !important;
        color: hsl(210,40%,96%) !important;
    }

    [data-theme="dark"] .modal-title { color: hsl(210,40%,96%) !important; }
    [data-theme="dark"] .btn-close   { filter: invert(1); }

    /* Misc elements */
    [data-theme="dark"] .breadcrumb,
    [data-theme="dark"] .custom-breadcrumb {
        background: hsl(222,47%,13%) !important;
        border-color: hsl(222,30%,22%) !important;
    }

    [data-theme="dark"] .breadcrumb-item a,
    [data-theme="dark"] .breadcrumb-item.active {
        color: hsl(215,20%,65%) !important;
    }

    [data-theme="dark"] .input-group-text {
        background: hsl(222,47%,20%) !important;
        border-color: hsl(222,30%,30%) !important;
        color: hsl(210,40%,80%) !important;
    }

    [data-theme="dark"] .dataTables_wrapper .dataTables_length,
    [data-theme="dark"] .dataTables_wrapper .dataTables_filter,
    [data-theme="dark"] .dataTables_wrapper .dataTables_info,
    [data-theme="dark"] .dataTables_wrapper .dataTables_paginate {
        color: hsl(210,40%,75%) !important;
    }

    [data-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: hsl(210,40%,80%) !important;
    }

    [data-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    [data-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: hsl(201,85%,30%) !important;
        color: white !important;
        border-color: hsl(201,85%,30%) !important;
    }

    [data-theme="dark"] .alert-info    { background: hsl(201,85%,12%) !important; border-color: hsl(201,85%,30%) !important; color: hsl(201,85%,80%) !important; }
    [data-theme="dark"] .alert-success { background: hsl(152,74%,10%) !important; border-color: hsl(152,74%,25%) !important; color: hsl(152,74%,75%) !important; }
    [data-theme="dark"] .alert-warning { background: hsl(38,92%,10%) !important;  border-color: hsl(38,92%,25%) !important;  color: hsl(38,92%,80%) !important;  }
    [data-theme="dark"] .alert-danger  { background: hsl(0,84%,12%) !important;   border-color: hsl(0,84%,28%) !important;   color: hsl(0,84%,80%) !important;   }

    [data-theme="dark"] .badge.bg-primary   { background: hsl(201,85%,30%) !important; }
    [data-theme="dark"] .badge.bg-secondary { background: hsl(215,20%,35%) !important; }
    [data-theme="dark"] .badge.bg-success   { background: hsl(152,74%,25%) !important; }

    [data-theme="dark"] hr,
    [data-theme="dark"] .dropdown-divider { border-color: hsl(222,30%,25%) !important; }

    [data-theme="dark"] h1,[data-theme="dark"] h2,
    [data-theme="dark"] h3,[data-theme="dark"] h4,
    [data-theme="dark"] h5,[data-theme="dark"] h6 {
        color: hsl(210,40%,96%) !important;
    }

    /* ── Dark Mode Toggle Button ── */
    .dark-mode-toggle {
        background: var(--muted);
        border: 1px solid var(--border);
        color: var(--foreground);
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-fast);
        margin-right: 0.5rem;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .dark-mode-toggle:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: rotate(15deg);
    }

    [data-theme="dark"] .dark-mode-toggle {
        background: var(--muted);
        border-color: var(--border);
        color: #fbbf24;
    }

    [data-theme="dark"] .dark-mode-toggle:hover {
        background: #d97706;
        border-color: #d97706;
        color: white;
    }

    /* ── BREADCRUMB ── */
    .sscms-breadcrumb-nav {
        margin-bottom: 16px;
    }
    .sscms-breadcrumb {
        display: flex;
        align-items: center;
        gap: 4px;
        list-style: none;
        padding: 8px 14px;
        margin: 0;
        background: var(--card, #fff);
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px;
        font-size: 13px;
        flex-wrap: wrap;
        box-shadow: var(--shadow-sm);
    }
    .sscms-bc-item {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--muted-foreground, #64748b);
    }
    .sscms-bc-item a {
        color: var(--primary, #0369a1);
        text-decoration: none;
        font-weight: 500;
        transition: color .15s;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .sscms-bc-item a:hover {
        color: var(--primary-dark, #025585);
        text-decoration: underline;
    }
    .sscms-bc-item.active {
        color: var(--foreground, #0f172a);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .sscms-bc-sep {
        font-size: 9px;
        opacity: .4;
        flex-shrink: 0;
    }
    [data-theme="dark"] .sscms-breadcrumb {
        background: hsl(222,47%,13%);
        border-color: hsl(222,30%,22%);
    }
    [data-theme="dark"] .sscms-bc-item {
        color: hsl(215,20%,55%);
    }
    [data-theme="dark"] .sscms-bc-item.active {
        color: hsl(210,40%,88%);
    }
    [data-theme="dark"] .sscms-bc-item a {
        color: hsl(201,85%,60%);
    }
    [data-theme="dark"] .sscms-bc-item a:hover {
        color: hsl(201,85%,70%);
    }

    /* ── AI Suggestion Chips ── */
    .ai-suggestions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 4px 0 10px;
        animation: fadeIn .25s ease;
    }
    @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
    .ai-suggestion-chip {
        background: var(--muted, #f1f5f9);
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 20px;
        padding: 5px 12px;
        font-size: 0.72rem;
        font-weight: 500;
        color: var(--primary, #0369a1);
        cursor: pointer;
        transition: all .15s;
        line-height: 1.35;
        text-align: left;
    }
    .ai-suggestion-chip:hover {
        background: var(--primary, #0369a1);
        border-color: var(--primary, #0369a1);
        color: #fff;
        transform: translateY(-1px);
    }
    [data-theme="dark"] .ai-suggestion-chip {
        background: hsl(222,47%,16%);
        border-color: hsl(222,30%,25%);
        color: hsl(201,85%,60%);
    }
    [data-theme="dark"] .ai-suggestion-chip:hover {
        background: hsl(201,85%,30%);
        border-color: hsl(201,85%,40%);
        color: #fff;
    }

    /* ── AI message formatting ── */
    .message-text ol,
    .message-text ul {
        margin: 6px 0 4px 16px;
        padding: 0;
    }
    .message-text li {
        margin-bottom: 3px;
        line-height: 1.5;
    }
    .message-text strong { font-weight: 700; }
    .message-text em { font-style: italic; }
    .message-text p { margin: 0 0 6px; }
    .message-text br + br { display: none; } /* collapse double line breaks */
</style>

<!-- Top Status Bar -->
<div class="top-header">
    <div class="clinic-status">
        <span class="status-indicator"></span>
        <span class="status-text">Clinic Online</span>
        <span class="separator">•</span>
        <span class="current-time" id="currentTime"></span>
    </div>
</div>

<!-- Main Navbar -->
<nav class="navbar navbar-expand-lg main-navbar">
    <div class="container-fluid">
        <!-- Sidebar Toggle (Mobile) -->
        <button class="sidebar-toggle-btn d-lg-none" id="mobileSidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Brand -->
        <div class="navbar-brand-container">
            <img src="/assets/img/ICCLOGO.png" alt="SSCMS Logo" class="brand-logo" 
                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDUiIGhlaWdodD0iNDUiIHZpZXdCb3g9IjAgMCA0NSA0NSIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQ1IiBoZWlnaHQ9IjQ1IiByeD0iMTIiIGZpbGw9IiMwZjczYmEiLz4KPHBhdGggZD0iTTIyLjUgMTJjLTUuNSAwLTEwIDQuNS0xMCAxMHM0LjUgMTAgMTAgMTAgMTAtNC41IDEwLTEwLTQuNS0xMC0xMC0xMHptMCAxNWMtMi44IDAtNS0yLjItNS01czIuMi01IDUtNSA1IDIuMiA1IDUtMi4yIDUtNSA1eiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+'">
            <div class="brand-info">
                <h1 class="brand-title">Smart School Clinic Management System</h1>
                <span class="brand-subtitle">Immaculate Conception College of Balayan, Inc.</span>
            </div>
        </div>

        <!-- Spacer -->
        <div class="flex-grow-1"></div>

        <!-- Dark Mode Toggle -->
        <button class="dark-mode-toggle" id="darkModeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">
            <i class="fas fa-moon" id="darkModeIcon"></i>
        </button>

        <!-- Ask Nurse Angge Button - Minimal Design -->
        <button class="ask-ai-btn" id="askAiBtn" aria-label="Open Nurse Angge AI Assistant">
            <i class="fas fa-sparkles ask-ai-icon"></i>
            <span class="ask-ai-text">Ask</span>
        </button>

        <!-- Profile Section -->
        <div class="profile-section">
            <div class="profile-greeting">
                <span class="greeting-text"><?= $greeting ?></span>
                <span class="user-name"><?= $user_name ?></span>
            </div>
            <div class="profile-dropdown">
                <button class="profile-trigger" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="profile-avatar">
                        <?php if ($profile_picture): ?>
                            <img src="<?= $profile_picture ?>" alt="Profile" class="profile-image">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user-md"></i>
                            </div>
                        <?php endif; ?>
                        <div class="online-indicator"></div>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end profile-menu" aria-labelledby="profileDropdown">
                    <li class="profile-header">
                        <div class="profile-info">
                            <strong><?= $user_name ?></strong>
                            <small><?= $admin_category ?></small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/settings.php"><i class="fas fa-user-circle"></i> Manage Profile</a></li>
                    <li><a class="dropdown-item logout-item" href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="profile-image-container">
            <?php if ($profile_picture): ?>
                <img src="<?= $profile_picture ?>" alt="Profile" class="profile-image">
            <?php else: ?>
                <div class="avatar-placeholder">
                    <i class="fas fa-user-nurse"></i>
                </div>
            <?php endif; ?>
            <div class="status-dot"></div>
        </div>
        <p class="mb-0 fw-semibold"><?= $user_name ?></p>
        <small class="text-muted"><?= $admin_category ?></small>
    </div>

    <nav class="mt-0">
        <div class="nav-section">Main Menu</div>
        
        <a class="nav-link <?= $current_file === 'dashboard.php' ? 'active' : '' ?>" 
           href="/dashboard.php" 
           data-title="Dashboard">
            <div class="nav-icon">
                <i class="fas fa-home"></i>
            </div>
            <span>Dashboard</span>
        </a>

        <!-- Patient Management -->
        <a class="nav-link accordion-toggle <?= in_array($current_file, ['manage-patients.php', 'search-patient.php', 'pendings.php', 'log-new-patient.php']) ? 'active' : '' ?>" 
           href="#" 
           data-bs-toggle="collapse" 
           data-bs-target="#patientCareCollapse" 
           aria-expanded="<?= in_array($current_file, ['manage-patients.php', 'search-patient.php', 'pendings.php', 'log-new-patient.php']) ? 'true' : 'false' ?>" 
           aria-controls="patientCareCollapse"
           data-title="Patient Management">
            <div class="nav-icon">
                <i class="fas fa-user-injured"></i>
            </div>
            <span>Patient Management</span>
            <i class="fas fa-chevron-down accordion-icon"></i>
        </a>
        <div class="collapse <?= in_array($current_file, ['manage-patients.php', 'search-patient.php', 'pendings.php', 'log-new-patient.php']) ? 'show' : '' ?>" id="patientCareCollapse">
            <a class="nav-link nav-sublink <?= $current_file === 'manage-patients.php' ? 'active' : '' ?>" href="/patients/manage-patients.php">
                <div class="nav-icon"><i class="fas fa-user-edit"></i></div>
                <span>Manage Patients</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'search-patient.php' ? 'active' : '' ?>" href="/patients/search-patient.php">
                <div class="nav-icon"><i class="fas fa-search"></i></div>
                <span>Find Patients</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'pendings.php' ? 'active' : '' ?>" href="/patients/pendings.php">
                <div class="nav-icon"><i class="fas fa-clock"></i></div>
                <span>Pendings</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'log-new-patient.php' ? 'active' : '' ?>" href="/patients/log-new-patient.php">
                <div class="nav-icon"><i class="fas fa-notes-medical"></i></div>
                <span>Log Visit</span>
            </a>
        </div>

        <!-- Appointments -->
        <a class="nav-link accordion-toggle <?= in_array($current_file, ['new-appointment.php', 'set-appointment.php', 'appointment-list.php', 'todays-appointments.php']) ? 'active' : '' ?>" 
           href="#" 
           data-bs-toggle="collapse" 
           data-bs-target="#appointmentsCollapse" 
           aria-expanded="<?= in_array($current_file, ['new-appointment.php', 'set-appointment.php', 'appointment-list.php', 'todays-appointments.php']) ? 'true' : 'false' ?>" 
           aria-controls="appointmentsCollapse"
           data-title="Appointments">
            <div class="nav-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <span>Appointments</span>
            <i class="fas fa-chevron-down accordion-icon"></i>
        </a>
        <div class="collapse <?= in_array($current_file, ['new-appointment.php', 'set-appointment.php', 'appointment-list.php', 'todays-appointments.php']) ? 'show' : '' ?>" id="appointmentsCollapse">
            <a class="nav-link nav-sublink <?= $current_file === 'new-appointment.php' ? 'active' : '' ?>" href="/appointments/new-appointment.php">
                <div class="nav-icon"><i class="fas fa-calendar-plus"></i></div>
                <span>Request Appointment</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'set-appointment.php' ? 'active' : '' ?>" href="/appointments/set-appointment.php">
                <div class="nav-icon"><i class="fas fa-calendar-edit"></i></div>
                <span>Schedule Appointment</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'appointment-list.php' ? 'active' : '' ?>" href="/appointments/appointment-list.php">
                <div class="nav-icon"><i class="fas fa-list"></i></div>
                <span>Appointment Management</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'todays-appointments.php' ? 'active' : '' ?>" href="/appointments/todays-appointments.php">
                <div class="nav-icon"><i class="fas fa-calendar-day"></i></div>
                <span>Appointments Calendar</span>
            </a>
        </div>

        <!-- Specialist Visits -->
        <a class="nav-link accordion-toggle <?= in_array($current_file, ['specialist_calendar.php', 'schedule_specialist_visit.php']) ? 'active' : '' ?>" 
           href="#" 
           data-bs-toggle="collapse" 
           data-bs-target="#specialistsCollapse" 
           aria-expanded="<?= in_array($current_file, ['specialist_calendar.php', 'schedule_specialist_visit.php']) ? 'true' : 'false' ?>" 
           aria-controls="specialistsCollapse"
           data-title="Specialist Visits">
            <div class="nav-icon">
                <i class="fas fa-user-md"></i>
            </div>
            <span>Specialist Visits</span>
            <i class="fas fa-chevron-down accordion-icon"></i>
        </a>
        <div class="collapse <?= in_array($current_file, ['specialist_calendar.php', 'schedule_specialist_visit.php']) ? 'show' : '' ?>" id="specialistsCollapse">
            <a class="nav-link nav-sublink <?= $current_file === 'specialist_calendar.php' ? 'active' : '' ?>" href="/calendar/specialist_calendar.php">
                <div class="nav-icon"><i class="fas fa-calendar-week"></i></div>
                <span>Specialist Schedule</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'schedule_specialist_visit.php' ? 'active' : '' ?>" href="/calendar/schedule_specialist_visit.php">
                <div class="nav-icon"><i class="fas fa-calendar-plus"></i></div>
                <span>Book Specialist</span>
            </a>
        </div>

        <!-- Reports -->
        <a class="nav-link accordion-toggle <?= in_array($current_file, ['daily-reports.php', 'monthly-reports.php', 'graphs.php', 'calendar.php']) ? 'active' : '' ?>" 
           href="#" 
           data-bs-toggle="collapse" 
           data-bs-target="#reportsCollapse" 
           aria-expanded="<?= in_array($current_file, ['daily-reports.php', 'monthly-reports.php', 'graphs.php', 'calendar.php']) ? 'true' : 'false' ?>" 
           aria-controls="reportsCollapse"
           data-title="Reports">
            <div class="nav-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <span>Reports</span>
            <i class="fas fa-chevron-down accordion-icon"></i>
        </a>
        <div class="collapse <?= in_array($current_file, ['daily-reports.php', 'monthly-reports.php', 'graphs.php', 'calendar.php']) ? 'show' : '' ?>" id="reportsCollapse">
            <a class="nav-link nav-sublink <?= $current_file === 'daily-reports.php' ? 'active' : '' ?>" href="/reports/daily-reports.php">
                <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                <span>Daily Reports</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'monthly-reports.php' ? 'active' : '' ?>" href="/reports/monthly-reports.php">
                <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                <span>Monthly Reports</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'graphs.php' ? 'active' : '' ?>" href="/reports/graphs.php">
                <div class="nav-icon"><i class="fas fa-chart-pie"></i></div>
                <span>Graphs</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'calendar.php' ? 'active' : '' ?>" href="/calendar/calendar.php">
                <div class="nav-icon"><i class="fas fa-calendar-alt"></i></div>
                <span>Reports Calendar</span>
            </a>
        </div>

        <!-- Medicine Inventory -->
        <a class="nav-link accordion-toggle <?= in_array($current_file, ['add_new_medicine.php', 'update_stocks.php', 'view_batches.php', 'edit_medicine.php', 'request_medicine.php']) ? 'active' : '' ?>" 
           href="#" 
           data-bs-toggle="collapse" 
           data-bs-target="#medicineCollapse" 
           aria-expanded="<?= in_array($current_file, ['add_new_medicine.php', 'update_stocks.php', 'view_batches.php', 'edit_medicine.php', 'request_medicine.php']) ? 'true' : 'false' ?>" 
           aria-controls="medicineCollapse"
           data-title="Medicine Inventory">
            <div class="nav-icon">
                <i class="fas fa-capsules"></i>
            </div>
            <span>Medicine Inventory</span>
            <i class="fas fa-chevron-down accordion-icon"></i>
        </a>
        <div class="collapse <?= in_array($current_file, ['add_new_medicine.php', 'update_stocks.php', 'view_batches.php', 'edit_medicine.php', 'request_medicine.php']) ? 'show' : '' ?>" id="medicineCollapse">
            <a class="nav-link nav-sublink <?= $current_file === 'add_new_medicine.php' ? 'active' : '' ?>" href="/inventory/add_new_medicine.php">
                <div class="nav-icon"><i class="fas fa-capsules"></i></div>
                <span>Add Medicine</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'update_stocks.php' ? 'active' : '' ?>" href="/inventory/update_stocks.php">
                <div class="nav-icon"><i class="fas fa-boxes-stacked"></i></div>
                <span>Update Stocks</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'view_batches.php' ? 'active' : '' ?>" href="/inventory/view_batches.php">
                <div class="nav-icon"><i class="fas fa-warehouse"></i></div>
                <span>View Medicines</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'edit_medicine.php' ? 'active' : '' ?>" href="/inventory/edit_medicine.php">
                <div class="nav-icon"><i class="fas fa-edit"></i></div>
                <span>Edit Medicines</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'request_medicine.php' ? 'active' : '' ?>" href="/inventory/request_medicine.php">
                <div class="nav-icon"><i class="fas fa-hand-holding-medical"></i></div>
                <span>Dispense Medicine</span>
            </a>
        </div>

        <!-- Assets -->
        <a class="nav-link accordion-toggle <?= in_array($current_file, ['add_asset.php', 'asset_inventory.php', 'add_supply.php', 'supply_inventory.php']) ? 'active' : '' ?>" 
           href="#" 
           data-bs-toggle="collapse" 
           data-bs-target="#assetsCollapse" 
           aria-expanded="<?= in_array($current_file, ['add_asset.php', 'asset_inventory.php', 'add_supply.php', 'supply_inventory.php']) ? 'true' : 'false' ?>" 
           aria-controls="assetsCollapse"
           data-title="Assets">
            <div class="nav-icon">
                <i class="fas fa-box-open"></i>
            </div>
            <span>Assets</span>
            <i class="fas fa-chevron-down accordion-icon"></i>
        </a>
        <div class="collapse <?= in_array($current_file, ['add_asset.php', 'asset_inventory.php', 'add_supply.php', 'supply_inventory.php']) ? 'show' : '' ?>" id="assetsCollapse">
            <a class="nav-link nav-sublink <?= $current_file === 'add_asset.php' ? 'active' : '' ?>" href="/inventory/add_asset.php">
                <div class="nav-icon"><i class="fas fa-plus-square"></i></div>
                <span>Add Asset</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'asset_inventory.php' ? 'active' : '' ?>" href="/inventory/asset_inventory.php">
                <div class="nav-icon"><i class="fas fa-warehouse"></i></div>
                <span>Manage Assets</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'add_supply.php' ? 'active' : '' ?>" href="/inventory/add_supply.php">
                <div class="nav-icon"><i class="fas fa-plus-square"></i></div>
                <span>Add Supply</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'supply_inventory.php' ? 'active' : '' ?>" href="/inventory/supply_inventory.php">
                <div class="nav-icon"><i class="fas fa-warehouse"></i></div>
                <span>Manage Supply</span>
            </a>
        </div>

        <!-- Logs -->
        <a class="nav-link accordion-toggle <?= in_array($current_file, ['medicine_logs.php', 'stock_audit_logs.php', 'medicine_expiration.php']) ? 'active' : '' ?>" 
           href="#" 
           data-bs-toggle="collapse" 
           data-bs-target="#logsCollapse" 
           aria-expanded="<?= in_array($current_file, ['medicine_logs.php', 'stock_audit_logs.php', 'medicine_expiration.php']) ? 'true' : 'false' ?>" 
           aria-controls="logsCollapse"
           data-title="Logs">
            <div class="nav-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <span>Logs</span>
            <i class="fas fa-chevron-down accordion-icon"></i>
        </a>
        <div class="collapse <?= in_array($current_file, ['medicine_logs.php', 'stock_audit_logs.php', 'medicine_expiration.php']) ? 'show' : '' ?>" id="logsCollapse">
            <a class="nav-link nav-sublink <?= $current_file === 'medicine_logs.php' ? 'active' : '' ?>" href="/inventory/medicine_logs.php">
                <div class="nav-icon"><i class="fas fa-prescription"></i></div>
                <span>Medicine Usage</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'stock_audit_logs.php' ? 'active' : '' ?>" href="/inventory/stock_audit_logs.php">
                <div class="nav-icon"><i class="fas fa-history"></i></div>
                <span>Stock Audit</span>
            </a>
            <a class="nav-link nav-sublink <?= $current_file === 'medicine_expiration.php' ? 'active' : '' ?>" href="/inventory/medicine_expiration.php">
                <div class="nav-icon"><i class="fas fa-calendar-times"></i></div>
                <span>Expiration Logs</span>
            </a>
        </div>

        <div class="nav-section">System</div>
        
        <?php if ($is_superadmin): ?>
        <a class="nav-link <?= $current_file === 'manage_admin.php' ? 'active' : '' ?>" 
           href="/admin/manage_admin.php"
           data-title="Manage Users">
            <div class="nav-icon">
                <i class="fas fa-users-cog"></i>
            </div>
            <span>Manage Users</span>
        </a>
        <?php endif; ?>
        
        <a class="nav-link <?= $current_file === 'settings.php' ? 'active' : '' ?>" 
           href="/settings.php"
           data-title="Settings">
            <div class="nav-icon">
                <i class="fas fa-cog"></i>
            </div>
            <span>Settings</span>
        </a>
        
        <a class="nav-link" 
           href="/logout.php"
           data-title="Logout">
            <div class="nav-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <span>Logout</span>
        </a>
    </nav>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- AI Chat Module -->
<div class="ai-chat-overlay" id="aiChatOverlay"></div>
<div class="ai-chat-container" id="aiChatContainer">
    <!-- AI Chat Header -->
    <div class="ai-chat-header">
        <div class="ai-chat-title-section">
            <div class="ai-chat-icon">
                <img src="/assets/img/nurse_angge.png" alt="NA"
                     style="width:100%;height:100%;border-radius:10px;object-fit:cover;"
                     onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-user-nurse\'></i>'">
            </div>
            <div class="ai-chat-title-text">
                <h3>Nurse Angge</h3>
                <p style="opacity:.9;font-size:.75rem;display:flex;align-items:center;gap:.4rem;">
                    <i class="fas fa-circle" style="font-size:.45rem;color:#4ade80;"></i>
                    <span id="aiBackendLabel">AI Medical Assistant</span>
                </p>
            </div>
        </div>
        <div class="ai-chat-actions">
            <button class="ai-chat-end-btn" id="aiChatEndBtn" aria-label="End Chat">
                <i class="fas fa-redo"></i>
                <span>End Chat</span>
            </button>
            <button class="ai-chat-close-btn" id="aiChatCloseBtn" aria-label="Close AI Chat">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- AI Chat Messages -->
    <div class="ai-chat-messages" id="aiChatMessages">
        <div class="ai-welcome-message">
            <div class="ai-welcome-icon">
                <img src="/assets/img/nurse_angge.png" alt="Nurse Angge" 
                     style="width: 100%; height: 100%; border-radius: 20px; object-fit: cover;"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-hand-sparkles\'></i>'">
            </div>
            <h4>Hello, I'm Nurse Angge!</h4>
            <p>I'm your AI Assistant specializing in medical analysis for the Clinic Management System. I'm here to help with your health questions or any other topic, in English or Tagalog. How can I assist you today?</p>
        </div>
    </div>

    <!-- AI Chat Input -->
    <div class="ai-chat-input-container">
        <div class="ai-chat-input-wrapper">
            <textarea 
                class="ai-chat-input" 
                id="aiChatInput" 
               
                rows="1"
                aria-label="Chat message input"
                maxlength="500"></textarea>
            <button class="ai-chat-send-btn" id="aiChatSendBtn" aria-label="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <div class="char-counter" id="charCounter">0/500</div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[SSCMS] Initializing enhanced navigation with AI Chat');

        // Initialize all components
        initializeSidebar();
        initializeTime();
        initializeTooltips();
        initializeAIChat();

        console.log('[SSCMS] Navigation and AI Chat fully loaded');
    });

    // ============================================
    // SIDEBAR MANAGEMENT
    // ============================================
    function initializeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mobileToggle = document.getElementById('mobileSidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');

        if (!sidebar || !overlay) {
            console.error('[Sidebar] Missing sidebar or overlay elements');
            return;
        }

        function openSidebar() {
            sidebar.classList.add('show');
            overlay.classList.add('show');
            if (mobileToggle) mobileToggle.classList.add('active');
            mobileToggle && (mobileToggle.querySelector('i').className = 'fas fa-times');
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            if (mobileToggle) mobileToggle.classList.remove('active');
            mobileToggle && (mobileToggle.querySelector('i').className = 'fas fa-bars');
        }

        // Mobile Toggle - Show/Hide
        if (mobileToggle) {
            mobileToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
            });
        }

        // Overlay click - Close sidebar
        overlay.addEventListener('click', closeSidebar);

        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) closeSidebar();
        });

        // Close sidebar when a nav link is tapped on mobile
        sidebar.querySelectorAll('a.nav-link:not(.accordion-toggle)').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) closeSidebar();
            });
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth >= 992) closeSidebar();
            }, 250);
        });
    }

    // ============================================
    // REAL-TIME CLOCK (ACCURATE ACROSS PAGES)
    // ============================================
    function initializeTime() {
        const timeElement = document.getElementById('currentTime');
        
        if (!timeElement) {
            console.warn('[Time] Time element not found');
            return;
        }

        function updateTime() {
            const now = new Date();
            const options = {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            timeElement.textContent = now.toLocaleString('en-US', options);
        }

        // Update immediately
        updateTime();
        
        // Update every second for accuracy
        setInterval(updateTime, 1000);
        
        console.log('[Time] Clock initialized and updating every second');
    }

    // ============================================
    // TOOLTIP INITIALIZATION
    // ============================================
    function initializeTooltips() {
        // Tooltips are handled via CSS ::after pseudo-element
        console.log('[Tooltips] CSS-based tooltips initialized');
    }

    // ============================================
    // AI CHAT MODULE WITH NURSE ANGGE INTELLIGENCE
    // ============================================
    function initializeAIChat() {
        const askAiBtn = document.getElementById('askAiBtn');
        const aiChatContainer = document.getElementById('aiChatContainer');
        const aiChatOverlay = document.getElementById('aiChatOverlay');
        const aiChatCloseBtn = document.getElementById('aiChatCloseBtn');
        const aiChatInput = document.getElementById('aiChatInput');
        const aiChatSendBtn = document.getElementById('aiChatSendBtn');
        const aiChatMessages = document.getElementById('aiChatMessages');

        if (!askAiBtn || !aiChatContainer || !aiChatOverlay) {
            console.error('[AI Chat] Missing required elements');
            return;
        }

        // Chat history stored in memory
        let chatHistory = [];
        const maxChars = 500;

        // Fetch and display which AI backend is active
        function loadAIStatus() {
            const label = document.getElementById('aiBackendLabel');
            if (!label) return;
            fetch('/api/status.php')
                .then(r => r.json())
                .then(data => {
                    if (data.active_backend === 'local') {
                        label.textContent = 'Local AI · ' + (data.model ? data.model.replace('.gguf','') : 'llama.cpp');
                    } else {
                        label.textContent = 'Cloud AI · Groq';
                    }
                })
                .catch(() => { label.textContent = 'AI Medical Assistant'; });
        }

        // Open AI Chat
        function openAIChat() {
            aiChatContainer.classList.add('show');
            aiChatOverlay.classList.add('show');
            aiChatInput.focus();
            loadAIStatus();
            // Show starter suggestions if chat hasn't been used yet
            if (chatHistory.length === 0) {
                removeSuggestions();
                const starterChips = [
                    'I have a fever and headache', 'How do I manage stress?',
                    'First aid for wounds', 'How to book an appointment?', 'I feel anxious'
                ];
                const wrap = document.createElement('div');
                wrap.className = 'ai-suggestions';
                wrap.id = 'aiSuggestions';
                starterChips.forEach(function(s) {
                    const chip = document.createElement('button');
                    chip.className = 'ai-suggestion-chip';
                    chip.textContent = s;
                    chip.addEventListener('click', function() {
                        aiChatInput.value = s;
                        aiChatInput.dispatchEvent(new Event('input'));
                        sendMessage();
                    });
                    wrap.appendChild(chip);
                });
                aiChatMessages.appendChild(wrap);
                aiChatMessages.scrollTop = aiChatMessages.scrollHeight;
            }
        }

        // Close AI Chat
        function closeAIChat() {
            aiChatContainer.classList.remove('show');
            aiChatOverlay.classList.remove('show');
            console.log('[AI Chat] Closed');
        }

        // Event Listeners
        askAiBtn.addEventListener('click', openAIChat);
        aiChatCloseBtn.addEventListener('click', closeAIChat);
        aiChatOverlay.addEventListener('click', closeAIChat);

        // End Chat button
        const aiChatEndBtn = document.getElementById('aiChatEndBtn');
        if (aiChatEndBtn) {
            aiChatEndBtn.addEventListener('click', function() {
                // Clear chat history
                chatHistory = [];
                
                // Clear messages
                aiChatMessages.innerHTML = '';
                
                // Add welcome message back
                const welcomeDiv = document.createElement('div');
                welcomeDiv.className = 'ai-welcome-message';
                welcomeDiv.innerHTML = `
                    <div class="ai-welcome-icon">
                        <img src="/assets/img/nurse_angge.png" alt="Nurse Angge" 
                             style="width: 100%; height: 100%; border-radius: 20px; object-fit: cover;"
                             onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\\'fas fa-hand-sparkles\\'></i>'">
                    </div>
                    <h4>Hello, I'm Nurse Angge!</h4>
                    <p>I'm your AI Assistant specializing in medical analysis for the Clinic Management System. I'm here to help with your health questions or any other topic, in English or Tagalog. How can I assist you today?</p>
                `;
                aiChatMessages.appendChild(welcomeDiv);
                
                showNotification('Chat ended. Starting fresh!', 'success');
                console.log('[AI Chat] Conversation reset');
            });
        }

        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && aiChatContainer.classList.contains('show')) {
                closeAIChat();
            }
        });

        // Auto-resize textarea
        aiChatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            
            // Character count validation and display
            const length = this.value.length;
            const charCounter = document.getElementById('charCounter');
            
            if (charCounter) {
                charCounter.textContent = `${length}/500`;
                
                if (length > 450) {
                    charCounter.classList.add('danger');
                    charCounter.classList.remove('warning');
                } else if (length > 400) {
                    charCounter.classList.add('warning');
                    charCounter.classList.remove('danger');
                } else {
                    charCounter.classList.remove('warning', 'danger');
                }
            }
            
            if (length > maxChars) {
                this.value = this.value.substring(0, maxChars);
            }
        });

        // Send message on Enter (Shift+Enter for new line)
        aiChatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Send button click
        aiChatSendBtn.addEventListener('click', sendMessage);

        // Escape plain text for safe innerHTML insertion (user messages only)
        function escapeHtml(text) {
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Format user-typed plain text into safe HTML
        function formatUserText(text) {
            return escapeHtml(text).replace(/\n/g, '<br>');
        }

        // Send Message — routes through server-side process-message.php
        async function sendMessage() {
            const message = aiChatInput.value.trim();
            if (!message || message.length > maxChars) return;

            aiChatInput.value = '';
            aiChatInput.style.height = 'auto';

            const welcome = aiChatMessages.querySelector('.ai-welcome-message');
            if (welcome) welcome.remove();

            addMessage('user', message, false);
            chatHistory.push({ role: 'user', content: message });
            removeSuggestions();
            showTypingIndicator();
            aiChatSendBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('user_input', message);

                const response = await fetch('/assistant/process-message.php', {
                    method: 'POST',
                    body: formData
                });

                hideTypingIndicator();
                aiChatSendBtn.disabled = false;

                if (!response.ok) throw new Error('Server error ' + response.status);

                const result = await response.json();
                if (result.success) {
                    chatHistory.push({ role: 'assistant', content: result.message });
                    addMessage('ai', result.message, true);   // already HTML from server
                } else {
                    addMessage('ai', result.error || 'Something went wrong. Please try again.', true);
                }
            } catch (error) {
                console.error('[AI Chat] Error:', error);
                hideTypingIndicator();
                aiChatSendBtn.disabled = false;
                addMessage('ai', 'Oops! Could not reach the assistant. Please try again.', false);
            }
        }

        // Add Message to Chat
        // type = 'user' | 'ai'
        // isHtml = true means content is already server-formatted HTML (AI replies)
        function addMessage(type, text, isHtml) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message ${type}`;

            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';

            if (type === 'user') {
                const initials = '<?= mb_strtoupper(mb_substr($user_name, 0, 2)) ?>';
                avatar.textContent = initials || 'U';
            } else {
                avatar.innerHTML = '<img src="/assets/img/nurse_angge.png" alt="NA" style="width:100%;height:100%;border-radius:10px;object-fit:cover;" onerror="this.style.display=\'none\';this.parentElement.innerHTML=\'<i class=\\\'fas fa-user-nurse\\\'></i>\'">';
            }

            const content = document.createElement('div');
            content.className = 'message-content';

            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';

            const messageText = document.createElement('div');
            messageText.className = 'message-text';

            if (isHtml) {
                // Server already formatted — render as HTML directly
                messageText.innerHTML = text;
            } else {
                // User plain text — sanitize before rendering
                messageText.innerHTML = formatUserText(text);
            }

            bubble.appendChild(messageText);
            content.appendChild(bubble);

            const time = document.createElement('span');
            time.className = 'message-time';
            const now = new Date();
            time.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            content.appendChild(time);

            messageDiv.appendChild(avatar);
            messageDiv.appendChild(content);

            aiChatMessages.appendChild(messageDiv);
            aiChatMessages.scrollTop = aiChatMessages.scrollHeight;

            // Show suggestion chips after each AI reply
            if (type === 'ai') renderSuggestions();
        }

        // Contextual suggestion chips shown after each AI reply
        const SUGGESTION_SETS = [
            ['What are common signs of dengue?', 'How do I lower a high fever?', 'When should I see a doctor?'],
            ['Tips for managing stress', 'What vitamins should students take?', 'How much sleep do I need?'],
            ['First aid for wounds', 'What is normal blood pressure?', 'How to prevent colds?'],
            ['Signs of dehydration', 'Foods good for immunity', 'How to handle a panic attack?'],
            ['What is the clinic schedule?', 'How to book an appointment?', 'Who can I contact for emergencies?'],
        ];
        let suggestionIdx = 0;

        function renderSuggestions() {
            removeSuggestions();
            const set = SUGGESTION_SETS[suggestionIdx % SUGGESTION_SETS.length];
            suggestionIdx++;

            const wrap = document.createElement('div');
            wrap.className = 'ai-suggestions';
            wrap.id = 'aiSuggestions';

            set.forEach(function(s) {
                const chip = document.createElement('button');
                chip.className = 'ai-suggestion-chip';
                chip.textContent = s;
                chip.addEventListener('click', function() {
                    aiChatInput.value = s;
                    aiChatInput.dispatchEvent(new Event('input'));
                    sendMessage();
                });
                wrap.appendChild(chip);
            });

            aiChatMessages.appendChild(wrap);
            aiChatMessages.scrollTop = aiChatMessages.scrollHeight;
        }

        function removeSuggestions() {
            const existing = document.getElementById('aiSuggestions');
            if (existing) existing.remove();
        }

        // Show Typing Indicator
        function showTypingIndicator() {
            const typingDiv = document.createElement('div');
            typingDiv.className = 'typing-indicator';
            typingDiv.id = 'typingIndicator';

            typingDiv.innerHTML = `
                <div class="message-avatar">
                    <img src="/assets/img/nurse_angge.png" alt="Nurse Angge" 
                         style="width: 100%; height: 100%; border-radius: 10px; object-fit: cover;"
                         onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-robot\'></i>'">
                </div>
                <div class="typing-dots">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            `;

            aiChatMessages.appendChild(typingDiv);
            aiChatMessages.scrollTop = aiChatMessages.scrollHeight;
        }

        // Hide Typing Indicator
        function hideTypingIndicator() {
            const typingIndicator = document.getElementById('typingIndicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
        }

        // Show Notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `ai-notification ${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: calc(var(--top-bar-height) + var(--header-height) + 20px);
                right: 20px;
                padding: 12px 20px;
                border-radius: 10px;
                color: white;
                font-size: 0.875rem;
                font-weight: 500;
                z-index: 9999;
                animation: slideInRight 0.3s ease, slideOutRight 0.3s ease 2.5s forwards;
                box-shadow: var(--shadow-lg);
            `;
            
            if (type === 'success') {
                notification.style.background = 'var(--accent)';
            } else {
                notification.style.background = 'var(--destructive)';
            }
            
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        console.log('[AI Chat] Nurse Angge module initialized with Groq API');
    }

    // ============================================
    // SMOOTH SCROLL FOR ANCHOR LINKS
    // ============================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // ============================================
    // ACTIVE LINK HIGHLIGHTING ON SCROLL
    // ============================================
    function highlightActiveSection() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === `#${entry.target.id}`) {
                            link.classList.add('active');
                        }
                    });
                }
            });
        }, { threshold: 0.5 });

        sections.forEach(section => observer.observe(section));
    }

    // Initialize section highlighting if needed
    if (document.querySelectorAll('section[id]').length > 0) {
        highlightActiveSection();
    }

    // ── Dark Mode ──
    (function() {
        const toggle = document.getElementById('darkModeToggle');
        const icon   = document.getElementById('darkModeIcon');
        const html   = document.documentElement;

        function applyTheme(dark) {
            html.setAttribute('data-theme', dark ? 'dark' : 'light');
            if (icon) {
                icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
            }
            if (toggle) {
                toggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
                toggle.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
            }
            // Broadcast so charts/pages can re-render
            document.dispatchEvent(new CustomEvent('sscms:themechange', { detail: { dark } }));
        }

        // Load saved preference (default: light)
        const saved = localStorage.getItem('sscms_theme') || 'light';
        applyTheme(saved === 'dark');

        if (toggle) {
            toggle.addEventListener('click', function() {
                const isDark = html.getAttribute('data-theme') === 'dark';
                const next   = !isDark;
                localStorage.setItem('sscms_theme', next ? 'dark' : 'light');
                applyTheme(next);
            });
        }
    })();

    // ── Breadcrumbs (referrer-aware) ──
    (function() {
        const TRAIL_KEY = 'sscms_bc_trail';

        // Page label + icon map
        const PAGE_NAMES = {
            'index.php':                 ['Dashboard',           'fa-house-chimney'],
            'dashboard.php':             ['Dashboard',           'fa-house-chimney'],
            'manage-patients.php':       ['Manage Patients',     'fa-users'],
            'view_patient_info.php':     ['Patient Details',     'fa-user'],
            'edit_patient.php':          ['Edit Patient',        'fa-user-pen'],
            'log-visit.php':             ['Log Visit',           'fa-notes-medical'],
            'log-new-patient.php':       ['New Patient',         'fa-user-plus'],
            'log-patient.php':           ['Log Patient',         'fa-notes-medical'],
            'search-patient.php':        ['Find Patient',        'fa-magnifying-glass'],
            'patient_health_report.php': ['Health Records',      'fa-file-medical'],
            'recent-visits.php':         ['Recent Visits',       'fa-clock-rotate-left'],
            'pendings.php':              ['Pending Patients',    'fa-hourglass-half'],
            'view_pending_patient.php':  ['View Pending',        'fa-user-clock'],
            'edit_pendings.php':         ['Edit Pending',        'fa-user-pen'],
            'forms.php':                 ['Patient Forms',       'fa-file-lines'],
            'manage_teachers.php':       ['Manage Teachers',     'fa-chalkboard-user'],
            'teacher_form.php':          ['Teacher Form',        'fa-user-tie'],
            'daily-reports.php':         ['Daily Reports',       'fa-calendar-day'],
            'monthly-reports.php':       ['Monthly Reports',     'fa-calendar-check'],
            'graphs.php':                ['Analytics',           'fa-chart-line'],
            'settings.php':              ['Settings',            'fa-gear'],
            'profile.php':               ['Profile',             'fa-circle-user'],
            'manage-admins.php':         ['Manage Admins',       'fa-user-shield'],
            'advisers.php':              ['Advisers',            'fa-id-badge'],
            'sections.php':              ['Sections',            'fa-layer-group'],
            'levels.php':                ['Levels',              'fa-stairs'],
            'contacts.php':              ['Contacts',            'fa-address-book'],
            'active-users.php':          ['Active Users',        'fa-users-gear'],
            'medicine_logs.php':         ['Medicine Logs',       'fa-pills'],
            'medicine_expiration.php':   ['Expiration Tracker',  'fa-triangle-exclamation'],
            'stock_audit_logs.php':      ['Stock Audit',         'fa-clipboard-list'],
            'dispose_history.php':       ['Dispose History',     'fa-trash-can'],
            'view_batches.php':          ['View Batches',        'fa-boxes-stacked'],
            'edit_medicine.php':         ['Edit Medicine',       'fa-pen-to-square'],
            'request_medicine.php':      ['Request Medicine',    'fa-hand-holding-medical'],
            'assistant.php':             ['AI Assistant',        'fa-robot'],
            'appointments.php':          ['Appointments',        'fa-calendar-check'],
        };

        // Fallback folder parents used when there is no referrer
        const FOLDER_FALLBACK = {
            'patients':  { label: 'Manage Patients',  icon: 'fa-users',       href: '/patients/manage-patients.php' },
            'reports':   { label: 'Reports',          icon: 'fa-chart-bar',   href: '/reports/daily-reports.php'    },
            'settings':  { label: 'Settings',         icon: 'fa-gear',        href: '/settings.php'                 },
            'inventory': { label: 'Inventory',        icon: 'fa-kit-medical', href: '/inventory/view_medicine_stock.php' },
            'assistant': { label: 'AI Assistant',     icon: 'fa-robot',       href: '/assistant/assistant.php'      },
        };

        // Pages that are "roots" — clicking them resets the trail
        const ROOT_PAGES = new Set([
            'index.php', 'dashboard.php',
            'manage-patients.php', 'search-patient.php',
            'recent-visits.php', 'pendings.php',
            'daily-reports.php', 'monthly-reports.php', 'graphs.php',
            'settings.php', 'manage_teachers.php',
        ]);

        function getFile(urlStr) {
            try {
                var p = new URL(urlStr).pathname;
                return p.split('/').pop().split('?')[0] || 'index.php';
            } catch(e) {
                return urlStr.split('/').pop().split('?')[0] || 'index.php';
            }
        }

        function isSameHost(url) {
            try { return new URL(url).host === location.host; } catch(e) { return false; }
        }

        function loadTrail() {
            try { return JSON.parse(sessionStorage.getItem(TRAIL_KEY) || '[]'); } catch(e) { return []; }
        }

        function saveTrail(trail) {
            try { sessionStorage.setItem(TRAIL_KEY, JSON.stringify(trail)); } catch(e) {}
        }

        function updateTrail() {
            var currFile = getFile(location.href);
            var currMeta = PAGE_NAMES[currFile];

            // On dashboard or unknown page — wipe trail
            if (!currMeta || currFile === 'index.php' || currFile === 'dashboard.php') {
                saveTrail([]);
                return;
            }

            var trail = loadTrail();
            var ref   = document.referrer && isSameHost(document.referrer) ? document.referrer : null;
            var refFile = ref ? getFile(ref) : null;

            // If the current page is already somewhere in the trail, the user
            // navigated "back" — trim the trail to just before that entry.
            var currIdx = trail.findIndex(function(t) { return t.file === currFile; });
            if (currIdx !== -1) {
                trail = trail.slice(0, currIdx);
                saveTrail(trail);
                return;
            }

            // Root pages reset the trail (they are top-level destinations)
            if (ROOT_PAGES.has(currFile)) {
                saveTrail([]);
                return;
            }

            // We're going deeper. Push the referrer onto the trail if it's
            // a known page and not already the last entry.
            if (refFile && PAGE_NAMES[refFile] && refFile !== 'index.php' && refFile !== 'dashboard.php') {
                var lastFile = trail.length ? trail[trail.length - 1].file : null;

                if (lastFile !== refFile) {
                    // Check if referrer is somewhere earlier in the trail (branch switch)
                    var refIdx = trail.findIndex(function(t) { return t.file === refFile; });
                    if (refIdx !== -1) {
                        trail = trail.slice(0, refIdx + 1);
                    } else {
                        trail.push({ file: refFile, href: ref, label: PAGE_NAMES[refFile][0], icon: PAGE_NAMES[refFile][1] });
                    }
                } else {
                    // Same referrer already on trail — update its href (query string may differ)
                    trail[trail.length - 1].href = ref;
                }
            }

            saveTrail(trail);
        }

        function buildCrumbs() {
            var currFile = getFile(location.href);
            var currMeta = PAGE_NAMES[currFile];

            if (!currMeta || currFile === 'index.php' || currFile === 'dashboard.php') return null;

            var trail = loadTrail();
            var items = [{ label: 'Dashboard', icon: 'fa-house-chimney', href: '/index.php' }];

            // Add trail entries (skip any that equal the current file)
            trail.forEach(function(t) {
                if (t.file !== currFile) {
                    items.push({ label: t.label, icon: t.icon, href: t.href });
                }
            });

            // If trail is empty (direct navigation / root), add folder fallback
            if (trail.length === 0) {
                var folder = location.pathname.replace(/^\/SSCMS\/?/, '').split('/')[0];
                var fb = FOLDER_FALLBACK[folder];
                if (fb && fb.label !== currMeta[0]) {
                    items.push(fb);
                }
            }

            // Add current page as the last (non-linked) crumb
            items.push({ label: currMeta[0], icon: currMeta[1], href: null });

            return items.length > 1 ? items : null;
        }

        function renderCrumbs(items) {
            var nav = document.createElement('nav');
            nav.className = 'sscms-breadcrumb-nav';
            nav.setAttribute('aria-label', 'breadcrumb');
            var ol = document.createElement('ol');
            ol.className = 'sscms-breadcrumb';

            items.forEach(function(item, i) {
                var li  = document.createElement('li');
                var isLast = i === items.length - 1;
                li.className = 'sscms-bc-item' + (isLast ? ' active' : '');

                if (!isLast) {
                    var a = document.createElement('a');
                    a.href = item.href;
                    a.innerHTML = '<i class="fas ' + item.icon + '"></i> ' + item.label;
                    li.appendChild(a);
                    var sep = document.createElement('i');
                    sep.className = 'fas fa-chevron-right sscms-bc-sep';
                    li.appendChild(sep);
                } else {
                    li.innerHTML = '<i class="fas ' + item.icon + '"></i> ' + item.label;
                }
                ol.appendChild(li);
            });

            nav.appendChild(ol);
            return nav;
        }

        // Update trail first (before DOMContentLoaded, so it's synchronous)
        updateTrail();

        document.addEventListener('DOMContentLoaded', function() {
            var crumbs = buildCrumbs();
            if (!crumbs) return;
            var nav = renderCrumbs(crumbs);

            var target = document.querySelector('.content main .container-fluid') ||
                         document.querySelector('.content main') ||
                         document.querySelector('.content');
            if (target) target.insertBefore(nav, target.firstChild);
        });
    })();
</script>

<?php
// Flush output buffer
ob_end_flush();
?>