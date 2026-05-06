<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        error_log("[SSCMS Medicine Request] Raw POST data: " . print_r($_POST, true));

        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_SPECIAL_CHARS);
        $batch_ids = $_POST['batch_id'] ?? [];
        $medicine_quantities = $_POST['medicine_quantity'] ?? [];
        $visit_date = date('Y-m-d');
        $visit_time = date('H:i:s');

        if (!$patient_id || !$reason || empty($batch_ids)) {
            error_log("[SSCMS Medicine Request] Validation failed: Missing required fields - patient_id=$patient_id, reason=$reason, batch_ids=" . (empty($batch_ids) ? 'none' : implode(',', $batch_ids)));
            throw new Exception('Patient, reason, and at least one batch are required.');
        }

        if (count($batch_ids) !== count($medicine_quantities)) {
            error_log("[SSCMS Medicine Request] Validation failed: Mismatch between batch_ids and quantities.");
            throw new Exception('Mismatch between batches and quantities.');
        }

        // Verify patient exists
        $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("[SSCMS Medicine Request] Validation failed: Invalid patient_id=$patient_id");
            throw new Exception('Invalid patient selected.');
        }

        // Process batches
        $medicine_stmt = $conn->prepare("INSERT INTO medicine_logs (medicine_id, patient_id, quantity_used, visit_date, reason, batch_id, batch_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($batch_ids as $index => $batch_id) {
            $quantity = filter_var($medicine_quantities[$index], FILTER_VALIDATE_INT);
            if (!$batch_id || $quantity <= 0) {
                error_log("[SSCMS Medicine Request] Validation failed: Invalid or missing batch_id=$batch_id, quantity=$quantity");
                throw new Exception('Batch and valid quantity are required.');
            }

            // Fetch batch details
            $stmt = $conn->prepare("SELECT mb.id, mb.medicine_id, mb.batch_number, mb.quantity, m.name FROM medicine_batches mb JOIN medicines m ON mb.medicine_id = m.id WHERE mb.id = ? AND mb.quantity > 0");
            $stmt->execute([$batch_id]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$batch) {
                error_log("[SSCMS Medicine Request] Validation failed: Batch not found or out of stock: batch_id=$batch_id");
                throw new Exception("Batch ID '$batch_id' not found or out of stock.");
            }
            if ($quantity > $batch['quantity']) {
                error_log("[SSCMS Medicine Request] Validation failed: Quantity exceeds stock. Requested=$quantity, Available={$batch['quantity']}");
                throw new Exception("Quantity for '{$batch['name']}' (Batch: {$batch['batch_number']}) exceeds available stock.");
            }

            // Insert into medicine_logs
            $medicine_stmt->execute([$batch['medicine_id'], $patient_id, $quantity, "$visit_date $visit_time", $reason, $batch_id, $batch['batch_number']]);
            error_log("[SSCMS Medicine Request] Medicine logged: medicine_id={$batch['medicine_id']}, batch_id=$batch_id, batch_number={$batch['batch_number']}, quantity_used=$quantity, patient_id=$patient_id, reason=$reason");

            // Update batch quantity
            $stmt = $conn->prepare("UPDATE medicine_batches SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $batch_id]);
            $affected_rows = $stmt->rowCount();
            if ($affected_rows === 0) {
                error_log("[SSCMS Medicine Request] Failed to update medicine_batches: No rows affected for batch_id=$batch_id");
                throw new Exception("Failed to update quantity for batch '{$batch['batch_number']}'.");
            }
            error_log("[SSCMS Medicine Request] Batch quantity updated: batch_id=$batch_id, quantity_used=$quantity");

            // Update medicine quantity
            $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $batch['medicine_id']]);
            $affected_rows = $stmt->rowCount();
            if ($affected_rows === 0) {
                error_log("[SSCMS Medicine Request] Failed to update medicines: No rows affected for medicine_id={$batch['medicine_id']}");
                throw new Exception("Failed to update quantity for medicine '{$batch['name']}'.");
            }
            error_log("[SSCMS Medicine Request] Medicine quantity updated: medicine_id={$batch['medicine_id']}, quantity_used=$quantity");
        }

        $conn->commit();
        $_SESSION['success_message'] = 'Medicine request logged successfully!';
        error_log("[SSCMS Medicine Request] Success: patient_id=$patient_id, reason=$reason, batches=" . (empty($batch_ids) ? 'none' : implode(', ', $batch_ids)));
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
        error_log("[SSCMS Medicine Request] Error: " . $e->getMessage() . " | Line: " . $e->getLine());
    }
    header('Location: request_medicine.php');
    exit;
}

