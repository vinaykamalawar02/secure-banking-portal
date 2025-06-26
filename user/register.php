<?php
require_once '../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($username) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!validate_email($email)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (check_username_exists($username)) {
            $error = 'Username already exists.';
        } elseif (check_email_exists($email)) {
            $error = 'Email already exists.';
        } else {
            $account_number = generate_account_number();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $conn->prepare("INSERT INTO users (name, username, password, email, phone, account_number, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$name, $username, $hashed_password, $email, $phone, $account_number]);
                $success = 'Registration successful! Your account is pending approval.';
                $_POST = array();
            } catch (Exception $e) {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Account - ATM System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen flex items-center justify-center p-6">
    <div class="form-wrapper glass p-8 space-y-6 max-w-md w-full bg-white rounded-lg shadow-lg">
        <div class="text-center">
            <i class="fas fa-user-plus text-blue-600 text-4xl mb-2"></i>
            <h1 class="text-2xl font-bold text-gray-800">Create New Account</h1>
            <p class="text-gray-500 text-sm">Fill in your details to register</p>
        </div>
        <?php if (!empty($success)): ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST" autocomplete="off" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                <input type="text" name="name" id="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Enter your full name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                <input type="text" name="username" id="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Choose a username" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                <input type="email" name="email" id="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Enter your email" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="Enter your phone number" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                <input type="password" name="password" id="password" required placeholder="Create a password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
            </div>
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm your password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-semibold shadow transition-all">
                <i class="fas fa-user-plus mr-2"></i> Register
            </button>
        </form>
        <div class="text-center text-sm text-gray-500">
            Already have an account? <a href="../index.php" class="text-blue-600 hover:underline">Login here</a>
        </div>
    </div>
</body>
</html> 