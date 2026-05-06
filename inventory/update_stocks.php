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

// Handle barcode scanning via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan_barcode_ajax') {
    header('Content-Type: application/json');
    try {
        $barcode = filter_var($_POST['barcode'], FILTER_SANITIZE_STRING);
        $stmt = $conn->prepare("SELECT id, name FROM medicines WHERE barcode = ? AND is_active = 1");
        $stmt->execute([$barcode]);
        $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($medicine) {
            echo json_encode([
                'success' => true,
                'medicine' => $medicine,
                'message' => "Medicine found: {$medicine['name']}"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No active medicine found with this barcode.'
            ]);
        }
    } catch (Exception $e) {
        error_log("[SSCMS Medicine Inventory] Barcode lookup error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error processing barcode.'
        ]);
    }
    exit;
}

// Handle batch creation and stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_batch'])) {
    try {
        $conn->beginTransaction();
        $batches_added = false;

        // Log raw POST data for debugging
        error_log("[SSCMS Medicine Inventory] Raw POST data: " . json_encode($_POST));

        // Process batch submission
        if (isset($_POST['medicine_id'], $_POST['quantity'], $_POST['expiration_date'], $_POST['purchase_cost'], $_POST['supplier_name'])) {
            // Sanitize and validate inputs
            $medicine_id = filter_var($_POST['medicine_id'], FILTER_VALIDATE_INT);
            $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
            $expiration_date = filter_var($_POST['expiration_date'], FILTER_SANITIZE_STRING);
            $purchase_cost = filter_var($_POST['purchase_cost'], FILTER_VALIDATE_FLOAT, ['options' => ['default' => null]]);
            $supplier_name = filter_var($_POST['supplier_name'], FILTER_SANITIZE_STRING, ['options' => ['default' => null]]);

            // Validate inputs
            if (!$medicine_id || $quantity <= 0) {
                error_log("[SSCMS Medicine Inventory] Invalid input: medicine_id=$medicine_id, quantity=$quantity");
                throw new Exception('Invalid medicine ID or quantity.');
            }

            // Validate expiration date (required, must be in future or today)
            $exp_date = null;
            if ($expiration_date) {
                $date = DateTime::createFromFormat('Y-m-d', $expiration_date);
                if ($date && $date->format('Y-m-d') === $expiration_date && $date >= new DateTime()) {
                    $exp_date = $expiration_date;
                } else {
                    error_log("[SSCMS Medicine Inventory] Invalid or past expiration date: $expiration_date");
                    throw new Exception('Invalid or past expiration date.');
                }
            } else {
                error_log("[SSCMS Medicine Inventory] Expiration date is required");
                throw new Exception('Expiration date is required.');
            }

            // Check if medicine exists and is active
            $stmt = $conn->prepare("SELECT id, name, quantity FROM medicines WHERE id = ? AND is_active = 1");
            $stmt->execute([$medicine_id]);
            $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$medicine) {
                error_log("[SSCMS Medicine Inventory] Medicine not found or inactive: id=$medicine_id");
                throw new Exception('Medicine not found or is inactive in the database.');
            }

            // Generate unique batch number
            $batch_number = 'BATCH-' . time() . '-' . rand(1000, 9999);

            // Insert new batch
            $stmt = $conn->prepare("
                INSERT INTO medicine_batches (medicine_id, batch_number, quantity, purchase_price, supplier_name, expiration_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$medicine_id, $batch_number, $quantity, $purchase_cost, $supplier_name, $exp_date]);

            // Calculate new total quantity for audit log
            $old_quantity = $medicine['quantity'];
            $new_quantity = $old_quantity + $quantity;

            // Update medicines table
            $stmt = $conn->prepare("UPDATE medicines SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $medicine_id]);

            // Log to stock_audit_logs
            $stmt = $conn->prepare("
                INSERT INTO stock_audit_logs (medicine_id, medicine_name, batch_number, quantity_added, old_quantity, new_quantity, cost, supplier_name, expiration_date, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $medicine_id, $medicine['name'], $batch_number, $quantity, $old_quantity, $new_quantity,
                $purchase_cost, $supplier_name, $exp_date, $_SESSION['user_id']
            ]);

            $batches_added = true;
            error_log("[SSCMS Medicine Inventory] Batch added: medicine_id=$medicine_id, batch_number=$batch_number, quantity=$quantity");
        }

        if ($batches_added) {
            $conn->commit();
            $_SESSION['success_message'] = $batch_number; // Store batch number for modal
            error_log("[SSCMS Medicine Inventory] Transaction committed");
        } else {
            $conn->rollBack();
            $_SESSION['error_message'] = 'No valid batch data provided.';
            error_log("[SSCMS Medicine Inventory] No batches added, transaction rolled back");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("[SSCMS Medicine Inventory] Exception: " . $e->getMessage());
    }

    header('Location: update_stocks.php');
    exit;
}