$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$showTable = isset($_GET['search']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Request Medicine">
    <meta name="author" content="ICCB">
    <title>Request Medicine - Clinic Management</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        .no-patients {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
            background: #fafafa;
            border-radius: 8px;
        }
        .no-patients i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }
        .patient-info-card {
            background: linear-gradient(45deg, rgba(2,132,199,0.1) 0%, rgba(255,255,255,0.5) 100%);
            border-left: 4px solid var(--primary);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        .table-container {
            display: none;
        }
        .table-container.show {
            display: block;
        }
        .medicine-row {
            margin-bottom: 1rem;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: 5px;
            position: relative;
        }
        .remove-medicine-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }
        @media (max-width: 992px) {
            .content {
                margin-left: var(--sidebar-collapsed-width);
            }
        }
        @media (max-width: 767px) {
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
            .medicine-row {
                padding: 0.5rem;
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
                        <h5 class="card-title"><i class="fas fa-prescription-bottle-alt me-1"></i> Request Medicine</h5>
                    </div>
                    <div class="card-body p-3">
                        <form id="searchForm" method="GET" action="request_medicine.php">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="filter-group">
                                        <label for="searchPatient" class="form-label">Search Patients</label>
                                        <input type="text" id="searchPatient" name="search" class="form-control" placeholder="Search by name (last, first, or middle)" value="<?= htmlspecialchars($searchTerm) ?>">
                                        <div class="form-text">Enter name to search for patients</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end">
                                    <div class="filter-group">
                                        <button type="submit" id="searchBtn" class="btn btn-primary me-2">
                                            <i class="fas fa-search me-1"></i>Search
                                            <span id="searchSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                        </button>
                                        <button type="button" id="clearSearchBtn" class="btn btn-secondary">
                                            <i class="fas fa-undo me-1"></i>Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive table-container <?= $showTable ? 'show' : '' ?>">
                            <table id="patientTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Name</th>
                                        <th style="width: 15%;">Gender</th>
                                        <th style="width: 15%;">Grade/Year</th>
                                        <th style="width: 20%;">Program/Section</th>
                                        <th style="width: 15%;">Guardian Contact</th>
                                        <th style="width: 10%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="patientTableBody">
                                    <?php if ($showTable): ?>
                                        <?php
                                        $query = "SELECT DISTINCT p.id, p.first_name, p.middle_name, p.last_name, p.gender, p.grade_year, p.program_section, p.guardian_contact 
                                                  FROM patients p WHERE 1=1";
                                        $params = [];
                                        if ($searchTerm) {
                                            $query .= " AND (p.last_name LIKE ? OR p.first_name LIKE ? OR p.middle_name LIKE ?)";
                                            $params = ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%"];
                                        }
                                        $query .= " ORDER BY p.last_name, p.first_name, p.middle_name";
                                        $stmt = $conn->prepare($query);
                                        $stmt->execute($params);
                                        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        <?php if (empty($patients)): ?>
                                            <tr>
                                                <td colspan="6">
                                                    <div class="no-patients">
                                                        <i class="fas fa-users-slash"></i>
                                                        <p>No patients found</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($patients as $patient): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] : '')) ?></td>
                                                    <td><?= htmlspecialchars($patient['gender']) ?></td>
                                                    <td><?= htmlspecialchars($patient['grade_year'] ?: 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($patient['program_section'] ?: 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($patient['guardian_contact'] ?: 'N/A') ?></td>
                                                    <td>
                                                        <button class="btn btn-primary btn-sm log-patient-btn" data-patient='<?= json_encode($patient) ?>'>
                                                            Request Medicine
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>

            <div class="modal fade" id="logPatientModal" tabindex="-1" aria-labelledby="logPatientModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-semibold" id="studentName">Request Medicine</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="patientInfo" class="patient-info-card mb-4 d-none"></div>
                            <form id="requestMedicineForm" method="POST" action="request_medicine.php">
                                <input type="hidden" name="patient_id" id="patient_id">

                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3">Medicine Request Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="reason" class="form-label">Reason for Request <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="reason" id="reason" required placeholder="Enter reason for medicine request">
                                                <small class="form-text text-muted">Specify the reason for requesting medicine</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3">Medicine Information</h6>
                                    <div id="medicineRows">
                                        <div class="medicine-row">
                                            <button type="button" class="btn btn-danger btn-sm remove-medicine-btn" onclick="removeMedicineRow(this)" style="display: none;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="batch_0" class="form-label">Select Batch <span class="text-danger">*</span></label>
                                                        <select name="batch_id[]" id="batch_0" class="form-select batch-select" onchange="updateBatchQuantity(this)" required>
                                                            <option value="">-- Select Batch --</option>
                                                            <?php
                                                            $batches = $conn->query("SELECT mb.id, mb.batch_number, mb.quantity, m.name FROM medicine_batches mb JOIN medicines m ON mb.medicine_id = m.id WHERE mb.quantity > 0 ORDER BY m.name, mb.batch_number");
                                                            if ($batches->rowCount() == 0) {
                                                                echo '<option value="" disabled>No batches available</option>';
                                                            } else {
                                                                while ($batch = $batches->fetch(PDO::FETCH_ASSOC)) {
                                                                    echo '<option value="' . $batch['id'] . '" data-quantity="' . $batch['quantity'] . '">' . htmlspecialchars($batch['name']) . ' (Batch: ' . $batch['batch_number'] . ', Qty: ' . $batch['quantity'] . ')</option>';
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                        <div class="invalid-feedback">Please select a batch.</div>
                                                        <small class="form-text text-muted">Select the medicine batch requested</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="medicine_quantity_0" class="form-label">Quantity Requested <span class="text-danger">*</span></label>
                                                        <input type="number" class="form-control medicine-quantity" name="medicine_quantity[]" id="medicine_quantity_0" min="1" placeholder="Enter quantity" onchange="validateQuantity(this)" required>
                                                        <div class="quantity-warning text-danger small mt-1" style="display: none;"></div>
                                                        <div class="invalid-feedback">Please enter a valid quantity.</div>
                                                        <small class="form-text text-muted">Enter the quantity of medicine requested</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="addMedicineBtn">
                                        <i class="fas fa-plus me-1"></i> Add Another Batch
                                    </button>
                                </div>

                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3">Request Date & Time</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="visit_time" class="form-label">Request Time</label>
                                                <input type="text" class="form-control" value="<?= date('h:i A') ?>" readonly>
                                                <small class="form-text text-muted">Current request time (Asia/Manila)</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="visit_date" class="form-label">Request Date</label>
                                                <input type="text" class="form-control" value="<?= date('l, F j, Y') ?>" readonly>
                                                <input type="hidden" name="visit_date" value="<?= date('Y-m-d') ?>">
                                                <small class="form-text text-muted">Current request date (Asia/Manila)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary" id="submitRequest">
                                        <i class="fas fa-save me-1"></i> Log Request
                                        <span id="logSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                    </button>
                                </div>
                            </form>
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
            console.log('[SSCMS Medicine Request] Initialized');

            // Initialize DataTable only if table is shown and has data
            <?php if ($showTable && !empty($patients)): ?>
            const patientTable = $('#patientTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'asc']],
                language: {
                    search: '',
                    searchPlaceholder: '',
                    processing: '<i class="fas fa-spinner fa-spin"></i> Loading...',
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                },
                columnDefs: [
                    { orderable: false, targets: 5 }
                ]
            });
            <?php endif; ?>

            // Search functionality
            const searchPatient = $('#searchPatient');
            const clearSearchBtn = $('#clearSearchBtn');

            // Clear search
            clearSearchBtn.on('click', function() {
                console.log('[SSCMS Medicine Request] Clear search button clicked');
                searchPatient.val('');
                $('#searchForm').submit();
            });

            // Handle Log Patient button clicks
            $(document).on('click', '.log-patient-btn', function() {
                const patient = $(this).data('patient');
                openPatientModal(patient);
            });

            function openPatientModal(patient) {
                try {
                    console.log('[SSCMS Medicine Request] Opening modal for patient:', patient);
                    $('#patient_id').val(patient.id);
                    $('#studentName').text(`Request Medicine for ${patient.first_name} ${patient.last_name}`);
                    $('#patientInfo').html(`
                        <div class="d-flex flex-column">
                            <span><strong>Name:</strong> ${patient.last_name}, ${patient.first_name} ${patient.middle_name || ''}</span>
                            <span><strong>Grade/Year:</strong> ${patient.grade_year || 'N/A'}</span>
                            <span><strong>Program/Section:</strong> ${patient.program_section || 'N/A'}</span>
                            <span><strong>Guardian Contact:</strong> ${patient.guardian_contact || 'N/A'}</span>
                        </div>
                    `).removeClass('d-none');
                    $('#requestMedicineForm')[0].reset();
                    $('#medicineRows').html(getMedicineRow(0));
                    $('.remove-medicine-btn').hide();
                    const modal = new bootstrap.Modal(document.getElementById('logPatientModal'));
                    modal.show();
                } catch (err) {
                    console.error('[SSCMS Medicine Request] Failed to open modal:', err);
                    alert('Failed to open modal. Please try again.');
                }
            }

            // Add medicine row
            let medicineRowCount = 1;
            $('#addMedicineBtn').on('click', function() {
                console.log('[SSCMS Medicine Request] Adding medicine row');
                $('#medicineRows').append(getMedicineRow(medicineRowCount));
                if (medicineRowCount > 0) {
                    $('.remove-medicine-btn').show();
                }
                medicineRowCount++;
            });

            function getMedicineRow(index) {
                return `
                    <div class="medicine-row">
                        <button type="button" class="btn btn-danger btn-sm remove-medicine-btn" onclick="removeMedicineRow(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="batch_${index}" class="form-label">Select Batch <span class="text-danger">*</span></label>
                                    <select name="batch_id[]" id="batch_${index}" class="form-select batch-select" onchange="updateBatchQuantity(this)" required>
                                        <option value="">-- Select Batch --</option>
                                        <?php
                                        $batches = $conn->query("SELECT mb.id, mb.batch_number, mb.quantity, m.name FROM medicine_batches mb JOIN medicines m ON mb.medicine_id = m.id WHERE mb.quantity > 0 ORDER BY m.name, mb.batch_number");
                                        if ($batches->rowCount() == 0) {
                                            echo '<option value="" disabled>No batches available</option>';
                                        } else {
                                            while ($batch = $batches->fetch(PDO::FETCH_ASSOC)) {
                                                echo '<option value="' . $batch['id'] . '" data-quantity="' . $batch['quantity'] . '">' . htmlspecialchars($batch['name']) . ' (Batch: ' . $batch['batch_number'] . ', Qty: ' . $batch['quantity'] . ')</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a batch.</div>
                                    <small class="form-text text-muted">Select the medicine batch requested</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="medicine_quantity_${index}" class="form-label">Quantity Requested <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control medicine-quantity" name="medicine_quantity[]" id="medicine_quantity_${index}" min="1" placeholder="Enter quantity" onchange="validateQuantity(this)" required>
                                    <div class="quantity-warning text-danger small mt-1" style="display: none;"></div>
                                    <div class="invalid-feedback">Please enter a valid quantity.</div>
                                    <small class="form-text text-muted">Enter the quantity of medicine requested</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Form validation
            $('#requestMedicineForm').on('submit', function(e) {
                console.log('[SSCMS Medicine Request] Form submitting');
                const batchIds = $('select[name="batch_id[]"]').map(function() { return $(this).val(); }).get();
                const quantities = $('input[name="medicine_quantity[]"]').map(function() { return parseInt($(this).val()) || 0; }).get();

                if (!batchIds.some(id => id)) {
                    e.preventDefault();
                    alert('Please select at least one batch.');
                    $('.batch-select').first().addClass('is-invalid').focus();
                    return;
                }
                $('.batch-select').removeClass('is-invalid');

                for (let i = 0; i < batchIds.length; i++) {
                    if (batchIds[i] && (!quantities[i] || quantities[i] <= 0)) {
                        e.preventDefault();
                        alert('Please enter a valid quantity greater than 0 for all selected batches.');
                        $(`#medicine_quantity_${i}`).addClass('is-invalid').focus();
                        return;
                    }
                    if (batchIds[i]) {
                        const availableQuantity = parseInt($(`#batch_${i} option:selected`).data('quantity')) || 0;
                        if (quantities[i] > availableQuantity) {
                            e.preventDefault();
                            alert(`Quantity for selected batch exceeds available stock in inventory!`);
                            $(`#medicine_quantity_${i}`).addClass('is-invalid').focus();
                            $(`#medicine_quantity_${i}`).next('.quantity-warning').text(`Quantity exceeds available stock (${availableQuantity}).`).show();
                            return;
                        }
                        $(`#medicine_quantity_${i}`).next('.quantity-warning').hide();
                    }
                }

                $('#logSpinner').removeClass('d-none');
                $('#submitRequest').addClass('disabled');
            });

            // Initialize toasts
            $('.toast').toast({ delay: 5000 });
            $('.toast').toast('show');

            function updateBatchQuantity(select) {
                console.log('[SSCMS Medicine Request] Updating batch quantity');
                const quantityInput = $(select).closest('.medicine-row').find('.medicine-quantity');
                const quantityWarning = $(select).closest('.medicine-row').find('.quantity-warning');

                const availableQuantity = parseInt($(select).find('option:selected').data('quantity')) || 0;
                if ($(select).val()) {
                    quantityInput.attr('max', availableQuantity);
                    quantityInput.val(availableQuantity > 0 ? 1 : 0);
                    quantityInput.removeClass('is-invalid');
                    validateQuantity(quantityInput[0]);
                } else {
                    quantityInput.val('');
                    quantityWarning.hide().text('');
                }
            }

            function validateQuantity(input) {
                console.log('[SSCMS Medicine Request] Validating quantity');
                const select = $(input).closest('.medicine-row').find('.batch-select');
                const quantityWarning = $(input).closest('.medicine-row').find('.quantity-warning');

                const availableQuantity = parseInt(select.find('option:selected').data('quantity')) || 0;
                const enteredQuantity = parseInt($(input).val()) || 0;

                if (enteredQuantity > availableQuantity || enteredQuantity <= 0) {
                    quantityWarning.text(`Quantity exceeds available stock (${availableQuantity}).`).show();
                    $(input).val(availableQuantity > 0 ? availableQuantity : '');
                    $(input).removeClass('is-invalid');
                } else {
                    quantityWarning.hide().text('');
                    $(input).removeClass('is-invalid');
                }
            }

            window.removeMedicineRow = function(button) {
                console.log('[SSCMS Medicine Request] Removing medicine row');
                $(button).closest('.medicine-row').remove();
                medicineRowCount--;
                if (medicineRowCount <= 1) {
                    $('.remove-medicine-btn').hide();
                }
            };
        });
    </script>
</body>
</html>