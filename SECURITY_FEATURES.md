# 🔐 ATM System Security Features

This document outlines the comprehensive security features implemented in the ATM System to ensure secure banking operations.

## 🛡️ Security Features Overview

### 1. Two-Factor Authentication (2FA)
- **Email-based OTP verification** for enhanced login security
- **Configurable 2FA settings** per user account
- **Automatic OTP generation** and email delivery
- **OTP expiration** after 5 minutes for security
- **Resend functionality** for failed delivery attempts

**Files:**
- `verify_2fa.php` - 2FA verification page
- `includes/security.php` - 2FA functions
- `user/security_settings.php` - User 2FA management

### 2. Role-Based Access Control (RBAC)
- **Three user roles**: Admin, Manager, User
- **Granular permissions** for each role
- **Permission-based access** to system features
- **Dynamic permission checking** throughout the application

**Permission Matrix:**
```
Admin:
- View all users and transactions
- Create/delete users and managers
- Access security logs and analytics
- Generate reports
- System settings management

Manager:
- View managed users
- Create user accounts
- Approve account requests
- View transactions and activity logs
- Generate reports

User:
- View own profile and transactions
- Perform banking operations
- Change password and security settings
- Enable/disable 2FA
```

### 3. Audit Logs & Activity Tracking
- **Comprehensive logging** of all user activities
- **Security event tracking** with severity levels
- **IP address and user agent** logging
- **Real-time log analytics** and visualization
- **Export functionality** (CSV/PDF)

**Log Categories:**
- Login/logout events
- Password changes
- 2FA activations
- Transaction activities
- Security alerts
- System access patterns

### 4. Data Encryption
- **Password hashing** using bcrypt
- **AES-256-CBC encryption** for sensitive data
- **Secure key management** with environment variables
- **Encrypted session data** storage
- **JWT token encryption** for API access

### 5. Auto Logout & Session Management
- **Configurable session timeout** (default: 30 minutes)
- **Automatic logout** on inactivity
- **Secure session handling** with HTTP-only cookies
- **Session fixation protection**
- **Concurrent session management**

## 📊 Dashboard & Analytics

### 6. Real-Time Dashboard
- **Live security metrics** and system status
- **Real-time alerts** and notifications
- **Interactive charts** for data visualization
- **System health monitoring**
- **Performance indicators**

**Dashboard Features:**
- Security events counter
- Failed login attempts tracking
- Active sessions monitoring
- Suspicious activity detection
- System uptime and health status

### 7. Log Analytics & Visualization
- **Security event charts** by severity and type
- **Login pattern analysis** and visualization
- **Geographic access tracking** (IP-based)
- **Time-based trend analysis**
- **Anomaly detection** algorithms

### 8. Report Generator
- **CSV export** for data analysis
- **PDF report generation** for documentation
- **Customizable date ranges** and filters
- **Automated report scheduling**
- **Email delivery** of reports

## 🔧 Implementation Details

### Database Schema
The security system uses several new database tables:

```sql
-- OTP Tokens for 2FA
otp_tokens (id, user_id, user_type, otp, expires_at, created_at)

-- Login Attempts for brute force protection
login_attempts (id, username, user_type, ip_address, user_agent, success, created_at)

-- Enhanced Security Logs
security_logs (id, user_id, user_type, event_type, details, severity, ip_address, user_agent, created_at)

-- User Sessions for session management
user_sessions (id, user_id, user_type, session_id, ip_address, user_agent, last_activity, expires_at, created_at)

-- API Tokens for JWT authentication
api_tokens (id, user_id, user_type, token_hash, token_type, expires_at, created_at, last_used)

-- Security Settings for users
user_security_settings (id, user_id, user_type, two_factor_enabled, two_factor_method, last_password_change, password_expires_at, failed_login_attempts, account_locked, lockout_until, created_at, updated_at)

-- System Security Configuration
system_security_config (id, config_key, config_value, description, updated_by, updated_at)
```

### Configuration Files

#### `includes/security.php`
Main security configuration and functions:
- Security constants and settings
- 2FA implementation
- Password validation
- Encryption functions
- Session management
- Audit logging
- Permission checking

#### `includes/config.php`
Updated with security enhancements:
- Secure session configuration
- Error handling
- Timezone settings
- Autoloading

