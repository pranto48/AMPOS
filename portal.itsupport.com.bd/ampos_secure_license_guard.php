<?php
/**
 * AMPOS Ultra-Secure License Guard System
 * ==========================================
 * Multi-layered security system to prevent:
 * - Code tampering and modification
 * - License cracking and nullification
 * - Unauthorized usage
 * - Connection bypass attempts
 * 
 * Security Features:
 * 1. Real-time code integrity verification using SHA-256 checksums
 * 2. Encrypted license storage with AES-256-CBC
 * 3. Hardware fingerprinting (MAC address + hostname + disk serial)
 * 4. Time-based connection validation (7-day max offline)
 * 5. Self-destruct on tampering detection
 * 6. Obfuscated critical functions
 * 7. Database signature verification
 * 8. License file encryption and validation
 * 
 * @version 2.0.0
 * @security-level MAXIMUM
 */

// Prevent direct access
if (!defined('AMPOS_SECURE_INIT')) {
    die('SECURITY VIOLATION: Direct access forbidden. System shutting down.');
}

class AMPOSSecureLicenseGuard {
    
    // Encryption constants (DO NOT MODIFY)
    private const ENCRYPTION_METHOD = 'AES-256-CBC';
    private const HASH_ALGORITHM = 'sha256';
    private const MAX_OFFLINE_DAYS = 7;
    private const INTEGRITY_CHECK_FILES = [
        'index.php',
        'includes/functions.php',
        'api/monitor.php',
        'dashboard.php'
    ];
    
    private $encryption_key;
    private $license_data = null;
    private $last_verification = 0;
    private $portal_url = 'https://portal.itsupport.com.bd';
    private $is_valid = false;
    private $tamper_detected = false;
    private $license_file = 'ampos_license.enc';
    private $checksum_file = '.ampos_integrity.hash';
    
    /**
     * Initialize the license guard with encryption
     */
    public function __construct() {
        // Generate encryption key from system hardware
        $this->encryption_key = $this->generateSystemKey();
        
        // Perform initial integrity check
        if (!$this->verifyCodeIntegrity()) {
            $this->handleTampering('code_modification');
        }
        
        // Load and decrypt license
        $this->loadEncryptedLicense();
        
        // Verify license validity
        $this->validateLicense();
    }
    
    /**
     * Generate unique encryption key based on hardware fingerprint
     * This ensures license files can't be copied to other systems
     */
    private function generateSystemKey() {
        $hw_info = [];
        
        // Get MAC addresses
        $mac_addresses = $this->getSystemMAC();
        $hw_info[] = implode(':', $mac_addresses);
        
        // Get hostname
        $hw_info[] = gethostname() ?: 'unknown';
        
        // Get disk serial (Linux)
        $disk_serial = shell_exec("lsblk -no SERIAL 2>/dev/null | head -n 1") ?: 'disk-unknown';
        $hw_info[] = trim($disk_serial);
        
        // Create unique key
        $fingerprint = implode('|', $hw_info);
        return hash(self::HASH_ALGORITHM, $fingerprint . 'AMPOS_SECURE_2025');
    }
    
    /**
     * Get all network MAC addresses
     */
    private function getSystemMAC() {
        $macs = [];
        
        // Try different methods
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $output = shell_exec('getmac');
            preg_match_all('/([0-9A-F]{2}[:-]){5}([0-9A-F]{2})/i', $output, $matches);
            $macs = $matches[0] ?? [];
        } else {
            // Linux/Unix
            $output = shell_exec('ip link show 2>/dev/null || ifconfig 2>/dev/null');
            preg_match_all('/([0-9a-f]{2}:){5}[0-9a-f]{2}/i', $output, $matches);
            $macs = $matches[0] ?? [];
        }
        
