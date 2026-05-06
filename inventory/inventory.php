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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        if (isset($_POST['add_medicine'])) {
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

            if (!$name || $quantity === false || $quantity < 0) {
                throw new Exception('Invalid medicine name or quantity.');
            }

            $stmt = $conn->prepare("INSERT INTO medicines (name, quantity) VALUES (?, ?)");
            $stmt->execute([$name, $quantity]);
            $_SESSION['success_message'] = 'Medicine added successfully!';
            error_log("[SSCMS Inventory] Medicine added: name=$name, quantity=$quantity");
        } elseif (isset($_POST['edit_medicine'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

            if (!$id || !$name || $quantity === false || $quantity < 0) {
                throw new Exception('Invalid medicine ID, name, or quantity.');
            }

            $stmt = $conn->prepare("UPDATE medicines SET name = ?, quantity = ? WHERE id = ?");
            $stmt->execute([$name, $quantity, $id]);
            $_SESSION['success_message'] = 'Medicine updated successfully!';
            error_log("[SSCMS Inventory] Medicine updated: id=$id, name=$name, quantity=$quantity");
        } elseif (isset($_POST['delete_medicine'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$id) {
                throw new Exception('Invalid medicine ID.');
            }

            // Check for usage in medicine_logs
            $stmt = $conn->prepare("SELECT COUNT(*) FROM medicine_logs WHERE medicine_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete medicine with usage logs.');
            }

            $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success_message'] = 'Medicine deleted successfully!';
            error_log("[SSCMS Inventory] Medicine deleted: id=$id");
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("[SSCMS Inventory] Error: " . $e->getMessage());
    }

    header('Location: inventory.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Medicine Inventory">
    <meta name="author" content="ICCB">
    <title>Medicine Inventory - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
            --purple: #7c3aed;
            --purple-dark: #6d28d9;
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
            overflow-x: hidden;
            font-size: 0.8rem;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1rem;
            padding-top: 1.5rem;
            min-height: 100vh;
            transition: margin-left var(--transition-speed);
        }

        .container-fluid {
            max-width: 1200px;
            padding: 0 0.75rem;
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .dashboard-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
        }

        .dashboard-title i {
            color: var(--primary);
            font-size: 1rem;
            margin-right: 0.5rem;
            background-color: #e0f2fe;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-breadcrumb {
            padding: 0.4rem 0.75rem;
            background-color: var(--card-bg);
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 0.75rem;
            font-size: 0.75rem;
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
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border: none;
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
        }

        .card-header {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table {
            color: var(--text-primary);
            font-size: 0.65rem;
            margin-bottom: 0;
        }

        .table th, .table td {
            padding: 0.3rem;
            border-color: var(--border);
            vertical-align: middle;
        }

        .table th {
            font-weight: 600;
            background: #f9fafb;
            color: var(--text-primary);
        }

        .table tbody tr:hover {
            background-color: #e0f2fe;
            transform: scale(1.02);
            transition: all 0.2s;
        }

        .medicine-name {
            display: flex;
            align-items: center;
            color: var(--primary);
        }

        .medicine-name i {
            margin-right: 0.3rem;
            font-size: 0.7rem;
        }

        .btn {
            border-radius: 5px;
            padding: 0.3rem 0.7rem;
            font-weight: 500;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            transition: all var(--transition-speed);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success);
            border-color: var(--success);
        }

        .btn-success:hover {
            background-color: var(--success-dark);
            border-color: var(--success-dark);
        }

        .btn-warning {
            background-color: var(--warning);
            border-color: var(--warning);
        }

        .btn-warning:hover {
            background-color: var(--warning-dark);
            border-color: var(--warning-dark);
        }

        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background-color: var(--danger-dark);
            border-color: var(--danger-dark);
        }

        .form-control, .form-select {
            border-radius: 5px;
            border: 1px solid var(--border);
            padding: 0.4rem 0.6rem;
            font-size: 0.8rem;
            height: 34px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.1);
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.3rem;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-text {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .modal-content {
            border-radius: 8px;
            background: var(--card-bg);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1rem;
            background-color: var(--bscs-maroon);
            color: white;
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 0.75rem 1rem;
        }

        .modal-lg {
            max-width: 500px;
        }

        .toast-container {
            position: fixed;
            top: 0.75rem;
            right: 0.75rem;
        }

        .clinic-footer {
            margin-top: 1rem;
            padding: 0.75rem 0;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.75rem;
            border-top: 1px solid var(--border);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }

        @media print, pdf {
            @page {
                size: A4;
                margin: 1cm;
            }
            body {
                font-family: 'Inter', sans-serif;
                font-size: 6pt;
                background: #ffffff;
                color: #000000;
                margin: 0;
                padding: 0;
                width: 210mm;
                height: 297mm;
                box-sizing: border-box;
            }
            .content, .container-fluid, .card, .card-body {
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: none !important;
                width: 100% !important;
                max-width: 190mm !important;
            }
            .dashboard-header, .custom-breadcrumb, .btn, .toast-container, nav, .modal {
                display: none !important;
            }
            .card-header {
                padding: 0.2cm 0;
                text-align: center;
                border-bottom: 1px solid #000;
                font-size: 7pt;
                font-weight: 600;
                color: #000;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                font-size: 6pt;
                margin: 0.2cm 0;
                page-break-inside: avoid;
            }
            .table th, .table td {
                border: 1px solid #000;
                padding: 0.1cm;
                text-align: left;
            }
            .table th {
                background: #e0f2fe;
                font-weight: 600;
                color: #000;
            }
            .table-responsive {
                overflow: visible;
                margin: 0;
            }
            .clinic-footer {
                position: absolute;
                bottom: 0.5cm;
                width: 100%;
                text-align: center;
                font-size: 6pt;
                color: #000;
                border-top: 1px solid #000;
                padding-top: 0.1cm;
            }
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
                padding: 0.5rem;
                padding-top: 1.5rem;
            }
            .modal-lg {
                max-width: 95%;
            }
            .table {
                font-size: 0.6rem;
            }
            .btn {
                font-size: 0.7rem;
                padding: 0.2rem 0.5rem;
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
                    <h1 class="dashboard-title">
                        <i class="fas fa-capsules"></i>
                        Medicine Inventory
                    </h1>
                </div>

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="custom-breadcrumb fade-in">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Medicine Inventory</li>
                    </ol>
                </nav>

                <!-- Toast Container -->
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

                <!-- Medicine Inventory Card -->
                <div class="card fade-in">
                    <div class="card-header">
                        <div>
                            <i class="fas fa-capsules me-1"></i>
                            Medicine Stock
                        </div>
                        <div>
                            <button class="btn btn-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
                                <i class="fas fa-plus me-1"></i> Add
                            </button>
                            <button class="btn btn-success btn-sm me-1" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-1"></i> PDF
                            </button>
                            <button class="btn btn-warning btn-sm" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table id="medicineTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 60%;">Medicine Name</th>
                                        <th style="width: 20%;">Quantity</th>
                                        <th style="width: 20%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="card-header mt-2">
                            <div>
                                <i class="fas fa-clipboard-list me-1"></i>
                                Usage Logs
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="medicineLogsTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Medicine</th>
                                        <th style="width: 25%;">Patient</th>
                                        <th style="width: 15%;">Qty Used</th>
                                        <th style="width: 20%;">Date</th>
                                        <th style="width: 15%;">Reason</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Add Medicine Modal -->
        <div class="modal fade" id="addMedicineModal" tabindex="-1" aria-labelledby="addMedicineModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="addMedicineModalLabel">Add New Medicine</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addMedicineForm" action="inventory.php" method="POST">
                            <input type="hidden" name="add_medicine" value="1">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" id="name" required>
                                        <div class="form-text">Enter the name of the medicine</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="quantity" id="quantity" min="0" required>
                                        <div class="form-text">Enter the quantity available</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Medicine
                                    <span id="addSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Medicine Modal -->
        <div class="modal fade" id="editMedicineModal" tabindex="-1" aria-labelledby="editMedicineModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="editMedicineModalLabel">Edit Medicine</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editMedicineForm" action="inventory.php" method="POST">
                            <input type="hidden" name="edit_medicine" value="1">
                            <input type="hidden" name="id" id="editMedicineId">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="editName" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" id="editName" required>
                                        <div class="form-text">Enter the name of the medicine</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="editQuantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="quantity" id="editQuantity" min="0" required>
                                        <div class="form-text">Enter the quantity available</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Medicine
                                    <span id="editSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Medicine Modal -->
        <div class="modal fade" id="deleteMedicineModal" tabindex="-1" aria-labelledby="deleteMedicineModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="deleteMedicineModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete <span id="deleteMedicineName"></span>?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form id="deleteMedicineForm" action="inventory.php" method="POST">
                            <input type="hidden" name="delete_medicine" value="1">
                            <input type="hidden" name="id" id="deleteMedicineId">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete
                                <span id="deleteSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <footer class="clinic-footer fade-in">
            <div class="container-fluid">
                <p class="mb-0">Clinic Management System © 2025 ICCB. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Inventory] Initialized');

            // Initialize Medicine DataTable
            const medicineTable = $('#medicineTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'asc']],
                language: { search: "", searchPlaceholder: "Search medicines..." },
                columnDefs: [{ orderable: false, targets: 2 }],
                serverSide: false,
                ajax: {
                    url: 'fetch_medicines.php',
                    type: 'GET',
                    dataSrc: function(json) {
                        console.log('[SSCMS Inventory] Medicine JSON:', json);
                        if (!json.data) {
                            console.error('[SSCMS Inventory] No data in medicine JSON response');
                            return [];
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('[SSCMS Inventory] Medicine AJAX error:', error, thrown);
                        alert('Error fetching medicines. Check console for details.');
                    }
                },
                columns: [
                    { 
                        data: 'name',
                        render: function(data) {
                            return `<span class="medicine-name"><i class="fas fa-capsules"></i> ${data}</span>`;
                        }
                    },
                    { data: 'quantity' },
                    {
                        data: null,
                        render: function(data) {
                            return `
                                <button class="btn btn-warning btn-sm edit-medicine-btn" 
                                        data-id="${data.id}" 
                                        data-name="${data.name}" 
                                        data-quantity="${data.quantity}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm ms-1 delete-medicine-btn" 
                                        data-id="${data.id}" 
                                        data-name="${data.name}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            `;
                        }
                    }
                ]
            });

            // Initialize Medicine Logs DataTable
            const medicineLogsTable = $('#medicineLogsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[3, 'desc']],
                language: { search: "", searchPlaceholder: "Search logs..." },
                columnDefs: [],
                serverSide: false,
                ajax: {
                    url: 'fetch_medicine_logs.php',
                    type: 'GET',
                    dataSrc: function(json) {
                        console.log('[SSCMS Inventory] Logs JSON:', json);
                        if (!json.data) {
                            console.error('[SSCMS Inventory] No data in logs JSON response');
                            return [];
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('[SSCMS Inventory] Logs AJAX error:', error, thrown);
                        alert('Error fetching logs. Check console for details.');
                    }
                },
                columns: [
                    { 
                        data: 'medicine_name',
                        render: function(data) {
                            return `<span class="medicine-name"><i class="fas fa-capsules"></i> ${data}</span>`;
                        }
                    },
                    { data: 'patient_name' },
                    { data: 'quantity_used' },
                    { 
                        data: 'visit_date', 
                        render: function(data) {
                            return new Date(data).toLocaleString('en-US', { dateStyle: 'short', timeStyle: 'short' });
                        }
                    },
                    { data: 'reason' }
                ]
            });

            // Edit Medicine Button Click
            $('#medicineTable').on('click', '.edit-medicine-btn', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const quantity = $(this).data('quantity');

                console.log('[SSCMS Inventory] Edit medicine:', { id, name, quantity });
                $('#editMedicineId').val(id);
                $('#editName').val(name);
                $('#editQuantity').val(quantity);

                const modal = new bootstrap.Modal(document.getElementById('editMedicineModal'));
                modal.show();
            });

            // Delete Medicine Button Click
            $('#medicineTable').on('click', '.delete-medicine-btn', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');

                console.log('[SSCMS Inventory] Delete medicine:', { id, name });
                $('#deleteMedicineId').val(id);
                $('#deleteMedicineName').text(name);

                const modal = new bootstrap.Modal(document.getElementById('deleteMedicineModal'));
                modal.show();
            });

            // Form Validation
            $('#addMedicineForm, #editMedicineForm, #deleteMedicineForm').on('submit', function(e) {
                const form = $(this);
                const spinner = form.find('.spinner-border');

                console.log('[SSCMS Inventory] Form submitting:', form.serialize());
                spinner.removeClass('d-none');
                form.find('[type=submit]').prop('disabled', true);
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');

            // Export to PDF
            window.exportToPDF = function() {
                const element = document.querySelector('.card-body');
                if (!element) {
                    console.error('[SSCMS Inventory] PDF Export: Card body not found');
                    return;
                }
                const opt = {
                    margin: [8, 8, 8, 8],
                    filename: 'medicine_inventory.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 3, useCORS: true },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['css', 'legacy'], avoid: ['.table'] }
                };
                html2pdf().from(element).set(opt).save().catch(err => {
                    console.error('[SSCMS Inventory] PDF Export Error:', err);
                });
            };
        });
    </script>
</body>
</html>