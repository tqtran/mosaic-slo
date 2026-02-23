-- Assessment Enrollment Architecture Migration
-- This migration converts assessments table from course_section_fk + students_fk to enrollment_fk
-- WARNING: This is a CRITICAL migration. Back up your database before running!

-- ============================================================================
-- STEP 1: Verify Enrollment Data Exists
-- ============================================================================
-- Check that all students with assessments have corresponding enrollment records
SELECT 
    a.assessments_pk,
    a.course_section_fk,
    a.students_fk,
    cs.crn,
    s.c_number,
    cs.term_code,
    CASE WHEN e.enrollment_pk IS NULL THEN 'MISSING ENROLLMENT' ELSE 'OK' END as status
FROM assessments a
INNER JOIN course_sections cs ON a.course_section_fk = cs.course_sections_pk
INNER JOIN students s ON a.students_fk = s.students_pk
LEFT JOIN enrollment e ON e.term_code = cs.term_code 
    AND e.crn = cs.crn 
    AND e.student_fk = s.students_pk
WHERE e.enrollment_pk IS NULL;

-- If you see MISSING ENROLLMENT, you need to create enrollment records first!
-- See STEP 1a before proceeding.

-- ============================================================================
-- STEP 1a: Create Missing Enrollment Records (if needed)
-- ============================================================================
-- This inserts enrollment records for any assessments without matching enrollments
INSERT INTO enrollment (term_code, crn, student_fk, course_section_fk, enrollment_status, enrollment_date, created_at, updated_at)
SELECT DISTINCT
    cs.term_code,
    cs.crn,
    a.students_fk,
    a.course_section_fk,
    'completed' as enrollment_status,
    COALESCE(a.assessed_date, a.created_at) as enrollment_date,
    NOW() as created_at,
    NOW() as updated_at
FROM assessments a
INNER JOIN course_sections cs ON a.course_section_fk = cs.course_sections_pk
LEFT JOIN enrollment e ON e.term_code = cs.term_code 
    AND e.crn = cs.crn 
    AND e.student_fk = a.students_fk
WHERE e.enrollment_pk IS NULL;

-- Verify no missing enrollments remain:
-- Re-run the SELECT from STEP 1. Should return 0 rows.

-- ============================================================================
-- STEP 2: Add enrollment_fk Column
-- ============================================================================
ALTER TABLE assessments 
ADD COLUMN enrollment_fk INT NULL AFTER assessments_pk;

-- Verify column added:
DESCRIBE assessments;

-- ============================================================================
-- STEP 3: Populate enrollment_fk from Existing Data
-- ============================================================================
UPDATE assessments a
INNER JOIN course_sections cs ON a.course_section_fk = cs.course_sections_pk
INNER JOIN enrollment e ON e.term_code = cs.term_code 
    AND e.crn = cs.crn 
    AND e.student_fk = a.students_fk
SET a.enrollment_fk = e.enrollment_pk;

-- Verify all assessments have enrollment_fk populated:
SELECT COUNT(*) as total_assessments,
       SUM(CASE WHEN enrollment_fk IS NULL THEN 1 ELSE 0 END) as missing_enrollment_fk,
       SUM(CASE WHEN enrollment_fk IS NOT NULL THEN 1 ELSE 0 END) as populated_enrollment_fk
FROM assessments;

-- If missing_enrollment_fk > 0, investigate before proceeding!

-- ============================================================================
-- STEP 4: Make enrollment_fk NOT NULL
-- ============================================================================
-- Only proceed if all enrollment_fk values are populated!
ALTER TABLE assessments 
MODIFY COLUMN enrollment_fk INT NOT NULL;

-- ============================================================================
-- STEP 5: Add Foreign Key Constraint
-- ============================================================================
ALTER TABLE assessments
ADD CONSTRAINT fk_assessments_enrollment 
    FOREIGN KEY (enrollment_fk) 
    REFERENCES enrollment(enrollment_pk)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- ============================================================================
-- STEP 6: Create Index on enrollment_fk
-- ============================================================================
ALTER TABLE assessments
ADD INDEX idx_enrollment_fk (enrollment_fk);

-- ============================================================================
-- STEP 7: Drop Old Columns and Constraints
-- ============================================================================
-- First, drop any foreign key constraints on old columns
-- (Check constraint names in your database - these are examples)
-- ALTER TABLE assessments DROP FOREIGN KEY fk_assessments_course_section;
-- ALTER TABLE assessments DROP FOREIGN KEY fk_assessments_students;

-- Drop the old columns
ALTER TABLE assessments DROP COLUMN course_section_fk;
ALTER TABLE assessments DROP COLUMN students_fk;

-- ============================================================================
-- STEP 8: Verify Final Structure
-- ============================================================================
DESCRIBE assessments;
-- Should show:
-- - enrollment_fk INT NOT NULL
-- - NO course_section_fk
-- - NO students_fk

-- ============================================================================
-- STEP 9: Test Queries
-- ============================================================================
-- Test that assessments can be queried with enrollment
SELECT 
    a.assessments_pk,
    e.term_code,
    e.crn,
    s.c_number,
    CONCAT(s.student_last_name, ', ', s.student_first_name) as student_name,
    slo.slo_code,
    a.score_value,
    a.achievement_level,
    a.assessed_date
FROM assessments a
INNER JOIN enrollment e ON a.enrollment_fk = e.enrollment_pk
INNER JOIN students s ON e.student_fk = s.students_pk
LEFT JOIN student_learning_outcomes slo ON a.student_learning_outcome_fk = slo.student_learning_outcomes_pk
LIMIT 10;

-- Test lookup by term + CRN (common LTI pattern)
SELECT 
    a.assessments_pk,
    s.c_number,
    a.score_value
FROM assessments a
INNER JOIN enrollment e ON a.enrollment_fk = e.enrollment_pk
INNER JOIN students s ON e.student_fk = s.students_pk
WHERE e.term_code = '202630' 
  AND e.crn = '12345';

-- ============================================================================
-- ROLLBACK (if needed - must be done BEFORE STEP 7!)
-- ============================================================================
-- If you need to rollback BEFORE dropping old columns:
/*
ALTER TABLE assessments DROP FOREIGN KEY fk_assessments_enrollment;
ALTER TABLE assessments DROP COLUMN enrollment_fk;
-- Your original assessments table is restored
*/

-- ============================================================================
-- NOTES
-- ============================================================================
-- 1. This migration assumes course_sections.term_code exists
--    If not, you need to add it first or modify the JOIN logic
-- 2. Enrollment uniqueness is enforced by (term_code, crn, student_fk)
-- 3. After this migration, CSV imports must use new format:
--    term_code, crn, c_number (instead of crn, student_id)
-- 4. All application code must be updated BEFORE running STEP 7
--    (dropping old columns). Deploy code first, then migrate.
-- 5. Consider running this during a maintenance window
-- 6. Monitor application logs after deployment for any lingering references

-- ============================================================================
-- SUCCESS VERIFICATION
-- ============================================================================
-- After migration, verify:
-- [ ] All assessments have enrollment_fk
-- [ ] No NULL enrollment_fk values
-- [ ] Foreign key constraint exists
-- [ ] Old columns removed
-- [ ] Application loads without errors
-- [ ] Can add new assessments via UI
-- [ ] Can edit existing assessments
-- [ ] CSV import works with new format
-- [ ] DataTables display shows term and CRN
-- [ ] No references to course_section_fk or students_fk in logs
