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

// Handle batch disposal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose_batch'])) {
    try {
        $conn->beginTransaction();

        $batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
        if (!$batch_id) {
            throw new Exception('Invalid batch ID.');
        }

        // Fetch batch details for transfer
        $stmt = $conn->prepare("
            SELECT mb.batch_number, mb.quantity, mb.medicine_id, m.name AS medicine_name, 
                   mb.purchase_price, mb.supplier_name, mb.expiration_date, mb.added_at
            FROM medicine_batches mb
            JOIN medicines m ON mb.medicine_id = m.id
            WHERE mb.id = ?
        ");
        $stmt->execute([$batch_id]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            throw new Exception('Batch not found.');
        }

        // Update medicines table quantity
        $stmt = $conn->prepare("
            UPDATE medicines
            SET quantity = quantity - ?
            WHERE id = ?
        ");
        $stmt->execute([$batch['quantity'], $batch['medicine_id']]);

        // Move batch to disposed_batches
        $stmt = $conn->prepare("
            INSERT INTO disposed_batches (medicine_id, batch_number, quantity, purchase_price, supplier_name, expiration_date, added_at, disposed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $batch['medicine_id'],
            $batch['batch_number'],
            $batch['quantity'],
            $batch['purchase_price'],
            $batch['supplier_name'],
            $batch['expiration_date'],
            $batch['added_at']
        ]);

        // Delete batch from medicine_batches
        $stmt = $conn->prepare("DELETE FROM medicine_batches WHERE id = ?");
        $stmt->execute([$batch_id]);

        $conn->commit();
        $_SESSION['success_message'] = 'Batch disposed successfully!';
        error_log("[SSCMS Medicine Inventory] Batch disposed: batch_id=$batch_id, batch_number={$batch['batch_number']}");
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("[SSCMS Medicine Inventory] Dispose error: " . $e->getMessage());
    }

    header('Location: medicine_expiration.php');
    exit;
}

// Fetch all batches with medicine names
try {
    $stmt = $conn->prepare("
        SELECT mb.id, mb.batch_number, m.name AS medicine_name, mb.quantity, mb.purchase_price, 
               mb.supplier_name, mb.expiration_date, mb.added_at
        FROM medicine_batches mb
        JOIN medicines m ON mb.medicine_id = m.id
        ORDER BY mb.added_at DESC
    ");
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate expiration status for alerts
    $today = new DateTime();
    $expiring_batches = [];
    foreach ($batches as $batch) {
        if ($batch['expiration_date']) {
            $exp_date = new DateTime($batch['expiration_date']);
            $interval = $today->diff($exp_date);
            $days_left = $interval->days * ($interval->invert ? -1 : 1);
            if ($days_left <= 365 && $days_left >= -30) { // Include expired within 30 days and expiring within 12 months
                $expiring_batches[] = [
                    'batch_id' => $batch['id'],
                    'batch_number' => $batch['batch_number'],
                    'medicine_name' => $batch['medicine_name'],
                    'expiration_date' => $batch['expiration_date'],
                    'days_left' => $days_left
                ];
            }
        }
    }
} catch (Exception $e) {
    error_log("[SSCMS Medicine Inventory] Error fetching batches: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error fetching batches.';
    $batches = [];
    $expiring_batches = [];
}

// Fetch medicines for filter dropdown
try {
    $stmt = $conn->prepare("SELECT id, name FROM medicines ORDER BY name ASC");
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
    <meta name="description" content="Clinic Management System - Manage Medicine Expirations">
    <meta name="author" content="ICCB">
    <title>Manage Medicine Expirations - SSCMS</title>
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
            --expired: #dc2626;
            --expiring-soon: #ffca28;
            --expiring-medium: #f59e0b;
            --expiring-far: #22c55e;
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
            font-size: 0.75rem;
            table-layout: auto;
            width: 100%;
        }
        .table th, .table td {
            padding: 0.5rem;
            border-color: var(--border);
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .table th {
            background: #f9fafb;
            font-weight: 600;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
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
            transition: all var(--transition-speed) ease;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .btn-secondary {
            background-color: var(--secondary);
            border-color: var(--secondary);
            transition: all var(--transition-speed) ease;
        }
        .btn-secondary:hover {
            background-color: var(--secondary-dark);
            border-color: var(--secondary-dark);
        }
        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
            transition: all var(--transition-speed) ease;
        }
        .btn-danger:hover {
            background-color: var(--danger-dark);
            border-color: var(--danger-dark);
        }
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            transition: all var(--transition-speed) ease;
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #138496;
        }
        .toast-container {
            position: fixed;
            top: 0.75rem;
            right: 0.75rem;
            z-index: 1050;
        }
        .alert-expiring {
            background-color: var(--expiring-soon);
            color: var(--text-primary);
        }
        .alert-expired {
            background-color: var(--expired);
            color: white;
        }
        .expired {
            background-color: rgba(220, 38, 38, 0.2);
        }
        .expiring-soon {
            background-color: rgba(255, 202, 40, 0.2);
        }
        .expiring-medium {
            background-color: rgba(245, 158, 11, 0.2);
        }
        .expiring-far {
            background-color: rgba(34, 197, 94, 0.2);
        }
        .expiration-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            display: inline-block;
        }
        .status-expired {
            background-color: var(--expired);
            color: white;
        }
        .status-soon {
            background-color: var(--expiring-soon);
            color: var(--text-primary);
        }
        .status-medium {
            background-color: var(--expiring-medium);
            color: var(--text-primary);
        }
        .status-far {
            background-color: var(--expiring-far);
            color: white;
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
                font-size: 0.7rem;
            }
            .card-title {
                font-size: 1rem;
            }
            .form-row {
                flex-direction: column;
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

                <!-- Expiring Batches Notification -->
                <?php if (!empty($expiring_batches)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>Expiration Alerts</strong>
                        <ul class="mb-0">
                            <?php foreach ($expiring_batches as $exp_batch): ?>
                                <?php
                                $status_class = $exp_batch['days_left'] < 0 ? 'alert-expired' :
                                    ($exp_batch['days_left'] <= 90 ? 'alert-expiring' :
                                        ($exp_batch['days_left'] <= 180 ? 'alert-warning' : 'alert-success'));
                                ?>
                                <li class="<?= $status_class ?>">
                                    Batch <?= htmlspecialchars($exp_batch['batch_number']) ?> (<?= htmlspecialchars($exp_batch['medicine_name']) ?>)
                                    expires on <?= htmlspecialchars($exp_batch['expiration_date']) ?>
                                    (<?= $exp_batch['days_left'] ?> days left)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Manage Medicine Expirations</h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="row mb-3 g-3">
                            <div class="col-md-4">
                                <label for="searchInput" class="form-label">Search by Medicine Name</label>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search by medicine name">
                            </div>
                            <div class="col-md-4">
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
                            <div class="col-md-4">
                                <label for="expiration_filter" class="form-label">Filter by Expiration</label>
                                <select id="expiration_filter" class="form-select">
                                    <option value="">All Expirations</option>
                                    <option value="expired">Expired</option>
                                    <option value="1-3">1-3 Months</option>
                                    <option value="3-6">3-6 Months</option>
                                    <option value="6-12">6-12 Months</option>
                                    <option value="12+">Beyond 12 Months</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="batchesTable" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Batch Number</th>
                                        <th>Medicine Name</th>
                                        <th>Quantity</th>
                                        <th>Purchase Price (₱)</th>
                                        <th>Supplier Name</th>
                                        <th>Expiration Date</th>
                                        <th>Status</th>
                                        <th>Added At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $batch): ?>
                                        <?php
                                        $row_class = '';
                                        $status_class = '';
                                        $status_text = '';
                                        if ($batch['expiration_date']) {
                                            $exp_date = new DateTime($batch['expiration_date']);
                                            $interval = $today->diff($exp_date);
                                            $days_left = $interval->days * ($interval->invert ? -1 : 1);
                                            if ($days_left < 0) {
                                                $row_class = 'expired';
                                                $status_class = 'status-expired';
                                                $status_text = 'Expired';
                                            } elseif ($days_left <= 90) {
                                                $row_class = 'expiring-soon';
                                                $status_class = 'status-soon';
                                                $status_text = '1-3 Months';
                                            } elseif ($days_left <= 180) {
                                                $row_class = 'expiring-medium';
                                                $status_class = 'status-medium';
                                                $status_text = '3-6 Months';
                                            } elseif ($days_left <= 365) {
                                                $row_class = 'expiring-far';
                                                $status_class = 'status-far';
                                                $status_text = '6-12 Months';
                                            } else {
                                                $status_class = 'status-far';
                                                $status_text = '12+ Months';
                                            }
                                        }
                                        ?>
                                        <tr class="<?= $row_class ?>">
                                            <td><?= htmlspecialchars($batch['batch_number']) ?></td>
                                            <td><?= htmlspecialchars($batch['medicine_name']) ?></td>
                                            <td><?= htmlspecialchars($batch['quantity']) ?></td>
                                            <td><?= htmlspecialchars($batch['purchase_price'] ? number_format($batch['purchase_price'], 2) : '-') ?></td>
                                            <td><?= htmlspecialchars($batch['supplier_name'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($batch['expiration_date'] ?: '-') ?></td>
                                            <td><span class="expiration-status <?= $status_class ?>"><?= $status_text ?: '-' ?></span></td>
                                            <td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($batch['added_at']))) ?></td>
                                            <td>
                                                <form action="medicine_expiration.php" method="POST" style="display:inline;">
                                                    <input type="hidden" name="dispose_batch" value="1">
                                                    <input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to dispose this batch?')">
                                                        <i class="fas fa-trash"></i> Dispose
                                                    </button>
                                                </form>
                                            </td>
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
                            <a href="dispose_history.php" class="btn btn-info">
                                <i class="fas fa-history me-1"></i> Dispose History
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
            console.log('[SSCMS Medicine Inventory] Manage Expirations Initialized');

            // Initialize DataTable
            const batchesTable = $('#batchesTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[7, 'desc']], // Order by Added At (descending)
                searching: true,
                dom: 'rtip',
                stripeClasses: ['table-light', 'table-striped'],
                columnDefs: [
                    { orderable: false, targets: 8 }, // Disable sorting on Actions column
                    { 
                        targets: 6, // Status column
                        searchable: true
                    }
                ]
            });

            // Custom live search by medicine name
            $('#searchInput').on('input', function() {
                batchesTable.search($(this).val()).draw();
            });

            // Filter by medicine dropdown
            $('#medicine_filter').on('change', function() {
                const medicineId = $(this).val();
                if (medicineId) {
                    batchesTable.column(1).search($(this).find('option:selected').text(), true, false).draw();
                } else {
                    batchesTable.column(1).search('').draw();
                }
            });

            // Filter by expiration range
            $('#expiration_filter').on('change', function() {
                const filterValue = $(this).val();
                if (filterValue) {
                    batchesTable.column(6).search(filterValue, true, false).draw();
                } else {
                    batchesTable.column(6).search('').draw();
                }
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');

            // Initialize alerts
            $('.alert').alert();
        });
    </script>
</body>
</html>