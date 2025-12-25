<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    redirectToLogin();
}

$pdo = getLicenseDbConnection();
$customer_id = $_SESSION['customer_id'];
$message = '';
$error = '';

// Handle direct purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_ampos'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    
    if ($product_id) {
        try {
            // Get product details
            $stmt = $pdo->prepare("SELECT * FROM `products` WHERE id = ? AND category = 'AMPOS'");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                // Create order
                $stmt = $pdo->prepare("INSERT INTO `orders` (customer_id, total_amount, status) VALUES (?, ?, 'pending_approval')");
                $stmt->execute([$customer_id, $product['price']]);
                $order_id = $pdo->lastInsertId();
                
                // Generate license key
                $license_key = generateLicenseKey('AMPOS');
                
                // Create license
                $expires_at = date('Y-m-d H:i:s', strtotime('+' . $product['license_duration_days'] . ' days'));
                $stmt = $pdo->prepare("
                    INSERT INTO `licenses` (customer_id, product_id, license_key, max_devices, expires_at, status)
                    VALUES (?, ?, ?, ?, ?, 'inactive')
                ");
                $stmt->execute([
                    $customer_id,
                    $product_id,
                    $license_key,
                    $product['max_devices'],
                    $expires_at
                ]);
                $license_id = $pdo->lastInsertId();
                
                // Add order item
                $stmt = $pdo->prepare("
                    INSERT INTO `order_items` (order_id, product_id, quantity, price, license_key_generated)
                    VALUES (?, ?, 1, ?, ?)
                ");
                $stmt->execute([$order_id, $product_id, $product['price'], $license_key]);
                
                $message = '<div class="alert-glass-warning mb-6"><i class="fas fa-clock mr-2"></i>Order #' . htmlspecialchars($order_id) . ' placed successfully! Your AMPOS license is pending payment approval. Once approved by admin, your license will be activated.</div>';
            } else {
                $error = '<div class="alert-glass-error mb-6"><i class="fas fa-exclamation-triangle mr-2"></i>Invalid product selected.</div>';
            }
        } catch (PDOException $e) {
            error_log("AMPOS License Purchase Error: " . $e->getMessage());
            $error = '<div class="alert-glass-error mb-6"><i class="fas fa-exclamation-triangle mr-2"></i>An error occurred while processing your purchase. Please try again.</div>';
        }
    } else {
        $error = '<div class="alert-glass-error mb-6"><i class="fas fa-exclamation-triangle mr-2"></i>Please select a valid product.</div>';
    }
}

