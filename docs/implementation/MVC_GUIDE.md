# MVC Implementation Guide

This guide provides practical implementation patterns for the MVC architecture described in [../concepts/MVC.md](../concepts/MVC.md).

## Model Implementation Pattern

### Base Model Setup

All models extend a base Model class that provides:
- Database connection management
- CRUD operations (create, read, update, delete)
- Query building helpers
- Prepared statement execution

**Model class properties:**
- `$table` - Database table name
- `$primaryKey` - Primary key column name (following `{table}_pk` convention)

### Common Model Methods

**CRUD Operations:**
- `find($id)` - Retrieve single record by primary key
- `findAll()` - Retrieve all records
- `create($data)` - Insert new record, return primary key
- `update($id, $data)` - Update existing record
- `delete($id)` - Soft delete (set is_active = false) or hard delete

**Query Methods:**
- `findBy($conditions)` - Find records matching conditions
- `findOne($conditions)` - Find first record matching conditions
- Use prepared statements for all queries
- Return associative arrays or null

### Domain-Specific Methods

Each model implements methods specific to its domain:

**Student Model Example:**
- `findByStudentId($studentId)` - Lookup by external student ID
- `findByName($lastName, $firstName)` - Search by name
- `getEnrollments($studentId)` - Get student's enrollments
- `getAssessments($studentId)` - Get student's assessment history

**Course Model Example:**
- `findByCourseCode($code)` - Lookup by course code
- `getSections($courseId)` - Get all sections for course
- `getSLOs($courseId)` - Get SLOs associated with course
- `getEnrollmentCount($sectionId)` - Count students in section

## Controller Implementation Pattern

### Controller Responsibilities

Controllers handle the request/response cycle:
1. Receive and validate input
2. Check user permissions
3. Execute business logic via models
4. Prepare data for views
5. Render response (HTML or JSON)

### Request Handling

**Route Pattern:**
- `GET /admin/students` → `StudentController->index()`
- `GET /admin/students/123` → `StudentController->show($id)`
- `POST /admin/students` → `StudentController->create()`
- `PUT /admin/students/123` → `StudentController->update($id)`
- `DELETE /admin/students/123` → `StudentController->delete($id)`

**Input Access:**
- GET parameters: Query string values
- POST data: Form submissions or JSON payloads
- URL parameters: Route segments (e.g., ID from URL)

### Validation Pattern

**Pre-processing validation:**
1. Define validation rules (required, type, length, format)
2. Run validator on input data
3. Return error response if validation fails
4. Proceed with processing if validation passes

**Common validation rules:**
- `required` - Field must be present and non-empty
- `email` - Valid email format
- `numeric` - Numeric value
- `min:n` / `max:n` - Length constraints
- `in:a,b,c` - Value must be in list
- Custom validators for domain logic

### Authorization Pattern

**Permission checks before actions:**
1. Get current authenticated user
2. Check if user has required permission
3. Optionally check context (department, program, course scope)
4. Throw authorization exception if denied
5. Proceed with action if authorized

**Permission naming:**
- Format: `resource.action` (e.g., `students.edit`, `reports.view`)
- Check against user's assigned roles
- Support hierarchical permissions (admin inherits all)

### Response Rendering

**HTML responses:**
- Load view template
- Pass data array to view
- View renders with data interpolation
- Set appropriate HTTP headers

**JSON responses:**
- Serialize data to JSON
- Set `Content-Type: application/json` header
- Return appropriate HTTP status code
- Include error details in standardized format

## View Implementation Pattern

### Template Structure

Views are PHP templates with minimal logic:
- Located in `src/views/` directory
- Organized by controller/feature
- Use `.php` extension
- Include Bootstrap 5 classes for styling

### Data Access

Views receive data as variables:
- Controller passes associative array
- Array keys become variables in template scope
- Access with `$variableName`
- Escape output to prevent XSS

### Output Escaping

**Always escape user-generated content:**
- Use `htmlspecialchars()` for HTML context
- Use `json_encode()` for JavaScript context
- Use parameterized queries (never in views)
- Trust only explicitly sanitized data

