# Security Architecture

## Security Philosophy

Defense-in-depth approach with FERPA compliance for educational assessment data.

## Security Layers

```
Transport (HTTPS/TLS)
    ↓
Authentication (3 methods)
    ↓
Authorization (RBAC + Context)
    ↓
Application (Validation, Escaping, CSRF)
    ↓
Data (Prepared Statements, Encryption, Audit)
```

## Threat Model

**Assets to Protect:**
- Student assessment data (FERPA protected)
- User credentials
- Institutional data
- System integrity

**Threat Actors:**
- External attackers
- Malicious users
- Compromised accounts
- Insider threats

**Attack Vectors:**
- SQL injection
- XSS (Cross-Site Scripting)
- CSRF (Cross-Site Request Forgery)
- Session hijacking
- Brute force attacks
- Data exfiltration

## Security Controls

### 1. Input Validation

**All User Input:**
- Type checking
- Length limits
- Whitelist validation
- Reject suspicious patterns

### 2. SQL Injection Prevention

**Prepared Statements:**
- All database queries use parameterized statements
- No string concatenation in SQL
- Input bound separately from query structure

### 3. XSS Prevention

**Output Escaping:**
- HTML escape all user-generated content
- Context-aware escaping (HTML, JS, URL)
- Content Security Policy headers

### 4. CSRF Protection

**Token Validation:**
- Unique token per session
- Token in all forms
- Verify token on POST/PUT/DELETE
- Reject requests without valid token

### 5. Authentication Security

**Password Security:**
- Bcrypt hashing, cost 12
- Salt automatically handled by bcrypt
- No password length maximum
- Rate limiting on login attempts

**OAuth/SAML Security:**
- Signature validation (LTI)
- Certificate verification (SAML)
- Nonce checking (replay prevention)
- Clock skew tolerance: 5 minutes

### 6. Session Security

**Configuration:**
- HttpOnly cookies (no JS access)
- Secure flag (HTTPS only)
- SameSite=Strict
- 2-hour timeout
- Regenerate ID on privilege change

### 7. Authorization Security

**Access Control:**
- Every action checks permissions
- Context-aware authorization
- Deny by default
- Log authorization failures

### 8. Data Security

**Encryption:**
- Secrets encrypted at rest (AES-256)
- Passwords hashed with bcrypt
- API keys encrypted in database
- TLS 1.2+ for transport

**Audit Logging:**
- All authentication events
- Authorization failures
- Data modifications
- Admin actions
- Include: user, timestamp, IP, action

### 9. HTTP Security Headers

**Required Headers:**
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy: default-src 'self'`
- `Strict-Transport-Security: max-age=31536000`

### 10. File Upload Security

**Validation:**
- Whitelist file types
- Size limits
- Virus scanning (if available)
- Store outside web root
- Serve with Content-Disposition: attachment

## FERPA Compliance

**Requirements:**

1. **Access Control**: Only authorized users access student records
2. **Audit Trail**: Log all access to student data
3. **Consent**: Student consent for data sharing (if applicable)
4. **Data Minimization**: Collect only necessary data
5. **Breach Notification**: Incident response plan
6. **Data Retention**: Documented retention policies

**Implementation:**
- Role-based access control
- Audit logging on all student data access
- Encrypted storage of sensitive data
- Secure data disposal procedures

## Security Best Practices

### Code Level

**Do:**
- Validate all input
- Escape all output
- Use prepared statements
- Check permissions on every action
- Log security events
- Handle errors gracefully
- Use HTTPS everywhere

**Don't:**
- Trust user input
- Store passwords in plain text
- Use string concatenation in SQL
- Display detailed error messages to users
- Log sensitive data
- Use deprecated cryptography

### Deployment

**Pre-Production:**
- Penetration testing
- Security code review
- Dependency vulnerability scan
- Configuration audit

**Production:**
- HTTPS with valid certificate
- Disable debug mode
- Restrict file permissions
- Regular security updates
- Monitor logs for anomalies
- Backup regularly

## Incident Response

**Process:**

1. **Detection**: Monitor logs, user reports
2. **Containment**: Isolate affected systems
3. **Investigation**: Determine scope and cause
4. **Remediation**: Fix vulnerabilities
5. **Recovery**: Restore normal operations
6. **Post-Mortem**: Document and improve

**Contacts:**
- Security team
- System administrators
- Institution's IT security office
- FERPA compliance officer

## Security Checklist

### Pre-Deployment

- [ ] All passwords hashed with bcrypt
- [ ] Prepared statements for all queries
- [ ] Output escaping on all user data
- [ ] CSRF tokens on all forms
- [ ] HTTPS configured with valid certificate
- [ ] Security headers configured
- [ ] Debug mode disabled
- [ ] Error logging enabled (not displayed)
- [ ] File permissions restrictive
- [ ] Database credentials secured
- [ ] Audit logging enabled

### Ongoing

- [ ] Regular security updates
- [ ] Monitor audit logs
- [ ] Review access permissions
- [ ] Test backup restoration
- [ ] Incident response plan updated
- [ ] Security training for admins
- [ ] Third-party code reviews

---

**Last Updated**: February 2026
