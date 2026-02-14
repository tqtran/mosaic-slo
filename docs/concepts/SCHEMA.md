# Database Schema Documentation

## Overview

This document describes the complete database schema for the MOSAIC assessment platform. The schema follows third normal form (3NF) and uses InnoDB storage engine with UTF8MB4 character set.

**Database Requirement:** MySQL 8.0+ is required. This is a hard requirement with no alternative database support. The system uses direct MySQL queries with prepared statements (no ORM or abstraction layer).

## Naming Conventions

- **Primary Keys**: `{table_name}_pk` (e.g., `courses_pk`, `students_pk`)
- **Foreign Keys**: `{referenced_table}_fk` (e.g., `course_fk`, `program_fk`)
- **Tables**: Full names, no abbreviations (e.g., `student_learning_outcomes` not `slo`)
- **Ordering**: `sequence_num` for display order
- **Soft Deletes**: `is_active` BOOLEAN flag

## Entity Relationship Diagram

```
institution
    └─> institutional_outcomes
        └─> program_outcomes
            ├─> programs
            └─> student_learning_outcomes

departments
    ├─> programs
    └─> courses
        ├─> student_learning_outcomes
        └─> course_sections
            └─> enrollment
                ├─> students
                └─> assessments

users
    ├─> user_roles -> roles
    ├─> course_sections (instructor)
    └─> audit fields (created_by, updated_by, assessed_by)

terms
    └─> course_sections
```

## Tables

### 1. Root Entity

#### institution
Institution root entity (typically one record).

| Column | Type | Description |
|--------|------|-------------|
| institution_pk | INT | Primary key |
| institution_name | VARCHAR(255) | Institution name |
| institution_code | VARCHAR(50) | Institution identifier |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Indexes**: institution_code (unique)

---

### 2. Outcomes Hierarchy

#### institutional_outcomes
Top-level institutional learning outcomes.

| Column | Type | Description |
|--------|------|-------------|
| institutional_outcomes_pk | INT | Primary key |
| institution_fk | INT | Foreign key to institution |
| code | VARCHAR(50) | Unique outcome code |
| description | TEXT | Outcome description |
| sequence_num | INT | Display order |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |
| created_by_fk | INT | User who created record |
| updated_by_fk | INT | User who last updated |

**Foreign Keys**: institution_fk → institution(institution_pk), created_by_fk → users(users_pk), updated_by_fk → users(users_pk)

**Indexes**: code (unique), institution_fk, sequence_num, is_active

#### program_outcomes
Program-level learning outcomes mapped to institutional outcomes.

| Column | Type | Description |
|--------|------|-------------|
| program_outcomes_pk | INT | Primary key |
| program_fk | INT | Foreign key to programs |
| institutional_outcomes_fk | INT | Parent institutional outcome |
| code | VARCHAR(50) | Unique outcome code |
| description | TEXT | Outcome description |
| sequence_num | INT | Display order |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |
| created_by_fk | INT | User who created record |
| updated_by_fk | INT | User who last updated |

**Foreign Keys**: program_fk → programs(programs_pk), institutional_outcomes_fk → institutional_outcomes(institutional_outcomes_pk), created_by_fk → users(users_pk), updated_by_fk → users(users_pk)

**Indexes**: code (unique), program_fk, institutional_outcomes_fk, sequence_num, is_active

#### student_learning_outcomes
Course-level student learning outcomes mapped to program outcomes.

| Column | Type | Description |
|--------|------|-------------|
| student_learning_outcomes_pk | INT | Primary key |
| course_fk | INT | Foreign key to courses |
| program_outcomes_fk | INT | Parent program outcome (nullable) |
| slo_code | VARCHAR(50) | SLO code within course |
| description | TEXT | SLO description |
| assessment_method | VARCHAR(255) | How SLO is assessed |
| sequence_num | INT | Display order |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |
| created_by_fk | INT | User who created record |
| updated_by_fk | INT | User who last updated |

