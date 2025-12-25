<?php
header('Content-Type: text/plain'); // Plain text for raw encrypted data

// Include the license service's database configuration
require_once __DIR__ . '/config.php';

// --- Encryption/Decryption Configuration ---
// NOTE: This key MUST be kept secret and MUST match the key used in the AMPOS app's license_manager.php
define('ENCRYPTION_KEY', 'ITSupportBD_AMPOS_SecureKey_2024');
define('CIPHER_METHOD', 'aes-256-cbc');

function encryptLicenseData(array $data) {
    $iv_length = openssl_cipher_iv_length(CIPHER_METHOD);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt(json_encode($data), CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $encrypted);
}

$input = json_decode(file_get_contents('php://input'), true);

// Log the received input for debugging
error_log("AMPOS License verification received input: " . print_r($input, true));

$app_license_key = $input['app_license_key'] ?? null;
$user_id = $input['user_id'] ?? null;
$installation_id = $input['installation_id'] ?? null;
$app_version = $input['app_version'] ?? '1.0.0';

// Validate required fields
if (empty($app_license_key) || empty($user_id) || empty($installation_id)) {
    error_log("AMPOS License verification failed: Missing app_license_key, user_id, or installation_id.");
    echo encryptLicenseData([
        'success' => false,
        'message' => 'Missing application license key, user ID, or installation ID.',
        'actual_status' => 'invalid_request'
    ]);
    exit;
}

try {
    $pdo = getLicenseDbConnection();

    // 1. Fetch the license from MySQL - Check for AMPOS product category
    $stmt = $pdo->prepare("
        SELECT l.*, p.name as product_name, p.category, p.max_devices as product_max_devices
        FROM `licenses` l
        LEFT JOIN `products` p ON l.product_id = p.id
        WHERE l.license_key = ? AND (p.category = 'AMPOS' OR l.license_key LIKE 'AMPOS-%')
    ");
    $stmt->execute([$app_license_key]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$license) {
        // Try without category check for backward compatibility
        $stmt = $pdo->prepare("SELECT * FROM `licenses` WHERE license_key = ?");
        $stmt->execute([$app_license_key]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$license) {
            echo encryptLicenseData([
                'success' => false,
                'message' => 'Invalid or expired AMPOS license key.',
                'actual_status' => 'not_found'
            ]);
            exit;
        }
    }

    // 2. Check license status and expiry
    if ($license['status'] !== 'active' && $license['status'] !== 'free') {
        echo encryptLicenseData([
            'success' => false,
            'message' => 'License is ' . $license['status'] . '.',
            'actual_status' => $license['status']
        ]);
        exit;
    }

    // Check expiry date
    if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
        // Automatically update status to 'expired' if past due
        $stmt = $pdo->prepare("UPDATE `licenses` SET status = 'expired', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$license['id']]);
        echo encryptLicenseData([
            'success' => false,
            'message' => 'License has expired.',
            'actual_status' => 'expired'
        ]);
        exit;
    }

    // 3. Enforce one-to-one binding using installation_id
    if (empty($license['bound_installation_id'])) {
        // License is not bound, bind it to this installation_id
        $stmt = $pdo->prepare("UPDATE `licenses` SET bound_installation_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$installation_id, $license['id']]);
        error_log("AMPOS License '{$app_license_key}' bound to new installation ID: {$installation_id}");
    } elseif ($license['bound_installation_id'] !== $installation_id) {
        // License is bound to a different installation_id, deny access
        echo encryptLicenseData([
            'success' => false,
            'message' => 'License is already in use by another installation.',
            'actual_status' => 'in_use'
        ]);
        exit;
    }

    // 4. Update last_active_at timestamp and app_version
    $stmt = $pdo->prepare("UPDATE `licenses` SET last_active_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$license['id']]);

    // 5. Determine license tier features
    $tier = 'basic';
    $max_products = 100;
    $max_users = 1;
    $features = ['basic_pos', 'reports'];
    
    $product_name = strtolower($license['product_name'] ?? '');
    if (strpos($product_name, 'enterprise') !== false) {
        $tier = 'enterprise';
        $max_products = 999999;
        $max_users = 999999;
        $features = ['basic_pos', 'reports', 'inventory', 'multi_store', 'api_access', 'priority_support', 'custom_branding', 'advanced_analytics'];
    } elseif (strpos($product_name, 'advanced') !== false) {
        $tier = 'advanced';
        $max_products = 10000;
        $max_users = 50;
        $features = ['basic_pos', 'reports', 'inventory', 'multi_store', 'api_access'];
    } elseif (strpos($product_name, 'standard') !== false) {
        $tier = 'standard';
        $max_products = 1000;
        $max_users = 10;
        $features = ['basic_pos', 'reports', 'inventory'];
    }

    // 6. Return encrypted success data
    echo encryptLicenseData([
        'success' => true,
        'message' => 'License is active.',
        'actual_status' => $license['status'],
        'expires_at' => $license['expires_at'],
        'tier' => $tier,
        'max_products' => $max_products,
        'max_users' => $max_users,
        'features' => $features,
        'product_name' => $license['product_name'] ?? 'AMPOS Basic'
    ]);

} catch (Exception $e) {
    error_log("AMPOS License verification error: " . $e->getMessage());
    echo encryptLicenseData([
        'success' => false,
        'message' => 'An internal error occurred during license verification.',
        'actual_status' => 'error'
    ]);
}
?>
