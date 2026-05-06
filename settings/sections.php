<?php
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
        error_log("[SSCMS Sections] Add error: " . $e->getMessage());
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
        error_log("[SSCMS Sections] Edit error: " . $e->getMessage());
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
        error_log("[SSCMS Sections] Delete error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

// Ensure variables are defined
if (!isset($grouped_sections)) {
    try {
        $stmt = $conn->query("SELECT id, name, category FROM program_sections ORDER BY category, name");
        $program_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        error_log("[SSCMS Sections] Fetch error: " . $e->getMessage());
        $grouped_sections = [];
    }
}
?>

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