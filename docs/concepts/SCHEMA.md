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

```text
institution
    └─> institutional_outcomes
        └─> program_outcomes
            └─> programs

slo_sets (per year/quarter/period)
    ├─> terms
    │   └─> course_sections (with CRN) [optional reference]
    └─> student_learning_outcomes
        └─> courses

enrollment (term_code, crn stored directly)
    ├─> students (c_number)
    └─> assessments

departments
    ├─> programs
    └─> courses

users
    ├─> user_roles -> roles
    ├─> course_sections (instructor)
    └─> audit fields (created_by, updated_by, assessed_by)
```

## Tables

### 1. Root Entity

#### institution
Institution root entity (typically one record).

| Column | Type | Description |
| -------- | ------ | ------------- |
| institution_pk | INT | Primary key |
| institution_name | VARCHAR(255) | Institution name |
| institution_code | VARCHAR(50) | Institution identifier |
| lti_consumer_key | VARCHAR(255) | LTI consumer key for LMS integration |
| lti_consumer_secret | VARCHAR(255) | LTI consumer secret |
| lti_consumer_name | VARCHAR(100) | LMS display name (e.g., "Canvas Production") |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Indexes**: institution_code (unique), lti_consumer_key (unique)

**Note**: One MOSAIC instance = one institution = one LMS integration. LTI keys stored at institution level for simplicity.

---

### 2. Outcomes Hierarchy

#### institutional_outcomes
Top-level institutional learning outcomes.

| Column | Type | Description |
| -------- | ------ | ------------- |
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
| -------- | ------ | ------------- |
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

---

### 3. SLO Sets & Outcomes

#### slo_sets
Grouping of SLOs by time period (year, quarter, period). Each term uses outcomes from one SLO set.

| Column | Type | Description |
| -------- | ------ | ------------- |
| slo_sets_pk | INT | Primary key |
| set_code | VARCHAR(50) | Unique set code (e.g., 'AY2024') |
| set_name | VARCHAR(255) | Set name (e.g., 'Academic Year 2024') |
| set_type | ENUM | 'year', 'quarter', 'semester', 'custom' |
| start_date | DATE | Set start date |
| end_date | DATE | Set end date |
| is_active | BOOLEAN | Active set flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |
| created_by_fk | INT | User who created record |
| updated_by_fk | INT | User who last updated |

**Foreign Keys**: created_by_fk → users(users_pk), updated_by_fk → users(users_pk)

**Indexes**: set_code (unique), set_type, is_active, start_date, end_date

#### student_learning_outcomes
Course-level student learning outcomes within SLO sets. SLOs are uploaded per set and can be mapped to program outcomes.

| Column | Type | Description |
| -------- | ------ | ------------- |
| student_learning_outcomes_pk | INT | Primary key |
| slo_set_fk | INT | Foreign key to slo_sets |
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

**Foreign Keys**: slo_set_fk → slo_sets(slo_sets_pk), course_fk → courses(courses_pk), program_outcomes_fk → program_outcomes(program_outcomes_pk), created_by_fk → users(users_pk), updated_by_fk → users(users_pk)

**Unique Constraint**: (slo_set_fk, course_fk, slo_code)

**Indexes**: slo_set_fk, course_fk, program_outcomes_fk, sequence_num, is_active

---

### 4. Organizational Structure

#### departments
Academic departments.

| Column | Type | Description |
| -------- | ------ | ------------- |
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
| -------- | ------ | ------------- |
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
| -------- | ------ | ------------- |
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
Academic terms/semesters. Each term is associated with an SLO set to determine which SLOs are assessed.

| Column | Type | Description |
| -------- | ------ | ------------- |
| terms_pk | INT | Primary key |
| slo_set_fk | INT | Foreign key to slo_sets |
| term_code | VARCHAR(50) | Term code (e.g., 'FA2024') |
| term_name | VARCHAR(100) | Term name (e.g., 'Fall 2024') |
| term_year | INT | Academic year |
| start_date | DATE | Term start date |
| end_date | DATE | Term end date |
| is_active | BOOLEAN | Current term flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Foreign Keys**: slo_set_fk → slo_sets(slo_sets_pk)

**Indexes**: term_code (unique), slo_set_fk, term_year, is_active

#### course_sections
Course offerings in specific terms. Each section has a unique CRN (Course Reference Number) used for registration and LTI integration.

