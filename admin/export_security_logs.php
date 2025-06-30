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

// Get export parameters
$format = $_GET['format'] ?? 'csv';
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
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$security_logs = $stmt->fetchAll();

// Log the export activity
log_security_event($_SESSION['user_id'], 'admin', 'export_security_logs', "Exported $format format with filters: " . json_encode($_GET));

if ($format === 'csv') {
    // Export as CSV
    $filename = 'security_logs_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Timestamp',
        'User Name',
        'User Type',
        'Event Type',
        'Severity',
        'Details',
        'IP Address',
        'User Agent'
    ]);
    
    // CSV data
    foreach ($security_logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['user_name'] ?? 'Unknown',
            ucfirst($log['user_type']),
            ucfirst(str_replace('_', ' ', $log['event_type'])),
            ucfirst($log['severity']),
            $log['details'] ?? '',
            $log['ip_address'],
            $log['user_agent'] ?? ''
        ]);
    }
    
    fclose($output);
    exit();
    
} elseif ($format === 'pdf') {
    // Export as PDF (requires TCPDF or similar library)
    // For now, we'll create a simple HTML report that can be printed
    $filename = 'security_logs_' . date('Y-m-d_H-i-s') . '.html';
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Logs Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .report-info {
            margin-bottom: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .severity-info { background: #d1ecf1; }
        .severity-warning { background: #fff3cd; }
        .severity-error { background: #f8d7da; }
        .severity-critical { background: #f5c6cb; }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ATM System Security Logs Report</h1>
        <p>Generated on <?= date('F d, Y \a\t H:i:s') ?></p>
    </div>
    
    <div class="report-info">
        <h3>Report Summary</h3>
        <p><strong>Total Records:</strong> <?= count($security_logs) ?></p>
        <p><strong>Date Range:</strong> 
            <?= $date_from ? date('M d, Y', strtotime($date_from)) : 'All dates' ?> 
            to 
            <?= $date_to ? date('M d, Y', strtotime($date_to)) : 'All dates' ?>
        </p>
        <?php if ($severity_filter): ?>
        <p><strong>Severity Filter:</strong> <?= ucfirst($severity_filter) ?></p>
        <?php endif; ?>
        <?php if ($event_type_filter): ?>
        <p><strong>Event Type Filter:</strong> <?= ucfirst(str_replace('_', ' ', $event_type_filter)) ?></p>
        <?php endif; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User Name</th>
                <th>User Type</th>
                <th>Event Type</th>
                <th>Severity</th>
                <th>Details</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($security_logs as $log): ?>
            <tr>
                <td><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                <td><?= htmlspecialchars($log['user_name'] ?? 'Unknown') ?></td>
                <td><?= ucfirst($log['user_type']) ?></td>
                <td><?= ucfirst(str_replace('_', ' ', $log['event_type'])) ?></td>
                <td class="severity-<?= $log['severity'] ?>"><?= ucfirst($log['severity']) ?></td>
                <td><?= htmlspecialchars($log['details'] ?? '') ?></td>
                <td><?= htmlspecialchars($log['ip_address']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>This report was generated by the ATM System Security Module</p>
        <p>For questions or concerns, please contact the system administrator</p>
    </div>
    
    <div class="no-print" style="margin-top: 30px; text-align: center;">
        <button onclick="window.print()">Print Report</button>
        <button onclick="window.close()">Close</button>
    </div>
</body>
</html>
    <?php
    exit();
    
} else {
    // Invalid format
    header('Location: security_logs.php?error=invalid_format');
    exit();
}
?> 