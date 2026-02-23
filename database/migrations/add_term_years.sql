-- Migration: Add Term Years Support
-- Date: 2026-02-22
-- Description: Adds term_years table and updates programs table to support academic terms

-- Create term_years table
CREATE TABLE IF NOT EXISTS tbl_term_years (
    term_years_pk INT AUTO_INCREMENT PRIMARY KEY,
    term_name VARCHAR(50) NOT NULL,
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by_fk INT,
    updated_by_fk INT,
    UNIQUE KEY uk_term_name (term_name)
);

-- Insert default term year for existing data
INSERT INTO tbl_term_years (term_name, start_date, end_date, is_active, is_current) 
VALUES ('2025-2026', '2025-08-01', '2026-05-31', 1, 1);

-- Add term_year_fk to programs table
ALTER TABLE tbl_programs 
ADD COLUMN term_year_fk INT NOT NULL DEFAULT 1 AFTER programs_pk;

-- Update unique constraint to include term_year_fk
ALTER TABLE tbl_programs DROP INDEX uk_program_code;
ALTER TABLE tbl_programs ADD UNIQUE KEY uk_term_program_code (term_year_fk, program_code);

-- Add program_fk to courses table
ALTER TABLE tbl_courses 
ADD COLUMN program_fk INT NOT NULL DEFAULT 1 AFTER courses_pk;

-- Update unique constraint on courses
ALTER TABLE tbl_courses DROP INDEX IF EXISTS uk_course_name_number;
ALTER TABLE tbl_courses DROP INDEX IF EXISTS uk_term_course_number;
ALTER TABLE tbl_courses ADD UNIQUE KEY uk_program_course_number (program_fk, course_number);

-- Remove default values after migration
ALTER TABLE tbl_programs ALTER COLUMN term_year_fk DROP DEFAULT;
ALTER TABLE tbl_courses ALTER COLUMN program_fk DROP DEFAULT;
