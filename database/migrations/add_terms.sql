-- Migration: Add Terms Table
-- Date: 2026-02-22
-- Description: Creates tbl_terms as a child of tbl_term_years and adds term_fk to course_sections
--
-- This migration creates a Terms table to allow semester-level organization (Fall 2025, Spring 2026, etc.)
-- within Term Years (2025-2026, etc.). Course sections (CRNs) are tied to specific terms.

-- Create tbl_terms table
CREATE TABLE IF NOT EXISTS tbl_terms (
    terms_pk INT AUTO_INCREMENT PRIMARY KEY,
    term_year_fk INT NOT NULL,
    term_name VARCHAR(50) NOT NULL COMMENT 'Fall 2025, Spring 2026, Summer 2026',
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by_fk INT,
    updated_by_fk INT,
    UNIQUE KEY uk_term_name (term_year_fk, term_name)
);

-- Insert default terms for current term year (2025-2026)
-- Assumes term_years_pk = 1 for 2025-2026
INSERT INTO tbl_terms (term_year_fk, term_name, start_date, end_date, is_active)
VALUES 
    (1, 'Fall 2025', '2025-08-15', '2025-12-15', TRUE),
    (1, 'Spring 2026', '2026-01-10', '2026-05-10', TRUE),
    (1, 'Summer 2026', '2026-06-01', '2026-08-01', TRUE);

-- Add term_fk to tbl_course_sections
ALTER TABLE tbl_course_sections 
ADD COLUMN term_fk INT NOT NULL DEFAULT 1 AFTER course_fk;

-- Remove default after migration
ALTER TABLE tbl_course_sections 
ALTER COLUMN term_fk DROP DEFAULT;

-- Note: Existing CRNs will be assigned to the first term (Fall 2025)
-- Administrators should review and update CRN term assignments as needed
