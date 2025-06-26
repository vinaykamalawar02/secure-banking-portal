<?php
require_once '../includes/config.php';
require_role('admin');

$admin_info = get_admin_info($_SESSION['user_id']);
$error = '';
$success = '';

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $user_id = intval($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        
        if ($user_id && in_array($action, ['approve', 'reject'])) {
            try {
                if ($action === 'approve') {
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'");
                    $stmt->execute([$user_id]);
                    $success = 'User approved successfully!';
                    log_activity($_SESSION['user_id'], 'admin', 'Approved user ID: ' . $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND status = 'pending'");
                    $stmt->execute([$user_id]);
                    $success = 'User rejected successfully!';
                    log_activity($_SESSION['user_id'], 'admin', 'Rejected user ID: ' . $user_id);
                }
            } catch (Exception $e) {
                $error = 'Action failed. Please try again.';
            }
        } else {
            $error = 'Invalid action.';
        }
    }
}

// Get pending users
$stmt = $conn->prepare("
    SELECT u.*, m.name as manager_name 
    FROM users u 
    LEFT JOIN managers m ON u.manager_id = m.id 
    WHERE u.status = 'pending' 
    ORDER BY u.created_at DESC
");
$stmt->execute();
$pending_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals - ATM System</title>
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
                    <i class="fas fa-clock text-yellow-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-semibold text-gray-900">Pending Approvals</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-user-circle text-gray-400"></i>
                        <span class="text-sm text-gray-700"><?= htmlspecialchars($admin_info['name']) ?></span>
                    </div>
                    <a href="../logout.php" class="text-red-600 hover:text-red-800 text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Pending User Approvals</h2>
            <p class="text-gray-600">Review and approve new user registrations</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pending Users List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    Pending Users (<?= count($pending_users) ?>)
                </h3>
            </div>
            <div class="p-6">
                <?php if (empty($pending_users)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                        <p class="text-gray-500 text-lg">No pending approvals</p>
                        <p class="text-gray-400 text-sm">All user registrations have been processed</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($pending_users as $user): ?>
                            <div class="border border-gray-200 rounded-lg p-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="p-3 rounded-full bg-yellow-100">
                                            <i class="fas fa-user-clock text-yellow-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></h4>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($user['username']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                                            <p class="text-sm text-gray-500">Account: <?= htmlspecialchars($user['account_number']) ?></p>
                                            <p class="text-xs text-gray-400">Registered: <?= format_date($user['created_at']) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-medium">
                                                <i class="fas fa-check mr-1"></i>Approve
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm font-medium">
                                                <i class="fas fa-times mr-1"></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 