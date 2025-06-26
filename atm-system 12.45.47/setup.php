<?php
// ATM System Setup Script
// This script initializes the database and creates default users

echo "ATM System Setup\n";
echo "================\n\n";

// Database configuration
$db_path = __DIR__ . '/database.sqlite';

// Create database file if it doesn't exist
if (!file_exists($db_path)) {
    touch($db_path);
    echo "✓ Database file created\n";
} else {
    echo "✓ Database file already exists\n";
}

try {
    $conn = new PDO('sqlite:' . $db_path);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Enable foreign keys
    $conn->exec('PRAGMA foreign_keys = ON');
    echo "✓ Database connection established\n";
    
    // Create tables
    $tables = [
        'admin' => "CREATE TABLE IF NOT EXISTS admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            phone TEXT,
            status TEXT DEFAULT 'active',
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        'managers' => "CREATE TABLE IF NOT EXISTS managers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            phone TEXT,
            admin_id INTEGER,
            status TEXT DEFAULT 'active',
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admin(id)
        )",
        
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            email TEXT UNIQUE,
            phone TEXT,
            account_number TEXT NOT NULL UNIQUE,
            balance REAL DEFAULT 0.00,
            manager_id INTEGER,
            admin_id INTEGER,
            status TEXT DEFAULT 'active',
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (manager_id) REFERENCES managers(id),
            FOREIGN KEY (admin_id) REFERENCES admin(id)
        )",
        
        'transactions' => "CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            type TEXT NOT NULL,
            balance_after REAL NOT NULL,
            description TEXT,
            reference TEXT,
            status TEXT DEFAULT 'completed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )",
        
        'account_requests' => "CREATE TABLE IF NOT EXISTS account_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            address TEXT,
            initial_deposit REAL DEFAULT 0.00,
            status TEXT DEFAULT 'pending',
            manager_id INTEGER,
            admin_id INTEGER,
            processed_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (manager_id) REFERENCES managers(id),
            FOREIGN KEY (admin_id) REFERENCES admin(id)
        )",
        
        'activity_logs' => "CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            user_type TEXT NOT NULL,
            action TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        'password_resets' => "CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            token TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    ];
    
    foreach ($tables as $table_name => $sql) {
        $conn->exec($sql);
        echo "✓ Table '$table_name' created/verified\n";
    }
    
    // Create indexes
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_admin_status ON admin(status)",
        "CREATE INDEX IF NOT EXISTS idx_manager_status ON managers(status)",
        "CREATE INDEX IF NOT EXISTS idx_manager_admin ON managers(admin_id)",
        "CREATE INDEX IF NOT EXISTS idx_user_status ON users(status)",
        "CREATE INDEX IF NOT EXISTS idx_user_manager ON users(manager_id)",
        "CREATE INDEX IF NOT EXISTS idx_user_admin ON users(admin_id)",
        "CREATE INDEX IF NOT EXISTS idx_transaction_user ON transactions(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_transaction_type ON transactions(type)",
        "CREATE INDEX IF NOT EXISTS idx_transaction_date ON transactions(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_request_status ON account_requests(status)",
        "CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_logs(user_id, user_type)",
        "CREATE INDEX IF NOT EXISTS idx_activity_date ON activity_logs(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_reset_email ON password_resets(email)",
        "CREATE INDEX IF NOT EXISTS idx_reset_token ON password_resets(token)"
    ];
    
    foreach ($indexes as $index_sql) {
        $conn->exec($index_sql);
    }
    echo "✓ Database indexes created\n";
    
    // Insert default admin user if not exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO admin (name, username, password, email, phone) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['System Administrator', 'admin', $admin_password, 'admin@atm.system', '1234567890']);
        echo "✓ Default admin user created (username: admin, password: admin123)\n";
    } else {
        echo "✓ Admin user already exists\n";
    }
    
    // Insert default manager if not exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM managers WHERE username = 'manager1'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $manager_password = password_hash('manager123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO managers (name, username, password, email, phone, admin_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['John Manager', 'manager1', $manager_password, 'manager1@atm.system', '9876543210', 1]);
        echo "✓ Default manager created (username: manager1, password: manager123)\n";
    } else {
        echo "✓ Manager user already exists\n";
    }
    
    // Insert sample user if not exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = 'user1'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $user_password = password_hash('user123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (name, username, password, email, phone, account_number, balance, manager_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['John Doe', 'user1', $user_password, 'user1@example.com', '5551234567', 'ACC2024123456', 1000.00, 1]);
        echo "✓ Sample user created (username: user1, password: user123)\n";
    } else {
        echo "✓ Sample user already exists\n";
    }
    
    echo "\n🎉 Setup completed successfully!\n\n";
    echo "Default login credentials:\n";
    echo "Admin: username=admin, password=admin123\n";
    echo "Manager: username=manager1, password=manager123\n";
    echo "User: username=user1, password=user123\n\n";
    echo "You can now access the ATM system at: http://localhost/your-project-folder/\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 