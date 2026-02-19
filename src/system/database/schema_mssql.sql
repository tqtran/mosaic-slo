-- MOSAIC Database Schema - Microsoft SQL Server
-- SQL Server 2016+ Required
-- Character Set: UTF8
-- Collation: SQL_Latin1_General_CP1_CI_AS

-- Note: SQL Server uses different syntax from MySQL
-- - IDENTITY(1,1) instead of AUTO_INCREMENT
-- - BIT instead of BOOLEAN
-- - NVARCHAR for Unicode support
-- - GETDATE() instead of CURRENT_TIMESTAMP
-- - No ON UPDATE triggers (must use triggers)

-- Drop existing tables if they exist (for clean reinstall)
IF OBJECT_ID('dbo.lti_nonces', 'U') IS NOT NULL DROP TABLE dbo.lti_nonces;
IF OBJECT_ID('dbo.security_log', 'U') IS NOT NULL DROP TABLE dbo.security_log;
IF OBJECT_ID('dbo.error_log', 'U') IS NOT NULL DROP TABLE dbo.error_log;
IF OBJECT_ID('dbo.audit_log', 'U') IS NOT NULL DROP TABLE dbo.audit_log;
IF OBJECT_ID('dbo.user_roles', 'U') IS NOT NULL DROP TABLE dbo.user_roles;
IF OBJECT_ID('dbo.roles', 'U') IS NOT NULL DROP TABLE dbo.roles;
IF OBJECT_ID('dbo.assessments', 'U') IS NOT NULL DROP TABLE dbo.assessments;
IF OBJECT_ID('dbo.enrollment', 'U') IS NOT NULL DROP TABLE dbo.enrollment;
IF OBJECT_ID('dbo.terms', 'U') IS NOT NULL DROP TABLE dbo.terms;
IF OBJECT_ID('dbo.student_learning_outcomes', 'U') IS NOT NULL DROP TABLE dbo.student_learning_outcomes;
IF OBJECT_ID('dbo.slo_sets', 'U') IS NOT NULL DROP TABLE dbo.slo_sets;
IF OBJECT_ID('dbo.program_outcomes', 'U') IS NOT NULL DROP TABLE dbo.program_outcomes;
IF OBJECT_ID('dbo.programs', 'U') IS NOT NULL DROP TABLE dbo.programs;
IF OBJECT_ID('dbo.institutional_outcomes', 'U') IS NOT NULL DROP TABLE dbo.institutional_outcomes;
IF OBJECT_ID('dbo.institution', 'U') IS NOT NULL DROP TABLE dbo.institution;
IF OBJECT_ID('dbo.users', 'U') IS NOT NULL DROP TABLE dbo.users;
GO

-- ============================================================================
-- 1. USER MANAGEMENT (Created First - Referenced by Audit Fields)
-- ============================================================================

CREATE TABLE users (
    users_pk INT IDENTITY(1,1) PRIMARY KEY,
    user_id NVARCHAR(100) NOT NULL UNIQUE,
    first_name NVARCHAR(100) NOT NULL,
    last_name NVARCHAR(100) NOT NULL,
    email NVARCHAR(255) NOT NULL UNIQUE,
    password_hash NVARCHAR(255) NOT NULL,
    is_active BIT DEFAULT 1,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE()
);
CREATE INDEX idx_user_id ON users(user_id);
CREATE INDEX idx_email ON users(email);
CREATE INDEX idx_is_active ON users(is_active);
GO

CREATE TABLE roles (
    roles_pk INT IDENTITY(1,1) PRIMARY KEY,
    role_name NVARCHAR(50) NOT NULL UNIQUE,
    description NVARCHAR(MAX),
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE()
);
CREATE INDEX idx_role_name ON roles(role_name);
GO

CREATE TABLE user_roles (
    user_roles_pk INT IDENTITY(1,1) PRIMARY KEY,
    user_fk INT NOT NULL,
    role_fk INT NOT NULL,
    context_type NVARCHAR(50),
    context_id INT,
    created_at DATETIME DEFAULT GETDATE()
);
CREATE INDEX idx_user_fk ON user_roles(user_fk);
CREATE INDEX idx_role_fk ON user_roles(role_fk);
CREATE INDEX idx_context ON user_roles(context_type, context_id);
GO

-- ============================================================================
-- 2. ROOT ENTITY
-- ============================================================================

