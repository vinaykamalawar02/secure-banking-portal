<?php
require_once 'includes/config.php';

$error = '';
$success = '';
$active_tab = $_GET['tab'] ?? 'customer'; // Default to customer tab

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_type = $_POST['login_type'] ?? 'customer';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $redirect_url = '';
        
        switch ($login_type) {
            case 'admin':
                // Check admin login
                $stmt = $conn->prepare("SELECT id, name, username, password, status FROM admin WHERE username = ? AND status = 'active'");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();
                
                if ($admin && password_verify($password, $admin['password'])) {
                    login_user($admin['id'], $admin['username'], 'admin');
                    log_activity($admin['id'], 'admin', 'Login successful');
                    $redirect_url = 'admin/index.php';
                } else {
                    $error = 'Invalid admin credentials.';
                }
                break;
                
            case 'manager':
                // Check manager login
                $stmt = $conn->prepare("SELECT id, name, username, password, status FROM managers WHERE username = ? AND status = 'active'");
                $stmt->execute([$username]);
                $manager = $stmt->fetch();
                
                if ($manager && password_verify($password, $manager['password'])) {
                    login_user($manager['id'], $manager['username'], 'manager');
                    log_activity($manager['id'], 'manager', 'Login successful');
                    $redirect_url = 'manager/index.php';
                } else {
                    $error = 'Invalid manager credentials.';
                }
                break;
                
            case 'customer':
            default:
                // Check user login
                $stmt = $conn->prepare("SELECT id, name, username, password, status FROM users WHERE username = ? AND status = 'active'");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    login_user($user['id'], $user['username'], 'user');
                    log_activity($user['id'], 'user', 'Login successful');
                    $redirect_url = 'user/index.php';
                } else {
                    $error = 'Invalid customer credentials.';
                }
                break;
        }
        
        if ($redirect_url) {
            header('Location: ' . $redirect_url);
            exit();
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
    </div>

    <!-- Tab Navigation -->
    <div class="tab-container">
      <button onclick="switchTab('customer')" id="tab-customer" class="tab-button <?= $active_tab === 'customer' ? 'active' : '' ?>">
        <i class="fas fa-user"></i>Customer
      </button>
      <button onclick="switchTab('manager')" id="tab-manager" class="tab-button <?= $active_tab === 'manager' ? 'active' : '' ?>">
        <i class="fas fa-user-tie"></i>Manager
      </button>
      <button onclick="switchTab('admin')" id="tab-admin" class="tab-button <?= $active_tab === 'admin' ? 'active' : '' ?>">
        <i class="fas fa-shield-alt"></i>Admin
      </button>
    </div>

    <!-- Customer Login Form -->
    <form method="POST" id="form-customer" class="login-form <?= $active_tab === 'customer' ? 'active' : '' ?>">
      <input type="hidden" name="login_type" value="customer">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      
      <div class="form-group">
        <label for="customer-username" class="form-label">Username</label>
        <div class="input-group">
          <i class="fas fa-user input-icon"></i>
          <input type="text" name="username" id="customer-username" required placeholder="Enter your username" class="form-input" />
        </div>
      </div>

      <div class="form-group">
        <label for="customer-password" class="form-label">Password</label>
        <div class="input-group">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" id="customer-password" required placeholder="Enter your password" class="form-input" />
          <button type="button" onclick="togglePassword('customer-password')" class="password-toggle">
            <i class="fas fa-eye" id="customer-eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="submit-button customer">
        <i class="fas fa-sign-in-alt"></i> Access Customer Portal
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
          <input type="text" name="username" id="manager-username" required placeholder="Enter manager username" class="form-input" />
        </div>
      </div>

      <div class="form-group">
        <label for="manager-password" class="form-label">Manager Password</label>
        <div class="input-group">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" id="manager-password" required placeholder="Enter manager password" class="form-input" />
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
          <input type="text" name="username" id="admin-username" required placeholder="Enter admin username" class="form-input" />
        </div>
      </div>

      <div class="form-group">
        <label for="admin-password" class="form-label">Admin Password</label>
        <div class="input-group">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" name="password" id="admin-password" required placeholder="Enter admin password" class="form-input" />
          <button type="button" onclick="togglePassword('admin-password')" class="password-toggle">
            <i class="fas fa-eye" id="admin-eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="submit-button admin">
        <i class="fas fa-shield-alt"></i> Access Admin Portal
      </button>
    </form>

    <!-- Footer Links -->
    <div class="login-footer">
      <a href="user/register.php" class="create-account-btn">
        <i class="fas fa-user-plus"></i> Create New Account
      </a>
      <a href="#" class="contact-link" onclick="showModal('contact-modal')">
        <i class="fas fa-headset"></i>Contact Support
      </a>
    </div>
  </div>

  <!-- Contact Form Modal -->
  <div id="contact-modal" class="modal">
    <div class="modal-content">
      <button onclick="hideModal('contact-modal')" class="modal-close">
        <i class="fas fa-times"></i>
      </button>
      <div class="modal-header">
        <div class="icon">
          <i class="fas fa-headset"></i>
        </div>
        <h2>Contact Support</h2>
        <p>We're here to help you</p>
      </div>
      <form class="space-y-4">
        <div class="form-group">
          <label class="form-label">Your Name</label>
          <input type="text" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Message</label>
          <textarea class="form-input" rows="3" required></textarea>
        </div>
        <button type="submit" class="submit-button customer">
          <i class="fas fa-paper-plane"></i>Send Message
        </button>
      </form>
    </div>
  </div>

  <script>
    function switchTab(tab) {
      // Hide all forms
      document.getElementById('form-customer').classList.remove('active');
      document.getElementById('form-manager').classList.remove('active');
      document.getElementById('form-admin').classList.remove('active');
      
      // Remove active class from all tabs
      document.getElementById('tab-customer').classList.remove('active');
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

    // Set initial tab based on URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
      switchTab(tab);
    }

    // Show error popup if there's an error
    <?php if (!empty($error)): ?>
    window.onload = () => {
      showErrorPopup(<?= json_encode($error) ?>);
    };
    <?php endif; ?>
  </script>

</body>
</html>