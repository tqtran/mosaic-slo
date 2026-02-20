-- MOSAIC Dimension Tables Import
-- 
-- Populates dimension tables from slo_import and slo_cslo staging tables
-- Skips course_sections, enrollment, and assessments (too large for single transaction)
--
-- IMPORTANT: Table Prefix Configuration
-- This file uses 'tbl_' prefix by default.
-- Find/Replace: 'tbl_' with your prefix (or '' for no prefix)

START TRANSACTION;

-- STEP 1: Extract unique programs from both sources (slo_cslo first)
INSERT IGNORE INTO tbl_programs (program_code, program_name, degree_type, is_active)
SELECT DISTINCT
    UPPER(REPLACE(`Program`, ' ', '_')) AS program_code,
    `Program` AS program_name,
    'AS' AS degree_type,
    1 AS is_active
FROM slo_cslo
WHERE `Program` IS NOT NULL AND `Program` != ''
UNION
SELECT DISTINCT
    UPPER(REPLACE(`Program`, ' ', '_')) AS program_code,
    `Program` AS program_name,
    'AS' AS degree_type,
    1 AS is_active
FROM slo_import
WHERE `Program` IS NOT NULL AND `Program` != '';

SELECT CONCAT('STEP 1 COMPLETE: Inserted ', CAST((SELECT COUNT(*) FROM tbl_programs) AS CHAR), ' total programs') AS status;


-- STEP 2: Extract unique courses from both sources
-- Subject data denormalized into course record
-- First from slo_cslo (course catalog)
INSERT IGNORE INTO tbl_courses (program_fk, course_code, course_number, course_title, subject_code, subject_name, discipline, is_active)
SELECT DISTINCT
    p.programs_pk,
    c.`CRS ID` AS course_code,
    c.`CRSE_NUMB` AS course_number,
    c.`CRS TITLE` AS course_title,
    c.`SUBJ` AS subject_code,
    c.`Discipline` AS subject_name,
    c.`Discipline` AS discipline,
    1 AS is_active
FROM slo_cslo c
LEFT JOIN tbl_programs p ON p.program_code = UPPER(REPLACE(c.`Program`, ' ', '_'))
WHERE c.`CRS ID` IS NOT NULL AND c.`CRS ID` != '';

-- Then from slo_import (assessment data)
INSERT IGNORE INTO tbl_courses (program_fk, course_code, course_number, course_title, subject_code, subject_name, discipline, is_active)
SELECT DISTINCT
    p.programs_pk,
    i.`Course` AS course_code,
    NULL AS course_number,
    i.`Title` AS course_title,
    i.`Sub Code` AS subject_code,
    i.`Subject` AS subject_name,
    i.`Subject` AS discipline,
    1 AS is_active
FROM slo_import i
LEFT JOIN tbl_programs p ON p.program_code = UPPER(REPLACE(i.`Program`, ' ', '_'))
WHERE i.`Course` IS NOT NULL AND i.`Course` != '';

SELECT CONCAT('STEP 2 COMPLETE: Inserted ', CAST((SELECT COUNT(*) FROM tbl_courses) AS CHAR), ' total courses') AS status;


-- STEP 3: Extract unique SLO sets (academic years) from slo_import only
INSERT IGNORE INTO tbl_slo_sets (set_code, set_name, set_type, is_active)
SELECT DISTINCT
    `Academic Year` AS set_code,
    CONCAT('Academic Year ', `Academic Year`) AS set_name,
    'year' AS set_type,
    1 AS is_active
FROM slo_import
WHERE `Academic Year` IS NOT NULL AND `Academic Year` != '';

SELECT CONCAT('STEP 3 COMPLETE: Inserted ', CAST((SELECT COUNT(*) FROM tbl_slo_sets) AS CHAR), ' total SLO sets') AS status;


-- STEP 4: Extract unique terms from slo_import only
INSERT IGNORE INTO tbl_terms (slo_set_fk, term_code, term_name, term_year, semester, is_active)
SELECT DISTINCT
    slo_set.slo_sets_pk AS slo_set_fk,
    i.`Term` AS term_code,
    i.`Term` AS term_name,
    CASE 
        WHEN i.`Semester` LIKE '%Spring%' OR i.`Semester` LIKE '%Winter%' 
        THEN CAST(CONCAT('20', SUBSTRING_INDEX(i.`Academic Year`, '-', -1)) AS UNSIGNED)
        ELSE CAST(CONCAT('20', SUBSTRING_INDEX(i.`Academic Year`, '-', 1)) AS UNSIGNED)
    END AS term_year,
    i.`Semester` AS semester,
    1 AS is_active
