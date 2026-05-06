<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

function sendResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

try {
    if (!isset($_POST['action'])) {
        sendResponse(false, 'No action specified');
    }

    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
    $validCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Faculty and Staff', 'Alumni'];
    $academicCategories = ['Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Faculty and Staff'];

    switch ($action) {
        case 'add':
            // Sanitize input
            $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_SPECIAL_CHARS);
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
            $grade_year = filter_input(INPUT_POST, 'grade_year', FILTER_SANITIZE_SPECIAL_CHARS);
            $program_section = filter_input(INPUT_POST, 'program_section', FILTER_SANITIZE_SPECIAL_CHARS);
            $guardian_contact = filter_input(INPUT_POST, 'guardian_contact', FILTER_SANITIZE_SPECIAL_CHARS);
            $guardian_name = filter_input(INPUT_POST, 'guardian_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $guardian_facebook = filter_input(INPUT_POST, 'guardian_facebook', FILTER_SANITIZE_SPECIAL_CHARS);
            $pediatrician_name = filter_input(INPUT_POST, 'pediatrician_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $pediatrician_contact = filter_input(INPUT_POST, 'pediatrician_contact', FILTER_SANITIZE_SPECIAL_CHARS);
            $allergies = filter_input(INPUT_POST, 'allergies', FILTER_SANITIZE_SPECIAL_CHARS);
            $medical_conditions = filter_input(INPUT_POST, 'medical_conditions', FILTER_SANITIZE_SPECIAL_CHARS);
            $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS);
            $other_contact = filter_input(INPUT_POST, 'other_contact', FILTER_SANITIZE_SPECIAL_CHARS);
            $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_SPECIAL_CHARS);
            $emergency_contact_name = filter_input(INPUT_POST, 'emergency_contact_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $emergency_contact_number = filter_input(INPUT_POST, 'emergency_contact_number', FILTER_SANITIZE_SPECIAL_CHARS);

            // Validate required fields
            if (!$last_name || !$first_name || !$gender || !$category) {
                sendResponse(false, 'Required fields are missing');
            }

            if (!in_array($category, $validCategories)) {
                sendResponse(false, 'Invalid category');
            }

            if (!in_array($gender, ['Male', 'Female', 'Other'])) {
                sendResponse(false, 'Invalid gender');
            }

            // Validate grade/year and program/section for academic categories
            if (in_array($category, $academicCategories)) {
                if ($grade_year) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM grade_years WHERE name = ? AND category = ?");
                    $stmt->execute([$grade_year, $category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid grade/year for the selected category');
                    }
                }
                if ($program_section) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM program_sections WHERE name = ? AND category = ?");
                    $stmt->execute([$program_section, $category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid program/section for the selected category');
                    }
                }
            } elseif ($category === 'Alumni') {
                $grade_year = 'Alumni';
                $program_section = 'Alumni';
            }

            // Insert into database
            $stmt = $conn->prepare("
                INSERT INTO patients (
                    last_name, first_name, middle_name, gender, category, grade_year, program_section, 
                    guardian_contact, guardian_name, guardian_facebook, pediatrician_name, pediatrician_contact, 
                    allergies, medical_conditions, notes, other_contact, address, emergency_contact_name, emergency_contact_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $last_name,
                $first_name,
                $middle_name ?: null,
                $gender,
                $category,
                $grade_year,
                $program_section,
                $guardian_contact,
                $guardian_name,
                $guardian_facebook,
                $pediatrician_name,
                $pediatrician_contact,
                $allergies,
                $medical_conditions,
                $notes,
                $other_contact,
                $address,
                $emergency_contact_name,
                $emergency_contact_number
            ]);

            $_SESSION['success_message'] = 'Patient added successfully';
            sendResponse(true, 'Patient added successfully');
            break;

        case 'update':
            // Sanitize input
            $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
            $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_SPECIAL_CHARS);
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
            $grade_year = filter_input(INPUT_POST, 'grade_year', FILTER_SANITIZE_SPECIAL_CHARS);
            $program_section = filter_input(INPUT_POST, 'program_section', FILTER_SANITIZE_SPECIAL_CHARS);
            $guardian_contact = filter_input(INPUT_POST, 'guardian_contact', FILTER_SANITIZE_SPECIAL_CHARS);
            $guardian_name = filter_input(INPUT_POST, 'guardian_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $guardian_facebook = filter_input(INPUT_POST, 'guardian_facebook', FILTER_SANITIZE_SPECIAL_CHARS);
            $pediatrician_name = filter_input(INPUT_POST, 'pediatrician_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $pediatrician_contact = filter_input(INPUT_POST, 'pediatrician_contact', FILTER_SANITIZE_SPECIAL_CHARS);
            $allergies = filter_input(INPUT_POST, 'allergies', FILTER_SANITIZE_SPECIAL_CHARS);
            $medical_conditions = filter_input(INPUT_POST, 'medical_conditions', FILTER_SANITIZE_SPECIAL_CHARS);
            $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS);
            $other_contact = filter_input(INPUT_POST, 'other_contact', FILTER_SANITIZE_SPECIAL_CHARS);
            $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_SPECIAL_CHARS);
            $emergency_contact_name = filter_input(INPUT_POST, 'emergency_contact_name', FILTER_SANITIZE_SPECIAL_CHARS);
            $emergency_contact_number = filter_input(INPUT_POST, 'emergency_contact_number', FILTER_SANITIZE_SPECIAL_CHARS);

            // Validate required fields
            if (!$patient_id || !$last_name || !$first_name || !$gender || !$category) {
                sendResponse(false, 'Required fields are missing');
            }

            if (!in_array($category, $validCategories)) {
                sendResponse(false, 'Invalid category');
            }

            if (!in_array($gender, ['Male', 'Female', 'Other'])) {
                sendResponse(false, 'Invalid gender');
            }

            // Validate grade/year and program/section for academic categories
            if (in_array($category, $academicCategories)) {
                if ($grade_year) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM grade_years WHERE name = ? AND category = ?");
                    $stmt->execute([$grade_year, $category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid grade/year for the selected category');
                    }
                }
                if ($program_section) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM program_sections WHERE name = ? AND category = ?");
                    $stmt->execute([$program_section, $category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid program/section for the selected category');
                    }
                }
            } elseif ($category === 'Alumni') {
                $grade_year = 'Alumni';
                $program_section = 'Alumni';
            }

            // Update database
            $stmt = $conn->prepare("
                UPDATE patients
                SET last_name = ?, first_name = ?, middle_name = ?, gender = ?, category = ?, grade_year = ?, program_section = ?,
                    guardian_contact = ?, guardian_name = ?, guardian_facebook = ?, pediatrician_name = ?, pediatrician_contact = ?,
                    allergies = ?, medical_conditions = ?, notes = ?, other_contact = ?, address = ?, emergency_contact_name = ?, emergency_contact_number = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $last_name,
                $first_name,
                $middle_name ?: null,
                $gender,
                $category,
                $grade_year,
                $program_section,
                $guardian_contact,
                $guardian_name,
                $guardian_facebook,
                $pediatrician_name,
                $pediatrician_contact,
                $allergies,
                $medical_conditions,
                $notes,
                $other_contact,
                $address,
                $emergency_contact_name,
                $emergency_contact_number,
                $patient_id
            ]);

            if ($stmt->rowCount() === 0) {
                sendResponse(false, 'No changes made or patient not found');
            }

            $_SESSION['success_message'] = 'Patient updated successfully';
            sendResponse(true, 'Patient updated successfully');
            break;

        case 'move':
            // Sanitize input
            $patient_ids = filter_input(INPUT_POST, 'patient_ids', FILTER_SANITIZE_SPECIAL_CHARS);
            $new_category = filter_input(INPUT_POST, 'new_category', FILTER_SANITIZE_SPECIAL_CHARS);
            $new_grade_year = filter_input(INPUT_POST, 'new_grade_year', FILTER_SANITIZE_SPECIAL_CHARS);
            $new_program_section = filter_input(INPUT_POST, 'new_program_section', FILTER_SANITIZE_SPECIAL_CHARS);

            // Validate required fields
            if (!$patient_ids || !$new_category) {
                sendResponse(false, 'Patient IDs and new category are required');
            }

            if (!in_array($new_category, $validCategories)) {
                sendResponse(false, 'Invalid new category');
            }

            $patient_ids_array = array_filter(array_map('intval', explode(',', $patient_ids)));
            if (empty($patient_ids_array)) {
                sendResponse(false, 'No valid patient IDs provided');
            }

            // Validate grade/year and program/section for academic categories
            if (in_array($new_category, $academicCategories)) {
                if ($new_grade_year) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM grade_years WHERE name = ? AND category = ?");
                    $stmt->execute([$new_grade_year, $new_category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid new grade/year for the selected category');
                    }
                }
                if ($new_program_section) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM program_sections WHERE name = ? AND category = ?");
                    $stmt->execute([$new_program_section, $new_category]);
                    if ($stmt->fetchColumn() == 0) {
                        sendResponse(false, 'Invalid new program/section for the selected category');
                    }
                }
            } elseif ($new_category === 'Alumni') {
                $new_grade_year = 'Alumni';
                $new_program_section = 'Alumni';
            }

            // Update database
            $placeholders = implode(',', array_fill(0, count($patient_ids_array), '?'));
            $stmt = $conn->prepare("
                UPDATE patients
                SET category = ?, grade_year = ?, program_section = ?
                WHERE id IN ($placeholders)
            ");
            $params = array_merge([$new_category, $new_grade_year, $new_program_section], $patient_ids_array);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                sendResponse(false, 'No patients were moved');
            }

            $_SESSION['success_message'] = 'Patients moved successfully';
            sendResponse(true, 'Patients moved successfully');
            break;

        case 'delete':
            // Sanitize input
            $patient_ids = filter_input(INPUT_POST, 'patient_ids', FILTER_SANITIZE_SPECIAL_CHARS);

            // Validate input
            if (!$patient_ids) {
                sendResponse(false, 'No patient IDs provided');
            }

            $patient_ids_array = array_filter(array_map('intval', explode(',', $patient_ids)));
            if (empty($patient_ids_array)) {
                sendResponse(false, 'No valid patient IDs provided');
            }

            // Delete from database
            $placeholders = implode(',', array_fill(0, count($patient_ids_array), '?'));
            $stmt = $conn->prepare("DELETE FROM patients WHERE id IN ($placeholders)");
            $stmt->execute($patient_ids_array);

            if ($stmt->rowCount() === 0) {
                sendResponse(false, 'No patients were deleted');
            }

            $_SESSION['success_message'] = 'Patients deleted successfully';
            sendResponse(true, 'Patients deleted successfully');
            break;

        case 'import':
            // Validate input
            $patients = json_decode($_POST['patients'] ?? '', true);
            if (!$patients || !is_array($patients)) {
                sendResponse(false, 'Invalid or empty patient data');
            }

            // Prepare the insert statement
            $stmt = $conn->prepare("
                INSERT INTO patients (
                    last_name, first_name, middle_name, gender, category, grade_year, program_section, 
                    guardian_contact, guardian_name, guardian_facebook, pediatrician_name, pediatrician_contact, 
                    allergies, medical_conditions, notes, other_contact, address, emergency_contact_name, emergency_contact_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $validGenders = ['Male', 'Female', 'Other'];
            $insertedCount = 0;

            foreach ($patients as $index => $patient) {
                // Sanitize patient data
                $last_name = filter_var($patient['last_name'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
                $first_name = filter_var($patient['first_name'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
                $middle_name = filter_var($patient['middle_name'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $gender = filter_var($patient['gender'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
                $category = filter_var($patient['category'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
                $grade_year = filter_var($patient['grade_year'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $program_section = filter_var($patient['program_section'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $guardian_contact = filter_var($patient['guardian_contact'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $guardian_name = filter_var($patient['guardian_name'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $guardian_facebook = filter_var($patient['guardian_facebook'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $pediatrician_name = filter_var($patient['pediatrician_name'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $pediatrician_contact = filter_var($patient['pediatrician_contact'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $allergies = filter_var($patient['allergies'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $medical_conditions = filter_var($patient['medical_conditions'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $notes = filter_var($patient['notes'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $other_contact = filter_var($patient['other_contact'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $address = filter_var($patient['address'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $emergency_contact_name = filter_var($patient['emergency_contact_name'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
                $emergency_contact_number = filter_var($patient['emergency_contact_number'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);

                // Validate required fields
                if (!$last_name || !$first_name || !$gender || !$category) {
                    sendResponse(false, "Missing required fields in row " . ($index + 2));
                }

                if (!in_array($category, $validCategories)) {
                    sendResponse(false, "Invalid category in row " . ($index + 2) . ": $category");
                }

                if (!in_array($gender, $validGenders)) {
                    sendResponse(false, "Invalid gender in row " . ($index + 2) . ": $gender");
                }

                // Validate grade/year and program/section for academic categories
                if (in_array($category, $academicCategories)) {
                    if ($grade_year) {
                        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM grade_years WHERE name = ? AND category = ?");
                        $stmt_check->execute([$grade_year, $category]);
                        if ($stmt_check->fetchColumn() == 0) {
                            sendResponse(false, "Invalid grade/year in row " . ($index + 2) . ": $grade_year");
                        }
                    }
                    if ($program_section) {
                        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM program_sections WHERE name = ? AND category = ?");
                        $stmt_check->execute([$program_section, $category]);
                        if ($stmt_check->fetchColumn() == 0) {
                            sendResponse(false, "Invalid program/section in row " . ($index + 2) . ": $program_section");
                        }
                    }
                } elseif ($category === 'Alumni') {
                    $grade_year = 'Alumni';
                    $program_section = 'Alumni';
                }

                // Insert patient
                $stmt->execute([
                    $last_name,
                    $first_name,
                    $middle_name ?: null,
                    $gender,
                    $category,
                    $grade_year ?: null,
                    $program_section ?: null,
                    $guardian_contact ?: null,
                    $guardian_name ?: null,
                    $guardian_facebook ?: null,
                    $pediatrician_name ?: null,
                    $pediatrician_contact ?: null,
                    $allergies ?: null,
                    $medical_conditions ?: null,
                    $notes ?: null,
                    $other_contact ?: null,
                    $address ?: null,
                    $emergency_contact_name ?: null,
                    $emergency_contact_number ?: null
                ]);

                $insertedCount++;
            }

            $_SESSION['success_message'] = "$insertedCount patient(s) imported successfully";
            sendResponse(true, "$insertedCount patient(s) imported successfully");
            break;

        default:
            sendResponse(false, 'Invalid action');
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}
?>