**Foreign Keys**: course_fk → courses(courses_pk), program_outcomes_fk → program_outcomes(program_outcomes_pk), created_by_fk → users(users_pk), updated_by_fk → users(users_pk)

**Unique Constraint**: (course_fk, slo_code)

**Indexes**: course_fk, program_outcomes_fk, sequence_num, is_active

---

### 3. Organizational Structure

#### departments
Academic departments.

| Column | Type | Description |
|--------|------|-------------|
| departments_pk | INT | Primary key |
| department_code | VARCHAR(50) | Department code |
| department_name | VARCHAR(255) | Department name |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |
| created_by_fk | INT | User who created record |
| updated_by_fk | INT | User who last updated |

**Foreign Keys**: created_by_fk → users(users_pk), updated_by_fk → users(users_pk)

**Indexes**: department_code (unique), is_active

#### programs
Academic programs within departments.

| Column | Type | Description |
|--------|------|-------------|
| programs_pk | INT | Primary key |
| department_fk | INT | Foreign key to departments |
| program_code | VARCHAR(50) | Program code |
| program_name | VARCHAR(255) | Program name |
| degree_type | VARCHAR(50) | Degree type (BA, BS, MA, etc.) |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |
| created_by_fk | INT | User who created record |
| updated_by_fk | INT | User who last updated |

**Foreign Keys**: department_fk → departments(departments_pk), created_by_fk → users(users_pk), updated_by_fk → users(users_pk)

**Indexes**: program_code (unique), department_fk, is_active

#### courses
Course definitions (catalog entries).

| Column | Type | Description |
|--------|------|-------------|
| courses_pk | INT | Primary key |
| department_fk | INT | Foreign key to departments |
| course_code | VARCHAR(50) | Course code |
| course_name | VARCHAR(255) | Course name |
| description | TEXT | Course description |
| credit_hours | INT | Credit hours |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |
| created_by_fk | INT | User who created record |
| updated_by_fk | INT | User who last updated |

**Foreign Keys**: department_fk → departments(departments_pk), created_by_fk → users(users_pk), updated_by_fk → users(users_pk)

**Indexes**: course_code (unique), department_fk, is_active

#### terms
Academic terms/semesters.

| Column | Type | Description |
|--------|------|-------------|
| terms_pk | INT | Primary key |
| term_code | VARCHAR(50) | Term code (e.g., 'FA2024') |
| term_name | VARCHAR(100) | Term name (e.g., 'Fall 2024') |
| term_year | INT | Academic year |
| start_date | DATE | Term start date |
| end_date | DATE | Term end date |
| is_active | BOOLEAN | Current term flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Indexes**: term_code (unique), term_year, is_active

#### course_sections
Course offerings in specific terms.

| Column | Type | Description |
|--------|------|-------------|
| course_sections_pk | INT | Primary key |
| course_fk | INT | Foreign key to courses |
| term_fk | INT | Foreign key to terms |
| instructor_fk | INT | Foreign key to users |
| section_code | VARCHAR(50) | Section identifier |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Foreign Keys**: course_fk → courses(courses_pk), term_fk → terms(terms_pk), instructor_fk → users(users_pk)

**Unique Constraint**: (course_fk, term_fk, section_code)

**Indexes**: course_fk, term_fk, instructor_fk, is_active

---

### 4. Students & Enrollment

#### students
Student records.

| Column | Type | Description |
|--------|------|-------------|
| students_pk | INT | Primary key |
| student_id | VARCHAR(50) | Unique student ID |
| first_name | VARCHAR(100) | First name |
| last_name | VARCHAR(100) | Last name |
| email | VARCHAR(255) | Email address |
| is_active | BOOLEAN | Active student flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Indexes**: student_id (unique), email, is_active

#### enrollment
Student enrollment in course sections.

