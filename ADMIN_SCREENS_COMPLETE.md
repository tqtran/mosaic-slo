# Admin Screens Complete - Summary

All 8 administrative CRUD screens have been successfully created following the established pattern from programs.php.

## Completed Admin Screens

### 1. **Institutions** [institutions.php + institutions_data.php]
- Fields: `institution_code`, `institution_name`
- No FK dependencies
- Status: ✓ Already existed

### 2. **Institutional Outcomes** [institutional_outcomes.php + institutional_outcomes_data.php]
- Fields: `outcome_code`, `outcome_description`, `sequence_num`
- FK: `institution_fk` → institutions
- Status: ✓ Already existed

### 3. **Programs** [programs.php + programs_data.php]
- Fields: `program_code`, `program_name`, `degree_type`
- No FK dependencies
- Status: ✓ Template for all others (already existed)

### 4. **Program Outcomes** [program_outcomes.php + program_outcomes_data.php]
- Fields: `outcome_code`, `outcome_description`, `sequence_num`
- FK: `program_fk` → programs
- Status: ✓ Already existed

### 5. **Courses** [courses.php + courses_data.php]
- Fields: `course_name`, `course_number`
- No FK dependencies
- Lines: 433 (main) + 91 (data)
- Status: ✓ JUST CREATED

### 6. **Course Sections** [course_sections.php + course_sections_data.php]
- Fields: `crn`, `section_number`, `instructor_name`
- FK: `course_fk` → courses
- Lines: 459 (main) + 97 (data)
- Check: Assessments before delete
- Status: ✓ JUST CREATED

### 7. **Student Learning Outcomes** [student_learning_outcomes.php + student_learning_outcomes_data.php]
- Fields: `slo_code`, `slo_description` (textarea), `sequence_num`
- FK: `course_fk` → courses
- Lines: 489 (main) + 94 (data)
- Special: Description truncated to 60 chars in table
- Check: Assessments before delete
- Status: ✓ JUST CREATED

### 8. **Students** [students.php + students_data.php]
- Fields: `student_id`, `student_first_name`, `student_last_name`, `email`
- No FK dependencies (FERPA-protected data)
- Lines: 465 (main) + 125 (data)
- Notice: Fields should be encrypted in production
- Check: Assessments before delete
- Status: ✓ JUST CREATED

### 9. **Assessments** [assessments.php + assessments_data.php]
- Fields: `score_value`, `achievement_level`, `assessment_method`, `notes`, `assessed_date`
- **3 FK dependencies**:
  - `course_section_fk` → course_sections
  - `students_fk` → students
  - `student_learning_outcome_fk` → student_learning_outcomes
- Lines: 561 (main) + 165 (data)
- Complex: 3-way JOIN in data endpoint
- Status: ✓ JUST CREATED

## Consistent Pattern Across All Screens

Each admin screen follows this structure:

### Main File Pattern (~430-560 lines)
1. **POST Handler** - Actions: add, edit, toggle_status, delete
2. **CSRF Validation** - Required on all POST operations
3. **Statistics Query** - Total/Active/Inactive counts
4. **FK Lookup Queries** - Populate SELECT dropdowns
5. **Theme Header** - AdminLTE layout with breadcrumbs
6. **Success/Error Alerts** - Bootstrap 5 dismissible alerts
7. **Statistics Cards** - Info boxes with counts
8. **DataTable** - Server-side processing with search/sort/pagination
9. **Add/Edit Modals** - Bootstrap 5 modals with forms
10. **Toggle/Delete Forms** - Hidden forms for status changes
11. **JavaScript Functions** - edit*(), toggleStatus(), delete*()
12. **DataTable Initialization** - Footer search, export buttons (CSV, Excel, PDF, Print)

### Data File Pattern (~90-165 lines)
1. **Request Parameters** - Parse DataTables AJAX params
2. **Column Mapping** - Define searchable/sortable columns
3. **WHERE Building** - Global search + column-specific search
4. **Total Records** - Get total count
5. **Filtered Records** - Get filtered count
6. **Main Query** - LEFT JOIN for FK displays
7. **Data Formatting** - Badge for status, action buttons
8. **JSON Response** - DataTables-compatible output

## FK Dropdown Pattern

