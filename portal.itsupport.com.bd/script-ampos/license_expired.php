<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/license_manager.php';

// If license is somehow active or in grace period, redirect to index
if (isset($_SESSION['license_status_code']) && ($_SESSION['license_status_code'] === 'active' || $_SESSION['license_status_code'] === 'grace_period')) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Disabled - AMPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-red-900 via-rose-900 to-pink-900 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md px-4">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl shadow-2xl mb-4 animate-pulse">
                <i class="fas fa-exclamation-triangle text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mt-4">License Disabled</h1>
            <p class="text-red-200 mt-2">Your AMPOS application license is no longer active.</p>
        </div>
        
        <div class="bg-white/10 backdrop-blur-lg border border-white/20 rounded-2xl shadow-2xl p-8 space-y-6 text-center">
            <p class="text-red-200 text-lg">
                <?= htmlspecialchars($_SESSION['license_message'] ?? 'Your license has been disabled. The application is now non-functional.') ?>
            </p>
            
            <div class="bg-white/5 rounded-xl p-4 text-left">
                <h4 class="text-white font-semibold mb-2"><i class="fas fa-info-circle mr-2"></i>What happened?</h4>
                <ul class="text-red-200 text-sm space-y-1">
                    <li>• Your license may have expired</li>
                    <li>• The 7-day grace period has ended</li>
                    <li>• Your license may have been revoked</li>
                    <li>• License files may have been modified</li>
                </ul>
            </div>
            
            <p class="text-red-100">
                Please contact IT Support BD to renew your license or purchase a new one.
            </p>
            
            <div class="space-y-3">
                <a href="https://portal.itsupport.com.bd/products.php" target="_blank" 
                   class="w-full inline-block px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white font-semibold rounded-xl hover:from-blue-600 hover:to-purple-700 focus:ring-2 focus:ring-blue-400 focus:outline-none transition-all duration-200 shadow-lg">
                    <i class="fas fa-shopping-cart mr-2"></i>Purchase New License
                </a>
                
                <a href="license_setup.php" 
                   class="w-full inline-block px-6 py-3 bg-white/10 border border-white/20 text-white font-semibold rounded-xl hover:bg-white/20 focus:ring-2 focus:ring-white/30 focus:outline-none transition-all duration-200">
                    <i class="fas fa-key mr-2"></i>Enter Different License Key
                </a>
                
                <a href="logout.php" 
                   class="w-full inline-block px-6 py-3 bg-white/5 border border-white/10 text-red-200 font-semibold rounded-xl hover:bg-white/10 focus:ring-2 focus:ring-white/20 focus:outline-none transition-all duration-200">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
        
        <!-- Contact Info -->
        <div class="mt-6 text-center">
            <p class="text-red-300 text-sm">
                Need help? Contact us at 
                <a href="mailto:support@itsupport.com.bd" class="text-white hover:underline">support@itsupport.com.bd</a>
            </p>
        </div>
    </div>
</body>
</html>
