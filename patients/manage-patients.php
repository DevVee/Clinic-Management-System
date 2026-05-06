<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check user session (uncomment for production)
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../login.php');
//     exit;
// }

// Updated categories
$categories = [
    ['name' => 'Pre School', 'icon' => 'fa-child', 'description' => 'Pre-school students', 'color' => '#0284c7'],
    ['name' => 'Elementary', 'icon' => 'fa-school', 'description' => 'Elementary students', 'color' => '#059669'],
    ['name' => 'JHS', 'icon' => 'fa-book', 'description' => 'Junior High School students', 'color' => '#d97706'],
    ['name' => 'SHS', 'icon' => 'fa-graduation-cap', 'description' => 'Senior High School students', 'color' => '#dc2626'],
    ['name' => 'College', 'icon' => 'fa-university', 'description' => 'College students', 'color' => '#7c3aed'],
    ['name' => 'Faculty and Staff', 'icon' => 'fa-chalkboard-teacher', 'description' => 'Faculty and staff members', 'color' => '#2c2c2c'],
    ['name' => 'Alumni', 'icon' => 'fa-user-graduate', 'description' => 'Graduated students', 'color' => '#4b5563']
];

// Academic categories
$academicCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Faculty and Staff'];

// Fetch grade/year and program/section
$gradeYearsStmt = $conn->query("SELECT name, category FROM grade_years ORDER BY category, name");
$gradeYears = $gradeYearsStmt->fetchAll(PDO::FETCH_ASSOC);

