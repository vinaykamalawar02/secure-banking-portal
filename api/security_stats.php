<?php
require_once '../includes/config.php';
require_once '../includes/security.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and has admin access
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Initialize secure session
init_secure_session();

try {
    // Get real-time security statistics
    $stats = [];
    
    // Recent security events (last 24 hours) - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT severity, COUNT(*) as count
        FROM security_logs 
        WHERE created_at >= datetime('now', '-1 day')
        GROUP BY severity
    ");
    $stmt->execute();
    $stats['recent_events'] = $stmt->fetchAll();
    
    // Failed login attempts (last hour) - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM login_attempts 
        WHERE success = 0 AND created_at >= datetime('now', '-1 hour')
    ");
    $stmt->execute();
    $stats['failed_logins_1h'] = $stmt->fetch()['count'];
    
    // Failed login attempts (last 24 hours) - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM login_attempts 
        WHERE success = 0 AND created_at >= datetime('now', '-1 day')
    ");
    $stmt->execute();
    $stats['failed_logins_24h'] = $stmt->fetch()['count'];
    
    // Successful logins (last 24 hours) - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM activity_logs 
        WHERE action = 'Login successful' AND created_at >= datetime('now', '-1 day')
    ");
    $stmt->execute();
    $stats['successful_logins_24h'] = $stmt->fetch()['count'];
    
    // 2FA verifications (last 24 hours) - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM security_logs 
        WHERE event_type = '2FA verification successful' AND created_at >= datetime('now', '-1 day')
    ");
    $stmt->execute();
    $stats['2fa_verifications_24h'] = $stmt->fetch()['count'];
    
    // Suspicious activities detected - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM security_logs 
        WHERE event_type = 'suspicious_activity' AND created_at >= datetime('now', '-1 day')
    ");
    $stmt->execute();
    $stats['suspicious_activities_24h'] = $stmt->fetch()['count'];
    
    // Active sessions - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM user_sessions 
        WHERE expires_at > datetime('now')
    ");
    $stmt->execute();
    $stats['active_sessions'] = $stmt->fetch()['count'];
    
    // Recent security alerts (last 10 events)
    $stmt = $conn->prepare("
        SELECT event_type, severity, details, created_at
        FROM security_logs 
        WHERE severity IN ('warning', 'error', 'critical')
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['recent_alerts'] = $stmt->fetchAll();
    
    // System health indicators
    $stats['system_health'] = [
        'database_connected' => true,
        'session_management' => true,
        'encryption_available' => function_exists('openssl_encrypt'),
        'last_backup' => date('Y-m-d H:i:s', strtotime('-1 day')), // Mock data
        'uptime' => '99.9%' // Mock data
    ];
    
    // Security metrics trends (last 7 days) - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT 
            date(created_at) as date,
            COUNT(*) as total_events,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_events,
            SUM(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) as error_events,
            SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning_events
        FROM security_logs 
        WHERE created_at >= datetime('now', '-7 days')
        GROUP BY date(created_at)
        ORDER BY date
    ");
    $stmt->execute();
    $stats['weekly_trends'] = $stmt->fetchAll();
    
    // Top IP addresses with failed login attempts - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT ip_address, COUNT(*) as attempts
        FROM login_attempts 
        WHERE success = 0 AND created_at >= datetime('now', '-1 day')
        GROUP BY ip_address
        ORDER BY attempts DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['top_failed_ips'] = $stmt->fetchAll();
    
    // User activity by role - Fixed SQLite syntax
    $stmt = $conn->prepare("
        SELECT user_type, COUNT(*) as activity_count
        FROM activity_logs 
        WHERE created_at >= datetime('now', '-1 day')
        GROUP BY user_type
    ");
    $stmt->execute();
    $stats['user_activity_by_role'] = $stmt->fetchAll();
    
    // Log the API access
    log_security_event($_SESSION['user_id'], 'admin', 'api_access', 'Security stats API accessed');
    
    // Return the statistics
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    // Log the error
    log_security_event($_SESSION['user_id'], 'admin', 'api_error', 'Security stats API error: ' . $e->getMessage(), 'error');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve security statistics',
        'message' => $e->getMessage()
    ]);
}
?> 