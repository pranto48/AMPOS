<?php
/**
 * AMPOS License API
 * Provides endpoints for license management operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/license_manager.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_license_info':
        getLicenseInfo();
        break;
    case 'update_license_key':
        updateLicenseKey();
        break;
    case 'force_recheck':
        forceRecheck();
        break;
    case 'get_installation_id':
        getInstallationIdAction();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getLicenseInfo() {
    echo json_encode([
        'success' => true,
        'data' => [
            'status_code' => $_SESSION['license_status_code'] ?? 'unknown',
            'message' => $_SESSION['license_message'] ?? 'Unknown status',
            'tier' => $_SESSION['license_tier'] ?? 'basic',
            'product_name' => $_SESSION['license_product_name'] ?? 'AMPOS Basic',
            'max_products' => $_SESSION['license_max_products'] ?? 100,
            'max_users' => $_SESSION['license_max_users'] ?? 1,
            'features' => $_SESSION['license_features'] ?? ['basic_pos', 'reports'],
            'expires_at' => $_SESSION['license_expires_at'] ?? null,
            'grace_period_end' => $_SESSION['license_grace_period_end'] ?? null,
            'last_verified' => $_SESSION['license_last_verified'] ?? null,
            'installation_id' => getInstallationId(),
            'has_license_key' => !empty(getAppLicenseKey())
        ]
    ]);
}

function updateLicenseKey() {
    // Require authentication for license key updates
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $new_license_key = trim($input['license_key'] ?? '');

    if (empty($new_license_key)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'License key is required']);
        return;
    }

    // Validate the new license key with the portal
    $installation_id = getInstallationId();
    $post_data = [
        'app_license_key' => $new_license_key,
        'user_id' => $_SESSION['user_id'],
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

    $encrypted_response = @file_get_contents(LICENSE_API_URL, false, $context);

    if ($encrypted_response === false) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Could not connect to license server']);
        return;
    }

    $licenseData = decryptLicenseData($encrypted_response);

    if ($licenseData === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Invalid response from license server']);
        return;
    }

    if (isset($licenseData['success']) && $licenseData['success'] === true) {
        if (setAppLicenseKey($new_license_key)) {
            // Force re-verification
            $_SESSION['license_last_verified'] = 0;
            verifyLicenseWithPortal();
            
            echo json_encode([
                'success' => true,
                'message' => 'License key updated successfully',
                'tier' => $licenseData['tier'] ?? 'basic',
                'product_name' => $licenseData['product_name'] ?? 'AMPOS Basic'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save license key']);
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $licenseData['message'] ?? 'Invalid license key'
        ]);
    }
}

function forceRecheck() {
    // Require authentication for force recheck
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    // Reset last verified time to force immediate recheck
    $_SESSION['license_last_verified'] = 0;
    verifyLicenseWithPortal();

    echo json_encode([
        'success' => true,
        'message' => 'License recheck completed',
        'data' => [
            'status_code' => $_SESSION['license_status_code'] ?? 'unknown',
            'message' => $_SESSION['license_message'] ?? 'Unknown status',
            'tier' => $_SESSION['license_tier'] ?? 'basic',
            'last_verified' => $_SESSION['license_last_verified'] ?? null
        ]
    ]);
}

function getInstallationIdAction() {
    echo json_encode([
        'success' => true,
        'installation_id' => getInstallationId()
    ]);
}
?>
