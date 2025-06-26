<?php
require_once '../includes/config.php';
require_role('admin');

$admin_info = get_admin_info($_SESSION['user_id']);
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $initial_balance = floatval($_POST['initial_balance'] ?? 0);
        $manager_id = intval($_POST['manager_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($name) || empty($username) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!validate_email($email)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($initial_balance < 0) {
            $error = 'Initial balance cannot be negative.';
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already exists.';
            } else {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already exists.';
                } else {
                    // Generate account number
                    $account_number = generate_account_number();
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    try {
                        $conn->beginTransaction();
                        
                        // Create user
                        $stmt = $conn->prepare("
                            INSERT INTO users (name, username, password, email, phone, account_number, balance, manager_id, admin_id, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                        ");
                        $stmt->execute([
                            $name, $username, $hashed_password, $email, $phone, 
                            $account_number, $initial_balance, $manager_id, $_SESSION['user_id']
                        ]);
                        
                        $user_id = $conn->lastInsertId();
                        
                        // If initial balance > 0, create deposit transaction
                        if ($initial_balance > 0) {
                            $stmt = $conn->prepare("
                                INSERT INTO transactions (user_id, amount, type, balance_after, description, reference) 
                                VALUES (?, ?, 'deposit', ?, 'Initial deposit', ?)
                            ");
                            $reference = 'TXN' . date('YmdHis') . rand(1000, 9999);
                            $stmt->execute([$user_id, $initial_balance, $initial_balance, $reference]);
                        }
                        
                        // Log activity
                        log_activity($_SESSION['user_id'], 'admin', 'Created new user: ' . $username);
                        
                        $conn->commit();
                        
                        $success = 'User account created successfully! Account Number: ' . $account_number;
                        
                        // Clear form data
                        $_POST = array();
                        
                    } catch (Exception $e) {
                        $conn->rollBack();
                        $error = 'Failed to create user account. Please try again.';
                    }
                }
            }
        }
    }
}

// Get managers for dropdown
$stmt = $conn->prepare("SELECT id, name, username FROM managers WHERE status = 'active' ORDER BY name");
$stmt->execute();
$managers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User Account - ATM System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-4">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <i class="fas fa-user-plus text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-semibold text-gray-900">Create User Account</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-user-circle text-gray-400"></i>
                        <span class="text-sm text-gray-700"><?= htmlspecialchars($admin_info['name']) ?></span>
                    </div>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800 text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Create New User Account</h2>
            <p class="text-gray-600">Add a new customer to the ATM system</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- User Creation Form -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">User Information</h3>
                </div>
                <div class="p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <!-- Personal Information -->
                        <div class="space-y-4">
                            <h4 class="font-medium text-gray-900">Personal Details</h4>
                            
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                <input type="text" 
                                       name="name" 
                                       id="name" 
                                       required
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                       placeholder="Enter full name"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                                <input type="text" 
                                       name="username" 
                                       id="username" 
                                       required
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                       placeholder="Enter username"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                <input type="email" 
                                       name="email" 
                                       id="email" 
                                       required
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       placeholder="Enter email address"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input type="tel" 
                                       name="phone" 
                                       id="phone" 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                       placeholder="Enter phone number"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="space-y-4">
                            <h4 class="font-medium text-gray-900">Account Details</h4>
                            
                            <div>
                                <label for="manager_id" class="block text-sm font-medium text-gray-700 mb-1">Assign Manager</label>
                                <select name="manager_id" 
                                        id="manager_id"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select a manager (optional)</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?= $manager['id'] ?>" <?= ($_POST['manager_id'] ?? '') == $manager['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($manager['name']) ?> (<?= htmlspecialchars($manager['username']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="initial_balance" class="block text-sm font-medium text-gray-700 mb-1">Initial Balance</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">$</span>
                                    </div>
                                    <input type="number" 
                                           name="initial_balance" 
                                           id="initial_balance" 
                                           step="0.01" 
                                           min="0"
                                           value="<?= htmlspecialchars($_POST['initial_balance'] ?? '0.00') ?>"
                                           placeholder="0.00"
                                           class="w-full pl-7 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <p class="text-sm text-gray-500 mt-1">Leave as 0.00 if no initial deposit</p>
                            </div>
                        </div>

                        <!-- Security Information -->
                        <div class="space-y-4">
                            <h4 class="font-medium text-gray-900">Security</h4>
                            
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       required
                                       placeholder="Enter password"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-sm text-gray-500 mt-1">Minimum 6 characters</p>
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirm_password" 
                                       required
                                       placeholder="Confirm password"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-between pt-4">
                            <button type="button" onclick="window.location.href='index.php'" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium">
                                <i class="fas fa-user-plus mr-2"></i>Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Information Panel -->
            <div class="space-y-6">
                <!-- Account Creation Info -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-2 rounded-full bg-blue-100 text-blue-600 mr-3">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">Account Creation Info</h3>
                    </div>
                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                            <span>Account number will be automatically generated</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                            <span>User will be set to active status</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                            <span>Initial deposit transaction will be recorded</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                            <span>Activity will be logged for audit purposes</span>
                        </div>
                    </div>
                </div>

                <!-- Requirements -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-2 rounded-full bg-yellow-100 text-yellow-600 mr-3">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">Requirements</h3>
                    </div>
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex items-start">
                            <i class="fas fa-asterisk text-red-500 mt-1 mr-2 text-xs"></i>
                            <span>All fields marked with * are required</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-asterisk text-red-500 mt-1 mr-2 text-xs"></i>
                            <span>Username must be unique</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-asterisk text-red-500 mt-1 mr-2 text-xs"></i>
                            <span>Email must be valid and unique</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-asterisk text-red-500 mt-1 mr-2 text-xs"></i>
                            <span>Password must be at least 6 characters</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Recent Users</h3>
                    </div>
                    <div class="p-6">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT u.*, m.name as manager_name 
                            FROM users u 
                            LEFT JOIN managers m ON u.manager_id = m.id 
                            ORDER BY u.created_at DESC 
                            LIMIT 5
                        ");
                        $stmt->execute();
                        $recent_users = $stmt->fetchAll();
                        ?>
                        
                        <?php if (empty($recent_users)): ?>
                            <p class="text-gray-500 text-center py-4">No users created yet</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($recent_users as $user): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="p-2 rounded-full bg-blue-100">
                                                <i class="fas fa-user text-blue-600 text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></p>
                                                <p class="text-sm text-gray-500"><?= htmlspecialchars($user['account_number']) ?></p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900"><?= format_currency($user['balance']) ?></p>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= get_status_badge_class($user['status']) ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>
