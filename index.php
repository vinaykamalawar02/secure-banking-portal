<?php
require_once 'includes/config.php';
require_once 'includes/security.php';

// Initialize secure session
init_secure_session();

$error = '';
$success = '';
$active_tab = $_GET['tab'] ?? 'user'; // Default to user tab

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $login_type = $_POST['login_type'] ?? 'user';
        
        // Validate login type
        if (!in_array($login_type, ['admin', 'manager', 'user'])) {
            $error = 'Invalid login type.';
        } elseif (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            // Check for account lockout
            if (!check_login_attempts($username, $login_type)) {
                $error = 'Account is temporarily locked due to multiple failed login attempts. Please try again later.';
            } else {
                $redirect_url = '';
                $user_data = null;
                
                try {
                    switch ($login_type) {
                        case 'admin':
                            // Check admin login
                            $stmt = $conn->prepare("SELECT id, name, username, password, email, status, two_factor_enabled FROM admin WHERE username = ? AND status = 'active'");
                            $stmt->execute([$username]);
                            $user_data = $stmt->fetch();
                            break;
                            
                        case 'manager':
                            // Check manager login
                            $stmt = $conn->prepare("SELECT id, name, username, password, email, status, two_factor_enabled FROM managers WHERE username = ? AND status = 'active'");
                            $stmt->execute([$username]);
                            $user_data = $stmt->fetch();
                            break;
                            
                        case 'user':
                        default:
                            // Check user login
                            $stmt = $conn->prepare("SELECT id, name, username, password, email, status, two_factor_enabled FROM users WHERE username = ? AND status = 'active'");
                            $stmt->execute([$username]);
                            $user_data = $stmt->fetch();
                            break;
                    }
                    
                    if ($user_data && password_verify($password, $user_data['password'])) {
                        // Clear failed login attempts
                        clear_login_attempts($username, $login_type);
                        
                        // Check if 2FA is enabled (with null check)
                        if (!empty($user_data['two_factor_enabled'])) {
                            // Generate and send OTP
                            $otp = generate_otp();
                            if (store_otp($user_data['id'], $login_type, $otp)) {
                                if (send_otp_email($user_data['email'], $otp, $user_data['username'])) {
                                    // Start 2FA process
                                    secure_login($user_data['id'], $user_data['username'], $login_type, true);
                                    header('Location: verify_2fa.php');
                                    exit();
                                } else {
                                    $error = 'Failed to send verification code. Please try again.';
                                    log_security_event($user_data['id'], $login_type, '2FA email send failed');
                                }
                            } else {
                                $error = 'Failed to generate verification code. Please try again.';
                            }
                        } else {
                            // Direct login without 2FA
                            secure_login($user_data['id'], $user_data['username'], $login_type, false);
                            
                            // Log successful login
                            log_security_event($user_data['id'], $login_type, 'Login successful');
                            
                            // Redirect to appropriate dashboard
                            switch ($login_type) {
                                case 'admin':
                                    $redirect_url = 'admin/index.php';
                                    break;
                                case 'manager':
                                    $redirect_url = 'manager/index.php';
                                    break;
                                case 'user':
                                default:
                                    $redirect_url = 'user/index.php';
                                    break;
                            }
                            
                            header('Location: ' . $redirect_url);
                            exit();
                        }
                    } else {
                        // Record failed login attempt
                        record_login_attempt($username, $login_type, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
                        $error = 'Invalid credentials. Please try again.';
                        log_security_event(0, $login_type, 'Failed login attempt', "Username: $username");
                    }
                } catch (PDOException $e) {
                    $error = 'Database error occurred. Please try again.';
                    log_security_event(0, $login_type, 'Database error during login', $e->getMessage());
                }
            }
        }
    }
}

// If user is already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    switch ($_SESSION['user_role']) {
        case 'admin':
            header('Location: admin/index.php');
            exit();
        case 'manager':
            header('Location: manager/index.php');
            exit();
        case 'user':
            header('Location: user/index.php');
            exit();
    }
}