CREATE TABLE institution (
    institution_pk INT IDENTITY(1,1) PRIMARY KEY,
    institution_name NVARCHAR(255) NOT NULL,
    institution_code NVARCHAR(50) NOT NULL UNIQUE,
    lti_consumer_key NVARCHAR(255),
    lti_consumer_secret NVARCHAR(255),
    lti_consumer_name NVARCHAR(100),
    is_active BIT DEFAULT 1,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE()
);
CREATE INDEX idx_institution_code ON institution(institution_code);
CREATE UNIQUE INDEX unique_lti_key ON institution(lti_consumer_key) WHERE lti_consumer_key IS NOT NULL;
GO

-- ============================================================================
-- 3. OUTCOMES HIERARCHY
-- ============================================================================

CREATE TABLE institutional_outcomes (
    institutional_outcomes_pk INT IDENTITY(1,1) PRIMARY KEY,
    institution_fk INT,
    code NVARCHAR(50) NOT NULL UNIQUE,
    description NVARCHAR(MAX) NOT NULL,
    sequence_num INT DEFAULT 0,
    is_active BIT DEFAULT 1,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    created_by_fk INT,
    updated_by_fk INT
);
CREATE INDEX idx_code ON institutional_outcomes(code);
CREATE INDEX idx_institution_fk ON institutional_outcomes(institution_fk);
CREATE INDEX idx_sequence_num ON institutional_outcomes(sequence_num);
CREATE INDEX idx_is_active ON institutional_outcomes(is_active);
GO

-- ============================================================================
-- 4. PROGRAMS
-- ============================================================================

CREATE TABLE programs (
    programs_pk INT IDENTITY(1,1) PRIMARY KEY,
    program_code NVARCHAR(50) NOT NULL UNIQUE,
    program_name NVARCHAR(255) NOT NULL,
    degree_type NVARCHAR(50),
    is_active BIT DEFAULT 1,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    created_by_fk INT,
    updated_by_fk INT
);
CREATE INDEX idx_program_code ON programs(program_code);
CREATE INDEX idx_is_active ON programs(is_active);
GO

CREATE TABLE program_outcomes (
    program_outcomes_pk INT IDENTITY(1,1) PRIMARY KEY,
    program_fk INT,
    institutional_outcomes_fk INT,
    code NVARCHAR(50) NOT NULL UNIQUE,
    description NVARCHAR(MAX) NOT NULL,
    sequence_num INT DEFAULT 0,
    is_active BIT DEFAULT 1,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    created_by_fk INT,
    updated_by_fk INT
);
CREATE INDEX idx_code_po ON program_outcomes(code);
CREATE INDEX idx_program_fk ON program_outcomes(program_fk);
CREATE INDEX idx_institutional_outcomes_fk ON program_outcomes(institutional_outcomes_fk);
CREATE INDEX idx_sequence_num_po ON program_outcomes(sequence_num);
CREATE INDEX idx_is_active_po ON program_outcomes(is_active);
GO

-- ============================================================================
-- 5. SLO SETS & OUTCOMES
-- ============================================================================

CREATE TABLE slo_sets (
    slo_sets_pk INT IDENTITY(1,1) PRIMARY KEY,
    set_code NVARCHAR(50) NOT NULL UNIQUE,
    set_name NVARCHAR(255) NOT NULL,
    set_type NVARCHAR(20) NOT NULL CHECK (set_type IN ('year', 'quarter', 'semester', 'custom')),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BIT DEFAULT 1,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    created_by_fk INT,
    updated_by_fk INT
);
CREATE INDEX idx_set_code ON slo_sets(set_code);
CREATE INDEX idx_set_type ON slo_sets(set_type);
CREATE INDEX idx_is_active_slosets ON slo_sets(is_active);
CREATE INDEX idx_start_date ON slo_sets(start_date);
CREATE INDEX idx_end_date ON slo_sets(end_date);
GO

CREATE TABLE student_learning_outcomes (
    student_learning_outcomes_pk INT IDENTITY(1,1) PRIMARY KEY,
    slo_set_code NVARCHAR(50),
    program_outcomes_fk INT,
    slo_code NVARCHAR(50) NOT NULL,
    description NVARCHAR(MAX) NOT NULL,
    assessment_method NVARCHAR(255),
    sequence_num INT DEFAULT 0,
    is_active BIT DEFAULT 1,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    created_by_fk INT,
    updated_by_fk INT,
    CONSTRAINT unique_slo_in_set UNIQUE (slo_set_code, slo_code)
);
CREATE INDEX idx_slo_set_code ON student_learning_outcomes(slo_set_code);
CREATE INDEX idx_program_outcomes_fk ON student_learning_outcomes(program_outcomes_fk);
CREATE INDEX idx_sequence_num_slo ON student_learning_outcomes(sequence_num);
CREATE INDEX idx_is_active_slo ON student_learning_outcomes(is_active);
GO

