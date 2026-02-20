-- MOSAIC Course Sections Import
-- 
-- Populates course sections from slo_import staging table
-- Each CRN represents a specific course offering in a specific term
--
-- IMPORTANT: Table Prefix Configuration
-- This file uses 'tbl_' prefix by default.
-- Find/Replace: 'tbl_' with your prefix (or '' for no prefix)

START TRANSACTION;

-- Extract unique course sections (CRN + term + course)
-- Note: Instructor data not available in current CSV structure
INSERT IGNORE INTO tbl_course_sections (
    course_fk, 
    term_fk, 
    crn, 
    modality,
    is_active
)
SELECT DISTINCT
    c.courses_pk,
    t.terms_pk,
    i.`CRN` AS crn,
    COALESCE(i.`Modality`, 'In-Person') AS modality,
    1 AS is_active
FROM slo_import i
-- Link to courses table via course code
INNER JOIN tbl_courses c ON c.course_code = i.`Course`
-- Link to terms table via term code
INNER JOIN tbl_terms t ON t.term_code = i.`Term`
WHERE i.`CRN` IS NOT NULL 
  AND i.`CRN` != ''
  AND i.`Course` IS NOT NULL
  AND i.`Term` IS NOT NULL;

-- Verify imported count
SELECT 
    CONCAT('COURSE SECTIONS IMPORT COMPLETE: Inserted ', ROW_COUNT(), ' unique course sections') AS status;

SELECT 
    'Course Sections Summary' AS summary,
    (SELECT COUNT(*) FROM tbl_course_sections) AS total_sections,
    (SELECT COUNT(DISTINCT crn) FROM tbl_course_sections) AS unique_crns,
    (SELECT COUNT(DISTINCT term_fk) FROM tbl_course_sections) AS terms_covered,
    (SELECT COUNT(DISTINCT course_fk) FROM tbl_course_sections) AS courses_offered;

COMMIT;

SELECT 'COURSE SECTIONS IMPORT COMPLETED!' AS status;
SELECT 'Next: Run import_enrollment.sql to link students to sections.' AS next_step;
