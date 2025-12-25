<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/license_manager.php';

$message = '';

// If a license key is already set AND active/grace_period, redirect to index
if (getAppLicenseKey() && ($_SESSION['license_status_code'] === 'active' || $_SESSION['license_status_code'] === 'grace_period')) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_license_key = trim($_POST['license_key'] ?? '');
    error_log("DEBUG: license_setup.php received POST with license_key: " . (empty($entered_license_key) ? 'EMPTY' : 'PRESENT'));

    if (empty($entered_license_key)) {
        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Please enter a license key.</div>';
    } else {
        // Attempt to validate the license key against the external API
        $installation_id = getInstallationId();
        if (empty($installation_id)) {
            error_log("ERROR: license_setup.php failed to get installation ID.");
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Application installation ID missing. Please re-run database setup.</div>';
        } else {
            $license_api_url = LICENSE_API_URL;
            $post_data = [
                'app_license_key' => $entered_license_key,
                'user_id' => 'setup_user',
                'installation_id' => $installation_id,
                'app_version' => '1.0.0'
            ];

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($post_data),
                    'timeout' => 15,
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
                error_log("License API connection Error during setup: {$error_message}");
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Failed to connect to license verification service. Network error: ' . htmlspecialchars($error_message) . '</div>';
            } else {
                $licenseData = decryptLicenseData($encrypted_response);

                if ($licenseData === false) {
                    error_log("License API Decryption/Parse Error during setup.");
                    $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Invalid or corrupted response from license verification service.</div>';
                } elseif (isset($licenseData['success']) && $licenseData['success'] === true) {
                    // License is valid, save it to app_settings
                    if (setAppLicenseKey($entered_license_key)) {
                        error_log("DEBUG: license_setup.php successfully saved license key");
                        // Force re-verification to update session variables
                        $_SESSION['license_last_verified'] = 0; // Force refresh
                        verifyLicenseWithPortal();
                        $message = '<div class="bg-green-500/20 border border-green-500/30 text-green-300 text-sm rounded-lg p-3 text-center">License key activated successfully! Tier: ' . htmlspecialchars($licenseData['tier'] ?? 'basic') . '. Redirecting...</div>';
                        header('Refresh: 3; url=index.php');
                        exit;
                    } else {
                        error_log("ERROR: license_setup.php failed to save license key to database.");
                        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Failed to save license key to database.</div>';
                    }
                } else {
                    error_log("DEBUG: license_setup.php license validation failed: " . ($licenseData['message'] ?? 'Unknown reason.'));
                    $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">' . htmlspecialchars($licenseData['message'] ?? 'Invalid license key.') . '</div>';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPOS License Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-900 via-indigo-900 to-purple-900 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md px-4">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-2xl mb-4">
                <i class="fas fa-cash-register text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mt-4">AMPOS License Setup</h1>
            <p class="text-blue-200 mt-2">Enter your license key to activate AMPOS</p>
        </div>
        
        <form method="POST" action="license_setup.php" class="bg-white/10 backdrop-blur-lg border border-white/20 rounded-2xl shadow-2xl p-8 space-y-6">
            <?= $message ?>
            
            <div class="space-y-4">
                <div>
                    <label for="license_key" class="block text-sm font-medium text-blue-100 mb-2">License Key</label>
                    <input type="text" name="license_key" id="license_key" required
                           class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-400 focus:border-transparent text-white placeholder-blue-300"
                           placeholder="AMPOS-XXXX-XXXX-XXXX-XXXX" value="<?= htmlspecialchars(getAppLicenseKey() ?? '') ?>">
                </div>
            </div>
            
            <button type="submit"
                    class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white font-semibold rounded-xl hover:from-blue-600 hover:to-purple-700 focus:ring-2 focus:ring-blue-400 focus:outline-none transition-all duration-200 shadow-lg">
                <i class="fas fa-key mr-2"></i>Activate License
            </button>
            
            <div class="text-center">
                <a href="https://portal.itsupport.com.bd/products.php" target="_blank" class="text-blue-300 hover:text-white text-sm transition-colors">
                    <i class="fas fa-shopping-cart mr-1"></i>Purchase a license
                </a>
            </div>
        </form>
        
        <!-- License Tiers Info -->
        <div class="mt-8 bg-white/5 backdrop-blur-lg border border-white/10 rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-white mb-4 text-center">Available License Tiers</h3>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-white/10 rounded-xl p-3 text-center">
                    <div class="text-green-400 font-bold">Basic</div>
                    <div class="text-blue-200">Free</div>
                    <div class="text-xs text-blue-300 mt-1">100 products, 1 user</div>
                </div>
                <div class="bg-white/10 rounded-xl p-3 text-center">
                    <div class="text-blue-400 font-bold">Standard</div>
                    <div class="text-blue-200">$5/year</div>
                    <div class="text-xs text-blue-300 mt-1">1,000 products, 10 users</div>
                </div>
                <div class="bg-white/10 rounded-xl p-3 text-center">
                    <div class="text-purple-400 font-bold">Advanced</div>
                    <div class="text-blue-200">$10/year</div>
                    <div class="text-xs text-blue-300 mt-1">10,000 products, 50 users</div>
                </div>
                <div class="bg-white/10 rounded-xl p-3 text-center">
                    <div class="text-yellow-400 font-bold">Enterprise</div>
                    <div class="text-blue-200">$100/year</div>
                    <div class="text-xs text-blue-300 mt-1">Unlimited everything</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
