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

// Handle AJAX request for fetching audit logs
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    try {
        $medicine_id = isset($_GET['medicine_id']) && is_numeric($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : null;
        $year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : null;
        $month = isset($_GET['month']) && is_numeric($_GET['month']) ? (int)$_GET['month'] : null;
        $date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : null;

        // Fetch logs with batch number and expiration date
        $query = "SELECT sal.medicine_id, sal.medicine_name, sal.batch_number, sal.quantity_added, sal.old_quantity, 
                         sal.new_quantity, sal.cost, sal.expiration_date, sal.user_id, sal.created_at, 
                         COALESCE(u.name, '-') AS user_name
                  FROM stock_audit_logs sal
                  LEFT JOIN users u ON sal.user_id = u.id";
        $params = [];
        $conditions = [];

        if ($medicine_id !== null) {
            $conditions[] = "sal.medicine_id = ?";
            $params[] = $medicine_id;
        }

        if ($year !== null) {
            $conditions[] = "YEAR(sal.created_at) = ?";
            $params[] = $year;
        }

        if ($month !== null) {
            $conditions[] = "MONTH(sal.created_at) = ?";
            $params[] = $month;
        }

        if ($date !== null) {
            $conditions[] = "DATE(sal.created_at) = ?";
            $params[] = $date;
        }

        if ($conditions) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $query .= " ORDER BY sal.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total cost
        $total_cost = 0;
        foreach ($logs as $log) {
            $total_cost += $log['cost'];
        }

        // Fetch medicines for dropdown
        $medicine_query = "SELECT id, name FROM medicines ORDER BY name ASC";
        $medicine_stmt = $conn->prepare($medicine_query);
        $medicine_stmt->execute();
        $medicines = $medicine_stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("[SSCMS Medicine Inventory] Query: $query, Params: " . json_encode($params) . ", Results: " . count($logs));
        echo json_encode([
            'data' => $logs,
            'total_cost' => $total_cost,
            'medicines' => $medicines
        ]);
    } catch (PDOException $e) {
        error_log("[SSCMS Medicine Inventory] PDO error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['data' => [], 'error' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("[SSCMS Medicine Inventory] Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['data' => [], 'error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SSCMS - Stock Audit Logs">
    <meta name="author" content="ICCB">
    <title>Stock Audit Logs - Clinic Management</title>
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
        .btn-success {
            background-color: var(--success);
            border-color: var(--success);
        }
        .btn-success:hover {
            background-color: var(--success-dark);
            border-color: var(--success-dark);
        }
        .toast-container {
            position: fixed;
            top: 0.75rem;
            right: 0.75rem;
        }
        .total-cost {
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 1rem;
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
                        <h5 class="card-title"><i class="fas fa-clipboard-list me-1"></i> Audit Logs</h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="medicineFilter" class="form-label">Select Medicine</label>
                                <select id="medicineFilter" class="form-select" title="Choose a medicine to view its stock logs">
                                    <option value="">All Medicines</option>
                                </select>
                                <div class="form-text">Select a medicine to filter logs</div>
                            </div>
                            <div class="col-md-3">
                                <label for="yearFilter" class="form-label">Year</label>
                                <select id="yearFilter" class="form-select" title="Filter logs by year">
                                    <!-- Years populated dynamically by JavaScript -->
                                </select>
                                <div class="form-text">Filter logs by year</div>
                            </div>
                            <div class="col-md-3">
                                <label for="monthFilter" class="form-label">Month</label>
                                <select id="monthFilter" class="form-select" title="Filter logs by month">
                                    <option value="">All Months</option>
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                                <div class="form-text">Filter logs by month</div>
                            </div>
                            <div class="col-md-3">
                                <label for="dateFilter" class="form-label">Date</label>
                                <input type="date" id="dateFilter" class="form-control" title="Filter logs by specific date">
                                <div class="form-text">Select a date to filter logs</div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="auditLogTable" class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Batch Number</th>
                                        <th>Medicine Name</th>
                                        <th>Quantity Added</th>
                                        <th>Old Quantity</th>
                                        <th>New Quantity</th>
                                        <th>Recorded by</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Total Cost (₱)</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="total-cost mb-3" id="totalCost"></div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" id="clearFilterBtn">
                                <i class="fas fa-undo me-1"></i> Clear
                            </button>
                            <button class="btn btn-success" id="downloadExcelBtn">
                                <i class="fas fa-file-excel me-1"></i> Download Excel
                            </button>
                            <a href="medicine_expiration.php" class="btn btn-primary">
                                <i class="fas fa-calendar-times me-1"></i> Manage Expirations
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[SSCMS Medicine Inventory] Initialized');

            // Dynamically populate Year filter
            const currentYear = new Date().getFullYear();
            const yearFilter = $('#yearFilter');
            yearFilter.append('<option value="">All Years</option>');
            for (let year = currentYear; year >= 2000; year--) {
                yearFilter.append(`<option value="${year}">${year}</option>`);
            }

            // Initialize Audit Log DataTable
            const auditLogTable = $('#auditLogTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[6, 'desc']],
                language: { search: "", searchPlaceholder: "Search logs..." },
                serverSide: false,
                ajax: {
                    url: 'stock_audit_logs.php?ajax=1',
                    type: 'GET',
                    data: function(d) {
                        d.medicine_id = $('#medicineFilter').val();
                        d.year = $('#yearFilter').val();
                        d.month = $('#monthFilter').val();
                        d.date = $('#dateFilter').val();
                    },
                    dataSrc: function(json) {
                        console.log('[SSCMS Medicine Inventory] Audit Log JSON:', json);
                        if (!json.data) {
                            console.error('[SSCMS Medicine Inventory] No data in JSON:', json);
                            alert('Failed to load audit logs: ' + (json.error || 'No data returned'));
                            return [];
                        }
                        if (json.error) {
                            console.error('[SSCMS Medicine Inventory] Error in JSON:', json.error);
                            alert('Error: ' + json.error);
                            return [];
                        }
                        $('#totalCost').html(`Total Cost: ₱ <span>${parseFloat(json.total_cost).toFixed(2)}</span>`);
                        if (json.medicines) {
                            const medicineFilter = $('#medicineFilter');
                            medicineFilter.empty().append('<option value="">All Medicines</option>');
                            json.medicines.forEach(med => {
                                medicineFilter.append(`<option value="${med.id}">${med.name}</option>`);
                            });
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        console.error('[SSCMS Medicine Inventory] AJAX error:', xhr.status, error);
                        console.log('[SSCMS Medicine Inventory] Response text:', xhr.responseText);
                        alert('Error fetching audit logs. Status: ' + xhr.status + '. Check console.');
                    }
                },
                columns: [
                    { data: 'batch_number', defaultContent: '-' },
                    { data: 'medicine_name' },
                    { data: 'quantity_added' },
                    { data: 'old_quantity' },
                    { data: 'new_quantity' },
                    { data: 'user_name' },
                    { 
                        data: 'created_at',
                        render: function(data) {
                            return data ? new Date(data).toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: '2-digit' }) : '-';
                        }
                    },
                    { 
                        data: 'created_at',
                        render: function(data) {
                            return data ? new Date(data).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '-';
                        }
                    },
                    { 
                        data: 'cost',
                        render: function(data) {
                            return '₱ ' + parseFloat(data).toFixed(2);
                        }
                    }
                ]
            });

            // Filter on change
            $('#medicineFilter, #yearFilter, #monthFilter, #dateFilter').on('change', function() {
                auditLogTable.ajax.reload();
            });

            // Clear Filter Button
            $('#clearFilterBtn').on('click', function() {
                $('#medicineFilter, #yearFilter, #monthFilter, #dateFilter').val('');
                auditLogTable.ajax.reload();
            });

            // Excel Export
            $('#downloadExcelBtn').on('click', function() {
                const data = auditLogTable.rows().data().toArray();
                if (data.length === 0) {
                    alert('No data to export.');
                    return;
                }
                
                const totalCost = parseFloat($('#totalCost span').text()).toFixed(2);
                const currentDate = new Date().toISOString().split('T')[0];
                const medicineName = $('#medicineFilter option:selected').text() || 'All_Medicines';
                
                // Prepare data
                const excelData = [
                    { '': 'Stock Audit Logs', ' ': '', '  ': '', '   ': '', '    ': '', '     ': '', '      ': '', '       ': '', '        ': '' }, // Title row
                    { '': '', ' ': '', '  ': '', '   ': '', '    ': '', '     ': '', '      ': '', '       ': '', '        ': '' } // Empty row
                ];
                
                // Header row
                excelData.push({
                    '': 'Batch Number',
                    ' ': 'Medicine Name',
                    '  ': 'Quantity Added',
                    '   ': 'Old Quantity',
                    '    ': 'New Quantity',
                    '     ': 'Recorded by',
                    '      ': 'Date',
                    '       ': 'Time',
                    '        ': 'Total Cost'
                });
                
                // Data rows
                data.forEach(row => {
                    excelData.push({
                        '': row.batch_number || '-',
                        ' ': row.medicine_name,
                        '  ': row.quantity_added,
                        '   ': row.old_quantity,
                        '    ': row.new_quantity,
                        '     ': row.user_name,
                        '      ': row.created_at ? new Date(row.created_at).toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: '2-digit' }) : '-',
                        '       ': row.created_at ? new Date(row.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '-',
                        '        ': parseFloat(row.cost).toFixed(2)
                    });
                });
                
                // Empty row
                excelData.push({
                    '': '', ' ': '', '  ': '', '   ': '', '    ': '', '     ': '', '      ': '', '       ': '', '        ': ''
                });
                
                // Total row
                excelData.push({
                    '': '', ' ': '', '  ': '', '   ': '', '    ': '', '     ': '', '      ': '', '       ': 'Total Cost:', '        ': parseFloat(totalCost).toFixed(2)
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
                        
                        // Total row (last row)
                        if (R === range.e.r) {
                            ws[cellAddress].s.font = { bold: true };
                            ws[cellAddress].s.fill = { fgColor: { rgb: 'F0F0F0' } };
                        }
                        
                        // Total Cost column (C=8)
                        if (C === 8 && R >= 3 && R < range.e.r) {
                            ws[cellAddress].t = 'n';
                            ws[cellAddress].z = '"₱"#,##0.00';
                        }
                        if (C === 8 && R === range.e.r) {
                            ws[cellAddress].t = 'n';
                            ws[cellAddress].z = '"₱"#,##0.00';
                        }
                    }
                }
                
                // Set column widths
                ws['!cols'] = [
                    { width: 15 },
                    { width: 25 },
                    { width: 15 },
                    { width: 12 },
                    { width: 12 },
                    { width: 20 },
                    { width: 12 },
                    { width: 12 },
                    { width: 15 }
                ];
                
                // Merge title row
                ws['!merges'] = [
                    { s: { r: 0, c: 0 }, e: { r: 0, c: 8 } }
                ];
                
                // Create workbook
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Stock Audit Logs');
                
                // Download
                XLSX.writeFile(wb, `Stock_Audit_Logs_${medicineName.replace(/[^a-zA-Z0-9]/g, '_')}_${currentDate}.xlsx`);
            });

            // Initialize toasts
            $('.toast').toast({ delay: 3000 });
            $('.toast').toast('show');
        });
    </script>
</body>
</html>