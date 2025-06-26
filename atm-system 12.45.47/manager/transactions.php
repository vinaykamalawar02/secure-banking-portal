<?php
// Initialize session and check authentication
require_once '../includes/config.php';
require_role('manager');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has manager role
if ($_SESSION['user_role'] !== 'manager') {
    header('Location: ../unauthorized.php');
    exit();
}

$manager_info = get_manager_info($_SESSION['user_id']);

// Handle filters
$filter_type = $_GET['type'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Build query
$where_conditions = ["u.manager_id = ?"];
$params = [$_SESSION['user_id']];

if (!empty($filter_type)) {
    $where_conditions[] = "t.type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_user)) {
    $where_conditions[] = "u.id = ?";
    $params[] = $filter_user;
}

if (!empty($filter_date)) {
    $where_conditions[] = "DATE(t.created_at) = ?";
    $params[] = $filter_date;
}

$where_clause = implode(' AND ', $where_conditions);

// Get transactions
$stmt = $conn->prepare("
    SELECT t.*, u.name as user_name, u.account_number 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE $where_clause
    ORDER BY t.created_at DESC
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get users for filter
$stmt = $conn->prepare("SELECT id, name, account_number FROM users WHERE manager_id = ? ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();

// Get statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) as total_withdrawals
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.manager_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - ATM System</title>
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
                    <h1 class="text-xl font-semibold text-gray-900">Transactions</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-user-circle text-gray-400"></i>
                        <span class="text-sm text-gray-700"><?= htmlspecialchars($manager_info['name']) ?></span>
                    </div>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800 text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Transaction History</h2>
            <p class="text-gray-600">Monitor all customer transactions under your management</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-exchange-alt text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Transactions</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_transactions']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-arrow-down text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Deposits</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= format_currency($stats['total_deposits']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="fas fa-arrow-up text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Withdrawals</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= format_currency($stats['total_withdrawals']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Filters</h3>
            </div>
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                        <select name="type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Types</option>
                            <option value="deposit" <?= $filter_type === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                            <option value="withdrawal" <?= $filter_type === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                            <option value="transfer" <?= $filter_type === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                        <select name="user" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Customers</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['account_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Transaction List</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance After</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">No transactions found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($transaction['reference']) ?></div>
                                        <div class="text-sm text-gray-500">ID: <?= $transaction['id'] ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <i class="fas fa-user text-blue-600 text-sm"></i>
                                                </div>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($transaction['user_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($transaction['account_number']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold <?= get_transaction_type_color($transaction['type']) ?>">
                                            <?= $transaction['type'] === 'withdrawal' ? '-' : '+' ?><?= format_currency($transaction['amount']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="<?= get_transaction_type_icon($transaction['type']) ?> mr-2"></i>
                                            <span class="text-sm text-gray-900"><?= ucfirst($transaction['type']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= format_currency($transaction['balance_after']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= format_date($transaction['created_at']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= get_status_badge_class($transaction['status']) ?>">
                                            <?= ucfirst($transaction['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Export Section -->
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Export Data</h3>
                    <p class="text-sm text-gray-600">Download transaction data for reporting</p>
                </div>
                <div class="space-x-3">
                    <button onclick="exportToCSV()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                    <button onclick="printTransactions()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportToCSV() {
            // Create CSV content
            const headers = ['Transaction ID', 'Reference', 'Customer', 'Account Number', 'Amount', 'Type', 'Balance After', 'Date', 'Status'];
            const rows = [
                <?php foreach ($transactions as $transaction): ?>
                    [
                        '<?= $transaction['id'] ?>',
                        '<?= htmlspecialchars($transaction['reference']) ?>',
                        '<?= htmlspecialchars($transaction['user_name']) ?>',
                        '<?= htmlspecialchars($transaction['account_number']) ?>',
                        '<?= $transaction['type'] === 'withdrawal' ? '-' : '+' ?><?= $transaction['amount'] ?>',
                        '<?= ucfirst($transaction['type']) ?>',
                        '<?= $transaction['balance_after'] ?>',
                        '<?= $transaction['created_at'] ?>',
                        '<?= ucfirst($transaction['status']) ?>'
                    ],
                <?php endforeach; ?>
            ];
            
            const csvContent = [headers, ...rows]
                .map(row => row.map(cell => `"${cell}"`).join(','))
                .join('\n');
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'transactions_<?= date('Y-m-d') ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        function printTransactions() {
            window.print();
        }
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>