<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Ensure user is logged in
if (!is_logged_in() || $_SESSION['user_role'] !== 'user') {
    header('Location: ../unauthorized.php');
    exit();
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get transaction statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) as total_withdrawals
    FROM transactions 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Calculate spending insights
$total_spending = $stats['total_withdrawals'] ?? 0;
$total_deposits = $stats['total_deposits'] ?? 0;
$total_transactions = $stats['total_transactions'] ?? 0;

$avg_daily_spending = $total_spending > 0 ? $total_spending / max(1, $total_transactions) : 0;
$savings_rate = $total_deposits > 0 ? (($total_deposits - $total_spending) / $total_deposits) * 100 : 0;

// Get monthly spending data for charts
$stmt = $conn->prepare("
    SELECT 
        DATE(created_at) as date,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) as spending,
        COUNT(*) as transactions
    FROM transactions 
    WHERE user_id = ? AND created_at >= DATE('now', '-30 days')
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$_SESSION['user_id']]);
$spending_data = $stmt->fetchAll();

// Get recent transactions
$stmt = $conn->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_transactions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ATM System</title>
    
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="../assets/css/user.css" rel="stylesheet" />
</head>
<body>
    <!-- Navigation -->
    <nav class="user-nav">
        <div class="nav-container">
            <div class="nav-content">
                <div class="nav-brand">
                    <div class="icon">
                        <i class="fas fa-university"></i>
                        <div class="live-indicator"></div>
                    </div>
                    <div>
                        <h1>Customer Portal</h1>
                        <p>Account Active</p>
                    </div>
                </div>
                
                <div class="nav-user">
                    <div class="user-info">
                        <div>
                            <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                            <div class="user-role">Customer</div>
                        </div>
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h2>Welcome back, <?= htmlspecialchars($user['name']) ?>!</h2>
                    <p>Here's your financial overview for today</p>
                    <div class="welcome-stats">
                        <div class="stat-item">
                            <i class="fas fa-credit-card icon"></i>
                            <span class="account"><?= htmlspecialchars($user['username']) ?></span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-clock icon"></i>
                            <span class="time" id="current-time"></span>
                        </div>
                    </div>
                </div>
                <div class="welcome-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>

        <!-- Balance Card -->
        <div class="balance-card">
            <div class="balance-content">
                <div class="balance-info">
                    <h3>Current Balance</h3>
                    <div class="amount"><?= format_currency($user['balance']) ?></div>
                    <div class="balance-status">
                        <div class="status-item">
                            <div class="status-indicator"></div>
                            <span class="status-text">Account Active</span>
                        </div>
                        <div class="status-item">
                            <i class="fas fa-shield-alt"></i>
                            <span class="status-text">Secure</span>
                        </div>
                    </div>
                </div>
                <div class="balance-icon">
                    <i class="fas fa-dollar-sign"></i>
                    <div class="check">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Deposits</h3>
                        <div class="value"><?= format_currency($stats['total_deposits']) ?></div>
                        <div class="trend positive">+15% this month</div>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Withdrawals</h3>
                        <div class="value"><?= format_currency($stats['total_withdrawals']) ?></div>
                        <div class="trend neutral">This month</div>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Transactions</h3>
                        <div class="value"><?= number_format($stats['total_transactions']) ?></div>
                        <div class="trend positive">+22% this month</div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-grid">
            <a href="withdraw.php" class="action-card">
                <div class="action-icon red">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="action-title">Withdraw Money</div>
                <div class="action-desc">Withdraw cash from your account</div>
            </a>
            
            <a href="transfer.php" class="action-card">
                <div class="action-icon blue">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="action-title">Transfer Money</div>
                <div class="action-desc">Send money to other users</div>
            </a>
            
            <a href="history.php" class="action-card">
                <div class="action-icon purple">
                    <i class="fas fa-history"></i>
                </div>
                <div class="action-title">Transaction History</div>
                <div class="action-desc">View all your transactions</div>
            </a>
            
            <a href="../logout.php" class="action-card">
                <div class="action-icon gray">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="action-title">System Logout</div>
                <div class="action-desc">Secure logout from system</div>
            </a>
        </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Financial Health -->
                <div class="health-card">
                    <h3 class="health-title">Financial Health</h3>
                    <div class="health-item">
                        <span class="health-label">Average Daily Spending</span>
                        <span class="health-value neutral"><?= format_currency($avg_daily_spending) ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Savings Rate</span>
                        <span class="health-value positive"><?= round($savings_rate, 1) ?>%</span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Transaction Frequency</span>
                        <span class="health-value neutral"><?= $total_transactions ?> total</span>
                    </div>
                </div>

                <!-- Smart Insights -->
                <div class="insights-card">
                    <h3 class="insights-title">Smart Insights</h3>
                    <div class="insight-item">
                        <i class="insight-icon success"></i>
                        <div class="insight-content">
                            <h4>Good Savings Rate</h4>
                            <p>You're saving <?= round($savings_rate, 1) ?>% of your deposits</p>
                        </div>
                    </div>
                    <div class="insight-item">
                        <i class="insight-icon info"></i>
                        <div class="insight-content">
                            <h4>Spending Pattern</h4>
                            <p>Average daily spending: <?= format_currency($avg_daily_spending) ?></p>
                        </div>
                    </div>
                    <div class="insight-item">
                        <i class="insight-icon"></i>
                        <div class="insight-content">
                            <h4>Recommendation</h4>
                            <p>Consider setting up automatic savings</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="transactions-card">
                    <div class="transactions-header">
                        <h3 class="transactions-title">Recent Transactions</h3>
                        <a href="#" class="transactions-link">View All</a>
                    </div>
                    <div class="transactions-list">
                        <?php if (empty($recent_transactions)): ?>
                            <div class="empty-state">
                                <div class="icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <p>No recent transactions</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_transactions as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="transaction-icon <?= $transaction['type'] ?>">
                                        <i class="fas fa-<?= $transaction['type'] === 'deposit' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                    </div>
                                    <div class="transaction-content">
                                        <div class="transaction-title"><?= ucfirst($transaction['type']) ?></div>
                                        <div class="transaction-desc"><?= date('M j, g:i A', strtotime($transaction['created_at'])) ?></div>
                                    </div>
                                    <div class="transaction-amount <?= $transaction['type'] === 'deposit' ? 'positive' : 'negative' ?>">
                                        <?= $transaction['type'] === 'deposit' ? '+' : '-' ?><?= format_currency($transaction['amount']) ?>
                                    </div>
                                </div>
                                <div class="transaction-balance">
                                    Balance: <?= format_currency($transaction['balance_after']) ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update time and date
        function updateDateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString();
        }
        
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Transaction Chart
        const ctx = document.getElementById('transactionChart').getContext('2d');
        const chartData = <?= json_encode($spending_data) ?>;
        
        const labels = chartData.map(item => new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        const transactionAmounts = chartData.map(item => item.spending);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Spending',
                    data: transactionAmounts,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>