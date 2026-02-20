-- MOSAIC Seed Data Import Script (SQL Version)
-- 
-- Imports data from slo_import staging table into MOSAIC tables.
-- Assumes 180k+ records already loaded into slo_import table.
-- 
-- Run this after setup completes to populate the database.
-- 
-- Usage:
--   mysql -u username -p database_name < import_seed_data.sql
--   OR import via phpMyAdmin
--
-- IMPORTANT: Table Prefix Configuration
-- This file uses 'tbl_' prefix by default.
-- If you have NO prefix, find/replace: 'tbl_' with '' (empty string)
-- If you have different prefix, find/replace: 'tbl_' with your prefix

-- Increase timeouts for large dataset import
SET SESSION wait_timeout = 28800;
SET SESSION interactive_timeout = 28800;
SET SESSION net_read_timeout = 300;
SET SESSION net_write_timeout = 300;

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, AUTOCOMMIT=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;

START TRANSACTION;

-- STEP 1: Extract unique programs from Program column
INSERT IGNORE INTO tbl_programs (program_code, program_name, degree_type, is_active)
SELECT DISTINCT
    UPPER(REPLACE(`Program`, ' ', '_')) AS program_code,
    `Program` AS program_name,
    'AS' AS degree_type,
    1 AS is_active
FROM slo_import
WHERE `Program` IS NOT NULL AND `Program` != '';

SELECT CONCAT('STEP 1 COMPLETE: Inserted ', ROW_COUNT(), ' unique programs') AS status;


-- STEP 2: Extract unique SLO sets (academic years)
INSERT IGNORE INTO tbl_slo_sets (set_code, set_name, set_type, is_active)
SELECT DISTINCT
    `Academic Year` AS set_code,
    CONCAT('Academic Year ', `Academic Year`) AS set_name,
    'year' AS set_type,
    1 AS is_active
FROM slo_import
WHERE `Academic Year` IS NOT NULL AND `Academic Year` != '';

SELECT CONCAT('STEP 2 COMPLETE: Inserted ', ROW_COUNT(), ' unique SLO sets') AS status;


-- STEP 3: Extract unique terms
INSERT IGNORE INTO tbl_terms (slo_set_code, term_code, term_name, term_year, is_active)
SELECT DISTINCT
    `Academic Year` AS slo_set_code,
    `Term` AS term_code,
    `Term` AS term_name,
    CASE 
        WHEN `Semester` LIKE '%Spring%' OR `Semester` LIKE '%Winter%' 
        THEN CAST(CONCAT('20', SUBSTRING_INDEX(`Academic Year`, '-', -1)) AS UNSIGNED)
        ELSE CAST(CONCAT('20', SUBSTRING_INDEX(`Academic Year`, '-', 1)) AS UNSIGNED)
    END AS term_year,
    1 AS is_active
FROM slo_import
WHERE `Term` IS NOT NULL AND `Term` != ''
  AND `Academic Year` IS NOT NULL AND `Academic Year` != '';

SELECT CONCAT('STEP 3 COMPLETE: Inserted ', ROW_COUNT(), ' unique terms') AS status;


-- STEP 4: Extract unique student learning outcomes
INSERT IGNORE INTO tbl_student_learning_outcomes (slo_set_code, slo_code, description, assessment_method, sequence_num, is_active)
SELECT DISTINCT
    `Academic Year` AS slo_set_code,
    `CSLO` AS slo_code,
    COALESCE(NULLIF(`SLO Language`, ''), `CSLO`) AS description,
    COALESCE(NULLIF(`Assessment`, ''), 'Assignment') AS assessment_method,
    0 AS sequence_num,
    1 AS is_active
FROM slo_import
WHERE `CSLO` IS NOT NULL AND `CSLO` != ''
  AND `Academic Year` IS NOT NULL AND `Academic Year` != '';

SELECT CONCAT('STEP 4 COMPLETE: Inserted ', ROW_COUNT(), ' unique SLOs') AS status;


-- STEP 5: Extract unique students
INSERT IGNORE INTO tbl_students (student_id, is_active)
SELECT DISTINCT
    `StudentID` AS student_id,
    1 AS is_active
FROM slo_import
WHERE `StudentID` IS NOT NULL AND `StudentID` != '';

