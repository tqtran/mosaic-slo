-- MOSAIC Assessments Import
-- 
-- Populates assessments from slo_import staging table
-- All 182k assessment records imported (no deduplication)
--
-- IMPORTANT: Table Prefix Configuration
-- This file uses 'tbl_' prefix by default.
-- Find/Replace: 'tbl_' with your prefix (or '' for no prefix)

START TRANSACTION;

-- Import all assessment records
-- Note: This is the full 182k rows from slo_import
INSERT IGNORE INTO tbl_assessments (
    enrollment_fk,
    student_learning_outcome_fk,
    score,
    score_max,
    performance_level,
    assessment_method,
    assessment_date,
    feedback,
    is_active
)
SELECT 
    e.enrollment_pk,
    slo.student_learning_outcomes_pk,
    CASE 
        WHEN i.`Met` = 'Met' THEN 1.0
        WHEN i.`Met` = 'Not Met' THEN 0.0
        ELSE NULL
    END AS score,
    1.0 AS score_max,
    i.`Met` AS performance_level,
    COALESCE(NULLIF(i.`Assessment`, ''), 'Assignment') AS assessment_method,
    NOW() AS assessment_date,
    NULL AS feedback,
    1 AS is_active
FROM slo_import i
-- Resolve enrollment FK via student + course section
INNER JOIN tbl_students s ON s.student_id = i.`StudentID`
INNER JOIN tbl_course_sections cs ON cs.crn = i.`CRN`
INNER JOIN tbl_terms t ON t.term_code = i.`Term` AND cs.term_fk = t.terms_pk
INNER JOIN tbl_enrollment e ON e.students_fk = s.students_pk 
                            AND e.course_section_fk = cs.course_sections_pk
-- Resolve SLO FK via CSLO code
INNER JOIN tbl_student_learning_outcomes slo ON slo.slo_code = i.`CSLO`
WHERE i.`StudentID` IS NOT NULL
  AND i.`CRN` IS NOT NULL
  AND i.`Term` IS NOT NULL
  AND i.`CSLO` IS NOT NULL
  AND i.`Met` IS NOT NULL;

-- Verify imported count
SELECT 
    CONCAT('ASSESSMENTS IMPORT COMPLETE: Inserted ', ROW_COUNT(), ' assessment records') AS status;

SELECT 
    'Assessment Summary' AS summary,
    (SELECT COUNT(*) FROM tbl_assessments) AS total_assessments,
    (SELECT COUNT(DISTINCT enrollment_fk) FROM tbl_assessments) AS students_assessed,
    (SELECT COUNT(DISTINCT student_learning_outcome_fk) FROM tbl_assessments) AS slos_assessed,
    (SELECT COUNT(*) FROM tbl_assessments WHERE performance_level = 'Met') AS met_count,
    (SELECT COUNT(*) FROM tbl_assessments WHERE performance_level = 'Not Met') AS not_met_count,
    (SELECT COUNT(*) FROM tbl_assessments WHERE performance_level = 'Met') * 100.0 / 
        (SELECT COUNT(*) FROM tbl_assessments) AS success_rate_pct;

COMMIT;

SELECT 'ASSESSMENTS IMPORT COMPLETED!' AS status;
SELECT 'All data import operations complete!' AS next_step;
