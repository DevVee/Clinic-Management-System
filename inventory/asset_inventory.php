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

// Handle AJAX deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_asset'])) {
    header('Content-Type: application/json');
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception('Invalid asset ID.');
        }

        // Optionally, delete the associated picture file
        $stmt = $conn->prepare("SELECT picture FROM assets WHERE id = ?");
        $stmt->execute([$id]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($asset && $asset['picture'] && file_exists('../' . $asset['picture'])) {
            unlink('../' . $asset['picture']);
        }

        $stmt = $conn->prepare("DELETE FROM assets WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Asset not found.');
        }

        error_log("[SSCMS Asset Inventory] Asset deleted: id=$id");
        echo json_encode(['success' => true, 'message' => 'Asset deleted successfully!']);
    } catch (Exception $e) {
        error_log("[SSCMS Asset Inventory] Delete error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    ob_end_flush();
    exit;
}

// Handle AJAX fetch for DataTable
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_assets'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->prepare("SELECT id, name, quantity, picture, description, conditions, created_at FROM assets");
        $stmt->execute();
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $assets]);
    } catch (Exception $e) {
        error_log("[SSCMS Asset Inventory] Fetch error: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to fetch assets']);
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
    <meta name="description" content="ICCBI Clinic Management System - Assets Inventory">
    <meta name="author" content="ICCB">
    <title>ICCBI Clinic Assets Inventory</title>
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
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            font-size: 0.65rem;
            padding: 0.2rem 0.4rem;
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #138496;
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

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-boxes me-1"></i>
                                Assets Overview
                            </div>
                            <a href="add_asset.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Asset
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table id="assetTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Asset Name</th>
                                        <th style="width: 15%;">Quantity</th>
                                        <th style="width: 15%;">Condition</th>
                                        <th style="width: 25%;">Description</th>
                                        <th style="width: 10%;">Picture</th>
                                        <th style="width: 10%;">Created At</th>
                                        <th style="width: 10%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-success" id="downloadExcelBtn">
                                <i class="fas fa-file-excel me-1"></i> Download Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Edit Asset Modal -->
        <div class="modal fade" id="editAssetModal" tabindex="-1" aria-labelledby="editAssetModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="editAssetModalLabel">Edit Asset</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editAssetForm" action="add_asset.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="save_asset" value="1">
                            <input type="hidden" name="id" id="editAssetId">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="editName" class="form-label">Asset Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" id="editName" required>
                                        <div class="form-text">Enter the name of the asset</div>
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
                                        <label for="editPicture" class="form-label">Picture</label>
                                        <input type="file" class="form-control" name="picture" id="editPicture" accept="image/jpeg,image/png,image/gif">
                                        <div class="form-text">Upload a new image (JPEG, PNG, GIF, max 5MB, optional)</div>
                                        <div id="currentPicture" class="mt-2"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="editDescription" class="form-label">Description</label>
                                        <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                                        <div class="form-text">Enter the asset description (optional)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="editConditions" class="form-label">Condition</label>
                                        <select class="form-select" name="conditions" id="editConditions">
                                            <option value="">Select condition</option>
                                            <option value="Good">Good</option>
                                            <option value="New">New</option>
                                            <option value="Old">Old</option>
                                            <option value="Damaged">Damaged</option>
                                        </select>
                                        <div class="form-text">Select the condition of the asset (optional)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Asset
                                    <span id="editSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteAssetModal" tabindex="-1" aria-labelledby="deleteAssetModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold" id="deleteAssetModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete <strong id="deleteAssetName"></strong>? This action cannot be undone.
                        <input type="hidden" id="deleteAssetId">
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

        <footer class="clinic-footer">
            <div class="container-fluid">
                <p class="mb-0">ICCBI Clinic Management System © 2025 ICCB. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Asset Inventory] Initialized');

            // Initialize Asset DataTable
            const assetTable = $('#assetTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'asc']],
                language: { search: "", searchPlaceholder: "Search assets..." },
                columnDefs: [{ orderable: false, targets: 6 }],
                serverSide: false,
                ajax: {
                    url: 'asset_inventory.php',
                    type: 'GET',
                    data: { fetch_assets: true },
                    dataSrc: function(json) {
                        console.log('[SSCMS Asset Inventory] Asset JSON:', json);
                        if (!json.data) {
                            console.error('[SSCMS Asset Inventory] No data in JSON response:', json);
                            alert('Failed to load assets: ' + (json.error || 'No data returned'));
                            return [];
                        }
                        if (json.error) {
                            console.error('[SSCMS Asset Inventory] Error in JSON response:', json.error);
                            alert('Error: ' + json.error);
                            return [];
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('[SSCMS Asset Inventory] AJAX error:', xhr.status, error, thrown);
                        console.log('[SSCMS Asset Inventory] Response text:', xhr.responseText);
                        alert('Error fetching assets. Status: ' + xhr.status + '. Check console for details.');
                    }
                },
                columns: [
                    { data: 'name' },
                    { data: 'quantity' },
                    { 
                        data: 'conditions',
                        render: function(data) {
                            return data || '-';
                        }
                    },
                    { 
                        data: 'description',
                        render: function(data) {
                            return data || '-';
                        }
                    },
                    { 
                        data: 'picture',
                        render: function(data) {
                            return data ? `<a href="/${data}" target="_blank" class="btn btn-info btn-sm">View</a>` : '-';
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
                        render: function(data) {
                            return `
                                <div class="d-flex gap-1">
                                    <button class="btn btn-warning btn-sm edit-asset-btn" 
                                            data-id="${data.id}" 
                                            data-name="${data.name}" 
                                            data-quantity="${data.quantity}" 
                                            data-picture="${data.picture || ''}" 
                                            data-description="${data.description || ''}" 
                                            data-conditions="${data.conditions || ''}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-asset-btn" 
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

            // Edit Asset Button Click
            $('#assetTable').on('click', '.edit-asset-btn', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const quantity = $(this).data('quantity');
                const picture = $(this).data('picture');
                const description = $(this).data('description');
                const conditions = $(this).data('conditions');

                console.log('[SSCMS Asset Inventory] Edit asset:', { id, name, quantity, picture, description, conditions });

                $('#editAssetId').val(id);
                $('#editName').val(name);
                $('#editQuantity').val(quantity);
                $('#editDescription').val(description);
                $('#editConditions').val(conditions);
                $('#currentPicture').html(picture ? `<a href="/${picture}" target="_blank" class="btn btn-info btn-sm">View Current Image</a>` : 'No image uploaded');

                const modal = new bootstrap.Modal(document.getElementById('editAssetModal'));
                modal.show();
            });

            // Delete Asset Button Click
            $('#assetTable').on('click', '.delete-asset-btn', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');

                console.log('[SSCMS Asset Inventory] Delete asset:', { id, name });

                $('#deleteAssetId').val(id);
                $('#deleteAssetName').text(name);

                const modal = new bootstrap.Modal(document.getElementById('deleteAssetModal'));
                modal.show();
            });

            // Confirm Delete
            $('#confirmDeleteBtn').on('click', function() {
                const id = $('#deleteAssetId').val();
                const spinner = $('#deleteSpinner');
                const button = $(this);
                spinner.removeClass('d-none');
                button.prop('disabled', true);

                $.ajax({
                    url: 'asset_inventory.php',
                    type: 'POST',
                    data: { delete_asset: true, id: id },
                    dataType: 'json',
                    success: function(response) {
                        spinner.addClass('d-none');
                        button.prop('disabled', false);
                        if (response.success) {
                            assetTable.ajax.reload();
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
                            $('#deleteAssetModal').modal('hide');
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
                        console.error('[SSCMS Asset Inventory] Delete AJAX error:', xhr.status, error, thrown);
                        const toast = $(`
                            <div class="toast align-items-center text-bg-danger border-0" role="alert">
                                <div class="d-flex">
                                    <div class="toast-body">Error deleting asset. Check console.</div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                                </div>
                            </div>
                        `);
                        $('.toast-container').append(toast);
                        toast.toast({ delay: 3000 }).toast('show');
                    }
                });
            });

            // Form Validation
            $('#editAssetForm').on('submit', function(e) {
                const form = $(this);
                const spinner = form.find('.spinner-border');
                spinner.removeClass('d-none');
                form.find('[type=submit]').prop('disabled', true);
            });

            // Excel Export
            $('#downloadExcelBtn').on('click', function() {
                const data = assetTable.rows().data().toArray();
                if (data.length === 0) {
                    alert('No data to export.');
                    return;
                }

                const currentDate = new Date().toISOString().split('T')[0];

                // Prepare data
                const excelData = [
                    { '': 'ICCBI Clinic Assets Inventory', ' ': '', '  ': '', '   ': '' }, // Title row
                    { '': '', ' ': '', '  ': '', '   ': '' } // Empty row
                ];

                // Header row
                excelData.push({
                    '': 'Asset Name',
                    ' ': 'Quantity',
                    '  ': 'Condition',
                    '   ': 'Description'
                });

                // Data rows
                data.forEach(row => {
                    excelData.push({
                        '': row.name,
                        ' ': row.quantity,
                        '  ': row.conditions || '-',
                        '   ': row.description || '-'
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
                    { width: 12 },
                    { width: 15 },
                    { width: 30 }
                ];

                // Merge title row
                ws['!merges'] = [
                    { s: { r: 0, c: 0 }, e: { r: 0, c: 3 } }
                ];

                // Create workbook
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Assets Inventory');

                // Download
                XLSX.writeFile(wb, `ICCBI_Assets_Inventory_${currentDate}.xlsx`);
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>