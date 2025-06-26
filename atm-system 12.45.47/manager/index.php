<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Ensure user is logged in as manager
if (!is_logged_in() || $_SESSION['user_role'] !== 'manager') {
    header('Location: ../unauthorized.php');
    exit();
}

// Get statistics for this manager's users
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users WHERE manager_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$total_users = $stmt->fetch()['total_users'];

$stmt = $conn->prepare("SELECT COALESCE(SUM(balance), 0) as total_balance FROM users WHERE manager_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$total_balance = $stmt->fetch()['total_balance'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as total_transactions FROM transactions t JOIN users u ON t.user_id = u.id WHERE u.manager_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_transactions = $stmt->fetch()['total_transactions'];

$stmt = $conn->prepare("SELECT COUNT(*) as pending_approvals FROM users WHERE manager_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_approvals = $stmt->fetch()['pending_approvals'];

// Get transaction data for charts
$stmt = $conn->prepare("
    SELECT DATE(t.created_at) as date, COUNT(*) as count, COALESCE(SUM(t.amount), 0) as total
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.manager_id = ? AND t.created_at >= DATE('now', '-7 days')
    GROUP BY DATE(t.created_at)
    ORDER BY date
");
$stmt->execute([$_SESSION['user_id']]);
$chart_data = $stmt->fetchAll();

// Get recent transactions
$stmt = $conn->prepare("
    SELECT t.*, u.name as user_name 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.manager_id = ?
    ORDER BY t.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_transactions = $stmt->fetchAll();

// Get recent users
$stmt = $conn->prepare("
    SELECT * FROM users 
    WHERE manager_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - ATM System</title>
    
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="../assets/css/manager.css" rel="stylesheet" />
</head>
<body>
    <!-- Navigation -->
    <nav class="manager-nav">
        <div class="nav-container">
            <div class="nav-content">
                <div class="nav-brand">
                    <div class="icon">
                        <i class="fas fa-user-tie"></i>
                        <div class="live-indicator"></div>
                    </div>
                    <div>
                        <h1>Manager Dashboard</h1>
                        <p>Branch Live</p>
                    </div>
                </div>
                
                <div class="nav-user">
                    <div class="user-info">
                        <div>
                            <div class="user-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                            <div class="user-role">Branch Manager</div>
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
                    <h2>Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
                    <p>Here's your branch overview for today</p>
                    <div class="welcome-stats">
                        <div class="stat-item">
                            <i class="fas fa-users icon"></i>
                            <span class="count"><?= $total_users ?> customers</span>
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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Customers</h3>
                        <div class="value"><?= number_format($total_users) ?></div>
                        <div class="trend positive">+8% this month</div>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Balance</h3>
                        <div class="value"><?= format_currency($total_balance) ?></div>
                        <div class="trend positive">+15% this month</div>
                    </div>
                    <div class="stat-icon yellow">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Transactions</h3>
                        <div class="value"><?= number_format($total_transactions) ?></div>
                        <div class="trend positive">+22% this month</div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending Approvals</h3>
                        <div class="value"><?= number_format($pending_approvals) ?></div>
                        <div class="trend neutral">Requires attention</div>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-grid">
            <a href="create_user.php" class="action-card">
                <div class="action-icon green">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-title">Create User</div>
                <div class="action-desc">Add new customer accounts</div>
            </a>
            
            <a href="pending_approvals.php" class="action-card">
                <div class="action-icon yellow">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="action-title">Pending Approvals</div>
                <div class="action-desc">Review account applications</div>
            </a>
            
            <a href="transactions.php" class="action-card">
                <div class="action-icon blue">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="action-title">View Transactions</div>
                <div class="action-desc">Monitor customer activity</div>
            </a>
            
            <a href="../logout.php" class="action-card">
                <div class="action-icon red">
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
                        <span class="health-label">Average Balance</span>
                        <span class="health-value positive"><?= format_currency($total_users > 0 ? $total_balance / $total_users : 0) ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Transaction Rate</span>
                        <span class="health-value positive"><?= $total_users > 0 ? round(($total_transactions / $total_users) * 100, 1) : 0 ?>%</span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Growth Rate</span>
                        <span class="health-value positive">+15.2%</span>
                    </div>
                </div>

                <!-- Smart Insights -->
                <div class="insights-card">
                    <h3 class="insights-title">Smart Insights</h3>
                    <div class="insight-item">
                        <i class="insight-icon success"></i>
                        <div class="insight-content">
                            <h4>High Activity Period</h4>
                            <p>Peak transaction time: 2-4 PM</p>
                        </div>
                    </div>
                    <div class="insight-item">
                        <i class="insight-icon info"></i>
                        <div class="insight-content">
                            <h4>Customer Growth</h4>
                            <p>8 new customers this month</p>
                        </div>
                    </div>
                    <div class="insight-item">
                        <i class="insight-icon"></i>
                        <div class="insight-content">
                            <h4>Recommendation</h4>
                            <p>Consider extending hours during peak times</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="transactions-card">
                    <div class="transactions-header">
                        <h3 class="transactions-title">Recent Transactions</h3>
                        <a href="transactions.php" class="transactions-link">View All</a>
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
                                        <div class="transaction-title"><?= htmlspecialchars($transaction['user_name']) ?></div>
                                        <div class="transaction-desc"><?= ucfirst($transaction['type']) ?> • <?= date('M j, g:i A', strtotime($transaction['created_at'])) ?></div>
                                    </div>
                                    <div class="transaction-amount <?= $transaction['type'] === 'deposit' ? 'positive' : 'negative' ?>">
                                        <?= $transaction['type'] === 'deposit' ? '+' : '-' ?><?= format_currency($transaction['amount']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="users-card">
                    <div class="users-header">
                        <h3 class="users-title">Recent Users</h3>
                        <a href="#" class="users-link">View All</a>
                    </div>
                    <div class="users-list">
                        <?php if (empty($recent_users)): ?>
                            <div class="empty-state">
                                <div class="icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <p>No recent users</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_users as $user): ?>
                                <div class="user-item">
                                    <div class="user-avatar-small">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="user-content">
                                        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                        <div class="user-account"><?= htmlspecialchars($user['username']) ?></div>
                                    </div>
                                    <div class="user-balance"><?= format_currency($user['balance']) ?></div>
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
        const chartData = <?= json_encode($chart_data) ?>;
        
        const labels = chartData.map(item => new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        const transactionCounts = chartData.map(item => item.count);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Transactions',
                    data: transactionCounts,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
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