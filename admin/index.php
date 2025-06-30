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

// Get basic statistics (optimized queries)
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

// Get security statistics (optimized with LIMIT)
$stmt = $conn->prepare("SELECT COUNT(*) as security_events FROM security_logs WHERE created_at >= datetime('now', '-1 day') LIMIT 1000");
$stmt->execute();
$security_events_24h = $stmt->fetch()['security_events'];

$stmt = $conn->prepare("SELECT COUNT(*) as failed_logins FROM login_attempts WHERE success = 0 AND created_at >= datetime('now', '-1 day') LIMIT 1000");
$stmt->execute();
$failed_logins_24h = $stmt->fetch()['failed_logins'];

$stmt = $conn->prepare("SELECT COUNT(*) as active_sessions FROM user_sessions WHERE expires_at > datetime('now') LIMIT 1000");
$stmt->execute();
$active_sessions = $stmt->fetch()['active_sessions'];

$stmt = $conn->prepare("SELECT COUNT(*) as suspicious_activities FROM security_logs WHERE event_type = 'suspicious_activity' AND created_at >= datetime('now', '-1 day') LIMIT 1000");
$stmt->execute();
$suspicious_activities = $stmt->fetch()['suspicious_activities'];

// Get recent activities - Optimized with smaller time window and LIMIT
$stmt = $conn->prepare("
    SELECT 'user' as type, name, 'User registered' as action, created_at as time
    FROM users 
    WHERE created_at >= datetime('now', '-3 days')
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_users = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT 'transaction' as type, 'Transaction' as name, type || ' - $' || amount as action, created_at as time
    FROM transactions 
    WHERE created_at >= datetime('now', '-3 days')
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_transactions = $stmt->fetchAll();

// Combine recent activities
$recent_activities = array_merge($recent_users, $recent_transactions);
usort($recent_activities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});
$recent_activities = array_slice($recent_activities, 0, 8);

