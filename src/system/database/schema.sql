-- MOSAIC Database Schema
-- MySQL 8.0+ Required
-- Character Set: UTF8MB4
-- Collation: utf8mb4_unicode_ci
-- Storage Engine: InnoDB
--
-- All tables prefixed with tbl_ for easy find/replace during setup
-- Setup script will replace tbl_ with configured prefix

-- Drop existing tables if they exist (for clean reinstall)
DROP TABLE IF EXISTS tbl_lti_nonces;
DROP TABLE IF EXISTS tbl_user_roles;
DROP TABLE IF EXISTS tbl_roles;
DROP TABLE IF EXISTS tbl_assessments;
DROP TABLE IF EXISTS tbl_enrollment;
DROP TABLE IF EXISTS tbl_course_sections;
DROP TABLE IF EXISTS tbl_course_slos;
DROP TABLE IF EXISTS tbl_student_learning_outcomes;
DROP TABLE IF EXISTS tbl_courses;
DROP TABLE IF EXISTS tbl_students;
DROP TABLE IF EXISTS tbl_terms;
DROP TABLE IF EXISTS tbl_slo_sets;
DROP TABLE IF EXISTS tbl_programs;
DROP TABLE IF EXISTS tbl_institution;
DROP TABLE IF EXISTS tbl_users;
DROP TABLE IF EXISTS tbl_audit_log;
DROP TABLE IF EXISTS tbl_error_log;
DROP TABLE IF EXISTS tbl_security_log;


-- ====================
-- SYSTEM TABLES
-- ====================

CREATE TABLE tbl_users (
    users_pk INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_id (user_id),
    UNIQUE KEY uk_email (email)
);

CREATE TABLE tbl_roles (
    roles_pk INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_role_name (role_name)
);

CREATE TABLE tbl_user_roles (
    user_roles_pk INT AUTO_INCREMENT PRIMARY KEY,
    user_fk INT NOT NULL,
    role_fk INT NOT NULL,
    context_type VARCHAR(50),
    context_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_role_context (user_fk, role_fk, context_type, context_id)
);

CREATE TABLE tbl_institution (
    institution_pk INT AUTO_INCREMENT PRIMARY KEY,
    institution_name VARCHAR(255) NOT NULL,
    institution_code VARCHAR(50) NOT NULL,
    lti_consumer_key VARCHAR(255),
    lti_consumer_secret VARCHAR(255),
    lti_consumer_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_institution_code (institution_code),
    UNIQUE KEY uk_lti_consumer_key (lti_consumer_key)
);


-- ====================
-- ACADEMIC STRUCTURE
-- ====================

CREATE TABLE tbl_programs (
    programs_pk INT AUTO_INCREMENT PRIMARY KEY,
    program_code VARCHAR(50) NOT NULL,
    program_name VARCHAR(255) NOT NULL,
    degree_type VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by_fk INT,
    updated_by_fk INT,
    UNIQUE KEY uk_program_code (program_code)
);

CREATE TABLE tbl_courses (
    courses_pk INT AUTO_INCREMENT PRIMARY KEY,
    program_fk INT,
    course_code VARCHAR(50) NOT NULL,
    course_number VARCHAR(20),
    course_title VARCHAR(255) NOT NULL,
    subject_code VARCHAR(20),
    subject_name VARCHAR(255),
    discipline VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_course_code (course_code)
);

CREATE TABLE tbl_slo_sets (
    slo_sets_pk INT AUTO_INCREMENT PRIMARY KEY,
    set_code VARCHAR(50) NOT NULL,
    set_name VARCHAR(255) NOT NULL,
    set_type VARCHAR(20) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by_fk INT,
    updated_by_fk INT,
    UNIQUE KEY uk_set_code (set_code)
);

CREATE TABLE tbl_terms (
    terms_pk INT AUTO_INCREMENT PRIMARY KEY,
    slo_set_fk INT,
    term_code VARCHAR(50) NOT NULL,
    term_name VARCHAR(100) NOT NULL,
    term_year INT NOT NULL,
    semester VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_term_code (term_code)
);