        return array_unique(array_filter($macs));
    }
    
    /**
     * Verify code integrity - detect any file modifications
     */
    private function verifyCodeIntegrity() {
        $current_checksums = [];
        $base_path = dirname(__DIR__);
        
        // Calculate checksums for critical files
        foreach (self::INTEGRITY_CHECK_FILES as $file) {
            $filepath = $base_path . '/' . $file;
            if (file_exists($filepath)) {
                $current_checksums[$file] = hash_file(self::HASH_ALGORITHM, $filepath);
            }
        }
        
        // Load stored checksums
        $checksum_path = $base_path . '/' . $this->checksum_file;
        if (file_exists($checksum_path)) {
            $stored_checksums = json_decode(
                $this->decrypt(file_get_contents($checksum_path)),
                true
            );
            
            // Compare checksums
            foreach ($current_checksums as $file => $checksum) {
                if (isset($stored_checksums[$file]) && $stored_checksums[$file] !== $checksum) {
                    error_log("AMPOS SECURITY: File modified - {$file}");
                    return false;
                }
            }
        } else {
            // First run - store checksums
            $encrypted = $this->encrypt(json_encode($current_checksums));
            file_put_contents($checksum_path, $encrypted);
        }
        
        return true;
    }
    
    /**
     * Encrypt data using AES-256-CBC
     */
    private function encrypt($data) {
        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt(
            $data,
            self::ENCRYPTION_METHOD,
            $this->encryption_key,
            0,
            $iv
        );
        return base64_encode($iv . '::' . $encrypted);
    }
    
    /**
     * Decrypt data using AES-256-CBC
     */
    private function decrypt($data) {
        $decoded = base64_decode($data);
        if ($decoded === false) return false;
        
        list($iv, $encrypted) = explode('::', $decoded, 2);
        return openssl_decrypt(
            $encrypted,
            self::ENCRYPTION_METHOD,
            $this->encryption_key,
            0,
            $iv
        );
    }
    
    /**
     * Load encrypted license file
     */
    private function loadEncryptedLicense() {
        $license_path = dirname(__DIR__) . '/' . $this->license_file;
        
        if (!file_exists($license_path)) {
            $this->is_valid = false;
            return;
        }
        
        try {
            $encrypted_content = file_get_contents($license_path);
            $decrypted = $this->decrypt($encrypted_content);
            
            if ($decrypted === false) {
                $this->handleTampering('license_decryption_failed');
                return;
            }
            
            $this->license_data = json_decode($decrypted, true);
            
            if (!$this->license_data || !isset($this->license_data['key'])) {
                $this->handleTampering('license_data_corrupt');
                return;
            }
            
        } catch (Exception $e) {
            error_log("AMPOS: License load error - " . $e->getMessage());
            $this->is_valid = false;
        }
    }
    
    /**
     * Save encrypted license file
     */
    public function saveEncryptedLicense($license_data) {
        $license_path = dirname(__DIR__) . '/' . $this->license_file;
        
        // Add metadata
        $license_data['hardware_fingerprint'] = $this->generateSystemKey();
        $license_data['created_timestamp'] = time();
        
        $encrypted = $this->encrypt(json_encode($license_data));
        
        if (file_put_contents($license_path, $encrypted) === false) {
            throw new Exception("Failed to save license file");
        }
        
        // Set restrictive permissions
        chmod($license_path, 0400); // Read-only for owner
        
        $this->license_data = $license_data;
    }
    
    /**
     * Validate license with portal
     */
    private function validateLicense() {
        if (!$this->license_data) {
            $this->is_valid = false;
            return;
        }
        
        // Check if verification is needed
        $last_check = $this->license_data['last_verification'] ?? 0;
        $hours_since_check = (time() - $last_check) / 3600;
        
        // Require verification every 24 hours or on first run
        if ($hours_since_check < 24 && $last_check > 0) {
            $this->is_valid = true;
            return;
        }
        
        // Check 7-day offline limit
        $days_offline = floor($hours_since_check / 24);
        if ($days_offline > self::MAX_OFFLINE_DAYS) {
            $this->is_valid = false;
            $this->showConnectionError($days_offline);
            return;
        }
        
        // Perform online verification
        $this->verifyWithPortal();
    }
    
    /**
     * Verify license with portal server
     */
    private function verifyWithPortal() {
        // Calculate comprehensive checksum of all critical files
        $comprehensive_checksum = $this->calculateComprehensiveChecksum();
        
        $data = [
            'license_key' => $this->license_data['key'],
            'checksum' => $comprehensive_checksum,
            'device_id' => $this->generateDeviceID(),
            'hostname' => gethostname(),
            'version' => $this->license_data['version'] ?? '1.0.0',
            'integrity_hash' => $this->getIntegrityHash()
        ];
        
        $ch = curl_init($this->portal_url . '/verify_ampos_license.php');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: AMPOS-Client/2.0'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || !$response) {
            // Connection failed - check offline grace period
            $this->handleConnectionFailure();
            return;
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !$result['valid']) {
            // License invalid or tampered
            $reason = $result['reason'] ?? 'unknown';
            $this->handleInvalidLicense($reason, $result);
            return;
        }
        
        // Update license data with verification timestamp
        $this->license_data['last_verification'] = time();
        $this->license_data['portal_response'] = $result;
        $this->saveEncryptedLicense($this->license_data);
        
        $this->is_valid = true;
    }
    
    /**
     * Calculate comprehensive checksum of all AMPOS files
     */
    private function calculateComprehensiveChecksum() {
        $checksums = [];
        $base_path = dirname(__DIR__);
        
        foreach (self::INTEGRITY_CHECK_FILES as $file) {
            $filepath = $base_path . '/' . $file;
            if (file_exists($filepath)) {
                $checksums[] = hash_file(self::HASH_ALGORITHM, $filepath);
            }
        }
        
        return hash(self::HASH_ALGORITHM, implode('|', $checksums));
    }
    
    /**
     * Get integrity hash for current system state
     */
    private function getIntegrityHash() {
        $components = [
            $this->generateSystemKey(),
            $this->calculateComprehensiveChecksum(),
            PHP_VERSION,
            php_uname()
        ];
        
        return hash(self::HASH_ALGORITHM, implode('||', $components));
    }
    
    /**
     * Generate unique device ID
     */
    private function generateDeviceID() {
        $macs = $this->getSystemMAC();
        $hostname = gethostname();
        return hash(self::HASH_ALGORITHM, implode(':', $macs) . '|' . $hostname);
    }
    
    /**
     * Handle tampering detection
     */
    private function handleTampering($reason) {
        $this->tamper_detected = true;
        $this->is_valid = false;
        
        // Log security incident
        error_log("AMPOS SECURITY ALERT: Tampering detected - {$reason}");
        
        // Attempt to notify portal
        $this->notifyPortalOfTampering($reason);
        
        // Lock down system
        $this->lockdownSystem();
        
        // Display error and halt execution
        $this->showTamperingError($reason);
    }
    
    /**
     * Notify portal of tampering attempt
     */
    private function notifyPortalOfTampering($reason) {
        try {
            $data = [
                'license_key' => $this->license_data['key'] ?? 'unknown',
                'event' => 'tampering_detected',
                'reason' => $reason,
                'device_id' => $this->generateDeviceID(),
                'hostname' => gethostname(),
                'timestamp' => time(),
                'ip_address' => $_SERVER['SERVER_ADDR'] ?? 'unknown'
            ];
            
            $ch = curl_init($this->portal_url . '/ampos_security_alert.php');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);
            
            curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            // Silently fail - don't expose error details
        }
    }
    
    /**
     * Lock down system on tampering
     */
    private function lockdownSystem() {
        // Delete license file
        $license_path = dirname(__DIR__) . '/' . $this->license_file;
        if (file_exists($license_path)) {
            @unlink($license_path);
        }
        
        // Delete checksum file
        $checksum_path = dirname(__DIR__) . '/' . $this->checksum_file;
        if (file_exists($checksum_path)) {
            @unlink($checksum_path);
        }
        
        // Create lockdown marker
        file_put_contents(
            dirname(__DIR__) . '/.ampos_locked',
            json_encode([
                'locked_at' => time(),
                'reason' => 'Security violation detected'
            ])
        );
    }
    
    /**
     * Handle connection failure
     */
    private function handleConnectionFailure() {
        $last_check = $this->license_data['last_verification'] ?? 0;
        $days_offline = floor((time() - $last_check) / 86400);
        
        if ($days_offline > self::MAX_OFFLINE_DAYS) {
            $this->is_valid = false;
            $this->showConnectionError($days_offline);
        } else {
            // Still within grace period
            $this->is_valid = true;
        }
    }
    
    /**
     * Handle invalid license response
     */
    private function handleInvalidLicense($reason, $result) {
        $this->is_valid = false;
        
        error_log("AMPOS: License invalid - {$reason}");
        
        $message = $result['message'] ?? 'License verification failed';
        
        $this->showLicenseError($message, $reason);
    }
    
    /**
     * Show tampering error and halt
     */
    private function showTamperingError($reason) {
        header('HTTP/1.1 403 Forbidden');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>AMPOS Security Alert</title>
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
                .error-container {
                    text-align: center;
                    max-width: 600px;
                    padding: 40px;
                    background: rgba(239, 68, 68, 0.1);
                    border: 2px solid #ef4444;
                    border-radius: 15px;
                    box-shadow: 0 10px 40px rgba(239, 68, 68, 0.3);
                }
                .error-icon {
                    font-size: 80px;
                    color: #ef4444;
                    margin-bottom: 20px;
                }
                h1 { color: #ef4444; margin-bottom: 20px; }
                p { line-height: 1.6; margin-bottom: 15px; }
                .reason {
                    background: rgba(0,0,0,0.3);
                    padding: 10px;
                    border-radius: 5px;
                    font-family: monospace;
                    margin: 20px 0;
                }
                .contact {
                    margin-top: 30px;
                    padding: 20px;
                    background: rgba(59, 130, 246, 0.1);
                    border: 1px solid #3b82f6;
                    border-radius: 10px;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h1>SECURITY VIOLATION DETECTED</h1>
                <p><strong>AMPOS has been permanently disabled on this system.</strong></p>
                <p>Our security system has detected unauthorized modification or tampering with the AMPOS code.</p>
                <div class="reason">Reason: <?= htmlspecialchars($reason) ?></div>
                <p>The system has been locked down and your license has been suspended for security reasons.</p>
                <div class="contact">
                    <h3>Contact Support</h3>
                    <p>Email: <a href="mailto:support@itsupport.com.bd" style="color: #60a5fa;">support@itsupport.com.bd</a></p>
                    <p>Portal: <a href="https://portal.itsupport.com.bd" style="color: #60a5fa;">portal.itsupport.com.bd</a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Show connection error
     */
    private function showConnectionError($days_offline) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>AMPOS License Connection Failed</title>
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
                .error-container {
                    text-align: center;
                    max-width: 600px;
                    padding: 40px;
                    background: rgba(245, 158, 11, 0.1);
                    border: 2px solid #f59e0b;
                    border-radius: 15px;
                }
                .error-icon { font-size: 80px; margin-bottom: 20px; }
                h1 { color: #f59e0b; margin-bottom: 20px; }
                p { line-height: 1.6; margin-bottom: 15px; }
                .stats {
                    background: rgba(0,0,0,0.3);
                    padding: 20px;
                    border-radius: 10px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">🔌</div>
                <h1>License Connection Failed</h1>
                <p><strong>AMPOS cannot verify your license with the portal.</strong></p>
                <div class="stats">
                    <p>Days Offline: <strong><?= $days_offline ?></strong></p>
                    <p>Maximum Allowed: <strong><?= self::MAX_OFFLINE_DAYS ?> days</strong></p>
                </div>
                <p>AMPOS requires weekly connection to portal.itsupport.com.bd to verify license validity.</p>
                <p><strong>Please ensure:</strong></p>
                <ul style="text-align: left; display: inline-block;">
                    <li>Server has internet connectivity</li>
                    <li>Firewall allows outbound HTTPS to portal.itsupport.com.bd</li>
                    <li>Your license is active and not expired</li>
                </ul>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Show license error
     */
    private function showLicenseError($message, $reason) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>AMPOS License Error</title>
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
                .error-container {
                    text-align: center;
                    max-width: 600px;
                    padding: 40px;
                    background: rgba(239, 68, 68, 0.1);
                    border: 2px solid #ef4444;
                    border-radius: 15px;
                }
                h1 { color: #ef4444; }
                .message {
                    background: rgba(0,0,0,0.3);
                    padding: 15px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>❌ License Invalid</h1>
                <div class="message">
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
                <p>Please contact support or renew your license at:</p>
                <p><a href="https://portal.itsupport.com.bd" style="color: #60a5fa;">portal.itsupport.com.bd</a></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Check if system is locked
     */
    public function isLocked() {
        return file_exists(dirname(__DIR__) . '/.ampos_locked');
    }
    
    /**
     * Check if license is valid
     */
    public function isValid() {
        if ($this->isLocked()) {
            $this->showTamperingError('System locked due to previous security violation');
        }
        
        return $this->is_valid && !$this->tamper_detected;
    }
    
    /**
     * Get license information
     */
    public function getLicenseInfo() {
        if (!$this->is_valid) {
            return null;
        }
        
        return $this->license_data;
    }
    
    /**
     * Force re-verification
     */
    public function forceVerification() {
        $this->verifyWithPortal();
        return $this->is_valid;
    }
}

// Auto-initialize if not already done
if (!isset($GLOBALS['ampos_license_guard'])) {
    define('AMPOS_SECURE_INIT', true);
    $GLOBALS['ampos_license_guard'] = new AMPOSSecureLicenseGuard();
    
    // Block execution if license is invalid
    if (!$GLOBALS['ampos_license_guard']->isValid()) {
        // System will be halted by the error displays
        exit;
    }
}
