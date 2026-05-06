<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {
        $barcode = filter_var($_POST['barcode'], FILTER_SANITIZE_STRING);
        
        $stmt = $conn->prepare("SELECT id, name FROM medicines WHERE barcode = ?");
        $stmt->execute([$barcode]);
        $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($medicine) {
            echo json_encode([
                'success' => true,
                'medicine_id' => $medicine['id'],
                'medicine_name' => $medicine['name']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No medicine found with this barcode.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request.'
        ]);
    }
} catch (Exception $e) {
    error_log("[SSCMS Medicine Inventory] Barcode lookup error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error processing barcode.'
    ]);
}
?>