### Partial/Component Pattern

**Reusable view fragments:**
- Header/footer templates
- Navigation menus
- Form components
- Data table layouts

Include partials in main template:
- Load partial file
- Pass data to partial scope
- Render in place

### Form Patterns

**Form structure:**
1. CSRF token field (hidden input)
2. Input fields with labels
3. Validation error display
4. Submit button
5. Cancel/back link

**Form validation display:**
- Show errors above form or next to fields
- Preserve submitted values on error
- Clear form on success
- Show success message

## Security Patterns

### SQL Injection Prevention

**Always use prepared statements:**
- Never concatenate user input into SQL
- Use parameter placeholders
- Bind values separately
- Let database driver handle escaping

### XSS Prevention

**Escape all output:**
- HTML context: Convert special characters
- JavaScript context: JSON encode
- URL context: URL encode
- CSS context: Sanitize values

### CSRF Protection

**Token validation on state-changing operations:**
1. Generate token on form render
2. Include hidden field in form
3. Validate token on submission
4. Reject request if token invalid/missing
5. Regenerate token after use

### Session Security

**Secure session configuration:**
- HttpOnly flag prevents JavaScript access
- Secure flag requires HTTPS
- SameSite=Strict prevents CSRF
- Regenerate ID on privilege change
- Set reasonable timeout (2 hours)

## Common Workflows

### Create Resource Workflow

1. **GET /resource/new** - Display creation form
   - Check user has create permission
   - Render form with CSRF token
   - Include any required lookup data (dropdowns, etc.)

2. **POST /resource** - Handle form submission
   - Validate CSRF token
   - Validate input data
   - Check user has create permission
   - Create record via model
   - Log action for audit
   - Redirect to success page or return JSON

### Update Resource Workflow

1. **GET /resource/{id}/edit** - Display edit form
   - Check user has edit permission
   - Load existing record
   - Render form pre-filled with current values
   - Include CSRF token

2. **PUT /resource/{id}** - Handle update submission
   - Validate CSRF token
   - Validate input data
   - Check user has edit permission on this resource
   - Update record via model
   - Log action for audit
   - Redirect to success page or return JSON

### Delete Resource Workflow

1. **DELETE /resource/{id}** - Handle delete request
   - Validate CSRF token
   - Check user has delete permission
   - Check for dependent records (prevent orphans)
   - Soft delete (set is_active = false) or hard delete
   - Log action for audit
   - Return success response

### List/Search Workflow

1. **GET /resource** - Display list with search/filter
   - Check user has view permission
   - Parse query parameters (search, filter, sort, page)
   - Build query with conditions
   - Apply pagination
   - Render list view with results

## Error Handling

### Exception Types

- **ValidationException** - Input validation failed
- **AuthenticationException** - User not logged in
- **ForbiddenException** - User lacks permission
- **NotFoundException** - Resource not found
- **DatabaseException** - Database operation failed

### Error Response Patterns

**HTML responses:**
- Display error page with user-friendly message
- Show form with validation errors
- Provide actionable next steps
- Log detailed error for debugging

**JSON responses:**
- Return error object with standardized structure
- Include error code and message
- Include field-specific errors for validation
- Use appropriate HTTP status code

**Status codes:**
- 400 Bad Request - Validation failed
- 401 Unauthorized - Not authenticated
- 403 Forbidden - Not authorized
- 404 Not Found - Resource doesn't exist
- 500 Internal Server Error - Server error

## Testing MVC Components

### Model Testing

**Unit tests for models:**
- Test CRUD operations
- Test domain-specific queries
- Test validation logic
- Mock database for isolation
- Test error handling

### Controller Testing

**Integration tests for controllers:**
- Test route handling
- Test input validation
- Test authorization checks
- Test response rendering
- Mock models for isolation

### View Testing

**View rendering tests:**
- Test data interpolation
- Test conditional rendering
- Test output escaping
- Test partial inclusion
- Verify no logic leaks

See [../concepts/TESTING.md](../concepts/TESTING.md) for comprehensive testing strategy.
