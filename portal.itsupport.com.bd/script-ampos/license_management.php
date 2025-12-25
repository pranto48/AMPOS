<?php
require_once __DIR__ . '/includes/auth_check.php';

// Only admins can access this page
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Management - AMPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-white"><i class="fas fa-key mr-3"></i>License Management</h1>
            <a href="index.php" class="text-blue-300 hover:text-white"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        </div>
        
        <!-- License Status Card -->
        <div class="bg-white/10 backdrop-blur-lg border border-white/20 rounded-2xl p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Current License</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-white/5 rounded-xl p-4">
                    <div class="text-blue-300 text-sm">Status</div>
                    <div class="text-white text-lg font-bold"><?= htmlspecialchars(ucfirst($_SESSION['license_status_code'] ?? 'Unknown')) ?></div>
                </div>
                <div class="bg-white/5 rounded-xl p-4">
                    <div class="text-blue-300 text-sm">Tier</div>
                    <div class="text-white text-lg font-bold"><?= htmlspecialchars(ucfirst($_SESSION['license_tier'] ?? 'Basic')) ?></div>
                </div>
                <div class="bg-white/5 rounded-xl p-4">
                    <div class="text-blue-300 text-sm">Expires</div>
                    <div class="text-white text-lg font-bold"><?= $_SESSION['license_expires_at'] ? date('Y-m-d', strtotime($_SESSION['license_expires_at'])) : 'Never' ?></div>
                </div>
                <div class="bg-white/5 rounded-xl p-4">
                    <div class="text-blue-300 text-sm">Last Verified</div>
                    <div class="text-white text-lg font-bold"><?= $_SESSION['license_last_verified'] ? date('Y-m-d H:i', $_SESSION['license_last_verified']) : 'Never' ?></div>
                </div>
            </div>
            
            <div class="mt-4 flex gap-3">
                <button onclick="forceRecheck()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-sync mr-2"></i>Force Recheck
                </button>
            </div>
        </div>
        
        <!-- Features -->
        <div class="bg-white/10 backdrop-blur-lg border border-white/20 rounded-2xl p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Available Features</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($_SESSION['license_features'] ?? ['basic_pos'] as $feature): ?>
                <span class="px-3 py-1 bg-green-500/20 text-green-300 rounded-full text-sm"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($feature))) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
    async function forceRecheck() {
        const res = await fetch('api.php?action=force_recheck');
        const data = await res.json();
        alert(data.message);
        location.reload();
    }
    </script>
</body>
</html>
