# MVC Architecture Documentation

## Overview

SLO Cloud follows the Model-View-Controller (MVC) pattern for clean separation of concerns and maintainable code structure.

## Architecture Layers

```
┌─────────────────────────────────────────┐
│          Views (Presentation)           │
│  - HTML templates with PHP              │
│  - Bootstrap/CSS styling                │
│  - JavaScript interactions              │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│       Controllers (Application)         │
│  - Request handling                     │
│  - Business logic                       │
│  - Input validation                     │
│  - Route management                     │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│         Models (Data Layer)             │
│  - Database interaction                 │
│  - Data validation                      │
│  - CRUD operations                      │
│  - Business rules                       │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│           Database (MySQL)              │
│  - Data persistence                     │
│  - Referential integrity                │
│  - Transactions                         │
└─────────────────────────────────────────┘
```

---

## Models

### Base Model Class

**Location**: `src/Core/Model.php`

Provides common database operations for all models.

```php
abstract class Model {
    protected string $table;
    protected string $primaryKey;
    protected Database $db;
    
    // CRUD Operations
    public function all(): array
    public function find(int $id): ?array
    public function findBy(string $column, mixed $value): ?array
    public function create(array $data): int
    public function update(int $id, array $data): bool
    public function delete(int $id): bool
    
    // Query Building
    public function where(string $column, mixed $value): self
    public function orderBy(string $column, string $direction = 'ASC'): self
    public function limit(int $limit, int $offset = 0): self
    public function get(): array
}
```

### Domain Models

#### 1. Institution Models

**InstitutionalOutcome** (`src/Models/InstitutionalOutcome.php`)
```php
class InstitutionalOutcome extends Model {
    protected string $table = 'institutional_outcomes';
    protected string $primaryKey = 'institutional_outcomes_pk';
    
    public function findByInstitution(int $institutionId): array
    public function findActive(int $institutionId): array
    public function reorder(int $outcomeId, int $newSequence): bool
    public function getProgramOutcomes(int $outcomeId): array
    public function getAssessmentStats(int $outcomeId, ?int $termId = null): array
}
```

**ProgramOutcome** (`src/Models/ProgramOutcome.php`)
```php
class ProgramOutcome extends Model {
    protected string $table = 'program_outcomes';
    protected string $primaryKey = 'program_outcomes_pk';
    
    public function findByProgram(int $programId): array
    public function findByInstitutionalOutcome(int $institutionalOutcomeId): array
    public function findActive(int $programId): array
    public function getInstitutionalOutcome(int $outcomeId): ?array
    public function getStudentLearningOutcomes(int $outcomeId): array
    public function getAssessmentStats(int $outcomeId, ?int $termId = null): array
}
```

**StudentLearningOutcome** (`src/Models/StudentLearningOutcome.php`)
```php
class StudentLearningOutcome extends Model {
    protected string $table = 'student_learning_outcomes';
    protected string $primaryKey = 'student_learning_outcomes_pk';
    
    public function findByCourse(int $courseId): array
    public function findByProgramOutcome(int $programOutcomeId): array
    public function findActive(int $courseId): array
    public function getProgramOutcome(int $sloId): ?array
    public function getAssessments(int $sloId, ?int $sectionId = null): array
    public function getAssessmentStats(int $sloId, ?int $sectionId = null): array
}
```

#### 2. Organizational Models

**Department** (`src/Models/Department.php`)
```php
class Department extends Model {
    protected string $table = 'departments';
    protected string $primaryKey = 'departments_pk';
    
    public function findByCode(string $code): ?array
    public function getPrograms(int $departmentId): array
    public function getCourses(int $departmentId): array
    public function getStats(int $departmentId): array
}
```

**Program** (`src/Models/Program.php`)
```php
class Program extends Model {
    protected string $table = 'programs';
    protected string $primaryKey = 'programs_pk';
    
    public function findByDepartment(int $departmentId): array
    public function findByCode(string $code): ?array
    public function getOutcomes(int $programId): array
    public function getCourses(int $programId): array
}
```

**Course** (`src/Models/Course.php`)
```php
class Course extends Model {
    protected string $table = 'courses';
    protected string $primaryKey = 'courses_pk';
    
    public function findByDepartment(int $departmentId): array
    public function findByCode(string $code): ?array
    public function getSLOs(int $courseId): array
    public function getSections(int $courseId, ?int $termId = null): array
}
```

**CourseSection** (`src/Models/CourseSection.php`)
```php
class CourseSection extends Model {
    protected string $table = 'course_sections';
    protected string $primaryKey = 'course_sections_pk';
    
    public function findByCourse(int $courseId): array
    public function findByTerm(int $termId): array
    public function findByInstructor(int $instructorId): array
    public function getEnrollment(int $sectionId): array
    public function getAssessments(int $sectionId): array
}
```

#### 3. Student Models

**Student** (`src/Models/Student.php`)
```php
class Student extends Model {
    protected string $table = 'students';
    protected string $primaryKey = 'students_pk';
    
    public function findByStudentId(string $studentId): ?array
    public function getEnrollment(int $studentId): array
    public function getAssessments(int $studentId): array
}
```

