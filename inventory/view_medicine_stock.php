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

// Fetch medicines for dropdown
try {
    $stmt = $conn->prepare("SELECT id, name FROM medicines ORDER BY name ASC");
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS View Medicine Stock] Error fetching medicines: " . $e->getMessage());
    $medicines = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - View Medicine Stock">
    <meta name="author" content="ICCB">
    <title>View Medicine Stock - Clinic Management</title>
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
            padding-top: 1.5rem;
            min-height: 100vh;
        }
        .card {
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border: none;
        }
        .table {
            font-size: 0.65rem;
        }
        .table th, .table td {
            padding: 0.3rem;
            border-color: var(--border);
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 0;
        }
        .table th {
            background: #f9fafb;
            font-weight: 600;
        }
        .btn-success {
            background-color: var(--success);
            border-color: var(--success);
        }
        .btn-success:hover {
            background-color: var(--success-dark);
            border-color: var(--success-dark);
        }
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .toast-container {
            position: fixed;
            top: 0.75rem;
            right: 0.75rem;
        }
        .total-cost {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .filter-form {
            background-color: var(--card-bg);
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
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
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <div class="dashboard-header">
                    <h1 class="dashboard-title">
                        <i class="fas fa-capsules"></i>
                        View Medicine Stock
                    </h1>
                </div>

                <nav aria-label="breadcrumb" class="custom-breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="inventory.php" class="text-decoration-none">Medicine Inventory</a></li>
                        <li class="breadcrumb-item"><a href="stock_audit_logs.php" class="text-decoration-none">Stock Audit Logs</a></li>
                        <li class="breadcrumb-item active" aria-current="page">View Medicine Stock</li>
                    </ol>
                </nav>

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

                <div class="card filter-form">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label for="medicineSelect" class="form-label">Medicine</label>
                            <select id="medicineSelect" name="medicine_id" class="form-select form-select-sm" required>
                                <option value="">Select Medicine</option>
                                <?php foreach ($medicines as $medicine): ?>
                                    <option value="<?= htmlspecialchars($medicine['id']) ?>"><?= htmlspecialchars($medicine['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="monthFilter" class="form-label">Month</label>
                            <select id="monthFilter" name="month" class="form-select form-select-sm">
                                <option value="">All Months</option>
                                <?php
                                for ($y = 2024; $y <= 2025; $y++) {
                                    for ($m = 1; $m <= 12; $m++) {
                                        $month = sprintf("%d-%02d", $y, $m);
                                        echo "<option value='$month'>".date('F Y', strtotime("$month-01"))."</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" id="startDate" name="start_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" id="endDate" name="end_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-filter me-1"></i>Apply</button>
                            <button type="button" id="resetFilter" class="btn btn-secondary btn-sm"><i class="fas fa-undo me-1"></i>Reset</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <i class="fas fa-capsules me-1"></i>
                            Medicine Stock Logs
                        </div>
                        <div>
                            <button class="btn btn-success btn-sm" id="downloadExcelBtn" disabled>
                                <i class="fas fa-file-excel me-1"></i> Download Excel
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="total-cost mb-2" id="totalCost">Total Cost: ₱0.00</div>
                        <div class="table-responsive">
                            <table id="stockTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 15%;">Quantity Added</th>
                                        <th style="width: 15%;">Old Quantity</th>
                                        <th style="width: 15%;">New Quantity</th>
                                        <th style="width: 15%;">Cost (₱)</th>
                                        <th style="width: 15%;">User</th>
                                        <th style="width: 25%;">Date</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="clinic-footer">
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
            console.log('[SSCMS View Medicine Stock] Initialized');

            const stockTable = $('#stockTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50],
                order: [[5, 'desc']],
                language: { search: "", searchPlaceholder: "Search logs..." },
                columnDefs: [],
                serverSide: false,
                data: [],
                columns: [
                    { data: 'quantity_added' },
                    { data: 'old_quantity' },
                    { data: 'new_quantity' },
                    { 
                        data: 'cost',
                        render: function(data) {
                            return '₱' + parseFloat(data).toFixed(2);
                        }
                    },
                    { 
                        data: 'user_name',
                        render: function(data) {
                            return data || '-';
                        }
                    },
                    { 
                        data: 'created_at',
                        render: function(data) {
                            return new Date(data).toLocaleString('en-US', { dateStyle: 'short', timeStyle: 'short' });
                        }
                    }
                ]
            });

            function loadLogs(filters = {}) {
                $.ajax({
                    url: 'fetch_medicine_stock.php',
                    type: 'GET',
                    data: filters,
                    dataType: 'json',
                    success: function(response) {
                        console.log('[SSCMS View Medicine Stock] Logs JSON:', response);
                        if (response.error) {
                            alert('Error: ' + response.error);
                            stockTable.clear().draw();
                            $('#totalCost').text('Total Cost: ₱0.00');
                            $('#downloadExcelBtn').prop('disabled', true);
                            return;
                        }

                        stockTable.clear().rows.add(response.data).draw();
                        $('#totalCost').text(`Total Cost: ₱${parseFloat(response.total_cost).toFixed(2)}`);
                        $('#downloadExcelBtn').prop('disabled', response.data.length === 0);

                        // Excel export
                        $('#downloadExcelBtn').off('click').on('click', function() {
                            if (response.data.length === 0) {
                                alert('No data to export.');
                                return;
                            }
                            const excelData = response.data.map(row => ({
                                'Quantity Added': row.quantity_added,
                                'Old Quantity': row.old_quantity,
                                'New Quantity': row.new_quantity,
                                Cost: '₱' + parseFloat(row.cost).toFixed(2),
                                User: row.user_name || '-',
                                Date: new Date(row.created_at).toLocaleString('en-US', { dateStyle: 'short', timeStyle: 'short' })
                            }));
                            excelData.push({
                                'Quantity Added': '',
                                'Old Quantity': '',
                                'New Quantity': '',
                                Cost: '₱' + parseFloat(response.total_cost).toFixed(2),
                                User: '',
                                Date: ''
                            });
                            const ws = XLSX.utils.json_to_sheet(excelData);
                            const wb = XLSX.utils.book_new();
                            XLSX.utils.book_append_sheet(wb, ws, 'Medicine Stock');
                            XLSX.write(wb, 'medicine_stock.xlsx');
                        });
                    },
                    error: function(xhr, error, thrown) {
                        console.error('[SSCMS View Medicine Stock] AJAX error:', xhr.status, error, thrown);
                        alert('Error fetching logs.');
                        stockTable.clear().draw();
                        $('#totalCost').text('Total Cost: ₱0.00');
                        $('#downloadExcelBtn').prop('disabled', true);
                    }
                });
            }

            // Filter form
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                const medicineId = $('#medicineSelect').val();
                if (!medicineId) {
                    alert('Please select a medicine.');
                    return;
                }
                const filters = {
                    medicine_id: medicineId,
                    month: $('#monthFilter').val(),
                    start_date: $('#startDate').val(),
                    end_date: $('#endDate').val()
                };
                loadLogs(filters);
            });

            // Reset filter
            $('#resetFilter').on('click', function() {
                $('#filterForm')[0].reset();
                stockTable.clear().draw();
                $('#totalCost').text('Total Cost: ₱0.00');
                $('#downloadExcelBtn').prop('disabled', true);
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').show();
        });
    </script>
</body>
</html>