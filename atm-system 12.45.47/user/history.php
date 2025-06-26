<?php
require_once '../includes/config.php';
require_role('user');

$user_info = get_user_info($_SESSION['user_id']);

// Get filter parameters
$type_filter = $_GET['type'] ?? '';
$date_filter = $_GET['date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ['user_id = ?'];
$params = [$_SESSION['user_id']];

if (!empty($type_filter)) {
    $where_conditions[] = 'type = ?';
    $params[] = $type_filter;
}

if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = 'DATE(created_at) = DATE("now")';
            break;
        case 'week':
            $where_conditions[] = 'created_at >= DATE("now", "-7 days")';
            break;
        case 'month':
            $where_conditions[] = 'created_at >= DATE("now", "-30 days")';
            break;
        case 'year':
            $where_conditions[] = 'created_at >= DATE("now", "-365 days")';
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE $where_clause");
$count_stmt->execute($params);
$total_transactions = $count_stmt->fetchColumn();
$total_pages = ceil($total_transactions / $per_page);

// Get transactions
$stmt = $conn->prepare("
    SELECT * FROM transactions 
    WHERE $where_clause
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get transaction statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN ABS(amount) ELSE 0 END), 0) as total_withdrawals,
        COALESCE(SUM(CASE WHEN type = 'transfer' AND amount > 0 THEN amount ELSE 0 END), 0) as total_received,
        COALESCE(SUM(CASE WHEN type = 'transfer' AND amount < 0 THEN ABS(amount) ELSE 0 END), 0) as total_sent
    FROM transactions 
    WHERE user_id = ?
");
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - ATM System</title>
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
                    <i class="fas fa-history text-purple-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-semibold text-gray-900">Transaction History</h1>
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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-full mr-3">
                        <i class="fas fa-exchange-alt text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Transactions</p>
                        <p class="text-lg font-semibold"><?= number_format($stats['total_count']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-full mr-3">
                        <i class="fas fa-arrow-down text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Deposits</p>
                        <p class="text-lg font-semibold text-green-600"><?= format_currency($stats['total_deposits']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 rounded-full mr-3">
                        <i class="fas fa-arrow-up text-red-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Withdrawals</p>
                        <p class="text-lg font-semibold text-red-600"><?= format_currency($stats['total_withdrawals']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-full mr-3">
                        <i class="fas fa-arrow-right text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Received</p>
                        <p class="text-lg font-semibold text-blue-600"><?= format_currency($stats['total_received']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-orange-100 rounded-full mr-3">
                        <i class="fas fa-arrow-left text-orange-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Sent</p>
                        <p class="text-lg font-semibold text-orange-600"><?= format_currency($stats['total_sent']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">Transaction Type</label>
                        <select name="type" id="type" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Types</option>
                            <option value="deposit" <?= $type_filter === 'deposit' ? 'selected' : '' ?>>Deposits</option>
                            <option value="withdrawal" <?= $type_filter === 'withdrawal' ? 'selected' : '' ?>>Withdrawals</option>
                            <option value="transfer" <?= $type_filter === 'transfer' ? 'selected' : '' ?>>Transfers</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <select name="date" id="date" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Time</option>
                            <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="year" <?= $date_filter === 'year' ? 'selected' : '' ?>>Last Year</option>
                        </select>
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="history.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transactions List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Transaction History</h3>
                <p class="text-sm text-gray-500">Showing <?= number_format($total_transactions) ?> transactions</p>
            </div>
            
            <?php if (empty($transactions)): ?>
                <div class="p-8 text-center">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-inbox text-4xl"></i>
                    </div>
                    <p class="text-gray-500">No transactions found</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="p-2 rounded-full <?= get_transaction_type_bg($transaction['type']) ?> bg-opacity-10 mr-3">
                                                <i class="fas fa-<?= $transaction['type'] === 'deposit' ? 'arrow-down' : ($transaction['type'] === 'withdrawal' ? 'arrow-up' : 'exchange-alt') ?> <?= get_transaction_type_color($transaction['type']) ?>"></i>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900"><?= ucfirst($transaction['type']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium <?= $transaction['amount'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $transaction['amount'] > 0 ? '+' : '' ?><?= format_currency($transaction['amount']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= format_currency($transaction['balance_after']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($transaction['description'] ?: 'No description') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y g:i A', strtotime($transaction['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($transaction['reference']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_transactions) ?> of <?= number_format($total_transactions) ?> results
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm <?= $i === $page ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 