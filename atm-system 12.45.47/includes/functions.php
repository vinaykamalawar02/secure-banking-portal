<?php
// Common utility functions

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function redirect($location) {
    header("Location: " . $location);
    exit();
}

function display_error($message) {
    return '<div class="alert alert-danger">' . $message . '</div>';
}

function display_success($message) {
    return '<div class="alert alert-success">' . $message . '</div>';
}

function format_date($date) {
    return date('M d, Y H:i', strtotime($date));
}

function format_time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

function format_currency($amount) {
    // Handle null, undefined, or empty values
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        $amount = 0;
    }
    return '$' . number_format((float)$amount, 2);
}

function get_user_balance($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['balance'] : 0;
}

function log_activity($user_id, $user_type, $action) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_type, action, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        $user_type,
        $action,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

function update_user_balance($user_id, $amount) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    return $stmt->execute([$amount, $user_id]);
}

function create_transaction($user_id, $amount, $type, $description = '') {
    global $conn;
    
    // Get current balance
    $current_balance = get_user_balance($user_id);
    $new_balance = $current_balance + $amount;
    
    // Update user balance
    if (update_user_balance($user_id, $amount)) {
        // Create transaction record
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, balance_after, description, reference) VALUES (?, ?, ?, ?, ?, ?)");
        $reference = 'TXN' . date('YmdHis') . rand(1000, 9999);
        return $stmt->execute([$user_id, $amount, $type, $new_balance, $description, $reference]);
    }
    
    return false;
}

function get_user_transactions($user_id, $limit = 10) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

function get_user_info($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function get_manager_info($manager_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM managers WHERE id = ?");
    $stmt->execute([$manager_id]);
    return $stmt->fetch();
}

function get_admin_info($admin_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
    $stmt->execute([$admin_id]);
    return $stmt->fetch();
}

function generate_account_number() {
    return 'ACC' . date('Y') . rand(100000, 999999);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $phone);
}

function get_status_badge_class($status) {
    switch ($status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'inactive':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function get_transaction_type_icon($type) {
    switch ($type) {
        case 'deposit':
            return 'fas fa-arrow-down text-green-600';
        case 'withdrawal':
            return 'fas fa-arrow-up text-red-600';
        case 'transfer':
            return 'fas fa-exchange-alt text-blue-600';
        default:
            return 'fas fa-circle text-gray-600';
    }
}

function get_transaction_type_color($type) {
    switch ($type) {
        case 'deposit':
            return 'text-green-600';
        case 'withdrawal':
            return 'text-red-600';
        case 'transfer':
            return 'text-blue-600';
        default:
            return 'text-gray-600';
    }
}

function get_transaction_type_bg($type) {
    switch ($type) {
        case 'deposit':
            return 'bg-green-500';
        case 'withdrawal':
            return 'bg-red-500';
        case 'transfer':
            return 'bg-blue-500';
        default:
            return 'bg-gray-500';
    }
}

// User Management Functions
function create_user_account($data) {
    global $conn;
    
    try {
        $conn->beginTransaction();
        
        // Generate account number
        $account_number = generate_account_number();
        
        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Create user
        $stmt = $conn->prepare("
            INSERT INTO users (name, username, password, email, phone, account_number, balance, manager_id, admin_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $data['name'],
            $data['username'],
            $hashed_password,
            $data['email'],
            $data['phone'] ?? '',
            $account_number,
            $data['initial_balance'] ?? 0,
            $data['manager_id'] ?? null,
            $data['admin_id'] ?? null
        ]);
        
        $user_id = $conn->lastInsertId();
        
        // If initial balance > 0, create deposit transaction
        if (($data['initial_balance'] ?? 0) > 0) {
            $stmt = $conn->prepare("
                INSERT INTO transactions (user_id, amount, type, balance_after, description, reference) 
                VALUES (?, ?, 'deposit', ?, 'Initial deposit', ?)
            ");
            $reference = 'TXN' . date('YmdHis') . rand(1000, 9999);
            $stmt->execute([$user_id, $data['initial_balance'], $data['initial_balance'], $reference]);
        }
        
        $conn->commit();
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'account_number' => $account_number
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        return [
            'success' => false,
            'error' => 'Failed to create user account: ' . $e->getMessage()
        ];
    }
}

function check_username_exists($username) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch() !== false;
}

function check_email_exists($email) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch() !== false;
}

// Statistics Functions
function get_total_users() {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

function get_total_managers() {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM managers WHERE status = 'active'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

function get_total_transactions() {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

function get_total_balance() {
    global $conn;
    $stmt = $conn->prepare("SELECT SUM(balance) as total FROM users WHERE status = 'active'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

function get_recent_activities($limit = 10) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT * FROM activity_logs 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function get_recent_transactions($limit = 10) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT t.*, u.name as user_name, u.account_number 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}
?>