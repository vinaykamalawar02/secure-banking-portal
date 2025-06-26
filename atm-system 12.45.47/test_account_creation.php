<?php
// Test Account Creation Functionality
require_once 'includes/config.php';

echo "<h1>ATM System - Account Creation Test</h1>";

// Test database connection
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>✓ Database connection successful. Total users: " . $result['count'] . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Test function availability
$functions = [
    'generate_account_number',
    'validate_email',
    'check_username_exists',
    'check_email_exists',
    'create_user_account',
    'log_activity'
];

echo "<h2>Function Availability Test</h2>";
foreach ($functions as $function) {
    if (function_exists($function)) {
        echo "<p>✓ Function '$function' is available</p>";
    } else {
        echo "<p>❌ Function '$function' is missing</p>";
    }
}

// Test account number generation
echo "<h2>Account Number Generation Test</h2>";
for ($i = 0; $i < 5; $i++) {
    $account_number = generate_account_number();
    echo "<p>Generated account number: $account_number</p>";
}

// Test email validation
echo "<h2>Email Validation Test</h2>";
$test_emails = [
    'test@example.com' => true,
    'invalid-email' => false,
    'user@domain.co.uk' => true,
    'test.email@subdomain.example.com' => true
];

foreach ($test_emails as $email => $expected) {
    $result = validate_email($email);
    $status = $result === $expected ? '✓' : '❌';
    echo "<p>$status Email '$email': " . ($result ? 'valid' : 'invalid') . " (expected: " . ($expected ? 'valid' : 'invalid') . ")</p>";
}

// Test user creation
echo "<h2>User Creation Test</h2>";
$test_user_data = [
    'name' => 'Test User',
    'username' => 'testuser' . time(),
    'email' => 'testuser' . time() . '@example.com',
    'phone' => '1234567890',
    'password' => 'testpass123',
    'initial_balance' => 500.00,
    'manager_id' => 1,
    'admin_id' => 1
];

try {
    $result = create_user_account($test_user_data);
    if ($result['success']) {
        echo "<p>✓ User created successfully!</p>";
        echo "<p>User ID: " . $result['user_id'] . "</p>";
        echo "<p>Account Number: " . $result['account_number'] . "</p>";
    } else {
        echo "<p>❌ User creation failed: " . $result['error'] . "</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ User creation error: " . $e->getMessage() . "</p>";
}

// Test duplicate username check
echo "<h2>Duplicate Username Test</h2>";
$duplicate_username = 'admin'; // This should already exist
$exists = check_username_exists($duplicate_username);
echo "<p>" . ($exists ? '✓' : '❌') . " Username '$duplicate_username' " . ($exists ? 'exists' : 'does not exist') . " (should exist)</p>";

// Test duplicate email check
echo "<h2>Duplicate Email Test</h2>";
$duplicate_email = 'admin@atm.system'; // This should already exist
$exists = check_email_exists($duplicate_email);
echo "<p>" . ($exists ? '✓' : '❌') . " Email '$duplicate_email' " . ($exists ? 'exists' : 'does not exist') . " (should exist)</p>";

echo "<h2>Test Complete!</h2>";
echo "<p><a href='index.php'>Go to Login Page</a></p>";
echo "<p><a href='admin/create_user.php'>Go to Admin Create User Page</a></p>";
echo "<p><a href='manager/create_user.php'>Go to Manager Create User Page</a></p>";
?> 