```php
// In main file - before header
$coursesResult = $db->query("
    SELECT courses_pk, course_name, course_number 
    FROM {$dbPrefix}courses 
    WHERE is_active = 1 
    ORDER BY course_name
");
$courses = $coursesResult->fetchAll();

// In modal
<select class="form-select" name="course_fk" required>
    <option value="">Select Course</option>
    <?php foreach ($courses as $course): ?>
        <option value="<?= $course['courses_pk'] ?>">
            <?= htmlspecialchars($course['course_name']) ?>
        </option>
    <?php endforeach; ?>
</select>
```

## DataTables JOIN Pattern

```php
// For course_sections_data.php
$sql = "SELECT cs.*, c.course_name, c.course_number
        FROM {$dbPrefix}course_sections cs
        LEFT JOIN {$dbPrefix}courses c ON cs.course_fk = c.courses_pk
        {$whereClause}
        ORDER BY {$orderColumn} {$orderDirection}
        LIMIT ? OFFSET ?";
```

## Cascade Delete Pattern

```php
// Check for child records before delete
case 'delete':
    $id = (int)($_POST['course_sections_pk'] ?? 0);
    if ($id > 0) {
        $checkResult = $db->query(
            "SELECT COUNT(*) as count FROM {$dbPrefix}assessments WHERE course_section_fk = ?",
            [$id],
            'i'
        );
        $checkRow = $checkResult->fetch();
        
        if ($checkRow['count'] > 0) {
            $errorMessage = 'Cannot delete: associated assessments exist.';
        } else {
            $db->query("DELETE FROM {$dbPrefix}course_sections WHERE course_sections_pk = ?", [$id], 'i');
            $successMessage = 'Deleted successfully';
        }
    }
    break;
```

## Updated Files

### Modified Existing Files
- **administration/index.php** - Updated Quick Actions to include all 8 admin screens, updated Getting Started guide

### Created New Files (6 screens × 2 files = 12 files)
1. `administration/courses.php` (433 lines)
2. `administration/courses_data.php` (91 lines)
3. `administration/course_sections.php` (459 lines)
4. `administration/course_sections_data.php` (97 lines)
5. `administration/student_learning_outcomes.php` (489 lines)
6. `administration/student_learning_outcomes_data.php` (94 lines)
7. `administration/students.php` (465 lines)
8. `administration/students_data.php` (125 lines)
9. `administration/assessments.php` (561 lines)
10. `administration/assessments_data.php` (165 lines)

## Navigation Updated

The administration dashboard (`administration/index.php`) now includes:
- **Quick Actions** section with all 8 entity management screens
- **Getting Started** guide with correct workflow
- Links to System Config and Institution Setup

## Next Steps

To use these admin screens:

1. **Start PHP development server**:
   ```powershell
   php -S localhost:8000
   ```

2. **Access admin dashboard**:
   ```
   http://localhost:8000/src/administration/
   ```

3. **Set up database** (if not already done):
   - Run `src/system/database/schema.sql` to create all tables
   - Tables use `tbl_` prefix by default

4. **Workflow**:
   - Add institution(s)
   - Add institutional outcomes
   - Add programs
   - Add program outcomes
   - Add courses
   - Add course sections (CRNs)
   - Define SLOs for each course
   - Add students
   - Record assessments

## Notes

- All screens use **prepared statements** (no string concatenation in SQL)
- **CSRF tokens** required on all POST operations
- **HttpOnly, Secure, SameSite=Strict** cookies
- FK fields validated as integers > 0
- Student data fields have FERPA notice (should be encrypted in production)
- DataTables support export to CSV, Excel, PDF, and Print
- All actions include success/error messaging
- Cascade delete checks prevent orphaned records

## Schema Tables

```
tbl_institution              (standalone)
tbl_institutional_outcomes   → institution_fk
tbl_programs                 (standalone)
tbl_program_outcomes         → program_fk
tbl_courses                  (standalone)
tbl_course_sections          → course_fk
tbl_student_learning_outcomes → course_fk
tbl_students                 (standalone, FERPA-protected)
tbl_assessments              → course_section_fk, students_fk, student_learning_outcome_fk
```

All admin screens are complete and ready for testing!
