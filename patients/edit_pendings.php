<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check user session (uncomment for production)
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../login.php');
//     exit;
// }

// Check if patient ID is provided
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = 'Invalid patient ID.';
    header('Location: pendings.php');
    exit;
}

$patient_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Fetch patient data
try {
    $stmt = $conn->prepare("SELECT * FROM pending_patients WHERE id = :id");
    $stmt->execute(['id' => $patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $_SESSION['error_message'] = 'Patient not found.';
        header('Location: pendings.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error fetching patient: ' . $e->getMessage();
    header('Location: pendings.php');
    exit;
}

// Fetch categories, grade/year, and program/section
$categories = [
    'Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Faculty and Staff', 'Alumni'
];
$gradeYearsStmt = $conn->query("SELECT DISTINCT name FROM grade_years ORDER BY name");
$gradeYears = $gradeYearsStmt->fetchAll(PDO::FETCH_COLUMN);
$programSectionsStmt = $conn->query("SELECT DISTINCT name FROM program_sections ORDER BY name");
$programSections = $programSectionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'last_name' => filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS),
            'first_name' => filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS),
            'middle_name' => filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'gender' => filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_SPECIAL_CHARS),
            'address' => filter_input(INPUT_POST, 'address', FILTER_SANITIZE_SPECIAL_CHARS),
            'category' => filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS),
            'grade_year' => filter_input(INPUT_POST, 'grade_year', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'program_section' => filter_input(INPUT_POST, 'program_section', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'guardian_name' => filter_input(INPUT_POST, 'guardian_name', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'guardian_contact' => filter_input(INPUT_POST, 'guardian_contact', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'other_contact' => filter_input(INPUT_POST, 'other_contact', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'guardian_facebook' => filter_input(INPUT_POST, 'guardian_facebook', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'emergency_contact_name' => filter_input(INPUT_POST, 'emergency_contact_name', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'emergency_contact_number' => filter_input(INPUT_POST, 'emergency_contact_number', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'pediatrician_name' => filter_input(INPUT_POST, 'pediatrician_name', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'pediatrician_contact' => filter_input(INPUT_POST, 'pediatrician_contact', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'allergies' => filter_input(INPUT_POST, 'allergies', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'medical_conditions' => filter_input(INPUT_POST, 'medical_conditions', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'notes' => filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS) ?: null,
            'id' => $patient_id
        ];

        // Basic validation
        if (!$data['last_name'] || !$data['first_name'] || !$data['gender'] || !$data['address'] || !$data['category']) {
            $_SESSION['error_message'] = 'Please fill in all required fields.';
            header("Location: edit_pendings.php?id=$patient_id");
            exit;
        }

        // Update patient in database
        $stmt = $conn->prepare("
            UPDATE pending_patients SET
                last_name = :last_name,
                first_name = :first_name,
                middle_name = :middle_name,
                gender = :gender,
                address = :address,
                category = :category,
                grade_year = :grade_year,
                program_section = :program_section,
                guardian_name = :guardian_name,
                guardian_contact = :guardian_contact,
                other_contact = :other_contact,
                guardian_facebook = :guardian_facebook,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_number = :emergency_contact_number,
                pediatrician_name = :pediatrician_name,
                pediatrician_contact = :pediatrician_contact,
                allergies = :allergies,
                medical_conditions = :medical_conditions,
                notes = :notes
            WHERE id = :id
        ");
        $stmt->execute($data);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = 'Patient updated successfully.';
        } else {
            $_SESSION['error_message'] = 'No changes made to the patient record.';
        }
        header('Location: pendings.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error updating patient: ' . $e->getMessage();
        header("Location: edit_pendings.php?id=$patient_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Edit Pending Patient">
    <meta name="author" content="ICCB">
    <title>Edit Pending Patient - ICCB Smart Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>
    <style>
        :root { --sidebar-width: 220px; --sidebar-collapsed-width: 50px; }
        body { font-family: 'Inter', sans-serif; font-size: 0.875rem; background-color: #f8fafc; color: #1e293b; }
        .content {
            margin-left: var(--sidebar-width);
            padding: 1rem;
            min-height: 100vh;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #0f73ba 0%, #2c7be5 100%);
            color: white;
            padding: 0.85rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 0.85rem;
            box-shadow: 0 4px 12px rgba(15, 115, 186, 0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dashboard-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin: 0;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: box-shadow 0.3s ease;
        }
        .card:hover { box-shadow: 0 8px 20px rgba(15,115,186,0.1); }
        .card-body { padding: 1.25rem; }
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 0.8rem;
            border-radius: 6px;
            color: #1e293b;
            padding: 0.45rem 0.65rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0f73ba;
            box-shadow: 0 0 0 3px rgba(15,115,186,0.12);
            background-color: #ffffff;
        }
        .form-label { font-size: 0.8rem; font-weight: 500; color: #475569; margin-bottom: 0.3rem; }
        .section-heading {
            font-size: 0.75rem;
            font-weight: 600;
            color: #0f73ba;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.35rem;
            margin-bottom: 0.85rem;
        }
        .btn-sm { font-size: 0.75rem; padding: 0.3rem 0.65rem; border-radius: 6px; }
        .btn-primary { background-color: #0f73ba; border-color: #0f73ba; transition: all 0.2s ease; }
        .btn-primary:hover { background-color: #0d5a94; border-color: #0d5a94; transform: translateY(-1px); }
        .btn-outline-secondary { border-color: #94a3b8; color: #64748b; transition: all 0.2s ease; }
        .btn-outline-secondary:hover { background-color: #64748b; border-color: #64748b; color: white; }
        .btn-outline-light { border-color: rgba(255,255,255,0.6); color: white; }
        .btn-outline-light:hover { background-color: rgba(255,255,255,0.15); border-color: white; color: white; }
        .toast-container { z-index: 1050; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in      { animation: fadeInUp 0.4s ease both; }
        .anim-delay-1 { animation: fadeInUp 0.4s ease 0.1s both; }
        @media (max-width: 992px) { .content { margin-left: 50px; } }
        @media (max-width: 768px) {
            .content { margin-left: 0; }
            .dashboard-header { flex-direction: column; gap: 0.5rem; text-align: center; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Dashboard Header -->
                <div class="dashboard-header fade-in">
                    <span class="dashboard-title">
                        <i class="fas fa-user-edit"></i> Edit Pending Patient
                    </span>
                    <a href="pendings.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>

                <!-- Toast Container -->
                <div class="toast-container position-fixed top-0 end-0 p-2">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="toast align-items-center text-bg-danger border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                </div>

                <!-- Edit Form -->
                <div class="card anim-delay-1">
                    <div class="card-body p-3">
                        <form method="post">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($patient['last_name']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($patient['first_name']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= htmlspecialchars($patient['middle_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="Male" <?= $patient['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= $patient['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= $patient['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label for="address" class="form-label">Address *</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($patient['address']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>" <?= $patient['category'] === $cat ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="grade_year" class="form-label">Grade/Year</label>
                                    <select class="form-select" id="grade_year" name="grade_year">
                                        <option value="">Select Grade/Year</option>
                                        <?php foreach ($gradeYears as $gy): ?>
                                            <option value="<?= htmlspecialchars($gy) ?>" <?= $patient['grade_year'] === $gy ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($gy) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="program_section" class="form-label">Program/Section</label>
                                    <select class="form-select" id="program_section" name="program_section">
                                        <option value="">Select Program/Section</option>
                                        <?php foreach ($programSections as $ps): ?>
                                            <option value="<?= htmlspecialchars($ps) ?>" <?= $patient['program_section'] === $ps ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($ps) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="guardian_name" class="form-label">Guardian Name</label>
                                    <input type="text" class="form-control" id="guardian_name" name="guardian_name" value="<?= htmlspecialchars($patient['guardian_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="guardian_contact" class="form-label">Guardian Contact</label>
                                    <input type="text" class="form-control" id="guardian_contact" name="guardian_contact" value="<?= htmlspecialchars($patient['guardian_contact'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="other_contact" class="form-label">Other Contact</label>
                                    <input type="text" class="form-control" id="other_contact" name="other_contact" value="<?= htmlspecialchars($patient['other_contact'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="guardian_facebook" class="form-label">Guardian Facebook</label>
                                    <input type="text" class="form-control" id="guardian_facebook" name="guardian_facebook" value="<?= htmlspecialchars($patient['guardian_facebook'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= htmlspecialchars($patient['emergency_contact_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="emergency_contact_number" class="form-label">Emergency Contact Number</label>
                                    <input type="text" class="form-control" id="emergency_contact_number" name="emergency_contact_number" value="<?= htmlspecialchars($patient['emergency_contact_number'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="pediatrician_name" class="form-label">Pediatrician Name</label>
                                    <input type="text" class="form-control" id="pediatrician_name" name="pediatrician_name" value="<?= htmlspecialchars($patient['pediatrician_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="pediatrician_contact" class="form-label">Pediatrician Contact</label>
                                    <input type="text" class="form-control" id="pediatrician_contact" name="pediatrician_contact" value="<?= htmlspecialchars($patient['pediatrician_contact'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="allergies" class="form-label">Allergies</label>
                                    <textarea class="form-control" id="allergies" name="allergies" rows="2"><?= htmlspecialchars($patient['allergies'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label for="medical_conditions" class="form-label">Medical Conditions</label>
                                    <textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="2"><?= htmlspecialchars($patient['medical_conditions'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="4"><?= htmlspecialchars($patient['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <a href="pendings.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <?php include '../includes/footer.php' ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>