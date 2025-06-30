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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$severity_filter = $_GET['severity'] ?? '';
$event_type_filter = $_GET['event_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($severity_filter) {
    $where_conditions[] = "severity = ?";
    $params[] = $severity_filter;
}

if ($event_type_filter) {
    $where_conditions[] = "event_type = ?";
    $params[] = $event_type_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM security_logs $where_clause";
$stmt = $conn->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get security logs
$query = "
    SELECT sl.*, 
           CASE 
               WHEN sl.user_type = 'admin' THEN (SELECT name FROM admin WHERE id = sl.user_id)
               WHEN sl.user_type = 'manager' THEN (SELECT name FROM managers WHERE id = sl.user_id)
               WHEN sl.user_type = 'user' THEN (SELECT name FROM users WHERE id = sl.user_id)
               ELSE 'Unknown'
           END as user_name
    FROM security_logs sl
    $where_clause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$security_logs = $stmt->fetchAll();

// Get unique event types for filter
$stmt = $conn->prepare("SELECT DISTINCT event_type FROM security_logs ORDER BY event_type");
$stmt->execute();
$event_types = $stmt->fetchAll();

// Get severity statistics
$stmt = $conn->prepare("
    SELECT severity, COUNT(*) as count
    FROM security_logs 
    WHERE created_at >= datetime('now', '-30 days')
    GROUP BY severity
");
$stmt->execute();
$severity_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Logs - Admin Dashboard</title>
    
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet" />
    
    <style>
        .security-logs-container {
            padding: 20px;
        }
        
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .logs-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table-content {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .log-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }
        
        .log-row:hover {
            background: #f8f9fa;
        }
        
        .log-row:last-child {
            border-bottom: none;
        }
        
        .severity-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }
        
        .severity-info { background: #d1ecf1; color: #0c5460; }
        .severity-warning { background: #fff3cd; color: #856404; }
        .severity-error { background: #f8d7da; color: #721c24; }
        .severity-critical { background: #f5c6cb; color: #721c24; }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background: #f8f9fa;
        }
        
        .pagination .active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
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
                    </div>
                    <div>
                        <h1>Security Logs</h1>
                        <p>Monitor system security events</p>
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

    <div class="security-logs-container">
        <!-- Statistics -->
        <div class="stats-grid">
            <?php foreach ($severity_stats as $stat): ?>
            <div class="stat-card">
                <div class="stat-value"><?= $stat['count'] ?></div>
                <div class="stat-label"><?= ucfirst($stat['severity']) ?> Events (30 days)</div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <h3><i class="fas fa-filter"></i> Filter Security Logs</h3>
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label for="severity">Severity</label>
                    <select name="severity" id="severity">
                        <option value="">All Severities</option>
                        <option value="info" <?= $severity_filter === 'info' ? 'selected' : '' ?>>Info</option>
                        <option value="warning" <?= $severity_filter === 'warning' ? 'selected' : '' ?>>Warning</option>
                        <option value="error" <?= $severity_filter === 'error' ? 'selected' : '' ?>>Error</option>
                        <option value="critical" <?= $severity_filter === 'critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="event_type">Event Type</label>
                    <select name="event_type" id="event_type">
                        <option value="">All Events</option>
                        <?php foreach ($event_types as $type): ?>
                        <option value="<?= $type['event_type'] ?>" <?= $event_type_filter === $type['event_type'] ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $type['event_type'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date_from">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="<?= $date_from ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_to">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="<?= $date_to ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="security_logs.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <a href="export_security_logs.php?<?= http_build_query($_GET) ?>" class="btn btn-success">
                        <i class="fas fa-download"></i> Export
                    </a>
                </div>
            </form>
        </div>

        <!-- Security Logs Table -->
        <div class="logs-table">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Security Events (<?= $total_records ?> total)</h3>
            </div>
            
            <div class="table-content">
                <div class="log-row" style="font-weight: bold; background: #f8f9fa;">
                    <div>Timestamp</div>
                    <div>User</div>
                    <div>Event Type</div>
                    <div>Severity</div>
                    <div>Details</div>
                    <div>IP Address</div>
                </div>
                
                <?php foreach ($security_logs as $log): ?>
                <div class="log-row">
                    <div><?= format_date($log['created_at']) ?></div>
                    <div>
                        <?= htmlspecialchars($log['user_name'] ?? 'Unknown') ?>
                        <br>
                        <small>(<?= ucfirst($log['user_type']) ?>)</small>
                    </div>
                    <div><?= ucfirst(str_replace('_', ' ', $log['event_type'])) ?></div>
                    <div>
                        <span class="severity-badge severity-<?= $log['severity'] ?>">
                            <?= ucfirst($log['severity']) ?>
                        </span>
                    </div>
                    <div><?= htmlspecialchars($log['details'] ?? '') ?></div>
                    <div><?= htmlspecialchars($log['ip_address']) ?></div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($security_logs)): ?>
                <div class="log-row">
                    <div colspan="6" style="text-align: center; color: #666; padding: 40px;">
                        <i class="fas fa-info-circle"></i> No security events found matching your criteria.
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                   class="<?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html> 