| Column | Type | Description |
|--------|------|-------------|
| enrollment_pk | INT | Primary key |
| course_section_fk | INT | Foreign key to course_sections |
| student_fk | INT | Foreign key to students |
| enrollment_status | ENUM | 'enrolled', 'dropped', 'completed' |
| enrollment_date | DATE | Date enrolled |
| drop_date | DATE | Date dropped (nullable) |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Foreign Keys**: course_section_fk → course_sections(course_sections_pk), student_fk → students(students_pk)

**Unique Constraint**: (course_section_fk, student_fk)

**Indexes**: course_section_fk, student_fk, enrollment_status

---

### 5. Assessment Data

#### assessments
Individual student assessments for SLOs.

| Column | Type | Description |
|--------|------|-------------|
| assessments_pk | INT | Primary key |
| enrollment_fk | INT | Foreign key to enrollment |
| student_learning_outcome_fk | INT | Foreign key to SLOs |
| score_value | DECIMAL(5,2) | Numeric score (nullable) |
| achievement_level | ENUM | 'met', 'not_met', 'pending' |
| notes | TEXT | Assessment notes |
| assessed_date | DATE | Date assessed |
| is_finalized | BOOLEAN | Assessment locked flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |
| assessed_by_fk | INT | User who assessed |

**Foreign Keys**: enrollment_fk → enrollment(enrollment_pk), student_learning_outcome_fk → student_learning_outcomes(student_learning_outcomes_pk), assessed_by_fk → users(users_pk)

**Indexes**: enrollment_fk, student_learning_outcome_fk, achievement_level, assessed_date, is_finalized

---

### 6. User Management

#### users
System users (faculty, administrators, staff).

| Column | Type | Description |
|--------|------|-------------|
| users_pk | INT | Primary key |
| user_id | VARCHAR(100) | Unique username/ID |
| first_name | VARCHAR(100) | First name |
| last_name | VARCHAR(100) | Last name |
| email | VARCHAR(255) | Email address |
| password_hash | VARCHAR(255) | Hashed password |
| is_active | BOOLEAN | Account active flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Indexes**: user_id (unique), email (unique), is_active

#### roles
User roles for access control.

| Column | Type | Description |
|--------|------|-------------|
| roles_pk | INT | Primary key |
| role_name | VARCHAR(50) | Role name |
| description | TEXT | Role description |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Indexes**: role_name (unique)

**Standard Roles**: 'admin', 'department_chair', 'program_coordinator', 'instructor', 'assessment_coordinator'

#### user_roles
User role assignments (many-to-many with context).

| Column | Type | Description |
|--------|------|-------------|
| user_roles_pk | INT | Primary key |
| user_fk | INT | Foreign key to users |
| role_fk | INT | Foreign key to roles |
| context_type | VARCHAR(50) | Context type (nullable) |
| context_id | INT | Context entity ID (nullable) |
| created_at | TIMESTAMP | Record creation time |

**Foreign Keys**: user_fk → users(users_pk), role_fk → roles(roles_pk)

**Indexes**: user_fk, role_fk, (context_type, context_id)

**Context Types**: 'global' (context_id NULL), 'department', 'program', 'course'

---

### 7. LTI Integration

#### lti_consumers
LTI consumer registrations.

| Column | Type | Description |
|--------|------|-------------|
| lti_consumers_pk | INT | Primary key |
| consumer_key | VARCHAR(255) | LTI consumer key |
| consumer_secret | VARCHAR(255) | LTI consumer secret |
| consumer_name | VARCHAR(255) | Consumer display name |
| is_active | BOOLEAN | Consumer active flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Indexes**: consumer_key (unique), is_active

#### lti_nonces
Nonce tracking for LTI security.

| Column | Type | Description |
|--------|------|-------------|
| lti_nonces_pk | INT | Primary key |
| consumer_key | VARCHAR(255) | LTI consumer key |
| nonce_value | VARCHAR(255) | Nonce value |
| timestamp | BIGINT | Request timestamp |
| created_at | TIMESTAMP | Record creation time |

