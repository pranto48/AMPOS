<?php
// Database configuration for XAMPP/cPanel installation
// Default XAMPP MySQL settings - modify these for your environment
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'ampos');

// License System Configuration
define('LICENSE_API_URL', 'https://portal.itsupport.com.bd/verify_ampos_license.php');
define('APP_LICENSE_KEY_ENV', ''); // Can be set via environment for Docker deployments
define('LICENSE_DATA_KEY', ''); // Optional: Set a custom encryption key for stored license data

// Create database connection
function getDbConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 2006) {
                $pdo = null;
            } else {
                throw $e;
            }
        }
    }

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        } catch(PDOException $e) {
            die("ERROR: Could not connect to the database. " . $e->getMessage());
        }
    }
    
    return $pdo;
}

function getAppSetting($key) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM `app_settings` WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : null;
    } catch (PDOException $e) {
        error_log("Error getting app setting '$key': " . $e->getMessage());
        return null;
    }
}

function getLicenseDataSecretKey() {
    $raw_key = LICENSE_DATA_KEY;

    if (empty($raw_key)) {
        $pepper = 'ampos-license-data-pepper';
        $raw_key = hash('sha256', $pepper . '|' . php_uname('n'), true);
    } elseif (strlen($raw_key) < 32) {
        $raw_key = hash('sha256', $raw_key, true);
    }

    return substr($raw_key, 0, 32);
}

function encryptSensitiveValue($plain_text) {
    if ($plain_text === null || $plain_text === '') {
        return $plain_text;
    }

    $key = getLicenseDataSecretKey();
    $iv_length = openssl_cipher_iv_length('aes-256-gcm');
    $iv = random_bytes($iv_length);
    $tag = '';
    $ciphertext = openssl_encrypt($plain_text, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    return base64_encode(json_encode([
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'ciphertext' => base64_encode($ciphertext),
        'v' => 1,
    ]));
}

function decryptSensitiveValue($stored_value) {
    if ($stored_value === null || $stored_value === '') {
        return $stored_value;
    }

    $decoded = base64_decode($stored_value, true);
    if ($decoded === false) {
        return $stored_value;
    }

    $payload = json_decode($decoded, true);
    if (!is_array($payload) || ($payload['v'] ?? null) !== 1) {
        return $stored_value;
    }

    $key = getLicenseDataSecretKey();
    $iv = base64_decode($payload['iv'] ?? '', true);
    $tag = base64_decode($payload['tag'] ?? '', true);
    $cipher = base64_decode($payload['ciphertext'] ?? '', true);

    if ($iv === false || $tag === false || $cipher === false) {
        return $stored_value;
    }

    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    return $plain === false ? $stored_value : $plain;
}

function updateAppSetting($key, $value) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO `app_settings` (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating app setting '$key': " . $e->getMessage());
        return false;
    }
}

function getAppLicenseKey() {
    $value = getAppSetting('app_license_key');
    return decryptSensitiveValue($value);
}

function setAppLicenseKey($key) {
    $encrypted = encryptSensitiveValue($key);
    return updateAppSetting('app_license_key', $encrypted);
}

function getInstallationId() {
    return getAppSetting('installation_id');
}

// License tier feature checking
function getLicenseTier() {
    return $_SESSION['license_tier'] ?? 'basic';
}

function getLicenseMaxProducts() {
    return $_SESSION['license_max_products'] ?? 100;
}

function getLicenseMaxUsers() {
    return $_SESSION['license_max_users'] ?? 1;
}

function getLicenseFeatures() {
    return $_SESSION['license_features'] ?? ['basic_pos', 'reports'];
}

function hasFeature($feature) {
    $features = getLicenseFeatures();
    return in_array($feature, $features);
}
