<?php
// This file is included by auth_check.php and assumes session is started and config.php is loaded.

// Define how often to re-verify the license with the portal (in seconds)
// AMPOS uses weekly verification (7 days = 604800 seconds)
define('LICENSE_VERIFICATION_INTERVAL', 604800); // 7 days
define('LICENSE_GRACE_PERIOD_DAYS', 7); // 7 days grace period after expiry

// --- Encryption/Decryption Configuration ---
// NOTE: This key MUST match the key used in the portal's verify_ampos_license.php
define('ENCRYPTION_KEY', 'ITSupportBD_AMPOS_SecureKey_2024');
define('CIPHER_METHOD', 'aes-256-cbc');

// --- Integrity and anti-tamper configuration ---
define('LICENSE_FINGERPRINT_MODE', getenv('LICENSE_FINGERPRINT_MODE') ?: 'enforce'); // enforce | allow-rebaseline
define('LICENSE_FINGERPRINT_KEY', getenv('LICENSE_FINGERPRINT_KEY') ?: 'ampos-license-fingerprint');

function decryptLicenseData(string $encrypted_data) {
    $data = base64_decode($encrypted_data);
    $iv_length = openssl_cipher_iv_length(CIPHER_METHOD);

    if (strlen($data) < $iv_length) {
        error_log("DECRYPT_ERROR: Encrypted data too short.");
        return false;
    }

    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    $decrypted = openssl_decrypt($encrypted, CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);

    if ($decrypted === false) {
        error_log("DECRYPT_ERROR: Decryption failed. Key mismatch or corrupted data.");
        return false;
    }

    $result = json_decode($decrypted, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("DECRYPT_ERROR: JSON decoding failed after decryption: " . json_last_error_msg());
        return false;
    }
    return $result;
}

function computeFileFingerprint(string $path): ?string {
    if (!is_readable($path)) {
        return null;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return null;
    }

    return hash_hmac('sha256', $contents, getLicenseDataSecretKey() . '|' . LICENSE_FINGERPRINT_KEY);
}

function ensureLicenseIntegrity(): bool {
    $critical_files = [
        __DIR__ . '/license_manager.php',
        __DIR__ . '/auth_check.php',
        __DIR__ . '/../config.php',
    ];

    foreach ($critical_files as $file) {
        if (!file_exists($file)) {
            continue;
        }
        
        $fingerprint = computeFileFingerprint($file);
        if ($fingerprint === null) {
            error_log("LICENSE_INTEGRITY: Unable to fingerprint {$file}");
            continue;
        }

        $key = 'integrity_' . basename($file);
        $stored = getAppSetting($key);

        // First run or after explicit reset: capture baseline silently
        if (empty($stored)) {
            updateAppSetting($key, $fingerprint);
            continue;
        }

        if (!hash_equals($stored, $fingerprint)) {
            error_log("LICENSE_INTEGRITY: Fingerprint mismatch for {$file}. Stored baseline differs from current file.");

            if (LICENSE_FINGERPRINT_MODE === 'allow-rebaseline') {
                updateAppSetting($key, $fingerprint);
                continue;
            }

            $_SESSION['license_status_code'] = 'disabled';
            $_SESSION['license_message'] = 'License system integrity check failed. Core files were modified.';
            $_SESSION['license_tier'] = 'basic';
            $_SESSION['license_max_products'] = 0;
            $_SESSION['license_max_users'] = 0;
            $_SESSION['license_features'] = [];
            $_SESSION['license_expires_at'] = null;
            $_SESSION['license_grace_period_end'] = null;
            $_SESSION['license_last_verified'] = time();
            return false;
        }
    }

    return true;
}

// Initialize session defaults early so integrity failures set meaningful messages
if (!isset($_SESSION['license_status_code'])) $_SESSION['license_status_code'] = 'unknown';
if (!isset($_SESSION['license_message'])) $_SESSION['license_message'] = 'License status unknown.';
if (!isset($_SESSION['license_tier'])) $_SESSION['license_tier'] = 'basic';
if (!isset($_SESSION['license_max_products'])) $_SESSION['license_max_products'] = 100;
if (!isset($_SESSION['license_max_users'])) $_SESSION['license_max_users'] = 1;
if (!isset($_SESSION['license_features'])) $_SESSION['license_features'] = ['basic_pos', 'reports'];
if (!isset($_SESSION['license_expires_at'])) $_SESSION['license_expires_at'] = null;
if (!isset($_SESSION['license_grace_period_end'])) $_SESSION['license_grace_period_end'] = null;

// Block execution if core files were tampered with and re-baselining is not allowed
if (!ensureLicenseIntegrity()) {
    return;
}

// Function to generate a UUID (Universally Unique Identifier)
function generateUuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Performs the actual license verification with the portal API using file_get_contents.
 * Caches results in session for weekly verification interval.
 */
