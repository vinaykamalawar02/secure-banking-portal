<?php
require_once '../includes/config.php';
require_role('user');

$user_info = get_user_info($_SESSION['user_id']);
$error = '';
$success = '';

// Handle withdrawal form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $amount = floatval($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if ($amount <= 0) {
            $error = 'Please enter a valid amount.';
        } elseif ($amount > $user_info['balance']) {
            $error = 'Insufficient balance. Your current balance is ' . format_currency($user_info['balance']) . '.';
        } elseif ($amount > 10000) {
            $error = 'Maximum withdrawal amount is $10,000 per transaction.';
        } else {
            // Process withdrawal
            if (create_transaction($_SESSION['user_id'], -$amount, 'withdrawal', $description)) {
                $success = 'Withdrawal successful! Amount: ' . format_currency($amount);
                log_activity($_SESSION['user_id'], 'user', 'Withdrawal: ' . format_currency($amount));
                
                // Update user info after successful transaction
                $user_info = get_user_info($_SESSION['user_id']);
            } else {
                $error = 'Transaction failed. Please try again.';
            }
        }
    }
}

// Get recent withdrawals
$stmt = $conn->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? AND type = 'withdrawal'
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_withdrawals = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw - ATM System</title>
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
                    <i class="fas fa-arrow-up text-red-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-semibold text-gray-900">Withdraw Money</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-user-circle text-gray-400"></i>
                        <span class="text-sm text-gray-700"><?= htmlspecialchars($user_info['name']) ?></span>
                    </div>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800 text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
            <!-- Withdrawal Form -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Withdraw Money</h3>
                </div>
                <div class="p-6">
                    <!-- Current Balance Display -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg p-4 mb-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm opacity-90">Current Balance</p>
                                <p class="text-2xl font-bold"><?= format_currency($user_info['balance']) ?></p>
                            </div>
                            <div class="p-2 rounded-full bg-white bg-opacity-20">
                                <i class="fas fa-wallet text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Amount to Withdraw</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input type="number" 
                                       name="amount" 
                                       id="amount" 
                                       step="0.01" 
                                       min="0.01" 
                                       max="10000"
                                       required
                                       placeholder="0.00"
                                       class="block w-full pl-7 pr-12 border border-gray-300 rounded-md py-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                       onchange="updateQuickAmounts(this.value)">
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Maximum withdrawal: $10,000 per transaction</p>
                        </div>

                        <!-- Quick Amount Buttons -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quick Amounts</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" onclick="setAmount(20)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $20
                                </button>
                                <button type="button" onclick="setAmount(50)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $50
                                </button>
                                <button type="button" onclick="setAmount(100)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $100
                                </button>
                                <button type="button" onclick="setAmount(200)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $200
                                </button>
                                <button type="button" onclick="setAmount(500)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $500
                                </button>
                                <button type="button" onclick="setAmount(1000)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $1,000
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                            <textarea name="description" 
                                      id="description" 
                                      rows="3"
                                      placeholder="Enter a description for this withdrawal..."
                                      class="block w-full border border-gray-300 rounded-md py-3 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="button" onclick="window.location.href='index.php'" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md font-medium">
                                <i class="fas fa-arrow-up mr-2"></i>Withdraw
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Withdrawals -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Withdrawals</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_withdrawals)): ?>
                        <p class="text-gray-500 text-center py-4">No recent withdrawals</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_withdrawals as $withdrawal): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="p-2 rounded-full bg-red-100">
                                            <i class="fas fa-arrow-up text-red-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900"><?= format_currency($withdrawal['amount']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($withdrawal['reference']) ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-500"><?= format_date($withdrawal['created_at']) ?></p>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= get_status_badge_class($withdrawal['status']) ?>">
                                            <?= ucfirst($withdrawal['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Security Notice -->
        <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start">
                <i class="fas fa-shield-alt text-yellow-600 mt-1 mr-3"></i>
                <div>
                    <h4 class="text-sm font-medium text-yellow-800">Security Notice</h4>
                    <p class="text-sm text-yellow-700 mt-1">
                        For your security, please ensure you're in a safe location when making withdrawals. 
                        Never share your PIN or account details with anyone. 
                        If you notice any suspicious activity, contact your bank immediately.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
        }
        
        function updateQuickAmounts(value) {
            // You can add validation here if needed
            const amount = parseFloat(value);
            if (amount > <?= $user_info['balance'] ?>) {
                alert('Amount exceeds your current balance!');
                document.getElementById('amount').value = '';
            }
        }
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>