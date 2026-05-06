<?php
ob_start(); // Start output buffering
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Scan Items] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        $barcode = trim(filter_input(INPUT_POST, 'barcode', FILTER_SANITIZE_STRING));

        if (isset($_POST['update_stock'])) {
            // Update existing medicine
            $id = filter_var($_POST['medicine_id'], FILTER_VALIDATE_INT);
            $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
            $cost = filter_var($_POST['cost'], FILTER_VALIDATE_FLOAT, ['options' => ['default' => null]]);

            if ($id && $quantity !== null && $quantity > 0 && $cost !== null && $cost >= 0) {
                // Get current medicine details
                $stmt = $conn->prepare("SELECT name, quantity FROM medicines WHERE id = ?");
                $stmt->execute([$id]);
                $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($medicine) {
                    $old_quantity = $medicine['quantity'];
                    $new_quantity = $old_quantity + $quantity;

                    // Update medicine quantity
                    $stmt = $conn->prepare("UPDATE medicines SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_quantity, $id]);

                    // Log to stock_audit_logs
                    $stmt = $conn->prepare("INSERT INTO stock_audit_logs (medicine_id, medicine_name, quantity_added, old_quantity, new_quantity, cost, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $medicine['name'], $quantity, $old_quantity, $new_quantity, $cost, $_SESSION['user_id']]);

                    $_SESSION['success_message'] = 'Stock updated successfully!';
                    error_log("[SSCMS Scan Items] Stock updated: id=$id, new_quantity=$new_quantity");
                } else {
                    throw new Exception("Medicine not found.");
                }
            } else {
                throw new Exception("Invalid quantity or cost.");
            }
        } elseif (isset($_POST['add_medicine'])) {
            // Add new medicine
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $generic_name = filter_input(INPUT_POST, 'generic_name', FILTER_SANITIZE_STRING);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
            $purchase_price = filter_input(INPUT_POST, 'purchase_price', FILTER_VALIDATE_FLOAT);
            $supplier = filter_input(INPUT_POST, 'supplier', FILTER_SANITIZE_STRING);
            $expiration_date = filter_input(INPUT_POST, 'expiration_date', FILTER_SANITIZE_STRING);

            // Validate inputs
            if (!$name || !$generic_name || $quantity === false || $quantity < 0) {
                throw new Exception('Invalid medicine name, generic name, or quantity.');
            }
            if ($purchase_price === false || $purchase_price < 0) {
                throw new Exception('Invalid purchase price.');
            }
            if ($expiration_date && !DateTime::createFromFormat('Y-m-d', $expiration_date)) {
                throw new Exception('Invalid expiration date format.');
            }
            if (empty($barcode)) {
                throw new Exception("Barcode is required.");
            }

            // Check barcode uniqueness
            $stmt = $conn->prepare("SELECT id FROM medicines WHERE barcode = ?");
            $stmt->execute([$barcode]);
            if ($stmt->fetch()) {
                throw new Exception("Barcode already exists.");
            }

            // Nullable fields
            $supplier = $supplier ?: null;
            $expiration_date = $expiration_date ?: null;

            // Insert medicine
            $stmt = $conn->prepare("INSERT INTO medicines (name, generic_name, quantity, purchase_price, supplier, expiration_date, barcode) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $generic_name, $quantity, $purchase_price, $supplier, $expiration_date, $barcode]);

            $medicine_id = $conn->lastInsertId();

            // Log to stock_audit_logs if quantity > 0
            if ($quantity > 0) {
                $cost = $quantity * $purchase_price;
                $stmt = $conn->prepare("INSERT INTO stock_audit_logs (medicine_id, medicine_name, quantity_added, old_quantity, new_quantity, cost, purchase_price, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$medicine_id, $name, $quantity, 0, $quantity, $cost, $purchase_price, $_SESSION['user_id']]);
            }

            $_SESSION['success_message'] = 'Medicine added successfully!';
            error_log("[SSCMS Medicine Inventory] Medicine added: name=$name, quantity=$quantity");
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("[SSCMS Medicine Inventory] Error: " . $e->getMessage());
    }

    header('Location: scan_items.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Scan Medicine Items">
    <meta name="author" content="ICCB">
    <title>Scan Medicine Items - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
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
        .form-control {
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
        .scanning {
            border: 2px solid var(--primary);
            animation: scanning 0.5s ease-in-out infinite alternate;
        }
        @keyframes scanning {
            0% { box-shadow: 0 0 5px var(--primary); }
            100% { box-shadow: 0 0 15px var(--primary); }
        }
        .modal-content {
            border-radius: 10px;
            font-size: 0.8rem;
        }
        .modal-header {
            background-color: var(--bscs-maroon);
            color: white;
        }
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        .dashboard-title {
            font-size: 1.25rem;
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
            .dashboard-title {
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
                <div class="dashboard-header">
                    <h3 class="dashboard-title">
                        <i class="fas fa-barcode"></i>
                        Scan Medicine Items
                    </h3>
                </div>

                <nav aria-label="breadcrumb" class="custom-breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="inventory.php" class="text-decoration-none">Medicine Inventory</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Scan Items</li>
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

                <div class="card">
                    <div class="card-header">
                        <div>
                            <i class="fas fa-barcode me-1"></i>
                            Scan Medicine Barcode
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="barcode" class="form-label">Scan Barcode</label>
                                    <input type="text" class="form-control" id="barcode" name="barcode" placeholder="Scan or enter barcode" autocomplete="off" autofocus>
                                    <div class="form-text">Scan or enter the medicine barcode</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal for Update/Add Form -->
            <div class="modal fade" id="scanModal" tabindex="-1" aria-labelledby="scanModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-semibold" id="scanModalLabel">Add New Medicine</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="modalFormContainer"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="scanAgainBtn" data-bs-dismiss="modal">
                                <i class="fas fa-barcode me-1"></i> Scan Again
                            </button>
                            <button type="button" class="btn btn-primary" id="submitModalBtn">
                                <i class="fas fa-save me-1"></i> Submit
                                <span id="submitSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="clinic-footer">
                <div class="container-fluid">
                    <p class="mb-0">Clinic Management System © 2025 ICCB. All rights reserved.</p>
                </div>
            </footer>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Scan Items] Initialized');

            // Initialize modal
            const scanModal = new bootstrap.Modal(document.getElementById('scanModal'));

            // Auto-focus barcode input
            $('#barcode').focus();

            // Handle barcode input
            $('#barcode').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const barcode = $(this).val().trim();
                    if (!barcode) {
                        alert('Please enter or scan a barcode.');
                        return;
                    }

                    // Add scanning effect
                    $(this).addClass('scanning');
                    // Placeholder for scan sound
                    console.log('[SSCMS Scan Items] Scan sound placeholder');

                    // Fetch medicine by barcode
                    $.ajax({
                        url: 'fetch_for_scan.php',
                        type: 'GET',
                        data: { barcode: barcode },
                        success: function(response) {
                            console.log('[SSCMS Scan Items] Fetch response:', response);
                            $('#barcode').removeClass('scanning');
                            const modalFormContainer = $('#modalFormContainer');
                            modalFormContainer.empty();

                            if (response.error) {
                                alert('Error: ' + response.error);
                                $('#barcode').val('').focus();
                                return;
                            }

                            if (response.data && response.data.id) {
                                // Medicine exists, show update form
                                modalFormContainer.html(`
                                    <form id="updateStockForm" action="scan_items.php" method="POST">
                                        <input type="hidden" name="update_stock" value="1">
                                        <input type="hidden" name="medicine_id" value="${response.data.id}">
                                        <input type="hidden" name="barcode" value="${barcode}">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label class="form-label">Medicine Name</label>
                                                    <input type="text" class="form-control" value="${response.data.name}" readonly>
                                                    <div class="form-text">Name of the medicine</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label class="form-label">Generic Name</label>
                                                    <input type="text" class="form-control" value="${response.data.generic_name || '-'}" readonly>
                                                    <div class="form-text">Generic name of the medicine</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label class="form-label">Current Quantity</label>
                                                    <input type="text" class="form-control" value="${response.data.quantity}" readonly>
                                                    <div class="form-text">Current quantity in stock</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="quantity" class="form-label">New Stock <span class="text-danger">*</span></label>
                                                    <input type="number" class="form-control" id="quantity" name="quantity" min="0" placeholder="Enter quantity" required>
                                                    <div class="form-text">Enter the additional quantity</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="cost" class="form-label">Cost of New Stock (₱) <span class="text-danger">*</span></label>
                                                    <input type="number" class="form-control" id="cost" name="cost" min="0" step="0.01" placeholder="Enter cost" required>
                                                    <div class="form-text">Enter the cost of the new stock</div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                `);
                                $('#submitModalBtn').html('<i class="fas fa-save me-1"></i> Update Stock');
                                $('#submitModalBtn').attr('data-form', 'update');
                            } else {
                                // Medicine not found, show add form
                                modalFormContainer.html(`
                                    <form id="addMedicineForm" action="scan_items.php" method="POST">
                                        <input type="hidden" name="add_medicine" value="1">
                                        <input type="hidden" name="barcode" value="${barcode}">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="name" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Paracetamol" required>
                                                    <div class="form-text">Enter the name of the medicine</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="generic_name" class="form-label">Generic Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="generic_name" name="generic_name" placeholder="e.g., Acetaminophen" required>
                                                    <div class="form-text">Enter the generic name</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="quantity" class="form-label">Initial Quantity <span class="text-danger">*</span></label>
                                                    <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="0" required>
                                                    <div class="form-text">Enter the quantity available</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="purchase_price" class="form-label">Purchase Price (₱) <span class="text-danger">*</span></label>
                                                    <input type="number" class="form-control" id="purchase_price" name="purchase_price" min="0" step="0.01" value="0.00" required>
                                                    <div class="form-text">Enter the purchase price per unit</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="supplier" class="form-label">Supplier</label>
                                                    <input type="text" class="form-control" id="supplier" name="supplier" placeholder="e.g., ABC Pharma">
                                                    <div class="form-text">Enter the supplier name (optional)</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="expiration_date" class="form-label">Expiration Date</label>
                                                    <input type="date" class="form-control" id="expiration_date" name="expiration_date">
                                                    <div class="form-text">Enter the expiration date (optional)</div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                `);
                                $('#submitModalBtn').html('<i class="fas fa-save me-1"></i> Add Medicine');
                                $('#submitModalBtn').attr('data-form', 'add');
                            }

                            // Show modal
                            scanModal.show();
                            $('#barcode').val('').focus();
                        },
                        error: function(xhr, error, thrown) {
                            console.error('[SSCMS Scan Items] AJAX error:', xhr.status, error);
                            $('#barcode').removeClass('scanning').val('').focus();
                            alert('Error fetching medicine. Check console for details.');
                        }
                    });
                }
            });

            // Handle submit button
            $('#submitModalBtn').on('click', function() {
                const formType = $(this).attr('data-form');
                const form = formType === 'update' ? $('#updateStockForm') : $('#addMedicineForm');
                const spinner = $('#submitSpinner');
                spinner.removeClass('d-none');
                $(this).prop('disabled', true);
                form.submit();
            });

            // Handle scan again button
            $('#scanAgainBtn').on('click', function() {
                $('#barcode').val('').focus();
                scanModal.hide();
            });

            // Form validation
            $('#addMedicineForm').on('submit', function(e) {
                const form = $(this);
                const spinner = $('#submitSpinner');
                spinner.removeClass('d-none');
                form.find('[type=submit]').prop('disabled', true);
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html> 