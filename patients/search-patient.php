<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Fetch unique grade/year and program/section values for filters
$grades = $conn->query("SELECT DISTINCT grade_year FROM patients WHERE grade_year IS NOT NULL ORDER BY grade_year")->fetchAll(PDO::FETCH_COLUMN);
$programs = $conn->query("SELECT DISTINCT program_section FROM patients WHERE program_section IS NOT NULL ORDER BY program_section")->fetchAll(PDO::FETCH_COLUMN);

// Fetch patients based on search and filters
$patients = [];
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$gradeFilter = filter_input(INPUT_GET, 'grade', FILTER_SANITIZE_STRING) ?? '';
$programFilter = filter_input(INPUT_GET, 'program', FILTER_SANITIZE_STRING) ?? '';

$query = "SELECT * FROM patients WHERE 1=1";
$params = [];

if ($searchTerm) {
    $query .= " AND (last_name LIKE ? OR first_name LIKE ? OR middle_name LIKE ?)";
    $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
}

if ($gradeFilter) {
    $query .= " AND grade_year = ?";
    $params[] = $gradeFilter;
}

if ($programFilter) {
    $query .= " AND program_section = ?";
    $params[] = $programFilter;
}

$query .= " ORDER BY last_name, first_name, middle_name";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Search Patients">
    <meta name="author" content="ICCB">
    <title>Search Patients - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <?php include '../includes/sscmslogo.php'; ?>
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --secondary: #4b5563;
            --secondary-dark: #374151;
            --background: #f3f4f6;
            --card-bg: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --border-soft: rgba(209, 213, 219, 0.7);
            --accent-purple: #7c3aed;
            --accent-green: #059669;
            --sidebar-width: 200px;
            --sidebar-collapsed-width: 50px;
            --header-height: 50px;
            --card-border-radius: 12px;
            --transition-speed: 0.2s;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.5;
            overflow-x: hidden;
            font-size: 0.85rem;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            padding-top: 1.5rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container-fluid {
            max-width: 1440px;
            padding: 0 1.25rem;
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .dashboard-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
        }

        .dashboard-title i {
            color: var(--primary);
            font-size: 1.1rem;
            margin-right: 0.5rem;
            background-color: #e0f2fe;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-breadcrumb {
            padding: 0.75rem 1rem;
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1.25rem;
            font-size: 0.8rem;
        }

        .custom-breadcrumb .breadcrumb-item {
            color: var(--text-secondary);
        }

        .custom-breadcrumb .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 600;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid var(--border-soft);
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .table {
            color: var(--text-primary);
            font-size: 0.75rem;
            border: 1px solid var(--border-soft);
            border-radius: 6px;
            overflow: hidden;
        }

        .table th, .table td {
            padding: 0.6rem;
            border: 1px solid var(--border-soft);
            vertical-align: middle;
        }

        .table th {
            font-weight: 600;
            background: #f9fafb;
            color: var(--text-primary);
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .action-link {
            text-decoration: none;
            margin-right: 1.2rem;
            font-size: 0.75rem;
            font-weight: 500;
            transition: opacity var(--transition-speed);
        }

        .action-link:hover {
            opacity: 0.8;
        }

        .action-link.profile {
            color: var(--accent-purple);
        }

        .action-link.health {
            color: var(--accent-green);
        }

        .action-link i {
            margin-right: 0.4rem;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 1.25rem;
            align-items: flex-end;
            border: 1px solid var(--border-soft);
        }

        .filter-group {
            flex: 1;
            min-width: 220px;
            padding-right: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid var(--border-soft);
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            height: 38px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.4rem;
        }

        .btn-filter {
            background-color: var(--primary);
            border: 1px solid var(--border-soft);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.8rem;
            color: white;
            height: 38px;
            display: inline-flex;
            align-items: center;
            transition: all var(--transition-speed);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-filter:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .btn-reset {
            color: var(--secondary);
            border: 1px solid var(--border-soft);
            background: transparent;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.8rem;
            height: 38px;
            display: inline-flex;
            align-items: center;
            transition: all var(--transition-speed);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-reset:hover {
            background-color: #e5e7eb;
            border-color: var(--secondary-dark);
            color: var(--secondary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .btn-filter i, .btn-reset i {
            margin-right: 0.4rem;
        }

        .no-patients {
            text-align: center;
            padding: 2.5rem;
            color: var(--text-secondary);
        }

        .no-patients i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }

        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .text-accent-purple {
            color: var(--accent-purple);
        }

        .text-accent-green {
            color: var(--accent-green);
        }

        .text-accent-secondary {
            color: var(--secondary);
        }

        @media (max-width: 992px) {
            :root {
                --sidebar-width: var(--sidebar-collapsed-width);
            }
            .content {
                margin-left: var(--sidebar-width);
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 1.5rem;
            }
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group, .toolbar .form-control, .toolbar .form-select, .toolbar .btn-filter, .toolbar .btn-reset {
                width: 100%;
                padding-right: 0;
            }
        }

        @media (max-width: 576px) {
            .dashboard-title {
                font-size: 1.2rem;
            }
            .table {
                font-size: 0.7rem;
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
                <div class="dashboard-header">
                    <h1 class="dashboard-title">
                         Patient Search
                    </h1>
                </div>

                <!-- Breadcrumb -->
             

                <!-- Toolbar with Search and Filters -->
                <div class="toolbar">
                    <div class="filter-group">
                        <label for="searchInput" class="form-label">Search by Name</label>
                        <input type="text" class="form-control" id="searchInput" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Enter name...">
                    </div>
                    <div class="filter-group">
                        <label for="gradeFilter" class="form-label">Grade/Year</label>
                        <select class="form-select" id="gradeFilter" name="grade">
                            <option value="">All Grades</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?= htmlspecialchars($grade) ?>" <?= $gradeFilter === $grade ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($grade) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="programFilter" class="form-label">Program/Section</label>
                        <select class="form-select" id="programFilter" name="program">
                            <option value="">All Programs</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= htmlspecialchars($program) ?>" <?= $programFilter === $program ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($program) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group d-flex gap-2">
                        <button class="btn-filter" onclick="applyFilters()">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button class="btn-reset" onclick="resetFilters()">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- Patient List -->
                <div class="card fade-in">
                    <div class="card-body">
                        <?php if (empty($patients)): ?>
                            <div class="no-patients fade-in">
                                <i class="fas fa-users-slash"></i>
                                <p>No patients found</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="patientsTable" class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Gender</th>
                                            <th>Grade/Year</th>
                                            <th>Program/Section</th>
                                            <th>Guardian Contact</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patients as $patient): ?>
                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] : '')) ?>
                                                </td>
                                                <td class="text-accent-purple"><?= htmlspecialchars($patient['gender']) ?></td>
                                                <td class="text-accent-green"><?= htmlspecialchars($patient['grade_year'] ?: 'N/A') ?></td>
                                                <td class="text-accent-secondary"><?= htmlspecialchars($patient['program_section'] ?: 'N/A') ?></td>
                                                <td class="text-accent-secondary"><?= htmlspecialchars($patient['guardian_contact'] ?: 'N/A') ?></td>
                                                <td>
                                                    <a href="view_patient_info.php?id=<?= htmlspecialchars($patient['id']) ?>" class="action-link profile" title="View Profile">
                                                        <i class="fas fa-user"></i> Profile
                                                    </a>
                                                    <a href="patient_health_report.php?id=<?= htmlspecialchars($patient['id']) ?>" class="action-link health" title="View Health Record">
                                                        <i class="fas fa-file-medical"></i> Health Record
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#patientsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'asc']],
                language: { 
                    search: "",
                    searchPlaceholder: "Search within results...",
                    emptyTable: "No patients found matching the criteria"
                },
                columnDefs: [
                    { orderable: false, targets: 5 } // Disable sorting on Actions column
                ]
            });

            // Handle enter key for search
            $('#searchInput').on('keypress', function(e) {
                if (e.which === 13) {
                    applyFilters();
                }
            });
        });

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const grade = document.getElementById('gradeFilter').value;
            const program = document.getElementById('programFilter').value;
            
            const url = new URL(window.location);
            url.searchParams.set('search', search);
            url.searchParams.set('grade', grade);
            url.searchParams.set('program', program);
            window.location = url;
        }

        function resetFilters() {
            const url = new URL(window.location);
            url.searchParams.delete('search');
            url.searchParams.delete('grade');
            url.searchParams.delete('program');
            window.location = url;
        }
    </script>
</body>
</html>