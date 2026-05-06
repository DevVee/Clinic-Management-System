<?php
// Handle Create Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token.');
        }

        $name = filter_input(INPUT_POST, 'new_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'new_email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'new_password', FILTER_SANITIZE_STRING);
        $admin_category = filter_input(INPUT_POST, 'new_admin_category', FILTER_SANITIZE_STRING);

        if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$password || !$admin_category) {
            throw new Exception('All fields are required for new admin.');
        }
        if (!in_array($admin_category, ['Nurse', 'Clinic Staff', 'Doctor', 'Dentist'])) {
            throw new Exception('Invalid admin category.');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists.');
        }

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, admin_category, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $admin_category]);
        send_json_response(true, 'Admin account created successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Manage Admins] Create error: " . $e->getMessage());
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
        $password = filter_input(INPUT_POST, 'edit_password', FILTER_SANITIZE_STRING);

        if (!$edit_id || !$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$admin_category) {
            throw new Exception('All fields are required for editing admin.');
        }
        if (!in_array($admin_category, ['Nurse', 'Clinic Staff', 'Doctor', 'Dentist'])) {
            throw new Exception('Invalid admin category.');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $edit_id]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists.');
        }

        $query = "UPDATE users SET name = ?, email = ?, admin_category = ?, updated_at = NOW()";
        $params = [$name, $email, $admin_category];
        if ($password) {
            $query .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $query .= " WHERE id = ?";
        $params[] = $edit_id;

        $stmt = $conn->prepare($query);
        $stmt->execute($params);

        send_json_response(true, 'Admin account updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Manage Admins] Edit error: " . $e->getMessage());
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
        send_json_response(true, 'Admin account deleted successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Manage Admins] Delete error: " . $e->getMessage());
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

        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashed_password, $reset_id]);

        error_log("[SSCMS Manage Admins] Password reset for user_id=$reset_id by admin_id=$user_id");

        send_json_response(true, 'Password reset successfully!', ['temp_password' => $temp_password]);
    } catch (Exception $e) {
        error_log("[SSCMS Manage Admins] Reset password error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

try {
    $stmt = $conn->query("SELECT id, name, email, admin_category, profile_picture FROM users");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Manage Admins] Fetch admins error: " . $e->getMessage());
    $admins = [];
}
?>

<div class="tab-pane fade" id="manage-admins" role="tabpanel" aria-labelledby="manage-admins-tab">
    <div class="card fade-in">
        <div class="card-header">
            <i class="fas fa-user-plus"></i> Create New Admin
        </div>
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
                    <label for="new_admin_category" class="form-label">Role</label>
                    <select class="form-select" id="new_admin_category" name="new_admin_category" required>
                        <option value="Nurse">Nurse</option>
                        <option value="Clinic Staff">Clinic Staff</option>
                        <option value="Doctor">Doctor</option>
                        <option value="Dentist">Dentist</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create</button>
            </form>
        </div>
    </div>

    <div class="card fade-in">
        <div class="card-header">
            <i class="fas fa-user-edit"></i> Manage Existing Admins
        </div>
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
                                    <td>
                                        <?php if ($admin['id'] != $user_id): ?>
                                            <button class="btn btn-warning btn-sm reset-password-btn" data-id="<?= $admin['id'] ?>" data-name="<?= htmlspecialchars($admin['name']) ?>" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"><i class="fas fa-key"></i> Reset</button>
                                        <?php else: ?>
                                            <span class="text-muted">Own account</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm edit-admin-btn" data-id="<?= $admin['id'] ?>" data-name="<?= htmlspecialchars($admin['name']) ?>" data-email="<?= htmlspecialchars($admin['email']) ?>" data-admin_category="<?= htmlspecialchars($admin['admin_category']) ?>" data-bs-toggle="modal" data-bs-target="#editAdminModal"><i class="fas fa-edit"></i></button>
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