<?php
/**
 * ==============================================================================
 * admin/pos/ajax/operator_events.php — High-Frequency Cashier Operations Logging
 * ==============================================================================
 * Reference: Tomasz Antczak & Rafał Weron (2019)
 *   "Point of Sale (POS) Data from a Supermarket: Transactions and Cashier Operations"
 *   (MDPI Data 2019, 4, 67, Table 2: OperatorSignOn, OperatorSignOff, OperatorLock, OperatorUnLock)
 * ==============================================================================
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../public/dbconnect.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';
require_once __DIR__ . '/../../includes/pos_lib.php';

if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!has_admin_permission('pos.sale') && !has_admin_permission('pos.access') && !has_admin_permission('pos.manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

if (!method_is('post')) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = trim((string) ($data['action'] ?? ''));
$csrfToken = (string) ($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

// CSRF check
if (!verify_csrf($csrfToken)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
    exit;
}

$admin = current_admin();
$adminId = (int) ($admin['id'] ?? 0);
$pdo = db();

try {
    $allowedEvents = ['OperatorSignOn', 'OperatorSignOff', 'OperatorLock', 'OperatorUnLock'];
    $eventMap = [
        'signon'  => 'OperatorSignOn',
        'signoff' => 'OperatorSignOff',
        'lock'    => 'OperatorLock',
        'unlock'  => 'OperatorUnLock',
    ];

    $eventType = $eventMap[strtolower($action)] ?? (in_array($action, $allowedEvents, true) ? $action : null);
    if ($eventType === null) {
        echo json_encode(['success' => false, 'error' => 'Invalid operator event action.']);
        exit;
    }

    // Special validation for Unlock: requires verifying the cashier password or manager credentials
    if ($eventType === 'OperatorUnLock') {
        $password = (string) ($data['password'] ?? '');
        if ($password === '') {
            echo json_encode(['success' => false, 'error' => 'Password is required to unlock register.']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT password FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($password, (string) $hash)) {
            // Also check if a manager authorized unlock
            $managerUser = trim((string) ($data['manager_username'] ?? ''));
            $managerPass = trim((string) ($data['manager_password'] ?? ''));
            $mgrOk = false;

            if ($managerUser !== '' && $managerPass !== '') {
                $stMgr = $pdo->prepare("
                    SELECT a.password, r.name as role_name 
                    FROM admins a 
                    JOIN admin_roles r ON r.id = a.role_id 
                    WHERE (a.username = ? OR a.email = ?) AND a.is_active = 1 
                    LIMIT 1
                ");
                $stMgr->execute([$managerUser, $managerUser]);
                $mgrRow = $stMgr->fetch(PDO::FETCH_ASSOC);
                if ($mgrRow && password_verify($managerPass, $mgrRow['password'])) {
                    $mgrOk = true;
                }
            }

            if (!$mgrOk) {
                echo json_encode(['success' => false, 'error' => 'Invalid password. Unable to unlock terminal.']);
                exit;
            }
        }
    }

    $workstationId = (int) ($data['workstation_id'] ?? 1);
    $tranId        = isset($data['tran_id']) ? substr((string) $data['tran_id'], 0, 100) : null;
    $metadata      = isset($data['metadata']) ? json_encode($data['metadata']) : null;

    $stmt = $pdo->prepare("
        INSERT INTO pos_operator_logs (operator_id, workstation_id, tran_id, event_type, event_time, metadata, created_at)
        VALUES (?, ?, ?, ?, NOW(), ?, NOW())
    ");
    $stmt->execute([$adminId, $workstationId, $tranId, $eventType, $metadata]);
    $eventId = (int) $pdo->lastInsertId();

    echo json_encode([
        'success'    => true,
        'event_id'   => $eventId,
        'event_type' => $eventType,
        'timestamp'  => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    error_log('[admin/pos/ajax/operator_events] error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error while logging operator event.']);
}
