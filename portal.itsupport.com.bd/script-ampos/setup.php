<?php
/**
 * AMPOS Database Setup Script
 * Creates necessary tables for AMPOS installation
 */

require_once __DIR__ . '/config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDbConnection();
        
        // Create app_settings table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `app_settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(255) NOT NULL UNIQUE,
                `setting_value` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(100) NOT NULL UNIQUE,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `role` ENUM('admin', 'manager', 'cashier') DEFAULT 'cashier',
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create default admin user if not exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES ('admin', 'admin@localhost', ?, 'admin')")
                ->execute([$admin_password]);
        }
        
        // Generate installation ID
        $installation_id = sprintf('%s%s-%s-%s-%s-%s%s%s',
            ...str_split(bin2hex(random_bytes(16)), 4)
        );
        
        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('installation_id', ?) ON DUPLICATE KEY UPDATE setting_value = setting_value");
        $stmt->execute([$installation_id]);
        
        $success = true;
        $message = '<div class="bg-green-500/20 border border-green-500/30 text-green-300 rounded-lg p-4 text-center">Database setup completed successfully! Redirecting to license setup...</div>';
        header('Refresh: 3; url=license_setup.php');
        
    } catch (PDOException $e) {
        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 rounded-lg p-4 text-center">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPOS Database Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-900 via-indigo-900 to-purple-900 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-lg px-4">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-2xl mb-4">
                <i class="fas fa-database text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mt-4">AMPOS Setup</h1>
            <p class="text-blue-200 mt-2">Initialize database tables</p>
        </div>
        
        <div class="bg-white/10 backdrop-blur-lg border border-white/20 rounded-2xl shadow-2xl p-8">
            <?= $message ?>
            
            <?php if (!$success): ?>
            <form method="POST">
                <p class="text-blue-100 mb-6 text-center">Click below to create the required database tables for AMPOS.</p>
                <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white font-semibold rounded-xl hover:from-blue-600 hover:to-purple-700 transition-all">
                    <i class="fas fa-cogs mr-2"></i>Initialize Database
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
