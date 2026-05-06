<?php
ob_start();
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check authentication (skip redirect for AJAX requests)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session. Session data: " . print_r($_SESSION, true));
    if (isset($_GET['fetch_supplies']) || isset($_POST['delete_supply'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized access. Please log in.']);
        ob_end_flush();
        exit;
    }
    header('Location: /login.php');
    exit;
}

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
        $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $expiration_date = trim(filter_input(INPUT_POST, 'expiration_date', FILTER_SANITIZE_STRING)) ?: null;

        // Validate inputs
        if (!$name || $quantity === false || $quantity < 0) {
            throw new Exception('Invalid supply name or quantity.');
        }

        if ($id) {
            // Update
            $stmt = $conn->prepare("UPDATE supplies SET name = ?, quantity = ?, expiration_date = ? WHERE id = ?");
            $stmt->execute([$name, $quantity, $expiration_date, $id]);
            $_SESSION['success_message'] = 'Supply updated successfully!';
            error_log("[SSCMS Supplies] Updated supply: id=$id, name=$name, quantity=$quantity");
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO supplies (name, quantity, expiration_date, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$name, $quantity, $expiration_date]);
            $_SESSION['success_message'] = 'Supply added successfully!';
            error_log("[SSCMS Supplies] Added supply: name=$name, quantity=$quantity");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("[SSCMS Supplies] Error: " . $e->getMessage());
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_supply'])) {
    header('Content-Type: application/json');
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception('Invalid supply ID.');
        }

        $stmt = $conn->prepare("DELETE FROM supplies WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Supply not found.');
        }

        error_log("[SSCMS Supplies] Deleted supply: id=$id");
        echo json_encode(['success' => true, 'message' => 'Supply deleted successfully!']);
    } catch (Exception $e) {
        error_log("[SSCMS Supplies] Delete error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    ob_end_flush();
    exit;
}

// Handle AJAX fetch
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_supplies'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->prepare("SELECT id, name, quantity, expiration_date, created_at FROM supplies ORDER BY id DESC");
        $stmt->execute();
        $supplies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("[SSCMS Supplies] Fetched " . count($supplies) . " supplies");
        echo json_encode(['data' => $supplies]);
    } catch (Exception $e) {
        error_log("[SSCMS Supplies] Fetch error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch supplies: ' . $e->getMessage()]);
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
    <meta name="description" content="ICCBI Clinic Management System - Supplies Management">
    <meta name="author" content="ICCB">
    <title>ICCBI Clinic Supplies Management</title>
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
            background-color: var(--bscs-maroon);
            color: white;
            border-bottom: none;
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
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
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
        .btn-success {
            background-color: var(--success);
            border-color: var(--success);
        }
        .btn-success:hover {
            background-color: var(--success-dark);
            border-color: var(--success-dark);
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
            font-weight: 700;
            color: var(--bscs-maroon);
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

                <!-- Supplies Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-boxes me-1"></i>
                                Supplies Overview
                            </div>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplyModal">
                                <i class="fas fa-plus"></i> Add Supply
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table id="supplyTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 10%;">ID</th>
                                        <th style="width: 25%;">Supply Name</th>
                                        <th style="width: 15%;">Quantity</th>
                                        <th style="width: 20%;">Expiration Date</th>
                                        <th style="width: 20%;">Created At</th>
                                        <th style="width: 15%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Add Supply Modal -->
        <div class="modal fade" id="addSupplyModal" tabindex="-1" aria-labelledby="addSupplyModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="addSupplyModalLabel">Add New Supply</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addSupplyForm" method="POST">
                            <input type="hidden" name="save" value="1">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="addName" class="form-label">Supply Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" id="addName" required>
                                        <div class="form-text">Enter the name of the supply</div>
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
                                        <label for="addExpirationDate" class="form-label">Expiration Date</label>
                                        <input type="date" class="form-control" name="expiration_date" id="addExpirationDate">
                                        <div class="form-text">Enter the expiration date (optional)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Supply
                                    <span id="addSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Supply Modal -->
        <div class="modal fade" id="editSupplyModal" tabindex="-1" aria-labelledby="editSupplyModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="editSupplyModalLabel">Edit Supply</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editSupplyForm" method="POST">
                            <input type="hidden" name="save" value="1">
                            <input type="hidden" name="id" id="editSupplyId">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="editName" class="form-label">Supply Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" id="editName" required>
                                        <div class="form-text">Enter the name of the supply</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="editQuantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="quantity" id="editQuantity" min="0" required>
                                        <div class="form-text">Enter the quantity available</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="editExpirationDate" class="form-label">Expiration Date</label>
                                        <input type="date" class="form-control" name="expiration_date" id="editExpirationDate">
                                        <div class="form-text">Enter the expiration date (optional)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Supply
                                    <span id="editSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteSupplyModal" tabindex="-1" aria-labelledby="deleteSupplyModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="deleteSupplyModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete <strong id="deleteSupplyName"></strong>? This action cannot be undone.
                        <input type="hidden" id="deleteSupplyId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="fas fa-trash"></i> Delete
                            <span id="deleteSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

       
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Supplies] Initialized');
            const currentPath = window.location.pathname;
            console.log('[SSCMS Supplies] Current path:', currentPath);

            // Initialize DataTable
            const supplyTable = $('#supplyTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'desc']],
                language: { search: "", searchPlaceholder: "Search supplies..." },
                columnDefs: [
                    { orderable: false, targets: [5] },
                    { searchable: false, targets: [5] }
                ],
                serverSide: false,
                ajax: {
                    url: currentPath,
                    type: 'GET',
                    data: { fetch_supplies: true },
                    dataSrc: function(json) {
                        console.log('[SSCMS Supplies] Supply JSON:', json);
                        if (json.error) {
                            console.error('[SSCMS Supplies] Error in JSON response:', json.error);
                            alert('Error: ' + json.error);
                            return [];
                        }
                        if (!json || typeof json.data === 'undefined') {
                            console.error('[SSCMS Supplies] Invalid JSON response:', json);
                            alert('Failed to load supplies: Invalid response');
                            return [];
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('[SSCMS Supplies] AJAX error:', xhr.status, error, thrown);
                        console.log('[SSCMS Supplies] Response text:', xhr.responseText);
                        console.log('[SSCMS Supplies] Request URL:', this.url);
                        alert('Error fetching supplies. Status: ' + xhr.status + '. URL: ' + this.url + '. Check console for details.');
                    }
                },
                columns: [
                    { data: 'id' },
                    { data: 'name' },
                    { data: 'quantity' },
                    {
                        data: 'expiration_date',
                        render: function(data) {
                            return data ? new Date(data).toLocaleDateString('en-US', { dateStyle: 'short' }) : '-';
                        }
                    },
                    {
                        data: 'created_at',
                        render: function(data) {
                            return new Date(data).toLocaleString('en-US', { dateStyle: 'short', timeStyle: 'short' });
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(data) {
                            return `
                                <div class="d-flex gap-1">
                                    <button class="btn btn-warning btn-sm edit-supply-btn" 
                                            data-id="${data.id}" 
                                            data-name="${data.name}" 
                                            data-quantity="${data.quantity}" 
                                            data-expiration_date="${data.expiration_date || ''}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-supply-btn" 
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

            // Add Supply Form Submission
            $('#addSupplyForm').on('submit', function(e) {
                const form = $(this);
                const spinner = form.find('.spinner-border');
                spinner.removeClass('d-none');
                form.find('[type=submit]').prop('disabled', true);
            });

            // Edit Supply Button Click
            $('#supplyTable').on('click', '.edit-supply-btn', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const quantity = $(this).data('quantity');
                const expiration_date = $(this).data('expiration_date');

                console.log('[SSCMS Supplies] Edit supply:', { id, name, quantity, expiration_date });

                $('#editSupplyId').val(id);
                $('#editName').val(name);
                $('#editQuantity').val(quantity);
                $('#editExpirationDate').val(expiration_date);

                const modal = new bootstrap.Modal(document.getElementById('editSupplyModal'));
                modal.show();
            });

            // Edit Supply Form Submission
            $('#editSupplyForm').on('submit', function(e) {
                const form = $(this);
                const spinner = form.find('.spinner-border');
                spinner.removeClass('d-none');
                form.find('[type=submit]').prop('disabled', true);
            });

            // Delete Supply Button Click
            $('#supplyTable').on('click', '.delete-supply-btn', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');

                console.log('[SSCMS Supplies] Delete supply:', { id, name });

                $('#deleteSupplyId').val(id);
                $('#deleteSupplyName').text(name);

                const modal = new bootstrap.Modal(document.getElementById('deleteSupplyModal'));
                modal.show();
            });

            // Confirm Delete
            $('#confirmDeleteBtn').on('click', function() {
                const id = $('#deleteSupplyId').val();
                const spinner = $('#deleteSpinner');
                const button = $(this);
                spinner.removeClass('d-none');
                button.prop('disabled', true);

                $.ajax({
                    url: currentPath,
                    type: 'POST',
                    data: { delete_supply: true, id: id },
                    dataType: 'json',
                    success: function(response) {
                        spinner.addClass('d-none');
                        button.prop('disabled', false);
                        if (response.success) {
                            supplyTable.ajax.reload();
                            const toast = $(`
                                <div class="toast align-items-center text-bg-success border-0" role="alert">
                                    <div class="d-flex">
                                        <div class="toast-body">${response.message}</div>
                                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                                    </div>
                                </div>
                            `);
                            $('.toast-container').append(toast);
                            toast.toast({ delay: 3000 }).toast('show');
                            $('#deleteSupplyModal').modal('hide');
                        } else {
                            const toast = $(`
                                <div class="toast align-items-center text-bg-danger border-0" role="alert">
                                    <div class="d-flex">
                                        <div class="toast-body">Error: ${response.error}</div>
                                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                                    </div>
                                </div>
                            `);
                            $('.toast-container').append(toast);
                            toast.toast({ delay: 3000 }).toast('show');
                        }
                    },
                    error: function(xhr, error, thrown) {
                        spinner.addClass('d-none');
                        button.prop('disabled', false);
                        console.error('[SSCMS Supplies] Delete AJAX error:', xhr.status, error, thrown);
                        const toast = $(`
                            <div class="toast align-items-center text-bg-danger border-0" role="alert">
                                <div class="d-flex">
                                    <div class="toast-body">Error deleting supply. Check console.</div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                                </div>
                            </div>
                        `);
                        $('.toast-container').append(toast);
                        toast.toast({ delay: 3000 }).toast('show');
                    }
                });
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>