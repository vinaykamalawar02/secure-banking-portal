<?php
require_once 'includes/config.php';
require_once 'includes/security.php';

// Initialize secure session
init_secure_session();

$error = '';
$success = '';

// Check if user is in 2FA process
if (!isset($_SESSION['requires_2fa']) || !$_SESSION['requires_2fa']) {
    header('Location: index.php');
    exit();
}

// Handle 2FA verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        $error = 'Please enter the verification code.';
    } else {
        // Verify OTP
        if (verify_otp($_SESSION['temp_user_id'], $_SESSION['temp_role'], $otp)) {
            // Complete login process
            $_SESSION['user_id'] = $_SESSION['temp_user_id'];
            $_SESSION['username'] = $_SESSION['temp_username'];
            $_SESSION['user_role'] = $_SESSION['temp_role'];
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Clear temporary 2FA data
            unset($_SESSION['requires_2fa']);
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_username']);
            unset($_SESSION['temp_role']);
            
            // Log successful 2FA verification
            log_security_event($_SESSION['user_id'], $_SESSION['user_role'], '2FA verification successful');
            
            // Redirect to appropriate dashboard
            switch ($_SESSION['user_role']) {
                case 'admin':
                    header('Location: admin/index.php');
                    break;
                case 'manager':
                    header('Location: manager/index.php');
                    break;
                case 'user':
                    header('Location: user/index.php');
                    break;
                default:
                    header('Location: index.php');
            }
            exit();
        } else {
            $error = 'Invalid verification code. Please try again.';
            log_security_event($_SESSION['temp_user_id'], $_SESSION['temp_role'], '2FA verification failed', 'Invalid OTP entered');
        }
    }
}

// Resend OTP functionality
if (isset($_GET['resend']) && $_GET['resend'] === 'true') {
    $otp = generate_otp();
    $user_email = '';
    
    // Get user email based on role
    switch ($_SESSION['temp_role']) {
        case 'admin':
            $stmt = $conn->prepare("SELECT email FROM admin WHERE id = ?");
            break;
        case 'manager':
            $stmt = $conn->prepare("SELECT email FROM managers WHERE id = ?");
            break;
        case 'user':
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            break;
    }
    
    if (isset($stmt)) {
        $stmt->execute([$_SESSION['temp_user_id']]);
        $user = $stmt->fetch();
        $user_email = $user['email'] ?? '';
        
        if ($user_email && store_otp($_SESSION['temp_user_id'], $_SESSION['temp_role'], $otp)) {
            if (send_otp_email($user_email, $otp, $_SESSION['temp_username'])) {
                $success = 'A new verification code has been sent to your email.';
                log_security_event($_SESSION['temp_user_id'], $_SESSION['temp_role'], '2FA code resent');
            } else {
                $error = 'Failed to send verification code. Please try again.';
            }
        } else {
            $error = 'Failed to generate verification code. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - ATM System</title>
    
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Custom CSS -->
    <link href="assets/css/login.css" rel="stylesheet" />
    
    <style>
        .two-factor-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .two-factor-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .two-factor-icon {
            font-size: 48px;
            color: #007bff;
            margin-bottom: 15px;
        }
        
        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .otp-input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 24px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .otp-input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .resend-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .resend-link a {
            color: #007bff;
            text-decoration: none;
        }
        
        .resend-link a:hover {
            text-decoration: underline;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: #6c757d;
            text-decoration: none;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="two-factor-container">
        <div class="two-factor-header">
            <div class="two-factor-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Two-Factor Authentication</h2>
            <p>Enter the verification code sent to your email</p>
        </div>

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

        <form method="POST" id="otp-form">
            <div class="form-group">
                <label for="otp" class="form-label">Verification Code</label>
                <div class="input-group">
                    <i class="fas fa-key input-icon"></i>
                    <input type="text" name="otp" id="otp" required placeholder="Enter 6-digit code" class="form-input" maxlength="6" pattern="[0-9]{6}" />
                </div>
            </div>

            <button type="submit" class="submit-button">
                <i class="fas fa-check"></i> Verify Code
            </button>
        </form>

        <div class="resend-link">
            <a href="?resend=true">
                <i class="fas fa-redo"></i> Resend Code
            </a>
        </div>

        <div class="back-to-login">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        // Auto-focus on OTP input
        document.getElementById('otp').focus();
        
        // Auto-submit when 6 digits are entered
        document.getElementById('otp').addEventListener('input', function() {
            if (this.value.length === 6) {
                document.getElementById('otp-form').submit();
            }
        });
        
        // Only allow numbers
        document.getElementById('otp').addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html> 