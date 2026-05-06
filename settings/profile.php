<?php
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

        $profile_picture = $current_user['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024;
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

        $_SESSION['user_name'] = $name;
        $_SESSION['admin_category'] = $admin_category;
        $_SESSION['profile_picture'] = $profile_picture;

        send_json_response(true, 'Profile updated successfully!');
    } catch (Exception $e) {
        error_log("[SSCMS Profile] Update error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}
?>

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