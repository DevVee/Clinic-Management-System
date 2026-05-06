<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Updated categories to match manage-patients.php, removed 'Other'
$categories = [
    ['name' => 'Pre School', 'icon' => 'fa-child', 'description' => 'Pre-school students', 'color' => '#0284c7'],
    ['name' => 'Elementary', 'icon' => 'fa-school', 'description' => 'Elementary students', 'color' => '#059669'],
    ['name' => 'JHS', 'icon' => 'fa-book', 'description' => 'Junior High School students', 'color' => '#d97706'],
    ['name' => 'SHS', 'icon' => 'fa-graduation-cap', 'description' => 'Senior High School students', 'color' => '#dc2626'],
    ['name' => 'College', 'icon' => 'fa-university', 'description' => 'College students', 'color' => '#7c3aed'],
    ['name' => 'Faculty and Staff', 'icon' => 'fa-chalkboard-teacher', 'description' => 'Faculty and staff members', 'color' => '#2c2c2c'],
    ['name' => 'Alumni', 'icon' => 'fa-user-graduate', 'description' => 'Graduated students', 'color' => '#4b5563']
];

// Updated academic categories
$academicCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Faculty and Staff'];

// Fetch grade/year and program/section
$gradeYearsStmt = $conn->query("SELECT name, category FROM grade_years ORDER BY category, name");
$gradeYears = $gradeYearsStmt->fetchAll(PDO::FETCH_ASSOC);