// Fetch AMPOS products
$stmt = $pdo->prepare("SELECT * FROM `products` WHERE category = 'AMPOS' ORDER BY price ASC");
$stmt->execute();
$ampos_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch customer's AMPOS licenses
$stmt_licenses = $pdo->prepare("
    SELECT l.*, p.name as product_name, p.description as product_description, p.price
    FROM `licenses` l
    JOIN `products` p ON l.product_id = p.id
    WHERE l.customer_id = ? AND p.category = 'AMPOS'
    ORDER BY l.created_at DESC
");
$stmt_licenses->execute([$customer_id]);
$ampos_licenses = $stmt_licenses->fetchAll(PDO::FETCH_ASSOC);

// Fetch AMPOS orders
$stmt_orders = $pdo->prepare("
    SELECT o.*, oi.license_key_generated, p.name as product_name
    FROM `orders` o
    JOIN `order_items` oi ON o.id = oi.order_id
    JOIN `products` p ON oi.product_id = p.id
    WHERE o.customer_id = ? AND p.category = 'AMPOS'
    ORDER BY o.order_date DESC
");
$stmt_orders->execute([$customer_id]);
$ampos_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_licenses = count($ampos_licenses);
$active_licenses = count(array_filter($ampos_licenses, fn($l) => $l['status'] === 'active'));
$total_devices = array_sum(array_column($ampos_licenses, 'current_devices'));
$pending_orders = count(array_filter($ampos_orders, fn($o) => $o['status'] === 'pending_approval'));

portal_header("AMPOS Licenses - IT Support BD Portal");
?>

<style>
.ampos-hero {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.stat-card {
    background: rgba(17, 24, 39, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(59, 130, 246, 0.2);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(59, 130, 246, 0.4);
    box-shadow: 0 10px 30px rgba(59, 130, 246, 0.2);
}

.license-card {
    background: rgba(17, 24, 39, 0.8);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(75, 85, 99, 0.5);
    transition: all 0.3s ease;
}

.license-card:hover {
    border-color: rgba(59, 130, 246, 0.5);
}

.license-card.active {
    border-color: rgba(34, 197, 94, 0.5);
    background: rgba(17, 24, 39, 0.9);
}

.license-card.inactive {
    border-color: rgba(239, 68, 68, 0.5);
    opacity: 0.8;
}

.pricing-card {
    background: rgba(17, 24, 39, 0.7);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(75, 85, 99, 0.3);
    transition: all 0.3s ease;
}

.pricing-card:hover {
    transform: translateY(-8px);
    border-color: rgba(59, 130, 246, 0.6);
    box-shadow: 0 20px 40px rgba(59, 130, 246, 0.3);
}

.pricing-card.featured {
    border-color: rgba(139, 92, 246, 0.6);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
}

.tab-button {
    padding: 0.75rem 1.5rem;
    background: rgba(31, 41, 55, 0.5);
    border: 1px solid rgba(75, 85, 99, 0.3);
    color: #9CA3AF;
    transition: all 0.3s ease;
}

.tab-button:hover {
    background: rgba(59, 130, 246, 0.1);
    color: #60A5FA;
}

.tab-button.active {
    background: rgba(59, 130, 246, 0.2);
    border-color: rgba(59, 130, 246, 0.5);
    color: #60A5FA;
}

.progress-bar {
    height: 8px;
    background: rgba(75, 85, 99, 0.3);
    border-radius: 9999px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3B82F6, #8B5CF6);
    transition: width 0.3s ease;
}
</style>

<!-- Hero Section -->
<div class="ampos-hero glass-card mb-8 p-8 rounded-xl">
    <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                    <i class="fas fa-shield-alt text-3xl text-white"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-white">AMPOS Licenses</h1>
                    <p class="text-gray-300">Manage your Advanced Monitoring & Protection OS licenses</p>
                </div>
            </div>
        </div>
        <div class="flex gap-3">
            <button onclick="scrollToSection('purchase')" class="btn-glass-primary">
                <i class="fas fa-shopping-cart mr-2"></i>Purchase License
            </button>
            <a href="support.php" class="btn-glass-secondary">
                <i class="fas fa-question-circle mr-2"></i>Get Support
            </a>
        </div>
    </div>
</div>

<?= $message ?>
<?= $error ?>

<!-- Statistics Dashboard -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="stat-card p-6 rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-certificate text-2xl text-blue-400"></i>
            </div>
            <span class="text-3xl font-bold text-white"><?= $total_licenses ?></span>
        </div>
        <p class="text-gray-300 font-medium">Total Licenses</p>
        <p class="text-sm text-gray-400 mt-1">All your AMPOS licenses</p>
    </div>

    <div class="stat-card p-6 rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-check-circle text-2xl text-green-400"></i>
            </div>
            <span class="text-3xl font-bold text-white"><?= $active_licenses ?></span>
        </div>
        <p class="text-gray-300 font-medium">Active Licenses</p>
        <p class="text-sm text-gray-400 mt-1">Currently operational</p>
    </div>

    <div class="stat-card p-6 rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-server text-2xl text-purple-400"></i>
            </div>
            <span class="text-3xl font-bold text-white"><?= $total_devices ?></span>
        </div>
        <p class="text-gray-300 font-medium">Monitored Devices</p>
        <p class="text-sm text-gray-400 mt-1">Across all licenses</p>
    </div>

    <div class="stat-card p-6 rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-clock text-2xl text-yellow-400"></i>
            </div>
            <span class="text-3xl font-bold text-white"><?= $pending_orders ?></span>
        </div>
        <p class="text-gray-300 font-medium">Pending Orders</p>
        <p class="text-sm text-gray-400 mt-1">Awaiting approval</p>
    </div>
</div>

<!-- Tab Navigation -->
<div class="flex gap-2 mb-6 overflow-x-auto">
    <button class="tab-button active rounded-lg" data-tab="licenses" onclick="switchTab('licenses')">
        <i class="fas fa-key mr-2"></i>My Licenses
    </button>
    <button class="tab-button rounded-lg" data-tab="purchase" onclick="switchTab('purchase')">
        <i class="fas fa-shopping-cart mr-2"></i>Purchase New
    </button>
    <button class="tab-button rounded-lg" data-tab="orders" onclick="switchTab('orders')">
        <i class="fas fa-receipt mr-2"></i>Order History
    </button>
</div>

<!-- Licenses Tab -->
<div id="licenses-tab" class="tab-content">
    <div class="glass-card p-6 rounded-xl">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-white">
                <i class="fas fa-key mr-2 text-blue-400"></i>My AMPOS Licenses
            </h2>
            <?php if (!empty($ampos_licenses)): ?>
                <span class="px-4 py-2 bg-blue-500/20 text-blue-300 rounded-lg text-sm font-medium">
                    <?= count($ampos_licenses) ?> License<?= count($ampos_licenses) !== 1 ? 's' : '' ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (empty($ampos_licenses)): ?>
            <div class="text-center py-16">
                <div class="w-24 h-24 bg-gray-700/30 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-shield-alt text-5xl text-gray-500"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-300 mb-3">No AMPOS Licenses Yet</h3>
                <p class="text-gray-400 mb-6 max-w-md mx-auto">
                    You haven't purchased any AMPOS licenses. Get started by purchasing your first license!
                </p>
                <button onclick="switchTab('purchase')" class="btn-glass-primary">
                    <i class="fas fa-shopping-cart mr-2"></i>Browse License Plans
                </button>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($ampos_licenses as $license): ?>
                    <?php 
                        $is_active = $license['status'] === 'active';
                        $is_expired = strtotime($license['expires_at']) < time();
                        $days_remaining = max(0, floor((strtotime($license['expires_at']) - time()) / 86400));
                        $device_usage_percent = $license['max_devices'] > 0 ? ($license['current_devices'] / $license['max_devices']) * 100 : 0;
                    ?>
                    <div class="license-card <?= $is_active ? 'active' : 'inactive' ?> p-6 rounded-xl">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                            <!-- License Info -->
                            <div class="flex-1">
                                <div class="flex items-start justify-between mb-4">
                                    <div>
                                        <h3 class="text-xl font-bold text-white mb-1">
                                            <?= htmlspecialchars($license['product_name']) ?>
                                        </h3>
                                        <p class="text-gray-400 text-sm"><?= htmlspecialchars($license['product_description']) ?></p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= 
                                        $is_active ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'
                                    ?>">
                                        <?= htmlspecialchars(ucfirst($license['status'])) ?>
                                    </span>
                                </div>

                                <!-- License Key -->
                                <div class="bg-gray-900/50 p-4 rounded-lg mb-4 border border-gray-700">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <label class="text-xs text-gray-400 mb-1 block">License Key</label>
                                            <code id="license-key-<?= $license['id'] ?>" class="text-white font-mono text-sm break-all">
                                                <?= htmlspecialchars($license['license_key']) ?>
                                            </code>
                                        </div>
                                        <button 
                                            onclick="copyToClipboard('license-key-<?= $license['id'] ?>')" 
                                            class="ml-4 px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-blue-300 rounded-lg transition-colors text-sm"
                                        >
                                            <i class="fas fa-copy mr-1"></i>Copy
                                        </button>
                                    </div>
                                </div>

                                <!-- License Details Grid -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Max Devices</p>
                                        <p class="text-white font-semibold">
                                            <?= $license['max_devices'] == 99999 ? 'Unlimited' : htmlspecialchars($license['max_devices']) ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Current Usage</p>
                                        <p class="text-white font-semibold">
                                            <?= htmlspecialchars($license['current_devices']) ?> device<?= $license['current_devices'] !== 1 ? 's' : '' ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Issued Date</p>
                                        <p class="text-white font-semibold">
                                            <?= date('M d, Y', strtotime($license['issued_at'])) ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Expires</p>
                                        <p class="text-white font-semibold <?= $is_expired ? 'text-red-400' : '' ?>">
                                            <?= date('M d, Y', strtotime($license['expires_at'])) ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Device Usage Progress -->
                                <?php if ($license['max_devices'] < 99999): ?>
                                    <div class="mb-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm text-gray-400">Device Usage</span>
                                            <span class="text-sm text-gray-300">
                                                <?= round($device_usage_percent, 1) ?>%
                                            </span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= min(100, $device_usage_percent) ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Expiry Warning -->
                                <?php if ($is_active && $days_remaining <= 30 && $days_remaining > 0): ?>
                                    <div class="bg-yellow-500/10 border border-yellow-500/30 p-3 rounded-lg">
                                        <p class="text-yellow-300 text-sm">
                                            <i class="fas fa-exclamation-triangle mr-2"></i>
                                            License expires in <strong><?= $days_remaining ?> day<?= $days_remaining !== 1 ? 's' : '' ?></strong>. Consider renewing soon!
                                        </p>
                                    </div>
                                <?php elseif ($is_expired): ?>
                                    <div class="bg-red-500/10 border border-red-500/30 p-3 rounded-lg">
                                        <p class="text-red-300 text-sm">
                                            <i class="fas fa-times-circle mr-2"></i>
                                            This license has expired. Please renew to continue using AMPOS.
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col gap-3 lg:w-48">
                                <?php if ($is_active): ?>
                                    <a href="license_details.php?license_id=<?= htmlspecialchars($license['id']) ?>" 
                                       class="btn-glass-primary text-center">
                                        <i class="fas fa-download mr-2"></i>Download
                                    </a>
                                    <a href="license_setup.php?license_id=<?= htmlspecialchars($license['id']) ?>" 
                                       class="btn-glass-secondary text-center">
                                        <i class="fas fa-cog mr-2"></i>Setup Guide
                                    </a>
                                <?php endif; ?>
                                <a href="support.php?license=<?= htmlspecialchars($license['license_key']) ?>" 
                                   class="btn-glass-secondary text-center">
                                    <i class="fas fa-life-ring mr-2"></i>Support
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Purchase Tab -->
<div id="purchase-tab" class="tab-content hidden">
    <div class="glass-card p-6 rounded-xl mb-6">
        <h2 class="text-2xl font-bold text-white mb-2">
            <i class="fas fa-shopping-cart mr-2 text-blue-400"></i>Purchase AMPOS License
        </h2>
        <p class="text-gray-400">Choose the perfect AMPOS license plan for your monitoring needs</p>
    </div>

    <?php if (empty($ampos_products)): ?>
        <div class="glass-card p-16 rounded-xl text-center">
            <div class="w-24 h-24 bg-gray-700/30 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-box-open text-5xl text-gray-500"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-300 mb-3">No Products Available</h3>
            <p class="text-gray-400">AMPOS licenses are currently unavailable. Please check back later or contact support.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($ampos_products as $index => $product): ?>
                <?php $is_featured = $index === 1; // Middle tier featured ?>
                <div class="pricing-card <?= $is_featured ? 'featured' : '' ?> p-6 rounded-xl relative">
                    <?php if ($is_featured): ?>
                        <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                            <span class="px-4 py-1 bg-gradient-to-r from-blue-500 to-purple-600 text-white text-xs font-bold rounded-full">
                                RECOMMENDED
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-shield-alt text-3xl text-white"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-2">
                            <?= htmlspecialchars($product['name']) ?>
                        </h3>
                        <p class="text-gray-400 text-sm">
                            <?= htmlspecialchars($product['description']) ?>
                        </p>
                    </div>

                    <div class="text-center mb-6">
                        <div class="text-5xl font-bold text-white mb-2">
                            $<?= htmlspecialchars(number_format($product['price'], 2)) ?>
                        </div>
                        <p class="text-gray-400 text-sm">
                            <?= htmlspecialchars($product['license_duration_days'] / 365) ?> year license
                        </p>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <span>
                                <?= $product['max_devices'] == 99999 ? 'Unlimited devices' : htmlspecialchars($product['max_devices']) . ' device' . ($product['max_devices'] !== 1 ? 's' : '') ?>
                            </span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <span>Network monitoring</span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <span>Real-time alerts</span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <span>Docker deployment</span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <span>Email notifications</span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <span>Portal access</span>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                        <button type="submit" name="purchase_ampos" class="btn-glass-primary w-full">
                            <i class="fas fa-shopping-cart mr-2"></i>Purchase Now
                        </button>
                    </form>

                    <p class="text-xs text-gray-500 text-center mt-4">
                        <i class="fas fa-info-circle mr-1"></i>Requires admin approval
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="glass-card p-6 rounded-xl mt-8">
            <h3 class="text-xl font-bold text-white mb-4">
                <i class="fas fa-question-circle text-blue-400 mr-2"></i>Frequently Asked Questions
            </h3>
            <div class="space-y-4">
                <div>
                    <h4 class="font-semibold text-gray-200 mb-2">What happens after I purchase?</h4>
                    <p class="text-gray-400 text-sm">Your order will be placed in pending status. Once an administrator approves your payment, your license will be activated and you'll receive setup instructions via email.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-200 mb-2">Can I upgrade my license later?</h4>
                    <p class="text-gray-400 text-sm">Yes! Contact support to upgrade your license plan. The price difference will be calculated based on your current license term.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-200 mb-2">What if I need more devices?</h4>
                    <p class="text-gray-400 text-sm">You can either upgrade to a higher tier or purchase an additional license. Each license operates independently.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Orders Tab -->
<div id="orders-tab" class="tab-content hidden">
    <div class="glass-card p-6 rounded-xl">
        <h2 class="text-2xl font-bold text-white mb-6">
            <i class="fas fa-receipt mr-2 text-blue-400"></i>AMPOS Order History
        </h2>

        <?php if (empty($ampos_orders)): ?>
            <div class="text-center py-16">
                <div class="w-24 h-24 bg-gray-700/30 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-receipt text-5xl text-gray-500"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-300 mb-3">No Orders Yet</h3>
                <p class="text-gray-400 mb-6">You haven't placed any AMPOS orders.</p>
                <button onclick="switchTab('purchase')" class="btn-glass-primary">
                    <i class="fas fa-shopping-cart mr-2"></i>Purchase Your First License
                </button>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($ampos_orders as $order): ?>
                    <?php
                        $status_colors = [
                            'completed' => 'bg-green-500/20 text-green-300 border-green-500/30',
                            'pending_approval' => 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
                            'failed' => 'bg-red-500/20 text-red-300 border-red-500/30',
                            'cancelled' => 'bg-gray-500/20 text-gray-300 border-gray-500/30'
                        ];
                        $status_color = $status_colors[$order['status']] ?? 'bg-gray-500/20 text-gray-300 border-gray-500/30';
                    ?>
                    <div class="license-card p-6 rounded-xl">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-4 mb-3">
                                    <h3 class="text-xl font-bold text-white">
                                        Order #<?= htmlspecialchars($order['id']) ?>
                                    </h3>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold border <?= $status_color ?>">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $order['status']))) ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3">
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Product</p>
                                        <p class="text-white font-medium"><?= htmlspecialchars($order['product_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Amount</p>
                                        <p class="text-white font-medium">$<?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Order Date</p>
                                        <p class="text-white font-medium"><?= date('M d, Y', strtotime($order['order_date'])) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 mb-1">Time</p>
                                        <p class="text-white font-medium"><?= date('h:i A', strtotime($order['order_date'])) ?></p>
                                    </div>
                                </div>

                                <?php if (!empty($order['license_key_generated'])): ?>
                                    <div class="bg-gray-900/50 p-3 rounded-lg border border-gray-700">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <label class="text-xs text-gray-400 mb-1 block">Generated License Key</label>
                                                <code class="text-white font-mono text-sm">
                                                    <?= htmlspecialchars($order['license_key_generated']) ?>
                                                </code>
                                            </div>
                                            <button 
                                                onclick="copyToClipboard('order-key-<?= $order['id'] ?>')" 
                                                class="px-3 py-1 bg-blue-500/20 hover:bg-blue-500/30 text-blue-300 rounded text-sm"
                                                id="order-key-<?= $order['id'] ?>" 
                                                data-key="<?= htmlspecialchars($order['license_key_generated']) ?>"
                                            >
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($order['status'] === 'pending_approval'): ?>
                                    <div class="bg-yellow-500/10 border border-yellow-500/30 p-3 rounded-lg mt-3">
                                        <p class="text-yellow-300 text-sm">
                                            <i class="fas fa-clock mr-2"></i>
                                            This order is awaiting admin approval. You'll receive an email once approved.
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex flex-col gap-2 lg:w-40">
                                <?php if ($order['status'] === 'completed'): ?>
                                    <button onclick="switchTab('licenses')" class="btn-glass-secondary text-sm">
                                        <i class="fas fa-eye mr-2"></i>View License
                                    </button>
                                <?php endif; ?>
                                <a href="support.php?order=<?= htmlspecialchars($order['id']) ?>" class="btn-glass-secondary text-sm text-center">
                                    <i class="fas fa-question-circle mr-2"></i>Support
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });

    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });

    // Show selected tab
    document.getElementById(tabName + '-tab').classList.remove('hidden');

    // Add active class to selected button
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Scroll to top of page smoothly
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollToSection(sectionId) {
    switchTab(sectionId);
}

function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    let text;
    
    // Check if element has data-key attribute (for order keys)
    if (element.hasAttribute('data-key')) {
        text = element.getAttribute('data-key');
    } else {
        text = element.textContent.trim();
    }
    
    navigator.clipboard.writeText(text).then(() => {
        // Visual feedback
        const originalHTML = element.innerHTML;
        element.innerHTML = '<i class="fas fa-check"></i> Copied!';
        element.classList.add('bg-green-500/30');
        
        setTimeout(() => {
            element.innerHTML = originalHTML;
            element.classList.remove('bg-green-500/30');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy to clipboard');
    });
}

// Auto-switch to purchase tab if hash is present
if (window.location.hash === '#purchase') {
    switchTab('purchase');
}
</script>

<?php portal_footer(); ?>