**Enrollment** (`src/Models/Enrollment.php`)
```php
class Enrollment extends Model {
    protected string $table = 'enrollment';
    protected string $primaryKey = 'enrollment_pk';
    
    public function findBySection(int $sectionId): array
    public function findByStudent(int $studentId): array
    public function enroll(int $sectionId, int $studentId): int
    public function drop(int $enrollmentId): bool
}
```

#### 4. Assessment Models

**Assessment** (`src/Models/Assessment.php`)
```php
class Assessment extends Model {
    protected string $table = 'assessments';
    protected string $primaryKey = 'assessments_pk';
    
    public function findByEnrollment(int $enrollmentId): array
    public function findBySLO(int $sloId): array
    public function finalize(int $assessmentId): bool
    public function batchCreate(array $assessments): bool
}
```

#### 5. User Models

**User** (`src/Models/User.php`)
```php
class User extends Model {
    protected string $table = 'users';
    protected string $primaryKey = 'users_pk';
    
    public function findByUserId(string $userId): ?array
    public function findByEmail(string $email): ?array
    public function authenticate(string $userId, string $password): ?array
    public function getRoles(int $userId): array
    public function hasRole(int $userId, string $role, ?string $contextType = null, ?int $contextId = null): bool
}
```

---

## Controllers

### Base Controller Class

**Location**: `src/Core/Controller.php`

```php
abstract class Controller {
    protected View $view;
    protected Request $request;
    
    public function __construct() {
        $this->view = new View();
        $this->request = new Request();
    }
    
    protected function render(string $template, array $data = []): void
    protected function redirect(string $url): void
    protected function json(array $data): void
    protected function authorize(string $role, ?string $contextType = null, ?int $contextId = null): void
}
```

### Domain Controllers

#### 1. DashboardController

**Location**: `src/Controllers/DashboardController.php`

**Routes**:
- `GET /` - Main dashboard
- `GET /dashboard/faculty` - Faculty dashboard
- `GET /dashboard/admin` - Admin dashboard

**Methods**:
```php
public function index(): void           // Main dashboard
public function faculty(): void         // Faculty-specific dashboard
public function admin(): void           // Admin dashboard with system overview
```

#### 2. InstitutionalOutcomeController

**Location**: `src/Controllers/InstitutionalOutcomeController.php`

**Routes**:
- `GET /institutional-outcomes` - List outcomes
- `GET /institutional-outcomes/create` - Create form
- `POST /institutional-outcomes` - Store outcome
- `GET /institutional-outcomes/{id}` - View details
- `GET /institutional-outcomes/{id}/edit` - Edit form
- `PUT /institutional-outcomes/{id}` - Update outcome
- `POST /institutional-outcomes/{id}/reorder` - Reorder sequences
- `GET /institutional-outcomes/{id}/report` - Assessment report

#### 3. ProgramOutcomeController

**Location**: `src/Controllers/ProgramOutcomeController.php`

**Routes**:
- `GET /programs/{programId}/outcomes` - List outcomes for program
- `GET /programs/{programId}/outcomes/create` - Create form
- `POST /programs/{programId}/outcomes` - Store outcome
- `GET /outcomes/{id}` - View details
- `GET /outcomes/{id}/edit` - Edit form
- `PUT /outcomes/{id}` - Update outcome

#### 4. CourseController

**Location**: `src/Controllers/CourseController.php`

**Routes**:
- `GET /courses` - List courses
- `GET /courses/{id}` - Course details
- `GET /courses/{id}/slos` - Manage SLOs
- `GET /courses/{id}/sections` - Course sections

#### 5. CourseSectionController

**Location**: `src/Controllers/CourseSectionController.php`

**Routes**:
- `GET /sections/{id}` - Section details
- `GET /sections/{id}/enrollment` - Enrollment roster
- `GET /sections/{id}/assessments` - Assessment grid
- `POST /sections/{id}/assessments/batch` - Batch save assessments

#### 6. AssessmentController

**Location**: `src/Controllers/AssessmentController.php`

**Routes**:
- `GET /sections/{sectionId}/assess` - Assessment interface
- `POST /assessments` - Save assessment
- `POST /assessments/batch` - Batch save
- `PUT /assessments/{id}/finalize` - Finalize assessment

#### 7. ReportController

**Location**: `src/Controllers/ReportController.php`

**Routes**:
- `GET /reports/institutional` - Institutional outcomes report
- `GET /reports/program/{programId}` - Program outcomes report
- `GET /reports/course/{courseId}` - Course SLO report
- `GET /reports/instructor/{userId}` - Instructor report
- `GET /reports/export` - Export data

---

## Views

### View Structure

