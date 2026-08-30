<?php
/**
 * licensing_server/api.php
 * 
 * Remote Licensing Verification REST API Endpoint.
 * Supports:
 * - POST /api.php?action=activate
 * - POST /api.php?action=verify
 * - POST /api.php?action=deactivate
 * - GET  /api.php?action=public_key
 */

declare(strict_types=1);

require_once __DIR__ . '/license_server.php';

use GroCo\Licensing\LicenseServer;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

try {
    $server = new LicenseServer();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // If request body is JSON, decode it
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true) ?: [];
    $data = array_merge($_POST, $jsonData);

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Enforce HTTP POST for state-changing operations
    if (in_array($action, ['activate', 'verify', 'deactivate'], true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'METHOD_NOT_ALLOWED',
            'message' => 'Action requires HTTP POST request.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    switch ($action) {
        case 'public_key':
            echo json_encode([
                'success' => true,
                'public_key' => $server->getPublicKey(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            break;

        case 'activate':
            $licenseKey = trim((string)($data['license_key'] ?? ''));
            $domain = trim((string)($data['domain'] ?? ''));
            $installationId = trim((string)($data['installation_id'] ?? ''));
            $nonce = trim((string)($data['nonce'] ?? ''));

            if (!$licenseKey || !$domain || !$installationId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'MISSING_PARAMETERS',
                    'message' => 'license_key, domain, and installation_id are required.',
                ]);
                exit;
            }

            $res = $server->activate($licenseKey, $domain, $installationId, $clientIp, $nonce);
            if (!$res['success']) {
                http_response_code(403);
            }
            echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            break;

        case 'verify':
            $licenseKey = trim((string)($data['license_key'] ?? ''));
            $installationId = trim((string)($data['installation_id'] ?? ''));
            $domain = trim((string)($data['domain'] ?? ''));
            $nonce = trim((string)($data['nonce'] ?? ''));

            if (!$licenseKey || !$installationId || !$domain) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'MISSING_PARAMETERS',
                    'message' => 'license_key, installation_id, and domain are required.',
                ]);
                exit;
            }

            $res = $server->verify($licenseKey, $installationId, $domain, $clientIp, $nonce);
            if (!$res['success']) {
                http_response_code(403);
            }
            echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            break;

        case 'deactivate':
            $licenseKey = trim((string)($data['license_key'] ?? ''));
            $installationId = trim((string)($data['installation_id'] ?? ''));

            if (!$licenseKey || !$installationId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'MISSING_PARAMETERS']);
                exit;
            }

            $res = $server->deactivate($licenseKey, $installationId);
            echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'UNKNOWN_ACTION',
                'message' => 'Action must be activate, verify, deactivate, or public_key.',
            ]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'SERVER_ERROR',
        'message' => $e->getMessage(),
    ]);
}