**Indexes**: (consumer_key, nonce_value, timestamp) unique

---

## Key Relationships

### 1. Assessment Chain

```
Student → Enrollment → Course Section → Course → SLO → Assessment
                                              ↓
                                       Program Outcome
                                              ↓
                                  Institutional Outcome
```

### 2. Institutional Reporting

```
Institution defines Institutional Outcomes
    → institutional_outcomes records
    
Program creates Program Outcomes mapped to Institutional
    → program_outcomes records with institutional_outcomes_fk
    
Course defines SLOs mapped to Program Outcomes
    → student_learning_outcomes records with program_outcomes_fk
    
Query: Get all assessments rolling up to Institutional Outcome #3
    → JOIN assessments → student_learning_outcomes → program_outcomes → institutional_outcomes
    → WHERE institutional_outcomes_pk = 3
```

### 3. Access Control

```
User assigned role at different contexts:
    → Global admin: user_roles (user_fk, role='admin', context_type=NULL)
    → Department chair: user_roles (user_fk, role='department_chair', context_type='department', context_id=dept_pk)
    → Program coordinator: user_roles (user_fk, role='program_coordinator', context_type='program', context_id=program_pk)
    → Instructor: user_roles (user_fk, role='instructor', context_type='course', context_id=course_pk)
```

### 4. Audit Trail

All major tables include:
- `created_by_fk` → users(users_pk)
- `updated_by_fk` → users(users_pk)
- `created_at` TIMESTAMP
- `updated_at` TIMESTAMP

Assessment table adds:
- `assessed_by_fk` → users(users_pk)
- `assessed_date` DATE

---

## Design Principles

1. **Normalization**: Schema follows 3NF to minimize redundancy
2. **Surrogate Keys**: All tables use auto-incrementing integer primary keys
3. **Soft Deletes**: Important records use `is_active` flag instead of hard deletes
4. **Audit Trails**: Track who created/updated records and when
5. **Referential Integrity**: Foreign keys with appropriate CASCADE/SET NULL
6. **Flexibility**: Context-aware roles support various organizational structures
7. **Scalability**: Indexed foreign keys and commonly queried columns

---

## Common Query Patterns

### Assessment Reporting with Outcomes Hierarchy

**Query Concept**: Retrieve all student assessments for a course section with complete outcomes traceability

**Data Retrieved:**
- Student identification (ID, name)
- SLO details (code, description)
- Program outcome code (if aligned)
- Institutional outcome code (if aligned)
- Assessment results (achievement level, score)

**Relationships Traversed:**
- Assessments → Enrollments → Students
- Assessments → SLOs → Program Outcomes → Institutional Outcomes

**Filters:**
- Specific course section
- Only finalized assessments
- Active records only

**Ordering**: Student name (last, first), then SLO sequence

### Program Outcome Achievement Analytics

**Query Concept**: Calculate achievement statistics for all outcomes within a program

**Data Retrieved:**
- Program outcome identification (code, description)
- Total assessment count per outcome
- Count of met vs not met assessments
- Achievement rate percentage

**Relationships Traversed:**
- Program Outcomes → SLOs → Assessments

**Aggregations:**
- Count total assessments per outcome
- Sum assessments by achievement level
- Calculate achievement rate percentage

**Filters:**
- Specific program
- Only finalized assessments
- Active outcomes only

**Ordering**: Program outcome sequence

### Context-Based Permission Check

**Query Concept**: Verify if user has specific permission in given context (global or scoped)

**Data Retrieved:**
- Boolean indicating permission existence

**Relationships Traversed:**
- User Roles → Roles

**Logic:**
- Match user and role name
- Check if role is global (no context) OR matches specific context (type + ID)
- Return true if any matching role found

**Contexts Supported:**
- Global: No context restrictions
- Department-scoped: Limited to specific department
- Program-scoped: Limited to specific program
- Course-scoped: Limited to specific course
