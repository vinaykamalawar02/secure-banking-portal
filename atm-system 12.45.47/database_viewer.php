<?php
require_once 'includes/config.php';

// Ensure user is logged in as admin
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

// Get table names
$tables = [];
$stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table'");
while ($row = $stmt->fetch()) {
    $tables[] = $row['name'];
}

$selected_table = $_GET['table'] ?? ($tables[0] ?? '');
$table_data = [];

if ($selected_table && in_array($selected_table, $tables)) {
    $stmt = $conn->prepare("SELECT * FROM $selected_table LIMIT 100");
    $stmt->execute();
    $table_data = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Viewer - ATM System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Database Viewer</h1>
                <p class="text-gray-600">View and explore database contents</p>
            </div>
            <a href="admin/index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                <i class="fas fa-arrow-left mr-2"></i>Back to Admin
            </a>
        </div>

        <!-- Table Selector -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Select Table</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php foreach ($tables as $table): ?>
                        <a href="?table=<?= urlencode($table) ?>" 
                           class="p-4 border rounded-lg hover:bg-gray-50 <?= $selected_table === $table ? 'border-blue-500 bg-blue-50' : 'border-gray-200' ?>">
                            <div class="flex items-center">
                                <i class="fas fa-table text-gray-400 mr-3"></i>
                                <span class="font-medium"><?= htmlspecialchars($table) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Table Data -->
        <?php if ($selected_table && !empty($table_data)): ?>
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        <?= htmlspecialchars($selected_table) ?> (<?= count($table_data) ?> rows)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <?php if (!empty($table_data)): ?>
                                    <?php foreach (array_keys($table_data[0]) as $column): ?>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <?= htmlspecialchars($column) ?>
                                        </th>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($table_data as $row): ?>
                                <tr class="hover:bg-gray-50">
                                    <?php foreach ($row as $value): ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($value ?? 'NULL') ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($selected_table): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-500 text-center">No data found in <?= htmlspecialchars($selected_table) ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 