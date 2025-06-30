<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/security.php';

// Ensure user is logged in as admin
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

// Initialize secure session
init_secure_session();

// Get real-time statistics
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

// Get today's statistics
$stmt = $conn->prepare("SELECT COUNT(*) as today_transactions FROM transactions WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$today_transactions = $stmt->fetch()['today_transactions'];

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as today_volume FROM transactions WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$today_volume = $stmt->fetch()['today_volume'];

// Get security alerts
$stmt = $conn->prepare("
    SELECT COUNT(*) as security_alerts 
    FROM security_logs 
    WHERE severity IN ('warning', 'error', 'critical') 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute();
$security_alerts = $stmt->fetch()['security_alerts'];

// Get failed login attempts
$stmt = $conn->prepare("
    SELECT COUNT(*) as failed_logins 
    FROM login_attempts 
    WHERE success = FALSE 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute();
$failed_logins = $stmt->fetch()['failed_logins'];

// Get transaction data for charts
$stmt = $conn->prepare("
    SELECT type, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM transactions 
    GROUP BY type
    ORDER BY count DESC
");
$stmt->execute();
$chart_data = $stmt->fetchAll();

// Get recent security events
$stmt = $conn->prepare("
    SELECT event_type, details, severity, created_at, user_type
    FROM security_logs 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_security_events = $stmt->fetchAll();

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

// Get system health data
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users,
        (SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = CURDATE()) as today_transactions,
        (SELECT COUNT(*) FROM security_logs WHERE severity = 'critical' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as critical_alerts
");
$stmt->execute();
$system_health = $stmt->fetch();
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
    
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .security-alerts {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-radius: 15px;
            padding: 20px;
        }
        
        .security-alerts h3 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .alert-item:last-child {
            border-bottom: none;
        }
        
        .alert-severity {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .severity-critical { background: rgba(255, 255, 255, 0.3); }
        .severity-warning { background: rgba(255, 255, 255, 0.2); }
        .severity-error { background: rgba(255, 255, 255, 0.25); }
        
        .real-time-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #00ff00;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .activity-feed {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .activity-time {
            font-size: 12px;
            color: #666;
        }
        
        .system-health {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .health-metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .health-metric:last-child {
            border-bottom: none;
        }
        
        .health-value {
            font-weight: bold;
            font-size: 18px;
        }
        
        .health-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-good { background: #d4edda; color: #155724; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-critical { background: #f8d7da; color: #721c24; }
    </style>
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
                        <p><span class="real-time-indicator"></span>System Live</p>
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
                    <p>Real-time system monitoring and security overview</p>
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
                        <h3>Today's Transactions</h3>
                        <div class="value"><?= number_format($today_transactions) ?></div>
                        <div class="trend positive">+18% vs yesterday</div>
                    </div>
                    <div class="stat-icon green">
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
                    <div class="stat-icon yellow">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Security Alerts</h3>
                        <div class="value"><?= number_format($security_alerts) ?></div>
                        <div class="trend <?= $security_alerts > 0 ? 'negative' : 'positive' ?>">
                            <?= $security_alerts > 0 ? 'Requires attention' : 'All clear' ?>
                        </div>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <div class="main-content">
                <!-- Transaction Chart -->
                <div class="chart-container">
                    <h3><i class="fas fa-chart-pie"></i> Transaction Distribution</h3>
                    <canvas id="transactionChart" width="400" height="200"></canvas>
                </div>

                <!-- Recent Activities -->
                <div class="activity-feed">
                    <h3><i class="fas fa-history"></i> Recent Activities</h3>
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?= $activity['type'] === 'user' ? 'bg-blue' : 'bg-green' ?>">
                            <i class="fas <?= $activity['type'] === 'user' ? 'fa-user-plus' : 'fa-exchange-alt' ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?= htmlspecialchars($activity['name']) ?></div>
                            <div class="activity-time"><?= htmlspecialchars($activity['action']) ?> • <?= format_time_ago($activity['time']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sidebar">
                <!-- Security Alerts -->
                <div class="security-alerts">
                    <h3><i class="fas fa-exclamation-triangle"></i> Security Alerts</h3>
                    <div class="alert-item">
                        <span>Failed Login Attempts</span>
                        <span class="alert-severity severity-<?= $failed_logins > 10 ? 'critical' : 'warning' ?>">
                            <?= $failed_logins ?>
                        </span>
                    </div>
                    <div class="alert-item">
                        <span>Security Events (24h)</span>
                        <span class="alert-severity severity-<?= $security_alerts > 5 ? 'critical' : 'warning' ?>">
                            <?= $security_alerts ?>
                        </span>
                    </div>
                    <div class="alert-item">
                        <span>System Status</span>
                        <span class="alert-severity severity-good">Online</span>
                    </div>
                </div>

                <!-- System Health -->
                <div class="system-health">
                    <h3><i class="fas fa-heartbeat"></i> System Health</h3>
                    <div class="health-metric">
                        <span>Active Users</span>
                        <div>
                            <span class="health-value"><?= $system_health['active_users'] ?></span>
                            <span class="health-status status-good">Good</span>
                        </div>
                    </div>
                    <div class="health-metric">
                        <span>Pending Approvals</span>
                        <div>
                            <span class="health-value"><?= $system_health['pending_users'] ?></span>
                            <span class="health-status status-warning">Review</span>
                        </div>
                    </div>
                    <div class="health-metric">
                        <span>Today's Transactions</span>
                        <div>
                            <span class="health-value"><?= $system_health['today_transactions'] ?></span>
                            <span class="health-status status-good">Active</span>
                        </div>
                    </div>
                    <div class="health-metric">
                        <span>Critical Alerts</span>
                        <div>
                            <span class="health-value"><?= $system_health['critical_alerts'] ?></span>
                            <span class="health-status <?= $system_health['critical_alerts'] > 0 ? 'status-critical' : 'status-good' ?>">
                                <?= $system_health['critical_alerts'] > 0 ? 'Critical' : 'Clear' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="actions-grid">
                    <a href="security_logs.php" class="action-card">
                        <div class="action-icon red">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="action-title">Security Logs</div>
                        <div class="action-desc">View security events</div>
                    </a>
                    
                    <a href="reports.php" class="action-card">
                        <div class="action-icon blue">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-title">Generate Reports</div>
                        <div class="action-desc">Export data & analytics</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update time
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString();
            document.getElementById('current-date').textContent = now.toLocaleDateString();
        }
        
        updateTime();
        setInterval(updateTime, 1000);

        // Transaction Chart
        const ctx = document.getElementById('transactionChart').getContext('2d');
        const transactionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($chart_data, 'type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($chart_data, 'count')) ?>,
                    backgroundColor: [
                        '#28a745',
                        '#dc3545',
                        '#007bff',
                        '#ffc107'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Real-time updates (simulated)
        setInterval(() => {
            // Update security alerts count
            fetch('api/security_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update security metrics
                    console.log('Security stats updated:', data);
                })
                .catch(error => console.error('Error updating stats:', error));
        }, 30000); // Update every 30 seconds
    </script>
</body>
</html> 