```
views/
├── layouts/
│   ├── main.php              # Main layout template
│   ├── auth.php              # Auth layout
│   └── print.php             # Print layout
├── dashboard/
│   ├── index.php             # Main dashboard
│   ├── faculty.php           # Faculty dashboard
│   └── admin.php             # Admin dashboard
├── outcomes/
│   ├── institutional/
│   │   ├── index.php         # List institutional outcomes
│   │   ├── create.php        # Create form
│   │   ├── edit.php          # Edit form
│   │   ├── show.php          # Details view
│   │   └── report.php        # Assessment report
│   └── program/
│       ├── index.php         # List program outcomes
│       ├── create.php        # Create form
│       ├── edit.php          # Edit form
│       └── show.php          # Details view
├── courses/
│   ├── index.php             # Course list
│   ├── show.php              # Course details
│   └── slos/
│       ├── index.php         # SLO list
│       ├── create.php        # Create SLO
│       └── edit.php          # Edit SLO
├── sections/
│   ├── show.php              # Section details
│   ├── enrollment.php        # Enrollment roster
│   └── assess.php            # Assessment grid
├── assessments/
│   ├── grid.php              # Assessment grid view
│   └── batch.php             # Batch entry form
├── reports/
│   ├── institutional.php     # Institutional report
│   ├── program.php           # Program report
│   └── course.php            # Course report
└── partials/
    ├── nav.php               # Navigation
    ├── header.php            # Header
    ├── footer.php            # Footer
    └── alerts.php            # Alert messages
```

### View Helper Class

**Location**: `src/Core/View.php`

```php
class View {
    protected string $layoutPath = 'views/layouts/main.php';
    protected array $data = [];
    
    public function render(string $template, array $data = []): void
    public function partial(string $partial, array $data = []): void
    public function setLayout(string $layout): void
    public function escape(string $value): string
    public function url(string $path): string
}
```

### View Examples

#### Assessment Grid View

**File**: `views/sections/assess.php`

```php
<?php $this->setLayout('layouts/main'); ?>

<div class="container">
    <h1><?= $this->escape($course['course_code']) ?> - <?= $this->escape($section['section_code']) ?></h1>
    
    <form method="POST" action="<?= $this->url('/assessments/batch') ?>">
        <table class="assessment-grid">
            <thead>
                <tr>
                    <th>Student</th>
                    <?php foreach ($slos as $slo): ?>
                    <th><?= $this->escape($slo['slo_code']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= $this->escape($student['last_name']) ?>, <?= $this->escape($student['first_name']) ?></td>
                    <?php foreach ($slos as $slo): ?>
                    <td>
                        <select name="assessments[<?= $student['students_pk'] ?>][<?= $slo['student_learning_outcomes_pk'] ?>]">
                            <option value="">-</option>
                            <option value="met">Met</option>
                            <option value="not_met">Not Met</option>
                        </select>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <button type="submit" class="btn btn-primary">Save Assessments</button>
    </form>
</div>
```

#### Program Outcomes Report

**File**: `views/reports/program.php`

```php
<?php $this->setLayout('layouts/main'); ?>

<div class="container">
    <h1>Program Outcomes Report: <?= $this->escape($program['program_name']) ?></h1>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Outcome Code</th>
                <th>Description</th>
                <th>Total Assessments</th>
                <th>Met</th>
                <th>Achievement Rate</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($outcomes as $outcome): ?>
            <tr>
                <td><?= $this->escape($outcome['code']) ?></td>
                <td><?= $this->escape($outcome['description']) ?></td>
                <td><?= $outcome['total_assessments'] ?></td>
                <td><?= $outcome['met_count'] ?></td>
                <td>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $outcome['achievement_rate'] ?>%">
                            <?= number_format($outcome['achievement_rate'], 1) ?>%
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

---

## Request Flow

### Example: Saving Assessment

1. **User Action**: Faculty submits assessment grid form
   ```
   POST /sections/123/assessments/batch
   ```

2. **Router**: Routes to `AssessmentController::batchSave()`

3. **Controller**:
   ```php
   public function batchSave(int $sectionId): void {
       $this->authorize('instructor', 'course', $sectionId);
       
       $data = $this->request->post('assessments');
       
       if ($this->assessmentModel->batchCreate($data)) {
           $this->redirect("/sections/{$sectionId}/assessments?success=1");
       }
   }
   ```

4. **Model**:
   ```php
   public function batchCreate(array $assessments): bool {
       $this->db->beginTransaction();
       
       foreach ($assessments as $studentId => $slos) {
           foreach ($slos as $sloId => $level) {
               $this->create([
                   'enrollment_fk' => $this->getEnrollmentId($studentId),
                   'student_learning_outcome_fk' => $sloId,
                   'achievement_level' => $level,
                   'assessed_date' => date('Y-m-d'),
                   'assessed_by_fk' => Auth::userId()
               ]);
           }
       }
       
       return $this->db->commit();
   }
   ```

5. **View**: Redirect to success page with confirmation message

---

## Best Practices

### Models
- Keep models thin - only data access logic
- Use transactions for multi-table operations
- Validate data before database operations
- Return arrays or null, not mixed types

### Controllers
- Keep controllers thin - delegate to models
- One action per method
- Always validate and sanitize input
- Use dependency injection
- Check authorization before operations

### Views
- Always escape output with `$this->escape()`
- Keep business logic out of views
- Use partials for reusable components
- Separate display logic from data manipulation
- Use layouts for consistent structure

### General
- Follow naming conventions consistently
- Document complex logic
- Handle errors gracefully
- Use type hints in PHP 7.4+
- Write testable code
