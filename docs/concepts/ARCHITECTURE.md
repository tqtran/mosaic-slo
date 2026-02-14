# MOSAIC Architecture

## Overview

MOSAIC (Modular Outcomes System for Achievement and Institutional Compliance) is an open-source Student Learning Outcomes assessment platform with LTI integration support.

## Directory Structure

```
mosaic-slo/
├── design_concepts/       # Architecture & design documentation
│   ├── ARCHITECTURE.md    # This file - system overview
│   ├── MVC.md            # MVC pattern documentation
│   ├── PLUGIN.md         # Plugin architecture
│   └── SCHEMA.md         # Database schema documentation
├── src/                   # Source code (to be created)
│   ├── controllers/      # Application controllers
│   ├── models/           # Data models
│   ├── views/            # View templates
│   └── config/           # Configuration files
├── public/                # Public web files (to be created)
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript files
│   └── index.php         # Application entry point
├── database/              # Database files (to be created)
│   ├── migrations/       # Database migration scripts
│   └── seeds/            # Sample data
└── README.md             # Project overview (to be created)
```

## Architecture Layers

### 1. Presentation Layer (Views)
- HTML templates with PHP
- Bootstrap/CSS for styling
- JavaScript for interactivity
- See [VIEWS.md](VIEWS.md)

### 2. Application Layer (Controllers)
- Request handling
- Business logic
- Route management
- See [CONTROLLERS.md](CONTROLLERS.md)

### 3. Data Layer (Models)
- Database interaction
- Data validation
- CRUD operations
- See [MODELS.md](MODELS.md)

### 4. Database Layer
- MySQL database
- Assessment data storage
- LTI consumer management
- See [SCHEMA.md](SCHEMA.md)

## Assessment Hierarchy

```
Institution
    └── Institutional Outcomes
        └── Program Outcomes
            └── Programs

SLO Sets (per year/quarter/period)
    ├── Terms
    │   └── Course Sections
    │       └── Enrollment (with CRN)
    │           └── Assessments
    └── Student Learning Outcomes (SLOs)
        └── Mapped to Program Outcomes
```

**Key Concepts:**
- **SLO Sets**: SLOs are grouped by time period (academic year, quarter, semester, etc.)
- **Terms**: Each academic term is linked to an SLO Set, determining which SLOs are assessed
- **CRN**: Each enrollment has a unique Course Reference Number
- **Assessments**: Tied to enrollment records (via CRN) for specific SLOs from the term's SLO Set

## Key Features

1. **Multi-level Assessment Tracking**
   - Institutional outcomes
   - Program outcomes
   - Student learning outcomes
   - Individual student assessments

2. **Organizational Structure**
   - Departments
   - Programs
   - Courses
   - Course sections (by term)

3. **User Management**
   - Role-based access control
   - Context-aware permissions
   - Audit trail tracking

4. **LTI Integration**
   - LTI 1.1/1.3 support
   - Consumer key management
   - Secure launch validation

## Entity Relationship Diagram

```
institution
    └─> institutional_outcomes
        └─> program_outcomes
            └─> programs

slo_sets (per year/quarter/period)
    ├─> terms
    │   └─> course_sections
    │       └─> enrollment (with CRN)
    │           ├─> students
    │           └─> assessments
    └─> student_learning_outcomes
        └─> courses

departments
    ├─> programs
    └─> courses

users
    ├─> user_roles -> roles
    ├─> course_sections (instructor)
    └─> audit fields (created_by, updated_by, assessed_by)
```

## Technology Stack

- **Backend**: PHP 8.1+ (required)
- **Database**: MySQL 8.0+ (required, InnoDB engine, UTF8MB4 charset)
  - No database abstraction layer - direct MySQL implementation
  - For alternative storage needs, use data connector plugins
- **Web Server**: Apache, Nginx, or any PHP 8.1+ compatible server
- **Frontend**: HTML5, CSS3, JavaScript (Bootstrap 5)

## Security Features

- Password hashing (bcrypt)
- SQL injection prevention (prepared statements)
- XSS protection (output escaping)
- CSRF protection
- Role-based access control
- Audit logging

## Documentation

- [SCHEMA.md](SCHEMA.md) - Complete database schema
- [MODELS.md](MODELS.md) - Model classes and methods
- [CONTROLLERS.md](CONTROLLERS.md) - Controller routes and logic
- [VIEWS.md](VIEWS.md) - View templates and examples