// Fetch medicines for dropdown
$medicines = [];
try {
    $stmt = $conn->prepare("SELECT id, name FROM medicines WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Medicine Inventory] Error fetching medicines: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error fetching medicines.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Add Medicine Batch">
    <meta name="author" content="ICCB">
    <title>Add Medicine Batch - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
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
            z-index: 1060;
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
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            border-radius: 5px;
            font-size: 0.8rem;
        }
        .select2-container--bootstrap-5 .select2-selection--single {
            height: 38px !important;
            display: flex;
            align-items: center;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            padding-left: 12px;
            padding-right: 20px;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 36px;
            right: 3px;
        }
        .select2-dropdown {
            border-radius: 5px;
            border: 1px solid var(--border);
        }
        .select2-results__option {
            font-size: 0.8rem;
            padding: 8px 12px;
        }
        .select2-results__option--highlighted {
            background-color: var(--primary) !important;
            color: white !important;
        }
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .modal-header {
            background-color: var(--bscs-maroon);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .modal-body {
            padding: 1.5rem;
            text-align: center;
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
                <!-- Toast Container -->
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

                <!-- Success Modal for Batch Number -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="successModalLabel">Batch Added</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Batch added successfully! <br>
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
                        <h5 class="card-title">Add Medicine Batch</h5>
                    </div>
                    <div class="card-body p-3">
                        <!-- Barcode Scanner Section -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <label for="barcode" class="form-label">Scan Barcode</label>
                                <div class="barcode-input-container">
                                    <i class="fas fa-barcode"></i>
                                    <input type="text" id="barcode" name="barcode" class="form-control" placeholder="Scan or enter barcode" autocomplete="off">
                                </div>
                                <small class="text-muted">Scan barcode to automatically select medicine</small>
                            </div>
                        </div>

                        <!-- Batch Form -->
                        <form id="addBatchForm" action="update_stocks.php" method="POST">
                            <input type="hidden" name="add_batch" value="1">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="medicine_id" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                                    <select name="medicine_id" id="medicine_id" class="form-select" required>
                                        <option value="">Select Medicine</option>
                                        <?php foreach ($medicines as $medicine): ?>
                                            <option value="<?= htmlspecialchars($medicine['id']) ?>">
                                                <?= htmlspecialchars($medicine['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="quantity" class="form-label">Quantity Added <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="expiration_date" class="form-label">Expiration Date <span class="text-danger">*</span></label>
                                    <input type="date" name="expiration_date" id="expiration_date" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="purchase_cost" class="form-label">Purchase Cost (₱)</label>
                                    <input type="number" name="purchase_cost" id="purchase_cost" class="form-control" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="supplier_name" class="form-label">Supplier Name</label>
                                    <input type="text" name="supplier_name" id="supplier_name" class="form-control">
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-secondary" id="clearFormBtn">Clear</button>
                                <a href="stock_audit_logs.php" class="btn btn-secondary">
                                    <i class="fas fa-clipboard-list me-1"></i> View Audit Logs
                                </a>
                                <a href="view_batches.php" class="btn btn-secondary">
                                    <i class="fas fa-list me-1"></i> View Batches
                                </a>
                                <button type="submit" class="btn btn-primary" id="addBatchBtn">
                                    <i class="fas fa-save me-1"></i> Add Batch
                                    <span id="updateSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Medicine Inventory] Initialized');

            // Initialize Select2 for medicine dropdown with search
            $('#medicine_id').select2({
                placeholder: 'Search and select medicine',
                allowClear: true,
                width: '100%',
                theme: 'bootstrap-5',
                dropdownAutoWidth: true,
                minimumInputLength: 0,
                language: {
                    noResults: function() {
                        return "No medicines found";
                    },
                    inputTooShort: function() {
                        return "Type to search medicines";
                    }
                }
            });

            // Initialize modal if present
            if ($('#successModal').length) {
                const successModal = new bootstrap.Modal('#successModal', {
                    backdrop: 'static',
                    keyboard: false
                });
                successModal.show();
            }

            // Focus on barcode input by default
            $('#barcode').focus();

            // Barcode scanning with AJAX
            let barcodeTimeout;
            $('#barcode').on('input', function(e) {
                const barcode = $(this).val().trim();
                
                // Clear previous timeout
                if (barcodeTimeout) {
                    clearTimeout(barcodeTimeout);
                }
                
                if (barcode.length > 3) { // Start searching after 3 characters
                    barcodeTimeout = setTimeout(function() {
                        searchBarcode(barcode);
                    }, 500); // Wait 500ms after user stops typing
                }
            });

            // Search barcode function
            function searchBarcode(barcode) {
                $.ajax({
                    url: 'update_stocks.php',
                    type: 'POST',
                    data: {
                        action: 'scan_barcode_ajax',
                        barcode: barcode
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Select the medicine in dropdown
                            $('#medicine_id').val(response.medicine.id).trigger('change');
                            
                            // Clear barcode input
                            $('#barcode').val('');
                            
                            // Focus on quantity input
                            $('#quantity').focus();
                            
                            // Show success message
                            showToast('success', response.message);
                        } else {
                            showToast('danger', response.message);
                            $('#barcode').focus();
                        }
                    },
                    error: function() {
                        showToast('danger', 'Error processing barcode scan');
                        $('#barcode').focus();
                    }
                });
            }

            // Enter key on barcode input
            $('#barcode').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    const barcode = $(this).val().trim();
                    if (barcode.length > 0) {
                        searchBarcode(barcode);
                    }
                }
            });

            // Clear Form Button
            $('#clearFormBtn').on('click', function() {
                $('#addBatchForm')[0].reset();
                $('#medicine_id').val('').trigger('change');
                $('#barcode').val('');
                $('#barcode').focus();
            });

            // Form Validation for Add Batch
            $('#addBatchForm').on('submit', function(e) {
                const medicineId = $('#medicine_id').val();
                const quantity = parseInt($('#quantity').val());
                const purchaseCost = parseFloat($('#purchase_cost').val());
                const expirationDate = $('#expiration_date').val();

                if (!medicineId) {
                    e.preventDefault();
                    showToast('danger', 'Please select a medicine.');
                    return;
                }

                if (!quantity || quantity <= 0) {
                    e.preventDefault();
                    showToast('danger', 'Quantity must be greater than 0.');
                    return;
                }

                if (purchaseCost && purchaseCost < 0) {
                    e.preventDefault();
                    showToast('danger', 'Purchase cost cannot be negative.');
                    return;
                }

                if (!expirationDate) {
                    e.preventDefault();
                    showToast('danger', 'Expiration date is required.');
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

                // Show loading state
                $('#addBatchBtn').prop('disabled', true);
                $('#updateSpinner').removeClass('d-none');
            });

            // Initialize existing toasts (only for errors)
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');

            // Set minimum date for expiration date (today)
            const today = new Date().toISOString().split('T')[0];
            $('#expiration_date').attr('min', today);
        });
    </script>
</body>
</html>