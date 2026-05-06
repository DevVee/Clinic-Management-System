<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check user session (uncomment for production)
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../login.php');
//     exit;
// }

// Validate patient ID
$patient_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$patient_id) {
    $_SESSION['error_message'] = 'Invalid patient ID.';
    header('Location: pendings.php');
    exit;
}

// Fetch patient details
try {
    $stmt = $conn->prepare("SELECT * FROM pending_patients WHERE id = :id");
    $stmt->execute(['id' => $patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $_SESSION['error_message'] = 'Patient not found.';
        header('Location: pendings.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error fetching patient: ' . $e->getMessage();
    header('Location: pendings.php');
    exit;
}

// Handle actions (approve, reject, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);

    try {
        if ($action === 'approve') {
            $insertStmt = $conn->prepare("
                INSERT INTO patients (
                    last_name, first_name, middle_name, gender, address, category, grade_year, program_section,
                    guardian_name, guardian_contact, other_contact, guardian_facebook, emergency_contact_name,
                    emergency_contact_number, pediatrician_name, pediatrician_contact, allergies, medical_conditions, notes
                ) VALUES (
                    :last_name, :first_name, :middle_name, :gender, :address, :category, :grade_year, :program_section,
                    :guardian_name, :guardian_contact, :other_contact, :guardian_facebook, :emergency_contact_name,
                    :emergency_contact_number, :pediatrician_name, :pediatrician_contact, :allergies, :medical_conditions, :notes
                )
            ");
            $insertStmt->execute([
                'last_name' => $patient['last_name'],
                'first_name' => $patient['first_name'],
                'middle_name' => $patient['middle_name'] ?? null,
                'gender' => $patient['gender'],
                'address' => $patient['address'],
                'category' => $patient['category'],
                'grade_year' => $patient['grade_year'] ?? null,
                'program_section' => $patient['program_section'] ?? null,
                'guardian_name' => $patient['guardian_name'] ?? null,
                'guardian_contact' => $patient['guardian_contact'] ?? null,
                'other_contact' => $patient['other_contact'] ?? null,
                'guardian_facebook' => $patient['guardian_facebook'] ?? null,
                'emergency_contact_name' => $patient['emergency_contact_name'] ?? null,
                'emergency_contact_number' => $patient['emergency_contact_number'] ?? null,
                'pediatrician_name' => $patient['pediatrician_name'] ?? null,
                'pediatrician_contact' => $patient['pediatrician_contact'] ?? null,
                'allergies' => $patient['allergies'] ?? null,
                'medical_conditions' => $patient['medical_conditions'] ?? null,
                'notes' => $patient['notes'] ?? null
            ]);

            $conn->prepare("DELETE FROM pending_patients WHERE id = :id")->execute(['id' => $patient_id]);
            $_SESSION['success_message'] = 'Patient approved successfully.';
        } elseif ($action === 'reject') {
            $conn->prepare("UPDATE pending_patients SET status = 'rejected' WHERE id = :id")->execute(['id' => $patient_id]);
            $_SESSION['success_message'] = 'Patient rejected successfully.';
        } elseif ($action === 'delete') {
            $conn->prepare("DELETE FROM pending_patients WHERE id = :id")->execute(['id' => $patient_id]);
            $_SESSION['success_message'] = 'Patient deleted successfully.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
    header('Location: pendings.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - View Pending Patient">
    <meta name="author" content="ICCB">
    <title>View Pending Patient - ICCB Smart Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>
    <style>
        :root { --sidebar-width: 220px; --sidebar-collapsed-width: 50px; }
        body { font-family: 'Inter', sans-serif; font-size: 0.875rem; background-color: #f8fafc; color: #1e293b; }
        .content {
            margin-left: var(--sidebar-width);
            padding: 1rem;
            min-height: 100vh;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #0f73ba 0%, #2c7be5 100%);
            color: white;
            padding: 0.85rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 0.85rem;
            box-shadow: 0 4px 12px rgba(15,115,186,0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dashboard-title { font-size: 1.15rem; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 0.5rem; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 0.85rem; align-items: center; }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: box-shadow 0.3s ease;
        }
        .card:hover { box-shadow: 0 8px 20px rgba(15,115,186,0.1); }
        .card-body { padding: 1.25rem; }
        .section-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #0f73ba;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.35rem;
            margin-bottom: 0.75rem;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.45rem 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 0.78rem;
        }
        .detail-label { font-weight: 500; color: #475569; }
        .detail-value { color: #334155; }
        .btn-sm { font-size: 0.75rem; padding: 0.3rem 0.65rem; border-radius: 6px; }
        .btn-primary { background-color: #0f73ba; border-color: #0f73ba; transition: all 0.2s ease; }
        .btn-primary:hover { background-color: #0d5a94; border-color: #0d5a94; transform: translateY(-1px); }
        .btn-success { background-color: #059669; border-color: #059669; transition: all 0.2s ease; }
        .btn-success:hover { background-color: #047857; border-color: #047857; transform: translateY(-1px); }
        .btn-danger { background-color: #dc2626; border-color: #dc2626; transition: all 0.2s ease; }
        .btn-danger:hover { background-color: #b91c1c; border-color: #b91c1c; transform: translateY(-1px); }
        .btn-warning { background-color: #d97706; border-color: #d97706; color: white; transition: all 0.2s ease; }
        .btn-warning:hover { background-color: #b35e05; border-color: #b35e05; color: white; transform: translateY(-1px); }
        .btn-outline-primary { border-color: #0f73ba; color: #0f73ba; transition: all 0.2s ease; }
        .btn-outline-primary:hover { background-color: #0f73ba; color: white; }
        .toast-container { z-index: 1050; }
        .modal-content { border: none; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .modal-header {
            background: linear-gradient(135deg, #0f73ba, #2c7be5);
            color: white;
            font-size: 0.875rem;
            border-radius: 10px 10px 0 0;
            border-bottom: none;
            padding: 0.85rem 1.25rem;
        }
        .modal-header .btn-close { filter: brightness(0) invert(1); opacity: 0.85; }
        .modal-body { padding: 1.25rem; }
        .modal-footer { border-top: 1px solid #e2e8f0; padding: 0.75rem 1.25rem; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in      { animation: fadeInUp 0.4s ease both; }
        .anim-delay-1 { animation: fadeInUp 0.4s ease 0.1s both; }
        .anim-delay-2 { animation: fadeInUp 0.4s ease 0.2s both; }
        @media (max-width: 992px) { .content { margin-left: 50px; } }
        @media (max-width: 768px) {
            .content { margin-left: 0; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .detail-row { flex-direction: column; gap: 0.25rem; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Dashboard Header -->
                <div class="dashboard-header fade-in">
                    <h1 class="dashboard-title">
                        <i class="fas fa-user-clock"></i>
                        View Pending Patient
                    </h1>
                    <a href="pendings.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>

                <!-- Toast Container -->
                <div class="toast-container position-fixed top-0 end-0 p-2">
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

                <!-- Toolbar -->
                <div class="toolbar anim-delay-1">
                    <a href="pendings.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Pending Patients
                    </a>
                    <div class="ms-auto d-flex gap-2">
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#confirmModal" data-action="approve" title="Approve patient">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#confirmModal" data-action="reject" title="Reject patient">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirmModal" data-action="delete" title="Delete patient">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <!-- Patient Details -->
                <div class="card anim-delay-2">
                    <div class="card-body">
                        <!-- Personal Information -->
                        <h6 class="section-title">Personal Information</h6>
                        <div class="detail-row">
                            <span class="detail-label">Name</span>
                            <span class="detail-value">
                                <?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'][0] . '.' : '')) ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Gender</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['gender'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Address</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['address'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status</span>
                            <span class="detail-value <?= $patient['status'] === 'rejected' ? 'text-danger' : 'text-warning' ?>">
                                <?= htmlspecialchars(ucfirst($patient['status'] ?? 'pending')) ?>
                            </span>
                        </div>

                        <!-- Academic Information -->
                        <h6 class="section-title mt-3">Academic Information</h6>
                        <div class="detail-row">
                            <span class="detail-label">Category</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['category'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Grade/Year</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['grade_year'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Program/Section</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['program_section'] ?? '-') ?></span>
                        </div>

                        <!-- Guardian & Contact Information -->
                        <h6 class="section-title mt-3">Guardian & Contact Information</h6>
                        <div class="detail-row">
                            <span class="detail-label">Guardian Name</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['guardian_name'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Guardian Contact</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['guardian_contact'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Other Contact</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['other_contact'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Guardian Facebook</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['guardian_facebook'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Emergency Contact Name</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['emergency_contact_name'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Emergency Contact Number</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['emergency_contact_number'] ?? '-') ?></span>
                        </div>

                        <!-- Medical Information -->
                        <h6 class="section-title mt-3">Medical Information</h6>
                        <div class="detail-row">
                            <span class="detail-label">Pediatrician Name</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['pediatrician_name'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Pediatrician Contact</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['pediatrician_contact'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Allergies</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['allergies'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Medical Conditions</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['medical_conditions'] ?? '-') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Notes</span>
                            <span class="detail-value"><?= htmlspecialchars($patient['notes'] ?? '-') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Modal -->
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title fw-medium small" id="confirmModalLabel"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body small" id="confirmModalBody"></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <form id="actionForm" method="post">
                                    <input type="hidden" name="action" id="actionInput">
                                    <button type="submit" class="btn btn-primary btn-sm" id="confirmActionBtn">
                                        Confirm
                                        <span id="actionSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle action buttons
            $('[data-action]').on('click', function() {
                const action = $(this).data('action');
                let title, message;

                if (action === 'approve') {
                    title = 'Approve Patient';
                    message = 'Are you sure you want to approve this patient? This will move them to the main patient list.';
                } else if (action === 'reject') {
                    title = 'Reject Patient';
                    message = 'Are you sure you want to reject this patient?';
                } else if (action === 'delete') {
                    title = 'Delete Patient';
                    message = 'Are you sure you want to delete this patient? This action cannot be undone.';
                }

                $('#confirmModalLabel').text(title);
                $('#confirmModalBody').text(message);
                $('#actionInput').val(action);
            });

            // Handle form submission
            $('#actionForm').on('submit', function(e) {
                e.preventDefault();
                const $submitButton = $('#confirmActionBtn');
                const $spinner = $('#actionSpinner');

                $submitButton.prop('disabled', true);
                $spinner.removeClass('d-none');

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = 'pendings.php';
                        } else {
                            $submitButton.prop('disabled', false);
                            $spinner.addClass('d-none');
                            alert('Error: ' + (response.message || 'Action failed.'));
                        }
                    },
                    error: function(xhr) {
                        $submitButton.prop('disabled', false);
                        $spinner.addClass('d-none');
                        alert('Error: ' + (xhr.responseJSON?.message || 'An error occurred'));
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