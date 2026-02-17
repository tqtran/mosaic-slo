-- ============================================================================
-- Migration: Simplify Enrollment Table
-- Date: 2026-02-16
-- Description: 
--   - Remove dependency on course_sections table
--   - Store CRN and term_code directly in enrollment table
--   - Update students table to use c_number as primary identifier
-- ============================================================================

-- BACKUP YOUR DATABASE BEFORE RUNNING THIS MIGRATION!

-- ============================================================================
-- Step 1: Update students table to use c_number
-- ============================================================================

-- Add c_number column to students if it doesn't exist
ALTER TABLE students 
ADD COLUMN c_number VARCHAR(50) COMMENT 'Student C-Number from Banner SIS' AFTER students_pk;

-- For existing records, copy student_id to c_number if c_number is null
UPDATE students SET c_number = student_id WHERE c_number IS NULL OR c_number = '';

-- Add unique index on c_number
ALTER TABLE students 
ADD UNIQUE INDEX idx_c_number (c_number);

-- Make c_number NOT NULL after population
ALTER TABLE students 
MODIFY COLUMN c_number VARCHAR(50) NOT NULL COMMENT 'Student C-Number from Banner SIS';

-- Make other student fields nullable (may not have full data on import)
ALTER TABLE students 
MODIFY COLUMN first_name VARCHAR(100) NULL,
MODIFY COLUMN last_name VARCHAR(100) NULL,
MODIFY COLUMN student_id VARCHAR(50) NULL COMMENT 'Alternative student ID if needed';

-- ============================================================================
-- Step 2: Update enrollment table to store CRN and term_code directly
-- ============================================================================

-- Add new columns
ALTER TABLE enrollment 
ADD COLUMN term_code VARCHAR(20) COMMENT 'Term code from Banner (e.g., 202630)' AFTER enrollment_pk,
ADD COLUMN crn VARCHAR(20) COMMENT 'Course Reference Number from Banner' AFTER term_code;

-- Populate new columns from existing course_sections data (if available)
UPDATE enrollment e
LEFT JOIN course_sections cs ON e.course_section_fk = cs.course_sections_pk
LEFT JOIN terms t ON cs.term_fk = t.terms_pk
SET 
    e.term_code = t.term_code,
    e.crn = cs.crn
WHERE e.course_section_fk IS NOT NULL;

-- Make columns NOT NULL after population
ALTER TABLE enrollment 
MODIFY COLUMN term_code VARCHAR(20) NOT NULL COMMENT 'Term code from Banner (e.g., 202630)',
MODIFY COLUMN crn VARCHAR(20) NOT NULL COMMENT 'Course Reference Number from Banner';

-- Add 'withdrawn' to enrollment_status enum if not already present
ALTER TABLE enrollment 
MODIFY COLUMN enrollment_status ENUM('enrolled', 'dropped', 'completed', 'withdrawn') DEFAULT 'enrolled';

-- Drop old foreign key constraint
ALTER TABLE enrollment 
DROP FOREIGN KEY enrollment_ibfk_1;

-- Make course_section_fk nullable (optional reference)
ALTER TABLE enrollment 
MODIFY COLUMN course_section_fk INT COMMENT 'Optional link to course_sections if available';

-- Re-add foreign key as SET NULL instead of CASCADE
ALTER TABLE enrollment 
ADD CONSTRAINT enrollment_ibfk_1 
FOREIGN KEY (course_section_fk) REFERENCES course_sections(course_sections_pk) ON DELETE SET NULL;

-- Drop old unique constraint
ALTER TABLE enrollment 
DROP INDEX unique_enrollment;

-- Add new unique constraint based on term_code, crn, student_fk
ALTER TABLE enrollment 
ADD UNIQUE KEY unique_enrollment (term_code, crn, student_fk);

-- Add indexes for performance
ALTER TABLE enrollment 
ADD INDEX idx_term_code (term_code),
ADD INDEX idx_crn (crn);

-- ============================================================================
-- Migration Complete
-- ============================================================================

-- Verify results:
-- SELECT COUNT(*) FROM enrollment WHERE term_code IS NULL OR crn IS NULL;
-- Should return 0

-- SELECT e.*, s.c_number
-- FROM enrollment e
-- JOIN students s ON e.student_fk = s.students_pk
-- LIMIT 10;
