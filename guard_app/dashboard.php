<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Ensure log files are writable
$log_dir = dirname('errors.log');
if (!is_writable($log_dir)) {
    error_log("[SSCMS Guard Dashboard] Log directory not writable: $log_dir");
}
$log_dir = dirname('sms.log');
if (!is_writable($log_dir)) {
    error_log("[SSCMS Guard Dashboard] SMS log directory not writable: $log_dir");
}

// Fetch guard contact from settings
try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'guard_contact'");
    $stmt->execute();
    $guard_contact = $stmt->fetchColumn() ?: '09123456789';
} catch (Exception $e) {
    error_log("[SSCMS Guard Dashboard] Error fetching guard_contact: " . $e->getMessage(), 3, 'errors.log');
    $guard_contact = '09123456789';
}

// SMS function (from log-visit.php)
function sendSMS($number, $message) {
    $apiKey = '2840c5de6cdfbe118d100ad33fdc179b';
    $senderName = 'ICCBICLINIC';
    $url = 'https://api.semaphore.co/api/v4/messages';
    $data = [
        'apikey' => $apiKey,
        'number' => $number,
        'message' => $message,
        'sendername' => $senderName
    ];
    error_log("[SSCMS Guard Dashboard] SMS Request: Number=$number, Message=$message, Data=" . print_r($data, true), 3, 'sms.log');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        error_log("[SSCMS Guard Dashboard] cURL Error: $error, Number: $number", 3, 'sms.log');
        curl_close($ch);
        return "Failed to send SMS: $error";
    }
    curl_close($ch);
    error_log("[SSCMS Guard Dashboard] SMS Response: HTTP Code: $httpCode, Number: $number, Response: $response", 3, 'sms.log');
    $responseData = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($responseData)) {
        return isset($responseData[0]['status']) && $responseData[0]['status'] === 'Pending' ? "SMS send successful" : "Failed to send SMS: Invalid API response";
    }
    error_log("[SSCMS Guard Dashboard] SMS Error: HTTP $httpCode: $response, Number: $number", 3, 'sms.log');
    return "Failed to send SMS: HTTP $httpCode";
}

