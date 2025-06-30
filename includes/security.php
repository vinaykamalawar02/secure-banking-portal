<?php
// Security Configuration and Functions

// Security constants
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 8);
define('REQUIRE_SPECIAL_CHARS', true);
define('ENCRYPTION_KEY', 'dab1a226fde8c82887ef2cf832fa07b6410282b21c2893fadc4ef6dd36c9cf0d');
define('JWT_SECRET', '50aa338381e9a4d8c7b4b4ab6b7d279d2401763e503769c5af3bc7137f8b19e5');

// Two-Factor Authentication settings
define('OTP_EXPIRY', 300); // 5 minutes
define('OTP_LENGTH', 6);

// Email configuration for 2FA
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@atm-system.com');
define('SMTP_FROM_NAME', 'ATM System');

/**
 * Enhanced session management with auto-logout
 */
function init_secure_session() {
    // Only set session parameters if session hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        @ini_set('session.cookie_httponly', 1);
        @ini_set('session.cookie_secure', 1);
        @ini_set('session.use_strict_mode', 1);
        @ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
    }
    
    // Check for session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        log_activity($_SESSION['user_id'] ?? 0, $_SESSION['user_role'] ?? 'unknown', 'Session timeout - auto logout');
        logout();
        header('Location: /index.php?error=session_expired');
        exit();
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Enhanced login with 2FA support
 */
function secure_login($user_id, $username, $role, $require_2fa = false) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    if ($require_2fa) {
        $_SESSION['requires_2fa'] = true;
        $_SESSION['temp_user_id'] = $user_id;
        $_SESSION['temp_username'] = $username;
        $_SESSION['temp_role'] = $role;
        return false; // Login not complete until 2FA is verified
    }
    
    // Log successful login
    log_activity($user_id, $role, 'Login successful');
    return true;
}

/**
 * Generate OTP for 2FA
 */
function generate_otp() {
    return str_pad(rand(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
}

/**
 * Send OTP via email
 */
function send_otp_email($email, $otp, $username) {
    $subject = "ATM System - Two-Factor Authentication Code";
    $message = "
    <html>
    <head>
        <title>2FA Code</title>
    </head>
    <body>
        <h2>ATM System Security</h2>
        <p>Hello $username,</p>
        <p>Your two-factor authentication code is: <strong>$otp</strong></p>
        <p>This code will expire in " . (OTP_EXPIRY / 60) . " minutes.</p>
        <p>If you didn't request this code, please contact support immediately.</p>
        <br>
        <p>Best regards,<br>ATM System Team</p>
    </body>
    </html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

/**
 * Store OTP in database
 */
function store_otp($user_id, $user_type, $otp) {
    global $conn;
    
    // Delete any existing OTP for this user
    $stmt = $conn->prepare("DELETE FROM otp_tokens WHERE user_id = ? AND user_type = ?");
    $stmt->execute([$user_id, $user_type]);
    
    // Store new OTP
    $stmt = $conn->prepare("INSERT INTO otp_tokens (user_id, user_type, otp, expires_at) VALUES (?, ?, ?, ?)");
    $expires_at = date('Y-m-d H:i:s', time() + OTP_EXPIRY);
    return $stmt->execute([$user_id, $user_type, password_hash($otp, PASSWORD_DEFAULT), $expires_at]);
}

/**
 * Verify OTP
 */
function verify_otp($user_id, $user_type, $otp) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT otp, expires_at FROM otp_tokens WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id, $user_type]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return false;
    }
    
    // Check if OTP is expired
    if (strtotime($result['expires_at']) < time()) {
        // Delete expired OTP
        $stmt = $conn->prepare("DELETE FROM otp_tokens WHERE user_id = ? AND user_type = ?");
        $stmt->execute([$user_id, $user_type]);
        return false;
    }
    
    // Verify OTP
    if (password_verify($otp, $result['otp'])) {
        // Delete used OTP
        $stmt = $conn->prepare("DELETE FROM otp_tokens WHERE user_id = ? AND user_type = ?");
        $stmt->execute([$user_id, $user_type]);
        return true;
    }
    
    return false;
}

/**
 * Enhanced password validation
 */
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    }
    
    if (REQUIRE_SPECIAL_CHARS && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return $errors;
}

