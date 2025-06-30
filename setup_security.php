<?php
require_once 'includes/config.php';

echo "<h1>ATM System Security Setup</h1>";
echo "<p>Initializing security features...</p>";

try {
    // Read and execute security tables SQL
    $security_sql = file_get_contents('security_tables.sql');
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $security_sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $conn->exec($statement);
                echo "<p style='color: green;'>✓ " . substr($statement, 0, 50) . "...</p>";
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>⚠ " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Update existing users to have 2FA disabled by default
    $conn->exec("UPDATE users SET two_factor_enabled = 0 WHERE two_factor_enabled IS NULL");
    $conn->exec("UPDATE managers SET two_factor_enabled = 0 WHERE two_factor_enabled IS NULL");
    $conn->exec("UPDATE admin SET two_factor_enabled = 0 WHERE two_factor_enabled IS NULL");
    
    echo "<p style='color: green;'>✓ Updated existing users with 2FA settings</p>";
    
    // Insert sample security events for testing
    $sample_events = [
        ['admin', 'system_startup', 'Security system initialized', 'info'],
        ['admin', 'user_login', 'Admin user logged in', 'info'],
        ['manager', 'user_created', 'New user account created', 'info'],
        ['user', 'password_changed', 'Password updated successfully', 'info']
    ];
    
    foreach ($sample_events as $event) {
        $stmt = $conn->prepare("INSERT INTO security_logs (user_type, event_type, details, severity, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$event[0], $event[1], $event[2], $event[3], '127.0.0.1', 'Setup Script']);
    }
    
    echo "<p style='color: green;'>✓ Inserted sample security events</p>";
    
    // Create security configuration
    $config_settings = [
        'session_timeout' => '1800',
        'max_login_attempts' => '5',
        'lockout_duration' => '900',
        'password_min_length' => '8',
        'require_special_chars' => 'true',
        'two_factor_required' => 'false',
        'password_expiry_days' => '90',
        'log_retention_days' => '365'
    ];
    
    foreach ($config_settings as $key => $value) {
        $stmt = $conn->prepare("INSERT OR REPLACE INTO system_security_config (config_key, config_value, description) VALUES (?, ?, ?)");
        $stmt->execute([$key, $value, "Configuration for $key"]);
    }
    
    echo "<p style='color: green;'>✓ Security configuration initialized</p>";
    
    echo "<h2>Security Setup Complete!</h2>";
    echo "<p>The following security features have been enabled:</p>";
    echo "<ul>";
    echo "<li>Two-Factor Authentication (2FA) - Email-based OTP verification</li>";
    echo "<li>Enhanced Session Management - Auto-logout on inactivity</li>";
    echo "<li>Brute Force Protection - Account lockout after failed attempts</li>";
    echo "<li>Audit Logging - Comprehensive activity tracking</li>";
    echo "<li>Data Encryption - Secure storage of sensitive information</li>";
    echo "<li>Role-Based Access Control - Granular permissions</li>";
    echo "<li>Security Analytics - Real-time monitoring and reporting</li>";
    echo "</ul>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Configure email settings in <code>includes/security.php</code> for 2FA</li>";
    echo "<li>Update encryption keys in <code>includes/security.php</code></li>";
    echo "<li>Test the security features with different user roles</li>";
    echo "<li>Review and customize security policies as needed</li>";
    echo "</ol>";
    
    echo "<p><strong>Important:</strong> Please update the following configuration values in <code>includes/security.php</code>:</p>";
    echo "<ul>";
    echo "<li><code>ENCRYPTION_KEY</code> - Generate a secure 32-character key</li>";
    echo "<li><code>JWT_SECRET</code> - Generate a secure JWT secret</li>";
    echo "<li><code>SMTP_*</code> settings - Configure your email server</li>";
    echo "</ul>";
    
    echo "<p><a href='index.php'>← Back to Login</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Setup failed: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}

h1, h2, h3 {
    color: #333;
}

code {
    background: #f4f4f4;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
}

ul, ol {
    margin-left: 20px;
}

a {
    color: #007bff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style> 