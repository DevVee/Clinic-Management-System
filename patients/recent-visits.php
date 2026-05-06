<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    $_SESSION['error_message'] = 'Invalid patient ID.';
    header('Location: manage-patients.php');
    exit;
}

// Fetch patient details
try {
    $stmt = $conn->prepare("SELECT first_name, last_name, middle_name FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        $_SESSION['error_message'] = 'Patient not found.';
        header('Location: manage-patients.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("[SSCMS Visits] Database error (patient fetch): " . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred.';
    header('Location: manage-patients.php');
    exit;
}
$full_name = trim($patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name']);

// Fetch visit history
try {
    $stmt = $conn->prepare("
        SELECT 
            v.id, 
            v.visit_date, 
            v.visit_time, 
            v.severity,
            COALESCE(
                GROUP_CONCAT(
                    DISTINCT CASE 
                        WHEN vr.reason = 'Other' THEN COALESCE(vr.other_reason, 'Other')
                        ELSE vr.reason
                    END
                    SEPARATOR ', '
                ),
                CASE 
                    WHEN v.reason = 'Other' THEN COALESCE(v.other_reason, v.reason)
                    WHEN v.reason IS NOT NULL AND v.reason != '' THEN v.reason
                    ELSE 'Unknown'
                END
            ) AS reason,
            CASE 
                WHEN v.took_medicine = 'Yes' THEN
                    COALESCE(
                        GROUP_CONCAT(
                            DISTINCT CONCAT(m.name, ' [', vm.quantity, ']')
                            SEPARATOR ', '
                        ),
                        GROUP_CONCAT(
                            DISTINCT CONCAT(m2.name, ' [', ml.quantity_used, ']')
                            SEPARATOR ', '
                        ),
                        CONCAT(COALESCE(m3.name, v.medicine_name, 'Unknown'), ' [', COALESCE(v.medicine_quantity, 0), ']')
                    )
                ELSE 'No Medicine Taken'
            END AS medicine_taken
        FROM visits v
        LEFT JOIN visit_reasons vr ON v.id = vr.visit_id
        LEFT JOIN visit_medicines vm ON v.id = vm.visit_id
        LEFT JOIN medicines m ON vm.medicine_id = m.id
        LEFT JOIN medicine_logs ml ON v.patient_id = v.patient_id 
            AND DATE(ml.visit_date) = v.visit_date
        LEFT JOIN medicines m2 ON ml.medicine_id = m2.id
        LEFT JOIN medicines m3 ON v.medicine_id = m3.id
        WHERE v.patient_id = ?
        GROUP BY v.id, v.visit_date, v.visit_time, v.severity, v.took_medicine
        ORDER BY v.visit_date DESC, v.visit_time DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("[SSCMS Visits] Database error (visit history): " . $e->getMessage());
    $visits = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Visits">
    <meta name="author" content="ICCB">
    <title>Visits for <?= htmlspecialchars($full_name) ?> - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <?php include '../includes/sscmslogo.php'; ?>
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
            --warning: #d97706;
            --danger: #dc2626;
            --blue-accent: #60a5fa;
            --transition-speed: 0.2s;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.5;
            font-size: 10pt;
            margin: 0;
            padding-top: 0 !important;
        }

        .content {
            margin-top: 95px;
            padding: 10mm;
            margin-left: 90px;
        }

        .container {
            max-width: 210mm;
            margin: 0 auto;
        }

        .action-buttons {
            margin-bottom: 5mm;
            display: flex;
            gap: 5mm;
        }

        .report-card {
            width: 210mm;
            height: auto;
            border: 2px solid var(--primary);
            border-radius: 8px;
            background: var(--card-bg);
            padding: 10mm;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            top: 0;
            page-break-inside: avoid;
        }

        .report-header {
            display: flex;
            flex-direction: column;
            align-items: start;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 5mm;
            margin-bottom: 5mm;
        }

        .report-header h1 {
            font-size: 16pt;
            font-weight: 700;
            color: var(--primary);
            margin: 2mm 0;
        }

        .patient-name {
            font-size: 14pt;
            font-weight: 700;
            color: black;
        }

        .report-header .subtitle {
            font-size: 8pt;
            color: var(--text-secondary);
        }

        .report-section {
            margin-bottom: 3mm;
        }

        .report-section h2 {
            font-size: 12pt;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 1px solid var(--border);
            padding-bottom: 2mm;
            margin-bottom: 3mm;
        }

        .visit-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
            font-size: 7pt;
        }

        .visit-table th, .visit-table td {
            border: 1px solid var(--border);
            padding: 2mm;
            text-align: left;
        }

        .visit-table th {
            background: #f9fafb;
            font-weight: 600;
            width: 30mm;
        }

        .visit-table td {
            font-weight: 500;
        }

        .severity-text {
            font-weight: 500;
        }

        .severity-mild { color: var(--success); }
        .severity-moderate { color: var(--warning); }
        .severity-severe { color: var(--danger); }

        .back-button, .print-button, .download-button {
            display: inline-block;
            padding: 2mm 5mm;
            font-size: 8pt;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color var(--transition-speed);
        }

        .back-button {
            background-color: var(--secondary);
            color: white;
        }

        .back-button:hover {
            background-color: var(--secondary-dark);
        }

        .print-button {
            background-color: var(--primary);
            color: white;
        }

        .print-button:hover {
            background-color: var(--primary-dark);
        }

        .download-button {
            background-color: var(--success);
            color: white;
        }

        .download-button:hover {
            background-color: #047857;
        }

        .text-muted {
            color: var(--text-secondary);
            font-style: italic;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.4s ease forwards;
        }

        @media (max-width: 768px) {
            .content {
                padding: 5mm;
                margin-left: 0;
            }
            .container, .report-card {
                width: 100%;
                height: auto;
                max-height: none;
                padding: 5mm;
            }
            .report-header h1 {
                font-size: 14pt;
            }
            .patient-name {
                font-size: 11pt;
            }
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
                padding-top: 0 !important;
            }
            nav, .navbar, .main-navbar, #navbar, .top-header, .sidebar, .sidebar-overlay, .action-buttons, .clinic-footer {
                display: none !important;
            }
            .content {
                margin: 0;
                padding: 0;
            }
            .container {
                margin: 0;
                padding: 0;
                width: 210mm;
            }
            .report-card {
                width: 210mm;
                height: auto;
                border: 2px solid var(--primary);
                border-radius: 8px;
                padding: 10mm;
                box-shadow: none;
                display: flex;
                flex-direction: column;
                position: relative;
                overflow: hidden;
                top: 0;
                margin: 0;
                page-break-inside: avoid;
            }
            .report-header {
                text-align: center;
                border-bottom: 2px solid var(--primary);
                padding-bottom: 5mm;
                margin-bottom: 5mm;
            }
            .report-header h1 {
                font-size: 16pt;
                font-weight: 700;
                color: var(--primary);
            }
            .patient-name {
                font-size: 11pt;
                font-weight: 500;
                color: #000000;
            }
            .report-header .subtitle {
                font-size: 8pt;
                color: var(--text-secondary);
            }
            .report-section {
                margin-bottom: 3mm;
            }
            .report-section h2 {
                font-size: 12pt;
                font-weight: 600;
                color: var(--primary);
                border-bottom: 1px solid var(--border);
                padding-bottom: 2mm;
                margin-bottom: 3mm;
            }
            .visit-table {
                font-size: 7pt;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container">
                <div class="action-buttons fade-in">
                    <a href="patient_health_report.php?id=<?= $patient_id ?>" class="back-button">Back</a>
                    <button onclick="window.print()" class="print-button">Print</button>
                    <button onclick="exportToPDF()" class="download-button">Download as PDF</button>
                </div>
                <div class="report-card fade-in">
                    <!-- Report Header -->
                    <div class="report-header">
                        <h1>Visits</h1>
                        <div class="patient-name"><?= htmlspecialchars($full_name) ?></div>
                        <div class="subtitle">Clinic Management System</div>
                    </div>

                    <!-- Visit History -->
                    <div class="report-section">
                        <h2>Visit History</h2>
                        <?php if (empty($visits)): ?>
                            <p class="text-muted">No visits recorded.</p>
                        <?php else: ?>
                            <table class="visit-table">
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Reason</th>
                                    <th>Severity</th>
                                    <th>Medicine</th>
                                </tr>
                                <?php foreach ($visits as $visit): ?>
                                    <tr>
                                        <td><?= date('F j, Y', strtotime($visit['visit_date'])) ?></td>
                                        <td><?= date('h:i A', strtotime($visit['visit_time'])) ?></td>
                                        <td><?= htmlspecialchars($visit['reason']) ?></td>
                                        <td>
                                            <span class="severity-text severity-<?= strtolower($visit['severity']) ?>">
                                                <?= htmlspecialchars($visit['severity']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($visit['medicine_taken']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

      
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const reportCard = document.querySelector('.report-card');
            const originalStyles = {
                margin: reportCard.style.margin,
                position: reportCard.style.position,
                top: reportCard.style.top,
                boxShadow: reportCard.style.boxShadow
            };

            // Temporarily apply print styles
            reportCard.style.margin = '0';
            reportCard.style.position = 'absolute';
            reportCard.style.top = '0';
            reportCard.style.boxShadow = 'none';

            html2canvas(reportCard, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });

                const imgWidth = 190;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                const pageHeight = 297;

                if (imgHeight <= pageHeight - 20) {
                    pdf.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight);
                } else {
                    let position = 0;
                    while (position < imgHeight) {
                        pdf.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight, null, 'FAST', 0, position);
                        position += pageHeight - 20;
                        if (position < imgHeight) {
                            pdf.addPage();
                        }
                    }
                }

                pdf.save('Visits_<?php echo str_replace(' ', '_', htmlspecialchars($full_name)); ?>.pdf');

                // Restore original styles
                Object.assign(reportCard.style, originalStyles);
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('Failed to generate PDF. Please try again.');
                Object.assign(reportCard.style, originalStyles);
            });
        }
    </script>
</body>
</html>