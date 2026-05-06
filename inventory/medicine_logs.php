
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Get filter inputs
$filter_medicine = filter_input(INPUT_GET, 'medicine', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_year = filter_input(INPUT_GET, 'year', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_month = filter_input(INPUT_GET, 'month', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_program_section = filter_input(INPUT_GET, 'program_section', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$filter_grade_year = filter_input(INPUT_GET, 'grade_year', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

// Validate inputs
if ($filter_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $_SESSION['error_message'] = 'Invalid date format';
    $filter_date = '';
}
if ($filter_month && !preg_match('/^\d{1,2}$/', $filter_month)) {
    $_SESSION['error_message'] = 'Invalid month format';
    $filter_month = '';
}
if ($filter_year && !preg_match('/^\d{4}$/', $filter_year)) {
    $_SESSION['error_message'] = 'Invalid year format';
    $filter_year = '';
}
if ($filter_category && !in_array($filter_category, ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Faculty and Staff', 'Alumni', 'Visitor'])) {
    $_SESSION['error_message'] = 'Invalid category';
    $filter_category = '';
}
if ($filter_program_section && !preg_match('/^[A-Za-z0-9\s-]+$/', $filter_program_section)) {
    $_SESSION['error_message'] = 'Invalid program/section format';
    $filter_program_section = '';
}
if ($filter_grade_year && !preg_match('/^[A-Za-z0-9\s-]+$/', $filter_grade_year)) {
    $_SESSION['error_message'] = 'Invalid grade/year format';
    $filter_grade_year = '';
}
if ($filter_medicine && !preg_match('/^[A-Za-z0-9\s-]+$/', $filter_medicine)) {
    $_SESSION['error_message'] = 'Invalid medicine format';
    $filter_medicine = '';
}

// Fetch distinct categories, program sections, and grade years
try {
    $category_query = "SELECT DISTINCT category FROM patients ORDER BY category";
    $category_stmt = $conn->prepare($category_query);
    $category_stmt->execute();
    $categories = $category_stmt->fetchAll(PDO::FETCH_COLUMN);

    $program_query = "SELECT DISTINCT ps.name, ps.category 
                     FROM program_sections ps
                     JOIN patients p ON ps.name = p.program_section 
                     ORDER BY ps.name";
    $program_stmt = $conn->prepare($program_query);
    $program_stmt->execute();
    $program_sections = $program_stmt->fetchAll(PDO::FETCH_ASSOC);

    $grade_query = "SELECT DISTINCT name FROM grade_years ORDER BY name";
    $grade_stmt = $conn->prepare($grade_query);
    $grade_stmt->execute();
    $grade_years = $grade_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("[SSCMS Medicine Usage Logs] Error fetching filter options: " . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to load filter options: ' . $e->getMessage();
    $categories = [];
    $program_sections = [];
    $grade_years = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Medicine Usage Logs">
    <meta name="author" content="ICCB">
    <title>Medicine Usage Logs - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
            --border: #d1d5db;
            --success: #059669;
            --success-dark: #047857;
            --danger: #dc2626;
            --danger-dark: #b91c1c;
            --warning: #d97706;
            --warning-dark: #b45309;
            --bscs-maroon: #800000;
            --sidebar-width: 200px;
            --sidebar-collapsed-width: 50px;
            --header-height: 50px;
            --transition-speed: 0.2s;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.4;
            font-size: 0.8rem;
        }
        .content {
            margin-left: var(--sidebar-width);
            padding: 1rem;
            min-height: 100vh;
        }
        .card {
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border: none;
        }
        .card-header {
            background-color: var(--primary);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .table {
            font-size: 0.65rem;
        }
        .table th, .table td {
            padding: 0.3rem;
            border-color: var(--border);
            vertical-align: middle;
        }
        .table th {
            background: #f9fafb;
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 5px;
            border: 1px solid var(--border);
            font-size: 0.8rem;
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .btn-secondary {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }
        .btn-secondary:hover {
            background-color: var(--secondary-dark);
            border-color: var(--secondary-dark);
        }
        .btn-success {
            background-color: var(--success);
            border-color: var(--success);
        }
        .btn-success:hover {
            background-color: var(--success-dark);
            border-color: var(--success-dark);
        }
        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
        }
        .btn-danger:hover {
            background-color: var(--danger-dark);
            border-color: var(--danger-dark);
        }
        .toast-container {
            position: fixed;
            top: 0.75rem;
            right: 0.75rem;
        }
        @media (max-width: 992px) {
            .content {
                margin-left: var(--sidebar-collapsed-width);
            }
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 0.5rem;
            }
            .table {
                font-size: 0.6rem;
            }
            .card-title {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <div class="toast-container">
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

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="fas fa-clipboard-list me-1"></i> Medicine Usage Logs</h5>
                        <div>
                            <a href="../dashboard.php" class="text-decoration-none ms-2">
                                <img src="../assets/img/ICCLOGO.png" style="height: 20px;" alt="ICCB Logo">
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <form id="filterForm" action="medicine_logs.php" method="GET">
                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <label for="medicineFilter" class="form-label">Select Medicine</label>
                                    <select id="medicineFilter" name="medicine" class="form-select" title="Choose a medicine to view its usage logs">
                                        <option value="">All Medicines</option>
                                    </select>
                                    <div class="form-text">Select a medicine to filter logs</div>
                                </div>
                                <div class="col-md-3">
                                    <label for="yearFilter" class="form-label">Year</label>
                                    <select id="yearFilter" name="year" class="form-select" title="Filter logs by year">
                                        <option value="">All Years</option>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear; $year >= 2000; $year--): ?>
                                            <option value="<?= $year ?>" <?= $filter_year === (string)$year ? 'selected' : '' ?>>
                                                <?= $year ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="form-text">Filter logs by year</div>
                                </div>
                                <div class="col-md-3">
                                    <label for="monthFilter" class="form-label">Month</label>
                                    <select id="monthFilter" name="month" class="form-select" title="Filter logs by month">
                                        <option value="">All Months</option>
                                        <?php
                                        $months = [
                                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                        ];
                                        foreach ($months as $num => $name): ?>
                                            <option value="<?= $num ?>" <?= $filter_month === (string)$num ? 'selected' : '' ?>>
                                                <?= $name ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Filter logs by month</div>
                                </div>
                                <div class="col-md-3">
                                    <label for="dateFilter" class="form-label">Date</label>
                                    <input type="date" id="dateFilter" name="date" class="form-control" title="Filter logs by specific date" value="<?= htmlspecialchars($filter_date) ?>">
                                    <div class="form-text">Select a date to filter logs</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="filterCategory" class="form-label">Category</label>
                                    <select id="filterCategory" name="category" class="form-select" title="Filter logs by category">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>" <?= $filter_category === $category ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select a category to filter logs</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="filterProgramSection" class="form-label">Program/Section</label>
                                    <select id="filterProgramSection" name="program_section" class="form-select" title="Filter logs by program/section" disabled>
                                        <option value="">Select a category first</option>
                                    </select>
                                    <div class="form-text">Select a program/section to filter logs</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="filterGradeYear" class="form-label">Grade/Year</label>
                                    <select id="filterGradeYear" name="grade_year" class="form-select" title="Filter logs by grade/year">
                                        <option value="">All Grades/Years</option>
                                        <?php foreach ($grade_years as $grade): ?>
                                            <option value="<?= htmlspecialchars($grade) ?>" <?= $filter_grade_year === $grade ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($grade) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select a grade/year to filter logs</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button type="button" class="btn btn-secondary" id="clearFilterBtn">
                                    <i class="fas fa-undo me-1"></i> Clear
                                </button>
                                <button type="submit" class="btn btn-primary" id="applyFilterBtn">
                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                </button>
                                <button type="button" class="btn btn-success" id="downloadExcelBtn">
                                    <i class="fas fa-file-excel me-1"></i> Download Excel
                                </button>
                            </div>
                        </form>
                        <div class="table-responsive mt-3">
                            <table id="medicineLogsTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;">Medicine</th>
                                        <th style="width: 20%;">Patient</th>
                                        <th style="width: 10%;">Qty Used</th>
                                        <th style="width: 15%;">Date</th>
                                        <th style="width: 15%;">Time</th>
                                        <th style="width: 15%;">Reason</th>
                                        <th style="width: 5%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Medicine Logs] Initialized');

            // Initialize program sections
            const allPrograms = <?php echo json_encode($program_sections); ?>;
            const nonAcademicCategories = ['Faculty and Staff', 'Alumni', 'Visitor'];

            function updateProgramSection(category) {
                const $programSelect = $('#filterProgramSection');
                $programSelect.empty();
                $programSelect.append('<option value="">All Programs/Sections</option>');

                if (!category || nonAcademicCategories.includes(category)) {
                    $programSelect.prop('disabled', true);
                    if (category) {
                        $programSelect.append(`<option value="${category}">${category}</option>`);
                        $programSelect.val('<?php echo htmlspecialchars($filter_program_section); ?>');
                    }
                } else {
                    $programSelect.prop('disabled', false);
                    const filteredPrograms = allPrograms.filter(program => program.category === category);
                    filteredPrograms.forEach(program => {
                        const selected = program.name === '<?php echo htmlspecialchars($filter_program_section); ?>' ? 'selected' : '';
                        $programSelect.append(`<option value="${program.name}" ${selected}>${program.name}</option>`);
                    });
                }
            }

            // Update program section on category change
            $('#filterCategory').on('change', function() {
                const category = $(this).val();
                updateProgramSection(category);
            });

            // Initialize program section based on current category
            updateProgramSection('<?php echo htmlspecialchars($filter_category); ?>');

            // Clear conflicting date filters
            $('#dateFilter').on('change', function() {
                if ($(this).val()) {
                    $('#monthFilter').val('');
                    $('#yearFilter').val('');
                }
            });

            $('#monthFilter').on('change', function() {
                if ($(this).val()) {
                    $('#dateFilter').val('');
                }
            });

            // Initialize Medicine Logs DataTable
            const medicineLogsTable = $('#medicineLogsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[3, 'desc']],
                language: { search: "", searchPlaceholder: "Search logs..." },
                serverSide: false,
                ajax: {
                    url: 'fetch_medicine_logs.php',
                    type: 'GET',
                    data: function(d) {
                        d.medicine_id = $('#medicineFilter').val();
                        d.year = $('#yearFilter').val();
                        d.month = $('#monthFilter').val();
                        d.date = $('#dateFilter').val();
                        d.category = $('#filterCategory').val();
                        d.program_section = $('#filterProgramSection').val();
                        d.grade_year = $('#filterGradeYear').val();
                    },
                    dataSrc: function(json) {
                        console.log('[SSCMS Medicine Logs] Logs JSON:', json);
                        if (!json.data) {
                            console.error('[SSCMS Medicine Logs] No data in logs JSON response:', json);
                            alert('Failed to load logs: ' + (json.error || 'No data returned'));
                            return [];
                        }
                        if (json.error) {
                            console.error('[SSCMS Medicine Logs] Error in JSON response:', json.error);
                            alert('Error: ' + json.error);
                            return [];
                        }
                        if (json.medicines) {
                            const medicineFilter = $('#medicineFilter');
                            medicineFilter.empty().append('<option value="">All Medicines</option>');
                            json.medicines.forEach(med => {
                                const selected = med.id === '<?php echo htmlspecialchars($filter_medicine); ?>' ? 'selected' : '';
                                medicineFilter.append(`<option value="${med.id}" ${selected}>${med.name}</option>`);
                            });
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('[SSCMS Medicine Logs] AJAX error:', xhr.status, error, thrown);
                        console.log('[SSCMS Medicine Logs] Response text:', xhr.responseText);
                        alert('Error fetching logs. Status: ' + xhr.status + '. Check console for details.');
                    }
                },
                columns: [
                    { data: 'medicine_name' },
                    { data: 'patient_name' },
                    { data: 'quantity_used' },
                    { 
                        data: 'visit_date',
                        render: function(data) {
                            return data ? new Date(data).toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: '2-digit' }) : '-';
                        }
                    },
                    { 
                        data: 'visit_date',
                        render: function(data) {
                            return data ? new Date(data).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '-';
                        }
                    },
                    { data: 'reason' },
                    { 
                        data: null,
                        render: function(data, type, row) {
                            return `<button class="btn btn-danger btn-sm delete-btn" data-id="${row.id}"><i class="fas fa-trash"></i></button>`;
                        }
                    }
                ]
            });

            // Apply filters on form submit
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                medicineLogsTable.ajax.reload();
            });

            // Clear Filter Button
            $('#clearFilterBtn').on('click', function() {
                $('#medicineFilter, #yearFilter, #monthFilter, #dateFilter, #filterCategory, #filterProgramSection, #filterGradeYear').val('');
                updateProgramSection('');
                medicineLogsTable.ajax.reload();
            });

            // Delete Medicine Log
            $('#medicineLogsTable').on('click', '.delete-btn', function() {
                const id = $(this).data('id');
                if (confirm('Are you sure you want to delete this medicine usage log?')) {
                    $.ajax({
                        url: 'delete_medicine_log.php',
                        type: 'POST',
                        data: { id: id },
                        success: function(response) {
                            if (response.success) {
                                medicineLogsTable.ajax.reload();
                                alert('Medicine log deleted successfully.');
                            } else {
                                alert('Error deleting medicine log: ' + (response.error || 'Unknown error'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('[SSCMS Medicine Logs] Delete AJAX error:', xhr.status, error);
                            alert('Error deleting medicine log. Check console for details.');
                        }
                    });
                }
            });

            // Download Excel Button
            $('#downloadExcelBtn').on('click', function() {
                const data = medicineLogsTable.rows().data().toArray();
                if (data.length === 0) {
                    alert('No data to export.');
                    return;
                }
                
                const currentDate = new Date().toISOString().split('T')[0];
                const medicineName = $('#medicineFilter option:selected').text() || 'All_Medicines';
                
                // Prepare data
                const excelData = [
                    { '': 'Medicine Usage Logs', ' ': '', '  ': '', '   ': '', '    ': '', '     ': '' }, // Title row
                    { '': '', ' ': '', '  ': '', '   ': '', '    ': '', '     ': '' } // Empty row
                ];
                
                // Header row
                excelData.push({
                    '': 'Medicine',
                    ' ': 'Patient',
                    '  ': 'Qty Used',
                    '   ': 'Date',
                    '    ': 'Time',
                    '     ': 'Reason'
                });
                
                // Data rows
                data.forEach(row => {
                    excelData.push({
                        '': row.medicine_name,
                        ' ': row.patient_name,
                        '  ': row.quantity_used,
                        '   ': row.visit_date ? new Date(row.visit_date).toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: '2-digit' }) : '-',
                        '    ': row.visit_date ? new Date(row.visit_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '-',
                        '     ': row.reason
                    });
                });
                
                // Create worksheet
                const ws = XLSX.utils.json_to_sheet(excelData, { skipHeader: true });
                
                // Apply styling
                const range = XLSX.utils.decode_range(ws['!ref']);
                for (let R = 0; R <= range.e.r; ++R) {
                    for (let C = 0; C <= range.e.c; ++C) {
                        const cellAddress = XLSX.utils.encode_cell({ r: R, c: C });
                        if (!ws[cellAddress]) ws[cellAddress] = { t: 's', v: '' };
                        
                        // Apply borders to all cells
                        ws[cellAddress].s = {
                            border: {
                                top: { style: 'thin', color: { rgb: '000000' } },
                                bottom: { style: 'thin', color: { rgb: '000000' } },
                                left: { style: 'thin', color: { rgb: '000000' } },
                                right: { style: 'thin', color: { rgb: '000000' } }
                            }
                        };
                        
                        // Title row (R=0)
                        if (R === 0) {
                            ws[cellAddress].s.font = { bold: true, sz: 14 };
                            ws[cellAddress].s.fill = { fgColor: { rgb: 'E6F3FA' } };
                        }
                        
                        // Header row (R=2)
                        if (R === 2) {
                            ws[cellAddress].s.font = { bold: true };
                            ws[cellAddress].s.fill = { fgColor: { rgb: 'D3E3FD' } };
                        }
                    }
                }
                
                // Set column widths
                ws['!cols'] = [
                    { width: 25 },
                    { width: 25 },
                    { width: 10 },
                    { width: 12 },
                    { width: 12 },
                    { width: 30 }
                ];
                
                // Merge title row
                ws['!merges'] = [
                    { s: { r: 0, c: 0 }, e: { r: 0, c: 5 } }
                ];
                
                // Create workbook
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Medicine Usage Logs');
                
                // Download
                XLSX.writeFile(wb, `Medicine_Usage_Logs_${medicineName.replace(/[^a-zA-Z0-9]/g, '_')}_${currentDate}.xlsx`);
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>
