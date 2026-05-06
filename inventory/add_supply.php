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

// Handle form submission for adding or updating supply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_supply'])) {
    try {
        $conn->beginTransaction();

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $expiration_date = filter_input(INPUT_POST, 'expiration_date', FILTER_SANITIZE_STRING);
        $created_at = date('Y-m-d H:i:s');
        $picture = null;

        // Validate inputs
        if (!$name || $quantity === false || $quantity < 0) {
            throw new Exception('Invalid name or quantity.');
        }

        // Handle file upload
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $upload_dir = '../Uploads/supplies/';
            $file = $_FILES['picture'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error uploading file.');
            }
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
            }
            if ($file['size'] > $max_size) {
                throw new Exception('File size exceeds 5MB limit.');
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('supply_') . '.' . $ext;
            $destination = $upload_dir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new Exception('Failed to move uploaded file.');
            }

            $picture = 'Uploads/supplies/' . $filename;
        } elseif ($id) {
            // If editing and no new picture uploaded, retain existing picture
            $stmt = $conn->prepare("SELECT picture FROM supplies WHERE id = ?");
            $stmt->execute([$id]);
            $supply = $stmt->fetch(PDO::FETCH_ASSOC);
            $picture = $supply['picture'] ?? null;
        }

        // Nullable fields
        $expiration_date = $expiration_date ?: null;

        if ($id) {
            // Update existing supply
            $stmt = $conn->prepare("UPDATE supplies SET name = ?, quantity = ?, picture = ?, expiration_date = ? WHERE id = ?");
            $stmt->execute([$name, $quantity, $picture, $expiration_date, $id]);
            $_SESSION['success_message'] = 'Supply updated successfully!';
            error_log("[SSCMS Supply Inventory] Supply updated: id=$id, name=$name, quantity=$quantity");
        } else {
            // Add new supply
            $stmt = $conn->prepare("INSERT INTO supplies (name, quantity, picture, expiration_date, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $quantity, $picture, $expiration_date, $created_at]);
            $_SESSION['success_message'] = 'Supply added successfully!';
            error_log("[SSCMS Supply Inventory] Supply added: name=$name, quantity=$quantity");
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("[SSCMS Supply Inventory] Error: " . $e->getMessage());
    }

    header('Location: supplies_inventory.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ICCBI Clinic Management System - Add Supply">
    <meta name="author" content="ICCB">
    <title>Add Supply - ICCBI Clinic Supplies Inventory</title>
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

                <div class="card mb-3">
                    <div class="card-header bg-bscs-maroon text-black">
                        <div>
                            <i class="fas fa-plus me-1"></i>
                            Add New Supply
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <form id="addSupplyForm" action="add_supply.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="save_supply" value="1">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="name" class="form-label">Supply Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" id="name" required>
                                        <div class="form-text">Enter the name of the supply</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="quantity" id="quantity" min="0" required>
                                        <div class="form-text">Enter the quantity available</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="picture" class="form-label">Picture</label>
                                        <input type="file" class="form-control" name="picture" id="picture" accept="image/jpeg,image/png,image/gif">
                                        <div class="form-text">Upload an image (JPEG, PNG, GIF, max 5MB, optional)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="expiration_date" class="form-label">Expiration Date</label>
                                        <input type="date" class="form-control" name="expiration_date" id="expiration_date">
                                        <div class="form-text">Enter the expiration date (optional)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Supply
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
            console.log('[SSCMS Add Supply] Initialized');

            // Form Validation
            $('#addSupplyForm').on('submit', function(e) {
                const form = $(this);
                const spinner = form.find('.spinner-border');
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