// Display session expired message
if (isset($_GET['error']) && $_GET['error'] === 'session_expired') {
    $error = 'Your session has expired. Please log in again.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ATM System - Secure Banking Portal</title>

  <!-- FontAwesome Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  
  <!-- Custom CSS -->
  <link href="assets/css/login.css" rel="stylesheet" />
  
  <!-- Custom JavaScript -->
  <script src="assets/js/main.js"></script>
</head>
<body>
  <!-- Warning Popup Script -->
  <?php if (!empty($error)): ?>
  <script>
    window.onload = () => {
      showErrorPopup(<?= json_encode($error) ?>);
    };
  </script>
  <?php endif; ?>

  <div class="form-wrapper">
    <!-- Header -->
    <div class="login-header">
      <div class="icon">
        <i class="fas fa-university"></i>
      </div>
      <h1>Secure Banking Portal</h1>
      <p>Choose your access level and enter your credentials</p>
      <div class="security-badge">
        <i class="fas fa-shield-alt"></i>
        <span>Enhanced Security with 2FA</span>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-container">
      <button onclick="switchTab('user')" id="tab-user" class="tab-button <?= $active_tab === 'user' ? 'active' : '' ?>">
        <i class="fas fa-user"></i>User
      </button>
      <button onclick="switchTab('manager')" id="tab-manager" class="tab-button <?= $active_tab === 'manager' ? 'active' : '' ?>">
        <i class="fas fa-user-tie"></i>Manager
      </button>
      <button onclick="switchTab('admin')" id="tab-admin" class="tab-button <?= $active_tab === 'admin' ? 'active' : '' ?>">
        <i class="fas fa-shield-alt"></i>Admin
      </button>
    </div>

    <!-- User Login Form -->
    <form method="POST" id="form-user" class="login-form <?= $active_tab === 'user' ? 'active' : '' ?>">
      <input type="hidden" name="login_type" value="user">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      
      <div class="form-group">
        <label for="user-username" class="form-label">Username</label>
        <div class="input-group">
          <i class="fas fa-user input-icon"></i>
          <input type="text" name="username" id="user-username" required placeholder="Enter your username" class="form-input" maxlength="50" />
        </div>
      </div>

      <div class="form-group">
        <label for="user-password" class="form-label">Password</label>
        <div class="input-group">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" id="user-password" required placeholder="Enter your password" class="form-input" maxlength="255" />
          <button type="button" onclick="togglePassword('user-password')" class="password-toggle">
            <i class="fas fa-eye" id="user-eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="submit-button user">
        <i class="fas fa-sign-in-alt"></i> Access User Portal
      </button>
    </form>

    <!-- Manager Login Form -->
    <form method="POST" id="form-manager" class="login-form <?= $active_tab === 'manager' ? 'active' : '' ?>">
      <input type="hidden" name="login_type" value="manager">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      
      <div class="form-group">
        <label for="manager-username" class="form-label">Manager Username</label>
        <div class="input-group">
          <i class="fas fa-user-tie input-icon"></i>
          <input type="text" name="username" id="manager-username" required placeholder="Enter manager username" class="form-input" maxlength="50" />
        </div>
      </div>

      <div class="form-group">
        <label for="manager-password" class="form-label">Manager Password</label>
        <div class="input-group">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" id="manager-password" required placeholder="Enter manager password" class="form-input" maxlength="255" />
          <button type="button" onclick="togglePassword('manager-password')" class="password-toggle">
            <i class="fas fa-eye" id="manager-eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="submit-button manager">
        <i class="fas fa-user-tie"></i> Access Manager Portal
      </button>
    </form>

    <!-- Admin Login Form -->
    <form method="POST" id="form-admin" class="login-form <?= $active_tab === 'admin' ? 'active' : '' ?>">
      <input type="hidden" name="login_type" value="admin">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      
      <div class="form-group">
        <label for="admin-username" class="form-label">Admin Username</label>
        <div class="input-group">
          <i class="fas fa-shield-alt input-icon"></i>
          <input type="text" name="username" id="admin-username" required placeholder="Enter admin username" class="form-input" maxlength="50" />
        </div>
      </div>

      <div class="form-group">
        <label for="admin-password" class="form-label">Admin Password</label>
        <div class="input-group">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" id="admin-password" required placeholder="Enter admin password" class="form-input" maxlength="255" />
          <button type="button" onclick="togglePassword('admin-password')" class="password-toggle">
            <i class="fas fa-eye" id="admin-eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="submit-button admin">
        <i class="fas fa-shield-alt"></i> Access Admin Portal
      </button>
    </form>
  </div>

  <script>
    function switchTab(tab) {
      // Hide all forms
      document.getElementById('form-user').classList.remove('active');
      document.getElementById('form-manager').classList.remove('active');
      document.getElementById('form-admin').classList.remove('active');
      
      // Remove active class from all tabs
      document.getElementById('tab-user').classList.remove('active');
      document.getElementById('tab-manager').classList.remove('active');
      document.getElementById('tab-admin').classList.remove('active');
      
      // Show selected form and activate tab
      document.getElementById('form-' + tab).classList.add('active');
      document.getElementById('tab-' + tab).classList.add('active');
      
      // Update URL without page reload
      const url = new URL(window.location);
      url.searchParams.set('tab', tab);
      window.history.pushState({}, '', url);
    }

    function togglePassword(inputId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(inputId.replace('-password', '-eye-icon'));
      
      if (input && icon) {
        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        } else {
          input.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      }
    }

    // Set initial tab based on URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab && ['user', 'manager', 'admin'].includes(tab)) {
      switchTab(tab);
    }

    // Enhanced form validation
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', function(e) {
        const username = this.querySelector('input[name="username"]').value.trim();
        const password = this.querySelector('input[name="password"]').value;
        
        if (!username || !password) {
          e.preventDefault();
          showErrorPopup('Please fill in all required fields.');
          return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('.submit-button');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
        submitBtn.disabled = true;
        
        // Re-enable after 3 seconds if no redirect
        setTimeout(() => {
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        }, 3000);
      });
    });
  </script>
</body>
</html>