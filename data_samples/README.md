# Sample Data for Import

This folder contains sample CSV files for importing data into MOSAIC.

## Import Order

To maintain referential integrity, import files in this order:

1. **institution.csv** - Institution root entity (required first)
2. **users.csv** - System users (instructors, administrators)
3. **departments.csv** - Academic departments
4. **programs.csv** - Academic programs (requires departments)
5. **courses.csv** - Course catalog (requires departments)
6. **slo_sets.csv** - SLO assessment periods
7. **terms.csv** - Academic terms (requires SLO sets)
8. **course_sections.csv** - Course sections with CRNs (requires courses and terms)
9. **students.csv** - Student records
10. **enrollment.csv** - Student enrollments in course sections (requires course_sections and students)
11. **institutional_outcomes.csv** - Institution-level outcomes (requires institution)
12. **program_outcomes.csv** - Program-level outcomes (requires programs and institutional outcomes)
13. **student_learning_outcomes.csv** - Course SLOs (requires SLO sets, courses, program outcomes)

## Available Sample Files

### Root Entity
- **institution.csv** - Institution configuration with LTI credentials

### Organizational Structure
- **departments.csv** - 15 sample departments (CS, BUS, ENG, MATH, NURS, etc.)
- **programs.csv** - 26 sample programs across departments with degree types
- **courses.csv** - 40 sample courses with descriptions and credit hours

### Users & Roles
- **users.csv** - 11 sample users with roles (admin, instructor, department_chair, etc.)

### Assessment Periods
- **slo_sets.csv** - 13 sample SLO sets (academic years, semesters, quarters)
- **terms.csv** - 8 sample terms (Fall 2023 - Spring 2026)
- **course_sections.csv** - 50 sample course sections with unique CRNs

### Student Data
- **students.csv** - 50 sample students with contact information
- **enrollment.csv** - 62 sample enrollments (students registered in sections)

### Outcomes Hierarchy
- **institutional_outcomes.csv** - 10 institutional outcomes (critical thinking, communication, etc.)
- **program_outcomes.csv** - 28 program-level outcomes mapped to institutional outcomes
- **student_learning_outcomes.csv** - 26 course-level SLOs mapped to program outcomes

## File Formats

All CSV files use:
- UTF-8 encoding
- Comma-separated values
- Header row with column names
- Active/Inactive status where applicable

**Banner/California SIS Standards Applied:**
- **CRN Format**: 5-digit numeric (e.g., 10001, 45671)
- **Section Codes**: 3-digit with leading zeros (e.g., 001, 002, 003)
- **Student IDs**: C-number format with 8 digits (e.g., C00001001, C00123456)

## Column Descriptions

### institution.csv
- Institution Name: Full institution name
- Institution Code: Unique institution code
- LTI Consumer Key: LTI integration key (optional)
- LTI Consumer Name: Name for LTI consumer (optional)
- Status: Active or Inactive

### departments.csv
- Department Code: Unique department identifier (e.g., CS, MATH, ENG)
- Department Name: Full department name
- Status: Active or Inactive

### programs.csv
- Department Code: Reference to department
- Program Code: Unique program identifier
- Program Name: Full program name
- Degree Type: AS, BS, BA, MS, MSN, MFA, MBA, etc.
- Status: Active or Inactive

### courses.csv
- Department Code: Reference to department
- Course Code: Unique course identifier (e.g., CS101)
- Course Name: Full course title
- Description: Course description
- Credit Hours: Number of credit hours
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
- Term Code: Unique term identifier
- Term Name: Full term name
- Term Year: Four-digit year
- Start Date: YYYY-MM-DD format
- End Date: YYYY-MM-DD format
- Status: Active or Inactive

### course_sections.csv
- Course Code: Reference to course
- Term Code: Reference to term
- Section Code: 3-digit section identifier with leading zeros (001, 002, 003)
- CRN: 5-digit Course Reference Number - unique identifier for LTI integration
- Status: Active or Inactive

### enrollment.csv
- CRN: 5-digit reference to course section
- Student ID: C-number reference to student (C00xxxxxx format)
- Enrollment Status: enrolled, dropped, or completed
- Enrollment Date: YYYY-MM-DD format

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
- Course Code: Reference to course
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
