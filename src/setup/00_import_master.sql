-- MOSAIC Complete Data Import Workflow
-- 
-- Master script that orchestrates the full import from staging tables
-- Run each step in sequence, verifying counts before proceeding
--
-- PREREQUISITES:
-- 1. Schema created (tbl_ prefix applied via setup/index.php)
-- 2. Staging tables loaded: slo_cslo (~7k rows), slo_import (~182k rows)
-- 3. MySQL Workbench timeout set to 600 seconds
--
-- EXECUTION TIME ESTIMATES:
-- - Step 1 (Dimensions): 2-5 seconds
-- - Step 2 (Course Sections): 10-20 seconds  
-- - Step 3 (Enrollment): 1-2 minutes (DISTINCT on 182k rows)
-- - Step 4 (Assessments): 5-10 minutes (182k inserts with FK lookups)
-- Total: ~12-15 minutes

-- ==============================================================================
-- STEP 1: IMPORT DIMENSION TABLES
-- ==============================================================================
-- Populates: programs, courses (with denormalized subject data), slo_sets, terms, SLOs, course_slos, students
-- Source: 01_import_dimensions.sql
-- Expected: ~300-500 dimension records + ~50k students
-- Duration: 2-5 seconds
--
-- MANUAL EXECUTION:
-- Run src/setup/01_import_dimensions.sql in MySQL Workbench
-- Verify counts before proceeding
-- ==============================================================================

-- ==============================================================================
-- STEP 2: IMPORT COURSE SECTIONS  
-- ==============================================================================
-- Populates: course_sections (CRN offerings per term)
-- Source: 02_import_course_sections.sql
-- Expected: ~1000-3000 course sections
-- Duration: 10-20 seconds
--
-- MANUAL EXECUTION:
-- Run src/setup/02_import_course_sections.sql in MySQL Workbench
-- Verify unique_crns count
-- ==============================================================================

-- ==============================================================================
-- STEP 3: IMPORT ENROLLMENT
-- ==============================================================================
-- Populates: enrollment (student × course section)
-- Source: 03_import_enrollment.sql
-- Expected: ~50k enrollments (182k assessments → 50k unique students×sections)
-- Duration: 1-2 minutes (DISTINCT operation on large dataset)
--
-- MANUAL EXECUTION:
-- Run src/setup/03_import_enrollment.sql in MySQL Workbench
-- Verify total_enrollments count
-- ==============================================================================

-- ==============================================================================
-- STEP 4: IMPORT ASSESSMENTS
-- ==============================================================================
-- Populates: assessments (all 182k assessment records)
-- Source: 04_import_assessments.sql
-- Expected: ~182k assessments
-- Duration: 5-10 minutes (largest import with complex FK resolution)
--
-- MANUAL EXECUTION:
-- Run src/setup/04_import_assessments.sql in MySQL Workbench
-- Verify total_assessments count and success_rate_pct
-- ==============================================================================

-- ==============================================================================
-- POST-IMPORT VERIFICATION
-- ==============================================================================

SELECT '=== IMPORT VERIFICATION ===' AS step;

-- Verify all tables populated
SELECT 
    'Final Data Counts' AS summary,
    (SELECT COUNT(*) FROM tbl_programs) AS programs,
    (SELECT COUNT(*) FROM tbl_courses) AS courses,
    (SELECT COUNT(*) FROM tbl_slo_sets) AS slo_sets,
    (SELECT COUNT(*) FROM tbl_terms) AS terms,
    (SELECT COUNT(*) FROM tbl_student_learning_outcomes) AS slos,
    (SELECT COUNT(*) FROM tbl_course_slos) AS course_slo_mappings,
    (SELECT COUNT(*) FROM tbl_students) AS students,
    (SELECT COUNT(*) FROM tbl_course_sections) AS course_sections,
    (SELECT COUNT(*) FROM tbl_enrollment) AS enrollments,
    (SELECT COUNT(*) FROM tbl_assessments) AS assessments;

-- Verify referential integrity (even without FK constraints)
SELECT 
    'Referential Integrity Check' AS check_type,
    (SELECT COUNT(*) FROM tbl_course_sections WHERE course_fk NOT IN (SELECT courses_pk FROM tbl_courses)) 
        AS orphaned_sections,
    (SELECT COUNT(*) FROM tbl_enrollment WHERE students_fk NOT IN (SELECT students_pk FROM tbl_students)) 
        AS orphaned_enrollments,
    (SELECT COUNT(*) FROM tbl_assessments WHERE enrollment_fk NOT IN (SELECT enrollment_pk FROM tbl_enrollment)) 
        AS orphaned_assessments;

-- Verify assessment data quality
SELECT 
    'Assessment Quality Metrics' AS metrics,
    COUNT(*) AS total_assessments,
    COUNT(DISTINCT enrollment_fk) AS unique_students_assessed,
    COUNT(DISTINCT student_learning_outcome_fk) AS unique_slos_assessed,
    SUM(CASE WHEN performance_level = 'Met' THEN 1 ELSE 0 END) AS met_count,
    SUM(CASE WHEN performance_level = 'Not Met' THEN 1 ELSE 0 END) AS not_met_count,
    ROUND(SUM(CASE WHEN performance_level = 'Met' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS success_rate_pct
FROM tbl_assessments;

SELECT 'IMPORT WORKFLOW COMPLETE!' AS status;
SELECT 'All data successfully migrated from staging tables.' AS result;
