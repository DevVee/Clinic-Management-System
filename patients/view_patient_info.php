<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check user session (uncomment for production)
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../login.php');
//     exit;
// }

// Get patient ID from URL
$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING) ?? '';

// Fetch patient data
$patient = null;
if ($patient_id) {
    try {
        $stmt = $conn->prepare("
            SELECT id, last_name, first_name, middle_name, gender, category, grade_year, program_section,
                   guardian_contact, guardian_name, guardian_facebook, pediatrician_name, pediatrician_contact,
                   allergies, medical_conditions, notes, other_contact, address, emergency_contact_name, emergency_contact_number
            FROM patients
            WHERE id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
        header('Location: manage-patients.php' . ($category ? '?category=' . $category : ''));
        exit;
    }
}

// Redirect if patient not found
if (!$patient) {
    $_SESSION['error_message'] = 'Patient not found';
    header('Location: manage-patients.php' . ($category ? '?category=' . $category : ''));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - View Patient Info">
    <meta name="author" content="ICCB">
    <title>View <?= htmlspecialchars($patient['first_name']) ?> - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>
    <link rel="stylesheet" href="../css/manage-patients.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            font-size: 0.78rem;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .content {
        }
        .report-card {
            max-width: 900px;
            margin: 1.5rem auto;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(15,115,186,0.10), 0 2px 6px rgba(0,0,0,0.05);
            background: #ffffff;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: box-shadow 0.3s ease;
        }
        .report-card:hover {
            box-shadow: 0 12px 32px rgba(15,115,186,0.14), 0 4px 10px rgba(0,0,0,0.06);
        }
        .report-card-header {
            background: linear-gradient(135deg, #0f73ba, #2c7be5);
            color: #ffffff;
            padding: 1.5rem;
            text-align: center;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .report-card-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0.5rem 0 0;
        }
        .avatar {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.7);
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #ffffff;
            font-weight: 600;
        }
        .report-card-body {
            padding: 2rem;
        }
        .info-container {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .info-column {
            flex: 1;
            min-width: 300px;
        }
        .info-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-section:last-child {
            border-bottom: none;
        }
        .info-section h5 {
            font-size: 0.78rem;
            font-weight: 600;
            color: #0f73ba;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.4rem;
        }
        .info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.6rem;
            line-height: 1.5;
        }
        .info-label {
            font-weight: 600;
            color: #475569;
            width: 140px;
            flex-shrink: 0;
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 0.03em;
        }
        .info-value {
            color: #1e293b;
            flex: 1;
            word-break: break-word;
        }
        .no-info {
            color: #6b7280;
            font-style: italic;
        }
        .btn-primary {
            background-color: #0f73ba;
            border-color: #0f73ba;
            font-size: 0.7rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }
        .btn-primary:hover {
            background-color: #0d5a94;
            border-color: #0d5a94;
        }
        .btn-outline-secondary {
            border-color: #6b7280;
            color: #6b7280;
            font-size: 0.7rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }
        .btn-outline-secondary:hover {
            background-color: #6b7280;
            color: #ffffff;
        }
        .tooltip-icon {
            color: #6b7280;
            margin-left: 0.25rem;
            cursor: help;
            font-size: 0.6rem;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in      { animation: fadeInUp 0.45s ease both; }
        .anim-delay-1 { animation: fadeInUp 0.45s ease 0.10s both; }
        .anim-delay-2 { animation: fadeInUp 0.45s ease 0.20s both; }
        @media (max-width: 992px) {
            .content { margin-left: 50px; }
        }
        @media (max-width: 768px) {
            .content { margin-left: 0; }
            .report-card {
                margin: 1rem;
            }
            .info-container {
                flex-direction: column;
                gap: 1rem;
            }
            .info-column {
                min-width: auto;
            }
            .info-item {
                flex-direction: column;
                gap: 0.25rem;
            }
            .info-label {
                width: auto;
            }
            .report-card-header h2 {
                font-size: 1.2rem;
            }
            .avatar {
                width: 50px;
                height: 50px;
                font-size: 1rem;
            }
            .report-card-body {
                padding: 1.5rem;
            }
        }
        .dark-mode {
            background: #1f2a44;
        }
        .dark-mode .report-card {
            background: #374151;
        }
        .dark-mode .report-card-header {
            background: linear-gradient(135deg, #0d5a94, #2c7be5);
        }
        .dark-mode .info-section {
            border-bottom-color: #4b5568;
        }
        .dark-mode .info-section h5 {
            color: #60b3e8;
            border-bottom-color: #4b5568;
        }
        .dark-mode .info-label {
            color: #d1d5db;
        }
        .dark-mode .info-value {
            color: #f3f6ff;
        }
        .dark-mode .no-info {
            color: #9ca3af;
        }
        .dark-mode .btn-primary {
            background-color: #2c7be5;
            border-color: #2c7be5;
        }
        .dark-mode .btn-primary:hover {
            background-color: #0f73ba;
            border-color: #0f73ba;
        }
        .dark-mode .btn-outline-secondary {
            color: #ffffff;
            border-color: #333;
        }
        .dark-mode .btn-outline-secondary:hover {
            color: #000000;
            background-color: #ffffff;
            border-color: #333333;
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container-fluid">
                <!-- Toast Container -->
                <div class="toast-container position-fixed top-0 end-0 p-3">
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
                                <button type="button" class="btn-close btn-close btn-white me-2 me-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                </div>

                <!-- Report Card -->
                <div class="report-card fade-in">
                    <div class="report-card-header">
                        <div class="avatar"><?= strtoupper(substr($patient['first_name'], 0, 1)) . strtoupper(substr($patient['last_name'], 0, 1)) ?></div>
                        <h2 class="title"><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ($patient['middle_name'] ? ' ' . $patient['middle_name'] : '')) ?></h2>
                    </div>
                    <div class="report-card-body">
                        <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
                            <a href="edit_patient.php?id=<?= htmlspecialchars($patient['id']) ?>&category=<?= htmlspecialchars($category) ?>" class="btn btn-primary">Edit</a>
                            <a href="javascript:history.back();" class="btn btn-outline-secondary">← Back</a>
                        </div>

                        <!-- Info Columns -->
                        <div class="info-container">
                            <!-- Left Column -->
                            <div class="info-column anim-delay-1">
                                <!-- Personal Info -->
                                <div class="info-section">
                                    <h5>Personal Information</h5>
                                    <div class="info-item">
                                        <span class="info-label">Full Name</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['last_name'] . ' ' . $patient['first_name'] . ($patient['middle_name'] ? ' ' . $patient['middle_name'] : '')) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Gender</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'No info yet') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Home Address</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['address'] ?? 'No info yet') ?></span>
                                    </div>
                                </div>

                                <!-- Academic Info -->
                                <div class="info-section">
                                    <h5>Academic Information</h5>
                                    <div class="info-item">
                                        <span class="info-label">Level</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['category'] ?? 'No info yet') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Grade/Year</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['grade_year'] ?? 'No info yet') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Class/Group</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['program_section'] ?? 'No info yet') ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="info-column anim-delay-2">
                                <!-- Contact Info -->
                                <div class="info-section">
                                    <h5>Contact Information</h5>
                                    <div class="info-item">
                                        <span class="info-label">Guardian Name</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['guardian_name'] ?? 'No info yet') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Guardian Phone</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['guardian_contact'] ?? 'No info yet') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Other Phone</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['other_contact'] ?? 'No info yet') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Guardian Facebook
                                            <span class="tooltip-icon" data-bs-toggle="tooltip" title="Facebook link or username for guardian"><i class="fas fa-info-circle"></i></span>
                                        </span>
                                        <span class="info-value"><?= htmlspecialchars($patient['guardian_facebook'] ?? 'No info yet') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Emergency Contact</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['emergency_contact_name'] ?? 'No info yet') . ($patient['emergency_contact_number'] ? ' (' . htmlspecialchars($patient['emergency_contact_number']) . ')' : '') ?></span>
                                    </div>
                                </div>

                                <!-- Medical Info -->
                                <div class="info-section">
                                    <h5>Medical Information</h5>
                                    <div class="info-item">
                                        <span class="info-label">Doctor</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['pediatrician_name'] ?? 'No info yet') . ($patient['pediatrician_contact'] ? ' ' . htmlspecialchars($patient['pediatrician_contact']) . ')' : '') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Allergies</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['allergies'] ?? 'No info yet') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Health Conditions</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['medical_conditions'] ?? 'No info yet') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Notes</span>
                                        <span class="info-value"><?= htmlspecialchars($patient['notes'] ?? 'No info yet') ?></span>
                                    </div>
                                </div>
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
            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');

            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();

            // Dark mode toggle
            $('#darkModeToggle').on('click', function() {
                $('body').toggleClass('dark-mode');
                const isDark = $('body').hasClass('dark-mode');
                $(this).html(isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>');
                localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
            });

            // Initialize dark mode
            if (localStorage.getItem('darkMode') === 'enabled') {
                $('body').addClass('dark-mode');
                $('#darkModeToggle').html('<i class="fas fa-sun"></i>');
            }
        });
    </script>
</body>
</html>