# Authentication & Authorization

## Authentication Strategies

Three concurrent authentication methods:

### 1. Dashboard Authentication
- Local username/password
- Bcrypt hashing (cost 12)
- Session-based
- Used by: Admins, faculty, coordinators

### 2. LTI Authentication
- **LTI 1.1**: OAuth 1.0 signature validation
- **LTI 1.3**: JWT/JWKS with RSA keys
- Auto-provision users from LMS
- Role mapping from launch parameters
- Used by: Faculty and students via LMS

### 3. SAML SSO
- SAML 2.0 federation
- Institution IdP integration
- Automatic user provisioning
- Supports MFA from IdP
- Used by: All users via institutional login

## Authentication Flow

**Unified Login Router:**
```
Request → Detect Auth Type → Route to Provider → Validate → Create Session
```

**Detection Logic:**
- LTI: POST to `/launch.php` with LTI parameters
- SAML: GET to `/auth/saml` or POST with SAMLResponse
- Dashboard: Default login form at `/login.php`

## Authorization (RBAC)

### Role Hierarchy

```
System Admin
  └── Institution Admin
      └── Assessment Coordinator
          └── Department Chair
              └── Faculty
                  └── Student
```

### Context-Aware Permissions

Permissions evaluated with context:

- **Global**: `manage_users`, `system_config`
- **Institution**: `manage_institution_outcomes`
- **Program**: `manage_program_outcomes`, `view_program_reports`
- **Course**: `manage_slos`, `assess_students`, `view_assessments`
- **Student**: `view_own_results`

**Permission Check Pattern:**
```
can_user_do($action, $context)
  → Check role
  → Check context ownership
  → Check explicit permissions
  → Return true/false
```

### Default Role Permissions

**System Admin:**
- Full system access
- User management
- System configuration

**Institution Admin:**
- Manage institution data
- Institutional outcomes
- View all reports

**Assessment Coordinator:**
- Manage outcomes alignment
- Generate reports
- Review assessments

**Department Chair:**
- View department reports
- Manage programs
- Assign faculty

**Faculty:**
- Manage own courses
- Create/edit SLOs
- Assess students
- View course reports

**Student:**
- View own assessment results
- View course outcomes

## Session Management

**Security Configuration:**
- HttpOnly cookies
- Secure flag (HTTPS only)
- SameSite=Strict
- Session timeout: 2 hours
- Regenerate ID on login
- Destroy on logout

## Multi-Auth Scenarios

**Same User, Different Methods:**
System links accounts by email or institution ID:

```
user@example.edu logs in via:
  - Dashboard (username/password)
  - LTI from Canvas
  - SAML SSO from institution

All link to same user record.
```

## Security Considerations

**Password Policy:**
- Minimum 8 characters
- Requires: uppercase, lowercase, number
- Bcrypt cost 12
- No password reset via email (admin reset only)

**OAuth/SAML Security:**
- Signature validation
- Nonce prevention (replay attacks)
- Clock skew tolerance: 5 minutes
- Certificate validation

**Session Security:**
- IP binding (optional)
- User agent validation
- Concurrent session limit
- Auto-logout on inactivity

## User Provisioning

**Automatic User Creation:**

**From LTI:**
- Email, name, role from launch parameters
- Institution mapped from LTI consumer
- Activated on first launch

**From SAML:**
- Attributes from IdP assertions
- Institution from entity ID
- Email as unique identifier

**Dashboard:**
- Manual creation by admin
- Email invitation with temporary password
- Force password change on first login

---

**Last Updated**: February 2026
