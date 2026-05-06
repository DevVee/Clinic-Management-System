<?php
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
        error_log("[SSCMS Years] Add error: " . $e->getMessage());
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
        error_log("[SSCMS Years] Edit error: " . $e->getMessage());
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
        error_log("[SSCMS Years] Delete error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Ensure variables are defined
if (!isset($grouped_years)) {
    try {
        $stmt = $conn->query("SELECT id, name, category FROM grade_years ORDER BY category, name");
        $grade_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        error_log("[SSCMS Years] Fetch error: " . $e->getMessage());
        $grouped_years = [];
    }
}
?>

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