/**
 * Check login attempts and implement lockout
 */
function check_login_attempts($username, $user_type) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts, MAX(created_at) as last_attempt FROM login_attempts WHERE username = ? AND user_type = ? AND created_at > datetime('now', '-' || ? || ' seconds')");
    $stmt->execute([$username, $user_type, LOCKOUT_DURATION]);
    $result = $stmt->fetch();
    
    if ($result['attempts'] >= MAX_LOGIN_ATTEMPTS) {
        return false; // Account is locked
    }
    
    return true;
}

/**
 * Record failed login attempt
 */
function record_login_attempt($username, $user_type, $ip_address) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO login_attempts (username, user_type, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $user_type, $ip_address, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown']);
}

/**
 * Clear login attempts after successful login
 */
function clear_login_attempts($username, $user_type) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ? AND user_type = ?");
    $stmt->execute([$username, $user_type]);
}

/**
 * Encrypt sensitive data
 */
function encrypt_data($data) {
    $method = "AES-256-CBC";
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = openssl_random_pseudo_bytes(16);
    
    $encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt sensitive data
 */
function decrypt_data($encrypted_data) {
    $method = "AES-256-CBC";
    $key = hash('sha256', ENCRYPTION_KEY, true);
    
    $data = base64_decode($encrypted_data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    return openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Enhanced audit logging
 */
function log_security_event($user_id, $user_type, $event_type, $details = '', $severity = 'info') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO security_logs (user_id, user_type, event_type, details, severity, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        $user_type,
        $event_type,
        $details,
        $severity,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

/**
 * Check if user has permission for specific action
 */
function has_permission($user_role, $action) {
    $permissions = [
        'admin' => [
            'view_all_users', 'create_user', 'delete_user', 'view_all_transactions',
            'manage_managers', 'view_security_logs', 'generate_reports', 'system_settings'
        ],
        'manager' => [
            'view_managed_users', 'create_user', 'approve_accounts', 'view_transactions',
            'view_activity_logs', 'generate_reports'
        ],
        'user' => [
            'view_own_profile', 'update_own_profile', 'view_own_transactions',
            'perform_transactions', 'change_password'
        ]
    ];
    
    return in_array($action, $permissions[$user_role] ?? []);
}

/**
 * Generate JWT token for API access
 */
function generate_jwt_token($user_id, $user_role) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user_id,
        'user_role' => $user_role,
        'iat' => time(),
        'exp' => time() + 3600 // 1 hour expiry
    ]);
    
    $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, JWT_SECRET, true);
    $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64_header . "." . $base64_payload . "." . $base64_signature;
}

/**
 * Verify JWT token
 */
function verify_jwt_token($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    
    $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]));
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[2]));
    
    $expected_signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], JWT_SECRET, true);
    
    if (!hash_equals($signature, $expected_signature)) {
        return false;
    }
    
    $payload_data = json_decode($payload, true);
    if ($payload_data['exp'] < time()) {
        return false;
    }
    
    return $payload_data;
}

/**
 * Sanitize and validate input with enhanced security
 */
function secure_input($data, $type = 'string') {
    $data = trim($data);
    $data = stripslashes($data);
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'string':
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Generate secure random string
 */
function generate_secure_token($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check for suspicious activity
 */
function detect_suspicious_activity($user_id, $user_type) {
    global $conn;
    
    // Check for multiple failed login attempts
    $stmt = $conn->prepare("SELECT COUNT(*) as failed_attempts FROM login_attempts WHERE username = (SELECT username FROM users WHERE id = ?) AND created_at > datetime('now', '-1 hour')");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    if ($result['failed_attempts'] > 10) {
        log_security_event($user_id, $user_type, 'suspicious_activity', 'Multiple failed login attempts', 'warning');
        return true;
    }
    
    // Check for unusual transaction patterns
    $stmt = $conn->prepare("SELECT COUNT(*) as recent_transactions FROM transactions WHERE user_id = ? AND created_at > datetime('now', '-1 hour')");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    if ($result['recent_transactions'] > 50) {
        log_security_event($user_id, $user_type, 'suspicious_activity', 'Unusual transaction frequency', 'warning');
        return true;
    }
    
    return false;
}
