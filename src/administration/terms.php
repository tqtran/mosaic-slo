<?php
declare(strict_types=1);

/**
 * Terms Administration (Import Only)
 * 
 * Manage academic terms via CSV import.
 */

require_once __DIR__ . '/../system/includes/admin_session.php';
require_once __DIR__ . '/../system/includes/init.php';

// Handle POST requests
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'copy_term_data') {
            $sourceTermCode = trim($_POST['source_term_code'] ?? '');
            $destTermCode = trim($_POST['dest_term_code'] ?? '');
            
            if (empty($sourceTermCode) || empty($destTermCode)) {
                $errorMessage = 'Both source and destination term codes are required';
            } elseif ($sourceTermCode === $destTermCode) {
                $errorMessage = 'Source and destination terms cannot be the same';
            } else {
                // Verify both terms exist and get their PKs
                $sourceCheck = $db->query("SELECT terms_pk FROM {$dbPrefix}terms WHERE term_code = ?", [$sourceTermCode], 's');
                $sourceTermRow = $sourceCheck->fetch();
                $destCheck = $db->query("SELECT terms_pk FROM {$dbPrefix}terms WHERE term_code = ?", [$destTermCode], 's');
                $destTermRow = $destCheck->fetch();
                
                if (!$sourceTermRow) {
                    $errorMessage = "Source term '$sourceTermCode' not found";
                } elseif (!$destTermRow) {
                    $errorMessage = "Destination term '$destTermCode' not found";
                } else {
                    $sourceTermPk = $sourceTermRow['terms_pk'];
                    $destTermPk = $destTermRow['terms_pk'];
                    
                    // Start transaction
                    $db->query("START TRANSACTION");
                    
                    try {
                        $copiedInstitutionalOutcomes = 0;
                        $copiedPrograms = 0;
                        $copiedProgramOutcomes = 0;
                        $copiedCourses = 0;
                        $copiedSLOs = 0;
                        
                        // FK mapping arrays - map source PKs to destination PKs
                        $ioMapping = [];           // institutional_outcomes_pk mapping
                        $programMapping = [];      // programs_pk mapping
                        $programOutcomeMapping = []; // program_outcomes_pk mapping
                        $courseMapping = [];       // courses_pk mapping
                        
                        // Copy Institutional Outcomes
                        $ioResult = $db->query(
                            "SELECT institutional_outcomes_pk, outcome_code, outcome_description, sequence_num, is_active 
                             FROM {$dbPrefix}institutional_outcomes 
                             WHERE term_fk = ?",
                            [$sourceTermPk],
                            'i'
                        );
                        $institutionalOutcomes = $ioResult->fetchAll();
                        
                        foreach ($institutionalOutcomes as $io) {
                            // Check if exists in destination
                            $existingCheck = $db->query(
                                "SELECT institutional_outcomes_pk FROM {$dbPrefix}institutional_outcomes 
                                 WHERE term_fk = ? AND outcome_code = ?",
                                [$destTermPk, $io['outcome_code']],
                                'is'
                            );
                            $existing = $existingCheck->fetch();
                            
                            if ($existing) {
                                // Map to existing
                                $ioMapping[$io['institutional_outcomes_pk']] = $existing['institutional_outcomes_pk'];
                            } else {
                                // Insert new
                                $db->query(
                                    "INSERT INTO {$dbPrefix}institutional_outcomes 
                                     (term_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                                     VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                    [$destTermPk, $io['outcome_code'], $io['outcome_description'], $io['sequence_num'], $io['is_active']],
                                    'issii'
                                );
                                $newIoPk = $db->getInsertId();
                                $ioMapping[$io['institutional_outcomes_pk']] = $newIoPk;
                                $copiedInstitutionalOutcomes++;
                            }
                        }
                        
                        // Copy Programs
                        $programsResult = $db->query(
                            "SELECT programs_pk, program_code, program_name, degree_type, is_active 
                             FROM {$dbPrefix}programs 
                             WHERE term_fk = ?",
                            [$sourceTermPk],
                            'i'
                        );
                        $programs = $programsResult->fetchAll();
                        
                        foreach ($programs as $program) {
                            $sourceProgramPk = $program['programs_pk'];
                            
                            // Check if program exists in destination
                            $existingCheck = $db->query(
                                "SELECT programs_pk FROM {$dbPrefix}programs 
                                 WHERE term_fk = ? AND program_code = ?",
                                [$destTermPk, $program['program_code']],
                                'is'
                            );
                            $existing = $existingCheck->fetch();
                            
                            if ($existing) {
                                // Map to existing program
                                $programMapping[$sourceProgramPk] = $existing['programs_pk'];
                                $newProgramPk = $existing['programs_pk'];
                            } else {
                                // Insert program
                                $db->query(
                                    "INSERT INTO {$dbPrefix}programs 
                                     (term_fk, program_code, program_name, degree_type, is_active, created_at, updated_at) 
                                     VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                    [$destTermPk, $program['program_code'], $program['program_name'], $program['degree_type'], $program['is_active']],
                                    'isssi'
                                );
                                $newProgramPk = $db->getInsertId();
                                $programMapping[$sourceProgramPk] = $newProgramPk;
                                $copiedPrograms++;
                            }
                            
                            // Copy Program Outcomes for this program
                            if ($sourceProgramPk) {
                                $programOutcomesResult = $db->query(
                                    "SELECT program_outcomes_pk, outcome_code, outcome_description, sequence_num, is_active, institutional_outcomes_fk 
                                     FROM {$dbPrefix}program_outcomes 
                                     WHERE program_fk = ?",
                                    [$sourceProgramPk],
                                    'i'
                                );
                                $programOutcomes = $programOutcomesResult->fetchAll();
                                
                                foreach ($programOutcomes as $outcome) {
                                    // Map institutional_outcomes_fk to new PK if it exists
                                    $newIoFk = null;
                                    if ($outcome['institutional_outcomes_fk']) {
                                        $newIoFk = $ioMapping[$outcome['institutional_outcomes_fk']] ?? null;
                                    }
                                    
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}program_outcomes 
                                         (program_fk, institutional_outcomes_fk, outcome_code, outcome_description, sequence_num, is_active, created_at, updated_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                                        [$newProgramPk, $newIoFk, $outcome['outcome_code'], $outcome['outcome_description'], $outcome['sequence_num'], $outcome['is_active']],
                                        'iissii'
                                    );
                                    $newProgramOutcomePk = $db->getInsertId();
                                    $programOutcomeMapping[$outcome['program_outcomes_pk']] = $newProgramOutcomePk;
                                    $copiedProgramOutcomes++;
                                }
                            }
                        }
                        
                        // Copy Courses
                        $coursesResult = $db->query(
                            "SELECT courses_pk, program_fk, course_number, course_name, is_active 
                             FROM {$dbPrefix}courses 
                             WHERE term_fk = ?",
                            [$sourceTermPk],
                            'i'
                        );
                        $courses = $coursesResult->fetchAll();
                        
                        foreach ($courses as $course) {
                            $sourceCoursePk = $course['courses_pk'];
                            
                            // Map program_fk to destination program (or use same if programs were copied)
                            $newProgramFk = $programMapping[$course['program_fk']] ?? $course['program_fk'];
                            
                            // Check if course exists in destination
                            $existingCheck = $db->query(
                                "SELECT courses_pk FROM {$dbPrefix}courses 
                                 WHERE term_fk = ? AND course_number = ? AND program_fk = ?",
                                [$destTermPk, $course['course_number'], $newProgramFk],
                                'isi'
                            );
                            $existing = $existingCheck->fetch();
                            
                            if ($existing) {
                                // Map to existing course
                                $courseMapping[$sourceCoursePk] = $existing['courses_pk'];
                                $newCoursePk = $existing['courses_pk'];
                            } else {
                                // Insert course
                                $db->query(
                                    "INSERT INTO {$dbPrefix}courses 
                                     (program_fk, term_fk, course_number, course_name, is_active, created_at, updated_at) 
                                     VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                                    [$newProgramFk, $destTermPk, $course['course_number'], $course['course_name'], $course['is_active']],
                                    'iissi'
                                );
                                $newCoursePk = $db->getInsertId();
                                $courseMapping[$sourceCoursePk] = $newCoursePk;
                                $copiedCourses++;
                            }
                            
                            // Copy Student Learning Outcomes for this course
                            if ($sourceCoursePk) {
                                $slosResult = $db->query(
                                    "SELECT slo_code, slo_description, sequence_num, is_active 
                                     FROM {$dbPrefix}student_learning_outcomes 
                                     WHERE course_fk = ?",
                                    [$sourceCoursePk],
                                    'i'
                                );
                                $slos = $slosResult->fetchAll();
                                
                                $userId = $_SESSION['user_id'] ?? null;
                                foreach ($slos as $slo) {
                                    $db->query(
                                        "INSERT INTO {$dbPrefix}student_learning_outcomes 
                                         (course_fk, slo_code, slo_description, sequence_num, is_active, created_at, updated_at, created_by_fk, updated_by_fk) 
                                         VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)",
                                        [$newCoursePk, $slo['slo_code'], $slo['slo_description'], $slo['sequence_num'], $slo['is_active'], $userId, $userId],
                                        'issiii'
                                    );
                                    $copiedSLOs++;
                                }
                            }
                        }
                        
                        $db->query("COMMIT");
                        
                        $copiedTotal = $copiedInstitutionalOutcomes + $copiedPrograms + $copiedProgramOutcomes + $copiedCourses + $copiedSLOs;
                        if ($copiedTotal === 0) {
                            $successMessage = "Copy operation completed from '$sourceTermCode' to '$destTermCode'. No new records were created (all records already exist in destination term).";
                        } else {
                            $successMessage = "Copied data from '$sourceTermCode' to '$destTermCode': $copiedInstitutionalOutcomes institutional outcomes, $copiedPrograms programs, $copiedProgramOutcomes program outcomes, $copiedCourses courses, $copiedSLOs student learning outcomes";
                        }
                    } catch (\Exception $e) {
                        $db->query("ROLLBACK");
                        throw $e;
                    }
                }
            }
        } elseif ($action === 'add') {
            $bannerTerm = trim($_POST['banner_term'] ?? '');
            $termCode = trim($_POST['term_code'] ?? '');
            $termName = trim($_POST['term_name'] ?? '');
            $academicYear = trim($_POST['academic_year'] ?? '');
            $startDate = trim($_POST['start_date'] ?? '');
            $endDate = trim($_POST['end_date'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $errors = [];
            if (empty($bannerTerm)) {
                $errors[] = 'Banner term is required';
            }
            if (empty($termCode)) {
                $errors[] = 'Term code is required';
            }
            if (empty($termName)) {
                $errors[] = 'Term name is required';
            }
            // Dates are optional - used for sorting purposes only
            
            // Check for duplicate banner_term
            if (!empty($bannerTerm)) {
                $checkResult = $db->query(
                    "SELECT terms_pk FROM {$dbPrefix}terms WHERE banner_term = ?",
                    [$bannerTerm],
                    's'
                );
                if ($checkResult->fetch()) {
                    $errors[] = 'Banner term already exists';
                }
            }
            
            // Check for duplicate term_code
            if (!empty($termCode)) {
                $checkResult = $db->query(
                    "SELECT terms_pk FROM {$dbPrefix}terms WHERE term_code = ?",
                    [$termCode],
                    's'
                );
                if ($checkResult->fetch()) {
                    $errors[] = 'Term code already exists';
                }
            }
            
            if (empty($errors)) {
                $userId = $_SESSION['user_id'] ?? null;
                $db->query(
                    "INSERT INTO {$dbPrefix}terms (banner_term, term_code, term_name, academic_year, start_date, end_date, is_active, created_at, updated_at, created_by_fk, updated_by_fk)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)",
                    [$bannerTerm, $termCode, $termName, $academicYear, $startDate, $endDate, $isActive, $userId, $userId],
                    'sssssiii'
                );
                $successMessage = 'Term added successfully';
            } else {
                $errorMessage = implode('<br>', $errors);
            }
        } elseif ($action === 'edit') {
            $termsPk = (int)($_POST['terms_pk'] ?? 0);
            $bannerTerm = trim($_POST['banner_term'] ?? '');
            $termCode = trim($_POST['term_code'] ?? '');
            $termName = trim($_POST['term_name'] ?? '');
            $academicYear = trim($_POST['academic_year'] ?? '');
            $startDate = trim($_POST['start_date'] ?? '');
            $endDate = trim($_POST['end_date'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $errors = [];
            if ($termsPk <= 0) {
                $errors[] = 'Invalid term ID';
            }
            if (empty($bannerTerm)) {
                $errors[] = 'Banner term is required';
            }
            if (empty($termCode)) {
                $errors[] = 'Term code is required';
            }
            if (empty($termName)) {
                $errors[] = 'Term name is required';
            }
            // Dates are optional - used for sorting purposes only
            
            // Check for duplicate term_code (excluding current record)
            if (!empty($termCode)) {
                $checkResult = $db->query(
                    "SELECT terms_pk FROM {$dbPrefix}terms WHERE term_code = ? AND terms_pk != ?",
                    [$termCode, $termsPk],
                    'si'
                );
                if ($checkResult->fetch()) {
                    $errors[] = 'Term code already exists';
                }
            }
            
            // Check for duplicate banner_term (excluding current record)
            if (!empty($bannerTerm)) {
                $checkResult = $db->query(
                    "SELECT terms_pk FROM {$dbPrefix}terms WHERE banner_term = ? AND terms_pk != ?",
                    [$bannerTerm, $termsPk],
                    'si'
                );
                if ($checkResult->fetch()) {
                    $errors[] = 'Banner term already exists';
                }
            }
            
            if (empty($errors)) {
                $userId = $_SESSION['user_id'] ?? null;
                $db->query(
                    "UPDATE {$dbPrefix}terms 
                     SET banner_term = ?, term_code = ?, term_name = ?, academic_year = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = NOW(), updated_by_fk = ?
                     WHERE terms_pk = ?",
                    [$bannerTerm, $termCode, $termName, $academicYear, $startDate, $endDate, $isActive, $userId, $termsPk],
                    'ssssssiii'
                );
                $successMessage = 'Term updated successfully';
            } else {
                $errorMessage = implode('<br>', $errors);
            }
        } elseif ($action === 'toggle') {
            $termsPk = (int)($_POST['terms_pk'] ?? 0);
            if ($termsPk > 0) {
                $userId = $_SESSION['user_id'] ?? null;
                $db->query(
                    "UPDATE {$dbPrefix}terms SET is_active = NOT is_active, updated_at = NOW(), updated_by_fk = ? WHERE terms_pk = ?",
                    [$userId, $termsPk],
                    'ii'
                );
                $successMessage = 'Term status toggled successfully';
            }
        } elseif ($action === 'delete') {
            $termsPk = (int)($_POST['terms_pk'] ?? 0);
            if ($termsPk > 0) {
                // Check if term has dependent records
                $checkPrograms = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}programs WHERE term_fk = ?", [$termsPk], 'i');
                $checkCourses = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}courses WHERE term_fk = ?", [$termsPk], 'i');
                $checkIO = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}institutional_outcomes WHERE term_fk = ?", [$termsPk], 'i');
                
                $programsCount = $checkPrograms->fetch()['total'] ?? 0;
                $coursesCount = $checkCourses->fetch()['total'] ?? 0;
                $ioCount = $checkIO->fetch()['total'] ?? 0;
                
                if ($programsCount > 0 || $coursesCount > 0 || $ioCount > 0) {
                    $errorMessage = "Cannot delete term: it has $programsCount programs, $coursesCount courses, and $ioCount institutional outcomes. Delete those first or use 'Clear Term Data'.";
                } else {
                    $db->query("DELETE FROM {$dbPrefix}terms WHERE terms_pk = ?", [$termsPk], 'i');
                    $successMessage = 'Term deleted successfully';
                }
            }
        } elseif ($action === 'clear_term_data') {
            $termCode = trim($_POST['term_code'] ?? '');
            $confirmText = trim($_POST['confirm_text'] ?? '');
            
            if (empty($termCode)) {
                $errorMessage = 'Term code is required';
            } elseif ($confirmText !== 'DELETE') {
                $errorMessage = 'You must type "DELETE" to confirm';
            } else {
                // Verify term exists
                $termCheck = $db->query("SELECT terms_pk FROM {$dbPrefix}terms WHERE term_code = ?", [$termCode], 's');
                $termRow = $termCheck->fetch();
                
                if (!$termRow) {
                    $errorMessage = "Term '$termCode' not found";
                } else {
                    $termPk = $termRow['terms_pk'];
                    
                    // Start transaction
                    $db->query("START TRANSACTION");
                    
                    try {
                        // Initialize counters
                        $deletedAssessments = 0;
                        $deletedEnrollments = 0;
                        $deletedSections = 0;
                        $deletedSLOs = 0;
                        $deletedProgramCourses = 0;
                        $deletedCourses = 0;
                        $deletedProgramOutcomes = 0;
                        $deletedPrograms = 0;
                        $deletedInstitutionalOutcomes = 0;
                        
                        // 1. Delete Assessments (via enrollments for this term)
                        $enrollmentsResult = $db->query(
                            "SELECT enrollment_pk FROM {$dbPrefix}enrollment WHERE term_code = ?",
                            [$termCode],
                            's'
                        );
                        $enrollmentIds = [];
                        while ($row = $enrollmentsResult->fetch()) {
                            $enrollmentIds[] = $row['enrollment_pk'];
                        }
                        
                        if (!empty($enrollmentIds)) {
                            $placeholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
                            $assessmentCountResult = $db->query(
                                "SELECT COUNT(*) as total FROM {$dbPrefix}assessments WHERE enrollment_fk IN ($placeholders)",
                                $enrollmentIds,
                                str_repeat('i', count($enrollmentIds))
                            );
                            $countRow = $assessmentCountResult->fetch();
                            $deletedAssessments = $countRow['total'] ?? 0;
                            
                            if ($deletedAssessments > 0) {
                                $db->query(
                                    "DELETE FROM {$dbPrefix}assessments WHERE enrollment_fk IN ($placeholders)",
                                    $enrollmentIds,
                                    str_repeat('i', count($enrollmentIds))
                                );
                            }
                        }
                        
                        // 2. Delete Enrollments
                        $enrollmentCountResult = $db->query(
                            "SELECT COUNT(*) as total FROM {$dbPrefix}enrollment WHERE term_code = ?",
                            [$termCode],
                            's'
                        );
                        $countRow = $enrollmentCountResult->fetch();
                        $deletedEnrollments = $countRow['total'] ?? 0;
                        
                        if ($deletedEnrollments > 0) {
                            $db->query(
                                "DELETE FROM {$dbPrefix}enrollment WHERE term_code = ?",
                                [$termCode],
                                's'
                            );
                        }
                        
                        // 3. Delete Student Learning Outcomes (for courses in this term)
                        $sloCountResult = $db->query(
                            "SELECT COUNT(*) as total FROM {$dbPrefix}student_learning_outcomes slo
                             INNER JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
                             WHERE c.term_fk = ?",
                            [$termPk],
                            'i'
                        );
                        $countRow = $sloCountResult->fetch();
                        $deletedSLOs = $countRow['total'] ?? 0;
                        
                        if ($deletedSLOs > 0) {
                            $db->query(
                                "DELETE slo FROM {$dbPrefix}student_learning_outcomes slo
                                 INNER JOIN {$dbPrefix}courses c ON slo.course_fk = c.courses_pk
                                 WHERE c.term_fk = ?",
                                [$termPk],
                                'i'
                            );
                        }
                        
                        // 4. Delete Sections (for courses in this term)
                        $sectionCountResult = $db->query(
                            "SELECT COUNT(*) as total FROM {$dbPrefix}sections WHERE term_fk = ?",
                            [$termPk],
                            'i'
                        );
                        $countRow = $sectionCountResult->fetch();
                        $deletedSections = $countRow['total'] ?? 0;
                        
                        if ($deletedSections > 0) {
                            $db->query(
                                "DELETE FROM {$dbPrefix}sections WHERE term_fk = ?",
                                [$termPk],
                                'i'
                            );
                        }
                        
                        // 5. Delete Program-Course relationships (for courses in this term)
                        $programCourseCountResult = $db->query(
                            "SELECT COUNT(*) as total FROM {$dbPrefix}program_courses pc
                             INNER JOIN {$dbPrefix}courses c ON pc.course_fk = c.courses_pk
                             WHERE c.term_fk = ?",
                            [$termPk],
                            'i'
                        );
                        $countRow = $programCourseCountResult->fetch();
                        $deletedProgramCourses = $countRow['total'] ?? 0;
                        
                        if ($deletedProgramCourses > 0) {
                            $db->query(
                                "DELETE pc FROM {$dbPrefix}program_courses pc
                                 INNER JOIN {$dbPrefix}courses c ON pc.course_fk = c.courses_pk
                                 WHERE c.term_fk = ?",
                                [$termPk],
                                'i'
                            );
                        }
                        
                        // 6. Delete Courses
                        $courseCountResult = $db->query(
                            "SELECT COUNT(*) as total FROM {$dbPrefix}courses WHERE term_fk = ?",
                            [$termPk],
                            'i'
                        );
                        $countRow = $courseCountResult->fetch();
                        $deletedCourses = $countRow['total'] ?? 0;
                        
                        if ($deletedCourses > 0) {
                            $db->query(
                                "DELETE FROM {$dbPrefix}courses WHERE term_fk = ?",
                                [$termPk],
                                'i'
                            );
                        }
                        
                        // 7. Delete Program Outcomes (for programs in this term)
                        $programOutcomeCountResult = $db->query(
                            "SELECT COUNT(*) as total FROM {$dbPrefix}program_outcomes po
                             INNER JOIN {$dbPrefix}programs p ON po.program_fk = p.programs_pk
                             WHERE p.term_fk = ?",
                            [$termPk],
                            'i'
                        );
                        $countRow = $programOutcomeCountResult->fetch();
                        $deletedProgramOutcomes = $countRow['total'] ?? 0;
                        
                        if ($deletedProgramOutcomes > 0) {
                            $db->query(
                                "DELETE po FROM {$dbPrefix}program_outcomes po
                                 INNER JOIN {$dbPrefix}programs p ON po.program_fk = p.programs_pk
                                 WHERE p.term_fk = ?",
                                [$termPk],
                                'i'
                            );
                        }
                        
                        // 8. Delete Programs
                        $programCountResult = $db->query(
                            "SELECT COUNT(*) as total FROM {$dbPrefix}programs WHERE term_fk = ?",
                            [$termPk],
                            'i'
                        );
                        $countRow = $programCountResult->fetch();
                        $deletedPrograms = $countRow['total'] ?? 0;
                        
                        if ($deletedPrograms > 0) {
                            $db->query(
                                "DELETE FROM {$dbPrefix}programs WHERE term_fk = ?",
                                [$termPk],
                                'i'
                            );
                        }
                        
                        // 9. Delete Institutional Outcomes
                        $ioCountResult = $db->query(
                            "SELECT COUNT(*) as total FROM {$dbPrefix}institutional_outcomes WHERE term_fk = ?",
                            [$termPk],
                            'i'
                        );
                        $countRow = $ioCountResult->fetch();
                        $deletedInstitutionalOutcomes = $countRow['total'] ?? 0;
                        
                        if ($deletedInstitutionalOutcomes > 0) {
                            $db->query(
                                "DELETE FROM {$dbPrefix}institutional_outcomes WHERE term_fk = ?",
                                [$termPk],
                                'i'
                            );
                        }
                        
                        $db->query("COMMIT");
                        
                        // Build success message
                        $totalDeleted = $deletedAssessments + $deletedEnrollments + $deletedSections + $deletedSLOs + 
                                       $deletedProgramCourses + $deletedCourses + $deletedProgramOutcomes + 
                                       $deletedPrograms + $deletedInstitutionalOutcomes;
                        
                        if ($totalDeleted === 0) {
                            $successMessage = "Clear operation completed for term '$termCode'. No data found to delete.";
                        } else {
                            $details = [];
                            if ($deletedAssessments > 0) $details[] = "$deletedAssessments assessments";
                            if ($deletedEnrollments > 0) $details[] = "$deletedEnrollments enrollments";
                            if ($deletedSections > 0) $details[] = "$deletedSections sections";
                            if ($deletedSLOs > 0) $details[] = "$deletedSLOs SLOs";
                            if ($deletedProgramCourses > 0) $details[] = "$deletedProgramCourses program-course links";
                            if ($deletedCourses > 0) $details[] = "$deletedCourses courses";
                            if ($deletedProgramOutcomes > 0) $details[] = "$deletedProgramOutcomes program outcomes";
                            if ($deletedPrograms > 0) $details[] = "$deletedPrograms programs";
                            if ($deletedInstitutionalOutcomes > 0) $details[] = "$deletedInstitutionalOutcomes institutional outcomes";
                            
                            $successMessage = "Cleared data for term '$termCode': " . implode(', ', $details) . " deleted";
                        }
                    } catch (\Exception $e) {
                        $db->query("ROLLBACK");
                        throw $e;
                    }
                }
            }
        }
    } catch (\Exception $e) {
        $errorMessage = 'Error: ' . $e->getMessage();
        // Log the error for debugging
        error_log("Terms page error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
}

// Calculate statistics
$statsResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}terms");
$statsRow = $statsResult->fetch();
$totalTerms = $statsRow['total'] ?? 0;

