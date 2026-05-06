<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Top Navbar -->
<nav class="fixed top-0 left-0 right-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 shadow-sm z-50 transition-colors duration-300 glass-effect">
    <div class="container mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-shield-alt text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white">SSCMS</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">Guard Portal</p>
            </div>
        </div>
        <button id="dark-mode-toggle" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors duration-200">
            <i class="fas fa-moon text-blue-400 dark:text-yellow-400"></i>
        </button>
    </div>
</nav>

<!-- Bottom Navbar -->
<nav class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 z-50 transition-colors duration-300 glass-effect">
    <div class="container mx-auto px-4 py-2">
        <div class="flex justify-around items-center">
            <a href="dashboard.php" class="relative flex flex-col items-center py-2 px-4 rounded-xl transition-all duration-200 <?php echo $current_page === 'dashboard.php' ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800'; ?>">
                <div class="relative">
                    <i class="fas fa-home text-lg mb-1 <?php echo $current_page === 'dashboard.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'; ?>"></i>
                    <?php if ($current_page === 'dashboard.php'): ?>
                        <div class="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-blue-600 dark:bg-blue-400 rounded-full"></div>
                    <?php endif; ?>
                </div>
                <span class="text-xs font-medium <?php echo $current_page === 'dashboard.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'; ?>">Dashboard</span>
            </a>
            <a href="history.php" class="relative flex flex-col items-center py-2 px-4 rounded-xl transition-all duration-200 <?php echo $current_page === 'history.php' ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800'; ?>">
                <div class="relative">
                    <i class="fas fa-history text-lg mb-1 <?php echo $current_page === 'history.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'; ?>"></i>
                    <?php if ($current_page === 'history.php'): ?>
                        <div class="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-blue-600 dark:bg-blue-400 rounded-full"></div>
                    <?php endif; ?>
                </div>
                <span class="text-xs font-medium <?php echo $current_page === 'history.php' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'; ?>">History</span>
            </a>
        </div>
    </div>
</nav>

<style>
    .shadow-t-md {
        box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1), 0 -2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    nav a {
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    nav a:hover:not(.active) {
        transform: translateY(-1px);
    }
    .dark-mode-toggle {
        transition: all 0.3s ease;
    }
</style>
<script>
(function() {
    // Apply saved theme immediately (before render)
    var saved = localStorage.getItem('sscms_theme') || 'light';
    if (saved === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }

    // Wire up toggle button after DOM loads
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('dark-mode-toggle');
        if (!btn) return;

        function applyTheme(dark) {
            if (dark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = dark ? 'fas fa-sun text-yellow-400' : 'fas fa-moon text-blue-400';
            }
            localStorage.setItem('sscms_theme', dark ? 'dark' : 'light');
        }

        // Set initial icon state
        applyTheme(document.documentElement.classList.contains('dark'));

        btn.addEventListener('click', function() {
            applyTheme(!document.documentElement.classList.contains('dark'));
        });
    });
})();
</script>
