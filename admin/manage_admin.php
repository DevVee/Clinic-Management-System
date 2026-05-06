<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    error_log("[SSCMS Manage Admin] Unauthorized access: " . (isset($_SESSION['user_id']) ? "user_id: {$_SESSION['user_id']}" : "no session"));
    header('Location: /login.php');
    exit;
}

// Restrict access to SuperAdmin only (case-insensitive)
if (strcasecmp($_SESSION['role'], 'SuperAdmin') !== 0) {
    error_log("[SSCMS Manage Admin] Access denied for user_id: {$_SESSION['user_id']}, role: {$_SESSION['role']}");
    header('Location: /dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];

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

// Handle Create Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $name = filter_input(INPUT_POST, 'new_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'new_email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'new_password', FILTER_SANITIZE_STRING);
        $plain_password = $password; // Store plain password as per schema
        $admin_category = filter_input(INPUT_POST, 'new_admin_category', FILTER_SANITIZE_STRING);
        $role = filter_input(INPUT_POST, 'new_role', FILTER_SANITIZE_STRING);

        if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$password || !$admin_category || !$role) {
            throw new Exception('All fields are required.');
        }
        if (!in_array($admin_category, ['Nurse', 'Clinic Staff', 'Doctor', 'Dentist'])) {
            throw new Exception('Invalid admin category.');
        }
        if (!in_array($role, ['Admin', 'SuperAdmin'])) {
            throw new Exception('Invalid role.');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists.');
        }

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, plain_password, admin_category, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $plain_password, $admin_category, $role]);
        send_json_response(true, 'Admin created successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Manage Admin] Create admin error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Edit Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_admin') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $edit_id = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'edit_email', FILTER_SANITIZE_EMAIL);
        $admin_category = filter_input(INPUT_POST, 'edit_admin_category', FILTER_SANITIZE_STRING);
        $role = filter_input(INPUT_POST, 'edit_role', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'edit_password', FILTER_SANITIZE_STRING);
        $plain_password = $password ?: null; // Store plain password if provided

        if (!$edit_id || !$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$admin_category || !$role) {
            throw new Exception('All fields are required.');
        }
        if (!in_array($admin_category, ['Nurse', 'Clinic Staff', 'Doctor', 'Dentist'])) {
            throw new Exception('Invalid admin category.');
        }
        if (!in_array($role, ['Admin', 'SuperAdmin'])) {
            throw new Exception('Invalid role.');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $edit_id]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists.');
        }

        $query = "UPDATE users SET name = ?, email = ?, admin_category = ?, role = ?, updated_at = NOW()";
        $params = [$name, $email, $admin_category, $role];
        if ($password) {
            $query .= ", password = ?, plain_password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
            $params[] = $plain_password;
        }
        $query .= " WHERE id = ?";
        $params[] = $edit_id;

        $stmt = $conn->prepare($query);
        $stmt->execute($params);

        send_json_response(true, 'Admin updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Manage Admin] Edit admin error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Delete Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
        if (!$delete_id || $delete_id == $user_id) {
            throw new Exception('Cannot delete own account or invalid ID.');
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        send_json_response(true, 'Admin deleted successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Manage Admin] Delete admin error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Handle Reset Admin Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_admin_password') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $reset_id = filter_input(INPUT_POST, 'reset_id', FILTER_VALIDATE_INT);
        if (!$reset_id || $reset_id == $user_id) {
            throw new Exception('Cannot reset own password or invalid ID.');
        }

        $temp_password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ?, plain_password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashed_password, $temp_password, $reset_id]);

        error_log("[SSCMS Manage Admin] Password reset for user_id=$reset_id by admin_id=$user_id");
        send_json_response(true, 'Password reset successfully!', ['temp_password' => $temp_password]);
    } catch (Exception $e) {
        error_log("[SSCMS Manage Admin] Reset password error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Fetch all admins
try {
    $stmt = $conn->query("SELECT id, name, email, admin_category, role FROM users");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Manage Admin] Fetch admins error: " . $e->getMessage());
    $admins = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Manage Admins">
    <meta name="author" content="ICCB">
    <title>Manage Admins - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
      <?php include '../includes/sscmslogo.php'; ?>

    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --sidebar-width: 220px;
            --top-bar-height: 35px;
            --header-height: 60px;
            --primary-medical: #0f73ba;
            --secondary-medical: #2c7be5;
            --accent-green: #00c851;
            --accent-red: #ff4444;
            --background-primary: #f8fafc;
            --background-secondary: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --transition-fast: 0.15s ease;
        }

        body {
            padding-top: 1.5rem;
            padding-left: var(--sidebar-width);
        }

        @media (max-width: 768px) {
            body {
                padding-left: 0;
            }
        }

        .content {
            padding: 1.5rem;
        }

        .dashboard-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .custom-breadcrumb .breadcrumb {
            background: transparent;
            padding: 0;
        }

        .custom-breadcrumb .breadcrumb-item a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .custom-breadcrumb .breadcrumb-item.active {
            color: var(--text-primary);
        }

        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-medical), var(--secondary-medical));
            color: white;
            font-weight: 500;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .form-control, .form-select {
            border-radius: 0.375rem;
            border: 1px solid var(--border-light);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-medical);
            box-shadow: 0 0 0 3px rgba(15, 115, 186, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-medical), var(--secondary-medical));
            border: none;
            border-radius: 0.375rem;
            transition: var(--transition-fast);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0d5a94, var(--primary-medical));
            transform: translateY(-1px);
        }

        .btn-warning, .btn-danger {
            border-radius: 0.375rem;
            transition: var(--transition-fast);
        }

        .btn-warning:hover, .btn-danger:hover {
            transform: translateY(-1px);
        }

        .modal-content {
            border-radius: 0.5rem;
            border: none;
        }

        .modal-header {
            border-bottom: none;
        }

        .toast-container {
            z-index: 1050;
        }

        footer {
            margin-top: 2rem;
            padding: 1rem 0;
            border-top: 1px solid var(--border-light);
            text-align: center;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>
    <div class="content">
        <main>
            <div class="container-fluid">
                <h1 class="dashboard-title fade-in"><i class="fas fa-user-cog"></i> Manage Admins</h1>
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Manage Admins</li>
                    </ol>
                </nav>

                <div class="toast-container position-fixed bottom-0 end-0 p-2">
                    <div id="settingsToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="toast-header">
                            <strong class="me-auto">Notification</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body"></div>
                    </div>
                </div>

                <div class="card fade-in">
                    <div class="card-header"><i class="fas fa-user-plus"></i> Create New Admin</div>
                    <div class="card-body">
                        <form id="createAdminForm">
                            <input type="hidden" name="action" value="create_admin">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label for="new_name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="new_name" name="new_name" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="new_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="new_email" name="new_email" required>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label for="new_password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-2">
                                <label for="new_admin_category" class="form-label">Category</label>
                                <select class="form-select" id="new_admin_category" name="new_admin_category" required>
                                    <option value="Nurse">Nurse</option>
                                    <option value="Clinic Staff">Clinic Staff</option>
                                    <option value="Doctor">Doctor</option>
                                    <option value="Dentist">Dentist</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="new_role" class="form-label">Role</label>
                                <select class="form-select" id="new_role" name="new_role" required>
                                    <option value="Admin">Admin</option>
                                    <option value="SuperAdmin">SuperAdmin</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create</button>
                        </form>
                    </div>
                </div>

                <div class="card fade-in">
                    <div class="card-header"><i class="fas fa-user-edit"></i> Manage Existing Admins</div>
                    <div class="card-body">
                        <?php if (empty($admins)): ?>
                            <p class="text-muted">No admin accounts found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Category</th>
                                            <th>Role</th>
                                            <th>Password</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($admins as $admin): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($admin['name']) ?></td>
                                                <td><?= htmlspecialchars($admin['email']) ?></td>
                                                <td><?= htmlspecialchars($admin['admin_category']) ?></td>
                                                <td><?= htmlspecialchars($admin['role']) ?></td>
                                                <td>
                                                    <?php if ($admin['id'] != $user_id): ?>
                                                        <button class="btn btn-warning btn-sm reset-password-btn" data-id="<?= $admin['id'] ?>" data-name="<?= htmlspecialchars($admin['name']) ?>" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"><i class="fas fa-key"></i> Reset</button>
                                                    <?php else: ?>
                                                        <span class="text-muted">Own account</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-primary btn-sm edit-admin-btn" data-id="<?= $admin['id'] ?>" data-name="<?= htmlspecialchars($admin['name']) ?>" data-email="<?= htmlspecialchars($admin['email']) ?>" data-admin_category="<?= htmlspecialchars($admin['admin_category']) ?>" data-role="<?= htmlspecialchars($admin['role']) ?>" data-bs-toggle="modal" data-bs-target="#editAdminModal"><i class="fas fa-edit"></i></button>
                                                    <?php if ($admin['id'] != $user_id): ?>
                                                        <form class="d-inline delete-admin-form" style="display:inline;">
                                                            <input type="hidden" name="action" value="delete_admin">
                                                            <input type="hidden" name="delete_id" value="<?= $admin['id'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
        <footer class="fade-in">
            <div class="container-fluid">
                <div class="text-muted"><i class="fas fa-hospital me-1"></i> IMMACULATE CONCEPTION COLLEGE OF BALAYAN, INC. © SSCMS 2025</div>
            </div>
        </footer>
    </div>

    <div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAdminModalLabel">Edit Admin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAdminForm">
                        <input type="hidden" name="action" value="edit_admin">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-2">
                            <label for="edit_name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="edit_name" name="edit_name" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="edit_email" required>
                        </div>
                        <div class="mb-2">
                            <label for="edit_admin_category" class="form-label">Category</label>
                            <select class="form-select" id="edit_admin_category" name="edit_admin_category" required>
                                <option value="Nurse">Nurse</option>
                                <option value="Clinic Staff">Clinic Staff</option>
                                <option value="Doctor">Doctor</option>
                                <option value="Dentist">Dentist</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="edit_role" required>
                                <option value="Admin">Admin</option>
                                <option value="SuperAdmin">SuperAdmin</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="edit_password" class="form-label">New Password (optional)</label>
                            <input type="password" class="form-control" id="edit_password" name="edit_password" placeholder="Leave blank to keep current">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Reset password for <strong id="reset-admin-name"></strong>?</p>
                    <p class="text-muted">A temporary password will be generated. The admin must change it after logging in.</p>
                    <form id="resetPasswordForm">
                        <input type="hidden" name="action" value="reset_admin_password">
                        <input type="hidden" name="reset_id" id="reset_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Reset Password</button>
                    </form>
                    <div id="temp-password-container" class="mt-2 d-none">
                        <p><strong>Temporary Password:</strong> <code id="temp-password"></code></p>
                        <p class="text-muted">Copy this password and share it securely with the admin.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[SSCMS Manage Admin] Initialized');

            const toastEl = document.getElementById('settingsToast');
            const toastBody = toastEl.querySelector('.toast-body');
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });

            function handleFormSubmit(form, url, successMessage) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!form.checkValidity()) {
                        e.stopPropagation();
                        toastBody.textContent = 'Please fill in all required fields correctly.';
                        toastBody.style.color = 'var(--accent-red)';
                        toast.show();
                        form.classList.add('was-validated');
                        return;
                    }

                    const data = $(form).serialize();
                    $.ajax({
                        url: url,
                        method: 'POST',
                        data: data,
                        dataType: 'json',
                        success: function(response) {
                            console.log('[SSCMS Manage Admin] AJAX Success:', response);
                            if (response.success) {
                                toastBody.textContent = response.message || successMessage;
                                toastBody.style.color = 'var(--accent-green)';
                                toast.show();
                                if (form.id === 'resetPasswordForm') {
                                    document.getElementById('temp-password').textContent = response.temp_password;
                                    document.getElementById('temp-password-container').classList.remove('d-none');
                                } else if (form.id !== 'editAdminForm') {
                                    form.reset();
                                    form.classList.remove('was-validated');
                                    setTimeout(() => location.reload(), 1000);
                                }
                            } else {
                                toastBody.textContent = response.message || 'Operation failed.';
                                toastBody.style.color = 'var(--accent-red)';
                                toast.show();
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('[SSCMS Manage Admin] AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                            toastBody.textContent = 'Error: ' + textStatus;
                            toastBody.style.color = 'var(--accent-red)';
                            toast.show();
                        }
                    });
                });
            }

            handleFormSubmit(document.getElementById('createAdminForm'), '/admin/manage_admin.php', 'Admin created successfully!');
            handleFormSubmit(document.getElementById('editAdminForm'), '/admin/manage_admin.php', 'Admin updated successfully!');
            handleFormSubmit(document.getElementById('resetPasswordForm'), '/admin/manage_admin.php', 'Password reset successfully!');

            const editButtons = document.querySelectorAll('.edit-admin-btn');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit_id').value = this.dataset.id;
                    document.getElementById('edit_name').value = this.dataset.name;
                    document.getElementById('edit_email').value = this.dataset.email;
                    document.getElementById('edit_admin_category').value = this.dataset.admin_category;
                    document.getElementById('edit_role').value = this.dataset.role;
                });
            });

            const resetButtons = document.querySelectorAll('.reset-password-btn');
            resetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('reset_id').value = this.dataset.id;
                    document.getElementById('reset-admin-name').textContent = this.dataset.name;
                    document.getElementById('temp-password-container').classList.add('d-none');
                });
            });

            const deleteForms = document.querySelectorAll('.delete-admin-form');
            deleteForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to delete this admin?')) return;
                    handleFormSubmit(form, '/admin/manage_admin.php', 'Admin deleted successfully!')();
                });
            });
        });
    </script>
</body>
</html>