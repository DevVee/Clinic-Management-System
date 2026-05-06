<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS File Manager] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Fetch patients for filter
$stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name FROM patients ORDER BY last_name, first_name");
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch images from visits
$conditions = [];
$params = [];
$filter_patient = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
$filter_severity = filter_input(INPUT_GET, 'severity', FILTER_SANITIZE_SPECIAL_CHARS);
$filter_date_from = filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_SPECIAL_CHARS);
$filter_date_to = filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_SPECIAL_CHARS);
$sort_by = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'visit_date';
$sort_order = filter_input(INPUT_GET, 'sort_order', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'DESC';

if ($filter_patient) {
    $conditions[] = "v.patient_id = ?";
    $params[] = $filter_patient;
}
if ($filter_severity && in_array($filter_severity, ['Moderate', 'Severe'])) {
    $conditions[] = "v.severity = ?";
    $params[] = $filter_severity;
}
if ($filter_date_from) {
    $conditions[] = "v.visit_date >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $conditions[] = "v.visit_date <= ?";
    $params[] = $filter_date_to;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sort_column = $sort_by === 'size' ? 'file_size' : ($sort_by === 'name' ? 'photo_path' : 'v.visit_date');
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

$query = "
    SELECT v.id AS visit_id, v.patient_id, v.visit_date, v.visit_time, v.severity, v.photo_paths,
           p.first_name, p.last_name, p.middle_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    $where
    ORDER BY $sort_column $sort_order
";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$images = [];
$upload_dir = '../Uploads/patient_photos/';
foreach ($visits as $visit) {
    if ($visit['photo_paths']) {
        $photo_paths = json_decode($visit['photo_paths'], true);
        if (is_array($photo_paths)) {
            foreach ($photo_paths as $photo_path) {
                $file_path = $upload_dir . trim($photo_path);
                if (file_exists($file_path)) {
                    $images[] = [
                        'visit_id' => $visit['visit_id'],
                        'patient_id' => $visit['patient_id'],
                        'patient_name' => trim($visit['first_name'] . ' ' . ($visit['middle_name'] ? $visit['middle_name'] . ' ' : '') . $visit['last_name']),
                        'visit_date' => $visit['visit_date'],
                        'visit_time' => $visit['visit_time'],
                        'severity' => $visit['severity'],
                        'photo_path' => $photo_path,
                        'file_size' => filesize($file_path),
                        'file_mtime' => filemtime($file_path)
                    ];
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - File Manager">
    <meta name="author" content="ICCB">
    <title>File Manager - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include '../includes/sscmslogo.php'; ?>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
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
            --bscs-maroon: #800000;
            --sidebar-width: 200px;
            --header-height: 50px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 0.85rem;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 1rem;
            padding-top: 1.5rem;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
        }

        .file-manager-card {
            border: 2px solid var(--bscs-maroon);
            border-radius: 8px;
            background: var(--card-bg);
            padding: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .file-manager-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--bscs-maroon);
            padding-bottom: 0.8rem;
            margin-bottom: 0.8rem;
        }

        .file-manager-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--bscs-maroon);
            margin: 0;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .filter-form select, .filter-form input {
            font-size: 0.8rem;
            padding: 0.3rem;
        }

        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }

        .file-item {
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.5rem;
            text-align: center;
            position: relative;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .file-item:hover {
            transform: scale(1.05);
        }

        .file-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }

        .file-info {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .file-info strong {
            color: var(--text-primary);
        }

        .file-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            display: none;
            gap: 0.3rem;
        }

        .file-item:hover .file-actions {
            display: flex;
        }

        .action-button {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: white;
        }

        .view-button { background-color: var(--primary); }
        .view-button:hover { background-color: var(--primary-dark); }
        .edit-button { background-color: var(--warning); }
        .edit-button:hover { background-color: #b45309; }
        .delete-button { background-color: var(--danger); }
        .delete-button:hover { background-color: #b91c1c; }

        .modal-content {
            border-radius: 10px;
        }

        .modal-header {
            background: var(--bscs-maroon);
            color: white;
        }

        .photo-preview img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 6px;
            margin: 0.4rem 0.2rem 0 0;
            object-fit: cover;
        }

        .add-button {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .add-button:hover {
            background-color: var(--primary-dark);
        }

        .severity-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .severity-mild { background: var(--success); color: white; }
        .severity-moderate { background: var(--warning); color: white; }
        .severity-severe { background: var(--danger); color: white; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.4s ease forwards;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 0.8rem;
            }
            .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            .file-manager-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navigations.php'; ?>

    <div class="content">
        <main>
            <div class="container">
                <div class="file-manager-card fade-in">
                    <div class="file-manager-header">
                        <h1>Patient Image Manager</h1>
                        <div>
                            <button class="add-button" data-bs-toggle="modal" data-bs-target="#addPhotoModal">Add New Photo</button>
                        </div>
                    </div>

                    <!-- Filter Form -->
                    <form class="filter-form mb-3" method="GET">
                        <select name="patient_id" class="form-select">
                            <option value="">All Patients</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= $patient['id'] ?>" <?= $filter_patient == $patient['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($patient['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="severity" class="form-select">
                            <option value="">All Severities</option>
                            <option value="Moderate" <?= $filter_severity === 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                            <option value="Severe" <?= $filter_severity === 'Severe' ? 'selected' : '' ?>>Severe</option>
                        </select>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filter_date_from ?: '') ?>" placeholder="From Date">
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filter_date_to ?: '') ?>" placeholder="To Date">
                        <select name="sort_by" class="form-select">
                            <option value="visit_date" <?= $sort_by === 'visit_date' ? 'selected' : '' ?>>Visit Date</option>
                            <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>File Name</option>
                            <option value="size" <?= $sort_by === 'size' ? 'selected' : '' ?>>File Size</option>
                        </select>
                        <select name="sort_order" class="form-select">
                            <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        <a href="file-manager.php" class="btn btn-secondary btn-sm">Reset</a>
                    </form>

                    <!-- File Grid -->
                    <div class="file-grid">
                        <?php if (empty($images)): ?>
                            <p class="text-muted text-center">No images found.</p>
                        <?php else: ?>
                            <?php foreach ($images as $image): ?>
                                <div class="file-item" data-bs-toggle="modal" data-bs-target="#viewPhotoModal" 
                                     data-visit-id="<?= $image['visit_id'] ?>" data-photo-path="<?= $image['photo_path'] ?>">
                                    <img src="<?= '../Uploads/patient_photos/' . htmlspecialchars($image['photo_path']) ?>" alt="Patient Photo">
                                    <div class="file-actions">
                                        <button class="action-button view-button" onclick="window.open('<?= '../Uploads/patient_photos/' . htmlspecialchars($image['photo_path']) ?>', '_blank'); event.stopPropagation();">View</button>
                                        <button class="action-button edit-button" data-visit-id="<?= $image['visit_id'] ?>" data-photo-path="<?= $image['photo_path'] ?>" data-bs-toggle="modal" data-bs-target="#editPhotoModal" onclick="event.stopPropagation();">Edit</button>
                                        <button class="action-button delete-button" data-visit-id="<?= $image['visit_id'] ?>" data-photo-path="<?= $image['photo_path'] ?>" data-bs-toggle="modal" data-bs-target="#deletePhotoModal" onclick="event.stopPropagation();">Delete</button>
                                    </div>
                                    <div class="file-info">
                                        <strong><?= htmlspecialchars($image['patient_name']) ?></strong><br>
                                        <?= date('F j, Y', strtotime($image['visit_date'])) ?><br>
                                        <span class="severity-badge severity-<?= strtolower($image['severity']) ?>">
                                            <?= htmlspecialchars($image['severity']) ?>
                                        </span><br>
                                        <?= round($image['file_size'] / 1024, 2) ?> KB
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Add Photo Modal -->
            <div class="modal fade" id="addPhotoModal" tabindex="-1" aria-labelledby="addPhotoModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addPhotoModalLabel">Add New Photo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addPhotoForm" action="file-manager-process.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add">
                                <div class="mb-3">
                                    <label for="add_patient_id" class="form-label">Patient</label>
                                    <select name="patient_id" id="add_patient_id" class="form-select" required>
                                        <option value="">Select Patient</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?= $patient['id'] ?>"><?= htmlspecialchars($patient['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="add_visit_date" class="form-label">Visit Date</label>
                                    <input type="date" name="visit_date" id="add_visit_date" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="add_visit_time" class="form-label">Visit Time</label>
                                    <input type="time" name="visit_time" id="add_visit_time" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="add_severity" class="form-label">Severity</label>
                                    <select name="severity" id="add_severity" class="form-select" required>
                                        <option value="Moderate">Moderate</option>
                                        <option value="Severe">Severe</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="add_photo" class="form-label">Upload Photo (PNG only)</label>
                                    <input type="file" class="form-control" name="photo" id="add_photo" accept="image/png" required>
                                    <div id="addPhotoPreview" class="photo-preview"></div>
                                    <div class="form-text">Max 20MB, PNG format only (compressed automatically)</div>
                                </div>
                                <button type="submit" class="btn btn-primary" id="addSubmitPhoto">Add Photo</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Photo Modal -->
            <div class="modal fade" id="editPhotoModal" tabindex="-1" aria-labelledby="editPhotoModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editPhotoModalLabel">Edit Photo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editPhotoForm" action="file-manager-process.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="visit_id" id="edit_visit_id">
                                <input type="hidden" name="old_photo_path" id="edit_old_photo_path">
                                <div class="mb-3">
                                    <label for="edit_photo" class="form-label">Upload New Photo (PNG only)</label>
                                    <input type="file" class="form-control" name="photo" id="edit_photo" accept="image/png" required>
                                    <div id="editPhotoPreview" class="photo-preview"></div>
                                    <div class="form-text">Max 20MB, PNG format only (compressed automatically)</div>
                                </div>
                                <button type="submit" class="btn btn-primary" id="editSubmitPhoto">Save Changes</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Photo Modal -->
            <div class="modal fade" id="deletePhotoModal" tabindex="-1" aria-labelledby="deletePhotoModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deletePhotoModalLabel">Delete Photo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this photo? This action cannot be undone.</p>
                            <form id="deletePhotoForm" action="file-manager-process.php" method="POST">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="visit_id" id="delete_visit_id">
                                <input type="hidden" name="photo_path" id="delete_photo_path">
                                <button type="submit" class="btn btn-danger">Delete</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Photo Modal -->
            <div class="modal fade" id="viewPhotoModal" tabindex="-1" aria-labelledby="viewPhotoModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="viewPhotoModalLabel">View Photo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <img id="viewPhotoImage" src="" alt="Patient Photo" class="img-fluid">
                            <div id="viewPhotoDetails" class="mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="clinic-footer fade-in">
            <div class="container">
                <p class="mb-0">Clinic Management System © 2025 ICCB. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Compress image function
        function compressImage(file, maxSize = 1024, quality = 0.7) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.src = URL.createObjectURL(file);
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    if (width > maxSize || height > maxSize) {
                        const ratio = Math.min(maxSize / width, maxSize / height);
                        width *= ratio;
                        height *= ratio;
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(
                        (blob) => {
                            if (blob) {
                                const compressedFile = new File([blob], file.name, {
                                    type: 'image/png',
                                    lastModified: Date.now()
                                });
                                resolve(compressedFile);
                            } else {
                                reject(new Error('Compression failed'));
                            }
                        },
                        'image/png',
                        quality
                    );
                };
                img.onerror = () => reject(new Error('Failed to load image'));
            });
        }

        $(document).ready(function() {
            // Photo preview and compression for add/edit
            ['add_photo', 'edit_photo'].forEach(inputId => {
                $(`#${inputId}`).on('change', async function(e) {
                    const file = e.target.files[0];
                    const preview = $(`#${inputId === 'add_photo' ? 'addPhotoPreview' : 'editPhotoPreview'}`);
                    preview.empty();

                    if (!file) return;

                    console.log('[SSCMS File Manager] File selected:', file.name, file.size, file.type);
                    if (!file.type.match('image/png')) {
                        alert(`Only PNG files are allowed for ${file.name}.`);
                        $(this).val('');
                        preview.empty();
                        return;
                    }
                    if (file.size > 20000000) {
                        alert(`File size exceeds 20MB limit for ${file.name}.`);
                        $(this).val('');
                        preview.empty();
                        return;
                    }

                    try {
                        const compressedFile = await compressImage(file);
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            preview.append(`<img src="${e.target.result}" alt="Photo Preview" title="${compressedFile.name} (${(compressedFile.size / 1024).toFixed(2)} KB)">`);
                        };
                        reader.readAsDataURL(compressedFile);

                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(compressedFile);
                        this.files = dataTransfer.files;
                    } catch (error) {
                        console.error('[SSCMS File Manager] Compression error:', error);
                        alert(`Failed to compress ${file.name}.`);
                        $(this).val('');
                        preview.empty();
                    }
                });
            });

            // Handle add/edit form submission
            ['addPhotoForm', 'editPhotoForm'].forEach(formId => {
                $(`#${formId}`).on('submit', function(e) {
                    e.preventDefault();
                    const file = $(`#${formId === 'addPhotoForm' ? 'add_photo' : 'edit_photo'}`)[0].files[0];
                    if (!file) {
                        alert('Please select a photo to upload.');
                        return;
                    }
                    const formData = new FormData(this);
                    $(`#${formId === 'addPhotoForm' ? 'addSubmitPhoto' : 'editSubmitPhoto'}`).prop('disabled', true);
                    $.ajax({
                        url: 'file-manager-process.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            const res = JSON.parse(response);
                            if (res.success) {
                                alert('Photo operation successful');
                                location.reload();
                            } else {
                                alert(res.error);
                            }
                            $(`#${formId === 'addPhotoForm' ? 'addSubmitPhoto' : 'editSubmitPhoto'}`).prop('disabled', false);
                        },
                        error: function(xhr, status, error) {
                            console.error('[SSCMS File Manager] Form submission error:', error, xhr.responseText);
                            alert('Failed to process photo');
                            $(`#${formId === 'addPhotoForm' ? 'addSubmitPhoto' : 'editSubmitPhoto'}`).prop('disabled', false);
                        }
                    });
                });
            });

            // Handle delete form submission
            $('#deletePhotoForm').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                $.ajax({
                    url: 'file-manager-process.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        const res = JSON.parse(response);
                        if (res.success) {
                            alert('Photo deleted successfully');
                            location.reload();
                        } else {
                            alert(res.error);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[SSCMS File Manager] Delete error:', error, xhr.responseText);
                        alert('Failed to delete photo');
                    }
                });
            });

            // Populate edit/delete modal fields
            $('.edit-button, .delete-button').on('click', function() {
                const visitId = $(this).data('visit-id');
                const photoPath = $(this).data('photo-path');
                if ($(this).hasClass('edit-button')) {
                    $('#edit_visit_id').val(visitId);
                    $('#edit_old_photo_path').val(photoPath);
                    $('#editPhotoPreview').empty();
                    $('#edit_photo').val('');
                } else {
                    $('#delete_visit_id').val(visitId);
                    $('#delete_photo_path').val(photoPath);
                }
            });

            // Populate view modal
            $('.file-item').on('click', function() {
                const visitId = $(this).data('visit-id');
                const photoPath = $(this).data('photo-path');
                const image = $(this).find('img').attr('src');
                const patientName = $(this).find('.file-info strong').text();
                const visitDate = $(this).find('.file-info').contents().filter(function() { return this.nodeType === 3; })[0].nodeValue.trim();
                const severity = $(this).find('.severity-badge').text();
                const fileSize = $(this).find('.file-info').contents().filter(function() { return this.nodeType === 3; })[2].nodeValue.trim();

                $('#viewPhotoImage').attr('src', image);
                $('#viewPhotoDetails').html(`
                    <p><strong>Patient:</strong> ${patientName}</p>
                    <p><strong>Visit Date:</strong> ${visitDate}</p>
                    <p><strong>Severity:</strong> ${severity}</p>
                    <p><strong>File Size:</strong> ${fileSize}</p>
                `);
            });
        });
    </script>
</body>
</html>