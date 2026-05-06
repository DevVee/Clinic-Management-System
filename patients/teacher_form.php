<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Updated categories to include only academic levels
$categories = [
    ['name' => 'Pre School', 'icon' => 'fa-child', 'description' => 'Pre-school students', 'color' => '#0284c7'],
    ['name' => 'Elementary', 'icon' => 'fa-school', 'description' => 'Elementary students', 'color' => '#059669'],
    ['name' => 'JHS', 'icon' => 'fa-book', 'description' => 'Junior High School students', 'color' => '#d97706'],
    ['name' => 'SHS', 'icon' => 'fa-graduation-cap', 'description' => 'Senior High School students', 'color' => '#dc2626'],
    ['name' => 'College', 'icon' => 'fa-university', 'description' => 'College students', 'color' => '#7c3aed'],
];

// Updated academic categories
$academicCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College'];

// Fetch grade/year and program/section
$gradeYearsStmt = $conn->query("SELECT name, category FROM grade_years ORDER BY category, name");
$gradeYears = $gradeYearsStmt->fetchAll(PDO::FETCH_ASSOC);

$programSectionsStmt = $conn->query("SELECT name, category FROM program_sections ORDER BY category, name");
$programSections = $programSectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $data = [
        'last_name' => filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '',
        'first_name' => filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '',
        'middle_name' => filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'category' => filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '',
        'grade_year' => filter_input(INPUT_POST, 'grade_year', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'program_section' => filter_input(INPUT_POST, 'program_section', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'contact_number' => filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''
    ];

    // Handle "Other" specifies
    if ($data['category'] === 'Other') {
        $data['category'] = filter_input(INPUT_POST, 'other_category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    }
    if ($data['grade_year'] === 'Other') {
        $data['grade_year'] = filter_input(INPUT_POST, 'other_grade_year', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    }
    if ($data['program_section'] === 'Other') {
        $data['program_section'] = filter_input(INPUT_POST, 'other_program_section', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    }

    // Validate required fields
    $required = ['last_name', 'first_name', 'category', 'contact_number'];
    $errors = [];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    // Validate contact number (exactly 11 digits, numbers only)
    if (!preg_match('/^\d{11}$/', $data['contact_number'])) {
        $errors[] = 'Contact Number must be exactly 11 digits with no other characters.';
    }
    // Validate grade_year and program_section for academic categories
    if (in_array($data['category'], $academicCategories) || $data['category'] === 'Other') {
        if (empty($data['grade_year'])) {
            $errors[] = 'Grade/Year is required.';
        }
        if (empty($data['program_section'])) {
            $errors[] = 'Program/Section is required.';
        }
    }

    if (empty($errors)) {
        // Insert into teachers table
        $query = "INSERT INTO teachers (last_name, first_name, middle_name, category, grade_year, program_section, contact_number) 
                  VALUES (:last_name, :first_name, :middle_name, :category, :grade_year, :program_section, :contact_number)";
        $stmt = $conn->prepare($query);
        try {
            $stmt->execute($data);
            $_SESSION['success_message'] = 'Your information has been submitted successfully.';
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Error saving data: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Information Form - ICCBI Smart Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f5f7fa;
            color: #333333;
            margin: 0;
            padding: 30px;
        }
        .form-container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .form-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            border-bottom: 2px solid #005566;
        }
        .form-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            color: #005566;
        }
        .form-header p {
            font-size: 14px;
            color: #555555;
            margin-top: 10px;
        }
        .section-header {
            font-size: 18px;
            font-weight: 600;
            margin: 30px 0 15px;
            color: #005566;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 8px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            color: #333333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            font-size: 14px;
            background-color: #fafafa;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #005566;
            box-shadow: 0 0 5px rgba(0, 85, 102, 0.2);
            outline: none;
        }
        .required::after {
            content: '*';
            color: #d32f2f;
            margin-left: 4px;
        }
        .other-input {
            display: none;
            margin-top: 8px;
        }
        .submit-btn {
            background-color: #005566;
            color: #ffffff;
            padding: 12px 40px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: block;
            margin: 30px auto 0;
            transition: background-color 0.2s, transform 0.2s;
        }
        .submit-btn:hover {
            background-color: #003d4a;
            transform: scale(1.05);
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .toast {
            min-width: 300px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, #ffffff, #f0f4f8);
        }
        .modal-header {
            background: linear-gradient(90deg, #005566, #007a8c);
            color: #ffffff;
            border-radius: 12px 12px 0 0;
            padding: 20px;
            border-bottom: none;
        }
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .modal-body {
            padding: 30px;
            text-align: center;
            font-size: 18px;
            color: #333333;
            line-height: 1.6;
        }
        .modal-body i {
            font-size: 50px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .modal-footer {
            border-top: none;
            justify-content: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }
        .modal-footer .btn {
            background-color: #005566;
            color: #ffffff;
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.3s;
        }
        .modal-footer .btn:hover {
            background-color: #003d4a;
            transform: translateY(-2px);
        }
        .modal.fade .modal-dialog {
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s ease-in-out;
        }
        .modal.show .modal-dialog {
            transform: scale(1);
            opacity: 1;
        }
        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.875rem;
        }
        .is-invalid ~ .invalid-feedback {
            display: block;
        }
        .form-text {
            font-size: 0.875rem;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .modal-body i.pulse {
            animation: pulse 1.5s infinite;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h1>Immaculate Conception College Of Balayan, Inc.</h1>
            <h1>Smart Clinic Management System</h1>
            <p>Teacher Information Form</p>
            <p>Please complete all required fields marked with an asterisk (*).</p>
        </div>

        <!-- Error Toast -->
        <div class="toast-container">
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

        <!-- Success Modal -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="successModalLabel">Submission Successful</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <i class="fas fa-check-circle pulse"></i>
                            <p><?= htmlspecialchars($_SESSION['success_message']) ?></p>
                            <p>Thank you for providing your information to the ICCBI Smart Clinic Management System!</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <form method="POST" id="teacherForm">
            <!-- Personal Information Section -->
            <div class="section-header">Personal Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="last_name" class="required">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required placeholder="e.g. Dela Cruz">
                    <small class="form-text text-muted">Enter your family name.</small>
                </div>
                <div class="form-group">
                    <label for="first_name" class="required">First Name</label>
                    <input type="text" id="first_name" name="first_name" required placeholder="e.g. Juan">
                    <small class="form-text text-muted">Enter your given name.</small>
                </div>
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" placeholder="e.g. Santos">
                    <small class="form-text text-muted">Enter your middle name, if applicable.</small>
                </div>
            </div>

            <!-- Academic Information Section -->
            <div class="section-header">Advising Section</div>
            <div class="form-group">
                <label for="category" class="required">Advising Category</label>
                <select id="category" name="category" required>
                    <option value="">Select Advising Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name']) ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="Other">Other</option>
                </select>
                <small class="form-text text-muted">Select the academic category you advise.</small>
                <input type="text" id="other_category" name="other_category" class="other-input" placeholder="e.g. Special Program">
                <small class="form-text text-muted other-input">Enter the category if not listed.</small>
            </div>
            <div id="academicFields">
                <div class="form-row">
                    <div class="form-group">
                        <label for="grade_year" class="required">Grade/Year</label>
                        <select id="grade_year" name="grade_year" required>
                            <option value="">Select Grade/Year</option>
                        </select>
                        <small class="form-text text-muted">Select the grade or year level you advise.</small>
                        <input type="text" id="other_grade_year" name="other_grade_year" class="other-input" placeholder="e.g. Grade 12">
                        <small class="form-text text-muted other-input">Enter the grade/year if not listed.</small>
                    </div>
                    <div class="form-group">
                        <label for="program_section" class="required">Program/Section</label>
                        <select id="program_section" name="program_section" required>
                            <option value="">Select Program/Section</option>
                        </select>
                        <small class="form-text text-muted">Select the program or section you advise.</small>
                        <input type="text" id="other_program_section" name="other_program_section" class="other-input" placeholder="e.g. STEM-A">
                        <small class="form-text text-muted other-input">Enter the program/section if not listed.</small>
                    </div>
                </div>
            </div>

            <!-- Contact Information Section -->
            <div class="section-header">Contact Information</div>
            <div class="form-group">
                <label for="contact_number" class="required">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" required pattern="\d{11}" maxlength="11" placeholder="e.g. 09123456789">
                <small class="form-text text-muted">Enter an 11-digit mobile number (e.g., 09123456789).</small>
                <div class="invalid-feedback">Contact Number must be exactly 11 digits with no other characters.</div>
            </div>

            <button type="submit" class="submit-btn">Submit</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const gradeYears = <?= json_encode($gradeYears) ?>;
        const programSections = <?= json_encode($programSections) ?>;
        const academicCategories = <?= json_encode($academicCategories) ?>;

        // Function to capitalize first letter of each word
        function toSentenceCase(str) {
            return str.toLowerCase().replace(/(^|\s)\w/g, letter => letter.toUpperCase());
        }

        // Apply sentence case to input fields
        const textFields = [
            '#last_name',
            '#first_name',
            '#middle_name',
            '#other_category',
            '#other_grade_year',
            '#other_program_section'
        ];
        textFields.forEach(field => {
            $(field).on('input', function() {
                this.value = toSentenceCase(this.value);
            });
        });

        // Restrict contact number to numbers only
        $('#contact_number').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });

        // Populate grade/year and program/section dropdowns
        function populateDropdowns(category, gradeYearSelect, programSectionSelect) {
            const gradeYearOptions = gradeYears.filter(gy => gy.category === category);
            const programSectionOptions = programSections.filter(ps => ps.category === category);

            $(gradeYearSelect).html('<option value="">Select Grade/Year</option>');
            gradeYearOptions.forEach(gy => $(gradeYearSelect).append(`<option value="${gy.name}">${gy.name}</option>`));
            $(gradeYearSelect).append('<option value="Other">Other</option>');

            $(programSectionSelect).html('<option value="">Select Program/Section</option>');
            programSectionOptions.forEach(ps => $(programSectionSelect).append(`<option value="${ps.name}">${ps.name}</option>`));
            $(programSectionSelect).append('<option value="Other">Other</option>');
        }

        // Toggle academic fields based on category
        function toggleFields(category) {
            const isAcademic = academicCategories.includes(category) || category === 'Other';
            $('#academicFields').toggle(isAcademic);
            if (isAcademic) {
                populateDropdowns(category, '#grade_year', '#program_section');
                $('#grade_year, #program_section').prop('required', true);
            } else {
                $('#grade_year').html('<option value="">Select Grade/Year</option><option value="Other">Other</option>');
                $('#program_section').html('<option value="">Select Program/Section</option><option value="Other">Other</option>');
                $('#other_grade_year, #other_program_section').hide();
                $('#grade_year, #program_section').prop('required', false);
            }
        }

        $(document).ready(function() {
            // Initialize modal
            if ($('#successModal').length) {
                const successModal = new bootstrap.Modal('#successModal', {
                    backdrop: 'static',
                    keyboard: false
                });
                successModal.show();
            }

            // Initialize error toasts
            $('.toast').toast({ delay: 5000 });
            $('.toast').toast('show');

            // Category change handler
            $('#category').on('change', function() {
                const category = $(this).val();
                $('#other_category').toggle(category === 'Other');
                toggleFields(category);
            });

            // Other options
            $('#grade_year').on('change', function() {
                $('#other_grade_year').toggle($(this).val() === 'Other');
            });
            $('#program_section').on('change', function() {
                $('#other_program_section').toggle($(this).val() === 'Other');
            });

            // Set initial state for academic fields
            toggleFields($('#category').val());

            // Form Submission Validation
            $('#teacherForm').on('submit', function(e) {
                const errors = [];
                const requiredFields = [
                    { id: 'last_name', name: 'Last Name' },
                    { id: 'first_name', name: 'First Name' },
                    { id: 'category', name: 'Category' },
                    { id: 'contact_number', name: 'Contact Number' }
                ];

                requiredFields.forEach(field => {
                    if (!$(`#${field.id}`).val()) {
                        errors.push(`${field.name} is required.`);
                        $(`#${field.id}`).addClass('is-invalid');
                    } else {
                        $(`#${field.id}`).removeClass('is-invalid');
                    }
                });

                // Validate contact number client-side
                const contactNumber = $('#contact_number').val();
                if (!/^\d{11}$/.test(contactNumber)) {
                    errors.push('Contact Number must be exactly 11 digits with no other characters.');
                    $('#contact_number').addClass('is-invalid');
                } else {
                    $('#contact_number').removeClass('is-invalid');
                }

                const category = $('#category').val();
                if (academicCategories.includes(category) || category === 'Other') {
                    if (!$('#grade_year').val()) {
                        errors.push('Grade/Year is required.');
                        $('#grade_year').addClass('is-invalid');
                    } else {
                        $('#grade_year').removeClass('is-invalid');
                    }
                    if (!$('#program_section').val()) {
                        errors.push('Program/Section is required.');
                        $('#program_section').addClass('is-invalid');
                    } else {
                        $('#program_section').removeClass('is-invalid');
                    }
                }

                if (errors.length > 0) {
                    e.preventDefault();
                    $('.toast-container').html(`
                        <div class="toast align-items-center text-bg-danger border-0 show" role="alert">
                            <div class="d-flex">
                                <div class="toast-body">${errors.join('<br>')}</div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                    `);
                    $('.toast').toast({ delay: 5000 });
                    $('.toast').toast('show');
                }
            });
        });
    </script>
</body>
</html>