-- ============================================================================
-- 6. TERMS
-- ============================================================================

CREATE TABLE terms (
    terms_pk INT IDENTITY(1,1) PRIMARY KEY,
    slo_set_code NVARCHAR(50),
    term_code NVARCHAR(50) NOT NULL UNIQUE,
    term_name NVARCHAR(100) NOT NULL,
    term_year INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BIT DEFAULT 1,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE()
);
CREATE INDEX idx_term_code ON terms(term_code);
CREATE INDEX idx_slo_set_code_terms ON terms(slo_set_code);
CREATE INDEX idx_term_year ON terms(term_year);
CREATE INDEX idx_is_active_terms ON terms(is_active);
GO

-- ============================================================================
-- 7. ENROLLMENT (Student data denormalized)
-- ============================================================================

CREATE TABLE enrollment (
    enrollment_pk INT IDENTITY(1,1) PRIMARY KEY,
    term_code NVARCHAR(20) NOT NULL,
    crn NVARCHAR(20) NOT NULL,
    student_id NVARCHAR(20) NOT NULL,
    student_first_name NVARCHAR(100),
    student_last_name NVARCHAR(100),
    academic_year NVARCHAR(20),
    semester NVARCHAR(50),
    course_code NVARCHAR(50),
    course_title NVARCHAR(255),
    course_modality NVARCHAR(50),
    program_name NVARCHAR(255),
    subject_code NVARCHAR(20),
    subject_name NVARCHAR(100),
    enrollment_status NVARCHAR(10) NOT NULL DEFAULT '1',
    enrollment_date DATE NOT NULL,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    CONSTRAINT unique_enrollment UNIQUE (term_code, crn, student_id)
);
CREATE INDEX idx_term_code_enr ON enrollment(term_code);
CREATE INDEX idx_crn ON enrollment(crn);
CREATE INDEX idx_student_id_enr ON enrollment(student_id);
CREATE INDEX idx_course_code_enr ON enrollment(course_code);
CREATE INDEX idx_program_name_enr ON enrollment(program_name);
CREATE INDEX idx_enrollment_status ON enrollment(enrollment_status);
GO

-- ============================================================================
-- 8. ASSESSMENT DATA
-- ============================================================================

CREATE TABLE assessments (
    assessments_pk INT IDENTITY(1,1) PRIMARY KEY,
    enrollment_fk INT NOT NULL,
    student_learning_outcome_fk INT NOT NULL,
    score_value DECIMAL(5,2),
    achievement_level NVARCHAR(20) DEFAULT 'pending' CHECK (achievement_level IN ('met', 'partially_met', 'not_met', 'pending')),
    notes NVARCHAR(MAX),
    assessed_date DATE,
    is_finalized BIT DEFAULT 0,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    assessed_by_fk INT
);
CREATE INDEX idx_enrollment_fk ON assessments(enrollment_fk);
CREATE INDEX idx_student_learning_outcome_fk ON assessments(student_learning_outcome_fk);
CREATE INDEX idx_achievement_level ON assessments(achievement_level);
CREATE INDEX idx_assessed_date ON assessments(assessed_date);
CREATE INDEX idx_is_finalized ON assessments(is_finalized);
GO

-- ============================================================================
-- 9. AUDIT & ERROR LOGGING
-- ============================================================================

CREATE TABLE audit_log (
    audit_log_pk INT IDENTITY(1,1) PRIMARY KEY,
    table_name NVARCHAR(100) NOT NULL,
    record_pk INT NOT NULL,
    action NVARCHAR(20) NOT NULL CHECK (action IN ('INSERT', 'UPDATE', 'DELETE')),
    changed_by_fk INT,
    changed_data NVARCHAR(MAX), -- JSON stored as NVARCHAR
    old_data NVARCHAR(MAX), -- JSON stored as NVARCHAR
    ip_address NVARCHAR(45),
    user_agent NVARCHAR(MAX),
    created_at DATETIME DEFAULT GETDATE(),
    changed_by_fk INT
);
CREATE INDEX idx_table_name ON audit_log(table_name);
CREATE INDEX idx_record_pk ON audit_log(record_pk);
CREATE INDEX idx_action ON audit_log(action);
CREATE INDEX idx_changed_by_fk ON audit_log(changed_by_fk);
CREATE INDEX idx_created_at_audit ON audit_log(created_at);
GO