### API Endpoints

#### `api/security_stats.php`
Real-time security statistics API:
- Recent security events
- Failed login attempts
- Active sessions
- System health metrics
- Weekly trends
- User activity by role

## 🚀 Setup Instructions

### 1. Database Setup
Run the security setup script:
```bash
php setup_security.php
```

### 2. Configuration
Update `includes/security.php` with your settings:

```php
// Generate secure keys
define('ENCRYPTION_KEY', 'your-32-character-encryption-key-here');
define('JWT_SECRET', 'your-jwt-secret-key-here');

// Email configuration for 2FA
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@atm-system.com');
define('SMTP_FROM_NAME', 'ATM System');
```

### 3. Security Settings
Configure security policies in the database:
- Session timeout duration
- Maximum login attempts
- Lockout duration
- Password requirements
- 2FA requirements

## 🔍 Security Monitoring

### Real-Time Monitoring
- **Live dashboard** with security metrics
- **Alert system** for suspicious activities
- **Automated notifications** for critical events
- **Performance monitoring** and health checks

### Log Analysis
- **Pattern recognition** for unusual activities
- **Geographic analysis** of access patterns
- **Time-based analysis** for peak usage
- **Anomaly detection** algorithms

### Reporting
- **Daily security reports** with key metrics
- **Weekly trend analysis** and insights
- **Monthly compliance reports**
- **Custom report generation** on demand

## 🛡️ Security Best Practices

### Password Security
- **Minimum 8 characters** required
- **Complexity requirements**: uppercase, lowercase, numbers, special characters
- **Regular password changes** (90-day expiry)
- **Secure password reset** process

### Session Security
- **HTTP-only cookies** for session management
- **Secure flag** for HTTPS environments
- **SameSite attribute** for CSRF protection
- **Automatic session cleanup** for expired sessions

### Data Protection
- **Input validation** and sanitization
- **SQL injection prevention** with prepared statements
- **XSS protection** with output encoding
- **CSRF token validation** for all forms

### Access Control
- **Principle of least privilege** implementation
- **Role-based permissions** throughout the system
- **Session-based authentication** validation
- **IP-based access restrictions** (configurable)

## 📈 Performance & Scalability

### Optimization Features
- **Database indexing** for security tables
- **Caching mechanisms** for frequently accessed data
- **Efficient query optimization** for large datasets
- **Background processing** for report generation

### Scalability Considerations
- **Modular security architecture** for easy expansion
- **Configurable security policies** without code changes
- **API-based integration** for external systems
- **Multi-tenant support** capabilities

## 🔧 Maintenance & Updates

### Regular Maintenance Tasks
- **Log rotation** and archival
- **Database cleanup** of expired data
- **Security policy updates** and reviews
- **Performance monitoring** and optimization

### Update Procedures
- **Backup procedures** before updates
- **Testing environment** for security updates
- **Rollback procedures** for failed updates
- **Documentation updates** for changes

## 📞 Support & Troubleshooting

### Common Issues
1. **2FA email delivery problems** - Check SMTP configuration
2. **Session timeout issues** - Verify session configuration
3. **Database connection errors** - Check database permissions
4. **Performance issues** - Review database indexing

### Debugging Tools
- **Security log viewer** in admin dashboard
- **Real-time monitoring** tools
- **Error logging** and reporting
- **Performance profiling** capabilities

## 🔒 Compliance & Standards

### Security Standards Compliance
- **OWASP Top 10** security practices
- **PCI DSS** requirements for financial data
- **GDPR** compliance for data protection
- **ISO 27001** security management

### Audit Trail Requirements
- **Comprehensive logging** of all activities
- **Tamper-evident logs** with integrity checks
- **Retention policies** for log data
- **Access controls** for audit data

---

## 📝 Changelog

### Version 1.0.0 (Current)
- Initial implementation of security features
- Two-factor authentication system
- Role-based access control
- Comprehensive audit logging
- Real-time dashboard analytics
- Report generation capabilities

### Future Enhancements
- **Biometric authentication** support
- **Advanced threat detection** algorithms
- **Machine learning** for anomaly detection
- **Mobile app** security integration
- **Blockchain** for transaction verification

---

For technical support or security questions, please contact the development team or refer to the system documentation. 