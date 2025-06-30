-- Security-related database tables for ATM System (SQLite compatible)

-- OTP Tokens for Two-Factor Authentication
CREATE TABLE IF NOT EXISTS otp_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    user_type TEXT NOT NULL CHECK (user_type IN ('admin', 'manager', 'user')),
    otp TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Login Attempts for Brute Force Protection
CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    user_type TEXT NOT NULL CHECK (user_type IN ('admin', 'manager', 'user')),
    ip_address TEXT NOT NULL,
    user_agent TEXT,
    success INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Enhanced Security Logs
CREATE TABLE IF NOT EXISTS security_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    user_type TEXT NOT NULL CHECK (user_type IN ('admin', 'manager', 'user')),
    event_type TEXT NOT NULL,
    details TEXT,
    severity TEXT DEFAULT 'info' CHECK (severity IN ('info', 'warning', 'error', 'critical')),
    ip_address TEXT NOT NULL,
    user_agent TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- User Sessions for Session Management
CREATE TABLE IF NOT EXISTS user_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    user_type TEXT NOT NULL CHECK (user_type IN ('admin', 'manager', 'user')),
    session_id TEXT NOT NULL UNIQUE,
    ip_address TEXT NOT NULL,
    user_agent TEXT,
    last_activity TEXT DEFAULT CURRENT_TIMESTAMP,
    expires_at TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- API Tokens for JWT Authentication
CREATE TABLE IF NOT EXISTS api_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    user_type TEXT NOT NULL CHECK (user_type IN ('admin', 'manager', 'user')),
    token_hash TEXT NOT NULL,
    token_type TEXT DEFAULT 'access' CHECK (token_type IN ('access', 'refresh')),
    expires_at TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    last_used TEXT
);

-- Security Settings for Users
CREATE TABLE IF NOT EXISTS user_security_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    user_type TEXT NOT NULL CHECK (user_type IN ('admin', 'manager', 'user')),
    two_factor_enabled INTEGER DEFAULT 0,
    two_factor_method TEXT DEFAULT 'email' CHECK (two_factor_method IN ('email', 'sms', 'app')),
    last_password_change TEXT DEFAULT CURRENT_TIMESTAMP,
    password_expires_at TEXT,
    failed_login_attempts INTEGER DEFAULT 0,
    account_locked INTEGER DEFAULT 0,
    lockout_until TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, user_type)
);

-- System Security Configuration
CREATE TABLE IF NOT EXISTS system_security_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_key TEXT NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT,
    updated_by INTEGER,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Insert default security configuration
INSERT OR IGNORE INTO system_security_config (config_key, config_value, description) VALUES
('session_timeout', '1800', 'Session timeout in seconds (30 minutes)'),
('max_login_attempts', '5', 'Maximum failed login attempts before lockout'),
('lockout_duration', '900', 'Account lockout duration in seconds (15 minutes)'),
('password_min_length', '8', 'Minimum password length'),
('require_special_chars', 'true', 'Require special characters in passwords'),
('two_factor_required', 'false', 'Require 2FA for all users'),
('password_expiry_days', '90', 'Password expiry in days'),
('log_retention_days', '365', 'How long to keep security logs');

-- Add security-related columns to existing tables

-- Add 2FA settings to admin table
ALTER TABLE admin ADD COLUMN two_factor_enabled INTEGER DEFAULT 0;
ALTER TABLE admin ADD COLUMN two_factor_method TEXT DEFAULT 'email' CHECK (two_factor_method IN ('email', 'sms', 'app'));

-- Add 2FA settings to managers table
ALTER TABLE managers ADD COLUMN two_factor_enabled INTEGER DEFAULT 0;
ALTER TABLE managers ADD COLUMN two_factor_method TEXT DEFAULT 'email' CHECK (two_factor_method IN ('email', 'sms', 'app'));

-- Add 2FA settings to users table
ALTER TABLE users ADD COLUMN two_factor_enabled INTEGER DEFAULT 0;
ALTER TABLE users ADD COLUMN two_factor_method TEXT DEFAULT 'email' CHECK (two_factor_method IN ('email', 'sms', 'app'));

-- Add security-related columns to activity_logs table
ALTER TABLE activity_logs ADD COLUMN severity TEXT DEFAULT 'info' CHECK (severity IN ('info', 'warning', 'error', 'critical'));
ALTER TABLE activity_logs ADD COLUMN session_id TEXT;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_otp_user ON otp_tokens(user_id, user_type);
CREATE INDEX IF NOT EXISTS idx_otp_expires ON otp_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_login_username ON login_attempts(username, user_type);
CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip_address);
CREATE INDEX IF NOT EXISTS idx_login_time ON login_attempts(created_at);
CREATE INDEX IF NOT EXISTS idx_security_user ON security_logs(user_id, user_type);
CREATE INDEX IF NOT EXISTS idx_security_event ON security_logs(event_type);
CREATE INDEX IF NOT EXISTS idx_security_severity ON security_logs(severity);
CREATE INDEX IF NOT EXISTS idx_security_time ON security_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_session_user ON user_sessions(user_id, user_type);
CREATE INDEX IF NOT EXISTS idx_session_id ON user_sessions(session_id);
CREATE INDEX IF NOT EXISTS idx_session_expires ON user_sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_token_user ON api_tokens(user_id, user_type);
CREATE INDEX IF NOT EXISTS idx_token_hash ON api_tokens(token_hash);
CREATE INDEX IF NOT EXISTS idx_token_expires ON api_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_security_settings_user ON user_security_settings(user_id, user_type);
CREATE INDEX IF NOT EXISTS idx_config_key ON system_security_config(config_key);
CREATE INDEX IF NOT EXISTS idx_activity_severity ON activity_logs(severity);
CREATE INDEX IF NOT EXISTS idx_activity_session ON activity_logs(session_id);
CREATE INDEX IF NOT EXISTS idx_admin_2fa ON admin(two_factor_enabled);
CREATE INDEX IF NOT EXISTS idx_manager_2fa ON managers(two_factor_enabled);
CREATE INDEX IF NOT EXISTS idx_user_2fa ON users(two_factor_enabled); 