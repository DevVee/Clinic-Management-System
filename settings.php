<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Settings] Unauthorized access: " . (isset($_SESSION['user_id']) ? "user_id: {$_SESSION['user_id']}" : "no session"));
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = [];

// Define categories
$categories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Faculty and Staff', 'Alumni'];
sort($categories);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX responses
function send_json_response($success, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Fetch current user data
try {
    $stmt = $conn->prepare("SELECT name, email, admin_category, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current_user) {
        error_log("[SSCMS Settings] User not found: user_id=$user_id");
        send_json_response(false, 'User not found.');
    }
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch user error: " . $e->getMessage());
    send_json_response(false, 'Failed to fetch user data.');
}

// Fetch clinic contact numbers and guard contact
try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('clinic_contact_1', 'clinic_contact_2', 'guard_contact')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $clinic_contact_1 = $settings['clinic_contact_1'] ?? '0917 123 4567';
    $clinic_contact_2 = $settings['clinic_contact_2'] ?? '0928 765 4321';
    $guard_contact = $settings['guard_contact'] ?? '09123456789';
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch settings error: " . $e->getMessage());
    $clinic_contact_1 = '0917 123 4567';
    $clinic_contact_2 = '0928 765 4321';
    $guard_contact = '09123456789';
}

// Fetch program sections and grade years
try {
    $stmt = $conn->query("SELECT id, name, category FROM program_sections ORDER BY category, name");
    $program_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group sections by category
    $grouped_sections = [];
    foreach ($program_sections as $section) {
        $category = $section['category'];
        if (!isset($grouped_sections[$category])) {
            $grouped_sections[$category] = [];
        }
        $grouped_sections[$category][] = $section;
    }
    ksort($grouped_sections);
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch sections error: " . $e->getMessage());
    $grouped_sections = [];
}

try {
    $stmt = $conn->query("SELECT id, name, category FROM grade_years ORDER BY category, name");
    $grade_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group years by category
    $grouped_years = [];
    foreach ($grade_years as $year) {
        $category = $year['category'];
        if (!isset($grouped_years[$category])) {
            $grouped_years[$category] = [];
        }
        $grouped_years[$category][] = $year;
    }
    ksort($grouped_years);
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch years error: " . $e->getMessage());
    $grade_years = [];
}

