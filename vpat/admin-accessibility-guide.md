# Admin Pages WCAG 2.2 AA Accessibility Checklist

**Status**: WCAG 2.2 Level AA compliance implemented for admin layout and key pages.

## Overview

All MOSAIC administration pages MUST meet WCAG 2.2 Level AA standards. This document provides a checklist and implementation patterns for ensuring accessibility across all admin pages.

## Admin Layout (COMPLETED)

### Header (`src/system/plugins/local/theme-adminlte/layouts/admin/header.php`)

**Status**: [OK] WCAG 2.2 AA COMPLIANT

- [OK] Skip link to main content
- [OK] Semantic HTML landmarks (`<header role="navigation">`, `<aside role="navigation">`, `<main role="main">`)
- [OK] Enhanced focus indicators (3px outline + 4px shadow)
- [OK] Reduced motion support (`@media (prefers-reduced-motion: reduce)`)
- [OK] Enhanced contrast (#495057 for text-muted - 8.59:1 ratio)
- [OK] Touch targets (44x44px minimum)
- [OK] Enhanced line spacing (line-height: 1.5, paragraph spacing: 1.5em)
- [OK] Navbar with `role="navigation"` and `aria-label="Main navigation"`
- [OK] Hamburger toggle with `aria-label="Toggle sidebar navigation"` and `aria-expanded`
- [OK] All icons marked `aria-hidden="true"`
- [OK] Term selector dropdown with proper `<label for="headerTermSelector">`
- [OK] User dropdown with `role="menubar"`, `aria-haspopup="true"`, `aria-expanded="false"`
- [OK] Sidebar with `id="sidebar-nav"`, `role="navigation"`, `aria-label="Sidebar navigation"`
- [OK] Sidebar menu with `role="menu"` structure
- [OK] Menu items with `role="menuitem"` and `aria-current="page"` for active items
- [OK] Section headers with `role="presentation"`
- [OK] Disabled links with `aria-disabled="true"`

### Footer (`src/system/plugins/local/theme-adminlte/layouts/admin/footer.php`)

**Status**: [OK] WCAG 2.2 AA COMPLIANT

- [OK] Footer with `role="contentinfo"`

## Page-Specific Patterns

### Dashboard Pages (Statistical Overview)

**Example**: `src/administration/index.php` (COMPLETED)

Required elements:

1. **Semantic Sections**:
   ```php
   <section aria-labelledby="section-heading-id">
       <h2 id="section-heading-id">Section Title</h2>
       <!-- Content -->
   </section>
   ```

2. **Statistics Cards** (AdminLTE "small-box"):
   ```php
   <div class="small-box bg-info" role="region" aria-label="[Statistic name] statistic">
       <div class="inner">
           <h3><?= number_format($count) ?></h3>
           <p>[Label]</p>
       </div>
       <div class="icon" aria-hidden="true">
           <i class="fas fa-icon-name"></i>
       </div>
       <a href="[url]" class="small-box-footer" aria-label="View [entity] page">
           View [Entity] <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
       </a>
   </div>
   ```

3. **Alert Messages**:
   ```php
   <div class="alert alert-warning" role="alert">
       <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
       [Message]
   </div>
   ```

4. **Card Titles**: Use `<h2>` (not H3) for top-level section headings in main content

### Data Table Pages (CRUD Operations)

**Example**: `src/administration/students.php` (COMPLETED)

**Template**: Apply this pattern to:
- `terms.php`
- `institutional_outcomes.php`
- `programs.php`
- `program_outcomes.php`
- `courses.php`
- `student_learning_outcomes.php`
- `enrollment.php`
- `assessments.php`
- `users.php`

Required elements:

1. **Breadcrumbs**:
   ```php
   <ol class="breadcrumb float-sm-end">
       <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
       <li class="breadcrumb-item active" aria-current="page">[Page Name]</li>
   </ol>
   ```

2. **Alert Messages**:
   ```php
   <div class="alert alert-success alert-dismissible fade show" role="alert">
       <i class="fas fa-check-circle" aria-hidden="true"></i> <?= $successMessage ?>
       <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close alert"></button>
   </div>
   ```

3. **Card Header** with Add Button:
   ```php
   <div class="card-header">
       <h2 class="card-title">
           <i class="fas fa-table" aria-hidden="true"></i> [Entity Name]
       </h2>
       <div class="card-tools">
           <button type="button" class="btn btn-primary btn-sm" 
                   data-bs-toggle="modal" data-bs-target="#addModal" 
                   aria-label="Add new [entity]">
               <i class="fas fa-plus" aria-hidden="true"></i> Add [Entity]
           </button>
       </div>
   </div>
   ```

4. **Data Table**:
   ```php
   <table id="entityTable" class="table table-bordered table-striped" 
          aria-label="[Entity] data table">
       <caption class="visually-hidden">
           List of [entities] with filtering and sorting capabilities
       </caption>
       <thead>
           <tr>
               <th scope="col">Column 1</th>
               <th scope="col">Column 2</th>
               <!-- ... -->
               <th scope="col">Actions</th>
           </tr>
           <tr>
               <!-- Filter row - populated by JavaScript -->
           </tr>
       </thead>
       <tbody></tbody>
   </table>
   ```

5. **DataTables Filter Inputs** (JavaScript):
   ```javascript
   $('#entityTable thead tr:eq(1) th').each(function(i) {
       var title = $('#entityTable thead tr:eq(0) th:eq(' + i + ')').text();
       if (title !== 'Actions') {
           $(this).html('<input type="text" class="form-control form-control-sm" ' +
                       'placeholder="Search ' + title + '" ' +
                       'aria-label="Filter by ' + title + '" />');
       } else {
           $(this).html('');
       }
   });
   ```

6. **DataTables Export Buttons** (WCAG 2.4.6 - Descriptive labels):
   ```javascript
   buttons: [
       {extend: 'copy', text: 'Copy', attr: {'aria-label': 'Copy table data to clipboard'}},
       {extend: 'csv', text: 'CSV', attr: {'aria-label': 'Export table data as CSV'}},
       {extend: 'excel', text: 'Excel', attr: {'aria-label': 'Export table data as Excel'}},
       {extend: 'pdf', text: 'PDF', attr: {'aria-label': 'Export table data as PDF'}},
       {extend: 'print', text: 'Print', attr: {'aria-label': 'Print table data'}}
   ]
   ```

7. **DataTables Action Buttons** (WCAG 2.4.9, 2.5.5 - Link Purpose, Target Size Enhanced):
   
   **Pattern**: Use a single Edit button (44x44px) with descriptive aria-label. Place Delete and Toggle Status actions inside the Edit modal for progressive disclosure.
   
   **Accessibility Benefits**:
   - **Touch Target Size (WCAG 2.5.5 AAA)**: 44x44px buttons meet Enhanced Target Size guidelines
   - **Link Purpose (WCAG 2.4.9 AAA)**: aria-label provides context (entity type + identifier)
   - **Progressive Disclosure**: Destructive actions (delete, toggle status) require intentional modal open
   - **Cleaner Tables**: Reduces visual clutter and cognitive load
   - **Screen Reader Experience**: One clear action per row with descriptive label
   
   **Example** (enrollment_data.php):
   ```php
   $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
   
   $data[] = [
       // ... other columns ...
       '<button class="btn btn-warning" title="Edit" onclick=\'editEnrollment(' . $rowJson . ')\' aria-label="Edit enrollment for ' . htmlspecialchars($row['student_id'], ENT_QUOTES) . '"><i class="fas fa-edit" aria-hidden="true"></i></button>'
   ];
   ```
   
   **Pattern for Different Entity Types**:
   - **Courses**: `aria-label="Edit course [course_code] - [course_name]"`
   - **Programs**: `aria-label="Edit program [program_code] - [program_name]"`
   - **Students**: `aria-label="Edit student [student_id]"`
   - **Users**: `aria-label="Edit user [user_email]"`
   - **Terms**: `aria-label="Edit term [term_code]"`
   - **Outcomes**: `aria-label="Edit outcome [outcome_code]"`
   - **Assessments**: `aria-label="Edit assessment [assessment_pk]"`
   - **Enrollment**: `aria-label="Edit enrollment for [student_id]"`
   
   **DEPRECATED Pattern** (DO NOT USE):
   ```php
   // OLD: Multiple small buttons (32x32px - fails WCAG 2.5.5 AAA)
   '<button class="btn btn-sm btn-info">View</button>
   '<button class="btn btn-sm btn-primary">Edit</button>
   '<button class="btn btn-sm btn-warning">Toggle</button>
   '<button class="btn btn-sm btn-danger">Delete</button>
   ```

8. **Modals** (Add/Edit):
   
   **Add Modal Pattern**:
   ```php
   <div class="modal fade" id="addModal" tabindex="-1" 
        aria-labelledby="addModalLabel" aria-hidden="true">
       <div class="modal-dialog modal-lg">
           <div class="modal-content">
               <div class="modal-header bg-primary text-white">
                   <h5 class="modal-title" id="addModalLabel">
                       <i class="fas fa-plus" aria-hidden="true"></i> Add [Entity]
                   </h5>
                   <button type="button" class="btn-close btn-close-white" 
                           data-bs-dismiss="modal" aria-label="Close dialog"></button>
               </div>
               <form method="POST">
                   <div class="modal-body">
                       <!-- Form fields -->
                   </div>
                   <div class="modal-footer">
                       <button type="button" class="btn btn-secondary" 
                               data-bs-dismiss="modal">Cancel</button>
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save" aria-hidden="true"></i> Save
                       </button>
                   </div>
               </form>
           </div>
       </div>
   </div>
   ```
   
   **Edit Modal Pattern** (with Progressive Disclosure for Destructive Actions):
   ```php
   <div class="modal fade" id="editModal" tabindex="-1" 
        aria-labelledby="editModalLabel" aria-hidden="true">
       <div class="modal-dialog modal-lg">
           <div class="modal-content">
               <div class="modal-header bg-warning text-white">
                   <h5 class="modal-title" id="editModalLabel">
                       <i class="fas fa-edit" aria-hidden="true"></i> Edit [Entity]
                   </h5>
                   <button type="button" class="btn-close btn-close-white" 
                           data-bs-dismiss="modal" aria-label="Close dialog"></button>
               </div>
               <form method="POST" id="editForm">
                   <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                   <input type="hidden" name="action" value="update">
                   <input type="hidden" id="editEntityPk" name="[entity]_pk">
                   
                   <div class="modal-body">
                       <!-- Form fields for editing -->
                   </div>
                   
                   <div class="modal-footer d-flex justify-content-between">
                       <!-- LEFT SIDE: Destructive Actions (Progressive Disclosure) -->
                       <div>
                           <!-- Delete Button (if entity supports deletion) -->
                           <button type="button" class="btn btn-danger" 
                                   onclick="confirmDelete[Entity]()" 
                                   aria-label="Delete [entity type]">
                               <i class="fas fa-trash" aria-hidden="true"></i> Delete
                           </button>
                           
                           <!-- Toggle Status Button (if entity has is_active field) -->
                           <button type="button" class="btn btn-info" 
                                   onclick="toggleStatus[Entity]()" 
                                   id="toggleStatusBtn" 
                                   aria-label="Toggle entity status">
                               <i class="fas fa-toggle-on" aria-hidden="true"></i> 
                               <span id="toggleStatusText">Deactivate</span>
                           </button>
                       </div>
                       
                       <!-- RIGHT SIDE: Primary Actions -->
                       <div>
                           <button type="button" class="btn btn-secondary" 
                                   data-bs-dismiss="modal">Cancel</button>
                           <button type="submit" class="btn btn-primary">
                               <i class="fas fa-save" aria-hidden="true"></i> Save Changes
                           </button>
                       </div>
                   </div>
               </form>
           </div>
       </div>
   </div>
   ```
   
   **Progressive Disclosure Rationale**:
   - **Delete** and **Toggle Status** buttons are placed inside the Edit modal (not as inline DataTable buttons)
   - Requires intentional action (open modal -> locate button -> confirm)
   - Reduces accidental destructive actions
   - Allows larger touch targets (44x44px) with fewer buttons per DataTable row
   - Provides context (seeing full entity details before destructive action)
   - Meets WCAG 3.3.4 (Error Prevention - Legal, Financial, Data) by requiring deliberate steps

8. **Form Fields** (WCAG 3.3.2 - Labels or Instructions):
   ```php
   <!-- Required fields -->
   <div class="mb-3">
       <label for="fieldId" class="form-label">
           Field Label <span class="text-danger" aria-label="required">*</span>
       </label>
       <input type="text" class="form-control" id="fieldId" name="field_name" 
              required aria-required="true" aria-describedby="fieldIdHelp">
       <small id="fieldIdHelp" class="form-text text-muted">
           Help text if needed
       </small>
   </div>
   
   <!-- Optional fields -->
   <div class="mb-3">
       <label for="optionalFieldId" class="form-label">Optional Field</label>
       <input type="text" class="form-control" id="optionalFieldId" 
              name="optional_field" maxlength="255">
   </div>
   
   <!-- Checkboxes -->
   <div class="form-check">
       <input type="checkbox" class="form-check-input" id="checkboxId" 
              name="checkbox_name" checked>
       <label class="form-check-label" for="checkboxId">Checkbox Label</label>
   </div>
   ```

9. **Hidden Forms** (for status toggle/delete):
   ```php
   <form id="deleteForm" method="POST" style="display: none;" 
         aria-label="Delete [entity]">
       <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
       <input type="hidden" name="action" value="delete">
       <input type="hidden" name="[entity]_pk" id="deletePk">
   </form>
   ```

### Import Pages (File Upload)

**Example**: `src/administration/imports.php` (NEEDS IMPLEMENTATION)

Additional requirements:

1. **File Upload Input**:
   ```php
   <label for="csvFile" class="form-label">
       CSV File <span class="text-danger" aria-label="required">*</span>
   </label>
   <input type="file" class="form-control" id="csvFile" name="csv_file" 
          accept=".csv" required aria-required="true" 
          aria-describedby="csvFileHelp">
   <small id="csvFileHelp" class="form-text text-muted">
       Upload a CSV file in the specified format
   </small>
   ```

2. **Progress Indicators** (if async upload):
   ```php
   <div role="status" aria-live="polite" aria-atomic="true" 
        id="uploadProgress" style="display: none;">
       <div class="progress">
           <div class="progress-bar" role="progressbar" 
                aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" 
                style="width: 0%">
               0%
           </div>
       </div>
   </div>
   ```

3. **Live Result Messages**:
   ```php
   <div role="region" aria-live="polite" aria-atomic="true" 
        id="importResults"></div>
   ```

### Configuration Pages

**Example**: `src/administration/config.php` (NEEDS IMPLEMENTATION)

Follow form accessibility patterns with additional considerations:

1. **Section Grouping**:
   ```php
   <fieldset>
       <legend>Section Name</legend>
       <!-- Configuration options -->
   </fieldset>
   ```

2. **Help Text for Complex Settings**:
   ```php
   <label for="settingId" class="form-label">Setting Name</label>
   <input type="text" class="form-control" id="settingId" 
          name="setting_name" aria-describedby="settingIdHelp">
   <small id="settingIdHelp" class="form-text text-muted">
       Detailed explanation of what this setting does
   </small>
   ```

## Icon Usage

**ALL icons MUST include `aria-hidden="true"`:**

```php
<!-- Correct -->
<i class="fas fa-check-circle" aria-hidden="true"></i>
<i class="bi bi-person-circle" aria-hidden="true"></i>

<!-- Incorrect (fails WCAG 1.3.1) -->
<i class="fas fa-check-circle"></i>
```

Why: Screen readers will attempt to read icon fonts as text, creating confusion. The `aria-hidden` attribute prevents this.

## WCAG 2.2 AA Compliance Checklist

Use this checklist when implementing or auditing admin pages:

### Visual Design & Layout
- [ ] Color contrast ratio >= 4.5:1 for normal text (WCAG 1.4.3)
- [ ] Color contrast ratio >= 3:1 for large text (WCAG 1.4.3)
- [ ] Color contrast ratio >= 3:1 for UI components (WCAG 1.4.11)
- [ ] Information not conveyed by color alone (WCAG 1.4.1)
- [ ] Text can be resized to 200% without loss of functionality (WCAG 1.4.4)
- [ ] Reduced motion support via CSS `@media (prefers-reduced-motion)` (WCAG 2.3.3 Level AAA)

### Keyboard Navigation
- [ ] All interactive elements keyboard accessible (WCAG 2.1.1)
- [ ] No keyboard traps (WCAG 2.1.2)
- [ ] Skip link to main content (WCAG 2.4.1)
- [ ] Focus order follows logical sequence (WCAG 2.4.3)
- [ ] Focus indicator visible (3px outline + 4px shadow) (WCAG 2.4.7, 2.4.11, 2.4.13)
- [ ] Keyboard shortcuts don't conflict (WCAG 2.1.4)

### Semantic HTML & Structure
- [ ] Page has valid HTML structure (WCAG 4.1.1)
- [ ] Landmarks used correctly (`<header>`, `<nav>`, `<main>`, `<aside>`, `<footer>`) (WCAG 1.3.1)
- [ ] Headings follow logical hierarchy (H1->H2->H3, no skipped levels) (WCAG 1.3.1)
- [ ] Lists use proper markup (`<ul>`, `<ol>`, `<dl>`) (WCAG 1.3.1)
- [ ] Tables have structure (`<th scope="col">`, `<caption>`) (WCAG 1.3.1)

### ARIA (Accessible Rich Internet Applications)
- [ ] All icons marked `aria-hidden="true"` (WCAG 1.3.1)
- [ ] Links have descriptive purpose indicated by link text or ARIA (WCAG 2.4.4)
- [ ] Buttons have descriptive labels (visible text or `aria-label`) (WCAG 2.4.6)
- [ ] Form inputs have associated labels (WCAG 3.3.2)
- [ ] Required fields indicated with `aria-required="true"` (WCAG 3.3.2)
- [ ] Live regions used for dynamic content (`aria-live="polite"`) (WCAG 4.1.3)
- [ ] Modals have `aria-labelledby` pointing to title (WCAG 4.1.2)
- [ ] Current page indicated with `aria-current="page"` (WCAG 4.1.2)
- [ ] Disabled elements have `aria-disabled="true"` (WCAG 4.1.2)
- [ ] Expandable/collapsible elements have `aria-expanded` (WCAG 4.1.2)
- [ ] Menus use proper ARIA menu pattern (`role="menu"`, `role="menuitem"`) (WCAG 4.1.2)

### Forms
- [ ] Labels explicitly associated with inputs (for/id) (WCAG 1.3.1, 3.3.2)
- [ ] Required fields marked visually and with `aria-required` (WCAG 3.3.2)
- [ ] Error messages programmatically associated with fields (WCAG 3.3.1)
- [ ] Help text associated with `aria-describedby` (WCAG 3.3.2)
- [ ] Form submission provides success/error feedback (WCAG 3.3.1)

### Interactive Elements
- [ ] Touch targets >= 44x44px (WCAG 2.5.5 Level AAA, 2.5.8 Level AA requires 24px)
- [ ] Button purpose clear from label/context (WCAG 2.4.6)
- [ ] No reliance on hover-only interactions (WCAG 2.1.1)
- [ ] Confirmation for destructive actions (WCAG 3.3.4)

### Content
- [ ] Page title describes topic/purpose (WCAG 2.4.2)
- [ ] Link text describes destination/purpose (WCAG 2.4.4)
- [ ] Headings describe topic/purpose (WCAG 2.4.6)
- [ ] Text alternatives for non-text content (WCAG 1.1.1)

## Testing Checklist

Before marking a page as WCAG 2.2 AA compliant:

1. **Automated Testing**:
   - [ ] Run axe DevTools browser extension
   - [ ] Run WAVE browser extension
   - [ ] Validate HTML (W3C validator)

2. **Keyboard Testing**:
   - [ ] Tab through all interactive elements
   - [ ] Verify focus indicator visible on all elements
   - [ ] Test skip link (Tab once, Enter to skip)
   - [ ] Test form submission with Enter key
   - [ ] Test modal close with Escape key
   - [ ] Test all dropdowns with Arrow keys

3. **Screen Reader Testing** (NVDA/JAWS/VoiceOver):
   - [ ] Verify page title announced
   - [ ] Navigate by headings (H key in NVDA/JAWS)
   - [ ] Navigate by landmarks (D key in NVDA/JAWS)
   - [ ] Verify form labels read correctly
   - [ ] Verify table structure readable
   - [ ] Verify icons not announced
   - [ ] Verify required fields announced
   - [ ] Verify error messages announced

4. **Visual Testing**:
   - [ ] Zoom to 200% - verify no horizontal scroll, all content readable
   - [ ] Test with Windows High Contrast Mode
   - [ ] Use Color Contrast Analyzer tool on all text

5. **Mobile/Touch Testing**:
   - [ ] All buttons/links easily tappable (44x44px target)
   - [ ] Forms usable on mobile
   - [ ] Tables scroll horizontally or reflow

## Common Issues & Solutions

### Issue: Icons announcing meaningless text to screen readers
**Solution**: Add `aria-hidden="true"` to all icon elements

### Issue: Modal title not announced by screen reader
**Solution**: Add `aria-labelledby="modalTitleId"` to modal div, ensure title has matching ID

### Issue: Table not navigable by screen reader
**Solution**: Add `scope="col"` to all `<th>` in `<thead>`, add `<caption>` or `aria-label`

### Issue: Form errors not announced to screen reader
**Solution**: Use `role="alert"` on error messages, or `aria-describedby` to associate error with field

### Issue: Filter inputs in DataTables announced without context
**Solution**: Add `aria-label="Filter by [column name]"` to each filter input

### Issue: Buttons only show icons, unclear purpose
**Solution**: Add descriptive `aria-label="[Action description]"` to button

### Issue: Current page not indicated in navigation
**Solution**: Add `aria-current="page"` to active nav link

### Issue: Disabled navigation items not announced as disabled
**Solution**: Add `aria-disabled="true"` to disabled links (don't rely on `.disabled` class alone)

## Page Implementation Status

| Page | Status | Notes |
|------|--------|-------|
| **Layout** | | |
| Admin Header | [OK] COMPLIANT | Full WCAG 2.2 AA implementation |
| Admin Footer | [OK] COMPLIANT | Role contentinfo added |
| **Dashboard** | | |
| index.php | [OK] COMPLIANT | Statistics cards, system status |
| **Data Tables** | | |
| students.php | [OK] COMPLIANT | Template for other CRUD pages |
| terms.php | [REVIEW] NEEDS REVIEW | Apply students.php pattern |
| institutional_outcomes.php | [REVIEW] NEEDS REVIEW | Apply students.php pattern |
| programs.php | [REVIEW] NEEDS REVIEW | Apply students.php pattern |
| program_outcomes.php | [REVIEW] NEEDS REVIEW | Apply students.php pattern |
| courses.php | [REVIEW] NEEDS REVIEW | Apply students.php pattern |
| student_learning_outcomes.php | [REVIEW] NEEDS REVIEW | Apply students.php pattern |
| enrollment.php | [REVIEW] NEEDS REVIEW | Apply students.php pattern |
| assessments.php | [REVIEW] NEEDS REVIEW | Apply students.php pattern |
| **Import/Export** | | |
| imports.php | [REVIEW] NEEDS REVIEW | File upload specific patterns |
| **System** | | |
| users.php | [REVIEW] NEEDS REVIEW | Apply students.php pattern |
| config.php | [REVIEW] NEEDS REVIEW | Form-heavy, fieldset grouping |
| **LTI** | | |
| lti/index.php | [REVIEW] NEEDS REVIEW | LTI configuration interface |

## VPAT (Voluntary Product Accessibility Template)

Once all admin pages are compliant, generate a comprehensive VPAT documenting the admin interface accessibility conformance. See `vpat/assessment-page-vpat.md` for example format.

**VPAT should document**:
- Product: MOSAIC Administration Interface
- WCAG 2.2 Level A: Full conformance expected
- WCAG 2.2 Level AA: Full conformance expected
- WCAG 2.2 Level AAA: Partial conformance (enhanced features like 44px touch targets, 8.59:1 contrast, reduced motion)

## Resources

- [WCAG 2.2 Guidelines](https://www.w3.org/WAI/WCAG22/quickref/)
- [ARIA Authoring Practices Guide (APG)](https://www.w3.org/WAI/ARIA/apg/)
- [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)
- [axe DevTools Browser Extension](https://www.deque.com/axe/devtools/)
- [NVDA Screen Reader (Windows)](https://www.nvaccess.org/)
- [JAWS Screen Reader (Windows)](https://www.freedomscientific.com/products/software/jaws/)
- [VoiceOver (macOS/iOS)](https://www.apple.com/accessibility/voiceover/)

## Next Steps

1. **Apply students.php pattern to remaining data table pages** (terms, programs, courses, etc.)
2. **Implement imports.php accessibility** (file upload + progress indicators)
3. **Implement config.php accessibility** (form grouping + complex settings)
4. **Test all pages with screen reader** (NVDA or JAWS)
5. **Generate comprehensive admin interface VPAT**