FROM slo_import i
INNER JOIN tbl_slo_sets slo_set ON slo_set.set_code = i.`Academic Year`
WHERE i.`Term` IS NOT NULL AND i.`Term` != ''
  AND i.`Academic Year` IS NOT NULL AND i.`Academic Year` != '';

SELECT CONCAT('STEP 4 COMPLETE: Inserted ', CAST((SELECT COUNT(*) FROM tbl_terms) AS CHAR), ' total terms') AS status;


-- STEP 5: Extract unique student learning outcomes from slo_import
-- slo_import has codes like "CSLO 1", "CSLO 2" with full text in "SLO Language"
INSERT IGNORE INTO tbl_student_learning_outcomes (slo_set_fk, slo_code, description, assessment_method, is_active)
SELECT DISTINCT
    slo_set.slo_sets_pk AS slo_set_fk,
    i.`CSLO` AS slo_code,
    COALESCE(NULLIF(i.`SLO Language`, ''), i.`CSLO`) AS description,
    COALESCE(NULLIF(i.`Assessment`, ''), 'Assignment') AS assessment_method,
    1 AS is_active
FROM slo_import i
INNER JOIN tbl_slo_sets slo_set ON slo_set.set_code = i.`Academic Year`
WHERE i.`CSLO` IS NOT NULL AND i.`CSLO` != ''
  AND i.`Academic Year` IS NOT NULL AND i.`Academic Year` != '';

SELECT CONCAT('STEP 5 COMPLETE: Inserted ', CAST((SELECT COUNT(*) FROM tbl_student_learning_outcomes) AS CHAR), ' total SLOs') AS status;


-- STEP 6: Create course-SLO mappings from slo_import (actual assessments)
-- This identifies which courses actually assess which SLOs
INSERT IGNORE INTO tbl_course_slos (course_fk, student_learning_outcome_fk, is_required)
SELECT DISTINCT
    c.courses_pk,
    slo.student_learning_outcomes_pk,
    1 AS is_required
FROM slo_import i
INNER JOIN tbl_courses c ON c.course_code = i.`Course`
INNER JOIN tbl_student_learning_outcomes slo ON slo.slo_code = i.`CSLO`
WHERE i.`Course` IS NOT NULL AND i.`CSLO` IS NOT NULL;

SELECT CONCAT('STEP 6 COMPLETE: Inserted ', CAST((SELECT COUNT(*) FROM tbl_course_slos) AS CHAR), ' total course-SLO mappings') AS status;


-- STEP 7: Extract unique students from slo_import only
INSERT IGNORE INTO tbl_students (student_id, is_active)
SELECT DISTINCT
    `StudentID` AS student_id,
    1 AS is_active
FROM slo_import
WHERE `StudentID` IS NOT NULL AND `StudentID` != '';

SELECT CONCAT('STEP 7 COMPLETE: Inserted ', CAST((SELECT COUNT(*) FROM tbl_students) AS CHAR), ' total students') AS status;


-- Verify imported dimension counts
SELECT 
    'Dimension Tables Populated' AS summary,
    (SELECT COUNT(*) FROM tbl_programs) AS programs,
    (SELECT COUNT(*) FROM tbl_courses) AS courses,
    (SELECT COUNT(*) FROM tbl_slo_sets) AS slo_sets,
    (SELECT COUNT(*) FROM tbl_terms) AS terms,
    (SELECT COUNT(*) FROM tbl_student_learning_outcomes) AS student_learning_outcomes,
    (SELECT COUNT(*) FROM tbl_course_slos) AS course_slo_mappings,
    (SELECT COUNT(*) FROM tbl_students) AS students;

COMMIT;

SELECT 'DIMENSION IMPORT COMPLETED!' AS status;
SELECT 'Next: Import course_sections, enrollment, and assessments separately.' AS next_step;
