# Authentication & Authorization

## Authentication Strategies

Three concurrent authentication methods:

### 1. Dashboard Authentication
- Local username/password
- Argon2id hashing (64MB memory, 4 iterations, 2 threads)
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

### Emergency Admin (Break Glass Account)

**Configuration-Based Emergency Access**: Defined in `config.yaml`, bypasses database entirely for recovery scenarios.

**Purpose**: 
- Recover from database user lockouts
- Emergency access when normal authentication fails
- No database dependency

**Configuration** (`config.yaml`):
```yaml
emergency_admin:
  enabled: true                      # Set to false to disable
  username: sloadmin@breakglass.idx  # Email format required; change after setup!
  password: slopass                  # Change immediately after setup!
```

**Security Considerations**:
- Password stored in **plain text** in config file
- Bypasses all database checks and normal authorization
- All logins logged with "Emergency admin login used" warning
- Session marked with `is_emergency_admin` flag
- **Change default credentials immediately** after installation
- Disable by setting `enabled: false` when not needed

**Authentication Flow**:
1. User submits login form
2. System checks emergency admin credentials FIRST
3. If match: Instant access with special session
4. If no match: Normal database authentication proceeds

**Session Attributes**:
- `user_id: 0` (special emergency admin ID)
- `user_name: "Emergency Admin"`
- `is_emergency_admin: true`
- Full system access (equivalent to System Admin role)

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
- Argon2id (64MB memory cost, 4 time cost, 2 threads)
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
