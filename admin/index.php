<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Ensure user is logged in as admin
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users WHERE status = 'active'");
$stmt->execute();
$total_users = $stmt->fetch()['total_users'];

$stmt = $conn->prepare("SELECT COUNT(*) as total_managers FROM managers WHERE status = 'active'");
$stmt->execute();
$total_managers = $stmt->fetch()['total_managers'];

$stmt = $conn->prepare("SELECT COUNT(*) as total_transactions FROM transactions");
$stmt->execute();
$total_transactions = $stmt->fetch()['total_transactions'];

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_volume FROM transactions");
$stmt->execute();
$total_volume = $stmt->fetch()['total_volume'];

// Get transaction data for charts
$stmt = $conn->prepare("
    SELECT type, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM transactions 
    GROUP BY type
    ORDER BY count DESC
");
$stmt->execute();
$chart_data = $stmt->fetchAll();

// Get recent activities
$stmt = $conn->prepare("
    SELECT 'user' as type, name, 'User registered' as action, created_at as time
    FROM users 
    WHERE created_at >= DATE('now', '-7 days')
    UNION ALL
    SELECT 'transaction' as type, 'Transaction' as name, CONCAT(type, ' - $', amount) as action, created_at as time
    FROM transactions 
    WHERE created_at >= DATE('now', '-7 days')
    ORDER BY time DESC
    LIMIT 10
");
$stmt->execute();
$recent_activities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ATM System</title>
    
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet" />
</head>
<body>
    <!-- Navigation -->
    <nav class="admin-nav">
        <div class="nav-container">
            <div class="nav-content">
                <div class="nav-brand">
                    <div class="icon">
                        <i class="fas fa-shield-alt"></i>
                        <div class="live-indicator"></div>
                    </div>
                    <div>
                        <h1>Admin Dashboard</h1>
                        <p>System Live</p>
                    </div>
                </div>
                
                <div class="nav-user">
                    <div class="user-info">
                        <div>
                            <div class="user-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                            <div class="user-role">Administrator</div>
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
                    <p>Here's what's happening with your ATM system today</p>
                    <div class="welcome-stats">
                        <div class="stat-item">
                            <i class="fas fa-clock icon"></i>
                            <span class="time" id="current-time"></span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-calendar icon"></i>
                            <span class="time" id="current-date"></span>
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
                        <h3>Total Users</h3>
                        <div class="value"><?= number_format($total_users) ?></div>
                        <div class="trend positive">+12% this month</div>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Managers</h3>
                        <div class="value"><?= number_format($total_managers) ?></div>
                        <div class="trend positive">+5% this month</div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Transactions</h3>
                        <div class="value"><?= number_format($total_transactions) ?></div>
                        <div class="trend positive">+18% this month</div>
                    </div>
                    <div class="stat-icon yellow">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Transaction Volume</h3>
                        <div class="value"><?= format_currency($total_volume) ?></div>
                        <div class="trend positive">+25% this month</div>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-dollar-sign"></i>
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
            
            <a href="managers.php" class="action-card">
                <div class="action-icon blue">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="action-title">Manage Managers</div>
                <div class="action-desc">View and manage manager accounts</div>
            </a>
            
            <a href="pending_approvals.php" class="action-card">
                <div class="action-icon yellow">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="action-title">Pending Approvals</div>
                <div class="action-desc">Review account applications</div>
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
                <!-- System Health -->
                <div class="health-card">
                    <h3 class="health-title">System Health</h3>
                    <div class="health-item">
                        <span class="health-label">Database</span>
                        <div class="health-status healthy">
                            <div class="health-indicator healthy"></div>
                            Operational
                        </div>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Security</span>
                        <div class="health-status healthy">
                            <div class="health-indicator healthy"></div>
                            Secure
                        </div>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Performance</span>
                        <div class="health-status healthy">
                            <div class="health-indicator healthy"></div>
                            Optimal
                        </div>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Uptime</span>
                        <div class="health-status healthy">
                            <div class="health-indicator healthy"></div>
                            99.9%
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="activities-card">
                    <div class="activities-header">
                        <h3 class="activities-title">Recent Activities</h3>
                        <a href="#" class="activities-link">View All</a>
                    </div>
                    <div class="activities-list">
                        <?php if (empty($recent_activities)): ?>
                            <div class="empty-state">
                                <div class="icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <p>No recent activities</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($recent_activities, 0, 5) as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?= $activity['type'] ?>">
                                        <i class="fas fa-<?= $activity['type'] === 'user' ? 'user' : 'exchange-alt' ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?= htmlspecialchars($activity['name']) ?></div>
                                        <div class="activity-desc"><?= htmlspecialchars($activity['action']) ?></div>
                                    </div>
                                    <div class="activity-time">
                                        <?= format_time_ago($activity['time']) ?>
                                    </div>
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
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Transaction Chart
        const ctx = document.getElementById('transactionChart').getContext('2d');
        const chartData = <?= json_encode($chart_data) ?>;
        
        const labels = chartData.map(item => item.type.charAt(0).toUpperCase() + item.type.slice(1));
        const transactionCounts = chartData.map(item => item.count);
        const colors = ['#2563eb', '#16a34a', '#dc2626', '#ea580c', '#7c3aed', '#0891b2'];

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: transactionCounts,
                    backgroundColor: colors.slice(0, labels.length),
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2000,
                    easing: 'easeOutQuart'
                }
            }
        });
    </script>
</body>
</html>