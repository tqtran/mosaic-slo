# Sample Data for Import

This folder contains sample CSV files for importing data into MOSAIC.

## Import Order

To maintain referential integrity, import files in this order:

1. **institution.csv** - Institution root entity (required first)
2. **users.csv** - System users (instructors, administrators)
3. **programs.csv** - Academic programs
4. **slo_sets.csv** - SLO assessment periods
5. **terms.csv** - Academic terms (requires SLO sets)
6. **students.csv** - Student records
7. **enrollment.csv** - Student enrollments by CRN/term (requires students)
8. **institutional_outcomes.csv** - Institution-level outcomes (requires institution)
9. **program_outcomes.csv** - Program-level outcomes (requires programs and institutional outcomes)
10. **student_learning_outcomes.csv** - Course SLOs (requires SLO sets and program outcomes)

## Available Sample Files

### Root Entity
- **institution.csv** - Institution configuration with LTI credentials

### Organizational Structure
- **programs.csv** - Sample academic programs with degree types

### Users & Roles
- **users.csv** - Sample users with roles (admin, instructor, department_chair, etc.)

### Assessment Periods
- **slo_sets.csv** - Sample SLO sets (academic years, semesters, quarters)
- **terms.csv** - Sample terms (Fall 2023 - Spring 2026)

### Student Data
- **students.csv** - Sample students with contact information
- **enrollment.csv** - Sample enrollments (students registered by CRN and term)
- **comprehensive_enrollment_import.csv** - Bulk enrollment import format for SIS integration

### Outcomes Hierarchy
- **institutional_outcomes.csv** - Institutional outcomes (critical thinking, communication, etc.)
- **program_outcomes.csv** - Program-level outcomes mapped to institutional outcomes
- **student_learning_outcomes.csv** - Course-level SLOs mapped to program outcomes

## File Formats

All CSV files use:
- UTF-8 encoding
- Comma-separated values
- Header row with column names
- Active/Inactive status where applicable

**Banner/California SIS Standards Applied:**
- **CRN Format**: 5-digit string (e.g., "10001", "45671") - unique course section identifier
- **Term Codes**: Semester format (e.g., "202430" for Fall 2024, "202510" for Spring 2025)
- **Student IDs**: C-number format with 8 digits (e.g., C00001001, C00123456)

## Column Descriptions

### institution.csv
- Institution Name: Full institution name
- Institution Code: Unique institution code
- LTI Consumer Key: LTI integration key (optional)
- LTI Consumer Name: Name for LTI consumer (optional)
- Status: Active or Inactive

### programs.csv
- Program Code: Unique program identifier
- Program Name: Full program name
- Degree Type: AS, BS, BA, MS, MSN, MFA, MBA, etc.
- Status: Active or Inactive

### users.csv
- User ID: Unique username
- First Name: User's first name
- Last Name: User's last name
- Email: User's email address
- Role: admin, instructor, department_chair, program_coordinator, assessment_coordinator
- Status: Active or Inactive

### slo_sets.csv
- Set Code: Unique identifier for assessment period
- Set Name: Descriptive name
- Set Type: year, semester, quarter, or custom
- Start Date: YYYY-MM-DD format
- End Date: YYYY-MM-DD format
- Status: Active or Inactive

### terms.csv
- SLO Set Code: Reference to SLO set
- Term Code: Unique term identifier (e.g., 202430 for Fall 2024)
- Term Name: Full term name
- Term Year: Four-digit year
- Start Date: YYYY-MM-DD format
- End Date: YYYY-MM-DD format
- Status: Active or Inactive

### enrollment.csv
- Term Code: Term identifier (e.g., 202430)
- CRN: 5-digit Course Reference Number (string format)
- Student ID: C-number reference to student (C00xxxxxx format)
- Enrollment Status: enrolled, dropped, or completed
- Enrollment Date: YYYY-MM-DD format

### comprehensive_enrollment_import.csv
Bulk enrollment import format for SIS integration:
- term: Term code (e.g., 202430)
- crn: Course Reference Number
- cnum: Student ID (C-number)
- FN: Student first name
- LN: Student last name
- status: Enrollment status (1=enrolled, 0=dropped)
- regdate: Registration date (YYYY-MM-DD)
- updated: Last update timestamp

### students.csv
- Student ID: C-number format with 8 digits (C00001001, C00123456)
- First Name: Student's first name
- Last Name: Student's last name
- Email: Student's email address
- Status: Active or Inactive

### institutional_outcomes.csv
- Code: Unique outcome code (e.g., IO-1)
- Description: Full outcome description
- Sequence: Display order
- Status: Active or Inactive

### program_outcomes.csv
- Program Code: Reference to program
- Institutional Outcome Code: Reference to institutional outcome (optional mapping)
- Program Outcome Code: Unique outcome code (e.g., PO-CS-1)
- Description: Full outcome description
- Sequence: Display order
- Status: Active or Inactive

### student_learning_outcomes.csv
- SLO Set Code: Reference to SLO set
- Program Outcome Code: Reference to program outcome (optional mapping)
- SLO Code: Unique SLO identifier
- Description: Full SLO description
- Assessment Method: How the SLO is assessed
- Sequence: Display order
- Status: Active or Inactive

## Notes

- **Foreign Key References**: Some imports require referenced data to exist first (follow import order)
- **Codes**: Must be unique within each table
- **Status**: Use "Active" or "Inactive" (case-insensitive)
- **Dates**: Use YYYY-MM-DD format
- **Passwords**: User passwords must be set separately through the admin interface (CSV does not include passwords)

## Usage

Import these files through the respective admin pages in MOSAIC. Each admin page with import functionality will validate the CSV structure and provide feedback on any errors.
