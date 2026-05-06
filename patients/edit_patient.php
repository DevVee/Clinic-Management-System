<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check user session (uncomment for production)
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../login.php');
//     exit;
// }

// Get patient ID from URL
$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    $_SESSION['error_message'] = 'Invalid patient ID';
    header('Location: manage-patients.php');
    exit;
}

// Fetch patient data
try {
    $stmt = $conn->prepare("
        SELECT id, last_name, first_name, middle_name, gender, category, grade_year, program_section,
               guardian_contact, guardian_name, guardian_facebook, pediatrician_name, pediatrician_contact,
               allergies, medical_conditions, notes, other_contact, address, emergency_contact_name, emergency_contact_number
        FROM patients
        WHERE id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        $_SESSION['error_message'] = 'Patient not found';
        header('Location: manage-patients.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    header('Location: manage-patients.php');
    exit;
}

// Fetch grade years and program sections
$gradeYears = $conn->query("SELECT * FROM grade_years ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
$programSections = $conn->query("SELECT * FROM program_sections ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
$academicCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College'];
$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING) ?: $patient['category'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Edit Patient">
    <meta name="author" content="ICCB">
    <title>Edit Patient - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>
    <link rel="stylesheet" href="../css/manage-patients.css">
    <style>
        body { font-family: 'Inter', sans-serif; font-size: 0.875rem; background-color: #f8fafc; color: #1e293b; }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .card-header {
            background: linear-gradient(135deg, #0f73ba, #2c7be5);
            color: #ffffff;
            border-radius: 10px 10px 0 0 !important;
            padding: 0.75rem 1.25rem;
            border-bottom: none;
        }
        .card-header h5 {
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .form-label { font-weight: 500; font-size: 0.8rem; color: #475569; }
        .form-control, .form-select {
            font-size: 0.85rem;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            border-radius: 6px;
            color: #1e293b;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0f73ba;
            box-shadow: 0 0 0 3px rgba(15,115,186,0.12);
            background-color: #ffffff;
        }
        .preview-label { font-weight: 600; color: #475569; font-size: 0.8rem; }
        .preview-value { color: #1e293b; }
        .no-data { color: #6b7280; font-style: italic; }
        .btn-primary { background-color: #0f73ba; border-color: #0f73ba; }
        .btn-primary:hover { background-color: #0d5a94; border-color: #0d5a94; }
        h6.fw-semibold {
            color: #0f73ba;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.4rem;
            margin-bottom: 0.85rem !important;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .anim-delay-1 { animation: fadeInUp 0.4s ease 0.08s both; }
        .anim-delay-2 { animation: fadeInUp 0.4s ease 0.18s both; }
        @media (max-width: 992px) {
            .content { margin-left: 50px; }
        }
        @media (max-width: 768px) {
            .content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Dashboard Header -->
                <div class="dashboard-header fade-in" style="background: linear-gradient(135deg, #0f73ba 0%, #2c7be5 100%); color: white; padding: 0.85rem 1.5rem; border-radius: 10px; margin-bottom: 0.75rem; box-shadow: 0 4px 12px rgba(15,115,186,0.25); display: flex; align-items: center; justify-content: space-between;">
                    <h1 class="dashboard-title" style="font-size: 1.15rem; font-weight: 600; margin: 0; color: white; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-user-edit"></i>
                        Edit Patient
                    </h1>
                    <a href="javascript:history.back()" class="btn btn-outline-light btn-sm" style="border-color: rgba(255,255,255,0.6); color: white; font-size: 0.75rem;">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item"><a href="manage-patients.php<?= $category ? '?category=' . urlencode($category) : '' ?>" class="text-decoration-none">Manage Patients</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit <?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']) ?></li>
                    </ol>
                </nav>

                <!-- Toast Container -->
                <div class="toast-container position-fixed top-0 end-0 p-3">
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

                <!-- Preview Card -->
                <div class="card mb-4 fade-in anim-delay-1">
                    <div class="card-header">
                        <h5 class="mb-0">Patient Preview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><span class="preview-label">Name:</span> <span class="preview-value"><?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . ($patient['middle_name'] ?: '')) ?></span></p>
                                <p><span class="preview-label">Gender:</span> <span class="preview-value"><?= htmlspecialchars($patient['gender'] ?: 'N/A') ?></span></p>
                                <p><span class="preview-label">Category:</span> <span class="preview-value"><?= htmlspecialchars($patient['category'] ?: 'N/A') ?></span></p>
                                <p><span class="preview-label">Address:</span> <span class="preview-value"><?= htmlspecialchars($patient['address'] ?: 'N/A') ?></span></p>
                                <p><span class="preview-label">Guardian Name:</span> <span class="preview-value"><?= htmlspecialchars($patient['guardian_name'] ?: 'N/A') ?></span></p>
                                <p><span class="preview-label">Guardian Contact:</span> <span class="preview-value"><?= htmlspecialchars($patient['guardian_contact'] ?: 'N/A') ?></span></p>
                                <p><span class="preview-label">Other Contact:</span> <span class="preview-value"><?= htmlspecialchars($patient['other_contact'] ?: 'N/A') ?></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><span class="preview-label">Grade/Year:</span> <span class="preview-value"><?= htmlspecialchars($patient['grade_year'] ?: 'N/A') ?></span></p>
                                <p><span class="preview-label">Program/Section:</span> <span class="preview-value"><?= htmlspecialchars($patient['program_section'] ?: 'N/A') ?></span></p>
                                <p><span class="preview-label">Guardian Facebook:</span> <span class="preview-value"><?= htmlspecialchars($patient['guardian_facebook'] ?: 'N/A') ?></span></p>
                                <p><span class="preview-label">Emergency Contact:</span> <span class="preview-value"><?= htmlspecialchars($patient['emergency_contact_name'] ?: 'N/A') . ($patient['emergency_contact_number'] ? ' (' . htmlspecialchars($patient['emergency_contact_number']) . ')' : '') ?></span></p>
                                <p><span class="preview-label">Pediatrician:</span> <span class="preview-value"><?= htmlspecialchars($patient['pediatrician_name'] ?: 'N/A') . ($patient['pediatrician_contact'] ? ' (' . htmlspecialchars($patient['pediatrician_contact']) . ')' : '') ?></span></p>
                                <p><span class="preview-label">Allergies:</span> <span class="preview-value"><?= htmlspecialchars($patient['allergies'] ?: 'N/A') ?></span></p>
                                <p><span class="preview-label">Medical Conditions:</span> <span class="preview-value"><?= htmlspecialchars($patient['medical_conditions'] ?: 'N/A') ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="card fade-in anim-delay-2">
                    <div class="card-header">
                        <h5 class="mb-0">Update Patient Details</h5>
                    </div>
                    <div class="card-body">
                        <form id="editPatientForm" method="POST" action="../shared/manage-patients-process.php">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="patient_id" id="patient_id" value="<?= $patient['id'] ?>">

                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3">Personal Information</h6>
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($patient['last_name']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($patient['first_name']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="middle_name" class="form-label">Middle Name</label>
                                        <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= htmlspecialchars($patient['middle_name'] ?: '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?= $patient['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= $patient['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                            <option value="Other" <?= $patient['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($patient['address'] ?: '') ?>">
                                    </div>
                                </div>
                                <!-- Academic Information -->
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3">Academic Information</h6>
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">Select Category</option>
                                            <?php foreach (['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'] as $cat): ?>
                                                <option value="<?= $cat ?>" <?= $patient['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div id="academicFields" style="display: <?= in_array($patient['category'], $academicCategories) && $patient['category'] !== 'Alumni' ? 'block' : 'none' ?>;">
                                        <div class="mb-3">
                                            <label for="grade_year" class="form-label">Grade/Year</label>
                                            <select class="form-select" id="grade_year" name="grade_year">
                                                <option value="">Select Grade/Year</option>
                                            </select>
                                            <div id="grade_year_error" class="text-danger small mt-1 d-none">No grade/year available for this category.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="program_section" class="form-label">Program/Section</label>
                                            <select class="form-select" id="program_section" name="program_section">
                                                <option value="">Select Program/Section</option>
                                            </select>
                                            <div id="program_section_error" class="text-danger small mt-1 d-none">No program/section available for this category.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Guardian & Contact Information -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3">Guardian & Contact Information</h6>
                                    <div class="mb-3">
                                        <label for="guardian_name" class="form-label">Guardian Name</label>
                                        <input type="text" class="form-control" id="guardian_name" name="guardian_name" value="<?= htmlspecialchars($patient['guardian_name'] ?: '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="guardian_contact" class="form-label">Guardian Contact</label>
                                        <input type="text" class="form-control" id="guardian_contact" name="guardian_contact" value="<?= htmlspecialchars($patient['guardian_contact'] ?: '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="other_contact" class="form-label">Other Contact Number</label>
                                        <input type="text" class="form-control" id="other_contact" name="other_contact" value="<?= htmlspecialchars($patient['other_contact'] ?: '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="guardian_facebook" class="form-label">Guardian Facebook</label>
                                        <input type="text" class="form-control" id="guardian_facebook" name="guardian_facebook" value="<?= htmlspecialchars($patient['guardian_facebook'] ?: '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                        <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= htmlspecialchars($patient['emergency_contact_name'] ?: '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="emergency_contact_number" class="form-label">Emergency Contact Number</label>
                                        <input type="text" class="form-control" id="emergency_contact_number" name="emergency_contact_number" value="<?= htmlspecialchars($patient['emergency_contact_number'] ?: '') ?>">
                                    </div>
                                </div>
                                <!-- Medical Information -->
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3">Medical Information</h6>
                                    <div class="mb-3">
                                        <label for="pediatrician_name" class="form-label">Pediatrician Name</label>
                                        <input type="text" class="form-control" id="pediatrician_name" name="pediatrician_name" value="<?= htmlspecialchars($patient['pediatrician_name'] ?: '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="pediatrician_contact" class="form-label">Pediatrician Contact</label>
                                        <input type="text" class="form-control" id="pediatrician_contact" name="pediatrician_contact" value="<?= htmlspecialchars($patient['pediatrician_contact'] ?: '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="allergies" class="form-label">Allergies</label>
                                        <textarea class="form-control" id="allergies" name="allergies" rows="2"><?= htmlspecialchars($patient['allergies'] ?: '') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="medical_conditions" class="form-label">Medical Conditions</label>
                                        <textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="2"><?= htmlspecialchars($patient['medical_conditions'] ?: '') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="2"><?= htmlspecialchars($patient['notes'] ?: '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="javascript:history.back()" class="btn btn-secondary btn-sm">Cancel</a>

                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-save"></i> Save
                                    <span class="spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const gradeYears = <?= json_encode($gradeYears) ?>;
        const programSections = <?= json_encode($programSections) ?>;
        const academicCategories = <?= json_encode($academicCategories) ?>;

        function populateDropdowns(category, gradeYearSelect, programSectionSelect, gradeYearError, programSectionError, selectedGradeYear = '', selectedProgramSection = '') {
            const gradeYearOptions = gradeYears.filter(gy => gy.category === category);
            const programSectionOptions = programSections.filter(ps => ps.category === category);

            $(gradeYearSelect).empty().append('<option value="">Select Grade/Year</option>');
            $(programSectionSelect).empty().append('<option value="">Select Program/Section</option>');

            if (gradeYearOptions.length === 0) {
                $(gradeYearError).removeClass('d-none');
                $(gradeYearSelect).prop('disabled', true);
            } else {
                $(gradeYearError).addClass('d-none');
                $(gradeYearSelect).prop('disabled', false);
                gradeYearOptions.forEach(gy => {
                    $(gradeYearSelect).append(`<option value="${gy.name}" ${gy.name === selectedGradeYear ? 'selected' : ''}>${gy.name}</option>`);
                });
            }

            if (programSectionOptions.length === 0) {
                $(programSectionError).removeClass('d-none');
                $(programSectionSelect).prop('disabled', true);
            } else {
                $(programSectionError).addClass('d-none');
                $(programSectionSelect).prop('disabled', false);
                programSectionOptions.forEach(ps => {
                    $(programSectionSelect).append(`<option value="${ps.name}" ${ps.name === selectedProgramSection ? 'selected' : ''}>${ps.name}</option>`);
                });
            }
        }

        function toggleFields(category) {
            if (academicCategories.includes(category) && category !== 'Alumni') {
                $('#academicFields').show();
                $('#grade_year').prop('required', false);
                $('#program_section').prop('required', false);
                populateDropdowns(category, '#grade_year', '#program_section', '#grade_year_error', '#program_section_error', '<?= addslashes($patient['grade_year'] ?: '') ?>', '<?= addslashes($patient['program_section'] ?: '') ?>');
            } else {
                $('#academicFields').hide();
                $('#grade_year').prop('required', false);
                $('#program_section').prop('required', false);
                $('#grade_year').empty().append('<option value="">Select Grade/Year</option>');
                $('#program_section').empty().append('<option value="">Select Program/Section</option>');
                $('#grade_year_error').addClass('d-none');
                $('#program_section_error').addClass('d-none');
            }
        }

        $(document).ready(function() {
            // Initialize category fields
            toggleFields('<?= addslashes($patient['category']) ?>');

            // Category change handler
            $('#category').on('change', function() {
                toggleFields($(this).val());
            });

            // Form submission
            $('#editPatientForm').on('submit', function(e) {
                e.preventDefault();
                const $form = $(this);
                const $submitButton = $form.find('[type=submit]');
                const $spinner = $submitButton.find('.spinner-border');

                // Validate required fields
                let isValid = true;
                $form.find('[required]').each(function() {
                    if (!$(this).val()) {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });

                if (!isValid) {
                    $submitButton.prop('disabled', false);
                    $spinner.addClass('d-none');
                    alert('Please fill out all required fields.');
                    return;
                }

                $submitButton.prop('disabled', true);
                $spinner.removeClass('d-none');

                $.ajax({
                    url: $form.attr('action'),
                    method: $form.attr('method'),
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = 'manage-patients.php<?= $category ? '?category=' . urlencode($category) : '' ?>';
                        } else {
                            $submitButton.prop('disabled', false);
                            $spinner.addClass('d-none');
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        $submitButton.prop('disabled', false);
                        $spinner.addClass('d-none');
                        alert('Error: ' + (xhr.responseJSON?.message || 'An error occurred'));
                    }
                });
            });

            // Dark mode toggle
            $('#darkModeToggle').on('click', function() {
                $('body').toggleClass('dark-mode');
                const isDark = $('body').hasClass('dark-mode');
                $(this).html(`<i class="fas fa-${isDark ? 'sun' : 'moon'}"></i>`);
                localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
            });

            // Initialize dark mode
            if (localStorage.getItem('darkMode') === 'enabled') {
                $('body').addClass('dark-mode');
                $('#darkModeToggle').html('<i class="fas fa-sun"></i>');
            }

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>