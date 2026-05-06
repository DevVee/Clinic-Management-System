<?php
ob_start(); // Start output buffering
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Handle form submission for adding medicine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    try {
        $conn->beginTransaction();

        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $generic_name = filter_input(INPUT_POST, 'generic_name', FILTER_SANITIZE_STRING);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $purchase_price = filter_input(INPUT_POST, 'purchase_price', FILTER_VALIDATE_FLOAT);
        $supplier = filter_input(INPUT_POST, 'supplier', FILTER_SANITIZE_STRING);
        $expiration_date = filter_input(INPUT_POST, 'expiration_date', FILTER_SANITIZE_STRING);
        $barcode = filter_input(INPUT_POST, 'barcode', FILTER_SANITIZE_STRING);

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
        if ($barcode) {
            $stmt = $conn->prepare("SELECT id, name FROM medicines WHERE barcode = ?");
            $stmt->execute([$barcode]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                throw new Exception("Barcode already exists");
            }
        }

        // Nullable fields
        $supplier = $supplier ?: null;
        $expiration_date = $expiration_date ?: null;
        $barcode = $barcode ?: null;

        // Insert into medicines table
        $stmt = $conn->prepare("INSERT INTO medicines (name, generic_name, quantity, purchase_price, supplier, expiration_date, barcode) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $generic_name, $quantity, $purchase_price, $supplier, $expiration_date, $barcode]);
        $medicine_id = $conn->lastInsertId();

        // Generate unique batch number
        $batch_number = 'BATCH-' . time() . '-' . rand(1000, 9999);

        // Insert into medicine_batches table
        $stmt = $conn->prepare("
            INSERT INTO medicine_batches (medicine_id, batch_number, quantity, purchase_price, supplier_name, expiration_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$medicine_id, $batch_number, $quantity, $purchase_price, $supplier, $expiration_date]);

        // Log to stock_audit_logs
        $stmt = $conn->prepare("
            INSERT INTO stock_audit_logs (medicine_id, medicine_name, batch_number, quantity_added, old_quantity, new_quantity, cost, supplier_name, expiration_date, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $medicine_id, $name, $batch_number, $quantity, 0, $quantity,
            $purchase_price, $supplier, $expiration_date, $_SESSION['user_id']
        ]);

        $conn->commit();
        $_SESSION['success_message'] = $batch_number; // Store batch number for modal
        error_log("[SSCMS Medicine Inventory] Medicine and batch added: name=$name, quantity=$quantity, batch_number=$batch_number");
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("[SSCMS Medicine Inventory] Error: " . $e->getMessage());
    }

    header('Location: add_new_medicine.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Add New Medicine">
    <meta name="author" content="ICCB">
    <title>Add New Medicine - Clinic Management</title>
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
        .card-header {
            background-color: var(--primary);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1rem;
            color: white;
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
        .modal-header {
            background-color: var(--bscs-maroon);
            color: white;
        }
        .toast-container {
            position: fixed;
            top: 0.75rem;
            right: 0.75rem;
        }
        .dashboard-title {
            font-size: 1.25rem;
        }
        .barcode-input-container {
            position: relative;
        }
        .barcode-input-container .fa-barcode {
            position: absolute;
            top: 50%;
            left: 10px;
            transform: translateY(-50%);
            color: var(--text-secondary);
            z-index: 5;
        }
        .barcode-input-container input {
            padding-left: 35px;
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
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="successModalLabel">Medicine Added</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Medicine added successfully! <br>
                                    Batch Number: <strong><?= htmlspecialchars($_SESSION['success_message']) ?></strong>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <i class="fas fa-capsules me-1"></i>
                            Add Medicine
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <form id="addMedicineForm" action="add_new_medicine.php" method="POST">
                            <input type="hidden" name="add_medicine" value="1">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="addBarcode" class="form-label">Barcode</label>
                                        <div class="barcode-input-container">
                                            <i class="fas fa-barcode"></i>
                                            <input type="text" class="form-control" name="barcode" id="addBarcode" placeholder="Scan or enter barcode" autocomplete="off">
                                        </div>
                                        <div class="form-text">Enter or scan the barcode (optional)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="addName" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" id="addName" required>
                                        <div class="form-text">Enter the name of the medicine</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="addGenericName" class="form-label">Generic Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="generic_name" id="addGenericName" required>
                                        <div class="form-text">Enter the generic name</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="addQuantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="quantity" id="addQuantity" min="0" required>
                                        <div class="form-text">Enter the quantity available</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="addPurchasePrice" class="form-label">Purchase Price <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="purchase_price" id="addPurchasePrice" min="0" step="0.01" required>
                                        <div class="form-text">Enter the purchase price per unit</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="addSupplier" class="form-label">Supplier</label>
                                        <input type="text" class="form-control" name="supplier" id="addSupplier">
                                        <div class="form-text">Enter the supplier name (optional)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="addExpirationDate" class="form-label">Expiration Date</label>
                                        <input type="date" class="form-control" name="expiration_date" id="addExpirationDate">
                                        <div class="form-text">Enter the expiration date (optional)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" id="clearFormBtn">Clear</button>
                                <a href="inventory.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="addMedicineBtn">
                                    <i class="fas fa-save"></i> Add Medicine
                                    <span id="addSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Medicine Inventory] Add Medicine Initialized');

            // Initialize modal if present
            if ($('#successModal').length) {
                const successModal = new bootstrap.Modal('#successModal', {
                    backdrop: 'static',
                    keyboard: false
                });
                successModal.show();
            }

            // Focus on barcode input by default
            $('#addBarcode').focus();

            // Handle barcode input
            let barcodeTimeout;
            $('#addBarcode').on('input', function(e) {
                const barcode = $(this).val().trim();
                if (barcodeTimeout) {
                    clearTimeout(barcodeTimeout);
                }
                if (barcode.length > 0) {
                    barcodeTimeout = setTimeout(function() {
                        $('#addName').focus();
                    }, 500);
                }
            });

            $('#addBarcode').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const barcode = $(this).val().trim();
                    if (barcode.length > 0) {
                        $('#addName').focus();
                    }
                }
            });

            // Clear Form
            $('#clearFormBtn').on('click', function() {
                $('#addMedicineForm')[0].reset();
                $('#addBarcode').focus();
            });

            // Form Validation
            $('#addMedicineForm').on('submit', function(e) {
                const form = $(this);
                const spinner = form.find('.spinner-border');
                const quantity = parseInt($('#addQuantity').val());
                const purchasePrice = parseFloat($('#addPurchasePrice').val());
                const expirationDate = $('#addExpirationDate').val();
                const barcode = $('#addBarcode').val().trim();

                if (quantity < 0) {
                    e.preventDefault();
                    showToast('danger', 'Quantity cannot be negative.');
                    return;
                }

                if (purchasePrice < 0) {
                    e.preventDefault();
                    showToast('danger', 'Purchase price cannot be negative.');
                    return;
                }

                if (expirationDate) {
                    const today = new Date().toISOString().split('T')[0];
                    if (expirationDate < today) {
                        e.preventDefault();
                        showToast('danger', 'Expiration date cannot be in the past.');
                        return;
                    }
                }

                spinner.removeClass('d-none');
                $('#addMedicineBtn').prop('disabled', true);
            });

            // Toast function for dynamically created toasts
            function showToast(type, message) {
                const toastHtml = `
                    <div class="toast align-items-center text-bg-${type} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `;
                $('.toast-container').append(toastHtml);
                const $toast = $('.toast').last();
                $toast.toast({ autohide: false }); // No auto-hide for dynamic toasts
                $toast.toast('show');
                $toast.on('hidden.bs.toast', function() {
                    $(this).remove();
                });
            }

            // Initialize error toasts
            $('.toast.text-bg-danger').toast({ delay: 3000 }); // Error toasts auto-close after 3 seconds
            $('.toast.text-bg-danger').toast('show');

            // Set minimum date for expiration date
            const today = new Date().toISOString().split('T')[0];
            $('#addExpirationDate').attr('min', today);
        });
    </script>
</body>
</html>