// Get recent security alerts (optimized with LIMIT)
$stmt = $conn->prepare("
    SELECT event_type, severity, details, created_at
    FROM security_logs 
    WHERE severity IN ('warning', 'error', 'critical')
    ORDER BY created_at DESC
    LIMIT 3
");
$stmt->execute();
$recent_alerts = $stmt->fetchAll();

// Get security events by severity (last 7 days) - Optimized with LIMIT
$stmt = $conn->prepare("
    SELECT severity, COUNT(*) as count
    FROM security_logs 
    WHERE created_at >= datetime('now', '-7 days')
    GROUP BY severity
    LIMIT 10
");
$stmt->execute();
$security_severity_data = $stmt->fetchAll();

// Check if charts should be loaded (AJAX request)
$load_charts = isset($_GET['load_charts']) && $_GET['load_charts'] === 'true';

if ($load_charts) {
    // Return JSON for AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'security_severity_data' => $security_severity_data
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ATM System</title>
    
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Chart.js (lazy loaded) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    
    <!-- Custom CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet" />
    
    <style>
        .security-alerts {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .security-alerts h3 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .alert-item:last-child {
            margin-bottom: 0;
        }
        
        .alert-severity {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .severity-critical { background: #ff4757; }
        .severity-error { background: #ff6348; }
        .severity-warning { background: #ffa502; }
        
        .security-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .security-stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 4px solid #007bff;
        }
        
        .security-stat-card.warning {
            border-left-color: #ffc107;
        }
        
        .security-stat-card.danger {
            border-left-color: #dc3545;
        }
        
        .security-stat-card.success {
            border-left-color: #28a745;
        }
        
        .security-stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .security-stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            min-height: 400px;
        }
        
        .chart-title {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .real-time-indicator {
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .chart-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 300px;
            color: #666;
        }
        
        .chart-loading i {
            margin-right: 10px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive design for better performance */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .security-stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
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

        <!-- Security Alerts -->
        <?php if (!empty($recent_alerts)): ?>
        <div class="security-alerts">
            <h3>
                <i class="fas fa-exclamation-triangle"></i>
                Recent Security Alerts
            </h3>
            <?php foreach ($recent_alerts as $alert): ?>
            <div class="alert-item">
                <span class="alert-severity severity-<?= $alert['severity'] ?>">
                    <?= ucfirst($alert['severity']) ?>
                </span>
                <strong><?= ucfirst(str_replace('_', ' ', $alert['event_type'])) ?></strong>
                <?php if ($alert['details']): ?>
                - <?= htmlspecialchars($alert['details']) ?>
                <?php endif; ?>
                <br>
                <small><?= format_time_ago($alert['created_at']) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Security Statistics -->
        <div class="security-stats">
            <div class="security-stat-card <?= $security_events_24h > 10 ? 'warning' : 'success' ?>">
                <div class="security-stat-value"><?= $security_events_24h ?></div>
                <div class="security-stat-label">Security Events (24h)</div>
            </div>
            
            <div class="security-stat-card <?= $failed_logins_24h > 5 ? 'danger' : 'success' ?>">
                <div class="security-stat-value"><?= $failed_logins_24h ?></div>
                <div class="security-stat-label">Failed Logins (24h)</div>
            </div>
            
            <div class="security-stat-card success">
                <div class="security-stat-value"><?= $active_sessions ?></div>
                <div class="security-stat-label">Active Sessions</div>
            </div>
            
            <div class="security-stat-card <?= $suspicious_activities > 0 ? 'danger' : 'success' ?>">
                <div class="security-stat-value"><?= $suspicious_activities ?></div>
                <div class="security-stat-label">Suspicious Activities</div>
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

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Charts Section -->
            <div>
                <div class="chart-container" style="margin-top: 20px;">
                    <div class="chart-title">
                        <i class="fas fa-shield-alt"></i>
                        Security Events by Severity (7 days)
                    </div>
                    <div class="chart-loading" id="security-chart-loading">
                        <i class="fas fa-spinner"></i>
                        Loading security data...
                    </div>
                    <canvas id="securityChart" height="300" style="display: none;"></canvas>
                </div>
            </div>
            
            <!-- Quick Actions & Recent Activities -->
            <div>
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
                    
                    <a href="security_logs.php" class="action-card">
                        <div class="action-icon red">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="action-title">Security Logs</div>
                        <div class="action-desc">Monitor security events</div>
                    </a>
                    
                    <a href="pending_approvals.php" class="action-card">
                        <div class="action-icon yellow">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="action-title">Pending Approvals</div>
                        <div class="action-desc">Review account requests</div>
                    </a>
                </div>

                <!-- Recent Activities -->
                <div class="recent-activities">
                    <h3><i class="fas fa-history"></i> Recent Activities</h3>
                    <div class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-<?= $activity['type'] === 'user' ? 'user' : 'exchange-alt' ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?= htmlspecialchars($activity['name']) ?></div>
                                <div class="activity-desc"><?= htmlspecialchars($activity['action']) ?></div>
                                <div class="activity-time"><?= format_time_ago($activity['time']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
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

        // Lazy load charts for better performance
        function loadCharts() {
            // Show loading state
            const securityLoading = document.getElementById('security-chart-loading');
            
            if (securityLoading) securityLoading.style.display = 'flex';
            
            // Load chart data via AJAX
            fetch('index.php?load_charts=true')
                .then(response => response.json())
                .then(data => {
                    // Hide loading indicators
                    if (securityLoading) securityLoading.style.display = 'none';
                    
                    // Show chart canvases
                    const securityCanvas = document.getElementById('securityChart');
                    
                    if (securityCanvas) securityCanvas.style.display = 'block';
                    
                    // Initialize Security Chart
                    if (securityCanvas && data.security_severity_data) {
                        const securityCtx = securityCanvas.getContext('2d');
                        new Chart(securityCtx, {
                            type: 'bar',
                            data: {
                                labels: data.security_severity_data.map(item => item.severity),
                                datasets: [{
                                    label: 'Security Events',
                                    data: data.security_severity_data.map(item => item.count),
                                    backgroundColor: [
                                        '#17a2b8',
                                        '#ffc107',
                                        '#dc3545',
                                        '#6f42c1'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                }
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading charts:', error);
                    // Show error message
                    if (securityLoading) {
                        securityLoading.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed to load chart data';
                    }
                });
        }

        // Load charts after page is fully loaded with delay
        window.addEventListener('load', function() {
            // Delay chart loading for better initial page performance
            setTimeout(loadCharts, 1000);
        });

        // Real-time updates (optimized - less frequent for better performance)
        function updateSecurityStats() {
            fetch('../api/security_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Security stats updated:', data.data);
                    }
                })
                .catch(error => console.error('Error updating security stats:', error));
        }

        // Update every 60 seconds instead of 30 for better performance
        setInterval(updateSecurityStats, 60000);
        
        // Initial update after 10 seconds
        setTimeout(updateSecurityStats, 10000);
    </script>
</body>
</html>