$programSectionsStmt = $conn->query("SELECT name, category FROM program_sections ORDER BY category, name");
$programSections = $programSectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect all data
    $data = [
        'last_name' => filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '',
        'first_name' => filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '',
        'middle_name' => filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'gender' => filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_SPECIAL_CHARS) ?? '',
        'address' => filter_input(INPUT_POST, 'address', FILTER_SANITIZE_SPECIAL_CHARS) ?? '',
        'category' => filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '',
        'grade_year' => filter_input(INPUT_POST, 'grade_year', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'program_section' => filter_input(INPUT_POST, 'program_section', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'guardian_name' => filter_input(INPUT_POST, 'guardian_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'guardian_contact' => filter_input(INPUT_POST, 'guardian_contact', FILTER_SANITIZE_SPECIAL_CHARS) ?? '',
        'other_contact' => filter_input(INPUT_POST, 'other_contact', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'guardian_facebook' => filter_input(INPUT_POST, 'guardian_facebook', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'emergency_contact_name' => filter_input(INPUT_POST, 'emergency_contact_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'emergency_contact_number' => filter_input(INPUT_POST, 'emergency_contact_number', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'pediatrician_name' => filter_input(INPUT_POST, 'pediatrician_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'pediatrician_contact' => filter_input(INPUT_POST, 'pediatrician_contact', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'allergies' => filter_input(INPUT_POST, 'allergies', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'medical_conditions' => filter_input(INPUT_POST, 'medical_conditions', FILTER_SANITIZE_SPECIAL_CHARS) ?? null,
        'notes' => filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS) ?? null
    ];

    // Validate required fields
    $required = ['last_name', 'first_name', 'gender', 'category', 'address', 'guardian_contact'];
    $errors = [];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    // Validate all contact numbers (exactly 11 digits, numbers only)
    $contactFields = [
        'guardian_contact' => 'Guardian Contact',
        'other_contact' => 'Other Contact',
        'emergency_contact_number' => 'Emergency Contact Number',
        'pediatrician_contact' => 'Pediatrician Contact'
    ];
    foreach ($contactFields as $field => $label) {
        if (!empty($data[$field]) && !preg_match('/^\d{11}$/', $data[$field])) {
            $errors[] = "$label must be exactly 11 digits with no other characters.";
        }
    }
    // Validate grade_year and program_section for academic categories
    if (in_array($data['category'], $academicCategories)) {
        if (empty($data['grade_year'])) {
            $errors[] = 'Grade/year is required.';
        }
        if (empty($data['program_section'])) {
            $errors[] = 'Program/section is required.';
        }
    }

    if (empty($errors)) {
        // Insert into pending_patients
        $query = "INSERT INTO pending_patients (
            last_name, first_name, middle_name, gender, address, category, grade_year, program_section,
            guardian_name, guardian_contact, other_contact, guardian_facebook, emergency_contact_name,
            emergency_contact_number, pediatrician_name, pediatrician_contact, allergies, medical_conditions, notes
        ) VALUES (
            :last_name, :first_name, :middle_name, :gender, :address, :category, :grade_year, :program_section,
            :guardian_name, :guardian_contact, :other_contact, :guardian_facebook, :emergency_contact_name,
            :emergency_contact_number, :pediatrician_name, :pediatrician_contact, :allergies, :medical_conditions, :notes
        )";
        $stmt = $conn->prepare($query);
        try {
            $stmt->execute($data);
            $_SESSION['success_message'] = 'Your information has been submitted successfully and is pending review.';
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
    <title>Patient Information Form - Iccb Smart Clinic</title>
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
            max-width: 960px;
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
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            font-size: 14px;
            background-color: #fafafa;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #005566;
            box-shadow: 0 0 5px rgba(0, 85, 102, 0.2);
            outline: none;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .required::after {
            content: '*';
            color: #d32f2f;
            margin-left: 4px;
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
            min-width: 250px;
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
            <p>Patient Information Form</p>
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
                            <p>Thank you for providing your information to the ICCB Smart Clinic Management System!</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <form method="POST" id="patientForm">
            <!-- Personal information section -->
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
            <div class="form-group">
                <label for="gender" class="required">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <small class="form-text text-muted">Select your gender.</small>
            </div>

            <!-- Address section -->
            <div class="section-header">Address</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="municipality" class="required">Municipality/City</label>
                    <select id="municipality" name="municipality" required>
                        <option value="">Select Municipality/City</option>
                    </select>
                    <small class="form-text text-muted">Select your municipality or city.</small>
                    <div class="invalid-feedback">Please select a municipality.</div>
                </div>
                <div class="form-group">
                    <label for="barangay" class="required">Barangay</label>
                    <select id="barangay" name="barangay" disabled required>
                        <option value="">Select Municipality First</option>
                    </select>
                    <small class="form-text text-muted">Select your barangay after choosing a municipality.</small>
                    <div class="invalid-feedback">Please select a barangay.</div>
                </div>
            </div>
            <div class="form-group">
                <label for="address" class="required">Full Address</label>
                <input type="text" id="address" name="address" readonly required>
                <small class="form-text text-muted">This will be auto-filled based on municipality and barangay.</small>
            </div>

            <!-- Academic information section -->
            <div class="section-header">Academic Information</div>
            <div class="form-group">
                <label for="category" class="required">Category</label>
                <select id="category" name="category" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Select your academic or staff category.</small>
            </div>
            <div id="academicFields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="grade_year" class="required">Grade/Year</label>
                        <select id="grade_year" name="grade_year" required>
                            <option value="">Select Grade/Year</option>
                        </select>
                        <small class="form-text text-muted">Select your grade or year level.</small>
                    </div>
                    <div class="form-group">
                        <label for="program_section" class="required">Program/Section</label>
                        <select id="program_section" name="program_section" required>
                            <option value="">Select Program/Section</option>
                        </select>
                        <small class="form-text text-muted">Select your program or section.</small>
                    </div>
                </div>
            </div>

            <!-- Guardian & contact information section -->
            <div class="section-header">Guardian & Contact Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="guardian_name">Guardian Name</label>
                    <input type="text" id="guardian_name" name="guardian_name" placeholder="e.g. Maria Santos">
                    <small class="form-text text-muted">Enter the full name of the guardian.</small>
                </div>
                <div class="form-group">
                    <label for="guardian_contact" class="required">Guardian Contact</label>
                    <input type="text" id="guardian_contact" name="guardian_contact" required pattern="\d{11}" maxlength="11" placeholder="e.g. 09123456789">
                    <small class="form-text text-muted">Enter an 11-digit mobile number (e.g., 09123456789).</small>
                    <div class="invalid-feedback">Guardian Contact must be exactly 11 digits with no other characters.</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="other_contact">Other Contact Number (Optional)</label>
                    <input type="text" id="other_contact" name="other_contact" pattern="\d{11}" maxlength="11" placeholder="e.g. 09198765432">
                    <small class="form-text text-muted">Enter an 11-digit additional contact number, if available.</small>
                    <div class="invalid-feedback">Other Contact must be exactly 11 digits with no other characters.</div>
                </div>
                <div class="form-group">
                    <label for="guardian_facebook">Guardian Facebook (Optional)</label>
                    <input type="text" id="guardian_facebook" name="guardian_facebook" placeholder="e.g. facebook.com/maria.santos">
                    <small class="form-text text-muted">Enter the guardian's Facebook profile URL or username.</small>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="emergency_contact_name">Emergency Contact Name (Optional)</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" placeholder="e.g. Jose Reyes">
                    <small class="form-text text-muted">Enter the name of the emergency contact person.</small>
                </div>
                <div class="form-group">
                    <label for="emergency_contact_number">Emergency Contact Number (Optional)</label>
                    <input type="text" id="emergency_contact_number" name="emergency_contact_number" pattern="\d{11}" maxlength="11" placeholder="e.g. 09187654321">
                    <small class="form-text text-muted">Enter an 11-digit emergency contact number, if available.</small>
                    <div class="invalid-feedback">Emergency Contact Number must be exactly 11 digits with no other characters.</div>
                </div>
            </div>

            <!-- Medical information section -->
            <div class="section-header">Medical Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="pediatrician_name">Pediatrician Name (Optional)</label>
                    <input type="text" id="pediatrician_name" name="pediatrician_name" placeholder="e.g. Dr. Anna Lopez">
                    <small class="form-text text-muted">Enter the name of your pediatrician or doctor.</small>
                </div>
                <div class="form-group">
                    <label for="pediatrician_contact">Pediatrician Contact (Optional)</label>
                    <input type="text" id="pediatrician_contact" name="pediatrician_contact" pattern="\d{11}" maxlength="11" placeholder="e.g. 09176543210">
                    <small class="form-text text-muted">Enter an 11-digit pediatrician's contact number, if available.</small>
                    <div class="invalid-feedback">Pediatrician Contact must be exactly 11 digits with no other characters.</div>
                </div>
            </div>
            <div class="form-group">
                <label for="allergies">Allergies (Optional)</label>
                <textarea id="allergies" name="allergies" placeholder="e.g. Peanuts, Penicillin"></textarea>
                <small class="form-text text-muted">List any known allergies (e.g., food, medication).</small>
            </div>
            <div class="form-group">
                <label for="medical_conditions">Medical Conditions (Optional)</label>
                <textarea id="medical_conditions" name="medical_conditions" placeholder="e.g. Asthma, Diabetes"></textarea>
                <small class="form-text text-muted">List any medical conditions or diagnoses.</small>
            </div>
            <div class="form-group">
                <label for="notes">Additional Notes (Optional)</label>
                <textarea id="notes" name="notes" placeholder="e.g. Requires inhaler during emergencies"></textarea>
                <small class="form-text text-muted">Add any additional information relevant to your health or care.</small>
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
        const batangasCode = "041000000";

        // Function to capitalize first letter of each word
        function toSentenceCase(str) {
            return str.toLowerCase().replace(/(^|\s)\w/g, letter => letter.toUpperCase());
        }

        // Apply sentence case to input fields
        const textFields = [
            '#last_name',
            '#first_name',
            '#middle_name',
            '#guardian_name',
            '#emergency_contact_name',
            '#pediatrician_name'
        ];

        textFields.forEach(field => {
            $(field).on('input', function() {
                this.value = toSentenceCase(this.value);
            });
        });

        // Restrict contact numbers to 11 digits
        $('#guardian_contact, #other_contact, #emergency_contact_number, #pediatrician_contact').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });

        function populateDropdowns(category, gradeYearSelect, programSectionSelect) {
            const gradeYearOptions = gradeYears.filter(gy => gy.category === category);
            const programSectionOptions = programSections.filter(ps => ps.category === category);

            $(gradeYearSelect).html('<option value="">Select Grade/Year</option>');
            gradeYearOptions.forEach(gy => $(gradeYearSelect).append(`<option value="${gy.name}">${gy.name}</option>`));

            $(programSectionSelect).html('<option value="">Select Program/Section</option>');
            programSectionOptions.forEach(ps => $(programSectionSelect).append(`<option value="${ps.name}">${ps.name}</option>`));
        }

        function toggleFields(category) {
            const isAcademic = academicCategories.includes(category);
            $('#academicFields').toggle(isAcademic);
            if (isAcademic) {
                populateDropdowns(category, '#grade_year', '#program_section');
                $('#grade_year, #program_section').prop('required', true);
            } else {
                $('#grade_year').html('<option value="">Select Grade/Year</option>');
                $('#program_section').html('<option value="">Select Program/Section</option>');
                $('#grade_year, #program_section').prop('required', false);
            }
        }

        function updateAddress() {
            const municipality = $('#municipality').val();
            const barangay = $('#barangay').val();
            const municipalityText = $('#municipality option:selected').text();
            const barangayText = $('#barangay option:selected').text();

            if (barangayText && municipalityText && barangayText !== 'Select Barangay' && municipalityText !== 'Select Municipality/City') {
                $('#address').val(`${barangayText}, ${municipalityText}, Batangas`);
            } else {
                $('#address').val('');
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

            // Load Municipalities
            $.getJSON(`https://psgc.gitlab.io/api/provinces/${batangasCode}/cities-municipalities/`, function(municipalities) {
                $('#municipality').html('<option value="">Select Municipality/City</option>');
                municipalities.forEach(muni => {
                    $('#municipality').append(`<option value="${muni.code}">${muni.name}</option>`);
                });
            }).fail(function() {
                $('#municipality').html('<option value="">Error Loading Municipalities</option>');
            });

            // Municipality Change
            $('#municipality').on('change', function() {
                const code = $(this).val();
                if (code) {
                    $.getJSON(`https://psgc.gitlab.io/api/cities-municipalities/${code}/barangays/`, function(barangays) {
                        $('#barangay').html('<option value="">Select Barangay</option>');
                        barangays.forEach(barangay => {
                            $('#barangay').append(`<option value="${barangay.code}">${barangay.name}</option>`);
                        });
                        $('#barangay').prop('disabled', false);
                    }).fail(function() {
                        $('#barangay').html('<option value="">Error Loading Barangays</option>');
                        $('#barangay').prop('disabled', true);
                    });
                } else {
                    $('#barangay').html('<option value="">Select Municipality First</option>').prop('disabled', true);
                }
                updateAddress();
            });

            // Barangay Change
            $('#barangay').on('change', function() {
                updateAddress();
            });

            // Category Change
            $('#category').on('change', function() {
                const category = $(this).val();
                toggleFields(category);
            });

            // Form Submission Validation
            $('#patientForm').on('submit', function(e) {
                const errors = [];
                const requiredFields = [
                    { id: 'last_name', name: 'Last Name' },
                    { id: 'first_name', name: 'First Name' },
                    { id: 'gender', name: 'Gender' },
                    { id: 'municipality', name: 'Municipality/City' },
                    { id: 'barangay', name: 'Barangay' },
                    { id: 'category', name: 'Category' },
                    { id: 'guardian_contact', name: 'Guardian Contact' }
                ];

                requiredFields.forEach(field => {
                    if (!$(`#${field.id}`).val()) {
                        errors.push(`${field.name} is required.`);
                        $(`#${field.id}`).addClass('is-invalid');
                    } else {
                        $(`#${field.id}`).removeClass('is-invalid');
                    }
                });

                // Validate contact numbers client-side
                const contactFields = [
                    { id: 'guardian_contact', name: 'Guardian Contact' },
                    { id: 'other_contact', name: 'Other Contact' },
                    { id: 'emergency_contact_number', name: 'Emergency Contact Number' },
                    { id: 'pediatrician_contact', name: 'Pediatrician Contact' }
                ];

                contactFields.forEach(field => {
                    const value = $(`#${field.id}`).val();
                    if (value && !/^\d{11}$/.test(value)) {
                        errors.push(`${field.name} must be exactly 11 digits with no other characters.`);
                        $(`#${field.id}`).addClass('is-invalid');
                    } else {
                        $(`#${field.id}`).removeClass('is-invalid');
                    }
                });

                const category = $('#category').val();
                if (academicCategories.includes(category)) {
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

                if (!$('#address').val()) {
                    errors.push('Full Address is required.');
                    $('#address').addClass('is-invalid');
                } else {
                    $('#address').removeClass('is-invalid');
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