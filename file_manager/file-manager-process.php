<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS File Manager Process] Unauthorized access: no session");
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit(json_encode(['error' => 'Method not allowed']));
}

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
$upload_dir = '../Uploads/patient_photos/';

try {
    $conn->beginTransaction();

    if ($action === 'add') {
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $visit_date = filter_input(INPUT_POST, 'visit_date', FILTER_SANITIZE_SPECIAL_CHARS);
        $visit_time = filter_input(INPUT_POST, 'visit_time', FILTER_SANITIZE_SPECIAL_CHARS);
        $severity = filter_input(INPUT_POST, 'severity', FILTER_SANITIZE_SPECIAL_CHARS);

        if (!$patient_id || !$visit_date || !$visit_time || !in_array($severity, ['Moderate', 'Severe'])) {
            throw new Exception('Invalid input data');
        }

        // Fetch patient details
        $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$patient) {
            throw new Exception('Patient not found');
        }

        // Handle photo upload
        if (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Photo upload error');
        }

        $imageFileType = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if ($imageFileType !== 'png') {
            throw new Exception('Only PNG files are allowed');
        }
        if ($_FILES['photo']['size'] > 20000000) {
            throw new Exception('File size exceeds 20MB limit');
        }

        // Check for existing visit
        $stmt = $conn->prepare("SELECT id, photo_paths FROM visits WHERE patient_id = ? AND visit_date = ? AND visit_time = ?");
        $stmt->execute([$patient_id, $visit_date, $visit_time]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        $full_name = strtolower(str_replace(' ', '_', trim(
            $patient['last_name'] . '_' . 
            $patient['first_name'] . 
            ($patient['middle_name'] ? '_' . $patient['middle_name'] : '')
        )));
        $date_time = str_replace([':', ' '], ['', '_'], date('Y_m_d_Hi_s', strtotime("$visit_date $visit_time")));

        if ($visit) {
            $photo_paths = json_decode($visit['photo_paths'], true) ?: [];
            $index = count($photo_paths);
            $new_filename = "{$full_name}_{$date_time}_{$index}.png";
            $target_file = $upload_dir . $new_filename;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                throw new Exception('Failed to upload photo');
            }

            $photo_paths[] = $new_filename;
            $stmt = $conn->prepare("UPDATE visits SET photo_paths = ?, severity = ? WHERE id = ?");
            $stmt->execute([json_encode($photo_paths), $severity, $visit['id']]);
        } else {
            $new_filename = "{$full_name}_{$date_time}_0.png";
            $target_file = $upload_dir . $new_filename;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                throw new Exception('Failed to upload photo');
            }

            $photo_paths = [$new_filename];
            $stmt = $conn->prepare("
                INSERT INTO visits (patient_id, visit_date, visit_time, severity, photo_paths, reason)
                VALUES (?, ?, ?, ?, ?, 'Photo Upload')
            ");
            $stmt->execute([$patient_id, $visit_date, $visit_time, $severity, json_encode($photo_paths)]);
        }

        $conn->commit();
        error_log("[SSCMS File Manager Process] Add success: patient_id=$patient_id, filename=$new_filename");
        exit(json_encode(['success' => true]));
    } elseif ($action === 'edit') {
        $visit_id = filter_input(INPUT_POST, 'visit_id', FILTER_VALIDATE_INT);
        $old_photo_path = filter_input(INPUT_POST, 'old_photo_path', FILTER_SANITIZE_SPECIAL_CHARS);

        if (!$visit_id || !$old_photo_path) {
            throw new Exception('Invalid visit ID or photo path');
        }

        // Fetch visit details
        $stmt = $conn->prepare("SELECT photo_paths, patient_id, visit_date, visit_time FROM visits WHERE id = ?");
        $stmt->execute([$visit_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$visit) {
            throw new Exception('Visit not found');
        }

        $photo_paths = json_decode($visit['photo_paths'], true);
        if (!is_array($photo_paths)) {
            throw new Exception('Invalid photo data');
        }

        // Fetch patient details
        $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM patients WHERE id = ?");
        $stmt->execute([$visit['patient_id']]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$patient) {
            throw new Exception('Patient not found');
        }

        // Handle photo upload
        if (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Photo upload error');
        }

        $imageFileType = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if ($imageFileType !== 'png') {
            throw new Exception('Only PNG files are allowed');
        }
        if ($_FILES['photo']['size'] > 20000000) {
            throw new Exception('File size exceeds 20MB limit');
        }

        $full_name = strtolower(str_replace(' ', '_', trim(
            $patient['last_name'] . '_' . 
            $patient['first_name'] . 
            ($patient['middle_name'] ? '_' . $patient['middle_name'] : '')
        )));
        $date_time = str_replace([':', ' '], ['', '_'], date('Y_m_d_Hi_s', strtotime("{$visit['visit_date']} {$visit['visit_time']}")));
        $index = array_search($old_photo_path, $photo_paths);
        if ($index === false) {
            throw new Exception('Photo path not found in visit');
        }

        $new_filename = "{$full_name}_{$date_time}_{$index}.png";
        $target_file = $upload_dir . $new_filename;

        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            throw new Exception('Failed to upload new photo');
        }

        $photo_paths[$index] = $new_filename;
        $stmt = $conn->prepare("UPDATE visits SET photo_paths = ? WHERE id = ?");
        $stmt->execute([json_encode($photo_paths), $visit_id]);

        if (file_exists($upload_dir . $old_photo_path)) {
            unlink($upload_dir . $old_photo_path);
        }

        $conn->commit();
        error_log("[SSCMS File Manager Process] Edit success: visit_id=$visit_id, old_photo=$old_photo_path, new_photo=$new_filename");
        exit(json_encode(['success' => true]));
    } elseif ($action === 'delete') {
        $visit_id = filter_input(INPUT_POST, 'visit_id', FILTER_VALIDATE_INT);
        $photo_path = filter_input(INPUT_POST, 'photo_path', FILTER_SANITIZE_SPECIAL_CHARS);

        if (!$visit_id || !$photo_path) {
            throw new Exception('Invalid visit ID or photo path');
        }

        $stmt = $conn->prepare("SELECT photo_paths FROM visits WHERE id = ?");
        $stmt->execute([$visit_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$visit) {
            throw new Exception('Visit not found');
        }

        $photo_paths = json_decode($visit['photo_paths'], true);
        if (!is_array($photo_paths)) {
            throw new Exception('Invalid photo data');
        }

        $index = array_search($photo_path, $photo_paths);
        if ($index === false) {
            throw new Exception('Photo path not found in visit');
        }

        unset($photo_paths[$index]);
        $photo_paths = array_values($photo_paths);

        if (empty($photo_paths)) {
            $stmt = $conn->prepare("UPDATE visits SET photo_paths = NULL WHERE id = ?");
            $stmt->execute([$visit_id]);
        } else {
            $stmt = $conn->prepare("UPDATE visits SET photo_paths = ? WHERE id = ?");
            $stmt->execute([json_encode($photo_paths), $visit_id]);
        }

        if (file_exists($upload_dir . $photo_path)) {
            unlink($upload_dir . $photo_path);
        }

        $conn->commit();
        error_log("[SSCMS File Manager Process] Delete success: visit_id=$visit_id, photo=$photo_path");
        exit(json_encode(['success' => true]));
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $conn->rollBack();
    error_log("[SSCMS File Manager Process] Error: " . $e->getMessage());
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['error' => $e->getMessage()]));
}
?>