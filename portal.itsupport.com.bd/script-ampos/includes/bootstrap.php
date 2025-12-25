<?php
// Bootstrap file for AMPOS - handles session, database, and core includes

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
require_once __DIR__ . '/../config.php';

// Check if database tables exist
function checkDatabaseSetup() {
    try {
        $pdo = getDbConnection();
        
        // Check if app_settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'app_settings'");
        if ($stmt->rowCount() === 0) {
            return false;
        }
        
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() === 0) {
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Database check failed: " . $e->getMessage());
        return false;
    }
}

// Redirect to setup if database is not configured
$current_page = basename($_SERVER['PHP_SELF']);
$setup_pages = ['setup.php', 'database_setup.php'];

if (!in_array($current_page, $setup_pages) && !checkDatabaseSetup()) {
    header('Location: setup.php');
    exit;
}