| Column | Type | Description |
| -------- | ------ | ------------- |
| course_sections_pk | INT | Primary key |
| course_fk | INT | Foreign key to courses |
| term_fk | INT | Foreign key to terms |
| instructor_fk | INT | Foreign key to users |
| section_code | VARCHAR(50) | Section identifier |
| crn | VARCHAR(20) | Course Reference Number (unique) |
| is_active | BOOLEAN | Soft delete flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Foreign Keys**: course_fk → courses(courses_pk), term_fk → terms(terms_pk), instructor_fk → users(users_pk)

**Unique Constraint**: (course_fk, term_fk, section_code), crn (unique)

**Indexes**: course_fk, term_fk, instructor_fk, crn, is_active

---

### 5. Students & Enrollment

#### students
Student records. Primary identifier is `c_number` (Banner C-Number).

| Column | Type | Description |
| -------- | ------ | ------------- |
| students_pk | INT | Primary key |
| c_number | VARCHAR(50) | **Student C-Number from Banner SIS (unique)** |
| student_id | VARCHAR(50) | Alternative student ID if needed (nullable) |
| first_name | VARCHAR(100) | First name (nullable - may be populated later) |
| last_name | VARCHAR(100) | Last name (nullable - may be populated later) |
| email | VARCHAR(255) | Email address (nullable) |
| is_active | BOOLEAN | Active student flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Indexes**: c_number (unique), student_id, email, is_active

**Note**: Students can be auto-created during enrollment import with just the `c_number`. Other fields (name, email) can be populated later from student data feeds.

#### enrollment
Student enrollment records with direct CRN and term code from Banner. Independent of course_sections table for simplified Banner ENRs import.

| Column | Type | Description |
| -------- | ------ | ------------- |
| enrollment_pk | INT | Primary key |
| term_code | VARCHAR(20) | **Term code from Banner (e.g., 202630)** |
| crn | VARCHAR(20) | **Course Reference Number from Banner** |
| student_fk | INT | Foreign key to students |
| course_section_fk | INT | Optional link to course_sections if available (nullable) |
| enrollment_status | ENUM | 'enrolled', 'dropped', 'completed', 'withdrawn' |
| enrollment_date | DATE | Date enrolled |
| drop_date | DATE | Date dropped (nullable) |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Foreign Keys**: student_fk → students(students_pk), course_section_fk → course_sections(course_sections_pk) ON DELETE SET NULL

**Unique Constraint**: (term_code, crn, student_fk)

**Indexes**: term_code, crn, course_section_fk, student_fk, enrollment_status

**Design Notes**: 
- `crn` and `term_code` stored directly for Banner ENRs import independence
- `course_section_fk` is optional - can be populated later if course catalog exists
- Enables direct CSV import from Banner without pre-populating courses/sections
- LTI launches can use CRN to find enrollment records even without course_sections

---

### 6. Assessment Data

#### assessments
Individual student assessments for SLOs. Each assessment is tied to an enrollment record (which has the CRN directly) and a specific SLO from the term's SLO set.

| Column | Type | Description |
| -------- | ------ | ------------- |
| assessments_pk | INT | Primary key |
| enrollment_fk | INT | Foreign key to enrollment (links to course_section with CRN) |
| student_learning_outcome_fk | INT | Foreign key to SLOs |
| score_value | DECIMAL(5,2) | Numeric score (nullable) |
| achievement_level | ENUM | 'met', 'partially_met', 'not_met', 'pending' |
| notes | TEXT | Assessment notes |
| assessed_date | DATE | Date assessed |
| is_finalized | BOOLEAN | Assessment locked flag |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |
| assessed_by_fk | INT | User who assessed |

**Foreign Keys**: enrollment_fk → enrollment(enrollment_pk), student_learning_outcome_fk → student_learning_outcomes(student_learning_outcomes_pk), assessed_by_fk → users(users_pk)

**Indexes**: enrollment_fk, student_learning_outcome_fk, achievement_level, assessed_date, is_finalized

---

### 7. User Management

#### users
System users (faculty, administrators, staff).

| Column | Type | Description |
| -------- | ------ | ------------- |
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
| -------- | ------ | ------------- |
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
| -------- | ------ | ------------- |
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

### 8. LTI Integration

**Architecture Note**: LTI consumer keys are stored in the `institution` table rather than a separate `lti_consumers` table. This design reflects the reality that one MOSAIC instance = one institution = one LMS integration. Multiple consumer keys are not needed because:

- Authorization is handled by role + context from LTI launch parameters, not by consumer keys
- The consumer key authenticates the LMS instance, not individual users or courses
- Simpler configuration: LMS admin configures tool once at institution level
- Follows YAGNI principle: Don't build for hypothetical multi-LMS scenarios

