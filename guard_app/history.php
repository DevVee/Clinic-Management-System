
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Fetch programs and years for filters
try {
    $programs = $conn->query("SELECT DISTINCT name FROM program_sections ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $years = $conn->query("SELECT DISTINCT name FROM grade_years ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("[SSCMS Guard History] Error fetching programs/years: " . $e->getMessage(), 3, 'errors.log');
    $_SESSION['error_message'] = 'Failed to load filter options. Please try again.';
    $programs = [];
    $years = [];
}

// Handle filtering
$filters = [];
$where_clauses = ["UPPER(v.visit_handling) = 'SEND HOME'"];
$params = [];

if (isset($_GET['date']) && !empty($_GET['date'])) {
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $where_clauses[] = "v.visit_date = ?";
        $params[] = $date;
        $filters['date'] = $date;
    }
}
if (isset($_GET['program']) && !empty($_GET['program'])) {
    $program = filter_input(INPUT_GET, 'program', FILTER_SANITIZE_SPECIAL_CHARS);
    $where_clauses[] = "p.program_section = ?";
    $params[] = $program;
    $filters['program'] = $program;
}
if (isset($_GET['year']) && !empty($_GET['year'])) {
    $year = filter_input(INPUT_GET, 'year', FILTER_SANITIZE_SPECIAL_CHARS);
    $where_clauses[] = "p.grade_year = ?";
    $params[] = $year;
    $filters['year'] = $year;
}

$where_clause = implode(' AND ', $where_clauses);
try {
    $query = "
        SELECT v.id, v.visit_date, v.visit_time, v.discharge_time, v.severity, v.is_exited, p.first_name, p.last_name, p.program_section, p.grade_year
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE $where_clause
        ORDER BY v.visit_date DESC, v.visit_time DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Guard History] Error fetching visits: " . $e->getMessage(), 3, 'errors.log');
    $_SESSION['error_message'] = 'Failed to load visit history. Please try again.';
    $visits = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Guard History - Send Home Students">
    <meta name="author" content="ICCB">
    <title>Guard History - Clinic Management</title>
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
        .filter-button, .refresh-button {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 font-sans text-gray-900 dark:text-gray-100 transition-colors duration-300">
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mx-auto px-4 pt-20 pb-20 sm:px-6 lg:px-8 min-h-screen">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white"><i class="fas fa-history mr-2 text-blue-600 dark:text-blue-400"></i>Send Home History</h1>
            <div class="flex space-x-2">
                <button id="filter-button" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 text-sm font-medium text-gray-600 dark:text-gray-300">
                    <i class="fas fa-filter"></i>
                </button>
                <button id="refresh-button" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200 text-sm font-medium text-gray-600 dark:text-gray-300">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <!-- Filter Modal -->
        <div id="filterModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-sm w-full animate-bounce-in">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Filter History</h2>
                    <button onclick="closeModal('filterModal')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="history.php" method="GET" class="space-y-4">
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date</label>
                        <input type="date" name="date" id="date" value="<?= isset($filters['date']) ? htmlspecialchars($filters['date']) : '' ?>" class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded text-sm p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label for="program" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Program/Section</label>
                        <select name="program" id="program" class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded text-sm p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">All Programs</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= htmlspecialchars($program) ?>" <?= isset($filters['program']) && $filters['program'] === $program ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($program) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Grade/Year</label>
                        <select name="year" id="year" class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded text-sm p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">All Years</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?= htmlspecialchars($year) ?>" <?= isset($filters['year']) && $filters['year'] === $year ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg font-medium transition-colors duration-200 flex items-center justify-center text-sm animate-scale">
                            <i class="fas fa-filter mr-2"></i>Apply
                        </button>
                        <a href="history.php" class="flex-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 py-2 px-4 rounded-lg font-medium transition-colors duration-200 flex items-center justify-center text-sm animate-scale">
                            <i class="fas fa-undo mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
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

        <!-- Visit List -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="p-4">
                <?php if (empty($visits)): ?>
                    <div class="text-center text-gray-600 dark:text-gray-400 py-4 text-sm">
                        No Send Home records found for the selected filters.
                    </div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($visits as $index => $visit): ?>
                            <li class="py-3 px-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer animate-slide-in" onclick="openModal('visitModal<?= $index ?>')">
                                <div>
                                    <p class="text-gray-900 dark:text-white font-medium text-sm"><?= htmlspecialchars($visit['last_name'] . ', ' . $visit['first_name']) ?></p>
                                    <p class="text-gray-500 dark:text-gray-400 text-xs"><?= htmlspecialchars($visit['program_section'] ?: 'N/A') ?> <?= htmlspecialchars($visit['grade_year'] ?: '') ?></p>
                                </div>
                            </li>
                            <!-- Modal -->
                            <div id="visitModal<?= $index ?>" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
                                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-sm w-full animate-bounce-in">
                                    <div class="flex justify-between items-center mb-4">
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Visit Details</h2>
                                        <button onclick="closeModal('visitModal<?= $index ?>')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                        <p><strong>Name:</strong> <?= htmlspecialchars($visit['last_name'] . ', ' . $visit['first_name']) ?></p>
                                        <p><strong>Grade/Year:</strong> <?= htmlspecialchars($visit['grade_year'] ?: 'N/A') ?></p>
                                        <p><strong>Program/Section:</strong> <?= htmlspecialchars($visit['program_section'] ?: 'N/A') ?></p>
                                        <p><strong>Visit Date:</strong> <?= date('F j, Y', strtotime($visit['visit_date'])) ?></p>
                                        <p><strong>Visit Time:</strong> <?= date('h:i A', strtotime($visit['visit_time'])) ?></p>
                                        <p><strong>Discharge Time:</strong> <?= $visit['discharge_time'] ? date('h:i A', strtotime($visit['discharge_time'])) : '—' ?></p>
                                        <p><strong>Severity:</strong> <span class="inline-block px-2 py-1 rounded-full text-xs font-medium <?= $visit['severity'] === 'High' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : ($visit['severity'] === 'Medium' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200' : 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200') ?>"><?= htmlspecialchars($visit['severity']) ?></span></p>
                                        <p><strong>Status:</strong> <?= $visit['is_exited'] ? '<span class="text-green-600 dark:text-green-400">Exited</span>' : '<span class="text-red-600 dark:text-red-400">Not Exited</span>' ?></p>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button onclick="closeModal('visitModal<?= $index ?>')" class="bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 animate-scale">Close</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Refresh button
        document.getElementById('refresh-button').addEventListener('click', () => {
            window.location.reload();
        });

        // Filter modal
        document.getElementById('filter-button').addEventListener('click', () => {
            openModal('filterModal');
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

        // Auto-hide toast messages
        setTimeout(() => {
            const errorToast = document.getElementById('error-toast');
            const successToast = document.getElementById('success-toast');
            if (errorToast) errorToast.style.display = 'none';
            if (successToast) successToast.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>
