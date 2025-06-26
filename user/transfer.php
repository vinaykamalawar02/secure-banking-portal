<?php
require_once '../includes/config.php';
require_role('user');

$user_info = get_user_info($_SESSION['user_id']);
$error = '';
$success = '';

// Handle transfer form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $recipient_username = trim($_POST['recipient_username'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($recipient_username)) {
            $error = 'Please enter recipient username.';
        } elseif ($amount <= 0) {
            $error = 'Please enter a valid amount.';
        } elseif ($amount > $user_info['balance']) {
            $error = 'Insufficient balance. Your current balance is ' . format_currency($user_info['balance']) . '.';
        } elseif ($amount > 50000) {
            $error = 'Maximum transfer amount is $50,000 per transaction.';
        } elseif ($recipient_username === $user_info['username']) {
            $error = 'You cannot transfer money to yourself.';
        } else {
            // Check if recipient exists
            $stmt = $conn->prepare("SELECT id, name, username FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$recipient_username]);
            $recipient = $stmt->fetch();
            
            if (!$recipient) {
                $error = 'Recipient not found or account inactive.';
            } else {
                // Process transfer
                $conn->beginTransaction();
                try {
                    // Deduct from sender
                    if (create_transaction($_SESSION['user_id'], -$amount, 'transfer', "Transfer to " . $recipient['username'] . ": " . $description)) {
                        // Add to recipient
                        if (create_transaction($recipient['id'], $amount, 'transfer', "Transfer from " . $user_info['username'] . ": " . $description)) {
                            $conn->commit();
                            $success = 'Transfer successful! Amount: ' . format_currency($amount) . ' to ' . $recipient['name'];
                            log_activity($_SESSION['user_id'], 'user', 'Transfer: ' . format_currency($amount) . ' to ' . $recipient['username']);
                            
                            // Update user info after successful transaction
                            $user_info = get_user_info($_SESSION['user_id']);
                        } else {
                            throw new Exception('Failed to credit recipient account.');
                        }
                    } else {
                        throw new Exception('Failed to debit your account.');
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Transfer failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get recent transfers
$stmt = $conn->prepare("
    SELECT t.*, u.name as recipient_name, u.username as recipient_username
    FROM transactions t
    LEFT JOIN users u ON t.description LIKE CONCAT('%', u.username, '%')
    WHERE t.user_id = ? AND t.type = 'transfer'
    ORDER BY t.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_transfers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer - ATM System</title>
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
                    <i class="fas fa-exchange-alt text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-semibold text-gray-900">Transfer Money</h1>
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
            <!-- Transfer Form -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Transfer Money</h3>
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
                            <label for="recipient_username" class="block text-sm font-medium text-gray-700 mb-2">Recipient Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input type="text" 
                                       name="recipient_username" 
                                       id="recipient_username" 
                                       required
                                       placeholder="Enter recipient username"
                                       class="block w-full pl-10 border border-gray-300 rounded-md py-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Enter the username of the person you want to transfer to</p>
                        </div>

                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Transfer Amount</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input type="number" 
                                       name="amount" 
                                       id="amount" 
                                       step="0.01" 
                                       min="0.01" 
                                       max="50000"
                                       required
                                       placeholder="0.00"
                                       class="block w-full pl-7 pr-12 border border-gray-300 rounded-md py-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                       onchange="updateQuickAmounts(this.value)">
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Maximum transfer: $50,000 per transaction</p>
                        </div>

                        <!-- Quick Amount Buttons -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quick Amounts</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" onclick="setAmount(10)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $10
                                </button>
                                <button type="button" onclick="setAmount(25)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $25
                                </button>
                                <button type="button" onclick="setAmount(50)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $50
                                </button>
                                <button type="button" onclick="setAmount(100)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $100
                                </button>
                                <button type="button" onclick="setAmount(250)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $250
                                </button>
                                <button type="button" onclick="setAmount(500)" class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                                    $500
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                            <textarea name="description" 
                                      id="description" 
                                      rows="3"
                                      placeholder="Enter a description for this transfer..."
                                      class="block w-full border border-gray-300 rounded-md py-3 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="button" onclick="window.location.href='index.php'" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium">
                                <i class="fas fa-exchange-alt mr-2"></i>Transfer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Transfers -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Transfers</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_transfers)): ?>
                        <p class="text-gray-500 text-center py-4">No recent transfers</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_transfers as $transfer): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="p-2 bg-blue-100 rounded-full">
                                            <i class="fas fa-exchange-alt text-blue-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">
                                                <?= $transfer['amount'] > 0 ? 'Received from' : 'Sent to' ?>
                                                <?= htmlspecialchars($transfer['recipient_username'] ?? 'Unknown') ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?= date('M j, g:i A', strtotime($transfer['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium <?= $transfer['amount'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $transfer['amount'] > 0 ? '+' : '' ?><?= format_currency($transfer['amount']) ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            Balance: <?= format_currency($transfer['balance_after']) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
        }
        
        function updateQuickAmounts(value) {
            // This function can be used to add validation or formatting
            if (value > 50000) {
                alert('Maximum transfer amount is $50,000');
                document.getElementById('amount').value = 50000;
            }
        }
    </script>
</body>
</html> 