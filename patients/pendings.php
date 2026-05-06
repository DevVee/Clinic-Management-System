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

// Handle actions (approve, delete, bulk approve, bulk delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && isset($_POST['id']) && $_POST['action'] === 'approve') {
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        try {
            $stmt = $conn->prepare("SELECT * FROM pending_patients WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($patient) {
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
                    'last_name' => $patient['last_name'],
                    'first_name' => $patient['first_name'],
                    'middle_name' => $patient['middle_name'] ?? null,
                    'gender' => $patient['gender'],
                    'address' => $patient['address'],
                    'category' => $patient['category'],
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

                $conn->prepare("DELETE FROM pending_patients WHERE id = :id")->execute(['id' => $id]);
                echo json_encode(['success' => true, 'message' => 'Patient approved successfully.', 'count' => 1]);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Patient not found.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    } elseif (isset($_POST['action']) && isset($_POST['id']) && $_POST['action'] === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        try {
            $stmt = $conn->prepare("DELETE FROM pending_patients WHERE id = :id");
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Patient deleted successfully.', 'count' => 1]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Patient not found or already deleted.']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'bulk_approve' && isset($_POST['patient_ids'])) {
        try {
            $ids = array_map('intval', explode(',', filter_input(INPUT_POST, 'patient_ids', FILTER_SANITIZE_SPECIAL_CHARS)));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $conn->prepare("SELECT * FROM pending_patients WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = count($patients);

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
                    'last_name' => $patient['last_name'],
                    'first_name' => $patient['first_name'],
                    'middle_name' => $patient['middle_name'] ?? null,
                    'gender' => $patient['gender'],
                    'address' => $patient['address'],
                    'category' => $patient['category'],
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
            }

            $stmt = $conn->prepare("DELETE FROM pending_patients WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Selected patients approved successfully.', 'count' => $count]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No patients were approved.']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['patient_ids'])) {
        try {
            $ids = array_map('intval', explode(',', filter_input(INPUT_POST, 'patient_ids', FILTER_SANITIZE_SPECIAL_CHARS)));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $conn->prepare("DELETE FROM pending_patients WHERE id IN ($placeholders)");
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
    } elseif (isset($_POST['action']) && $_POST['action'] === 'bulk_move' && isset($_POST['patient_ids'])) {
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

            $update_query = "UPDATE pending_patients SET category = ?, grade_year = ?, program_section = ? WHERE id IN ($placeholders)";
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
    }
    header('Location: pendings.php');
    exit;
}

// Fetch pending patients
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$selectedCategory = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$selectedGradeYear = filter_input(INPUT_GET, 'grade_year', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$selectedProgramSection = filter_input(INPUT_GET, 'program_section', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$selectedGender = filter_input(INPUT_GET, 'gender', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$selectedStatus = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

$query = "SELECT * FROM pending_patients WHERE 1=1";
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

if ($selectedStatus) {
    $query .= " AND status = ?";
    $params[] = $selectedStatus;
}

$query .= " ORDER BY last_name, first_name, middle_name";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$pendingPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <meta name="description" content="Clinic Management System - Manage Pending Patients">
    <meta name="author" content="ICCB">
    <title>Pending Patients - ICCBI Smart Clinic</title>
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        .filter-form select { min-width: 100px; }
        .filter-form input  { max-width: 150px; }
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
        .table tbody tr:hover { background-color: #f0f7ff; }
        .table-bordered td, .table-bordered th { border-color: #e2e8f0; }
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
        .modal-header .btn-close { filter: brightness(0) invert(1); opacity: 0.85; }
        .modal-body { padding: 1.25rem; }
        .modal-footer { border-top: 1px solid #e2e8f0; padding: 0.75rem 1.25rem; }
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 0.8rem;
            border-radius: 6px;
            color: #1e293b;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0f73ba;
            box-shadow: 0 0 0 3px rgba(15,115,186,0.12);
            background-color: #ffffff;
        }
        .form-label { font-size: 0.8rem; font-weight: 500; color: #475569; margin-bottom: 0.3rem; }
        h6.fw-medium {
            color: #0f73ba;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.4rem;
            margin-bottom: 0.75rem !important;
        }
        .btn-sm { font-size: 0.75rem; padding: 0.3rem 0.65rem; border-radius: 6px; }
        .btn-primary { background-color: #0f73ba; border-color: #0f73ba; transition: all 0.2s ease; }
        .btn-primary:hover { background-color: #0d5a94; border-color: #0d5a94; transform: translateY(-1px); }
        .btn-outline-primary { border-color: #0f73ba; color: #0f73ba; transition: all 0.2s ease; }
        .btn-outline-primary:hover, .btn-outline-primary.active { background-color: #0f73ba; border-color: #0f73ba; color: white; }
        .btn-outline-light { border-color: rgba(255,255,255,0.6); color: white; }
        .btn-outline-light:hover { background-color: rgba(255,255,255,0.15); border-color: white; color: white; }
        .toast-container { z-index: 1050; }
        .no-patients {
            text-align: center;
            padding: 2rem;
            color: #64748b;
            font-size: 0.875rem;
        }
        .no-patients i { font-size: 2rem; margin-bottom: 0.5rem; color: #94a3b8; display: block; }
        .category-card {
            border-left: 4px solid;
            padding: 0.85rem 1rem;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            border-radius: 8px;
            background: #ffffff;
        }
        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(15,115,186,0.12);
        }
        .category-card .icon { font-size: 1.6rem; margin-bottom: 0.3rem; opacity: 0.85; }
        .category-card .card-title { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
        .category-card .card-text { font-size: 0.73rem; color: #64748b; }
        .category-view-card.active, .category-view-list.active { display: block; }
        .category-view-card, .category-view-list { display: none; }
        .card { border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
        .card-body { padding: 0.75rem; }
        #selectionCount { font-size: 0.75rem; color: #64748b; padding: 0.25rem 0.5rem; background: #f0f7ff; border-radius: 12px; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in      { animation: fadeInUp 0.4s ease both; }
        .anim-delay-1 { animation: fadeInUp 0.4s ease 0.08s both; }
        .anim-delay-2 { animation: fadeInUp 0.4s ease 0.16s both; }
        .anim-delay-3 { animation: fadeInUp 0.4s ease 0.24s both; }
        .anim-delay-4 { animation: fadeInUp 0.4s ease 0.32s both; }
        .anim-delay-5 { animation: fadeInUp 0.4s ease 0.40s both; }
        .anim-delay-6 { animation: fadeInUp 0.4s ease 0.48s both; }
        .anim-delay-7 { animation: fadeInUp 0.4s ease 0.56s both; }
        @keyframes badgePulse {
            0%, 100% { box-shadow: 0 0 0 0   rgba(217,119,6,0.45); }
            50%       { box-shadow: 0 0 0 5px rgba(217,119,6,0); }
        }
        .badge-pulse { animation: badgePulse 2.4s ease infinite; }
        @media (max-width: 992px) { .content { margin-left: var(--sidebar-collapsed-width); } }
        @media (max-width: 768px) {
            .content { margin-left: 0; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .filter-form { flex-direction: column; gap: 0.5rem; }
            .filter-form select, .filter-form input { width: 100%; }
            .action-buttons { justify-content: flex-end; }
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
                    <div>
                        <span class="dashboard-title">
                            <i class="fas fa-clock"></i>
                            <?php if ($selectedCategory): ?>
                                <?= htmlspecialchars(str_replace('-', ' ', ucwords($selectedCategory))) ?>
                            <?php else: ?>
                                Pending Patients
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($selectedCategory): ?>
                        <a href="pendings.php" class="btn btn-outline-light btn-sm">
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
                    <div class="mb-3 d-flex align-items-center gap-2 anim-delay-1">
                        <p class="mb-0 text-muted small"><?= htmlspecialchars($categoryDetails['description'] ?? '') ?></p>
                        <span class="badge rounded-pill badge-pulse" style="background-color: <?= htmlspecialchars($categoryDetails['color'] ?? '#0f73ba') ?>; font-size: 0.7rem;">
                            <?= count($pendingPatients) ?> pending
                        </span>
                    </div>

                    <!-- Toolbar with Filters -->
                    <div class="toolbar anim-delay-2">
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
                            <select class="form-select" id="filterStatus">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $selectedStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="resetFilterBtn">
                                Clear
                            </button>
                        </form>
                        <div class="action-buttons">
                            <button class="btn btn-success btn-sm" id="bulkApproveButton" disabled title="Approve selected patients">
                                <i class="fas fa-check"></i>
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

                    <!-- Pending Patients Table -->
                    <div class="card anim-delay-3">
                        <div class="card-body p-2">
                            <?php if (empty($pendingPatients)): ?>
                                <div class="no-patients">
                                    <i class="fas fa-users-slash"></i>
                                    <p>No pending patients found.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table id="pendingPatientsTable" class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll"></th>
                                                <th>Name</th>
                                                <th>Gender</th>
                                                <th>Category</th>
                                                <th>Grade/Year</th>
                                                <th>Program/Section</th>
                                                <th>Address</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingPatients as $patient): ?>
                                                <tr data-id="<?= $patient['id'] ?>">
                                                    <td><input type="checkbox" class="patient-checkbox" value="<?= $patient['id'] ?>" data-patient='<?= json_encode($patient) ?>'></td>
                                                    <td>
                                                        <a href="view_pending_patient.php?id=<?= $patient['id'] ?>" class="text-primary text-decoration-none">
                                                            <?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'][0] . '.' : '')) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($patient['gender'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($patient['category'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($patient['grade_year'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($patient['program_section'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($patient['address'] ?? '-') ?></td>
                                                    <td class="text-warning">
                                                        <?= ucfirst($patient['status'] ?? 'pending') ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-success btn-sm approve-btn" data-id="<?= $patient['id'] ?>" title="Approve patient">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm delete-btn" data-id="<?= $patient['id'] ?>" title="Delete patient">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
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
                    <div class="toolbar anim-delay-1">
                        <button class="btn btn-outline-primary btn-sm active" data-view="card" title="Card View">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button class="btn btn-outline-primary btn-sm" data-view="list" title="List View">
                            <i class="fas fa-list"></i>
                        </button>
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
                                                $stmt = $conn->prepare("SELECT COUNT(*) FROM pending_patients WHERE category = ?");
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
                                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM pending_patients WHERE category = ?");
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
                                    <input type="hidden" name="action" value="bulk_move">
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
                                    <input type="hidden" name="action" value="bulk_delete">
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

        <?php include '../includes/footer.php' ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        const gradeYears = <?= json_encode($gradeYears) ?>;
        const programSections = <?= json_encode($programSections) ?>;
        const academicCategories = <?= json_encode($academicCategories) ?>;

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
                alert('Please fill out all required fields.');
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
                    url: window.location.href,
                    method: $form.attr('method'),
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
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

                confirmModal.hide();
            });
        }

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
            $('#filterStatus').val('');
            window.location.href = 'pendings.php';
        }

        $(document).ready(function() {
            // Initialize DataTable
            const table = $('#pendingPatientsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[1, 'asc']],
                columnDefs: [{ orderable: false, targets: [0, 8] }],
                language: { search: "", searchPlaceholder: "Search patients..." }
            });

            // Category View Toggle
            $('[data-view]').on('click', function() {
                $('[data-view]').removeClass('active');
                $(this).addClass('active');
                $('.category-view-card, .category-view-list').removeClass('active');
                $(`.category-view-${$(this).data('view')}`).addClass('active');
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
                $('#bulkApproveButton, #moveButton, #deleteButton').prop('disabled', selectedCount === 0);
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
                if ($('#filterStatus').val()) params.append('status', $('#filterStatus').val());
                window.location.href = `pendings.php?${params.toString()}`;
            });

            $('#resetFilterBtn').on('click', resetFilterForm);

            // Approve Button
            $(document).on('click', '.approve-btn', function() {
                const id = $(this).data('id');
                $('#confirmModalLabel').text('Approve Patient');
                $('#confirmModalBody').text('Are you sure you want to approve this patient?');
                const confirmModal = new bootstrap.Modal('#confirmModal');
                confirmModal.show();

                $('#confirmActionBtn').off('click').on('click', function() {
                    $.post(window.location.href, {
                        action: 'approve',
                        id: id
                    }, function(response) {
                        if (response.success) {
                            showToast(`${response.count} patient${response.count > 1 ? 's' : ''} approved successfully.`, 'success');
                            table.row($(`tr[data-id="${id}"]`)).remove().draw();
                        } else {
                            showToast('Error: ' + (response.message || 'Approval failed.'), 'danger');
                        }
                    }, 'json').fail(function(xhr) {
                        showToast('Error: ' + (xhr.responseJSON?.message || 'An error occurred'), 'danger');
                    });
                    confirmModal.hide();
                });
            });

            // Delete Button (Individual)
            $(document).on('click', '.delete-btn', function() {
                const id = $(this).data('id');
                $('#confirmModalLabel').text('Delete Patient');
                $('#confirmModalBody').text('Are you sure you want to delete this patient? This action cannot be undone.');
                const confirmModal = new bootstrap.Modal('#confirmModal');
                confirmModal.show();

                $('#confirmActionBtn').off('click').on('click', function() {
                    $.post(window.location.href, {
                        action: 'delete',
                        id: id
                    }, function(response) {
                        if (response.success) {
                            showToast(`${response.count} patient${response.count > 1 ? 's' : ''} deleted successfully.`, 'success');
                            table.row($(`tr[data-id="${id}"]`)).remove().draw();
                        } else {
                            showToast('Error: ' + (response.message || 'Deletion failed.'), 'danger');
                        }
                    }, 'json').fail(function(xhr) {
                        showToast('Error: ' + (xhr.responseJSON?.message || 'An error occurred'), 'danger');
                    });
                    confirmModal.hide();
                });
            });

            // Bulk Approve Button
            $('#bulkApproveButton').on('click', function() {
                const selectedIds = $('.patient-checkbox:checked').map(function() { return $(this).val(); }).get();
                if (selectedIds.length === 0) return;

                $('#confirmModalLabel').text('Bulk Approve Patients');
                $('#confirmModalBody').text(`Are you sure you want to approve ${selectedIds.length} patient${selectedIds.length > 1 ? 's' : ''}?`);
                const confirmModal = new bootstrap.Modal('#confirmModal');
                confirmModal.show();

                $('#confirmActionBtn').off('click').on('click', function() {
                    $.post(window.location.href, {
                        action: 'bulk_approve',
                        patient_ids: selectedIds.join(',')
                    }, function(response) {
                        if (response.success) {
                            showToast(`${response.count} patient${response.count > 1 ? 's' : ''} approved successfully.`, 'success');
                            selectedIds.forEach(id => table.row($(`tr[data-id="${id}"]`)).remove());
                            table.draw();
                        } else {
                            showToast('Error: ' + (response.message || 'Bulk approval failed.'), 'danger');
                        }
                    }, 'json').fail(function(xhr) {
                        showToast('Error: ' + (xhr.responseJSON?.message || 'An error occurred'), 'danger');
                    });
                    confirmModal.hide();
                });
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

            // Edit Button
            $('#editButton').on('click', function() {
                const selected = $('.patient-checkbox:checked');
                if (selected.length === 1) {
                    const patientId = selected.val();
                    window.location.href = `edit_pendings.php?id=${patientId}`;
                }
            });

            // Bulk Delete Button
            $('#deleteButton').on('click', function() {
                const selectedIds = $('.patient-checkbox:checked').map(function() { return $(this).val(); }).get();
                if (selectedIds.length === 0) return;

                $('#delete_patient_ids').val(selectedIds.join(','));
                $('#deletePatientModal').modal('show');
            });

            // Delete Form Submission
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
                            action: 'bulk_delete',
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

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>