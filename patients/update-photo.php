<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Update Photo] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        $visit_id = filter_input(INPUT_POST, 'visit_id', FILTER_VALIDATE_INT);
        $old_photo_path = filter_input(INPUT_POST, 'old_photo_path', FILTER_SANITIZE_SPECIAL_CHARS);

        if (!$visit_id || !$old_photo_path) {
            throw new Exception('Invalid visit ID or photo path.');
        }

        // Fetch existing photo_paths
        $stmt = $conn->prepare("SELECT photo_paths, patient_id, visit_date, visit_time FROM visits WHERE id = ?");
        $stmt->execute([$visit_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$visit) {
            throw new Exception('Visit not found.');
        }

        $photo_paths = json_decode($visit['photo_paths'], true);
        if (!is_array($photo_paths)) {
            throw new Exception('Invalid photo data in database.');
        }

        // Validate new photo
        if (empty($_FILES['new_photo']['name'])) {
            throw new Exception('No photo uploaded.');
        }

        if ($_FILES['new_photo']['error'] === UPLOAD_ERR_OK) {
            $imageFileType = strtolower(pathinfo($_FILES['new_photo']['name'], PATHINFO_EXTENSION));
            if ($imageFileType !== 'png') {
                throw new Exception('Only PNG files are allowed.');
            }
            if ($_FILES['new_photo']['size'] > 20000000) {
                throw new Exception('File size exceeds 20MB limit.');
            }

            // Get patient details for filename
            $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM patients WHERE id = ?");
            $stmt->execute([$visit['patient_id']]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$patient) {
                throw new Exception('Patient not found.');
            }

            $full_name = strtolower(str_replace(' ', '_', trim(
                $patient['last_name'] . '_' . 
                $patient['first_name'] . 
                ($patient['middle_name'] ? '_' . $patient['middle_name'] : '')
            )));
            $date_time = str_replace([':', ' '], ['', '_'], date('Y_m_d_Hi_s', strtotime("{$visit['visit_date']} {$visit['visit_time']}")));
            $index = array_search($old_photo_path, $photo_paths);
            if ($index === false) {
                throw new Exception('Photo path not found in visit.');
            }

            $new_filename = "{$full_name}_{$date_time}_{$index}.png";
            $upload_dir = '../Uploads/patient_photos/';
            $target_file = $upload_dir . $new_filename;

            if (!move_uploaded_file($_FILES['new_photo']['tmp_name'], $target_file)) {
                error_log("[SSCMS Update Photo] Upload failed: Error=" . $_FILES['new_photo']['error'] . ", Target=$target_file");
                throw new Exception('Failed to upload new photo.');
            }

            // Update photo_paths array
            $photo_paths[$index] = $new_filename;

            // Update database
            $stmt = $conn->prepare("UPDATE visits SET photo_paths = ? WHERE id = ?");
            $stmt->execute([json_encode($photo_paths), $visit_id]);

            // Delete old photo
            if (file_exists($upload_dir . $old_photo_path)) {
                unlink($upload_dir . $old_photo_path);
            }

            $conn->commit();
            error_log("[SSCMS Update Photo] Success: visit_id=$visit_id, old_photo=$old_photo_path, new_photo=$new_filename");
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Photo upload error: ' . $_FILES['new_photo']['error']);
        }
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("[SSCMS Update Photo] Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>