SELECT CONCAT('STEP 5 COMPLETE: Inserted ', ROW_COUNT(), ' unique students') AS status;


-- STEP 6: Extract unique enrollment records (with student FK lookup)
-- NOTE: CSV is assessment-grain (182k rows), must deduplicate to enrollment-grain (~50k rows)
INSERT IGNORE INTO tbl_enrollment (
    students_fk, term_code, crn, academic_year, semester, 
    course_code, course_title, course_modality, program_name, 
    subject_code, subject_name, enrollment_status, enrollment_date
)
SELECT DISTINCT
    st.students_pk,
    i.`Term` AS term_code,
    i.`CRN` AS crn,
    i.`Academic Year` AS academic_year,
    COALESCE(i.`Semester`, '') AS semester,
    COALESCE(i.`Course`, '') AS course_code,
    COALESCE(i.`Title`, '') AS course_title,
    COALESCE(i.`Modality`, '') AS course_modality,
    COALESCE(i.`Program`, '') AS program_name,
    COALESCE(i.`Sub Code`, '') AS subject_code,
    COALESCE(i.`Subject`, '') AS subject_name,
    CASE WHEN i.`Course Status` = 'Active' THEN '1' ELSE '2' END AS enrollment_status,
    CURDATE() AS enrollment_date
FROM slo_import i
INNER JOIN tbl_students st ON st.student_id = i.`StudentID`
WHERE i.`StudentID` IS NOT NULL AND i.`StudentID` != ''
  AND i.`CRN` IS NOT NULL AND i.`CRN` != ''
  AND i.`Term` IS NOT NULL AND i.`Term` != '';

SELECT CONCAT('STEP 6 COMPLETE: Inserted ', ROW_COUNT(), ' unique enrollment records') AS status;


-- STEP 7: Insert assessments (with FK lookups)
-- This may take several minutes for large datasets
INSERT INTO tbl_assessments (enrollment_fk, student_learning_outcome_fk, score_value, achievement_level, assessed_date)
SELECT 
    e.enrollment_pk,
    slo.student_learning_outcomes_pk,
    CASE 
        WHEN i.`Met/Not Met` LIKE '%Partially%' THEN 0.5
        WHEN i.`Met/Not Met` LIKE '%Not%' THEN 0
        ELSE 1
    END AS score_value,
    CASE 
        WHEN i.`Met/Not Met` LIKE '%Partially%' THEN 'partially_met'
        WHEN i.`Met/Not Met` LIKE '%Not%' THEN 'not_met'
        ELSE 'met'
    END AS achievement_level,
    CURDATE() AS assessed_date
FROM slo_import i
INNER JOIN tbl_students st ON st.student_id = i.`StudentID`
INNER JOIN tbl_enrollment e 
    ON e.students_fk = st.students_pk
    AND e.term_code = i.`Term` 
    AND e.crn = i.`CRN`
INNER JOIN tbl_student_learning_outcomes slo 
    ON slo.slo_code = i.`CSLO`
WHERE i.`StudentID` IS NOT NULL AND i.`StudentID` != ''
  AND i.`CRN` IS NOT NULL AND i.`CRN` != ''
  AND i.`CSLO` IS NOT NULL AND i.`CSLO` != ''
  AND i.`Term` IS NOT NULL AND i.`Term` != '';

SELECT CONCAT('STEP 7 COMPLETE: Inserted ', ROW_COUNT(), ' assessment records') AS status;


-- STEP 8: Verify imported data counts
SELECT 
    'Final Database Counts' AS summary,
    (SELECT COUNT(*) FROM tbl_programs) AS programs,
    (SELECT COUNT(*) FROM tbl_slo_sets) AS slo_sets,
    (SELECT COUNT(*) FROM tbl_terms) AS terms,
    (SELECT COUNT(*) FROM tbl_student_learning_outcomes) AS student_learning_outcomes,
    (SELECT COUNT(*) FROM tbl_students) AS students,
    (SELECT COUNT(*) FROM tbl_enrollment) AS enrollment_records,
    (SELECT COUNT(*) FROM tbl_assessments) AS assessment_records;


-- Commit transaction
COMMIT;

-- Restore original settings
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

-- Success message
SELECT 'IMPORT COMPLETED SUCCESSFULLY!' AS status;
SELECT 'Optional: Drop the staging table with: DROP TABLE slo_import;' AS note;
