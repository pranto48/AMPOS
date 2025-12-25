<?php
/**
 * AMPOS License Verification API
 * Secure endpoint for validating AMPOS licenses with anti-tampering protection
 * 
 * Features:
 * - License key validation
 * - Checksum verification for code integrity
 * - Last check-in tracking (7-day grace period)
 * - Device limit enforcement
 * - Automatic deactivation for expired licenses
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Security: Rate limiting
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$current_time = time();
$rate_limit_key = 'ampos_verify_' . md5($ip);

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'time' => $current_time];
}

// Allow 10 requests per minute
if ($_SESSION[$rate_limit_key]['time'] < $current_time - 60) {
    $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => $current_time];
} else {
    $_SESSION[$rate_limit_key]['count']++;
    if ($_SESSION[$rate_limit_key]['count'] > 10) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Too many requests.',
            'valid' => false
        ]);
        exit;
    }
}

try {
    $pdo = getLicenseDbConnection();
    
    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        // Try GET parameters as fallback
        $data = $_GET;
    }
    
    $license_key = $data['license_key'] ?? '';
    $checksum = $data['checksum'] ?? '';
    $device_id = $data['device_id'] ?? '';
    $version = $data['version'] ?? '';
    $hostname = $data['hostname'] ?? '';
    
    // Validate required fields
    if (empty($license_key)) {
        throw new Exception('License key is required');
    }
    
    // Validate license key format
    if (!preg_match('/^AMPOS-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/', $license_key)) {
        throw new Exception('Invalid license key format');
    }
    
    // Fetch license from database
    $stmt = $pdo->prepare("
        SELECT l.*, p.name as product_name, p.max_devices, p.category,
               c.email as customer_email, c.first_name, c.last_name
        FROM `licenses` l
        JOIN `products` p ON l.product_id = p.id
        JOIN `customers` c ON l.customer_id = c.id
        WHERE l.license_key = ? AND p.category = 'AMPOS'
    ");
    $stmt->execute([$license_key]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$license) {
        // Log failed verification attempt
        error_log("AMPOS: Failed verification attempt for key: {$license_key} from IP: {$ip}");
        
        echo json_encode([
            'success' => false,
            'valid' => false,
            'error' => 'Invalid license key. This license does not exist or has been revoked.',
            'message' => 'License verification failed. Please contact support@itsupport.com.bd'
        ]);
        exit;
    }
    
    // Check license status
    if ($license['status'] !== 'active' && $license['status'] !== 'free') {
        echo json_encode([
            'success' => false,
            'valid' => false,
            'error' => 'License is ' . $license['status'],
            'message' => 'Your license has been deactivated. Please contact support or renew your license.',
            'status' => $license['status']
        ]);
        exit;
    }
    
    // Check expiration
    $expires_at = strtotime($license['expires_at']);
    $now = time();
    
    if ($expires_at < $now) {
        // Auto-deactivate expired license
        $stmt = $pdo->prepare("UPDATE `licenses` SET status = 'expired', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$license['id']]);
        
        echo json_encode([
            'success' => false,
            'valid' => false,
            'error' => 'License expired',
            'message' => 'Your license expired on ' . date('Y-m-d', $expires_at) . '. Please renew to continue using AMPOS.',
            'expired_at' => date('Y-m-d H:i:s', $expires_at),
            'status' => 'expired'
        ]);
        exit;
    }
    
    // CRITICAL: Verify code integrity using checksum
    if (!empty($checksum)) {
        // Get stored checksum for this license
        $stmt = $pdo->prepare("SELECT code_checksum FROM `licenses` WHERE id = ?");
        $stmt->execute([$license['id']]);
        $stored_checksum = $stmt->fetchColumn();
        
        if ($stored_checksum && $stored_checksum !== $checksum) {
            // Code has been tampered with!
            // Immediately deactivate the license
            $stmt = $pdo->prepare("UPDATE `licenses` SET status = 'suspended', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$license['id']]);
            
            // Log security incident
            error_log("AMPOS SECURITY ALERT: Code tampering detected for license {$license_key}. Checksum mismatch. License suspended.");
            
            // Notify admin (you can implement email notification here)
            
            echo json_encode([
                'success' => false,
                'valid' => false,
                'error' => 'Code integrity check failed',
                'message' => 'SECURITY ALERT: Code tampering detected. License has been suspended. Contact support immediately.',
                'status' => 'suspended',
                'reason' => 'checksum_mismatch'
            ]);
            exit;
        }
        
        // If no checksum stored yet, store it for future validation
        if (!$stored_checksum) {
            $stmt = $pdo->prepare("UPDATE `licenses` SET code_checksum = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$checksum, $license['id']]);
        }
    }
    
    // Check last check-in (7-day grace period)
    $last_check_in = strtotime($license['last_check_in'] ?? $license['created_at']);
    $days_since_checkin = floor(($now - $last_check_in) / 86400);
    
    if ($days_since_checkin > 7) {
        echo json_encode([
            'success' => false,
            'valid' => false,
            'error' => 'License connection timeout',
            'message' => 'License Connection Failed: No connection to portal for ' . $days_since_checkin . ' days. AMPOS requires weekly check-ins to verify license validity.',
            'days_disconnected' => $days_since_checkin,
            'last_check_in' => date('Y-m-d H:i:s', $last_check_in),
            'warning' => 'Please ensure your server has internet connectivity to portal.itsupport.com.bd'
        ]);
        exit;
    }
    
    // Handle device registration
    if (!empty($device_id)) {
        // Check if device is already registered
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM `license_devices` 
            WHERE license_id = ? AND device_id = ?
        ");
        $stmt->execute([$license['id'], $device_id]);
        $device_exists = $stmt->fetchColumn() > 0;
        
        if (!$device_exists) {
            // Check device limit
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `license_devices` WHERE license_id = ?");
            $stmt->execute([$license['id']]);
            $current_devices = $stmt->fetchColumn();
            
            if ($current_devices >= $license['max_devices']) {
                echo json_encode([
                    'success' => false,
                    'valid' => false,
                    'error' => 'Device limit reached',
                    'message' => 'Maximum device limit (' . $license['max_devices'] . ') reached. Please remove a device or upgrade your license.',
                    'current_devices' => $current_devices,
                    'max_devices' => $license['max_devices']
                ]);
                exit;
            }
            
            // Register new device
            $stmt = $pdo->prepare("
                INSERT INTO `license_devices` (license_id, device_id, hostname, first_seen, last_seen)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$license['id'], $device_id, $hostname]);
        } else {
            // Update last seen
            $stmt = $pdo->prepare("
                UPDATE `license_devices` 
                SET last_seen = CURRENT_TIMESTAMP, hostname = ?
                WHERE license_id = ? AND device_id = ?
            ");
            $stmt->execute([$hostname, $license['id'], $device_id]);
        }
    }
    
    // Update license check-in time and current device count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `license_devices` WHERE license_id = ?");
    $stmt->execute([$license['id']]);
    $device_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        UPDATE `licenses` 
        SET last_check_in = CURRENT_TIMESTAMP, 
            current_devices = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$device_count, $license['id']]);
    
    // Log successful verification
    $stmt = $pdo->prepare("
        INSERT INTO `license_verification_logs` 
        (license_id, device_id, ip_address, checksum, version, status)
        VALUES (?, ?, ?, ?, ?, 'success')
    ");
    $stmt->execute([$license['id'], $device_id, $ip, $checksum, $version]);
    
    // Calculate days until expiration
    $days_remaining = floor(($expires_at - $now) / 86400);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'valid' => true,
        'message' => 'License verified successfully',
        'license' => [
            'key' => $license_key,
            'status' => $license['status'],
            'product' => $license['product_name'],
            'customer' => $license['first_name'] . ' ' . $license['last_name'],
            'issued_at' => $license['issued_at'],
            'expires_at' => $license['expires_at'],
            'days_remaining' => $days_remaining,
            'max_devices' => $license['max_devices'],
            'current_devices' => $device_count,
            'last_check_in' => date('Y-m-d H:i:s'),
            'checksum_verified' => !empty($checksum),
        ],
        'warnings' => $days_remaining <= 30 ? ['License expires in ' . $days_remaining . ' days. Please renew soon.'] : []
    ]);
    
} catch (Exception $e) {
    error_log("AMPOS License Verification Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'valid' => false,
        'error' => 'Verification failed',
        'message' => $e->getMessage()
    ]);
}
?>
