# MOSAIC Demo

This directory contains demonstration pages for the MOSAIC platform using AdminLTE 3 framework.

**Access via:** Navigate to `/mosaic-slo/demo/` in your browser

## Demo Pages

### 1. Dashboard (`dashboard.php`)
**URL:** `/mosaic-slo/demo/dashboard.php`

Main analytics dashboard with AdminLTE interface.

**Features:**
- Course selection and filtering
- Real-time analytics visualizations
- SLO assessment metrics by discipline
- Assessment type distribution charts
- Interactive data tables with search/sort
- Responsive AdminLTE sidebar navigation

### 2. SLO Administration (`admin_slo.php`)
**URL:** `/mosaic-slo/demo/admin_slo.php`

Administrative interface for managing Student Learning Outcomes.

**Features:**
- Course-based SLO management
- Outcomes hierarchy display (Institutional → Program → SLO)
- Assessment method tracking
- Bulk upload functionality
- Data export (CSV, Excel, PDF)
- AdminLTE DataTables integration

### 3. Student Management (`admin_users.php`)
**URL:** `/mosaic-slo/demo/admin_users.php`

Student enrollment and data management interface.

**Features:**
- Student enrollments by term
- Course assignments tracking
- Bulk import/export capabilities
- Search and filter functionality
- Responsive data tables

### 4. LTI Endpoint (`lti_endpoint.php`)
**URL:** `/mosaic-slo/demo/lti_endpoint.php`

Instructor-facing assessment interface (no sidebar menu, clean interface).

**Features:**
- SLO selection for assessment
- Student outcomes grid entry
- Batch outcome actions
- Score entry (optional)
- Demo mode badge

## Framework

**AdminLTE 3** - Modern admin dashboard template built on Bootstrap 4
- Responsive design
- Professional UI components
- Sidebar navigation with menu items
- DataTables integration
- Chart.js visualizations

## Sample Data

- **sample.csv** - Multi-course assessment data (~50K records)
- Includes: Art, Chemistry, Biology, Math, Computer Science courses
- Academic Year 2024-25, Fall/Spring terms

## Session Management

All pages include:
- Secure session configuration (HttpOnly, Secure, SameSite)
- CSRF token validation on POST requests
- Session ID regeneration (30-minute intervals)

## Usage

```powershell
# Start PHP development server from project root
php -S localhost:8000

# Access demo portal
http://localhost:8000/mosaic-slo/demo/

# Individual pages
http://localhost:8000/mosaic-slo/demo/dashboard.php
http://localhost:8000/mosaic-slo/demo/admin_slo.php
http://localhost:8000/mosaic-slo/demo/admin_users.php
http://localhost:8000/mosaic-slo/demo/lti_endpoint.php
```

## Framework Features

- **AdminLTE 3:** Professional admin dashboard framework
- **Navigation:** Consistent sidebar menu across admin pages
- **Layout:** Responsive admin dashboard layout
- **Components:** AdminLTE widgets, cards, and UI elements
- **Branding:** MOSAIC platform styling and colors
