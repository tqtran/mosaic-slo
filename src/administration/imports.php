<?php
declare(strict_types=1);

/**
 * Centralized Data Import Management
 * 
 * Handles all CSV/data imports for the system.
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

$successMessage = '';
$errorMessage = '';
$importStats = [];
$activeTab = 'islo'; // Default to ISLO tab

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    $importType = $_POST['import_type'] ?? '';
    // Normalize import type to match tab IDs (replace underscores with hyphens)
    $activeTab = str_replace('_', '-', $importType);
    
    try {
        switch ($importType) {
            case 'islo':
                if (isset($_FILES['islo_upload']) && $_FILES['islo_upload']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['islo_upload']['tmp_name'];
                    $handle = fopen($tmpName, 'r');
                    
                    if ($handle !== false) {
                        $headers = fgetcsv($handle);
                        $imported = 0;
                        $skipped = 0;
                        
                        $selectedTermFk = getSelectedTermFk();
                        if (!$selectedTermFk) {
                            $errorMessage = 'No term selected. Please select a term first.';
                            fclose($handle);
                            break;
                        }
                        
                        $sequenceNum = 1;
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 1 && !empty(trim($row[0]))) {
                                $outcomeDescription = trim($row[0]);
                                $outcomeCode = 'ISLO-' . $sequenceNum;
                                
                                if (!empty($outcomeDescription)) {
                                    $result = $db->query(
                                        "SELECT institutional_outcomes_pk FROM {$dbPrefix}institutional_outcomes 
                                         WHERE outcome_code = ? AND term_fk = ?",
                                        [$outcomeCode, $selectedTermFk],
                                        'si'
                                    );
                                    
                                    if ($result->rowCount() > 0) {
                                        $existing = $result->fetch();
                                        $userId = $_SESSION['user_id'] ?? null;
                                        $db->query(
                                            "UPDATE {$dbPrefix}institutional_outcomes 
                                             SET outcome_description = ?, sequence_num = ?, updated_at = NOW(), updated_by_fk = ?
                                             WHERE institutional_outcomes_pk = ?",
                                            [$outcomeDescription, $sequenceNum, $userId, $existing['institutional_outcomes_pk']],
                                            'siii'
                                        );
                                    } else {
                                        $userId = $_SESSION['user_id'] ?? null;
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}institutional_outcomes (term_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                                             VALUES (?, ?, ?, ?, 1, NOW(), NOW(), ?, ?)",
                                            [$selectedTermFk, $outcomeCode, $outcomeDescription, $sequenceNum, $userId, $userId],
                                            'issiii'
                                        );
                                    }
                                    $imported++;
                                    $sequenceNum++;
                                } else {
                                    $skipped++;
                                }
                            }
                        }
                        
                        fclose($handle);
                        $successMessage = "ISLO import completed: {$imported} imported/updated, {$skipped} skipped";
                        $importStats = ['imported' => $imported, 'skipped' => $skipped];
                    }
                } else {
                    $errorMessage = 'Please select a valid CSV file';
                }
                break;
                
            case 'pslo':
                if (isset($_FILES['pslo_upload']) && $_FILES['pslo_upload']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['pslo_upload']['tmp_name'];
                    
                    $selectedTermFk = getSelectedTermFk();
                    if (!$selectedTermFk) {
                        $errorMessage = 'No term selected. Please select a term first.';
                        break;
                    }
                    
                    $handle = fopen($tmpName, 'r');
                    
                    if ($handle !== false) {
                        $bom = fread($handle, 3);
                        if ($bom !== "\xEF\xBB\xBF") {
                            rewind($handle);
                        }
                        
                        $headers = fgetcsv($handle);
                        $imported = 0;
                        $updated = 0;
                        $programsCreated = 0;
                        $errors = [];
                        $rowNum = 1;
                        
                        $programMap = [];
                        $programsResult = $db->query("
                            SELECT programs_pk, program_name, program_code 
                            FROM {$dbPrefix}programs 
                            WHERE is_active = 1
                        ");
                        while ($prog = $programsResult->fetch()) {
                            $programMap[$prog['program_name']] = $prog['programs_pk'];
                            $programMap[$prog['program_code']] = $prog['programs_pk'];
                        }
                        
                        $psloCountByProgram = [];
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            $rowNum++;
                            if (count($row) >= 5) {
                                $programCode = trim($row[0]);
                                $programFullName = trim($row[1]);
                                $psloid = trim($row[3]);
                                $outcomeDescription = trim($row[4]);
                                
                                $programFk = null;
                                if (isset($programMap[$programCode])) {
                                    $programFk = $programMap[$programCode];
                                } elseif (isset($programMap[$programFullName])) {
                                    $programFk = $programMap[$programFullName];
                                } else {
                                    foreach ($programMap as $key => $pk) {
                                        if (stripos($programFullName, $key) !== false || stripos($key, $programFullName) !== false) {
                                            $programFk = $pk;
                                            break;
                                        }
                                    }
                                }
                                
                                if ($programFk === null) {
                                    // Check if program exists in database by code
                                    $checkProgramResult = $db->query(
                                        "SELECT programs_pk FROM {$dbPrefix}programs WHERE program_code = ?",
                                        [$programCode],
                                        's'
                                    );
                                    
                                    if ($checkProgramResult->rowCount() > 0) {
                                        // Program exists in database, use it
                                        $existingProgram = $checkProgramResult->fetch();
                                        $programFk = $existingProgram['programs_pk'];
                                        $programMap[$programCode] = $programFk;
                                        $programMap[$programFullName] = $programFk;
                                    } else {
                                        // Create new program
                                        $parts = explode(',', $programFullName);
                                        
                                        if (count($parts) >= 2) {
                                            $degreeType = trim(array_pop($parts));
                                            $baseProgramName = trim(implode(',', $parts));
                                        } else {
                                            $baseProgramName = trim($programFullName);
                                            $degreeType = '';
                                        }
                                        
                                        try {
                                            $userId = $_SESSION['user_id'] ?? null;
                                            $db->query(
                                                "INSERT INTO {$dbPrefix}programs (term_fk, program_code, program_name, degree_type, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                                                 VALUES (?, ?, ?, ?, 1, NOW(), NOW(), ?, ?)",
                                                [$selectedTermFk, $programCode, $baseProgramName, $degreeType, $userId, $userId],
                                                'isssii'
                                            );
                                            
                                            $programFk = $db->getInsertId();
                                            $programMap[$programFullName] = $programFk;
                                            $programMap[$programCode] = $programFk;
                                            $programsCreated++;
                                        } catch (Exception $e) {
                                            $errors[] = "Row $rowNum: Failed to create program '$baseProgramName'";
                                            continue;
                                        }
                                    }
                                }
                                
                                if (!isset($psloCountByProgram[$programFk])) {
                                    $countResult = $db->query(
                                        "SELECT COUNT(*) as count FROM {$dbPrefix}program_outcomes WHERE program_fk = ?",
                                        [$programFk],
                                        'i'
                                    );
                                    $countRow = $countResult->fetch();
                                    $psloCountByProgram[$programFk] = $countRow['count'];
                                }
                                
                                $psloCountByProgram[$programFk]++;
                                $outcomeCode = 'PSLO-P' . $programFk . '-' . $psloCountByProgram[$programFk];
                                $sequenceNum = $psloCountByProgram[$programFk];
                                
                                if (empty($outcomeDescription)) {
                                    $errors[] = "Row $rowNum: Empty outcome description";
                                    continue;
                                }
                                
                                $result = $db->query(
                                    "SELECT program_outcomes_pk FROM {$dbPrefix}program_outcomes 
                                     WHERE program_fk = ? AND outcome_description = ?",
                                    [$programFk, $outcomeDescription],
                                    'is'
                                );
                                
                                if ($result->rowCount() > 0) {
                                    $existing = $result->fetch();
                                    $userId = $_SESSION['user_id'] ?? null;
                                    $db->query(
                                        "UPDATE {$dbPrefix}program_outcomes 
                                         SET outcome_code = ?, sequence_num = ?, is_active = 1, updated_at = NOW(), updated_by_fk = ?
                                         WHERE program_outcomes_pk = ?",
                                        [$outcomeCode, $sequenceNum, $userId, $existing['program_outcomes_pk']],
                                        'siii'
                                    );
                                    $updated++;
                                } else {
                                    $userId = $_SESSION['user_id'] ?? null;
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}program_outcomes (program_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                                         VALUES (?, ?, ?, ?, 1, NOW(), NOW(), ?, ?)",
                                        [$programFk, $outcomeCode, $outcomeDescription, $sequenceNum, $userId, $userId],
                                        'issiii'
                                    );
                                    $imported++;
                                }
                            }
                        }
                        
                        fclose($handle);
                        
                        $msg = "PSLO import completed: {$imported} imported, {$updated} updated";
                        if ($programsCreated > 0) $msg .= ", {$programsCreated} programs created";
                        $successMessage = $msg;
                        
                        if (!empty($errors)) {
                            $errorMessage = implode('<br>', array_slice($errors, 0, 10));
                            if (count($errors) > 10) {
                                $errorMessage .= '<br>... and ' . (count($errors) - 10) . ' more errors';
                            }
                        }
                        $importStats = ['imported' => $imported, 'updated' => $updated, 'programs_created' => $programsCreated];
                    }
                } else {
                    $errorMessage = 'Please select a valid CSV file';
                }
                break;
                
            case 'enrollment':
                if (isset($_FILES['enrollment_upload']) && $_FILES['enrollment_upload']['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['enrollment_upload']['tmp_name'];
                    $handle = fopen($tmpName, 'r');
                    
                    if ($handle !== false) {
                        // Skip BOM if present
                        $bom = fread($handle, 3);
                        if ($bom !== "\xEF\xBB\xBF") {
                            rewind($handle);
                        }
                        
                        $headers = fgetcsv($handle);
                        $imported = 0;
                        $updated = 0;
                        $skipped = 0;
                        $errors = [];
                        
                        // CSV Format: BannerTerm,TermCode,StudentID,SectionID,FirstName,LastName,PartofTerm,Discipline,CourseID
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 4) {
                                $bannerTerm = trim($row[0]);
                                $termCode = trim($row[1]);
                                $studentId = trim($row[2]);
                                $sectionId = trim($row[3]);
                                $firstName = isset($row[4]) ? trim($row[4]) : '';
                                $lastName = isset($row[5]) ? trim($row[5]) : '';
                                $partOfTerm = isset($row[6]) ? trim($row[6]) : '';
                                $discipline = isset($row[7]) ? trim($row[7]) : '';
                                $courseId = isset($row[8]) ? trim($row[8]) : '';
                                
                                if (empty($bannerTerm) || empty($sectionId) || empty($studentId)) {
                                    $skipped++;
                                    continue;
                                }
                                
                                // Find or create term
                                $termResult = $db->query(
                                    "SELECT terms_pk FROM {$dbPrefix}terms WHERE banner_term = ?",
                                    [$bannerTerm],
                                    's'
                                );
                                
                                if ($termResult->rowCount() === 0) {
                                    // Auto-create term from banner_term (format: YYYYMM)
                                    $year = substr($bannerTerm, 0, 4);
                                    $semCode = substr($bannerTerm, 4, 2);
                                    $semesterName = match($semCode) {
                                        '01' => 'Spring',
                                        '05' => 'Summer',
                                        '08' => 'Fall',
                                        default => 'Term'
                                    };
                                    $termName = "$semesterName $year";
                                    $academicYear = $semCode >= '08' ? "$year-" . ($year + 1) : ($year - 1) . "-$year";
                                    
                                    $userId = $_SESSION['user_id'] ?? null;
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}terms (banner_term, academic_year, term_name, is_active, created_at, updated_at, created_by_fk, updated_by_fk)
                                         VALUES (?, ?, ?, 1, NOW(), NOW(), ?, ?)",
                                        [$bannerTerm, $academicYear, $termName, $userId, $userId],
                                        'sssii'
                                    );
                                    $termFk = $db->getInsertId();
                                } else {
                                    $termRow = $termResult->fetch();
                                    $termFk = $termRow['terms_pk'];
                                }
                                
                                $validatedTermCode = $bannerTerm;
                                
                                // Find or create course
                                $courseFk = null;
                                if (!empty($courseId)) {
                                    $courseResult = $db->query(
                                        "SELECT courses_pk FROM {$dbPrefix}courses WHERE course_number = ?",
                                        [$courseId],
                                        's'
                                    );
                                    if ($courseResult->rowCount() > 0) {
                                        $course = $courseResult->fetch();
                                        $courseFk = $course['courses_pk'];
                                    } else {
                                        // Auto-create course with course_number as placeholder name
                                        $userId = $_SESSION['user_id'] ?? null;
                                        $db->query(
                                            "INSERT INTO {$dbPrefix}courses (course_number, course_name, term_fk, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                                             VALUES (?, ?, ?, 1, NOW(), NOW(), ?, ?)",
                                            [$courseId, $courseId, $termFk, $userId, $userId],
                                            'ssiii'
                                        );
                                        $courseFk = $db->getInsertId();
                                    }
                                } else {
                                    $errors[] = "CourseID is empty";
                                    $skipped++;
                                    continue;
                                }
                                
                                // Find or create student
                                $studentResult = $db->query(
                                    "SELECT students_pk FROM {$dbPrefix}students WHERE student_id = ?",
                                    [$studentId],
                                    's'
                                );
                                
                                $studentFk = null;
                                if ($studentResult->rowCount() === 0) {
                                    $userId = $_SESSION['user_id'] ?? null;
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}students (student_id, first_name, last_name, created_at, updated_at, created_by_fk, updated_by_fk) 
                                         VALUES (?, ?, ?, NOW(), NOW(), ?, ?)",
                                        [$studentId, $firstName, $lastName, $userId, $userId],
                                        'sssii'
                                    );
                                    $studentFk = $db->getInsertId();
                                } else {
                                    $student = $studentResult->fetch();
                                    $studentFk = $student['students_pk'];
                                    
                                    // Update student name if provided and currently empty
                                    if (!empty($firstName) || !empty($lastName)) {
                                        $userId = $_SESSION['user_id'] ?? null;
                                        $db->query(
                                            "UPDATE {$dbPrefix}students 
                                             SET first_name = CASE WHEN (first_name IS NULL OR first_name = '') AND ? != '' THEN ? ELSE first_name END,
                                                 last_name = CASE WHEN (last_name IS NULL OR last_name = '') AND ? != '' THEN ? ELSE last_name END,
                                                 updated_at = NOW(),
                                                 updated_by_fk = ?
                                             WHERE students_pk = ?",
                                            [$firstName, $firstName, $lastName, $lastName, $userId, $studentFk],
                                            'ssssii'
                                        );
                                    }
                                }
                                
                                // Check if enrollment exists
                                $checkResult = $db->query(
                                    "SELECT enrollment_pk FROM {$dbPrefix}enrollment 
                                     WHERE student_fk = ? AND course_fk = ? AND term_code = ? AND crn = ?",
                                    [$studentFk, $courseFk, $validatedTermCode, $sectionId],
                                    'iiss'
                                );
                                
                                if ($checkResult->rowCount() === 0) {
                                    $userId = $_SESSION['user_id'] ?? null;
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}enrollment 
                                         (student_fk, course_fk, term_code, crn, enrollment_date, created_at, updated_at, created_by_fk, updated_by_fk) 
                                         VALUES (?, ?, ?, ?, NOW(), NOW(), NOW(), ?, ?)",
                                        [$studentFk, $courseFk, $validatedTermCode, $sectionId, $userId, $userId],
                                        'iissii'
                                    );
                                    $imported++;
                                } else {
                                    // Enrollment already exists, skip update
                                    $updated++;
                                }
                            }
                        }
                        
                        fclose($handle);
                        $successMessage = "Enrollment import completed: {$imported} imported, {$updated} updated, {$skipped} skipped";
                        if (!empty($errors)) {
                            $errorMessage = implode('<br>', array_slice($errors, 0, 10));
                            if (count($errors) > 10) {
                                $errorMessage .= '<br>... and ' . (count($errors) - 10) . ' more errors';
                            }
                        }
                        $importStats = ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
                    }
                } else {
                    $errorMessage = 'Please select a valid CSV file';
                }
                break;
                
            case 'cslo':
                if (!isset($_FILES['cslo_upload']) || $_FILES['cslo_upload']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessage = 'Please select a valid CSV file';
                    break;
                }
                
                $selectedTermFk = getSelectedTermFk();
                if (!$selectedTermFk) {
                    $errorMessage = 'No term selected. Please select a term first.';
                    break;
                }
                
                $file = $_FILES['cslo_upload']['tmp_name'];
                $handle = fopen($file, 'r');
                
                if ($handle === false) {
                    $errorMessage = 'Failed to open CSV file';
                    break;
                }
                
                // Skip BOM
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                
                $headers = fgetcsv($handle);
                if ($headers === false) {
                    $errorMessage = 'Invalid CSV file format';
                    fclose($handle);
                    break;
                }
                
                $imported = 0;
                $updated = 0;
                $coursesCreated = 0;
                $errors = [];
                $rowNum = 0;
                $courseMaxSequence = [];
                
                // Build course map
                $courseMap = [];
                $coursesResult = $db->query(
                    "SELECT courses_pk, course_number FROM {$dbPrefix}courses WHERE is_active = 1"
                );
                while ($course = $coursesResult->fetch()) {
                    $courseMap[$course['course_number']] = $course['courses_pk'];
                    
                    $maxSeqResult = $db->query(
                        "SELECT COALESCE(MAX(sequence_num), 0) as max_seq 
                         FROM {$dbPrefix}student_learning_outcomes 
                         WHERE course_fk = ?",
                        [$course['courses_pk']],
                        'i'
                    );
                    $maxSeqRow = $maxSeqResult->fetch();
                    $courseMaxSequence[$course['courses_pk']] = (int)$maxSeqRow['max_seq'];
                }
                
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNum++;
                    if (count($row) < 2) continue;
                    
                    $data = array_combine($headers, $row);
                    if ($data === false) continue;
                    
                    $courseId = trim($data['CRS ID'] ?? $data['course_number'] ?? '');
                    $courseTitle = trim($data['CRS TITLE'] ?? '');
                    $csloText = trim($data['CSLO'] ?? $data['slo_description'] ?? '');
                    
                    if (empty($courseId) || empty($csloText)) {
                        $errors[] = "Row $rowNum: Missing CRS ID or CSLO";
                        continue;
                    }
                    
                    // Find or create course
                    if (!isset($courseMap[$courseId])) {
                        // Check if course exists in database
                        $checkCourseResult = $db->query(
                            "SELECT courses_pk FROM {$dbPrefix}courses WHERE course_number = ?",
                            [$courseId],
                            's'
                        );
                        
                        if ($checkCourseResult->rowCount() > 0) {
                            // Course exists, use it
                            $existingCourse = $checkCourseResult->fetch();
                            $courseFk = $existingCourse['courses_pk'];
                            $courseMap[$courseId] = $courseFk;
                            
                            // Get max sequence for this course
                            $maxSeqResult = $db->query(
                                "SELECT COALESCE(MAX(sequence_num), 0) as max_seq 
                                 FROM {$dbPrefix}student_learning_outcomes 
                                 WHERE course_fk = ?",
                                [$courseFk],
                                'i'
                            );
                            $maxSeqRow = $maxSeqResult->fetch();
                            $courseMaxSequence[$courseFk] = (int)$maxSeqRow['max_seq'];
                        } else {
                            // Create new course
                            $userId = $_SESSION['user_id'] ?? null;
                            $db->query(
                                "INSERT INTO {$dbPrefix}courses (course_number, course_name, term_fk, created_at, updated_at, is_active, created_by_fk, updated_by_fk)
                                 VALUES (?, ?, ?, NOW(), NOW(), 1, ?, ?)",
                                [$courseId, $courseTitle, $selectedTermFk, $userId, $userId],
                                'ssiii'
                            );
                            $courseFk = $db->getInsertId();
                            $courseMap[$courseId] = $courseFk;
                            $courseMaxSequence[$courseFk] = 0;
                            $coursesCreated++;
                        }
                    } else {
                        $courseFk = $courseMap[$courseId];
                    }
                    
                    // Split CSLO text by sentences (periods)
                    $sentences = preg_split('/\.(?=\s|$)/', $csloText);
                    $sentences = array_filter(array_map('trim', $sentences), function($s) {
                        return !empty($s);
                    });
                    
                    // Process each sentence as a separate SLO
                    foreach ($sentences as $sloSentence) {
                        // Skip empty sentences
                        if (empty($sloSentence)) {
                            continue;
                        }
                        
                        // Add period back if it doesn't end with punctuation
                        if (!preg_match('/[.!?]$/', $sloSentence)) {
                            $sloSentence .= '.';
                        }
                        
                        // Check if this exact SLO description already exists for this course
                        $existingResult = $db->query(
                            "SELECT student_learning_outcomes_pk, slo_code FROM {$dbPrefix}student_learning_outcomes 
                             WHERE course_fk = ? AND slo_description = ?",
                            [$courseFk, $sloSentence],
                            'is'
                        );
                        
                        if ($existingResult->rowCount() > 0) {
                            // SLO already exists, skip
                            $updated++;
                        } else {
                            // Get fresh max sequence from database to ensure we don't have duplicates
                            $maxSeqResult = $db->query(
                                "SELECT COALESCE(MAX(sequence_num), 0) as max_seq 
                                 FROM {$dbPrefix}student_learning_outcomes 
                                 WHERE course_fk = ?",
                                [$courseFk],
                                'i'
                            );
                            $maxSeqRow = $maxSeqResult->fetch();
                            $nextSequence = (int)$maxSeqRow['max_seq'] + 1;
                            
                            // Generate unique slo_code
                            $sloCode = "CSLO-{$courseId}-{$nextSequence}";
                            
                            // Insert new SLO
                            $userId = $_SESSION['user_id'] ?? null;
                            $db->query(
                                "INSERT INTO {$dbPrefix}student_learning_outcomes 
                                 (course_fk, slo_code, slo_description, sequence_num, created_at, updated_at, is_active, created_by_fk, updated_by_fk)
                                 VALUES (?, ?, ?, ?, NOW(), NOW(), 1, ?, ?)",
                                [$courseFk, $sloCode, $sloSentence, $nextSequence, $userId, $userId],
                                'issiii'
                            );
                            $imported++;
                        }
                    }
                }
                
                fclose($handle);
                $msg = "CSLO import completed: {$imported} imported";
                if ($updated > 0) $msg .= ", {$updated} duplicates skipped";
                if ($coursesCreated > 0) $msg .= ", {$coursesCreated} courses auto-created";
                $successMessage = $msg;
                
                if (!empty($errors)) {
                    $errorMessage = implode('<br>', array_slice($errors, 0, 10));
                    if (count($errors) > 10) {
                        $errorMessage .= '<br>... and ' . (count($errors) - 10) . ' more errors';
                    }
                }
                $importStats = ['imported' => $imported, 'updated' => $updated, 'courses_created' => $coursesCreated];
                break;
                
            case 'pslo_map':
                if (!isset($_FILES['pslo_map_upload']) || $_FILES['pslo_map_upload']['error'] !== UPLOAD_ERR_OK) {
                    $errorMessage = 'Please select a valid CSV file';
                    break;
                }
                
                $file = $_FILES['pslo_map_upload']['tmp_name'];
                $handle = fopen($file, 'r');
                
                if ($handle === false) {
                    $errorMessage = 'Failed to open CSV file';
                    break;
                }
                
                // Skip BOM
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                
                $headers = fgetcsv($handle);
                $imported = 0;
                $skipped = 0;
                $errors = [];
                
                // Build maps
                $programMap = [];
                $programsResult = $db->query("SELECT programs_pk, program_code FROM {$dbPrefix}programs WHERE is_active = 1");
                while ($prog = $programsResult->fetch()) {
                    $programMap[trim($prog['program_code'])] = $prog['programs_pk'];
                }
                
                $courseMap = [];
                $coursesResult = $db->query("SELECT courses_pk, course_number FROM {$dbPrefix}courses WHERE is_active = 1");
                while ($course = $coursesResult->fetch()) {
                    $courseMap[trim($course['course_number'])] = $course['courses_pk'];
                }
                
                $rowNum = 1;
                $programsCreated = 0;
                $coursesCreated = 0;
                
                // Get selected term for auto-created records
                $selectedTermFk = getSelectedTermFk();
                if (!$selectedTermFk) {
                    $errorMessage = 'No term selected. Please select a term first.';
                    fclose($handle);
                    break;
                }
                
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNum++;
                    if (count($row) >= 3) {
                        $programCode = trim($row[0]);
                        $programName = trim($row[1]);
                        $courseNumber = trim($row[2]);
                        
                        if (empty($programCode) || empty($courseNumber)) {
                            $skipped++;
                            continue;
                        }
                        
                        // Find or create program
                        $programFk = $programMap[$programCode] ?? null;
                        if (!$programFk) {
                            // Check if program exists in database
                            $checkProgramResult = $db->query(
                                "SELECT programs_pk FROM {$dbPrefix}programs WHERE program_code = ?",
                                [$programCode],
                                's'
                            );
                            
                            if ($checkProgramResult->rowCount() > 0) {
                                // Program exists, use it
                                $existingProgram = $checkProgramResult->fetch();
                                $programFk = $existingProgram['programs_pk'];
                                $programMap[$programCode] = $programFk;
                            } else {
                                // Create new program
                                $userId = $_SESSION['user_id'] ?? null;
                                $db->query(
                                    "INSERT INTO {$dbPrefix}programs (term_fk, program_code, program_name, is_active, created_at, updated_at, created_by_fk, updated_by_fk)
                                     VALUES (?, ?, ?, 1, NOW(), NOW(), ?, ?)",
                                    [$selectedTermFk, $programCode, $programName, $userId, $userId],
                                    'issii'
                                );
                                $programFk = $db->getInsertId();
                                $programMap[$programCode] = $programFk;
                                $programsCreated++;
                            }
                        }
                        
                        // Find or create course
                        $courseFk = $courseMap[$courseNumber] ?? null;
                        if (!$courseFk) {
                            // Check if course exists in database
                            $checkCourseResult = $db->query(
                                "SELECT courses_pk FROM {$dbPrefix}courses WHERE course_number = ?",
                                [$courseNumber],
                                's'
                            );
                            
                            if ($checkCourseResult->rowCount() > 0) {
                                // Course exists, use it
                                $existingCourse = $checkCourseResult->fetch();
                                $courseFk = $existingCourse['courses_pk'];
                                $courseMap[$courseNumber] = $courseFk;
                            } else {
                                // Create new course
                                $userId = $_SESSION['user_id'] ?? null;
                                $db->query(
                                    "INSERT INTO {$dbPrefix}courses (course_number, course_name, term_fk, is_active, created_at, updated_at, created_by_fk, updated_by_fk)
                                     VALUES (?, ?, ?, 1, NOW(), NOW(), ?, ?)",
                                    [$courseNumber, $courseNumber, $selectedTermFk, $userId, $userId],
                                    'ssiii'
                                );
                                $courseFk = $db->getInsertId();
                                $courseMap[$courseNumber] = $courseFk;
                                $coursesCreated++;
                            }
                        }
                        
                        // Check if mapping exists
                        $checkResult = $db->query(
                            "SELECT program_courses_pk FROM {$dbPrefix}program_courses 
                             WHERE program_fk = ? AND course_fk = ?",
                            [$programFk, $courseFk],
                            'ii'
                        );
                        
                        if ($checkResult->rowCount() === 0) {
                            $userId = $_SESSION['user_id'] ?? null;
                            $db->query(
                                "INSERT INTO {$dbPrefix}program_courses (program_fk, course_fk, created_at, created_by_fk) 
                                 VALUES (?, ?, NOW(), ?)",
                                [$programFk, $courseFk, $userId],
                                'iii'
                            );
                            $imported++;
                        }
                    }
                }
                
                fclose($handle);
                $msg = "Program-Course mapping import completed: {$imported} mappings created";
                if ($skipped > 0) $msg .= ", {$skipped} skipped";
                if ($programsCreated > 0) $msg .= ", {$programsCreated} programs auto-created";
                if ($coursesCreated > 0) $msg .= ", {$coursesCreated} courses auto-created";
                $successMessage = $msg;
                if (!empty($errors)) {
                    $errorMessage = implode('<br>', array_slice($errors, 0, 10));
                    if (count($errors) > 10) {
                        $errorMessage .= '<br>... and ' . (count($errors) - 10) . ' more errors';
                    }
                }
                $importStats = ['imported' => $imported, 'skipped' => $skipped, 'programs_created' => $programsCreated, 'courses_created' => $coursesCreated];
                break;
                
            default:
                $errorMessage = 'Invalid import type';
                break;
        }
    } catch (Exception $e) {
        $errorMessage = 'Import failed: ' . htmlspecialchars($e->getMessage());
        error_log("Import error: " . $e->getMessage());
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

// Set up theme context
$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Data Imports - ' . SITE_NAME,
    'currentPage' => 'admin_imports',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Data Imports']
    ]
]);

$theme = ThemeLoader::getActiveTheme(null, 'admin');
$theme->showHeader($context);
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Data Imports</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?= $successMessage ?>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?= $errorMessage ?>
                </div>
            <?php endif; ?>

            <div class="card card-primary card-outline card-tabs">
                <div class="card-header p-0 pt-1 border-bottom-0">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'islo' ? 'active' : '' ?>" data-bs-toggle="tab" href="#islo-tab" role="tab">
                                <i class="fas fa-graduation-cap"></i> ISLO
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'pslo' ? 'active' : '' ?>" data-bs-toggle="tab" href="#pslo-tab" role="tab">
                                <i class="fas fa-certificate"></i> PSLO
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'cslo' ? 'active' : '' ?>" data-bs-toggle="tab" href="#cslo-tab" role="tab">
                                <i class="fas fa-list-check"></i> CSLO
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'pslo-map' ? 'active' : '' ?>" data-bs-toggle="tab" href="#pslo-map-tab" role="tab">
                                <i class="fas fa-diagram-project"></i> PSLO Mapping
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'enrollment' ? 'active' : '' ?>" data-bs-toggle="tab" href="#enrollment-tab" role="tab">
                                <i class="fas fa-user-check"></i> Enrollment
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <!-- ISLO Import -->
                        <div class="tab-pane fade <?= $activeTab === 'islo' ? 'show active' : '' ?>" id="islo-tab" role="tabpanel">
                            <h4>Import Institutional Student Learning Outcomes</h4>
                            <p>Upload a CSV file with ISLO data. Format: One outcome per line (outcome description only)</p>
                            
                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="import_type" value="islo">
                                
                                <div class="mb-3">
                                    <label for="islo_upload" class="form-label">Select CSV File:</label>
                                    <input type="file" class="form-control" id="islo_upload" name="islo_upload" accept=".csv" required>
                                    <small class="form-text text-muted">Outcome codes will be auto-generated as ISLO-1, ISLO-2, etc.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import ISLO
                                </button>
                            </form>
                        </div>
                        
                        <!-- PSLO Import -->
                        <div class="tab-pane fade <?= $activeTab === 'pslo' ? 'show active' : '' ?>" id="pslo-tab" role="tabpanel">
                            <h4>Import Program Student Learning Outcomes</h4>
                            <p>Upload a CSV file with PSLO data. Format: Program Code, Program, seq, PSLOID, Program Outcome</p>
                            
                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="import_type" value="pslo">
                                
                                <div class="mb-3">
                                    <label for="pslo_upload" class="form-label">Select CSV File:</label>
                                    <input type="file" class="form-control" id="pslo_upload" name="pslo_upload" accept=".csv" required>
                                    <small class="form-text text-muted">Programs will be auto-created if they don't exist</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import PSLO
                                </button>
                            </form>
                        </div>
                        
                        <!-- CSLO Import -->
                        <div class="tab-pane fade <?= $activeTab === 'cslo' ? 'show active' : '' ?>" id="cslo-tab" role="tabpanel">
                            <h4>Import Course Student Learning Outcomes</h4>
                            <p>Upload a CSV file with CSLO data. Format: CRS ID, CRS TITLE, CSLO</p>
                            
                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="import_type" value="cslo">
                                
                                <div class="mb-3">
                                    <label for="cslo_upload" class="form-label">Select CSV File:</label>
                                    <input type="file" class="form-control" id="cslo_upload" name="cslo_upload" accept=".csv" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import CSLO
                                </button>
                            </form>
                        </div>
                        
                        <!-- PSLO Mapping Import -->
                        <div class="tab-pane fade <?= $activeTab === 'pslo-map' ? 'show active' : '' ?>" id="pslo-map-tab" role="tabpanel">
                            <h4>Import Program-Course Mappings</h4>
                            <p>Upload a CSV file to map courses to programs. Format: ProgramID, Program, Course</p>
                            
                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="import_type" value="pslo_map">
                                
                                <div class="mb-3">
                                    <label for="pslo_map_upload" class="form-label">Select CSV File:</label>
                                    <input type="file" class="form-control" id="pslo_map_upload" name="pslo_map_upload" accept=".csv" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import Mapping
                                </button>
                            </form>
                        </div>
                        
                        <!-- Enrollment Import -->
                        <div class="tab-pane fade <?= $activeTab === 'enrollment' ? 'show active' : '' ?>" id="enrollment-tab" role="tabpanel">
                            <h4>Import Enrollment Data</h4>
                            <p>Upload a CSV file with enrollment data. Format: BannerTerm, TermCode, StudentID, SectionID, FirstName, LastName, PartofTerm, Discipline, CourseID</p>
                            
                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="import_type" value="enrollment">
                                
                                <div class="mb-3">
                                    <label for="enrollment_upload" class="form-label">Select CSV File:</label>
                                    <input type="file" class="form-control" id="enrollment_upload" name="enrollment_upload" accept=".csv" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Import Enrollment
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php $theme->showFooter($context); ?>