$programSectionsStmt = $conn->query("SELECT name, category FROM program_sections ORDER BY category, name");
$programSections = $programSectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle actions (add, move, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        try {
            $insertStmt = $conn->prepare("
                INSERT INTO patients (
                    last_name, first_name, middle_name, gender, address, category, grade_year, program_section,
                    guardian_name, guardian_contact, other_contact, guardian_facebook, emergency_contact_name,
                    emergency_contact_number, pediatrician_name, pediatrician_contact, allergies, medical_conditions, notes
                ) VALUES (
                    :last_name, :first_name, :middle_name, :gender, :address, :category, :grade_year, :program_section,
                    :guardian_name, :guardian_contact, :other_contact, :guardian_facebook, :emergency_contact_name,
                    :emergency_contact_number, :pediatrician_name, :pediatrician_contact, :allergies, :medical_conditions, :notes
                )
            ");
            $insertStmt->execute([
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
                'notes' => filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS) ?: null
            ]);

            echo json_encode(['success' => true, 'message' => 'Patient added successfully.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'move' && isset($_POST['patient_ids'])) {
        try {
            $ids = array_map('intval', explode(',', filter_input(INPUT_POST, 'patient_ids', FILTER_SANITIZE_SPECIAL_CHARS)));
            $new_category = filter_input(INPUT_POST, 'new_category', FILTER_SANITIZE_SPECIAL_CHARS);
            $new_grade_year = filter_input(INPUT_POST, 'new_grade_year', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
            $new_program_section = filter_input(INPUT_POST, 'new_program_section', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;

            if (empty($new_category)) {
                echo json_encode(['success' => false, 'message' => 'New category is required.']);
                exit;
            }

            if (!in_array($new_category, array_map(function($cat) { return $cat['name']; }, $categories))) {
                echo json_encode(['success' => false, 'message' => 'Invalid category.']);
                exit;
            }

            if (!in_array($new_category, $academicCategories)) {
                $new_grade_year = null;
                $new_program_section = null;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $update_query = "UPDATE patients SET category = ?, grade_year = ?, program_section = ? WHERE id IN ($placeholders)";
            $params = [$new_category, $new_grade_year, $new_program_section, ...$ids];

            $stmt = $conn->prepare($update_query);
            $stmt->execute($params);

            $count = $stmt->rowCount();

            echo json_encode(['success' => true, 'message' => 'Selected patients moved successfully.', 'count' => $count]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['patient_ids'])) {
        try {
            $ids = array_map('intval', explode(',', filter_input(INPUT_POST, 'patient_ids', FILTER_SANITIZE_SPECIAL_CHARS)));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $conn->prepare("DELETE FROM patients WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Selected patients deleted successfully.', 'count' => count($ids)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No patients were deleted.']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'import' && isset($_POST['patients'])) {
        try {
            $patients = json_decode($_POST['patients'], true);
            $count = 0;

            foreach ($patients as $patient) {
                $insertStmt = $conn->prepare("
                    INSERT INTO patients (
                        last_name, first_name, middle_name, gender, address, category, grade_year, program_section,
                        guardian_name, guardian_contact, other_contact, guardian_facebook, emergency_contact_name,
                        emergency_contact_number, pediatrician_name, pediatrician_contact, allergies, medical_conditions, notes
                    ) VALUES (
                        :last_name, :first_name, :middle_name, :gender, :address, :category, :grade_year, :program_section,
                        :guardian_name, :guardian_contact, :other_contact, :guardian_facebook, :emergency_contact_name,
                        :emergency_contact_number, :pediatrician_name, :pediatrician_contact, :allergies, :medical_conditions, :notes
                    )
                ");
                $insertStmt->execute([
                    'last_name' => $patient['last_name'] ?? null,
                    'first_name' => $patient['first_name'] ?? null,
                    'middle_name' => $patient['middle_name'] ?? null,
                    'gender' => $patient['gender'] ?? null,
                    'address' => $patient['address'] ?? null,
                    'category' => $patient['category'] ?? null,
                    'grade_year' => $patient['grade_year'] ?? null,
                    'program_section' => $patient['program_section'] ?? null,
                    'guardian_name' => $patient['guardian_name'] ?? null,
                    'guardian_contact' => $patient['guardian_contact'] ?? null,
                    'other_contact' => $patient['other_contact'] ?? null,
                    'guardian_facebook' => $patient['guardian_facebook'] ?? null,
                    'emergency_contact_name' => $patient['emergency_contact_name'] ?? null,
                    'emergency_contact_number' => $patient['emergency_contact_number'] ?? null,
                    'pediatrician_name' => $patient['pediatrician_name'] ?? null,
                    'pediatrician_contact' => $patient['pediatrician_contact'] ?? null,
                    'allergies' => $patient['allergies'] ?? null,
                    'medical_conditions' => $patient['medical_conditions'] ?? null,
                    'notes' => $patient['notes'] ?? null
                ]);
                $count++;
            }

            echo json_encode(['success' => true, 'message' => "$count patients imported successfully."]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    header('Location: manage-patients.php');
    exit;
}

// Fetch patients
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$selectedCategory = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$selectedGradeYear = filter_input(INPUT_GET, 'grade_year', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$selectedProgramSection = filter_input(INPUT_GET, 'program_section', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$selectedGender = filter_input(INPUT_GET, 'gender', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

$query = "SELECT * FROM patients WHERE 1=1";
$params = [];

if ($searchTerm) {
    $query .= " AND (last_name LIKE ? OR first_name LIKE ? OR middle_name LIKE ?)";
    $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
}

if ($selectedCategory) {
    $query .= " AND category = ?";
    $params[] = str_replace('-', ' ', ucwords($selectedCategory));
}

if ($selectedGradeYear) {
    $query .= " AND grade_year = ?";
    $params[] = $selectedGradeYear;
}

if ($selectedProgramSection) {
    $query .= " AND program_section = ?";
    $params[] = $selectedProgramSection;
}

if ($selectedGender) {
    $query .= " AND gender = ?";
    $params[] = $selectedGender;
}

$query .= " ORDER BY last_name, first_name, middle_name";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category details
$categoryDetails = null;
if ($selectedCategory) {
    foreach ($categories as $cat) {
        if (strtolower(str_replace(' ', '-', $cat['name'])) === strtolower($selectedCategory)) {
            $categoryDetails = $cat;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Manage Patients">
    <meta name="author" content="ICCB">
    <title>Manage Patients - ICCBI Smart Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <?php include '../includes/sscmslogo.php'; ?>
    <style>
        :root {
            --sidebar-width: 220px;
            --sidebar-collapsed-width: 50px;
        }
        body {
            font-family: 'Inter', sans-serif;
            font-size: 0.875rem;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .content {
            margin-left: var(--sidebar-width);
            padding: 1rem;
            min-height: 100vh;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #0f73ba 0%, #2c7be5 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            box-shadow: 0 4px 12px rgba(15, 115, 186, 0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dashboard-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dashboard-title i {
            opacity: 0.9;
        }
        .toolbar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .filter-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-grow: 1;
        }
        .filter-form select, .filter-form input {
            height: 30px;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .filter-form select:focus, .filter-form input:focus {
            border-color: #0f73ba;
            outline: none;
            box-shadow: 0 0 0 2px rgba(15,115,186,0.1);
        }
        .filter-form select {
            min-width: 100px;
        }
        .filter-form input {
            max-width: 150px;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .table-responsive {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .table th {
            background: #f0f7ff;
            color: #0f73ba;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-bottom: 2px solid #d1e8f8;
        }
        .table td {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            color: #334155;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background-color: #f0f7ff;
        }
        .table-bordered td, .table-bordered th {
            border-color: #e2e8f0;
        }
        .modal-content {
            border: none;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .modal-header {
            background: linear-gradient(135deg, #0f73ba, #2c7be5);
            color: white;
            font-size: 0.875rem;
            border-radius: 10px 10px 0 0;
            border-bottom: none;
            padding: 0.85rem 1.25rem;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.85;
        }
        .modal-body {
            padding: 1.25rem;
        }
        .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 0.75rem 1.25rem;
        }
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 0.8rem;
            border-radius: 6px;
            color: #1e293b;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0f73ba;
            box-shadow: 0 0 0 3px rgba(15,115,186,0.12);
            background-color: #ffffff;
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #475569;
            margin-bottom: 0.3rem;
        }
        h6.fw-medium {
            color: #0f73ba;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.4rem;
            margin-bottom: 0.75rem !important;
        }
        .btn-sm {
            font-size: 0.75rem;
            padding: 0.3rem 0.65rem;
            border-radius: 6px;
        }
        .btn-primary {
            background-color: #0f73ba;
            border-color: #0f73ba;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: #0d5a94;
            border-color: #0d5a94;
        }
        .btn-outline-primary {
            border-color: #0f73ba;
            color: #0f73ba;
        }
        .btn-outline-primary:hover, .btn-outline-primary.active {
            background-color: #0f73ba;
            border-color: #0f73ba;
            color: white;
        }
        .btn-outline-light {
            border-color: rgba(255,255,255,0.6);
            color: white;
        }
        .btn-outline-light:hover {
            background-color: rgba(255,255,255,0.15);
            border-color: white;
            color: white;
        }
        .toast-container {
            z-index: 1050;
        }
        .no-patients {
            text-align: center;
            padding: 2rem;
            color: #64748b;
            font-size: 0.875rem;
        }
        .no-patients i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #94a3b8;
            display: block;
        }
        .category-card {
            border-left: 4px solid;
            padding: 0.85rem 1rem;
            transition: transform 0.18s, box-shadow 0.18s;
            border-radius: 8px;
            background: #ffffff;
        }
        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(15,115,186,0.12);
        }
        .category-card .icon {
            font-size: 1.6rem;
            margin-bottom: 0.3rem;
            opacity: 0.85;
        }
        .category-card .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
        }
        .category-card .card-text {
            font-size: 0.73rem;
            color: #64748b;
        }
        .category-view-card.active, .category-view-list.active {
            display: block;
        }
        .category-view-card, .category-view-list {
            display: none;
        }
        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.75rem;
        }
        .is-invalid ~ .invalid-feedback {
            display: block;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .card-body {
            padding: 0.75rem;
        }
        #selectionCount {
            font-size: 0.75rem;
            color: #64748b;
            padding: 0.25rem 0.5rem;
            background: #f0f7ff;
            border-radius: 12px;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in         { animation: fadeInUp 0.4s ease both; }
        .anim-delay-1    { animation: fadeInUp 0.4s ease 0.08s both; }
        .anim-delay-2    { animation: fadeInUp 0.4s ease 0.16s both; }
        .anim-delay-3    { animation: fadeInUp 0.4s ease 0.24s both; }
        .anim-delay-4    { animation: fadeInUp 0.4s ease 0.32s both; }
        .anim-delay-5    { animation: fadeInUp 0.4s ease 0.40s both; }
        .anim-delay-6    { animation: fadeInUp 0.4s ease 0.48s both; }
        .anim-delay-7    { animation: fadeInUp 0.4s ease 0.56s both; }
        @media (max-width: 992px) {
            .content { margin-left: var(--sidebar-collapsed-width); }
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-form {
                flex-direction: column;
                gap: 0.5rem;
            }
            .filter-form select, .filter-form input {
                width: 100%;
            }
            .action-buttons {
                justify-content: flex-end;
            }
            .dashboard-header {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
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
                    <div>
                        <span class="dashboard-title">
                            <i class="fas fa-users"></i>
                            <?php if ($selectedCategory): ?>
                                <?= htmlspecialchars(str_replace('-', ' ', ucwords($selectedCategory))) ?>
                            <?php else: ?>
                                Manage Patients
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($selectedCategory): ?>
                        <a href="manage-patients.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                    <?php endif; ?>
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

                <?php if ($selectedCategory): ?>
                    <!-- Category Info -->
                    <div class="mb-3 d-flex align-items-center gap-2">
                        <p class="mb-0 text-muted small"><?= htmlspecialchars($categoryDetails['description'] ?? '') ?></p>
                        <span class="badge rounded-pill" style="background-color: <?= htmlspecialchars($categoryDetails['color'] ?? '#0f73ba') ?>; font-size: 0.7rem;">
                            <?= count($patients) ?> patient<?= count($patients) !== 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <!-- Toolbar with Filters -->
                    <div class="toolbar anim-delay-1">
                        <form id="filterForm" class="filter-form">
                            <input type="text" class="form-control" id="filterName" placeholder="Search by name" value="<?= htmlspecialchars($searchTerm) ?>">
                            <select class="form-select" id="filterGender">
                                <option value="">All Genders</option>
                                <option value="Male" <?= $selectedGender === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $selectedGender === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= $selectedGender === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <select class="form-select" id="filterCategory">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars(str_replace(' ', '-', strtolower($cat['name']))) ?>" <?= $selectedCategory === str_replace(' ', '-', strtolower($cat['name'])) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select" id="filterGradeYear">
                                <option value="">All Grades/Years</option>
                                <?php foreach (array_unique(array_column($gradeYears, 'name')) as $gy): ?>
                                    <option value="<?= htmlspecialchars($gy) ?>" <?= $selectedGradeYear === $gy ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($gy) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select" id="filterProgramSection">
                                <option value="">All Programs/Sections</option>
                                <?php foreach (array_unique(array_column($programSections, 'name')) as $ps): ?>
                                    <option value="<?= htmlspecialchars($ps) ?>" <?= $selectedProgramSection === $ps ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ps) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="resetFilterBtn">
                                Clear
                            </button>
                        </form>
                        <div class="action-buttons">
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPatientModal" title="Add a new patient">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-warning btn-sm" id="editButton" disabled title="Edit selected patient">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-warning btn-sm" id="moveButton" disabled data-bs-toggle="modal" data-bs-target="#movePatientModal" title="Move or promote selected patients">
                                <i class="fas fa-arrows-alt"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" id="deleteButton" disabled title="Delete selected patients">
                                <i class="fas fa-trash"></i>
                            </button>
                            <span class="text-muted small" id="selectionCount">0 selected</span>
                        </div>
                    </div>

                    <!-- Patient List -->
                    <div class="card anim-delay-2">
                        <div class="card-body p-2">
                            <?php if (empty($patients)): ?>
                                <div class="no-patients">
                                    <i class="fas fa-users-slash"></i>
                                    <p>No patients found.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table id="patientsTable" class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll"></th>
                                                <th>Name</th>
                                                <th>Gender</th>
                                                <th>Grade/Year</th>
                                                <th>Program/Section</th>
                                                <th>Guardian Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($patients as $patient): ?>
                                                <tr data-id="<?= $patient['id'] ?>">
                                                    <td><input type="checkbox" class="patient-checkbox" value="<?= $patient['id'] ?>" data-patient='<?= json_encode($patient) ?>'></td>
                                                    <td>
                                                        <a href="view_patient_info.php?id=<?= $patient['id'] ?>" class="text-primary text-decoration-none">
                                                            <?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'][0] . '.' : '')) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($patient['gender'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($patient['grade_year'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($patient['program_section'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($patient['guardian_contact'] ?? '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Toolbar -->
                    <div class="toolbar">
                        <button class="btn btn-outline-primary btn-sm active" data-view="card" title="Card View">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button class="btn btn-outline-primary btn-sm" data-view="list" title="List View">
                            <i class="fas fa-list"></i>
                        </button>
                        <button class="btn btn-outline-primary btn-sm" id="importExcelBtn" title="Import from Excel">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        <input type="file" id="excelFileInput" accept=".xlsx, .xls" style="display: none;">
                    </div>

                    <!-- Category Cards -->
                    <div class="category-view-card active">
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-2 mb-2">
                            <?php foreach ($categories as $index => $category): ?>
                                <div class="col anim-delay-<?= min($index + 1, 7) ?>">
                                    <a href="?category=<?= strtolower(str_replace(' ', '-', $category['name'])) ?>" class="text-decoration-none">
                                        <div class="card category-card" style="border-left-color: <?= $category['color'] ?>;">
                                            <i class="fas <?= htmlspecialchars($category['icon']) ?> icon"></i>
                                            <h5 class="card-title"><?= htmlspecialchars($category['name']) ?></h5>
                                            <p class="card-text"><?= htmlspecialchars($category['description']) ?></p>
                                            <span class="badge" style="background-color: <?= $category['color'] ?>; font-size: 0.75rem;">
                                                <?php
                                                $stmt = $conn->prepare("SELECT COUNT(*) FROM patients WHERE category = ?");
                                                $stmt->execute([$category['name']]);
                                                $count = $stmt->fetchColumn();
                                                echo $count . ' patient' . ($count !== 1 ? 's' : '');
                                                ?>
                                            </span>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Category List -->
                    <div class="category-view-list">
                        <div class="card">
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Description</th>
                                                <th>Patients</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                                <tr>
                                                    <td>
                                                        <a href="?category=<?= strtolower(str_replace(' ', '-', $category['name'])) ?>" class="text-primary">
                                                            <?= htmlspecialchars($category['name']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($category['description']) ?></td>
                                                    <td>
                                                        <?php
                                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM patients WHERE category = ?");
                                                        $stmt->execute([$category['name']]);
                                                        $count = $stmt->fetchColumn();
                                                        echo $count . ' patient' . ($count !== 1 ? 's' : '');
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add Patient Modal -->
                <div class="modal fade" id="addPatientModal" tabindex="-1" aria-labelledby="addPatientModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-medium small" id="addPatientModalLabel">Add New Patient</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addPatientForm" method="post">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" id="address" name="address">
                                    <div class="row">
                                        <!-- Personal Information -->
                                        <div class="col-md-6">
                                            <h6 class="fw-medium mb-3">Personal Information</h6>
                                            <div class="mb-2">
                                                <label for="last_name" class="form-label small">Last Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="last_name" name="last_name" required>
                                            </div>
                                            <div class="mb-2">
                                                <label for="first_name" class="form-label small">First Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="first_name" name="first_name" required>
                                            </div>
                                            <div class="mb-2">
                                                <label for="middle_name" class="form-label small">Middle Name</label>
                                                <input type="text" class="form-control form-control-sm" id="middle_name" name="middle_name">
                                            </div>
                                            <div class="mb-2">
                                                <label for="gender" class="form-label small">Gender <span class="text-danger">*</span></label>
                                                <select class="form-select form-select-sm" id="gender" name="gender" required>
                                                    <option value="">Select Gender</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <label for="municipality" class="form-label small">Municipality/City <span class="text-danger">*</span></label>
                                                <select class="form-select form-select-sm" id="municipality" name="municipality" required>
                                                    <option value="">Loading...</option>
                                                </select>
                                                <div class="invalid-feedback">Please select a municipality.</div>
                                            </div>
                                            <div class="mb-2">
                                                <label for="barangay" class="form-label small">Barangay <span class="text-danger">*</span></label>
                                                <select class="form-select form-select-sm" id="barangay" name="barangay" required disabled>
                                                    <option value="">Select Municipality First</option>
                                                </select>
                                                <div class="invalid-feedback">Please select a barangay.</div>
                                            </div>
                                        </div>
                                        <!-- Academic Information -->
                                        <div class="col-md-6">
                                            <h6 class="fw-medium mb-3">Academic Information</h6>
                                            <div class="mb-2">
                                                <label for="category" class="form-label small">Category <span class="text-danger">*</span></label>
                                                <select class="form-select form-select-sm" id="category" name="category" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $cat): ?>
                                                        <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $selectedCategory && str_replace('-', ' ', ucwords($selectedCategory)) === $cat['name'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($cat['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div id="academicFields">
                                                <div class="mb-2">
                                                    <label for="grade_year" class="form-label small">Grade/Year</label>
                                                    <select class="form-select form-select-sm" id="grade_year" name="grade_year"></select>
                                                    <div id="grade_year_error" class="text-danger small mt-1 d-none">No grade/year options available for this category.</div>
                                                </div>
                                                <div class="mb-2">
                                                    <label for="program_section" class="form-label small">Program/Section</label>
                                                    <select class="form-select form-select-sm" id="program_section" name="program_section"></select>
                                                    <div id="program_section_error" class="text-danger small mt-1 d-none">No program/section options available for this category.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Guardian and Contact Information -->
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <h6 class="fw-medium mb-3">Guardian & Contact Information</h6>
                                            <div class="mb-2">
                                                <label for="guardian_name" class="form-label small">Guardian Name</label>
                                                <input type="text" class="form-control form-control-sm" id="guardian_name" name="guardian_name">
                                            </div>
                                            <div class="mb-2">
                                                <label for="guardian_contact" class="form-label small">Guardian Contact</label>
                                                <input type="text" class="form-control form-control-sm" id="guardian_contact" name="guardian_contact">
                                                <div id="guardian_contact_error" class="text-danger small mt-1 d-none">Guardian contact is required.</div>
                                            </div>
                                            <div class="mb-2">
                                                <label for="other_contact" class="form-label small">Other Contact Number</label>
                                                <input type="text" class="form-control form-control-sm" id="other_contact" name="other_contact">
                                            </div>
                                            <div class="mb-2">
                                                <label for="guardian_facebook" class="form-label small">Guardian Facebook</label>
                                                <input type="text" class="form-control form-control-sm" id="guardian_facebook" name="guardian_facebook">
                                            </div>
                                            <div class="mb-2">
                                                <label for="emergency_contact_name" class="form-label small">Emergency Contact Name</label>
                                                <input type="text" class="form-control form-control-sm" id="emergency_contact_name" name="emergency_contact_name">
                                            </div>
                                            <div class="mb-2">
                                                <label for="emergency_contact_number" class="form-label small">Emergency Contact Number</label>
                                                <input type="text" class="form-control form-control-sm" id="emergency_contact_number" name="emergency_contact_number">
                                            </div>
                                        </div>
                                        <!-- Medical Information -->
                                        <div class="col-md-6">
                                            <h6 class="fw-medium mb-3">Medical Information</h6>
                                            <div class="mb-2">
                                                <label for="pediatrician_name" class="form-label small">Pediatrician Name</label>
                                                <input type="text" class="form-control form-control-sm" id="pediatrician_name" name="pediatrician_name">
                                            </div>
                                            <div class="mb-2">
                                                <label for="pediatrician_contact" class="form-label small">Pediatrician Contact</label>
                                                <input type="text" class="form-control form-control-sm" id="pediatrician_contact" name="pediatrician_contact">
                                            </div>
                                            <div class="mb-2">
                                                <label for="allergies" class="form-label small">Allergies</label>
                                                <textarea class="form-control form-control-sm" id="allergies" name="allergies" rows="2"></textarea>
                                            </div>
                                            <div class="mb-2">
                                                <label for="medical_conditions" class="form-label small">Medical Conditions</label>
                                                <textarea class="form-control form-control-sm" id="medical_conditions" name="medical_conditions" rows="2"></textarea>
                                            </div>
                                            <div class="mb-2">
                                                <label for="notes" class="form-label small">Notes</label>
                                                <textarea class="form-control form-control-sm" id="notes" name="notes" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2 mt-4">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-save"></i> Save
                                            <span id="addSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Move Patients Modal -->
                <div class="modal fade" id="movePatientModal" tabindex="-1" aria-labelledby="movePatientModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-medium small" id="movePatientModalLabel">Move/Promote Patients</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="movePatientForm" method="post">
                                    <input type="hidden" name="action" value="move">
                                    <input type="hidden" id="move_patient_ids" name="patient_ids">
                                    <div class="mb-2">
                                        <label for="new_category" class="form-label small">New Category <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="new_category" name="new_category" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>">
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div id="move_academicFields">
                                        <div class="mb-2">
                                            <label for="new_grade_year" class="form-label small">New Grade/Year</label>
                                            <select class="form-select form-select-sm" id="new_grade_year" name="new_grade_year"></select>
                                            <div id="move_grade_year_error" class="text-danger small mt-1 d-none">No grade/year options available for this category.</div>
                                        </div>
                                        <div class="mb-2">
                                            <label for="new_program_section" class="form-label small">New Program/Section</label>
                                            <select class="form-select form-select-sm" id="new_program_section" name="new_program_section"></select>
                                            <div id="move_program_section_error" class="text-danger small mt-1 d-none">No program/section options available for this category.</div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-arrows-alt"></i> Move/Promote
                                            <span id="moveSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delete Patients Modal -->
                <div class="modal fade" id="deletePatientModal" tabindex="-1" aria-labelledby="deletePatientModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-medium small" id="deletePatientModalLabel">Confirm Delete</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="deletePatientForm" method="post">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" id="delete_patient_ids" name="patient_ids">
                                    <p class="small">Are you sure you want to delete the selected patient(s)? This action cannot be undone.</p>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                            <span id="deleteSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Modal -->
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-medium small" id="confirmModalLabel"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body small" id="confirmModalBody"></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary btn-sm" id="confirmActionBtn">Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        const gradeYears = <?= json_encode($gradeYears) ?>;
        const programSections = <?= json_encode($programSections) ?>;
        const academicCategories = <?= json_encode($academicCategories) ?>;
        const batangasCode = "041000000";

        function showToast(message, type = 'success') {
            const toastContainer = $('.toast-container');
            const toast = $(`
                <div class="toast align-items-center text-bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);
            toastContainer.append(toast);
            toast.toast({ delay: 3000 });
            toast.toast('show');
            setTimeout(() => toast.remove(), 3500);
        }

        function resetFilterForm() {
            $('#filterForm')[0].reset();
            $('#filterName').val('');
            $('#filterGender').val('');
            $('#filterCategory').val('');
            $('#filterGradeYear').val('');
            $('#filterProgramSection').val('');
            window.location.href = 'manage-patients.php';
        }

        function populateDropdowns(category, gradeYearSelect, programSectionSelect, gradeYearError, programSectionError) {
            const gradeYearOptions = gradeYears.filter(gy => gy.category === category);
            const programSectionOptions = programSections.filter(ps => ps.category === category);

            $(gradeYearSelect).html('<option value="">Select Grade/Year</option>');
            gradeYearOptions.forEach(gy => $(gradeYearSelect).append(`<option value="${gy.name}">${gy.name}</option>`));

            $(programSectionSelect).html('<option value="">Select Program/Section</option>');
            programSectionOptions.forEach(ps => $(programSectionSelect).append(`<option value="${ps.name}">${ps.name}</option>`));

            if (gradeYearOptions.length === 0) {
                $(gradeYearError).removeClass('d-none');
                $(gradeYearSelect).prop('disabled', true);
            } else {
                $(gradeYearError).addClass('d-none');
                $(gradeYearSelect).prop('disabled', false);
            }

            if (programSectionOptions.length === 0) {
                $(programSectionError).removeClass('d-none');
                $(programSectionSelect).prop('disabled', true);
            } else {
                $(programSectionError).addClass('d-none');
                $(programSectionSelect).prop('disabled', false);
            }
        }

        function toggleFields(category, academicFields, gradeYearSelect, programSectionSelect, gradeYearError, programSectionError) {
            const isAcademic = academicCategories.includes(category);
            $(academicFields).toggle(isAcademic);
            if (isAcademic) {
                populateDropdowns(category, gradeYearSelect, programSectionSelect, gradeYearError, programSectionError);
                $(gradeYearSelect).prop('required', true);
                $(programSectionSelect).prop('required', true);
            } else {
                $(gradeYearSelect).html('<option value="">Select Grade/Year</option>');
                $(programSectionSelect).html('<option value="">Select Program/Section</option>');
                $(gradeYearSelect).prop('required', false);
                $(programSectionSelect).prop('required', false);
                $(gradeYearError).addClass('d-none');
                $(programSectionError).addClass('d-none');
            }
        }

        function submitForm(formId, title, message) {
            const $form = $(`#${formId}`);
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
                showToast('Please fill out all required fields.', 'danger');
                return;
            }

            $('#confirmModalLabel').text(title);
            $('#confirmModalBody').text(message);
            const confirmModal = new bootstrap.Modal('#confirmModal');
            confirmModal.show();

            $('#confirmActionBtn').off('click').on('click', function() {
                $submitButton.prop('disabled', true);
                $spinner.removeClass('d-none');

                $.ajax({
                    url: $form.attr('action') || window.location.href,
                    method: $form.attr('method'),
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            $submitButton.prop('disabled', false);
                            $spinner.addClass('d-none');
                            showToast('Error: ' + response.message, 'danger');
                        }
                    },
                    error: function(xhr) {
                        $submitButton.prop('disabled', false);
                        $spinner.addClass('d-none');
                        showToast('Error: ' + (xhr.responseJSON?.message || 'An error occurred'), 'danger');
                    }
                });

                confirmModal.hide();
            });
        }

        $(document).ready(function() {
            // Initialize DataTable
            const table = $('#patientsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[1, 'asc']],
                columnDefs: [{ orderable: false, targets: 0 }],
                language: { search: "", searchPlaceholder: "Search patients..." }
            });

            // Category View Toggle
            $('[data-view]').on('click', function() {
                $('[data-view]').removeClass('active');
                $(this).addClass('active');
                $('.category-view-card, .category-view-list').removeClass('active');
                $(`.category-view-${$(this).data('view')}`).addClass('active');
            });

            // Address Selector
            const $municipalitySelect = $('#municipality');
            const $barangaySelect = $('#barangay');
            const $addressInput = $('#address');

            function loadMunicipalities() {
                $municipalitySelect.html('<option value="">Loading...</option>');
                $.getJSON(`https://psgc.gitlab.io/api/provinces/${batangasCode}/cities-municipalities/`, function(municipalities) {
                    $municipalitySelect.html('<option value="">Select Municipality/City</option>');
                    municipalities.forEach(muni => {
                        $municipalitySelect.append(`<option value="${muni.code}">${muni.name}</option>`);
                    });
                }).fail(function() {
                    $municipalitySelect.html('<option value="">Error loading municipalities</option>');
                });
            }

            function loadBarangays(municipalityCode) {
                $barangaySelect.html('<option value="">Loading Barangays...</option>').prop('disabled', true);
                $.getJSON(`https://psgc.gitlab.io/api/cities-municipalities/${municipalityCode}/barangays/`, function(barangays) {
                    $barangaySelect.html('<option value="">Select Barangay</option>');
                    barangays.forEach(barangay => {
                        $barangaySelect.append(`<option value="${barangay.code}">${barangay.name}</option>`);
                    });
                    $barangaySelect.prop('disabled', false);
                }).fail(function() {
                    $barangaySelect.html('<option value="">Error loading barangays</option>');
                    $barangaySelect.prop('disabled', true);
                });
            }

            function updateAddress() {
                const barangayText = $barangaySelect.find('option:selected').text();
                const municipalityText = $municipalitySelect.find('option:selected').text();
                if (barangayText && municipalityText && barangayText !== 'Select Barangay' && municipalityText !== 'Select Municipality/City') {
                    $addressInput.val(`${barangayText}, ${municipalityText}, Batangas`);
                } else {
                    $addressInput.val('');
                }
            }

            $('#addPatientModal').on('show.bs.modal', function() {
                loadMunicipalities();
                $barangaySelect.html('<option value="">Select Municipality First</option>').prop('disabled', true);
                $addressInput.val('');
                const selectedCategory = '<?php echo str_replace('-', ' ', ucwords($selectedCategory)); ?>';
                if (selectedCategory) {
                    $('#category').val(selectedCategory);
                    toggleFields(selectedCategory, '#academicFields', '#grade_year', '#program_section', '#grade_year_error', '#program_section_error');
                } else {
                    $('#category').val('');
                    toggleFields('', '#academicFields', '#grade_year', '#program_section', '#grade_year_error', '#program_section_error');
                }
            });

            $municipalitySelect.on('change', function() {
                const code = $(this).val();
                if (code) {
                    loadBarangays(code);
                } else {
                    $barangaySelect.html('<option value="">Select Municipality First</option>').prop('disabled', true);
                    $addressInput.val('');
                }
            });

            $barangaySelect.on('change', updateAddress);
            $municipalitySelect.on('change', updateAddress);

            $('#addPatientForm').on('submit', function(e) {
                e.preventDefault();
                if (!$addressInput.val()) {
                    $municipalitySelect.addClass('is-invalid');
                    $barangaySelect.addClass('is-invalid');
                    showToast('Please select both a municipality and a barangay.', 'danger');
                    return;
                }
                submitForm('addPatientForm', 'Add Patient', 'Are you sure you want to add this patient?');
            });

            // Category Dropdown
            $('#category').on('change', function() {
                toggleFields($(this).val(), '#academicFields', '#grade_year', '#program_section', '#grade_year_error', '#program_section_error');
            });

            // Checkbox Selection
            $('#selectAll').on('change', function() {
                $('.patient-checkbox').prop('checked', this.checked);
                updateSelection();
            });

            $(document).on('change', '.patient-checkbox', updateSelection);

            function updateSelection() {
                const selectedCount = $('.patient-checkbox:checked').length;
                $('#selectionCount').text(`${selectedCount} selected`);
                $('#editButton').prop('disabled', selectedCount !== 1);
                $('#moveButton, #deleteButton').prop('disabled', selectedCount === 0);
            }

            // Filter Form
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                const params = new URLSearchParams();
                if ($('#filterName').val()) params.append('search', $('#filterName').val());
                if ($('#filterGender').val()) params.append('gender', $('#filterGender').val());
                if ($('#filterCategory').val()) params.append('category', $('#filterCategory').val());
                if ($('#filterGradeYear').val()) params.append('grade_year', $('#filterGradeYear').val());
                if ($('#filterProgramSection').val()) params.append('program_section', $('#filterProgramSection').val());
                window.location.href = `manage-patients.php?${params.toString()}`;
            });

            $('#resetFilterBtn').on('click', resetFilterForm);

            // Edit Button
            $('#editButton').on('click', function() {
                const selected = $('.patient-checkbox:checked');
                if (selected.length === 1) {
                    const patientId = selected.val();
                    window.location.href = `edit_patient.php?id=${patientId}${ '<?php echo $selectedCategory ?>' ? '&category=' + encodeURIComponent('<?php echo $selectedCategory ?>') : ''}`;
                }
            });

            // Move Button
            $('#moveButton').on('click', function() {
                const selectedIds = $('.patient-checkbox:checked').map(function() { return $(this).val(); }).get();
                $('#move_patient_ids').val(selectedIds.join(','));
                $('#new_category').val('');
                toggleFields('', '#move_academicFields', '#new_grade_year', '#new_program_section', '#move_grade_year_error', '#move_program_section_error');
            });

            $('#new_category').on('change', function() {
                toggleFields($(this).val(), '#move_academicFields', '#new_grade_year', '#new_program_section', '#move_grade_year_error', '#move_program_section_error');
            });

            $('#movePatientForm').on('submit', function(e) {
                e.preventDefault();
                const selectedCount = $('.patient-checkbox:checked').length;
                submitForm('movePatientForm', 'Move/Promote Patients', `Are you sure you want to move/promote ${selectedCount} patient${selectedCount > 1 ? 's' : ''}?`);
            });

            // Delete Button
            $('#deleteButton').on('click', function() {
                const selectedIds = $('.patient-checkbox:checked').map(function() { return $(this).val(); }).get();
                if (selectedIds.length === 0) return;
                $('#delete_patient_ids').val(selectedIds.join(','));
                $('#deletePatientModal').modal('show');
            });

            $('#deletePatientForm').on('submit', function(e) {
                e.preventDefault();
                const selectedIds = $('.patient-checkbox:checked').map(function() { return $(this).val(); }).get();
                if (selectedIds.length === 0) return;

                $('#confirmModalLabel').text('Delete Patients');
                $('#confirmModalBody').text(`Are you sure you want to delete ${selectedIds.length} patient${selectedIds.length > 1 ? 's' : ''}? This action cannot be undone.`);
                const confirmModal = new bootstrap.Modal('#confirmModal');
                confirmModal.show();

                $('#confirmActionBtn').off('click').on('click', function() {
                    const $submitButton = $('#deletePatientForm').find('[type=submit]');
                    const $spinner = $submitButton.find('.spinner-border');

                    $submitButton.prop('disabled', true);
                    $spinner.removeClass('d-none');

                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            action: 'delete',
                            patient_ids: selectedIds.join(',')
                        },
                        dataType: 'json',
                        success: function(response) {
                            $submitButton.prop('disabled', false);
                            $spinner.addClass('d-none');
                            if (response.success) {
                                showToast(`${response.count} patient${response.count > 1 ? 's' : ''} deleted successfully.`, 'success');
                                selectedIds.forEach(id => table.row($(`tr[data-id="${id}"]`)).remove());
                                table.draw();
                            } else {
                                showToast('Error: ' + (response.message || 'Deletion failed.'), 'danger');
                            }
                            $('#deletePatientModal').modal('hide');
                        },
                        error: function(xhr) {
                            $submitButton.prop('disabled', false);
                            $spinner.addClass('d-none');
                            showToast('Error: ' + (xhr.responseJSON?.message || 'An error occurred'), 'danger');
                            $('#deletePatientModal').modal('hide');
                        }
                    });

                    confirmModal.hide();
                });
            });

            // Excel Import Functionality
            $('#importExcelBtn').on('click', function() {
                $('#excelFileInput').click();
            });

            $('#excelFileInput').on('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = function(e) {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheet];
                    const jsonData = XLSX.utils.sheet_to_json(worksheet);

                    if (jsonData.length === 0) {
                        showToast('The Excel file is empty.', 'danger');
                        return;
                    }

                    const requiredFields = ['last_name', 'first_name', 'gender', 'category'];
                    const firstRow = jsonData[0];
                    const missingFields = requiredFields.filter(field => !Object.keys(firstRow).includes(field));
                    if (missingFields.length > 0) {
                        showToast(`Missing required fields in Excel: ${missingFields.join(', ')}`, 'danger');
                        return;
                    }

                    const validCategories = <?= json_encode(array_map(function($cat) { return $cat['name']; }, $categories)) ?>;
                    const invalidCategories = jsonData.filter(row => !validCategories.includes(row.category));
                    if (invalidCategories.length > 0) {
                        showToast('Invalid categories found in Excel: ' + invalidCategories.map(row => row.category).join(', '), 'danger');
                        return;
                    }

                    $('#confirmModalLabel').text('Import Patients');
                    $('#confirmModalBody').text(`Are you sure you want to import ${jsonData.length} patient${jsonData.length > 1 ? 's' : ''} from the Excel file?`);
                    const confirmModal = new bootstrap.Modal('#confirmModal');
                    confirmModal.show();

                    $('#confirmActionBtn').off('click').on('click', function() {
                        const $spinner = $('<span class="spinner-border spinner-border-sm ms-1" role="status"></span>');
                        $('#importExcelBtn').append($spinner).prop('disabled', true);

                        $.ajax({
                            url: window.location.href,
                            method: 'POST',
                            data: {
                                action: 'import',
                                patients: JSON.stringify(jsonData)
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showToast(response.message, 'success');
                                    setTimeout(() => window.location.reload(), 1000);
                                } else {
                                    $spinner.remove();
                                    $('#importExcelBtn').prop('disabled', false);
                                    showToast('Error: ' + response.message, 'danger');
                                }
                            },
                            error: function(xhr) {
                                $spinner.remove();
                                $('#importExcelBtn').prop('disabled', false);
                                showToast('Error: ' + (xhr.responseJSON?.message || 'An error occurred during import'), 'danger');
                            }
                        });

                        confirmModal.hide();
                    });

                    $('#excelFileInput').val('');
                };
                reader.readAsArrayBuffer(file);
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>