CREATE TABLE error_log (
    error_log_pk INT IDENTITY(1,1) PRIMARY KEY,
    error_type NVARCHAR(100) NOT NULL,
    error_message NVARCHAR(MAX) NOT NULL,
    error_code NVARCHAR(50),
    stack_trace NVARCHAR(MAX),
    file_path NVARCHAR(255),
    line_number INT,
    request_uri NVARCHAR(MAX),
    request_method NVARCHAR(10),
    request_data NVARCHAR(MAX), -- JSON stored as NVARCHAR
    ip_address NVARCHAR(45),
    user_agent NVARCHAR(MAX),
    severity NVARCHAR(20) DEFAULT 'error' CHECK (severity IN ('debug', 'info', 'warning', 'error', 'critical')),
    is_resolved BIT DEFAULT 0,
    resolved_at DATETIME NULL,
    resolved_by_fk INT,
    created_at DATETIME DEFAULT GETDATE(),
    user_fk INT
);
CREATE INDEX idx_error_type ON error_log(error_type);
CREATE INDEX idx_severity ON error_log(severity);
CREATE INDEX idx_is_resolved ON error_log(is_resolved);
CREATE INDEX idx_user_fk_error ON error_log(user_fk);
CREATE INDEX idx_created_at_error ON error_log(created_at);
GO

CREATE TABLE security_log (
    security_log_pk INT IDENTITY(1,1) PRIMARY KEY,
    event_type NVARCHAR(100) NOT NULL,
    event_description NVARCHAR(MAX) NOT NULL,
    username NVARCHAR(100),
    ip_address NVARCHAR(45),
    user_agent NVARCHAR(MAX),
    request_uri NVARCHAR(MAX),
    severity NVARCHAR(20) DEFAULT 'info' CHECK (severity IN ('info', 'warning', 'critical')),
    is_threat BIT DEFAULT 0,
    metadata NVARCHAR(MAX), -- JSON stored as NVARCHAR
    created_at DATETIME DEFAULT GETDATE(),
    user_fk INT
);
CREATE INDEX idx_event_type ON security_log(event_type);
CREATE INDEX idx_user_fk_security ON security_log(user_fk);
CREATE INDEX idx_username ON security_log(username);
CREATE INDEX idx_ip_address ON security_log(ip_address);
CREATE INDEX idx_severity_security ON security_log(severity);
CREATE INDEX idx_is_threat ON security_log(is_threat);
CREATE INDEX idx_created_at_security ON security_log(created_at);
GO

-- ============================================================================
-- 10. LTI INTEGRATION
-- ============================================================================

CREATE TABLE lti_nonces (
    lti_nonces_pk INT IDENTITY(1,1) PRIMARY KEY,
    consumer_key NVARCHAR(255) NOT NULL,
    nonce_value NVARCHAR(255) NOT NULL,
    timestamp BIGINT NOT NULL,
    created_at DATETIME DEFAULT GETDATE(),
    CONSTRAINT unique_nonce UNIQUE (consumer_key, nonce_value, timestamp)
);
CREATE INDEX idx_consumer_key ON lti_nonces(consumer_key);
CREATE INDEX idx_timestamp ON lti_nonces(timestamp);
GO

-- ============================================================================
-- INITIAL DATA: Standard Roles
-- ============================================================================

INSERT INTO roles (role_name, description) VALUES
    ('admin', 'System administrator with full access'),
    ('department_chair', 'Department chair with department-level access'),
    ('program_coordinator', 'Program coordinator with program-level access'),
    ('instructor', 'Course instructor with course-level access'),
    ('assessment_coordinator', 'Assessment coordinator with reporting access');
GO

-- ============================================================================
-- TRIGGERS FOR updated_at COLUMNS
-- SQL Server doesn't support ON UPDATE CURRENT_TIMESTAMP, so we need triggers
-- ============================================================================

-- Trigger for users table
CREATE TRIGGER trg_users_updated_at
ON users
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE users
    SET updated_at = GETDATE()
    FROM users u
    INNER JOIN inserted i ON u.users_pk = i.users_pk;
END;
GO

-- Add similar triggers for other tables with updated_at columns
-- (institutional_outcomes, programs, program_outcomes, slo_sets, 
--  student_learning_outcomes, terms, students, enrollment, assessments)

-- ============================================================================
-- SCHEMA COMPLETE
-- ============================================================================
