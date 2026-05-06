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

// Handle form submission for editing medicine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_medicine'])) {
    try {
        $conn->beginTransaction();

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $generic_name = filter_input(INPUT_POST, 'generic_name', FILTER_SANITIZE_STRING);
        $barcode = filter_input(INPUT_POST, 'barcode', FILTER_SANITIZE_STRING);

        // Validate inputs
        if (!$id || !$name || !$generic_name) {
            throw new Exception('Invalid medicine ID, name, or generic name.');
        }
        if ($barcode) {
            $stmt = $conn->prepare("SELECT id FROM medicines WHERE barcode = ? AND id != ? AND is_active = 1");
            $stmt->execute([$barcode, $id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                throw new Exception("Barcode already exists for another active medicine.");
            }
        }

        // Nullable fields
        $barcode = $barcode ?: null;

        // Update medicines table
        $stmt = $conn->prepare("UPDATE medicines SET name = ?, generic_name = ?, barcode = ? WHERE id = ?");
        $stmt->execute([$name, $generic_name, $barcode, $id]);

        $conn->commit();
        $_SESSION['success_message'] = 'Medicine updated successfully!';
        error_log("[SSCMS Medicine Inventory] Medicine updated: id=$id, name=$name");
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("[SSCMS Medicine Inventory] Error: " . $e->getMessage());
    }

    header('Location: edit_medicine.php');
    exit;
}