// Handle marking student as exited
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['visit_id'])) {
    $visit_id = filter_input(INPUT_POST, 'visit_id', FILTER_VALIDATE_INT);
    try {
        if (!$visit_id || $visit_id <= 0) {
            throw new Exception('Invalid visit ID provided.');
        }

        // Check if the visit exists and is eligible for marking as exited
        $stmt = $conn->prepare("
            SELECT v.visit_handling, v.is_exited, p.first_name, p.last_name, p.guardian_contact, p.adviser_id
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            WHERE v.id = ?
        ");
        $stmt->execute([$visit_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$visit) {
            throw new Exception('Visit not found.');
        }
        if (strtoupper($visit['visit_handling']) !== 'SEND HOME') {
            throw new Exception('Visit is not marked as Send Home.');
        }
        if ($visit['is_exited'] == 1) {
            throw new Exception('Visit is already marked as exited.');
        }

        // Critical: update visit status (standalone, no transaction dependency on logging)
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE visits SET is_exited = 1 WHERE id = ?");
        $stmt->execute([$visit_id]);
        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to update visit status.');
        }
        $conn->commit();

        // Non-critical: log the action (silent fail)
        try {
            $conn->prepare("INSERT INTO daily_logs (message, log_date, user_id) VALUES (?, NOW(), NULL)")
                 ->execute(["Guard marked visit_id=$visit_id as exited"]);
        } catch (Exception $logEx) {
            // Logging failure does not affect main operation
        }

        $_SESSION['success_message'] = 'Student marked as exited successfully!';

        // Non-critical: send SMS notifications
        $student_name = $visit['first_name'] . ' ' . $visit['last_name'];
        $sms_message = "$student_name has passed the guard house after their clinic visit.";

        if (!empty($visit['guardian_contact']) && preg_match('/^(\+63|0)9\d{9}$/', $visit['guardian_contact'])) {
            $_SESSION['sms_status_guardian'] = sendSMS($visit['guardian_contact'], $sms_message);
        } else {
            $_SESSION['sms_status_guardian'] = "Guardian SMS skipped: " . (empty($visit['guardian_contact']) ? "no contact on file." : "invalid number.");
        }

        if (!empty($visit['adviser_id'])) {
            $stmt = $conn->prepare("SELECT contact_number FROM advisers WHERE id = ?");
            $stmt->execute([$visit['adviser_id']]);
            $adviser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($adviser && !empty($adviser['contact_number']) && preg_match('/^(\+63|0)9\d{9}$/', $adviser['contact_number'])) {
                $_SESSION['sms_status_adviser'] = sendSMS($adviser['contact_number'], $sms_message);
            } else {
                $_SESSION['sms_status_adviser'] = "Adviser SMS skipped: " . ($adviser ? "invalid contact." : "adviser not found.");
            }
        } else {
            $_SESSION['sms_status_adviser'] = "Adviser SMS skipped: no adviser assigned.";
        }

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
        error_log("[SSCMS Guard] Error: " . $e->getMessage() . " | Visit ID: " . ($visit_id ?? 'unknown'));
    }
    header('Location: dashboard.php');
    exit;
}

// Fetch today's Send Home visits that are not marked as exited
$today = date('Y-m-d');
try {
    $stmt = $conn->prepare("
        SELECT v.id, v.visit_date, v.visit_time, v.discharge_time, v.severity, p.first_name, p.last_name, p.program_section, p.grade_year
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE v.visit_date = ? AND UPPER(v.visit_handling) = 'SEND HOME' AND (v.is_exited IS NULL OR v.is_exited = 0)
        ORDER BY v.visit_time DESC
    ");
    $stmt->execute([$today]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[SSCMS Guard Dashboard] Fetched " . count($visits) . " visits for date=$today", 3, 'errors.log');
} catch (Exception $e) {
    error_log("[SSCMS Guard Dashboard] Error fetching visits: " . $e->getMessage() . " | Line: " . $e->getLine(), 3, 'errors.log');
    $_SESSION['error_message'] = 'Failed to load visits. Please try again.';
    $visits = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Guard Dashboard - Send Home Students">
    <meta name="author" content="ICCB">
    <title>Guard Dashboard - Clinic Management</title>
    <script>(function(){var t=localStorage.getItem('sscms_theme');if(t==='dark')document.documentElement.classList.add('dark');})()</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:{50:'#eff6ff',100:'#dbeafe',500:'#0f73ba',600:'#0369a1',700:'#075985'}}}}}</script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-bounce-in {
            animation: bounceIn 0.3s ease-out;
        }
        @keyframes bounceIn {
            0% { transform: scale(0.95); opacity: 0; }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-scale {
            transition: transform 0.2s ease;
        }
        .animate-scale:hover {
            transform: scale(1.02);
        }
        .refresh-button {
            transition: all 0.3s ease;
        }
        .task-item {
            transition: all 0.2s ease;
        }
        .task-item:hover {
            transform: translateX(2px);
        }
        .glass-effect {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.9);
        }
        .dark .glass-effect {
            background: rgba(17, 24, 39, 0.9);
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 font-sans text-gray-900 dark:text-gray-100 transition-colors duration-300">
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mx-auto px-4 pt-20 pb-20 min-h-screen max-w-md">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white"><?= date('l, F j, Y') ?></h2>
            </div>
            <button id="refresh-button" class="px-3 py-1 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 text-sm font-medium text-gray-600 dark:text-gray-300">
                Refresh
            </button>
        </div>

        <!-- Error/Success Messages -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div id="error-toast" class="mb-4 p-4 bg-red-50 dark:bg-red-900/50 border border-red-200 dark:border-red-700 rounded-lg animate-slide-in">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                    <p class="text-sm text-red-800 dark:text-red-200"><?= htmlspecialchars($_SESSION['error_message']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div id="success-toast" class="mb-4 p-4 bg-green-50 dark:bg-green-900/50 border border-green-200 dark:border-green-700 rounded-lg animate-slide-in">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                    <p class="text-sm text-green-800 dark:text-green-200"><?= htmlspecialchars($_SESSION['success_message']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['sms_status_guardian'])): ?>
            <div id="sms-guardian-toast" class="mb-4 p-4 <?= strpos($_SESSION['sms_status_guardian'], 'Failed') === 0 ? 'bg-yellow-50 dark:bg-yellow-900/50 border-yellow-200 dark:border-yellow-700' : 'bg-green-50 dark:bg-green-900/50 border-green-200 dark:border-green-700' ?> border rounded-lg animate-slide-in">
                <div class="flex items-center">
                    <i class="fas <?= strpos($_SESSION['sms_status_guardian'], 'Failed') === 0 ? 'fa-exclamation-triangle text-yellow-500' : 'fa-check-circle text-green-500' ?> mr-2"></i>
                    <p class="text-sm <?= strpos($_SESSION['sms_status_guardian'], 'Failed') === 0 ? 'text-yellow-800 dark:text-yellow-200' : 'text-green-800 dark:text-green-200' ?>"><?= htmlspecialchars($_SESSION['sms_status_guardian']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['sms_status_guardian']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['sms_status_adviser'])): ?>
            <div id="sms-adviser-toast" class="mb-4 p-4 <?= strpos($_SESSION['sms_status_adviser'], 'Failed') === 0 ? 'bg-yellow-50 dark:bg-yellow-900/50 border-yellow-200 dark:border-yellow-700' : 'bg-green-50 dark:bg-green-900/50 border-green-200 dark:border-green-700' ?> border rounded-lg animate-slide-in">
                <div class="flex items-center">
                    <i class="fas <?= strpos($_SESSION['sms_status_adviser'], 'Failed') === 0 ? 'fa-exclamation-triangle text-yellow-500' : 'fa-check-circle text-green-500' ?> mr-2"></i>
                    <p class="text-sm <?= strpos($_SESSION['sms_status_adviser'], 'Failed') === 0 ? 'text-yellow-800 dark:text-yellow-200' : 'text-green-800 dark:text-green-200' ?>"><?= htmlspecialchars($_SESSION['sms_status_adviser']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['sms_status_adviser']); ?>
        <?php endif; ?>

        <!-- Task List -->
        <div class="space-y-2">
            <?php if (empty($visits)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-clipboard-check text-4xl text-gray-300 dark:text-gray-600 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No students to send home today</p>
                </div>
            <?php else: ?>
                <?php foreach ($visits as $index => $visit): ?>
                    <div class="task-item bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-1">
                                <span class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']) ?></span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-1"><?= htmlspecialchars($visit['program_section'] ?: 'N/A') ?> <?= htmlspecialchars($visit['grade_year'] ?: '') ?></p>
                            <div class="flex items-center text-xs text-gray-500 dark:text-gray-500">
                                <i class="fas fa-clock mr-1"></i>
                                <span><?= date('h:i A', strtotime($visit['visit_time'])) ?></span>
                                <span class="mx-2">•</span>
                                <span class="px-2 py-1 rounded-full text-xs <?= $visit['severity'] === 'High' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : ($visit['severity'] === 'Medium' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200' : 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200') ?>"><?= htmlspecialchars($visit['severity']) ?></span>
                            </div>
                        </div>
                        <button onclick="openModal('visitModal<?= $index ?>')" class="ml-4 p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/50 rounded-lg transition-colors duration-200 text-sm font-medium">
                            View
                        </button>
                    </div>

                    <!-- Modal -->
                    <div id="visitModal<?= $index ?>" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-sm w-full animate-bounce-in">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Visit Details</h2>
                                <button onclick="closeModal('visitModal<?= $index ?>')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="space-y-3 mb-6 text-sm">
                                <div class="flex items-center">
                                    <i class="fas fa-user w-5 text-gray-400 mr-3"></i>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Student Name</p>
                                        <p class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']) ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-graduation-cap w-5 text-gray-400 mr-3"></i>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Program & Year</p>
                                        <p class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($visit['program_section'] ?: 'N/A') ?> <?= htmlspecialchars($visit['grade_year'] ?: '') ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-clock w-5 text-gray-400 mr-3"></i>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Visit Time</p>
                                        <p class="font-medium text-gray-900 dark:text-white"><?= date('h:i A', strtotime($visit['visit_time'])) ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-sign-out-alt w-5 text-gray-400 mr-3"></i>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Discharge Time</p>
                                        <p class="font-medium text-gray-900 dark:text-white"><?= $visit['discharge_time'] ? date('h:i A', strtotime($visit['discharge_time'])) : '—' ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-triangle w-5 text-gray-400 mr-3"></i>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Severity</p>
                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-medium <?= $visit['severity'] === 'High' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : ($visit['severity'] === 'Medium' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200' : 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200') ?>"><?= htmlspecialchars($visit['severity']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex space-x-3">
                                <form action="dashboard.php" method="POST" class="flex-1">
                                    <input type="hidden" name="visit_id" value="<?= $visit['id'] ?>">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg font-medium transition-colors duration-200 flex items-center justify-center text-sm animate-scale">
                                        <i class="fas fa-check mr-2"></i>Mark as Exited
                                    </button>
                                </form>
                                <button onclick="closeModal('visitModal<?= $index ?>')" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-lg transition-colors duration-200 text-sm font-medium animate-scale">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Refresh button
        document.getElementById('refresh-button').addEventListener('click', () => {
            window.location.reload();
        });

        // Modal functions
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.querySelectorAll('.fixed.inset-0').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Debug form submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                console.log('Form submitted with visit_id:', form.querySelector('input[name="visit_id"]').value);
            });
        });

        // Auto-hide toast messages
        setTimeout(() => {
            const errorToast = document.getElementById('error-toast');
            const successToast = document.getElementById('success-toast');
            const guardianToast = document.getElementById('sms-guardian-toast');
            const adviserToast = document.getElementById('sms-adviser-toast');
            if (errorToast) errorToast.style.display = 'none';
            if (successToast) successToast.style.display = 'none';
            if (guardianToast) guardianToast.style.display = 'none';
            if (adviserToast) adviserToast.style.display = 'none';
        }, 7000);
    </script>
</body>
</html>