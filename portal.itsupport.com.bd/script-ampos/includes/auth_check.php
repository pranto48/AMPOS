<?php
// Include the main bootstrap file which handles DB checks and starts the session.
require_once __DIR__ . '/bootstrap.php';
// Include config.php to make license-related functions available
require_once __DIR__ . '/../config.php';
// Include license_manager.php for license verification logic
require_once __DIR__ . '/license_manager.php';

// If the user is not logged in, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ensure user_role is set in session. If not, fetch it from DB
if (!isset($_SESSION['user_role'])) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['user_role'] = $user_data['role'] ?? 'cashier'; // Default to cashier if not found
}

// --- Role-based page access control ---
$current_page = basename($_SERVER['PHP_SELF']);
$admin_only_pages = ['users.php', 'settings.php', 'license_management.php', 'reports_advanced.php'];

if ($_SESSION['user_role'] !== 'admin' && in_array($current_page, $admin_only_pages)) {
    header('Location: index.php'); // Redirect non-admins from admin-only pages
    exit;
}

// --- License Validation ---
$license_status_code = $_SESSION['license_status_code'] ?? 'unknown';
$app_license_key = getAppLicenseKey();

// If license key is not configured, redirect to setup page
if (empty($app_license_key) && $current_page !== 'license_setup.php') {
    header('Location: license_setup.php');
    exit;
}

// If license is disabled (grace period over, revoked, etc.), redirect to license_expired.php
if ($license_status_code === 'disabled' && $current_page !== 'license_expired.php') {
    header('Location: license_expired.php');
    exit;
}

// --- Feature-based access control ---
// Check if current page requires specific features
$feature_requirements = [
    'inventory.php' => 'inventory',
    'multi_store.php' => 'multi_store',
    'api_settings.php' => 'api_access',
    'analytics.php' => 'advanced_analytics',
    'branding.php' => 'custom_branding'
];

if (isset($feature_requirements[$current_page])) {
    $required_feature = $feature_requirements[$current_page];
    if (!hasFeature($required_feature)) {
        $_SESSION['error_message'] = 'This feature requires a higher license tier. Please upgrade your license.';
        header('Location: index.php');
        exit;
    }
}

// For other statuses (active, grace_period, expired, invalid, portal_unreachable),
// the application remains accessible, and the header will display the appropriate message.
?>
