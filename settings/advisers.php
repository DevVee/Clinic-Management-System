<?php
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
        error_log("[SSCMS Advisers] Add error: " . $e->getMessage());
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
        error_log("[SSCMS Advisers] Edit error: " . $e->getMessage());
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
        error_log("[SSCMS Advisers] Delete error: " . $e->getMessage());
        send_json_response(false, $e->getMessage());
    }
}

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
    error_log("[SSCMS Advisers] Fetch error: " . $e->getMessage());
    $grouped_advisers = [];
}

try {
    $stmt = $conn->query("SELECT id, name, category FROM program_sections ORDER BY category, name");
    $program_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Advisers] Fetch sections error: " . $e->getMessage());
    $program_sections = [];
}

try {
    $stmt = $conn->query("SELECT id, name, category FROM grade_years ORDER BY category, name");
    $grade_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Advisers] Fetch years error: " . $e->getMessage());
    $grade_years = [];
}
?>

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