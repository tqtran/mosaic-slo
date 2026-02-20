-- Add Unique Constraints to Existing Schema
-- Run this to add constraints without dropping/recreating tables
-- 
-- IMPORTANT: Replace 'tbl_' prefix if yours is different
-- Find/Replace: 'tbl_' with your prefix

-- STEP 1: Add unique constraints to users table
ALTER TABLE tbl_users 
    ADD UNIQUE KEY uk_user_id (user_id),
    ADD UNIQUE KEY uk_email (email);

-- STEP 2: Add unique constraint to roles table
ALTER TABLE tbl_roles 
    ADD UNIQUE KEY uk_role_name (role_name);

-- STEP 3: Add unique constraint to user_roles table
ALTER TABLE tbl_user_roles 
    ADD UNIQUE KEY uk_user_role_context (user_fk, role_fk, context_type, context_id);

-- STEP 4: Add unique constraints to institution table
ALTER TABLE tbl_institution 
    ADD UNIQUE KEY uk_institution_code (institution_code),
    ADD UNIQUE KEY uk_lti_consumer_key (lti_consumer_key);

-- STEP 5: Add unique constraint to institutional_outcomes table
ALTER TABLE tbl_institutional_outcomes 
    ADD UNIQUE KEY uk_institution_code (institution_fk, code);

-- STEP 6: Add unique constraint to programs table (CRITICAL FOR IMPORT)
ALTER TABLE tbl_programs 
    ADD UNIQUE KEY uk_program_code (program_code);

-- STEP 7: Add unique constraint to program_outcomes table
ALTER TABLE tbl_program_outcomes 
    ADD UNIQUE KEY uk_program_code (program_fk, code);

-- STEP 8: Add unique constraint to slo_sets table (CRITICAL FOR IMPORT)
ALTER TABLE tbl_slo_sets 
    ADD UNIQUE KEY uk_set_code (set_code);

-- STEP 9: Add unique constraint to student_learning_outcomes table (CRITICAL FOR IMPORT)
ALTER TABLE tbl_student_learning_outcomes 
    ADD UNIQUE KEY uk_slo_code (slo_code);

-- STEP 10: Add unique constraint to terms table (CRITICAL FOR IMPORT)
ALTER TABLE tbl_terms 
    ADD UNIQUE KEY uk_term_code (term_code);

-- STEP 11: Add unique constraint to students table (CRITICAL FOR IMPORT)
ALTER TABLE tbl_students 
    ADD UNIQUE KEY uk_student_id (student_id);

-- STEP 12: Add unique constraint to enrollment table (CRITICAL FOR IMPORT)
ALTER TABLE tbl_enrollment 
    ADD UNIQUE KEY uk_enrollment (students_fk, term_code, crn);

-- STEP 13: Add unique constraint to lti_nonces table
ALTER TABLE tbl_lti_nonces 
    ADD UNIQUE KEY uk_nonce (consumer_key, nonce_value);

-- Success message
SELECT 'All unique constraints added successfully!' AS status;
SELECT 'You can now run the import script.' AS next_step;
