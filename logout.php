<?php
session_start();
require_once 'config/database.php';

// Update sessions table to remove current session
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM sessions WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$_SESSION['user_id'], session_id()]);
    } catch (Exception $e) {
        error_log("Logout session deletion error: " . $e->getMessage());
    }
}

// Clear session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_regenerate_id(true);
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School and Student Clinic Management System - Logout">
    <meta name="author" content="ICCB">
    <title>Logout - SSCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --background: #f3f4f6;
            --card-bg: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border: #d1d5db;
            --success: #059669;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .logout-container {
            max-width: 400px;
            width: 100%;
            padding: 1rem;
        }

        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            background: var(--card-bg);
        }

        .card-body {
            padding: 1.5rem;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            font-size: 0.85rem;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-3"><i class="fas fa-sign-out-alt me-2" style="color: var(--success);"></i>Logged Out</h4>
                <p>You have been successfully logged out of SSCMS.</p>
                <a href="/login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt me-2"></i>Login Again</a>
            </div>
        </div>
    </div>

    <script>
        // Auto-redirect to login after 2 seconds
        setTimeout(() => {
            window.location.href = '/login.php';
        }, 2000);
    </script>
</body>
</html>