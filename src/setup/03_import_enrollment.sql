-- MOSAIC Enrollment Import
-- 
-- Populates enrollment from slo_import staging table
-- Deduplicates 182k assessment records to ~50k unique student enrollments
--
-- IMPORTANT: Table Prefix Configuration
-- This file uses 'tbl_' prefix by default.
-- Find/Replace: 'tbl_' with your prefix (or '' for no prefix)

START TRANSACTION;

-- Extract unique enrollments (student + course section)
-- DISTINCT required: 182k assessment rows -> ~50k unique enrollments
INSERT IGNORE INTO tbl_enrollment (
    students_fk,
    course_section_fk,
    enrollment_date,
    is_active
)
SELECT DISTINCT
    s.students_pk,
    cs.course_sections_pk,
    NOW() AS enrollment_date,
    1 AS is_active
FROM slo_import i
-- Link to students table via student ID
INNER JOIN tbl_students s ON s.student_id = i.`StudentID`
-- Link to course_sections via CRN
INNER JOIN tbl_course_sections cs ON cs.crn = i.`CRN`
-- Link to terms to ensure matching term (CRNs may repeat across terms)
INNER JOIN tbl_terms t ON t.term_code = i.`Term` AND cs.term_fk = t.terms_pk
WHERE i.`StudentID` IS NOT NULL
  AND i.`CRN` IS NOT NULL
  AND i.`Term` IS NOT NULL;

-- Verify imported count
SELECT 
    CONCAT('ENROLLMENT IMPORT COMPLETE: Inserted ', ROW_COUNT(), ' unique enrollments') AS status;

SELECT 
    'Enrollment Summary' AS summary,
    (SELECT COUNT(*) FROM tbl_enrollment) AS total_enrollments,
    (SELECT COUNT(DISTINCT students_fk) FROM tbl_enrollment) AS unique_students,
    (SELECT COUNT(DISTINCT course_section_fk) FROM tbl_enrollment) AS sections_enrolled,
    (SELECT COUNT(*) / (SELECT COUNT(DISTINCT students_fk) FROM tbl_enrollment) 
     FROM tbl_enrollment) AS avg_enrollments_per_student;

COMMIT;

SELECT 'ENROLLMENT IMPORT COMPLETED!' AS status;
SELECT 'Next: Run import_assessments.sql to load assessment data.' AS next_step;
