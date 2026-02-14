# MVC Architecture

## Purpose

Separates application logic into three interconnected components for maintainability and clear responsibilities.

## Components

**Model** - Data access and business logic  
**View** - Presentation and user interface  
**Controller** - Request handling and coordination

## Model Layer

### Responsibility

Interact with database and encapsulate data operations.

### Core Model Base

All models extend base Model class providing:
- Database connection management
- CRUD operations (Create, Read, Update, Delete)
- Query building
- Prepared statement execution

### Model Structure

Each model represents a database table:
- Table name mapping
- Primary key definition
- Domain-specific query methods
- Data validation
- Business logic

### Key Models

**Student** - Student data and enrollment lookups  
**Course** - Course catalog and sections  
**SLO** - Student Learning Outcomes and outcomes hierarchy  
**Assessment** - Assessment records and grading  
**User** - User accounts and authentication  
**Role** - Role-based access control

## Controller Layer

### Responsibility

Handle HTTP requests, coordinate models and views, enforce security.

### Controller Pattern

Controllers:
- Receive HTTP requests
- Validate input data
- Check user permissions
- Execute business logic via models
- Prepare data for views
- Return responses (HTML or JSON)

### Request Flow

1. Route maps URL to controller method
2. Controller validates input
3. Controller checks authorization
4. Controller calls model methods
5. Controller prepares response data
6. Controller renders view or returns JSON

### Standard Actions

**index** - List/search resources  
**show** - Display single resource  
**create** - Create new resource  
**update** - Modify existing resource  
**delete** - Remove resource

## View Layer

### Responsibility

Present data to users, handle user interface.

### View Pattern

Views are templates that:
- Receive data from controllers
- Render HTML with data interpolation
- Use Bootstrap 5 for styling
- Include minimal presentation logic only
- Escape all user-generated output

### View Types

**Layout** - Application frame (header, footer, navigation)  
**Page** - Full page content  
**Partial** - Reusable fragments (forms, tables, cards)  
**Component** - UI widgets (buttons, alerts, modals)

## Data Flow

```
User Request → Router → Controller
                           ↓
                      Authorization Check
                           ↓
                      Input Validation
                           ↓
                        Model(s)
                           ↓
                       Database
                           ↓
                        Model(s)
                           ↓
                      Controller
                           ↓
                         View
                           ↓
                     HTTP Response → User
```

## Security Integration

### Input Validation

Controllers validate all input:
- Required field checks
- Type validation
- Length constraints
- Format validation
- Custom business rules

### Authorization

Controllers check permissions:
- User authentication required
- Role-based access control
- Context-scoped permissions (department, program, course)
- Fail securely (deny by default)

### Output Escaping

Views escape all output:
- HTML context - Prevent XSS
- JavaScript context - Prevent injection
- URL context - Prevent manipulation
- SQL context - Use prepared statements (in models)

### CSRF Protection

Forms include:
- CSRF token field
- Token validation on submission
- Token regeneration after use

## Separation of Concerns

### What Goes Where

**Models should:**
- Contain database queries
- Implement business rules
- Validate data integrity
- Return data structures

**Models should NOT:**
- Handle HTTP requests
- Render HTML
- Contain presentation logic
- Directly access user sessions

**Controllers should:**
- Validate input
- Check permissions
- Coordinate models
- Prepare view data

**Controllers should NOT:**
- Contain SQL queries
- Implement business logic
- Generate HTML directly
- Perform data transformations

**Views should:**
- Display data
- Provide user interface
- Include forms and interactions
- Escape output

**Views should NOT:**
- Query database
- Contain business logic
- Perform calculations
- Make authorization decisions

## Routing

Maps URLs to controller actions:

```
GET  /admin/students          → StudentController->index()
GET  /admin/students/:id      → StudentController->show(id)
POST /admin/students          → StudentController->create()
PUT  /admin/students/:id      → StudentController->update(id)
DELETE /admin/students/:id    → StudentController->delete(id)
```

RESTful patterns for resource management.

## Error Handling

### Exception Types

**ValidationException** - Invalid input  
**AuthenticationException** - Not logged in  
**ForbiddenException** - Insufficient permissions  
**NotFoundException** - Resource doesn't exist  
**DatabaseException** - Data operation failed

### Response Strategy

**HTML Requests:**
- Render error page
- Show validation errors on form
- Log details for debugging

**JSON Requests:**
- Return error object
- Include error code and message
- Use appropriate HTTP status code

## Session Management

Controllers manage user sessions:
- Authenticate users
- Track login state
- Store session data
- Regenerate IDs on privilege change
- Enforce timeouts

## MySQL 8.0+ Requirement

Models interact directly with MySQL:
- No database abstraction layer
- Use prepared statements for all queries
- Leverage MySQL-specific features
- InnoDB engine for transactions
- UTF8MB4 charset for international support

This is a hard requirement - see [PLUGIN.md](PLUGIN.md) for why.

## Design Rationale

**Why MVC:**
- Clear separation of concerns
- Easier testing (mock models, test controllers independently)
- Code reusability (same model used by multiple controllers)
- Maintainability (change one layer without affecting others)
- Team collaboration (frontend/backend developers work independently)

**Why No Template Engine:**
- PHP itself is a template language
- Avoids learning curve of additional syntax
- One less dependency
- Better performance
- Simpler debugging

**Why Direct MySQL:**
- Best performance (no abstraction overhead)
- Simpler codebase (no query builder complexity)
- Clear requirements for adopters
- Focus on features, not database compatibility

## Related Documentation

**Implementation Guide:**
- [MVC_GUIDE.md](../implementation/MVC_GUIDE.md) - Detailed implementation patterns and workflows

**System Context:**
- [ARCHITECTURE.md](ARCHITECTURE.md) - Overall system design
- [SCHEMA.md](SCHEMA.md) - Database schema
- [SECURITY.md](SECURITY.md) - Security requirements
- [TESTING.md](TESTING.md) - Testing strategy
