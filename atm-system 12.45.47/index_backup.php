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

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#2563eb',
            secondary: '#1e3a8a',
          }
        }
      }
    }
  </script>

  <!-- FontAwesome Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  
  <!-- Custom CSS -->
  <link href="assets/css/style.css" rel="stylesheet" />
  
  <!-- Custom JavaScript -->
  <script src="assets/js/main.js"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-6">

  <!-- Warning Popup Script -->
  <?php if (!empty($error)): ?>
  <script>
    window.onload = () => {
      const popup = document.createElement('div');
      popup.innerHTML = `
        <div class="flex items-center space-x-3">
          <i class="fas fa-exclamation-triangle text-yellow-500"></i>
          <span class="font-medium">${<?= json_encode($error) ?>}</span>
        </div>
      `;
      popup.className =
        'fixed top-6 left-1/2 transform -translate-x-1/2 px-6 py-4 bg-white text-gray-800 font-medium rounded-xl shadow-lg z-50 border-l-4 border-yellow-500';
      document.body.appendChild(popup);
      setTimeout(() => popup.remove(), 4000);
    };
  </script>
  <?php endif; ?>

  <div class="form-wrapper glass p-8 space-y-6">
    <!-- Header with Simplified Design -->
    <div class="text-center space-y-4">
      <div class="relative inline-block">
        <i class="fas fa-university text-4xl text-blue-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Secure Banking Portal</h1>
        <p class="text-gray-600">Choose your access level and enter your credentials</p>
      </div>
    </div>

    <!-- Simplified Login Type Tabs -->
    <div class="flex bg-gray-100 rounded-lg p-1">
      <button onclick="switchTab('customer')" id="tab-customer" class="tab-button flex-1 py-3 px-4 rounded-md text-sm font-semibold <?= $active_tab === 'customer' ? 'active' : 'text-gray-600' ?>">
        <i class="fas fa-user mr-2"></i>Customer
      </button>
      <button onclick="switchTab('manager')" id="tab-manager" class="tab-button flex-1 py-3 px-4 rounded-md text-sm font-semibold <?= $active_tab === 'manager' ? 'active' : 'text-gray-600' ?>">
        <i class="fas fa-user-tie mr-2"></i>Manager
      </button>
      <button onclick="switchTab('admin')" id="tab-admin" class="tab-button flex-1 py-3 px-4 rounded-md text-sm font-semibold <?= $active_tab === 'admin' ? 'active' : 'text-gray-600' ?>">
        <i class="fas fa-shield-alt mr-2"></i>Admin
      </button>
    </div>

    <!-- Customer Login Form -->
    <form method="POST" id="form-customer" class="space-y-5" style="display: <?= $active_tab === 'customer' ? 'block' : 'none' ?>;">
      <input type="hidden" name="login_type" value="customer">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      
      <div class="space-y-2">
        <label for="customer-username" class="block text-sm font-semibold text-gray-700">Username</label>
        <div class="flex items-center border-2 border-gray-200 rounded-lg px-4 py-3 bg-white">
          <i class="fas fa-user text-gray-400 mr-3"></i>
          <input type="text" name="username" id="customer-username" required placeholder="Enter your username"
                 class="w-full text-gray-700 bg-transparent focus:outline-none animated-input" />
        </div>
      </div>

      <div class="space-y-2">
        <label for="customer-password" class="block text-sm font-semibold text-gray-700">Password</label>
        <div class="flex items-center border-2 border-gray-200 rounded-lg px-4 py-3 bg-white">
          <i class="fas fa-lock text-gray-400 mr-3"></i>
          <input type="password" name="password" id="customer-password" required placeholder="Enter your password"
                 class="w-full text-gray-700 bg-transparent focus:outline-none animated-input" />
          <button type="button" onclick="togglePassword('customer-password')" class="ml-3 text-blue-500 hover:text-blue-700">
            <i class="fas fa-eye" id="customer-eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="w-full login-button text-white py-3 rounded-lg font-bold shadow-lg">
        <i class="fas fa-sign-in-alt mr-2"></i> Access Customer Portal
      </button>
    </form>

    <!-- Manager Login Form -->
    <form method="POST" id="form-manager" class="space-y-5" style="display: <?= $active_tab === 'manager' ? 'block' : 'none' ?>;">
      <input type="hidden" name="login_type" value="manager">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      
      <div class="space-y-2">
        <label for="manager-username" class="block text-sm font-semibold text-gray-700">Manager Username</label>
        <div class="flex items-center border-2 border-gray-200 rounded-lg px-4 py-3 bg-white">
          <i class="fas fa-user-tie text-gray-400 mr-3"></i>
          <input type="text" name="username" id="manager-username" required placeholder="Enter manager username"
                 class="w-full text-gray-700 bg-transparent focus:outline-none animated-input" />
        </div>
      </div>

      <div class="space-y-2">
        <label for="manager-password" class="block text-sm font-semibold text-gray-700">Manager Password</label>
        <div class="flex items-center border-2 border-gray-200 rounded-lg px-4 py-3 bg-white">
          <i class="fas fa-lock text-gray-400 mr-3"></i>
          <input type="password" name="password" id="manager-password" required placeholder="Enter manager password"
                 class="w-full text-gray-700 bg-transparent focus:outline-none animated-input" />
          <button type="button" onclick="togglePassword('manager-password')" class="ml-3 text-blue-500 hover:text-blue-700">
            <i class="fas fa-eye" id="manager-eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="w-full manager-button text-white py-3 rounded-lg font-bold shadow-lg">
        <i class="fas fa-user-tie mr-2"></i> Access Manager Portal
      </button>
    </form>

    <!-- Admin Login Form -->
    <form method="POST" id="form-admin" class="space-y-5" style="display: <?= $active_tab === 'admin' ? 'block' : 'none' ?>;">
      <input type="hidden" name="login_type" value="admin">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      
      <div class="space-y-2">
        <label for="admin-username" class="block text-sm font-semibold text-gray-700">Admin Username</label>
        <div class="flex items-center border-2 border-gray-200 rounded-lg px-4 py-3 bg-white">
          <i class="fas fa-shield-alt text-gray-400 mr-3"></i>
          <input type="text" name="username" id="admin-username" required placeholder="Enter admin username"
                 class="w-full text-gray-700 bg-transparent focus:outline-none animated-input" />
        </div>
      </div>

      <div class="space-y-2">
        <label for="admin-password" class="block text-sm font-semibold text-gray-700">Admin Password</label>
        <div class="flex items-center border-2 border-gray-200 rounded-lg px-4 py-3 bg-white">
          <i class="fas fa-lock text-gray-400 mr-3"></i>
          <input type="password" name="password" id="admin-password" required placeholder="Enter admin password"
                 class="w-full text-gray-700 bg-transparent focus:outline-none animated-input" />
          <button type="button" onclick="togglePassword('admin-password')" class="ml-3 text-blue-500 hover:text-blue-700">
            <i class="fas fa-eye" id="admin-eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="w-full admin-button text-white py-3 rounded-lg font-bold shadow-lg">
        <i class="fas fa-shield-alt mr-2"></i> Access Admin Portal
      </button>
    </form>

    <!-- Simplified Footer Links -->
    <div class="space-y-4 pt-6 border-t border-gray-200">
      <div class="text-center">
        <a href="user/register.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg font-semibold hover:shadow-lg transition-all">
          <i class="fas fa-user-plus mr-2"></i> Create New Account
        </a>
      </div>
      <div class="text-center">
        <a href="#" class="text-blue-600 hover:text-blue-800 font-medium" onclick="document.getElementById('contact-modal').style.display='block'">
          <i class="fas fa-headset mr-2"></i>Contact Support
        </a>
      </div>
    </div>
  </div>

  <!-- Simplified Contact Form Modal -->
  <div id="contact-modal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full mx-4">
      <button onclick="document.getElementById('contact-modal').style.display='none'" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700">
        <i class="fas fa-times"></i>
      </button>
      <div class="text-center mb-4">
        <i class="fas fa-headset text-3xl text-blue-600 mb-2"></i>
        <h2 class="text-xl font-bold text-gray-800">Contact Support</h2>
      </div>
      <form class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Your Name</label>
          <input type="text" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500" required>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
          <input type="email" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500" required>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Message</label>
          <textarea class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500" rows="3" required></textarea>
        </div>
        <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-2 rounded-lg font-semibold">
          <i class="fas fa-paper-plane mr-2"></i>Send Message
        </button>
      </form>
    </div>
  </div>

  <script>
    function switchTab(tab) {
      // Hide all forms
      document.getElementById('form-customer').style.display = 'none';
      document.getElementById('form-manager').style.display = 'none';
      document.getElementById('form-admin').style.display = 'none';
      
      // Remove active class from all tabs
      document.getElementById('tab-customer').classList.remove('active');
      document.getElementById('tab-manager').classList.remove('active');
      document.getElementById('tab-admin').classList.remove('active');
      
      // Show selected form and activate tab
      document.getElementById('form-' + tab).style.display = 'block';
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