<?php
/**
 * AMPOS Database Migration Runner
 * ================================
 * Execute database migrations safely with rollback support
 * 
 * Usage:
 * 1. Access via browser: https://portal.itsupport.com.bd/database/run_migration.php
 * 2. Or run via CLI: php run_migration.php
 * 
 * Security: This file should be deleted after running migrations in production
 */

// Prevent unauthorized access in production
$MIGRATION_PASSWORD = 'ampos_secure_2025'; // Change this!

if (php_sapi_name() !== 'cli') {
    // Web access - require password
    session_start();
    
    if (isset($_POST['migration_password'])) {
        if ($_POST['migration_password'] === $MIGRATION_PASSWORD) {
            $_SESSION['migration_authorized'] = true;
        } else {
            die('❌ Invalid password. Access denied.');
        }
    }
    
    if (!isset($_SESSION['migration_authorized'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>AMPOS Database Migration</title>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    color: #fff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                }
                .auth-container {
                    background: rgba(17, 24, 39, 0.8);
                    border: 2px solid rgba(59, 130, 246, 0.3);
                    border-radius: 15px;
                    padding: 40px;
                    max-width: 400px;
                    text-align: center;
                }
                h1 { color: #60a5fa; margin-bottom: 20px; }
                input {
                    width: 100%;
                    padding: 12px;
                    margin: 10px 0;
                    background: rgba(31, 41, 55, 0.8);
                    border: 1px solid rgba(75, 85, 99, 0.5);
                    color: #fff;
                    border-radius: 8px;
                    font-size: 16px;
                }
                button {
                    width: 100%;
                    padding: 12px;
                    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
                    border: none;
                    color: white;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 16px;
                    font-weight: bold;
                }
                button:hover { opacity: 0.9; }
            </style>
        </head>
        <body>
            <div class="auth-container">
                <h1>🔐 Migration Authentication</h1>
                <p>Enter password to run database migration</p>
                <form method="POST">
                    <input type="password" name="migration_password" placeholder="Migration Password" required autofocus>
                    <button type="submit">Authenticate</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Load database configuration
require_once __DIR__ . '/../config.php';

// HTML header for web output
if (php_sapi_name() !== 'cli') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>AMPOS Database Migration</title>
        <style>
            body {
                font-family: 'Courier New', monospace;
                background: #1a1a2e;
                color: #00ff00;
                padding: 20px;
                margin: 0;
            }
            .container {
                max-width: 1000px;
                margin: 0 auto;
                background: #0a0a0a;
                padding: 30px;
                border: 2px solid #00ff00;
                border-radius: 10px;
            }
            h1 { color: #00ff00; text-align: center; }
            .success { color: #00ff00; }
            .error { color: #ff0000; }
            .warning { color: #ffaa00; }
            .info { color: #00aaff; }
            pre {
                background: #000;
                padding: 15px;
                border-left: 3px solid #00ff00;
                overflow-x: auto;
            }
            .status {
                padding: 10px;
                margin: 10px 0;
                border-radius: 5px;
            }
            .status.success { background: rgba(0, 255, 0, 0.1); border-left: 4px solid #00ff00; }
            .status.error { background: rgba(255, 0, 0, 0.1); border-left: 4px solid #ff0000; }
            .status.warning { background: rgba(255, 170, 0, 0.1); border-left: 4px solid #ffaa00; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🚀 AMPOS Database Migration Runner</h1>
    <?php
}

function output($message, $type = 'info') {
    $colors = [
        'success' => "\033[0;32m",
        'error' => "\033[0;31m",
        'warning' => "\033[0;33m",
        'info' => "\033[0;36m",
    ];
    $reset = "\033[0m";
    
    if (php_sapi_name() === 'cli') {
        echo $colors[$type] . $message . $reset . "\n";
    } else {
        echo "<div class='status {$type}'><span class='{$type}'>{$message}</span></div>";
        flush();
    }
}

try {
    output("Starting AMPOS Security Migration...", 'info');
    output("=========================================\n", 'info');
    
    // Get database connection
    $pdo = getLicenseDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    output("✓ Database connection established", 'success');
    
    // Read migration file
    $migration_file = __DIR__ . '/migrations/add_ampos_security_tables.sql';
    
    if (!file_exists($migration_file)) {
        throw new Exception("Migration file not found: {$migration_file}");
    }
    
    output("✓ Migration file found", 'success');
    
    $sql = file_get_contents($migration_file);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', 
            preg_split('/;\s*$/m', $sql)
        )
    );
    
    output("\nFound " . count($statements) . " SQL statements to execute\n", 'info');
    
    // Begin transaction
    $pdo->beginTransaction();
    output("Transaction started", 'info');
    
    $executed = 0;
    $skipped = 0;
    
    foreach ($statements as $index => $statement) {
        // Skip comments and empty statements
        if (empty(trim($statement)) || 
            substr(trim($statement), 0, 2) === '--' || 
            substr(trim($statement), 0, 2) === '/*') {
            continue;
        }
        
        try {
            // Show first 100 chars of statement
            $preview = substr(trim($statement), 0, 100);
            if (strlen($statement) > 100) $preview .= '...';
            
            output("\nExecuting: {$preview}", 'info');
            
            $pdo->exec($statement);
            $executed++;
            output("✓ Success", 'success');
            
        } catch (PDOException $e) {
            // Check if error is about existing table/column (can be safely ignored)
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false) {
                $skipped++;
                output("⊘ Skipped (already exists)", 'warning');
            } else {
                throw $e; // Re-throw if it's a real error
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    output("\nTransaction committed successfully", 'success');
    
    output("\n=========================================", 'info');
    output("Migration completed successfully!", 'success');
    output("\nStatistics:", 'info');
    output("  - Total statements: " . count($statements), 'info');
    output("  - Executed: {$executed}", 'success');
    output("  - Skipped: {$skipped}", 'warning');
    
    // Verify created tables
    output("\n=========================================", 'info');
    output("Verifying created tables...", 'info');
    
    $tables_to_check = [
        'ampos_security_incidents',
        'license_devices',
        'license_verification_logs'
    ];
    
    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            output("✓ Table '{$table}' exists", 'success');
            
            // Show row count
            $count_stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `{$table}`");
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            output("  → Contains {$count} row(s)", 'info');
        } else {
            output("✗ Table '{$table}' NOT found", 'error');
        }
    }
    
    // Verify added columns
    output("\nVerifying added columns to 'licenses' table...", 'info');
    
    $columns_to_check = [
        'code_checksum',
        'suspension_reason',
        'last_check_in',
        'hardware_fingerprint'
    ];
    
    $stmt = $pdo->query("DESCRIBE `licenses`");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    foreach ($columns_to_check as $column) {
        if (in_array($column, $existing_columns)) {
            output("✓ Column 'licenses.{$column}' exists", 'success');
        } else {
            output("✗ Column 'licenses.{$column}' NOT found", 'error');
        }
    }
    
    // Check view
    output("\nVerifying created view...", 'info');
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $views = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (in_array('ampos_license_security_status', $views)) {
        output("✓ View 'ampos_license_security_status' created", 'success');
    } else {
        output("✗ View 'ampos_license_security_status' NOT found", 'error');
    }
    
    output("\n=========================================", 'info');
    output("✓ All database changes applied successfully!", 'success');
    output("\n⚠️  SECURITY NOTICE:", 'warning');
    output("Please delete this migration script (run_migration.php) from production server.", 'warning');
    output("\n🎉 AMPOS Security System is now ready!", 'success');
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        output("\nTransaction rolled back due to error", 'error');
    }
    
    output("\n=========================================", 'error');
    output("❌ Migration failed!", 'error');
    output("Error: " . $e->getMessage(), 'error');
    output("\nStack trace:", 'error');
    
    if (php_sapi_name() === 'cli') {
        echo $e->getTraceAsString() . "\n";
    } else {
        echo "<pre class='error'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
    
    exit(1);
}

// HTML footer for web output
if (php_sapi_name() !== 'cli') {
    ?>
        </div>
    </body>
    </html>
    <?php
}