// Fetch advisers
try {
    $stmt = $conn->query("SELECT a.id, ps.name AS program_section, gy.name AS grade_year, a.adviser_name, a.contact_number, a.program_section_id, a.grade_year_id 
                          FROM advisers a 
                          JOIN program_sections ps ON a.program_section_id = ps.id 
                          JOIN grade_years gy ON a.grade_year_id = gy.id 
                          ORDER BY ps.category, ps.name, gy.name");
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group advisers by program section category
    $grouped_advisers = [];
    foreach ($advisers as $adviser) {
        $category = $adviser['program_section'];
        if (!isset($grouped_advisers[$category])) {
            $grouped_advisers[$category] = [];
        }
        $grouped_advisers[$category][] = $adviser;
    }
    ksort($grouped_advisers);
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch advisers error: " . $e->getMessage());
    $grouped_advisers = [];
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $admin_category = filter_input(INPUT_POST, 'admin_category', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

        if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Name and valid email are required.');
        }
        if (!$admin_category || !in_array($admin_category, ['Nurse', 'Clinic Staff', 'Doctor', 'Dentist'])) {
            throw new Exception('Invalid admin category.');
        }

        // Handle profile picture upload
        $profile_picture = $current_user['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file_type = mime_content_type($_FILES['profile_picture']['tmp_name']);
            $file_size = $_FILES['profile_picture']['size'];

            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Only JPEG, PNG, or GIF images are allowed.');
            }
            if ($file_size > $max_size) {
                throw new Exception('Image size must be less than 2MB.');
            }

            $upload_dir = 'Uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $target = $upload_dir . $filename;

            if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
                throw new Exception('Failed to upload profile picture.');
            }
            $profile_picture = '/' . $target;

            if ($current_user['profile_picture'] && file_exists(substr($current_user['profile_picture'], 1))) {
                unlink(substr($current_user['profile_picture'], 1));
            }
        }

        // Update user
        $query = "UPDATE users SET name = ?, email = ?, admin_category = ?, profile_picture = ?, updated_at = NOW()";
        $params = [$name, $email, $admin_category, $profile_picture];
        if ($password) {
            $query .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $query .= " WHERE id = ?";
        $params[] = $user_id;

        $stmt = $conn->prepare($query);
        $stmt->execute($params);

        // Refresh session
        $_SESSION['user_name'] = $name;
        $_SESSION['admin_category'] = $admin_category;
        $_SESSION['profile_picture'] = $profile_picture;

        send_json_response(true, 'Profile updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Profile update error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Update Contact Numbers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_contacts') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $contact_1 = filter_input(INPUT_POST, 'clinic_contact_1', FILTER_SANITIZE_STRING);
        $contact_2 = filter_input(INPUT_POST, 'clinic_contact_2', FILTER_SANITIZE_STRING);
        $guard_contact = filter_input(INPUT_POST, 'guard_contact', FILTER_SANITIZE_STRING);

        if (!$contact_1 || !preg_match('/^(\+63|0)9\d{9}$/', $contact_1)) {
            throw new Exception('Invalid first contact number.');
        }
        if (!$contact_2 || !preg_match('/^(\+63|0)9\d{9}$/', $contact_2)) {
            throw new Exception('Invalid second contact number.');
        }
        if (!$guard_contact || !preg_match('/^(\+63|0)9\d{9}$/', $guard_contact)) {
            throw new Exception('Invalid guard contact number.');
        }

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->execute(['clinic_contact_1', $contact_1, $contact_1]);
        $stmt->execute(['clinic_contact_2', $contact_2, $contact_2]);
        $stmt->execute(['guard_contact', $guard_contact, $guard_contact]);

        send_json_response(true, 'Contact numbers updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Contact update error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Add Program Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_section') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $name = filter_input(INPUT_POST, 'section_name', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'section_category', FILTER_SANITIZE_STRING);

        if (!$name || strlen($name) > 100) {
            throw new Exception('Section name is required and must be 100 characters or less.');
        }
        if (!$category || !in_array($category, $categories)) {
            throw new Exception('Invalid section category.');
        }

        $stmt = $conn->prepare("INSERT INTO program_sections (name, category) VALUES (?, ?)");
        $stmt->execute([$name, $category]);
        send_json_response(true, 'Program section added successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Add section error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Edit Program Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_section') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $edit_id = filter_input(INPUT_POST, 'edit_section_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'edit_section_name', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'edit_section_category', FILTER_SANITIZE_STRING);

        if (!$edit_id || !$name || strlen($name) > 100) {
            throw new Exception('Section name is required and must be 100 characters or less.');
        }
        if (!$category || !in_array($category, $categories)) {
            throw new Exception('Invalid section category.');
        }

        $stmt = $conn->prepare("UPDATE program_sections SET name = ?, category = ? WHERE id = ?");
        $stmt->execute([$name, $category, $edit_id]);
        send_json_response(true, 'Program section updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Edit section error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Delete Program Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_section') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $delete_id = filter_input(INPUT_POST, 'delete_section_id', FILTER_VALIDATE_INT);
        if (!$delete_id) {
            throw new Exception('Invalid section ID.');
        }

        $stmt = $conn->prepare("DELETE FROM program_sections WHERE id = ?");
        $stmt->execute([$delete_id]);
        send_json_response(true, 'Program section deleted successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Delete section error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Add Grade Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_year') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $name = filter_input(INPUT_POST, 'year_name', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'year_category', FILTER_SANITIZE_STRING);

        if (!$name || strlen($name) > 50) {
            throw new Exception('Grade year name is required and must be 50 characters or less.');
        }
        if (!$category || !in_array($category, $categories)) {
            throw new Exception('Invalid grade year category.');
        }

        $stmt = $conn->prepare("INSERT INTO grade_years (name, category) VALUES (?, ?)");
        $stmt->execute([$name, $category]);
        send_json_response(true, 'Grade year added successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Add year error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Edit Grade Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_year') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $edit_id = filter_input(INPUT_POST, 'edit_year_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'edit_year_name', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'edit_year_category', FILTER_SANITIZE_STRING);

        if (!$edit_id || !$name || strlen($name) > 50) {
            throw new Exception('Grade year name is required and must be 50 characters or less.');
        }
        if (!$category || !in_array($category, $categories)) {
            throw new Exception('Invalid grade year category.');
        }

        $stmt = $conn->prepare("UPDATE grade_years SET name = ?, category = ? WHERE id = ?");
        $stmt->execute([$name, $category, $edit_id]);
        send_json_response(true, 'Grade year updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Edit year error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Delete Grade Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_year') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $delete_id = filter_input(INPUT_POST, 'delete_year_id', FILTER_VALIDATE_INT);
        if (!$delete_id) {
            throw new Exception('Invalid grade year ID.');
        }

        $stmt = $conn->prepare("DELETE FROM grade_years WHERE id = ?");
        $stmt->execute([$delete_id]);
        send_json_response(true, 'Grade year deleted successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Delete year error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Add Adviser
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_adviser') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $program_section_id = filter_input(INPUT_POST, 'program_section_id', FILTER_VALIDATE_INT);
        $grade_year_id = filter_input(INPUT_POST, 'grade_year_id', FILTER_VALIDATE_INT);
        $adviser_name = filter_input(INPUT_POST, 'adviser_name', FILTER_SANITIZE_STRING);
        $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);

        if (!$program_section_id || !$grade_year_id || !$adviser_name || !$contact_number) {
            throw new Exception('All fields are required.');
        }
        if (!preg_match('/^(\+63|0)9\d{9}$/', $contact_number)) {
            throw new Exception('Invalid contact number. Use a valid Philippine mobile number (e.g., 09171234567 or +639171234567).');
        }

        $stmt = $conn->prepare("SELECT id FROM advisers WHERE program_section_id = ? AND grade_year_id = ?");
        $stmt->execute([$program_section_id, $grade_year_id]);
        if ($stmt->fetch()) {
            throw new Exception('Adviser for this program section and grade year already exists.');
        }

        $stmt = $conn->prepare("INSERT INTO advisers (program_section_id, grade_year_id, adviser_name, contact_number, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$program_section_id, $grade_year_id, $adviser_name, $contact_number]);
        send_json_response(true, 'Adviser added successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Add adviser error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Edit Adviser
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_adviser') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $edit_id = filter_input(INPUT_POST, 'edit_adviser_id', FILTER_VALIDATE_INT);
        $program_section_id = filter_input(INPUT_POST, 'edit_program_section_id', FILTER_VALIDATE_INT);
        $grade_year_id = filter_input(INPUT_POST, 'edit_grade_year_id', FILTER_VALIDATE_INT);
        $adviser_name = filter_input(INPUT_POST, 'edit_adviser_name', FILTER_SANITIZE_STRING);
        $contact_number = filter_input(INPUT_POST, 'edit_contact_number', FILTER_SANITIZE_STRING);

        if (!$edit_id || !$program_section_id || !$grade_year_id || !$adviser_name || !$contact_number) {
            throw new Exception('All fields are required.');
        }
        if (!preg_match('/^(\+63|0)9\d{9}$/', $contact_number)) {
            throw new Exception('Invalid contact number. Use a valid Philippine mobile number (e.g., 09171234567 or +639171234567).');
        }

        $stmt = $conn->prepare("SELECT id FROM advisers WHERE program_section_id = ? AND grade_year_id = ? AND id != ?");
        $stmt->execute([$program_section_id, $grade_year_id, $edit_id]);
        if ($stmt->fetch()) {
            throw new Exception('Adviser for this program section and grade year already exists.');
        }

        $stmt = $conn->prepare("UPDATE advisers SET program_section_id = ?, grade_year_id = ?, adviser_name = ?, contact_number = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$program_section_id, $grade_year_id, $adviser_name, $contact_number, $edit_id]);
        send_json_response(true, 'Adviser updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Edit adviser error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Delete Adviser
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_adviser') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $delete_id = filter_input(INPUT_POST, 'delete_adviser_id', FILTER_VALIDATE_INT);
        if (!$delete_id) {
            throw new Exception('Invalid adviser ID.');
        }

        $stmt = $conn->prepare("DELETE FROM advisers WHERE id = ?");
        $stmt->execute([$delete_id]);
        send_json_response(true, 'Adviser deleted successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Settings] Delete adviser error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Fetch all users with last active status and profile picture
try {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.admin_category, u.profile_picture, s.last_active
        FROM users u
        LEFT JOIN sessions s ON u.id = s.user_id
        ORDER BY u.admin_category, u.name
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group users by admin_category
    $grouped_users = [];
    foreach ($users as $user) {
        $category = $user['admin_category'];
        if (!isset($grouped_users[$category])) {
            $grouped_users[$category] = [];
        }
        $grouped_users[$category][] = $user;
    }
    ksort($grouped_users);
} catch (Exception $e) {
    error_log("[SSCMS Settings] Fetch users error: " . $e->getMessage());
    $grouped_users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Settings">
    <meta name="author" content="ICCB">
    <title>Settings - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./css/settings.css">
    <?php include 'includes/logo.php'; ?>

    <style>
        /* Center success modal */
        #successModal .modal-dialog {
            display: flex;
            align-items: center;
            min-height: calc(100vh - 1rem);
            margin: 0 auto;
        }
        #successModal .modal-content {
            text-align: center;
            border-radius: 10px;
        }
        #successModal .modal-header {
            background-color: #d4edda;
            color: #155724;
            justify-content: center;
            border-bottom: none;
        }
        #successModal .modal-title {
            font-weight: bold;
        }
        #successModal .modal-body {
            padding: 20px;
            font-size: 1.1rem;
        }
        #successModal .btn-primary {
            background-color: #007bff;
            border: none;
            padding: 8px 20px;
        }
        /* Active/Inactive status text */
        .status-active-text {
            color: #28a745;
            font-weight: 500;
        }
        .status-inactive-text {
            color: #6c757d;
            font-weight: 500;
        }
        /* Category header in table */
        .category-header {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        /* Profile picture in users table */
        .user-profile-pic {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            vertical-align: middle;
        }
        .user-profile-placeholder {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
        }
        /* Profile picture preview */
        .profile-picture-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-top: 10px;
        }
        .profile-picture-preview.bg-light {
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* Fix tab label visibility */
        .nav-tabs .nav-link {
            color: #495057; /* Default text color for inactive tabs */
        }
        .nav-tabs .nav-link.active {
            color: #ffffff; /* White text for active tab */
            background-color: #0284c7; /* Primary color for active tab */
            border-color: #0284c7;
        }
        .nav-tabs .nav-link:hover {
            color: #ffffff;
            background-color: #0369a1; /* Darker primary for hover */
        }
    </style>
</head>
<body>
    <?php include 'includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <h1 class="dashboard-title fade-in">
                    <i class="fas fa-cog"></i>
                    System Settings
                </h1>
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Settings</li>
                    </ol>
                </nav>

                <!-- Toast Container for Errors -->
                <div class="toast-container position-fixed bottom-0 end-0 p-3">
                    <div id="settingsToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="toast-header">
                            <strong class="me-auto">Error</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body text-danger"></div>
                    </div>
                </div>

                <!-- Success Modal -->
                <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="successModalLabel">Success</h5>
                            </div>
                            <div class="modal-body" id="successModalBody">
                                <!-- Dynamic success message -->
                            </div>
                            <div class="modal-footer justify-content-center">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Okay</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3 fade-in" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">My Profile</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="active-users-tab" data-bs-toggle="tab" data-bs-target="#active-users" type="button" role="tab" aria-controls="active-users" aria-selected="false">Users</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab" aria-controls="contacts" aria-selected="false">Clinic Contacts</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button" role="tab" aria-controls="sections" aria-selected="false">Program Sections</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="years-tab" data-bs-toggle="tab" data-bs-target="#years" type="button" role="tab" aria-controls="years" aria-selected="false">Grade Years</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="advisers-tab" data-bs-toggle="tab" data-bs-target="#advisers" type="button" role="tab" aria-controls="advisers" aria-selected="false">Advisers</button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabContent">
                    <!-- My Profile -->
                    <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-user-circle"></i> Edit Profile
                            </div>
                            <div class="card-body">
                                <form id="profileForm" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_profile">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label for="name" class="form-label">Name</label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($current_user['name']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($current_user['email']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label for="admin_category" class="form-label">Role</label>
                                        <select class="form-select" id="admin_category" name="admin_category" required>
                                            <option value="Nurse" <?= $current_user['admin_category'] === 'Nurse' ? 'selected' : '' ?>>Nurse</option>
                                            <option value="Clinic Staff" <?= $current_user['admin_category'] === 'Clinic Staff' ? 'selected' : '' ?>>Clinic Staff</option>
                                            <option value="Doctor" <?= $current_user['admin_category'] === 'Doctor' ? 'selected' : '' ?>>Doctor</option>
                                            <option value="Dentist" <?= $current_user['admin_category'] === 'Dentist' ? 'selected' : '' ?>>Dentist</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label for="password" class="form-label">New Password (optional)</label>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current">
                                    </div>
                                    <div class="mb-2">
                                        <label for="profile_picture" class="form-label">Profile Picture</label>
                                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                                        <?php if ($current_user['profile_picture']): ?>
                                            <img src="<?= htmlspecialchars($current_user['profile_picture']) ?>" alt="Profile Picture" class="profile-picture-preview">
                                        <?php else: ?>
                                            <div class="profile-picture-preview bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-user-md" style="color: var(--primary); font-size: 1.5rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Clinic Contacts -->
                    <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-phone"></i> Manage Clinic Contact Numbers
                            </div>
                            <div class="card-body">
                                <form id="contactsForm">
                                    <input type="hidden" name="action" value="update_contacts">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label for="clinic_contact_1" class="form-label">Clinic Contact Number 1</label>
                                            <input type="text" class="form-control" id="clinic_contact_1" name="clinic_contact_1" value="<?= htmlspecialchars($clinic_contact_1) ?>" pattern="(\+63|0)9\d{9}" required>
                                            <div class="form-text">Enter a valid Philippine mobile number (e.g., 09171234567 or +639171234567)</div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label for="clinic_contact_2" class="form-label">Clinic Contact Number 2</label>
                                            <input type="text" class="form-control" id="clinic_contact_2" name="clinic_contact_2" value="<?= htmlspecialchars($clinic_contact_2) ?>" pattern="(\+63|0)9\d{9}" required>
                                            <div class="form-text">Enter a valid Philippine mobile number (e.g., 09287654321 or +639287654321)</div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label for="guard_contact" class="form-label">Guard Contact Number</label>
                                            <input type="text" class="form-control" id="guard_contact" name="guard_contact" value="<?= htmlspecialchars($guard_contact) ?>" pattern="(\+63|0)9\d{9}" required>
                                            <div class="form-text">Enter a valid Philippine mobile number (e.g., 09123456789 or +639123456789)</div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Users -->
                    <div class="tab-pane fade" id="active-users" role="tabpanel" aria-labelledby="active-users-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-users"></i> All Users
                            </div>
                            <div class="card-body">
                                <?php if (empty($grouped_users)): ?>
                                    <p class="text-muted">No users found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Profile</th>
                                                    <th>Status</th>
                                                    <th>Name</th>
                                                    <th>Role</th>
                                                    <th>Last Active</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($grouped_users as $category => $users): ?>
                                                    <tr class="category-header">
                                                        <td colspan="5"><?= htmlspecialchars($category) ?></td>
                                                    </tr>
                                                    <?php foreach ($users as $user): ?>
                                                        <?php
                                                        $is_active = !empty($user['last_active']) && strtotime($user['last_active']) >= (time() - 1800); // 30 minutes
                                                        $last_active = !empty($user['last_active']) ? date('Y-m-d H:i:s', strtotime($user['last_active'])) : 'Never';
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <?php if ($user['profile_picture']): ?>
                                                                    <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user['name']) ?>" class="user-profile-pic">
                                                                <?php else: ?>
                                                                    <div class="user-profile-placeholder">
                                                                        <i class="fas fa-user-md"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($is_active): ?>
                                                                    <span class="status-active-text">Active Now</span>
                                                                <?php else: ?>
                                                                    <span class="status-inactive-text">Not Active</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($user['name']) ?></td>
                                                            <td><?= htmlspecialchars($user['admin_category']) ?></td>
                                                            <td><?= htmlspecialchars($last_active) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Program Sections -->
                    <div class="tab-pane fade" id="sections" role="tabpanel" aria-labelledby="sections-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-book"></i> Add Program Section
                            </div>
                            <div class="card-body">
                                <form id="addSectionForm">
                                    <input type="hidden" name="action" value="add_section">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="mb-2">
                                        <label for="section_name" class="form-label">Section Name</label>
                                        <input type="text" class="form-control" id="section_name" name="section_name" maxlength="100" required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="section_category" class="form-label">Category</label>
                                        <select class="form-select" id="section_category" name="section_category" required>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
                                </form>
                            </div>
                        </div>
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-book"></i> Existing Sections
                            </div>
                            <div class="card-body">
                                <?php if (empty($grouped_sections)): ?>
                                    <p class="text-muted">No program sections found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Category</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($grouped_sections as $category => $sections): ?>
                                                    <tr class="category-header">
                                                        <td colspan="3"><?= htmlspecialchars($category) ?></td>
                                                    </tr>
                                                    <?php foreach ($sections as $section): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($section['name']) ?></td>
                                                            <td><?= htmlspecialchars($category) ?></td>
                                                            <td>
                                                                <button class="btn btn-primary btn-sm edit-section-btn" data-id="<?= $section['id'] ?>" data-name="<?= htmlspecialchars($section['name']) ?>" data-category="<?= htmlspecialchars($category) ?>" data-bs-toggle="modal" data-bs-target="#editSectionModal"><i class="fas fa-edit"></i></button>
                                                                <form class="d-inline delete-section-form" style="display:inline;">
                                                                    <input type="hidden" name="action" value="delete_section">
                                                                    <input type="hidden" name="delete_section_id" value="<?= $section['id'] ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Grade Years -->
                    <div class="tab-pane fade" id="years" role="tabpanel" aria-labelledby="years-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-graduation-cap"></i> Add Grade Year
                            </div>
                            <div class="card-body">
                                <form id="addYearForm">
                                    <input type="hidden" name="action" value="add_year">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="mb-2">
                                        <label for="year_name" class="form-label">Grade Year Name</label>
                                        <input type="text" class="form-control" id="year_name" name="year_name" maxlength="50" required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="year_category" class="form-label">Category</label>
                                        <select class="form-select" id="year_category" name="year_category" required>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
                                </form>
                            </div>
                        </div>
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-graduation-cap"></i> Existing Grade Years
                            </div>
                            <div class="card-body">
                                <?php if (empty($grouped_years)): ?>
                                    <p class="text-muted">No grade years found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Category</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($grouped_years as $category => $years): ?>
                                                    <tr class="category-header">
                                                        <td colspan="3"><?= htmlspecialchars($category) ?></td>
                                                    </tr>
                                                    <?php foreach ($years as $year): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($year['name']) ?></td>
                                                            <td><?= htmlspecialchars($category) ?></td>
                                                            <td>
                                                                <button class="btn btn-primary btn-sm edit-year-btn" data-id="<?= $year['id'] ?>" data-name="<?= htmlspecialchars($year['name']) ?>" data-category="<?= htmlspecialchars($category) ?>" data-bs-toggle="modal" data-bs-target="#editYearModal"><i class="fas fa-edit"></i></button>
                                                                <form class="d-inline delete-year-form" style="display:inline;">
                                                                    <input type="hidden" name="action" value="delete_year">
                                                                    <input type="hidden" name="delete_year_id" value="<?= $year['id'] ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Advisers -->
                    <div class="tab-pane fade" id="advisers" role="tabpanel" aria-labelledby="advisers-tab">
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-chalkboard-teacher"></i> Add Adviser
                            </div>
                            <div class="card-body">
                                <form id="addAdviserForm">
                                    <input type="hidden" name="action" value="add_adviser">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="mb-2">
                                        <label for="program_section_id" class="form-label">Program Section</label>
                                        <select class="form-select" id="program_section_id" name="program_section_id" required>
                                            <option value="">Select Program Section</option>
                                            <?php foreach ($program_sections as $section): ?>
                                                <option value="<?= $section['id'] ?>"><?= htmlspecialchars($section['name']) ?> (<?= htmlspecialchars($section['category']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label for="grade_year_id" class="form-label">Grade Year</label>
                                        <select class="form-select" id="grade_year_id" name="grade_year_id" required>
                                            <option value="">Select Grade Year</option>
                                            <?php foreach ($grade_years as $year): ?>
                                                <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['name']) ?> (<?= htmlspecialchars($year['category']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label for="adviser_name" class="form-label">Adviser Name</label>
                                        <input type="text" class="form-control" id="adviser_name" name="adviser_name" maxlength="255" required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="contact_number" class="form-label">Contact Number</label>
                                        <input type="text" class="form-control" id="contact_number" name="contact_number" maxlength="50" pattern="(\+63|0)9\d{9}" required>
                                        <div class="form-text">Enter a valid Philippine mobile number (e.g., 09171234567 or +639171234567)</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
                                </form>
                            </div>
                        </div>
                        <div class="card fade-in">
                            <div class="card-header">
                                <i class="fas fa-chalkboard-teacher"></i> Existing Advisers
                            </div>
                            <div class="card-body">
                                <?php if (empty($grouped_advisers)): ?>
                                    <p class="text-muted">No advisers found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Program Section</th>
                                                    <th>Grade Year</th>
                                                    <th>Adviser Name</th>
                                                    <th>Contact Number</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($grouped_advisers as $category => $advisers): ?>
                                                    <tr class="category-header">
                                                        <td colspan="5"><?= htmlspecialchars($category) ?></td>
                                                    </tr>
                                                    <?php foreach ($advisers as $adviser): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($adviser['program_section']) ?></td>
                                                            <td><?= htmlspecialchars($adviser['grade_year']) ?></td>
                                                            <td><?= htmlspecialchars($adviser['adviser_name']) ?></td>
                                                            <td><?= htmlspecialchars($adviser['contact_number']) ?></td>
                                                            <td>
                                                                <button class="btn btn-primary btn-sm edit-adviser-btn" 
                                                                        data-id="<?= $adviser['id'] ?>" 
                                                                        data-program-section-id="<?= $adviser['program_section_id'] ?>" 
                                                                        data-grade-year-id="<?= $adviser['grade_year_id'] ?>" 
                                                                        data-adviser-name="<?= htmlspecialchars($adviser['adviser_name']) ?>" 
                                                                        data-contact-number="<?= htmlspecialchars($adviser['contact_number']) ?>" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#editAdviserModal"><i class="fas fa-edit"></i></button>
                                                                <form class="d-inline delete-adviser-form" style="display:inline;">
                                                                    <input type="hidden" name="action" value="delete_adviser">
                                                                    <input type="hidden" name="delete_adviser_id" value="<?= $adviser['id'] ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <footer class="fade-in">
            <div class="container-fluid">
                <div class="text-muted">
                    <i class="fas fa-hospital me-1"></i>
                    IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. © SSCMS 2025
                </div>
            </div>
        </footer>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSectionModalLabel">Edit Program Section</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editSectionForm">
                        <input type="hidden" name="action" value="edit_section">
                        <input type="hidden" name="edit_section_id" id="edit_section_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-2">
                            <label for="edit_section_name" class="form-label">Section Name</label>
                            <input type="text" class="form-control" id="edit_section_name" name="edit_section_name" maxlength="100" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_section_category" class="form-label">Category</label>
                            <select class="form-select" id="edit_section_category" name="edit_section_category" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Year Modal -->
    <div class="modal fade" id="editYearModal" tabindex="-1" aria-labelledby="editYearModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editYearModalLabel">Edit Grade Year</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editYearForm">
                        <input type="hidden" name="action" value="edit_year">
                        <input type="hidden" name="edit_year_id" id="edit_year_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-2">
                            <label for="edit_year_name" class="form-label">Grade Year Name</label>
                            <input type="text" class="form-control" id="edit_year_name" name="edit_year_name" maxlength="50" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_year_category" class="form-label">Category</label>
                            <select class="form-select" id="edit_year_category" name="edit_year_category" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Adviser Modal -->
    <div class="modal fade" id="editAdviserModal" tabindex="-1" aria-labelledby="editAdviserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAdviserModalLabel">Edit Adviser</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAdviserForm">
                        <input type="hidden" name="action" value="edit_adviser">
                        <input type="hidden" name="edit_adviser_id" id="edit_adviser_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-2">
                            <label for="edit_program_section_id" class="form-label">Program Section</label>
                            <select class="form-select" id="edit_program_section_id" name="edit_program_section_id" required>
                                <option value="">Select Program Section</option>
                                <?php foreach ($program_sections as $section): ?>
                                    <option value="<?= $section['id'] ?>"><?= htmlspecialchars($section['name']) ?> (<?= htmlspecialchars($section['category']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="edit_grade_year_id" class="form-label">Grade Year</label>
                            <select class="form-select" id="edit_grade_year_id" name="edit_grade_year_id" required>
                                <option value="">Select Grade Year</option>
                                <?php foreach ($grade_years as $year): ?>
                                    <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['name']) ?> (<?= htmlspecialchars($year['category']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="edit_adviser_name" class="form-label">Adviser Name</label>
                            <input type="text" class="form-control" id="edit_adviser_name" name="edit_adviser_name" maxlength="255" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_contact_number" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="edit_contact_number" name="edit_contact_number" maxlength="50" pattern="(\+63|0)9\d{9}" required>
                            <div class="form-text">Enter a valid Philippine mobile number (e.g., 09171234567 or +639171234567)</div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[SSCMS Settings] Initialized');

            const toastEl = document.getElementById('settingsToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });

            const successModal = new bootstrap.Modal(document.getElementById('successModal'), { backdrop: 'static' });
            const successModalBody = document.getElementById('successModalBody');

            // Categories for consistency
            const categories = <?php echo json_encode($categories); ?>;

            // Tab persistence
            const tabs = document.querySelectorAll('#settingsTabs .nav-link');
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function() {
                    sessionStorage.setItem('activeTab', this.id);
                }, { once: true });
            });

            // Restore active tab on load
            const activeTabId = sessionStorage.getItem('activeTab') || 'profile-tab';
            const activeTab = document.getElementById(activeTabId);
            if (activeTab) {
                const tab = new bootstrap.Tab(activeTab);
                tab.show();
            }

            // Generic form handler
            function handleFormSubmit(form, url, successMessage, isMultipart = false, requiresConfirmation = false) {
                // Remove existing listeners to prevent duplicates
                form.removeEventListener('submit', form._submitHandler);
                form._submitHandler = function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (requiresConfirmation && !confirm('Are you sure you want to delete this item?')) {
                        return;
                    }

                    if (!form.checkValidity()) {
                        toastBody.textContent = 'Please fill in all required fields correctly.';
                        toast.show();
                        form.classList.add('was-validated');
                        return;
                    }

                    const data = isMultipart ? new FormData(form) : $(form).serialize();
                    $.ajax({
                        url: url,
                        method: 'POST',
                        data: data,
                        processData: !isMultipart,
                        contentType: isMultipart ? false : 'application/x-www-form-urlencoded',
                        dataType: 'json',
                        success: function(response) {
                            console.log('[SSCMS Settings] AJAX Success:', response);
                            if (response.success) {
                                successModalBody.textContent = response.message || successMessage;
                                successModal.show();

                                if (form.id === 'profileForm') {
                                    const profileDropdown = document.querySelector('#profileDropdown span');
                                    const sidebarHeader = document.querySelector('.sidebar-header p');
                                    const sidebarRole = document.querySelector('.sidebar-header small');
                                    if (profileDropdown) profileDropdown.textContent = form.querySelector('[name="name"]').value;
                                    if (sidebarHeader) sidebarHeader.textContent = form.querySelector('[name="name"]').value;
                                    if (sidebarRole) sidebarRole.textContent = form.querySelector('[name="admin_category"]').value;
                                } else if (form.id !== 'editSectionForm' && form.id !== 'editYearForm' && form.id !== 'editAdviserForm') {
                                    form.reset();
                                    form.classList.remove('was-validated');
                                }

                                setTimeout(() => {
                                    successModal.hide();
                                    if (form.id !== 'contactsForm') {
                                        location.reload();
                                    }
                                }, 1000);
                            } else {
                                toastBody.textContent = response.message || 'Operation failed.';
                                toast.show();
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('[SSCMS Settings] AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                            toastBody.textContent = 'Error: ' + textStatus;
                            toast.show();
                        }
                    });
                };
                form.addEventListener('submit', form._submitHandler);
            }

            // Profile Form
            handleFormSubmit(document.getElementById('profileForm'), '/settings.php', 'Profile updated successfully!', true);

            // Contacts Form
            handleFormSubmit(document.getElementById('contactsForm'), '/settings.php', 'Contact numbers updated successfully!');

            // Add Section Form
            handleFormSubmit(document.getElementById('addSectionForm'), '/settings.php', 'Section added successfully!');

            // Edit Section Form
            const editSectionButtons = document.querySelectorAll('.edit-section-btn');
            editSectionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit_section_id').value = this.dataset.id;
                    document.getElementById('edit_section_name').value = this.dataset.name;
                    document.getElementById('edit_section_category').value = this.dataset.category;
                }, { once: true });
            });
            handleFormSubmit(document.getElementById('editSectionForm'), '/settings.php', 'Section updated successfully!');

            // Delete Section Forms
            const deleteSectionForms = document.querySelectorAll('.delete-section-form');
            deleteSectionForms.forEach(form => {
                handleFormSubmit(form, '/settings.php', 'Section deleted successfully!', false, true);
            });

            // Add Year Form
            handleFormSubmit(document.getElementById('addYearForm'), '/settings.php', 'Grade year added successfully!');

            // Edit Year Form
            const editYearButtons = document.querySelectorAll('.edit-year-btn');
            editYearButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit_year_id').value = this.dataset.id;
                    document.getElementById('edit_year_name').value = this.dataset.name;
                    document.getElementById('edit_year_category').value = this.dataset.category;
                }, { once: true });
            });
            handleFormSubmit(document.getElementById('editYearForm'), '/settings.php', 'Grade year updated successfully!');

            // Delete Year Forms
            const deleteYearForms = document.querySelectorAll('.delete-year-form');
            deleteYearForms.forEach(form => {
                handleFormSubmit(form, '/settings.php', 'Grade year deleted successfully!', false, true);
            });

            // Add Adviser Form
            handleFormSubmit(document.getElementById('addAdviserForm'), '/settings.php', 'Adviser added successfully!');

            // Edit Adviser Form
            const editAdviserButtons = document.querySelectorAll('.edit-adviser-btn');
            editAdviserButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit_adviser_id').value = this.dataset.id;
                    document.getElementById('edit_program_section_id').value = this.dataset.programSectionId;
                    document.getElementById('edit_grade_year_id').value = this.dataset.gradeYearId;
                    document.getElementById('edit_adviser_name').value = this.dataset.adviserName;
                    document.getElementById('edit_contact_number').value = this.dataset.contactNumber;
                }, { once: true });
            });
            handleFormSubmit(document.getElementById('editAdviserForm'), '/settings.php', 'Adviser updated successfully!');

            // Delete Adviser Forms
            const deleteAdviserForms = document.querySelectorAll('.delete-adviser-form');
            deleteAdviserForms.forEach(form => {
                handleFormSubmit(form, '/settings.php', 'Adviser deleted successfully!', false, true);
            });
        });
    </script>
</body>
</html>