$activeResult = $db->query("SELECT COUNT(*) as total FROM {$dbPrefix}terms WHERE is_active = 1");
$activeRow = $activeResult->fetch();
$activeTerms = $activeRow['total'] ?? 0;

$coursesResult = $db->query("SELECT COUNT(DISTINCT term_fk) as total FROM {$dbPrefix}courses WHERE is_active = 1");
$coursesRow = $coursesResult->fetch();
$termsWithCourses = $coursesRow['total'] ?? 0;

// Get all terms for dropdowns
$allTermsResult = $db->query("
    SELECT term_code, term_name 
    FROM {$dbPrefix}terms 
    ORDER BY term_code ASC
");
$allTerms = $allTermsResult->fetchAll();

// Load theme system
require_once __DIR__ . '/../system/Core/ThemeLoader.php';
use Mosaic\Core\ThemeLoader;
use Mosaic\Core\ThemeContext;

$context = new ThemeContext([
    'layout' => 'admin',
    'pageTitle' => 'Terms Management',
    'currentPage' => 'admin_terms',
    'breadcrumbs' => [
        ['url' => BASE_URL, 'label' => 'Home'],
        ['label' => 'Terms']
    ]
]);

$theme = ThemeLoader::getActiveTheme(null, 'admin');
$theme->showHeader($context);
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<style>
    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
</style>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Terms</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle" aria-hidden="true"></i> <?= $successMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Terms Table -->
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-calendar-week" aria-hidden="true"></i> Academic Terms
                </h2>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#addTermModal" aria-label="Add new term">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add Term
                    </button>
                    <button type="button" class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#copyTermModal" aria-label="Copy term data">
                        <i class="fas fa-copy" aria-hidden="true"></i> Copy Term Data
                    </button>
                    <button type="button" class="btn btn-danger btn-sm me-1" data-bs-toggle="modal" data-bs-target="#clearTermModal" aria-label="Clear term data">
                        <i class="fas fa-trash" aria-hidden="true"></i> Clear Term Data
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table id="termsTable" class="table table-bordered table-striped table-hover" aria-label="Academic terms data table">
                    <caption class="visually-hidden">List of academic terms with filtering and sorting capabilities</caption>
                    <thead>
                        <tr>
                            <th scope="col">Banner Term</th>
                            <th scope="col">Term Code</th>
                            <th scope="col">Term Name</th>
                            <th scope="col">Academic Year</th>
                            <th scope="col">Start Date</th>
                            <th scope="col">End Date</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Term Modal -->
<div class="modal fade" id="addTermModal" tabindex="-1" aria-labelledby="addTermModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <span class="modal-title" id="addTermModalLabel"><i class="fas fa-plus" aria-hidden="true"></i> Add Term</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close dialog"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="banner_term" class="form-label">Banner Term <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="banner_term" name="banner_term" maxlength="20" required aria-required="true" placeholder="e.g., 202533" aria-describedby="bannerTermHelp">
                        <small id="bannerTermHelp" class="text-muted">Banner system term code</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="term_code" class="form-label">Term Code <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="term_code" name="term_code" maxlength="50" required aria-required="true" placeholder="e.g., 202630" aria-describedby="termCodeHelp">
                        <small id="termCodeHelp" class="text-muted">Institutional term code</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="term_name" class="form-label">Term Name <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="term_name" name="term_name" maxlength="100" required aria-required="true" placeholder="e.g., Spring 2026">
                    </div>
                    
                    <div class="mb-3">
                        <label for="academic_year" class="form-label">Academic Year</label>
                        <input type="text" class="form-control" id="academic_year" name="academic_year" maxlength="20" placeholder="e.g., 2025-2026">
                        <small class="text-muted">Optional. Format: YYYY-YYYY</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date">
                            <small class="text-muted">Optional. Used for sorting terms.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date">
                            <small class="text-muted">Optional. Used for sorting terms.</small>
                        </div>
                    </div>
                    
                    <fieldset class="mb-3">
                        <legend class="h6">Status</legend>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Term Modal -->
<div class="modal fade" id="editTermModal" tabindex="-1" aria-labelledby="editTermModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <span class="modal-title" id="editTermModalLabel"><i class="fas fa-edit" aria-hidden="true"></i> Edit Term</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close dialog"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="terms_pk" id="edit_terms_pk">
                    
                    <div class="mb-3">
                        <label for="edit_banner_term" class="form-label">Banner Term <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="edit_banner_term" name="banner_term" maxlength="20" required aria-required="true" aria-describedby="editBannerTermHelp">
                        <small id="editBannerTermHelp" class="text-muted">Banner system term code (e.g., 202533)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_term_code" class="form-label">Term Code <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="edit_term_code" name="term_code" maxlength="50" required aria-required="true" aria-describedby="editTermCodeHelp">
                        <small id="editTermCodeHelp" class="text-muted">Institutional term code (e.g., 202630)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_term_name" class="form-label">Term Name <span class="text-danger" aria-label="required">*</span></label>
                        <input type="text" class="form-control" id="edit_term_name" name="term_name" maxlength="100" required aria-required="true">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_academic_year" class="form-label">Academic Year</label>
                        <input type="text" class="form-control" id="edit_academic_year" name="academic_year" maxlength="20" placeholder="e.g., 2025-2026">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="edit_start_date" name="start_date">
                            <small class="text-muted">Optional. Used for sorting terms.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edit_end_date" name="end_date">
                            <small class="text-muted">Optional. Used for sorting terms.</small>
                        </div>
                    </div>
                    
                    <fieldset class="mb-3">
                        <legend class="h6">Status</legend>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <!-- LEFT SIDE: Destructive Actions -->
                    <div>
                        <button type="button" class="btn btn-danger" onclick="confirmDeleteTerm()" aria-label="Delete term">
                            <i class="fas fa-trash" aria-hidden="true"></i> Delete
                        </button>
                    </div>
                    <!-- RIGHT SIDE: Primary Actions -->
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Form (hidden) -->
<form method="POST" id="toggleForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="toggle">
    <input type="hidden" name="terms_pk" id="toggle_terms_pk">
</form>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="terms_pk" id="delete_terms_pk">
</form>

<!-- Copy Term Data Modal -->
<div class="modal fade" id="copyTermModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <span class="modal-title"><i class="fas fa-copy"></i> Copy Term Data</span>
            </div>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="copy_term_data">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="source_term_code" class="form-label">Source Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="source_term_code" name="source_term_code" required>
                                <option value="">Select Source Term</option>
                                <?php foreach ($allTerms as $term): ?>
                                    <option value="<?= htmlspecialchars($term['term_code']) ?>">
                                        <?= htmlspecialchars($term['term_code']) ?> - <?= htmlspecialchars($term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="dest_term_code" class="form-label">Destination Term <span class="text-danger">*</span></label>
                            <select class="form-select" id="dest_term_code" name="dest_term_code" required>
                                <option value="">Select Destination Term</option>
                                <?php foreach ($allTerms as $term): ?>
                                    <option value="<?= htmlspecialchars($term['term_code']) ?>">
                                        <?= htmlspecialchars($term['term_code']) ?> - <?= htmlspecialchars($term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>What will be copied:</strong>
                        <ul class="mb-0 mt-2">
                            <li><i class="fas fa-trophy text-primary"></i> Institutional Outcomes</li>
                            <li><i class="fas fa-graduation-cap text-success"></i> Programs and their Program Outcomes</li>
                            <li><i class="fas fa-book text-info"></i> Courses and their Student Learning Outcomes</li>
                        </ul>
                        <p class="mb-0 mt-2"><small>Note: Enrollments and assessments are NOT copied. Use CSV import to populate enrollment data.</small></p>
                    </div>
                    
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> This will copy institutional outcomes, programs, program outcomes, courses, and student learning outcomes from the source term to the destination term. Existing records with the same codes will be skipped.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-copy"></i> Copy Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear Term Data Modal -->
<div class="modal fade" id="clearTermModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <span class="modal-title"><i class="fas fa-trash"></i> Clear Term Data</span>
            </div>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="clear_term_data">
                    
                    <div class="mb-3">
                        <label for="clear_term_code" class="form-label">Term to Clear <span class="text-danger">*</span></label>
                        <select class="form-select" id="clear_term_code" name="term_code" required>
                            <option value="">Select Term</option>
                            <?php foreach ($allTerms as $term): ?>
                                <option value="<?= htmlspecialchars($term['term_code']) ?>">
                                    <?= htmlspecialchars($term['term_code']) ?> - <?= htmlspecialchars($term['term_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <strong>WARNING:</strong> This will permanently delete ALL data for this term:
                        <ul class="mb-0 mt-2">
                            <li>All assessments</li>
                            <li>All enrollments</li>
                            <li>All sections</li>
                            <li>All student learning outcomes (SLOs)</li>
                            <li>All courses</li>
                            <li>All program outcomes (PSLOs)</li>
                            <li>All programs</li>
                            <li>All institutional outcomes (ISLOs)</li>
                        </ul>
                        <p class="mb-0 mt-2"><strong>This action cannot be undone! The term record itself will remain.</strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_text" class="form-label">Type <code>DELETE</code> to confirm <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="confirm_text" name="confirm_text" required placeholder="Type DELETE in all caps">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $theme->showFooter($context); ?>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    // Setup - add a text input to each header cell (second row)
    $('#termsTable thead tr:eq(1) td').each(function(i) {
        var title = $('#termsTable thead tr:eq(0) th:eq(' + i + ')').text();
        if (title !== 'Actions' && title !== 'ID') {
            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" aria-label="Filter by ' + title + '" />');
        } else {
            $(this).html('');
        }
    });
    
    var table = $('#termsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '<?= BASE_URL ?>administration/terms_data.php',
        dom: 'Brtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        order: [[0, 'desc']],
        pageLength: 25,
        orderCellsTop: true, // Use the top row for sorting
        columnDefs: [
            { targets: [7], orderable: false, searchable: false }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search terms..."
        },
        initComplete: function() {
            // Apply the search - target the second header row where filters are
            var api = this.api();
            api.columns().every(function(colIdx) {
                var column = this;
                // Find input in the second header row (tr:eq(1)) for this column
                $('input', $('#termsTable thead tr:eq(1) td').eq(colIdx)).on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        }
    });
});

function editTerm(term) {
    $('#edit_terms_pk').val(term.terms_pk);
    $('#edit_banner_term').val(term.banner_term || '');
    $('#edit_term_code').val(term.term_code);
    $('#edit_term_name').val(term.term_name);
    $('#edit_academic_year').val(term.academic_year || '');
    $('#edit_start_date').val(term.start_date || '');
    $('#edit_end_date').val(term.end_date || '');
    $('#edit_is_active').prop('checked', term.is_active == 1);
    new bootstrap.Modal(document.getElementById('editTermModal')).show();
}

function toggleStatus(id, termCode) {
    if (confirm('Toggle status for term "' + termCode + '"?')) {
        $('#toggle_terms_pk').val(id);
        $('#toggleForm').submit();
    }
}

function deleteTerm(id, termCode) {
    if (confirm('Are you sure you want to DELETE term "' + termCode + '"? This action cannot be undone.')) {
        $('#delete_terms_pk').val(id);
        $('#deleteForm').submit();
    }
}

function confirmDeleteTerm() {
    const termPk = $('#edit_terms_pk').val();
    const termCode = $('#edit_term_code').val();
    deleteTerm(termPk, termCode);
}
</script>
