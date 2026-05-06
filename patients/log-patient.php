<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_category'])) {
    error_log("[SSCMS Dashboard] Unauthorized access: no session");
    header('Location: /login.php');
    exit;
}

// Get patient ID and search parameters
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$programFilter = filter_input(INPUT_GET, 'program', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$yearFilter = filter_input(INPUT_GET, 'year', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

if (!$patient_id) {
    $_SESSION['error_message'] = 'No patient selected.';
    header('Location: log-new-patient.php' . (isset($_GET['search']) ? '?search=' . urlencode($searchTerm) : ''));
    exit;
}

// Fetch patient details
$stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, gender, grade_year, program_section, guardian_contact FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    $_SESSION['error_message'] = 'Patient not found.';
    header('Location: log-new-patient.php' . (isset($_GET['search']) ? '?search=' . urlencode($searchTerm) : ''));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Clinic Management System - Log Patient Visit">
    <meta name="author" content="ICCB">
    <title>Log Visit for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?> - Clinic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <?php include '../includes/sscmslogo.php'; ?>
    <style>
        :root {
            --primary: #0f73ba;
            --primary-dark: #0d5a94;
            --secondary: #2c7be5;
            --secondary-dark: #1a5fbe;
            --background: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --success: #059669;
            --success-dark: #047857;
            --danger: #dc2626;
            --danger-dark: #b91c1c;
            --warning: #d97706;
            --warning-dark: #b45309;
            --sidebar-width: 220px;
            --sidebar-collapsed-width: 50px;
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
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(15,115,186,0.08), 0 1px 4px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            transition: box-shadow 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 8px 24px rgba(15,115,186,0.13), 0 3px 8px rgba(0,0,0,0.06);
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-bottom: none;
            padding: 0.85rem 1.25rem;
            color: white;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
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
        .photo-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .photo-preview .photo-container {
            position: relative;
            display: inline-block;
        }
        .photo-preview img {
            max-width: 120px;
            max-height: 120px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--border);
        }
        .photo-preview .remove-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            line-height: 20px;
            text-align: center;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .photo-preview .remove-btn:hover {
            background-color: var(--danger-dark);
        }
        .patient-info-card {
            background: linear-gradient(45deg, rgba(15,115,186,0.08) 0%, rgba(255,255,255,0.6) 100%);
            border-left: 4px solid var(--primary);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        .form-control, .form-select {
            border: 1px solid var(--border);
            background-color: #f8fafc;
            color: var(--text-primary);
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15,115,186,0.12);
            background-color: #ffffff;
        }
        h6.fw-semibold {
            color: var(--primary);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.4rem;
            margin-bottom: 0.85rem !important;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in      { animation: fadeInUp 0.4s ease both; }
        .anim-delay-1 { animation: fadeInUp 0.4s ease 0.10s both; }
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
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-user-injured me-1"></i> Log Visit for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
                        </h5>
                        <a href="log-new-patient.php?search=<?= urlencode($searchTerm) ?>&program=<?= urlencode($programFilter) ?>&year=<?= urlencode($yearFilter) ?>" class="btn btn-sm" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.5);">
                            <i class="fas fa-arrow-left me-1"></i> Back to Search
                        </a>
                    </div>
                    <div class="card-body p-3">
                        <div class="patient-info-card mb-4 anim-delay-1">
                            <div class="d-flex flex-column">
                                <span><strong>Name:</strong> <?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] : '')) ?></span>
                                <span><strong>Grade/Year:</strong> <?= htmlspecialchars($patient['grade_year'] ?: 'N/A') ?></span>
                                <span><strong>Program/Section:</strong> <?= htmlspecialchars($patient['program_section'] ?: 'N/A') ?></span>
                                <span><strong>Guardian Contact:</strong> <?= htmlspecialchars($patient['guardian_contact'] ?: 'N/A') ?></span>
                            </div>
                        </div>
                        <form id="logPatientForm" action="log-new-patient.php?search=<?= urlencode($searchTerm) ?>&program=<?= urlencode($programFilter) ?>&year=<?= urlencode($yearFilter) ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">

                            <!-- Visit Information Section -->
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3">Visit Information</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="reason" class="form-label">Reason for Visit <span class="text-danger">*</span></label>
                                            <select name="reason" id="reason" class="form-select" onchange="checkOtherReason()" required>
                                                <option value="">Select Reason</option>
                                                <option value="Headache">Headache</option>
                                                <option value="Stomach Ache">Stomach Ache</option>
                                                <option value="Fever">Fever</option>
                                                <option value="Cold/Flu">Cold/Flu</option>
                                                <option value="Cough">Cough</option>
                                                <option value="Dizziness">Dizziness</option>
                                                <option value="Nausea">Nausea</option>
                                                <option value="Vomiting">Vomiting</option>
                                                <option value="Diarrhea">Diarrhea</option>
                                                <option value="Allergy">Allergy</option>
                                                <option value="Skin Rash">Skin Rash</option>
                                                <option value="Injury (Wound/Bruise)">Injury (Wound/Bruise)</option>
                                                <option value="Sprain/Fracture">Sprain/Fracture</option>
                                                <option value="High Blood Pressure">High Blood Pressure</option>
                                                <option value="Low Blood Pressure">Low Blood Pressure</option>
                                                <option value="Menstrual Pain">Menstrual Pain</option>
                                                <option value="Eye Irritation">Eye Irritation</option>
                                                <option value="Ear Pain">Ear Pain</option>
                                                <option value="Dental Pain">Dental Pain</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <small class="form-text text-muted">Select the reason for the patient's visit</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6" id="otherReasonDiv" style="display: none;">
                                        <div class="form-group">
                                            <label for="other_reason" class="form-label">Specify Other Reason</label>
                                            <input type="text" class="form-control" name="other_reason" id="other_reason">
                                            <small class="form-text text-muted">Enter details for other reason</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="severity" class="form-label">Severity <span class="text-danger">*</span></label>
                                            <select name="severity" id="severity" class="form-select" required>
                                                <option value="">Select Severity</option>
                                                <option value="Mild">Mild</option>
                                                <option value="Moderate">Moderate</option>
                                                <option value="Severe">Severe</option>
                                            </select>
                                            <small class="form-text text-muted">Select the severity of the condition</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Media Upload Section -->
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3">Media Upload</h6>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="visit_photos" class="form-label">Upload Photos (Optional, PNG only, Max 10)</label>
                                            <input type="file" class="form-control" name="visit_photos[]" id="visit_photos" accept="image/png" multiple>
                                            <div id="photoPreview" class="photo-preview mt-2"></div>
                                            <small class="form-text text-muted">Upload up to 10 PNG photos related to the visit (max 20MB each, compressed automatically)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Medicine Information Section -->
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3">Medicine Information</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Did the patient take medicine? <span class="text-danger">*</span></label>
                                            <div class="d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="took_medicine" id="took_medicine_no" value="No" onclick="toggleMedicineOptions(false)" checked>
                                                    <label class="form-check-label" for="took_medicine_no">No</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="took_medicine" id="took_medicine_yes" value="Yes" onclick="toggleMedicineOptions(true)">
                                                    <label class="form-check-label" for="took_medicine_yes">Yes</label>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Indicate if the patient took any medicine</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6" id="medicineOptions" style="display: none;">
                                        <div class="form-group mb-3">
                                            <label for="medicine" class="form-label">Select Medicine <span class="text-danger">*</span></label>
                                            <select name="medicine" id="medicine" class="form-select" onchange="updateMedicineQuantity()">
                                                <option value="">Select Medicine</option>
                                                <?php
                                                $medicines = $conn->query("SELECT id, name, quantity FROM medicines WHERE quantity > 0 ORDER BY name ASC");
                                                if ($medicines->rowCount() == 0) {
                                                    echo '<option value="" disabled>No medicines available</option>';
                                                } else {
                                                    while ($med = $medicines->fetch(PDO::FETCH_ASSOC)) {
                                                        echo '<option value="' . htmlspecialchars($med['name']) . '" data-quantity="' . $med['quantity'] . '" data-id="' . $med['id'] . '">' . htmlspecialchars($med['name']) . ' (Available: ' . $med['quantity'] . ')</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted">Select the medicine taken</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="medicine_quantity" class="form-label">Quantity Taken <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" name="medicine_quantity" id="medicine_quantity" min="1" placeholder="Enter quantity">
                                            <div id="quantityWarning" class="text-danger small mt-1" style="display: none;">Quantity exceeds available stock!</div>
                                            <small class="form-text text-muted">Enter the quantity of medicine taken</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Visit Date & Time Section -->
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3">Visit Date & Time</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="visit_time" class="form-label">Visit Time <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="visit_time" value="<?= date('h:i A') ?>" readonly>
                                            <small class="form-text text-muted">Current visit time (Asia/Manila)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="visit_date" class="form-label">Visit Date <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" value="<?= date('l, F j, Y') ?>" readonly>
                                            <input type="hidden" name="visit_date" value="<?= date('Y-m-d') ?>">
                                            <small class="form-text text-muted">Current visit date (Asia/Manila)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="log-new-patient.php?search=<?= urlencode($searchTerm) ?>&program=<?= urlencode($programFilter) ?>&year=<?= urlencode($yearFilter) ?>" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary" id="submitPatient">
                                    <i class="fas fa-save me-1"></i> Log Visit
                                    <span id="logSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Compress image function
        function compressImage(file, maxSize = 1024, quality = 0.7) {
            return new Promise((resolve, reject) => {
                if (!file.type.match('image/png')) {
                    reject(new Error('Only PNG files are allowed.'));
                    return;
                }
                if (file.size > 20000000) {
                    reject(new Error('File size exceeds 20MB limit.'));
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.src = e.target.result;
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
                };
                reader.onerror = () => reject(new Error('Failed to read file'));
                reader.readAsDataURL(file);
            });
        }

        $(document).ready(function() {
            console.log('[SSCMS Log Patient] Initialized log-patient.php');

            // Maintain a list of compressed files
            let compressedFiles = [];

            // Photo preview and compression
            $('#visit_photos').on('change', async function(e) {
                const files = Array.from(e.target.files);
                const preview = $('#photoPreview');
                preview.empty();
                compressedFiles = [];

                if (files.length > 10) {
                    alert('You can upload a maximum of 10 photos.');
                    $(this).val('');
                    return;
                }

                for (const file of files) {
                    console.log('[SSCMS Log Patient] File selected:', file.name, file.size, file.type);
                    try {
                        const compressedFile = await compressImage(file);
                        compressedFiles.push(compressedFile);
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const photoContainer = $('<div class="photo-container"></div>');
                            const img = $(`<img src="${e.target.result}" alt="Photo Preview" title="${compressedFile.name} (${(compressedFile.size / 1024).toFixed(2)} KB)">`);
                            const removeBtn = $('<button class="remove-btn" type="button">x</button>');
                            removeBtn.on('click', function() {
                                const index = compressedFiles.indexOf(compressedFile);
                                if (index !== -1) {
                                    compressedFiles.splice(index, 1);
                                    updateFileInput();
                                    $(this).parent().remove();
                                    console.log('[SSCMS Log Patient] Removed file:', compressedFile.name);
                                }
                            });
                            photoContainer.append(img).append(removeBtn);
                            preview.append(photoContainer);
                        };
                        reader.readAsDataURL(compressedFile);
                    } catch (error) {
                        console.error('[SSCMS Log Patient] Compression error:', error.message);
                        alert(`Failed to process ${file.name}: ${error.message}`);
                        $(this).val('');
                        preview.empty();
                        compressedFiles = [];
                        return;
                    }
                }

                updateFileInput();
            });

            // Update file input with compressed files
            function updateFileInput() {
                const fileInput = document.getElementById('visit_photos');
                const dataTransfer = new DataTransfer();
                compressedFiles.forEach(file => dataTransfer.items.add(file));
                fileInput.files = dataTransfer.files;
                console.log('[SSCMS Log Patient] Updated file input with', compressedFiles.length, 'files');
            }

            // Form validation
            $('#logPatientForm').on('submit', function(e) {
                const reason = $('#reason').val();
                const severity = $('#severity').val();
                const tookMedicine = $('input[name="took_medicine"]:checked').val();
                const medicine = $('#medicine').val();
                const quantity = parseInt($('#medicine_quantity').val()) || 0;

                console.log('[SSCMS Log Patient] Form submitting:', {
                    reason,
                    severity,
                    tookMedicine,
                    medicine,
                    quantity
                });

                if (!reason) {
                    e.preventDefault();
                    alert('Please select a reason for the visit.');
                    $('#reason').addClass('is-invalid').focus();
                    return;
                }
                $('#reason').removeClass('is-invalid');

                if (reason === 'Other' && !$('#other_reason').val().trim()) {
                    e.preventDefault();
                    alert('Please specify the other reason.');
                    $('#other_reason').addClass('is-invalid').focus();
                    return;
                }
                $('#other_reason').removeClass('is-invalid');

                if (!severity) {
                    e.preventDefault();
                    alert('Please select a severity level.');
                    $('#severity').addClass('is-invalid').focus();
                    return;
                }
                $('#severity').removeClass('is-invalid');

                if (tookMedicine === 'Yes') {
                    if (!medicine) {
                        e.preventDefault();
                        alert('Please select a medicine.');
                        $('#medicine').addClass('is-invalid').focus();
                        return;
                    }
                    $('#medicine').removeClass('is-invalid');

                    if (!quantity || quantity <= 0) {
                        e.preventDefault();
                        alert('Please enter a valid quantity greater than 0.');
                        $('#medicine_quantity').addClass('is-invalid').focus();
                        return;
                    }
                    $('#medicine_quantity').removeClass('is-invalid');

                    const availableQuantity = parseInt($('#medicine option:selected').data('quantity')) || 0;
                    if (quantity > availableQuantity) {
                        e.preventDefault();
                        alert('Quantity exceeds available stock in inventory!');
                        $('#medicine_quantity').addClass('is-invalid').focus();
                        $('#quantityWarning').show();
                        return;
                    }
                    $('#quantityWarning').hide();
                }

                $('#logSpinner').removeClass('d-none');
                $('#submitPatient').prop('disabled', true);
            });

            // Dynamically set required attributes
            $('input[name="took_medicine"]').on('change', function() {
                const tookMedicine = $(this).val();
                console.log('[SSCMS Log Patient] Medicine radio changed:', tookMedicine);
                if (tookMedicine === 'Yes') {
                    $('#medicine').prop('required', true);
                    $('#medicine_quantity').prop('required', true);
                } else {
                    $('#medicine').prop('required', false);
                    $('#medicine_quantity').prop('required', false);
                }
            });

            function checkOtherReason() {
                console.log('[SSCMS Log Patient] Checking other reason');
                const reasonSelect = document.getElementById('reason');
                const otherReasonDiv = document.getElementById('otherReasonDiv');
                const otherReasonInput = document.getElementById('other_reason');

                if (reasonSelect.value === 'Other') {
                    otherReasonDiv.style.display = 'block';
                    otherReasonInput.required = true;
                } else {
                    otherReasonDiv.style.display = 'none';
                    otherReasonInput.required = false;
                    otherReasonInput.value = '';
                    otherReasonInput.classList.remove('is-invalid');
                }
            }

            function toggleMedicineOptions(show) {
                console.log('[SSCMS Log Patient] Toggling medicine options:', show);
                const medicineOptions = document.getElementById('medicineOptions');
                const medicineSelect = document.getElementById('medicine');
                const quantityInput = document.getElementById('medicine_quantity');
                const quantityWarning = document.getElementById('quantityWarning');

                if (show) {
                    medicineOptions.style.display = 'block';
                    medicineSelect.required = true;
                    quantityInput.required = true;
                    if (medicineSelect.value) {
                        updateMedicineQuantity();
                    }
                } else {
                    medicineOptions.style.display = 'none';
                    medicineSelect.required = false;
                    quantityInput.required = false;
                    medicineSelect.value = '';
                    quantityInput.value = '';
                    quantityWarning.style.display = 'none';
                    medicineSelect.classList.remove('is-invalid');
                    quantityInput.classList.remove('is-invalid');
                }
            }

            function updateMedicineQuantity() {
                console.log('[SSCMS Log Patient] Updating medicine quantity');
                const medicineSelect = document.getElementById('medicine');
                const quantityInput = document.getElementById('medicine_quantity');
                const quantityWarning = document.getElementById('quantityWarning');

                const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
                const availableQuantity = parseInt(selectedOption.getAttribute('data-quantity')) || 0;

                if (medicineSelect.value) {
                    quantityInput.max = availableQuantity;
                    quantityInput.value = availableQuantity > 0 ? 1 : 0;
                    quantityInput.classList.remove('is-invalid');
                    validateQuantity();
                }
            }

            function validateQuantity() {
                console.log('[SSCMS Log Patient] Validating quantity');
                const medicineSelect = document.getElementById('medicine');
                const quantityInput = document.getElementById('medicine_quantity');
                const quantityWarning = document.getElementById('quantityWarning');

                const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
                const availableQuantity = parseInt(selectedOption.getAttribute('data-quantity')) || 0;
                const enteredQuantity = parseInt(quantityInput.value) || 0;

                if (enteredQuantity > availableQuantity || enteredQuantity <= 0) {
                    quantityWarning.style.display = 'block';
                    quantityInput.value = availableQuantity > 0 ? availableQuantity : 0;
                    quantityInput.classList.remove('is-invalid');
                } else {
                    quantityWarning.style.display = 'none';
                    quantityInput.classList.remove('is-invalid');
                }
            }
        });
    </script>
</body>
</html>