// Handle AJAX soft deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_medicine'])) {
    header('Content-Type: application/json');
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception('Invalid medicine ID.');
        }

        // Check if medicine exists and is active
        $stmt = $conn->prepare("SELECT id FROM medicines WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Medicine not found or already deleted.');
        }

        // Soft delete by setting is_active = 0
        $stmt = $conn->prepare("UPDATE medicines SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to mark medicine as deleted.');
        }

        error_log("[SSCMS Medicine Inventory] Medicine soft deleted: id=$id");
        echo json_encode(['success' => true, 'message' => 'Medicine deleted successfully!']);
    } catch (Exception $e) {
        error_log("[SSCMS Medicine Inventory] Soft delete error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    ob_end_flush();
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
            padding: 0.75rem;
            min-height: 100vh;
        }
        .card {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 1rem;
        }
        .card-header {
            background-color: var(--primary);
            border-bottom: 1px solid var(--border);
            padding: 0.5rem 0.75rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }
        .table {
            font-size: 0.65rem;
            table-layout: auto;
            width: 100%;
        }
        .table th, .table td {
            padding: 0.3rem;
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
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all var(--transition-speed);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
            font-size: 0.8rem;
        }
        .btn-danger:hover {
            background-color: var(--danger-dark);
            border-color: var(--danger-dark);
        }
        .btn-edit {
            background-color: var(--primary);
            border-color: var(--primary);
            font-size: 0.8rem;
        }
        .btn-edit:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
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
            font-size: 1rem;
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
        .status-low {
            color: var(--danger);
            font-weight: 600;
        }
        .status-good {
            color: var(--success);
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
            .dashboard-title {
                font-size: 0.9rem;
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
                        <div>
                            <i class="fas fa-capsules me-1"></i>
                            Medicine Details
                        </div>
                        <div>
                            <a href="medicine_expiration.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-hourglass-end me-1"></i> View Expiration Logs
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="searchInput" class="form-label">Search Medicines</label>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search by name or generic name">
                            </div>
                            <div class="col-md-6">
                                <label for="statusFilter" class="form-label">Filter by Stock Status</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="">All</option>
                                    <option value="Low">Low</option>
                                    <option value="Good">Good</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="medicineTable" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">Medicine Name</th>
                                        <th style="width: 30%;">Generic Name</th>
                                        <th style="width: 15%;">Quantity</th>
                                        <th style="width: 15%;">Stock Status</th>
                                        <th style="width: 10%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Edit Medicine Modal -->
        <div class="modal fade" id="editMedicineModal" tabindex="-1" aria-labelledby="editMedicineModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="editMedicineModalLabel">Edit Medicine</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editMedicineForm" action="edit_medicine.php" method="POST">
                            <input type="hidden" name="edit_medicine" value="1">
                            <input type="hidden" name="id" id="editMedicineId">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="editBarcode" class="form-label">Barcode</label>
                                        <div class="barcode-input-container">
                                            <i class="fas fa-barcode"></i>
                                            <input type="text" class="form-control" name="barcode" id="editBarcode" placeholder="Scan or enter barcode" autocomplete="off">
                                        </div>
                                        <div class="form-text">Enter or scan the barcode (optional)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="editName" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" id="editName" required>
                                        <div class="form-text">Enter the name of the medicine</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="editGenericName" class="form-label">Generic Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="generic_name" id="editGenericName" required>
                                        <div class="form-text">Enter the generic name</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" id="clearEditFormBtn">Clear</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Medicine
                                    <span id="editSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteMedicineModal" tabindex="-1" aria-labelledby="deleteMedicineModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="deleteMedicineModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete <strong id="deleteMedicineName"></strong>? 
                        <input type="hidden" id="deleteMedicineId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="fas fa-trash me-1"></i> Delete
                            <span id="deleteSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
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
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Medicine Inventory] Initialized');

            // Initialize Medicine DataTable
            const medicineTable = $('#medicineTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'asc']],
                searching: true,
                dom: 'rtip',
                stripeClasses: ['table-light', 'table-striped'],
                serverSide: false,
                ajax: {
                    url: 'fetch_medicines.php',
                    type: 'GET',
                    dataSrc: function(json) {
                        console.log('[SSCMS Medicine Inventory] Medicine JSON:', json);
                        if (!json.data) {
                            console.error('[SSCMS Medicine Inventory] No data in JSON response:', json);
                            showToast('danger', 'Failed to load medicines: ' + (json.error || 'No data returned'));
                            return [];
                        }
                        if (json.error) {
                            console.error('[SSCMS Medicine Inventory] Error in JSON response:', json.error);
                            showToast('danger', 'Error: ' + json.error);
                            return [];
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('[SSCMS Medicine Inventory] AJAX error:', xhr.status, error, thrown);
                        console.log('[SSCMS Medicine Inventory] Response text:', xhr.responseText);
                        showToast('danger', 'Error fetching medicines. Status: ' + xhr.status + '. Check console.');
                    }
                },
                columns: [
                    { data: 'name' },
                    { 
                        data: 'generic_name',
                        render: function(data) {
                            return data || '-';
                        }
                    },
                    { data: 'quantity' },
                    {
                        data: 'quantity',
                        render: function(data) {
                            const status = data < 300 ? 'Low' : 'Good';
                            const statusClass = data < 300 ? 'status-low' : 'status-good';
                            return `<span class="${statusClass}">${status}</span>`;
                        }
                    },
                    {
                        data: null,
                        render: function(data) {
                            return `
                                <div class="d-flex gap-1">
                                    <button class="btn btn-edit btn-sm edit-medicine-btn" 
                                            data-id="${data.id}" 
                                            data-name="${data.name}" 
                                            data-generic-name="${data.generic_name || ''}" 
                                            data-barcode="${data.barcode || ''}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-medicine-btn" 
                                            data-id="${data.id}" 
                                            data-name="${data.name}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            `;
                        }
                    }
                ]
            });

            // Custom live search
            $('#searchInput').on('input', function() {
                const searchValue = $(this).val();
                medicineTable.search(searchValue).draw();
            });

            // Stock status filter
            $('#statusFilter').on('change', function() {
                const status = $(this).val();
                if (status) {
                    medicineTable.column(3).search(status, false, false).draw();
                } else {
                    medicineTable.column(3).search('').draw();
                }
            });

            // Edit Medicine Button Click
            $('#medicineTable').on('click', '.edit-medicine-btn', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const generic_name = $(this).data('generic-name');
                const barcode = $(this).data('barcode');

                console.log('[SSCMS Medicine Inventory] Edit medicine:', { id, name, generic_name, barcode });

                $('#editMedicineId').val(id);
                $('#editName').val(name);
                $('#editGenericName').val(generic_name);
                $('#editBarcode').val(barcode);

                const modal = new bootstrap.Modal(document.getElementById('editMedicineModal'));
                modal.show();

                // Focus on barcode input in modal
                $('#editBarcode').focus();
            });

            // Handle barcode input in edit modal
            let barcodeTimeout;
            $('#editBarcode').on('input', function(e) {
                const barcode = $(this).val().trim();
                if (barcodeTimeout) {
                    clearTimeout(barcodeTimeout);
                }
                if (barcode.length > 0) {
                    barcodeTimeout = setTimeout(function() {
                        $('#editName').focus();
                    }, 500);
                }
            });

            $('#editBarcode').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const barcode = $(this).val().trim();
                    if (barcode.length > 0) {
                        $('#editName').focus();
                    }
                }
            });

            // Clear Edit Form
            $('#clearEditFormBtn').on('click', function() {
                $('#editMedicineForm')[0].reset();
                $('#editMedicineId').val($('#editMedicineId').val()); // Preserve ID
                $('#editBarcode').focus();
            });

            // Delete Medicine Button Click
            $('#medicineTable').on('click', '.delete-medicine-btn', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');

                console.log('[SSCMS Medicine Inventory] Delete medicine:', { id, name });

                $('#deleteMedicineId').val(id);
                $('#deleteMedicineName').text(name);

                const modal = new bootstrap.Modal(document.getElementById('deleteMedicineModal'));
                modal.show();
            });

            // Confirm Delete
            $('#confirmDeleteBtn').on('click', function() {
                const id = $('#deleteMedicineId').val();
                const spinner = $('#deleteSpinner');
                const button = $(this);
                spinner.removeClass('d-none');
                button.prop('disabled', true);

                $.ajax({
                    url: 'edit_medicine.php',
                    type: 'POST',
                    data: { delete_medicine: true, id: id },
                    dataType: 'json',
                    success: function(response) {
                        spinner.addClass('d-none');
                        button.prop('disabled', false);
                        if (response.success) {
                            medicineTable.ajax.reload();
                            showToast('success', response.message);
                            $('#deleteMedicineModal').modal('hide');
                        } else {
                            showToast('danger', 'Error: ' + response.error);
                        }
                    },
                    error: function(xhr, error, thrown) {
                        spinner.addClass('d-none');
                        button.prop('disabled', false);
                        console.error('[SSCMS Medicine Inventory] Delete AJAX error:', xhr.status, error, thrown);
                        showToast('danger', 'Error deleting medicine. Check console.');
                    }
                });
            });

            // Form Validation
            $('#editMedicineForm').on('submit', function(e) {
                const form = $(this);
                const spinner = form.find('.spinner-border');
                const name = $('#editName').val().trim();
                const genericName = $('#editGenericName').val().trim();

                if (!name) {
                    e.preventDefault();
                    showToast('danger', 'Medicine name is required.');
                    return;
                }
                if (!genericName) {
                    e.preventDefault();
                    showToast('danger', 'Generic name is required.');
                    return;
                }

                spinner.removeClass('d-none');
                form.find('[type=submit]').prop('disabled', true);
            });

            // Toast function
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
                $toast.toast({ delay: 3000 });
                $toast.toast('show');
                $toast.on('hidden.bs.toast', function() {
                    $(this).remove();
                });
            }

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>