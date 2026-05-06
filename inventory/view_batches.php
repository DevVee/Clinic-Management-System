<?php
ob_start();
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Fetch all batches with medicine names
try {
    $stmt = $conn->prepare("
        SELECT mb.id, mb.batch_number, m.id AS medicine_id, m.name AS medicine_name, mb.quantity, mb.purchase_price, 
               mb.supplier_name, mb.expiration_date, mb.added_at
        FROM medicine_batches mb
        JOIN medicines m ON mb.medicine_id = m.id
        WHERE m.is_active = 1
        ORDER BY mb.added_at DESC
    ");
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Medicine Inventory] Error fetching batches: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error fetching batches.';
    $batches = [];
}

// Fetch medicines for filter dropdown
try {
    $stmt = $conn->prepare("SELECT id, name FROM medicines WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Medicine Inventory] Error fetching medicines: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error fetching medicines.';
    $medicines = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - View Medicine Batches">
    <meta name="author" content="ICCB">
    <title>View Medicine Batches - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        .toast-container {
            position: fixed;
            top: 0.75rem;
            right: 0.75rem;
        }
        .batch-number {
            color: var(--primary);
            font-weight: 600;
        }
        .medicine-name {
            color: var(--success);
            font-weight: 600;
        }
        .quantity {
            color: var(--warning);
            font-weight: 600;
        }
        .expiration-date {
            color: var(--danger);
            font-weight: 600;
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
                        <h5 class="card-title">Medicine Batches</h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="mb-3">
                            <label for="medicine_filter" class="form-label">Filter by Medicine</label>
                            <select id="medicine_filter" class="form-select">
                                <option value="">All Medicines</option>
                                <?php foreach ($medicines as $medicine): ?>
                                    <option value="<?= htmlspecialchars($medicine['id']) ?>">
                                        <?= htmlspecialchars($medicine['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="table-responsive">
                            <table id="batchesTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Batch Number</th>
                                        <th>Medicine Name</th>
                                        <th>Quantity</th>
                                        <th>Purchase Price (₱)</th>
                                        <th>Supplier Name</th>
                                        <th>Expiration Date</th>
                                        <th>Added At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $batch): ?>
                                        <tr data-medicine-id="<?= htmlspecialchars($batch['medicine_id']) ?>">
                                            <td class="batch-number"><?= htmlspecialchars($batch['batch_number']) ?></td>
                                            <td class="medicine-name"><?= htmlspecialchars($batch['medicine_name']) ?></td>
                                            <td class="quantity"><?= htmlspecialchars($batch['quantity']) ?></td>
                                            <td><?= htmlspecialchars($batch['purchase_price'] ? number_format($batch['purchase_price'], 2) : '-') ?></td>
                                            <td><?= htmlspecialchars($batch['supplier_name'] ?: '-') ?></td>
                                            <td class="expiration-date"><?= htmlspecialchars($batch['expiration_date'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($batch['added_at']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="update_stocks.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i> Add New Batch
                            </a>
                            <a href="stock_audit_logs.php" class="btn btn-secondary">
                                <i class="fas fa-clipboard-list me-1"></i> View Audit Logs
                            </a>
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
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Medicine Inventory] View Batches Initialized');

            // Initialize DataTable
            const batchesTable = $('#batchesTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[6, 'desc']], // Order by Added At (descending)
                language: { search: "", searchPlaceholder: "Search batches..." }
            });

            // Filter by medicine
            $('#medicine_filter').on('change', function() {
                const medicineId = $(this).val();
                console.log('[SSCMS Medicine Inventory] Filtering by medicine_id: ' + medicineId);
                
                if (medicineId) {
                    // Filter rows based on data-medicine-id attribute
                    batchesTable.rows().every(function() {
                        const rowMedicineId = $(this.node()).data('medicine-id');
                        const isVisible = rowMedicineId == medicineId;
                        console.log('[SSCMS Medicine Inventory] Row medicine_id: ' + rowMedicineId + ', Visible: ' + isVisible);
                        this.nodes().to$().toggle(isVisible);
                        return true;
                    });
                    batchesTable.draw();
                } else {
                    // Show all rows
                    batchesTable.rows().every(function() {
                        this.nodes().to$().show();
                        return true;
                    });
                    batchesTable.draw();
                }
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>