For institutions needing multiple LMS platforms (rare edge case like Canvas + Blackboard during transition), deploy separate MOSAIC instances or extend via plugin.

#### lti_nonces
Nonce tracking for LTI security (prevents replay attacks).

| Column | Type | Description |
| -------- | ------ | ------------- |
| lti_nonces_pk | INT | Primary key |
| consumer_key | VARCHAR(255) | LTI consumer key |
| nonce_value | VARCHAR(255) | Nonce value |
| timestamp | BIGINT | Request timestamp |
| created_at | TIMESTAMP | Record creation time |

**Indexes**: (consumer_key, nonce_value, timestamp) unique

---

## Key Relationships

### 1. Assessment Chain with SLO Sets

```text
SLO Set (per year/quarter) → Student Learning Outcomes → Assessments
                           ↓
                         Terms

Enrollment (term_code + crn) → Students
                             ↓
                        Assessment
                                                       
SLO → Program Outcome → Institutional Outcome
```

**Assessment Flow:**
1. SLOs are uploaded into an SLO Set (e.g., "Academic Year 2024")
2. Each Term is linked to an SLO Set
3. Enrollments are created with term_code and CRN (from Banner ENRs table)
4. Students are auto-created during enrollment import if needed
5. Assessments are recorded per Enrollment for specific SLOs from the Term's SLO Set
6. Course Sections table is optional - can be populated separately for course catalog

### 2. Institutional Reporting

```textInstitution defines Institutional Outcomes
    → institutional_outcomes records
    
Program creates Program Outcomes mapped to Institutional
    → program_outcomes records with institutional_outcomes_fk
    
SLO Set contains SLOs mapped to Program Outcomes
    → slo_sets records
    → student_learning_outcomes records with program_outcomes_fk and slo_set_fk
    
Query: Get all assessments rolling up to Institutional Outcome #3 for AY2024
    → JOIN assessments → enrollment (has term_code, crn)
    → JOIN assessments → student_learning_outcomes → program_outcomes → institutional_outcomes
    → WHERE institutional_outcomes_pk = 3 AND enrollment.term_code IN (SELECT term_code FROM terms WHERE slo_set has 'AY2024')
```
### 3. Access Control

```textUser assigned role at different contexts:
    → Global admin: user_roles (user_fk, role='admin', context_type=NULL)
    → Department chair: user_roles (user_fk, role='department_chair', context_type='department', context_id=dept_pk)
    → Program coordinator: user_roles (user_fk, role='program_coordinator', context_type='program', context_id=program_pk)
    → Instructor: user_roles (user_fk, role='instructor', context_type='course', context_id=course_pk)
```text
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
- Course Section CRN (via course_section)
- SLO details (code, description)
- SLO Set information
- Program outcome code (if aligned)
- Institutional outcome code (if aligned)
- Assessment results (achievement level, score)

**Relationships Traversed:**
- Assessments → Enrollments (has CRN, term_code) → Students
- Assessments → SLOs → SLO Sets
- SLOs → Program Outcomes → Institutional Outcomes
- Enrollments via term_code → Terms → SLO Sets (optional)

**Filters:**
- Specific course section
- Only finalized assessments
- Active records only

**Ordering**: Student name (last, first), then SLO sequence

### Program Outcome Achievement Analytics by SLO Set

**Query Concept**: Calculate achievement statistics for all outcomes within a program for a specific time period (SLO Set)

**Data Retrieved:**
- SLO Set information (code, name, date range)
- Program outcome identification (code, description)
- Total assessment count per outcome
- Count of met vs not met assessments
- Achievement rate percentage

**Relationships Traversed:**
- Program Outcomes → SLOs → SLO Sets
- SLOs → Assessments → Enrollments → Course Sections (with CRN)

**Aggregations:**
- Count total assessments per outcome per SLO set
- Sum assessments by achievement level
- Group by SLO set and program outcome

### CRN-Based Assessment Lookup

**Query Concept**: Retrieve all assessments for a specific CRN

**Data Retrieved:**
- Course section details (including CRN)
- Student information
- All SLO assessments for enrollments in that section
- Assessment status and scores

**Relationships Traversed:**
- Course Sections (via CRN) → Enrollments → Assessments → SLOs
- Enrollments → Students
- Course Sections → Courses

**Filters:**
- CRN match on course_sections table
- Active records only
- Active records only
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