CREATE TABLE tbl_student_learning_outcomes (
    student_learning_outcomes_pk INT AUTO_INCREMENT PRIMARY KEY,
    slo_set_fk INT,
    slo_code VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    assessment_method VARCHAR(255),
    sequence_num INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by_fk INT,
    updated_by_fk INT,
    UNIQUE KEY uk_slo_code (slo_code)
);

CREATE TABLE tbl_course_slos (
    course_slos_pk INT AUTO_INCREMENT PRIMARY KEY,
    course_fk INT NOT NULL,
    student_learning_outcome_fk INT NOT NULL,
    sequence_num INT DEFAULT 0,
    is_required BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_course_slo (course_fk, student_learning_outcome_fk)
);


-- ====================
-- STUDENT DATA (ENCRYPTED)
-- ====================

CREATE TABLE tbl_students (
    students_pk INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(255) NOT NULL COMMENT 'Encrypted student ID',
    student_first_name VARCHAR(255) COMMENT 'Encrypted first name',
    student_last_name VARCHAR(255) COMMENT 'Encrypted last name',
    email VARCHAR(255) COMMENT 'Encrypted email',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_id (student_id)
);


-- ====================
-- COURSE SECTIONS & ENROLLMENT
-- ====================

CREATE TABLE tbl_course_sections (
    course_sections_pk INT AUTO_INCREMENT PRIMARY KEY,
    course_fk INT NOT NULL,
    term_fk INT NOT NULL,
    crn VARCHAR(20) NOT NULL,
    section_number VARCHAR(10),
    modality VARCHAR(50),
    instructor_name VARCHAR(255),
    max_enrollment INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_crn_term (crn, term_fk)
);

CREATE TABLE tbl_enrollment (
    enrollment_pk INT AUTO_INCREMENT PRIMARY KEY,
    students_fk INT NOT NULL,
    course_section_fk INT NOT NULL,
    enrollment_status VARCHAR(20) DEFAULT 'active',
    enrollment_date DATE,
    completion_date DATE,
    grade VARCHAR(5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_section (students_fk, course_section_fk)
);


-- ====================
-- ASSESSMENTS
-- ====================

CREATE TABLE tbl_assessments (
    assessments_pk INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_fk INT NOT NULL,
    student_learning_outcome_fk INT NOT NULL,
    score_value DECIMAL(5,2),
    achievement_level VARCHAR(20) DEFAULT 'pending',
    assessment_method VARCHAR(255),
    notes TEXT,
    assessed_date DATE,
    is_finalized BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    assessed_by_fk INT,
    UNIQUE KEY uk_enrollment_slo (enrollment_fk, student_learning_outcome_fk)
);


-- ====================
-- LOGGING TABLES
-- ====================

CREATE TABLE tbl_audit_log (
    audit_log_pk INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    record_pk INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    changed_by_fk INT,
    changed_data JSON,
    old_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tbl_error_log (
    error_log_pk INT AUTO_INCREMENT PRIMARY KEY,
    error_type VARCHAR(100) NOT NULL,
    error_message TEXT NOT NULL,
    error_code VARCHAR(50),
    stack_trace TEXT,
    file_path VARCHAR(255),
    line_number INT,
    request_uri TEXT,
    request_method VARCHAR(10),
    request_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    severity VARCHAR(20) DEFAULT 'error',
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP NULL,
    resolved_by_fk INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_fk INT
);

CREATE TABLE tbl_security_log (
    security_log_pk INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    event_description TEXT NOT NULL,
    username VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_uri TEXT,
    severity VARCHAR(20) DEFAULT 'info',
    is_threat BOOLEAN DEFAULT FALSE,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_fk INT
);

CREATE TABLE tbl_lti_nonces (
    lti_nonces_pk INT AUTO_INCREMENT PRIMARY KEY,
    consumer_key VARCHAR(255) NOT NULL,
    nonce_value VARCHAR(255) NOT NULL,
    timestamp BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nonce (consumer_key, nonce_value)
);

INSERT INTO tbl_roles (role_name, description) VALUES
    ('admin', 'System administrator with full access'),
    ('department_chair', 'Department chair with department-level access'),
    ('program_coordinator', 'Program coordinator with program-level access'),
    ('instructor', 'Course instructor with course-level access'),
    ('assessment_coordinator', 'Assessment coordinator with reporting access');