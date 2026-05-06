<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Fetch all teachers
$stmt = $conn->query("SELECT * FROM teachers ORDER BY last_name, first_name");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="teachers_export_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');

    echo "Last Name\tFirst Name\tMiddle Name\tCategory\tGrade/Year\tProgram/Section\tContact Number\n";
    foreach ($teachers as $teacher) {
        echo htmlspecialchars($teacher['last_name']) . "\t" .
             htmlspecialchars($teacher['first_name']) . "\t" .
             htmlspecialchars($teacher['middle_name'] ?? '') . "\t" .
             htmlspecialchars($teacher['category']) . "\t" .
             htmlspecialchars($teacher['grade_year'] ?? '') . "\t" .
             htmlspecialchars($teacher['program_section'] ?? '') . "\t" .
             htmlspecialchars($teacher['contact_number']) . "\n";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - Iccb Smart Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f5f7fa;
            color: #333333;
            margin: 0;
            padding: 30px;
        }
        .container {
            max-width: 960px;
            margin: 40px auto;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            border-bottom: 2px solid #005566;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            color: #005566;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .table th {
            background-color: #005566;
            color: #ffffff;
            font-weight: 600;
        }
        .table tbody tr:hover {
            background-color: #f5f7fa;
        }
        .btn-export {
            background-color: #005566;
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 20px;
            transition: background-color 0.2s;
        }
        .btn-export:hover {
            background-color: #003d4a;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Immaculate Conception College Of Balayan, Inc.</h1>
            <h1>Smart Clinic Management System</h1>
            <h1>Manage Teachers</h1>
        </div>

        <!-- Error Toast -->
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

        <a href="?export=excel" class="btn-export"><i class="fas fa-file-excel"></i> Export to Excel</a>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Last Name</th>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Category</th>
                        <th>Grade/Year</th>
                        <th>Program/Section</th>
                        <th>Contact Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?= htmlspecialchars($teacher['last_name']) ?></td>
                            <td><?= htmlspecialchars($teacher['first_name']) ?></td>
                            <td><?= htmlspecialchars($teacher['middle_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($teacher['category']) ?></td>
                            <td><?= htmlspecialchars($teacher['grade_year'] ?? '') ?></td>
                            <td><?= htmlspecialchars($teacher['program_section'] ?? '') ?></td>
                            <td><?= htmlspecialchars($teacher['contact_number']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize error toasts
            $('.toast').toast({ delay: 5000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>