function verifyLicenseWithPortal() {
    // Initialize session variables if they don't exist
    if (!isset($_SESSION['license_status_code'])) $_SESSION['license_status_code'] = 'unknown';
    if (!isset($_SESSION['license_message'])) $_SESSION['license_message'] = 'License status unknown.';
    if (!isset($_SESSION['license_tier'])) $_SESSION['license_tier'] = 'basic';
    if (!isset($_SESSION['license_max_products'])) $_SESSION['license_max_products'] = 100;
    if (!isset($_SESSION['license_max_users'])) $_SESSION['license_max_users'] = 1;
    if (!isset($_SESSION['license_features'])) $_SESSION['license_features'] = ['basic_pos', 'reports'];
    if (!isset($_SESSION['license_expires_at'])) $_SESSION['license_expires_at'] = null;
    if (!isset($_SESSION['license_grace_period_end'])) $_SESSION['license_grace_period_end'] = null;

    // Check if we should use cached data (weekly verification)
    if (isset($_SESSION['license_last_verified']) && (time() - $_SESSION['license_last_verified'] < LICENSE_VERIFICATION_INTERVAL)) {
        return; // Use cached data
    }

    $app_license_key = getAppLicenseKey();
    $installation_id = getAppSetting('installation_id');
    $user_id = $_SESSION['user_id'] ?? 'anonymous';

    if (empty($app_license_key)) {
        $_SESSION['license_status_code'] = 'unconfigured';
        $_SESSION['license_message'] = 'Application license key is missing.';
        $_SESSION['license_tier'] = 'basic';
        $_SESSION['license_max_products'] = 0;
        $_SESSION['license_max_users'] = 0;
        $_SESSION['license_features'] = [];
        $_SESSION['license_expires_at'] = null;
        $_SESSION['license_last_verified'] = time();
        return;
    }
    
    if (empty($installation_id)) {
        $_SESSION['license_status_code'] = 'unconfigured';
        $_SESSION['license_message'] = 'Application installation ID is missing. Please re-run database setup.';
        $_SESSION['license_tier'] = 'basic';
        $_SESSION['license_max_products'] = 0;
        $_SESSION['license_max_users'] = 0;
        $_SESSION['license_features'] = [];
        $_SESSION['license_expires_at'] = null;
        $_SESSION['license_last_verified'] = time();
        return;
    }

    $post_data = [
        'app_license_key' => $app_license_key,
        'user_id' => $user_id,
        'installation_id' => $installation_id,
        'app_version' => '1.0.0'
    ];

    $license_api_url = LICENSE_API_URL;

    // Use stream context for POST request with file_get_contents
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($post_data),
            'timeout' => 15, // 15 second timeout
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);

    $encrypted_response = @file_get_contents($license_api_url, false, $context);

    if ($encrypted_response === false) {
        $error = error_get_last();
        $error_message = $error['message'] ?? 'Unknown connection error.';
        error_log("AMPOS_LICENSE_ERROR: License server unreachable. Error: {$error_message}");
        $_SESSION['license_status_code'] = 'portal_unreachable';
        $_SESSION['license_message'] = "Could not connect to license server. Will retry next week.";
        // Don't reset other license data - keep using cached values
        $_SESSION['license_last_verified'] = time();
        return;
    }

    $result = decryptLicenseData($encrypted_response);

    if ($result === false) {
        error_log("AMPOS_LICENSE_ERROR: Failed to decrypt or parse license response.");
        $_SESSION['license_status_code'] = 'error';
        $_SESSION['license_message'] = 'Failed to decrypt license response. Key mismatch or corrupted data.';
        $_SESSION['license_tier'] = 'basic';
        $_SESSION['license_max_products'] = 0;
        $_SESSION['license_max_users'] = 0;
        $_SESSION['license_features'] = [];
        $_SESSION['license_expires_at'] = null;
        $_SESSION['license_last_verified'] = time();
        return;
    }

    // Update session with actual license data
    $_SESSION['license_status_code'] = $result['actual_status'] ?? 'invalid';
    $_SESSION['license_message'] = $result['message'] ?? 'License is invalid.';
    $_SESSION['license_tier'] = $result['tier'] ?? 'basic';
    $_SESSION['license_max_products'] = $result['max_products'] ?? 100;
    $_SESSION['license_max_users'] = $result['max_users'] ?? 1;
    $_SESSION['license_features'] = $result['features'] ?? ['basic_pos', 'reports'];
    $_SESSION['license_expires_at'] = $result['expires_at'] ?? null;
    $_SESSION['license_product_name'] = $result['product_name'] ?? 'AMPOS Basic';

    // Handle grace period for expired licenses
    if ($_SESSION['license_status_code'] === 'expired' && $_SESSION['license_expires_at']) {
        $expiry_timestamp = strtotime($_SESSION['license_expires_at']);
        $grace_period_end = $expiry_timestamp + (LICENSE_GRACE_PERIOD_DAYS * 24 * 60 * 60);
        $_SESSION['license_grace_period_end'] = $grace_period_end;

        if (time() < $grace_period_end) {
            $_SESSION['license_status_code'] = 'grace_period';
            $_SESSION['license_message'] = 'Your license has expired. You are in a grace period until ' . date('Y-m-d H:i', $grace_period_end) . '. Please renew your license.';
        } else {
            // Grace period over, mark as disabled
            $_SESSION['license_status_code'] = 'disabled';
            $_SESSION['license_message'] = 'Your license has expired and the grace period has ended. The application is now disabled.';
        }
    } else {
        $_SESSION['license_grace_period_end'] = null;
    }

    error_log("AMPOS_LICENSE_INFO: License verification completed. Status: {$_SESSION['license_status_code']}. Tier: {$_SESSION['license_tier']}. Expires: {$_SESSION['license_expires_at']}");
    $_SESSION['license_last_verified'] = time();
}

// --- Main License Manager Logic ---

// 1. Ensure installation_id exists
$installation_id = getAppSetting('installation_id');
if (empty($installation_id)) {
    $new_uuid = generateUuid();
    updateAppSetting('installation_id', $new_uuid);
    $installation_id = $new_uuid;
}

// 2. Check if license key is configured (prioritize environment-provided key for anti-tamper)
$app_license_key = getAppLicenseKey();
if (!empty(APP_LICENSE_KEY_ENV) && APP_LICENSE_KEY_ENV !== $app_license_key) {
    setAppLicenseKey(APP_LICENSE_KEY_ENV);
    $app_license_key = APP_LICENSE_KEY_ENV;
}

// 3. Verify license with portal (uses cached data if within verification interval)
verifyLicenseWithPortal();
?>
