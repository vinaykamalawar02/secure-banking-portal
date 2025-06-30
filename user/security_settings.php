<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/security.php';

// Ensure user is logged in
if (!is_logged_in() || $_SESSION['user_role'] !== 'user') {
    header('Location: ../unauthorized.php');
    exit();
}

// Initialize secure session
init_secure_session();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'enable_2fa':
            // Enable 2FA for user
            $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1 WHERE id = ?");
            if ($stmt->execute([$_SESSION['user_id']])) {
                $success = 'Two-factor authentication has been enabled.';
                log_security_event($_SESSION['user_id'], 'user', '2FA enabled');
            } else {
                $error = 'Failed to enable two-factor authentication.';
            }
            break;
            
        case 'disable_2fa':
            // Disable 2FA for user
            $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0 WHERE id = ?");
            if ($stmt->execute([$_SESSION['user_id']])) {
                $success = 'Two-factor authentication has been disabled.';
                log_security_event($_SESSION['user_id'], 'user', '2FA disabled');
            } else {
                $error = 'Failed to disable two-factor authentication.';
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $error = 'Current password is incorrect.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } else {
                // Validate new password strength
                $password_errors = validate_password($new_password);
                if (!empty($password_errors)) {
                    $error = 'Password requirements not met: ' . implode(', ', $password_errors);
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                        $success = 'Password has been changed successfully.';
                        log_security_event($_SESSION['user_id'], 'user', 'Password changed');
                    } else {
                        $error = 'Failed to change password.';
                    }
                }
            }
            break;
    }
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get recent security events
$stmt = $conn->prepare("
    SELECT event_type, severity, details, created_at
    FROM security_logs 
    WHERE user_id = ? AND user_type = 'user'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_security_events = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - ATM System</title>
    
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Custom CSS -->
    <link href="../assets/css/user.css" rel="stylesheet" />
    
    <style>
        .security-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .security-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .security-section h3 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
        }
        
        .security-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .security-status.enabled {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .security-status.disabled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .password-form {
            display: grid;
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .form-group label {
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .security-events {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .event-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .event-icon.info { background: #17a2b8; color: white; }
        .event-icon.warning { background: #ffc107; color: black; }
        .event-icon.error { background: #dc3545; color: white; }
        .event-icon.critical { background: #6f42c1; color: white; }
        
        .event-content {
            flex: 1;
        }
        
        .event-title {
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .event-time {
            font-size: 12px;
            color: #666;
        }
        
        .password-requirements {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .password-requirements h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .requirement i {
            width: 16px;
        }
        
        .requirement.valid {
            color: #28a745;
        }
        
        .requirement.invalid {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="security-container">
        <h1><i class="fas fa-shield-alt"></i> Security Settings</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <!-- Two-Factor Authentication -->
        <div class="security-section">
            <h3>
                <i class="fas fa-mobile-alt"></i>
                Two-Factor Authentication (2FA)
            </h3>
            
            <div class="security-status <?= $user['two_factor_enabled'] ? 'enabled' : 'disabled' ?>">
                <i class="fas fa-<?= $user['two_factor_enabled'] ? 'check-circle' : 'times-circle' ?>"></i>
                <span>
                    Two-factor authentication is currently 
                    <strong><?= $user['two_factor_enabled'] ? 'enabled' : 'disabled' ?></strong>
                </span>
            </div>
            
            <p>
                Two-factor authentication adds an extra layer of security to your account. 
                When enabled, you'll need to enter a verification code sent to your email 
                in addition to your password when logging in.
            </p>
            
            <?php if ($user['two_factor_enabled']): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="disable_2fa">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.')">
                        <i class="fas fa-times"></i> Disable 2FA
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="enable_2fa">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Enable 2FA
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- Change Password -->
        <div class="security-section">
            <h3>
                <i class="fas fa-key"></i>
                Change Password
            </h3>
            
            <form method="POST" class="password-form">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <div class="requirement" id="req-length">
                        <i class="fas fa-circle"></i>
                        At least 8 characters long
                    </div>
                    <div class="requirement" id="req-uppercase">
                        <i class="fas fa-circle"></i>
                        Contains uppercase letter
                    </div>
                    <div class="requirement" id="req-lowercase">
                        <i class="fas fa-circle"></i>
                        Contains lowercase letter
                    </div>
                    <div class="requirement" id="req-number">
                        <i class="fas fa-circle"></i>
                        Contains number
                    </div>
                    <div class="requirement" id="req-special">
                        <i class="fas fa-circle"></i>
                        Contains special character
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Change Password
                </button>
            </form>
        </div>
        
        <!-- Recent Security Events -->
        <div class="security-section">
            <h3>
                <i class="fas fa-history"></i>
                Recent Security Events
            </h3>
            
            <div class="security-events">
                <?php if (empty($recent_security_events)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        <i class="fas fa-info-circle"></i> No security events found.
                    </p>
                <?php else: ?>
                    <?php foreach ($recent_security_events as $event): ?>
                        <div class="event-item">
                            <div class="event-icon <?= $event['severity'] ?>">
                                <i class="fas fa-<?= $event['severity'] === 'critical' ? 'exclamation-triangle' : ($event['severity'] === 'error' ? 'times-circle' : ($event['severity'] === 'warning' ? 'exclamation' : 'info-circle')) ?>"></i>
                            </div>
                            <div class="event-content">
                                <div class="event-title">
                                    <?= ucfirst(str_replace('_', ' ', $event['event_type'])) ?>
                                </div>
                                <?php if ($event['details']): ?>
                                    <div class="event-details"><?= htmlspecialchars($event['details']) ?></div>
                                <?php endif; ?>
                                <div class="event-time"><?= format_time_ago($event['created_at']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Back to Dashboard -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <script>
        // Password validation
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            
            // Check requirements
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
            };
            
            // Update requirement indicators
            Object.keys(requirements).forEach(req => {
                const element = document.getElementById('req-' + req);
                const icon = element.querySelector('i');
                
                if (requirements[req]) {
                    element.className = 'requirement valid';
                    icon.className = 'fas fa-check';
                } else {
                    element.className = 'requirement invalid';
                    icon.className = 'fas fa-times';
                }
            });
        });
        
        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html> 